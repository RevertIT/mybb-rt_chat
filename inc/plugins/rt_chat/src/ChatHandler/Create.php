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

class Create extends AbstractChatHandler
{
    private int $messageId;

    /**
     * Insert chat message handler
     *
     * @param int $uid User uid
     * @param string $message Message to be inserted
     * @param bool $overrideChecks Whether all data protection should be overridden or not.
     * @return array|bool
     */
    public function insertMessage(int $uid, string $message, bool $overrideChecks = false): array|bool
    {
        global $rt_cache;

        $message = trim_blank_chrs($message);

        if ($this->mybb->user['uid'] < 1)
        {
            $this->error($this->lang->rt_chat_not_logged_in);
        }
        if (!Core::can_view())
        {
            $this->error($this->lang->rt_chat_no_perms);
        }
        if (!Core::can_post() && !Core::can_moderate())
        {
            $this->lang->rt_chat_no_posts = $this->lang->sprintf($this->lang->rt_chat_no_posts, (int) $this->mybb->settings['rt_chat_minposts_chat'], $this->mybb->user['postnum']);
            $this->error($this->lang->rt_chat_no_posts);
        }
        if (Core::is_banned())
        {
            $this->error($this->lang->rt_chat_banned);
        }
        if (empty($message))
        {
            $this->error($this->lang->rt_chat_empty_msg);
        }
        if (isset($this->mybb->settings['rt_chat_msg_length']) && my_strlen($message) > (int) $this->mybb->settings['rt_chat_msg_length'])
        {
            $this->lang->rt_chat_too_long_msg = $this->lang->sprintf($this->lang->rt_chat_too_long_msg, my_strlen($message), $this->mybb->settings['rt_chat_msg_length']);
            $this->error($this->lang->rt_chat_too_long_msg);
        }

        if (!empty($this->getError()) && $overrideChecks === false)
        {
            return $this->getError();
        }

        $this->messageId = $this->db->insert_query('rtchat', [
            'uid' => $uid,
            'message' => $this->db->escape_string($message),
            'dateline' => TIME_NOW,
        ]);

        $rt_cache->delete(Core::get_plugin_info('prefix') . '_messages');

        return $this->renderTemplate(
            (int) $this->messageId,
            (int) $this->mybb->user['uid'],
            $this->db->escape_string($message),
            TIME_NOW
        );
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
    private function renderTemplate(int $messageId, int $uid, string $message, int $dateline): bool|array
    {
        if (empty($messageId))
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
        $row['username'] = isset($this->mybb->user['uid'], $this->mybb->user['username'], $this->mybb->user['usergroup'], $this->mybb->user['displaygroup']) ? build_profile_link(format_name($this->mybb->user['username'], $this->mybb->user['usergroup'], $this->mybb->user['displaygroup']), $this->mybb->user['uid']) : $this->lang->na;
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
}