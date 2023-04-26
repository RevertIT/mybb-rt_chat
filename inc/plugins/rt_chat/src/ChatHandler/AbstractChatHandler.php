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
     * @param string $message
     * @param int $dateline
     * @return bool|array
     */
    protected function renderTemplate(int $messageId, int $uid, string $message, int $dateline): bool|array
    {
        if (empty($messageId))
        {
            return false;
        }

        $user = get_user($uid);

        if (empty($user))
        {
            return false;
        }

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
        $row['original_message'] = base64_encode(htmlspecialchars_uni($message));
        $row['message'] = $this->parser->parse_message($message, $parser_options);
        $row['edit_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-edit" href="javascript:void(0);">'.$this->lang->rt_chat_edit.'</a>';
        $row['delete_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-delete" href="javascript:void(0);">'.$this->lang->rt_chat_delete.'</a>';

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
                SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar
                FROM ".TABLE_PREFIX."rtchat c
                LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = c.uid
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