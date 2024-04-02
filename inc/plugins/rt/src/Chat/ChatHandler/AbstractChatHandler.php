<?php
/**
 * RT Chat
 *
 * Is a plugin which adds MyBB Chat option, but instead of using Database for CRUD actions,
 * data is stored in cache, this plugin utilizes zero-database-query logic and provides data in the fastest way possible with minimal server resource usage,
 * its required to use in memory cache handlers such as redis or memcache(d)
 *
 * @package rt_chat
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

namespace rt\Chat\ChatHandler;

use rt\Chat\Core;

class AbstractChatHandler
{
    private string $errorMessage;
    private bool $errorStatus;
    protected \postParser $parser;
    protected \MyBB $mybb;
    protected \MyLanguage $lang;
    protected \DB_Base $db;

    public function __construct()
    {
        global $mybb, $lang, $db;

        // Get usual handlers
        $this->mybb = $mybb;
        $this->lang = $lang;
        $this->db = $db;

        $this->lang->load(Core::get_plugin_info('prefix'));

        $this->parser = new \postParser();

        if ($this->mybb->request_method !== 'post')
        {
            $this->error($this->lang->rt_chat_invalid_post_method);
        }
        if (!verify_post_check($this->mybb->get_input('my_post_key'), true))
        {
            $this->error($this->lang->invalid_post_code);
        }
    }

    /**
     * Generate error function to ease errors handling
     *
     * @param string $error
     * @return void
     */
    protected function error(string $error): void
    {
        $this->errorStatus = false;
        $this->errorMessage = $error;
    }

    /**
     * Get error data
     *
     * @return array|bool
     */
    protected function getError(): array|bool
    {
        if (empty($this->errorMessage))
        {
            return false;
        }

        return [
            'status' => $this->errorStatus,
            'error' => $this->errorMessage,
        ];
    }

	/**
	 * Generate a mockup to render latest message in the chat
	 *
	 * @param int $messageId
	 * @param int $uid
	 * @param int $touid
	 * @param string $message
	 * @param int $dateline
	 * @return bool|array
	 */
    protected function renderTemplate(int $messageId, int $uid, int $touid = 0, string $message, int $dateline): bool|array
    {
        $user = get_user($uid);

        // Parse bbcodes
        $parser_options = [
            "allow_html" => 0,
            "allow_mycode" => 0,
            "allow_smilies" => 0,
            "allow_imgcode" => 0,
            "allow_videocode" => 0,
            "filter_badwords" => 1,
            "filter_cdata" => 1
        ];

        if (isset($this->mybb->settings['rt_chat_mycode_enabled']) && (int) $this->mybb->settings['rt_chat_mycode_enabled'] === 1)
        {
            $parser_options['allow_mycode'] = 1;
        }
        if (isset($this->mybb->settings['rt_chat_smilies_enabled']) && (int) $this->mybb->settings['rt_chat_smilies_enabled'] === 1)
        {
            $parser_options['allow_smilies'] = 1;
        }

        $row = $messages = [];
        $row['id'] = $messageId;
        $row['dateline'] = $dateline;
        $row['date'] = my_date('relative', $dateline);
        $row['avatar'] = !empty($this->mybb->user['avatar']) ? htmlspecialchars_uni($this->mybb->user['avatar']) : "{$this->mybb->settings['bburl']}/images/default_avatar.png";
        $row['username'] = isset($user['uid'], $user['username'], $user['usergroup'], $user['displaygroup']) ? build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']) : $this->lang->na;
        $row['original_message'] = base64_encode($message);
        $row['message'] = $this->parser->parse_message($message, $parser_options);

		// Action bar
		$rt_chat_action_edit = $rt_chat_action_delete = $rt_chat_action_whisper = '';
		if (Core::can_moderate() || $uid === (int) $this->mybb->user['uid'])
		{
			eval("\$rt_chat_action_edit = \"".\rt\Chat\template('chat_action_edit', true)."\";");
			eval("\$rt_chat_action_delete = \"".\rt\Chat\template('chat_action_delete', true)."\";");
		}
		if (Core::can_post() && Core::can_send_whisper() && $uid !== (int) $this->mybb->user['uid'])
		{
			eval("\$rt_chat_action_whisper = \"".\rt\Chat\template('chat_action_whisper', true)."\";");
		}
		eval("\$rt_chat_actions = \"".\rt\Chat\template('chat_actions', true)."\";");

		// Whisper user
		$rt_chat_whisper = '';
		if ($touid > 0)
		{
			$to_user = get_user($touid);
			if (!empty($to_user))
			{
				$username = build_profile_link(format_name($to_user['username'], $to_user['usergroup'], $to_user['displaygroup']), $touid);
				eval("\$rt_chat_whisper = \"".\rt\Chat\template('chat_whisper_meta', true)."\";");
			}
			else
			{
				$touid = 0;
			}
		}

        eval("\$message = \"".\rt\Chat\template('chat_message', true)."\";");
        $messages[] =  [
            'id' => $messageId,
            'html' => $message,
        ];

        return [
            'status' => true,
            'messages' => $messages,
            'data' => [
                'last' => $messageId,
            ]
        ];
    }

    /**
     * Set cached messages from the database
     *
     * @return void
     */
    protected function setCachedMessages(): void
    {
        global $rt_cache;

        // Query DB for latest data
        $query = $this->db->write_query("
                SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar, t.username AS to_username, t.usergroup AS to_usergroup, t.displaygroup AS to_displaygroup
                FROM ".TABLE_PREFIX."rtchat c
                LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = c.uid
                LEFT JOIN ".TABLE_PREFIX."users t ON t.uid = c.touid
                ORDER BY c.id DESC
                LIMIT {$this->mybb->settings['rt_chat_total_messages']}
            ");

        $cached =  [];
        foreach ($query as $row)
        {
            $cached[] = $row;
        }

        // Set new cache
        $rt_cache->set(Core::get_plugin_info('prefix') . '_messages', $cached, 604800);
    }

    /**
     * Get cached messages from the cache
     *
     * @return array|null
     */
    protected function getCachedMessages(): ?array
    {
        global $rt_cache;

        return $rt_cache->get(Core::get_plugin_info('prefix') . '_messages');
    }

    /**
     * Set cached list of banned users from the database
     *
     * @return void
     */
    protected function setBannedUsers(): void
    {
        global $rt_cache;

        $query = $this->db->write_query("SELECT * FROM ".TABLE_PREFIX."rtchat_bans");

        $cached =  [];
        foreach ($query as $row)
        {
            $cached[] = $row;
        }

        // Set new cache
        $rt_cache->set(Core::get_plugin_info('prefix') . '_bans', $cached, 604800);
    }

    /**
     * Get cached list of banned users from the cache
     *
     * @return array|null
     */
    protected function getBannedUsers(): ?array
    {
        global $rt_cache;

        return $rt_cache->get(Core::get_plugin_info('prefix') . '_bans');
    }
}