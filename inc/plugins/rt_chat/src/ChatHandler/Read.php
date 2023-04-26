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

class Read extends AbstractChatHandler
{
    private mixed $messages;

    public function __construct()
    {
        parent::__construct();

        $this->messages = null;

        if (!Core::can_view())
        {
            $this->error($this->lang->rt_chat_no_perms);
        }
    }

    /**
     * Get cached list of messages
     *
     * @param array $loadedMessages
     * @return mixed
     */
    public function getMessages(array $loadedMessages = []): mixed
    {
        global $plugins;

        $plugins->run_hooks('rt_chat_begin_message_view', $loadedMessages);

        if ($this->getError())
        {
            return $this->getError();
        }

        // Get current cache
        $cached_messages = $this->getCachedMessages();

        // No messages found in cache try to cache it
        if (empty($cached_messages))
        {
            $this->setCachedMessages();
            $cached_messages = $this->getCachedMessages();
        }

        // Work with the cache and setup message response
        if (!empty($cached_messages))
        {
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

            $messages = $data = [];
            foreach ($cached_messages as $key => $row)
            {
                // Find message first/last iterations
                if ($key === 0)
                {
                    $data['first'] = $row['id'];
                }
                $data['last'] = $row['id'];
                $data['loaded'][] = $row['id'];

                $row['edit_message'] = $row['delete_message'] = '';
                if (Core::can_moderate() || $row['uid'] === $this->mybb->user['uid'])
                {
                    $row['edit_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-edit" href="javascript:void(0);">'.$this->lang->rt_chat_edit.'</a>';
                    $row['delete_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-delete" href="javascript:void(0);">'.$this->lang->rt_chat_delete.'</a>';
                }

                $row['dateline'] = $row['dateline'] ?? TIME_NOW;
                $row['avatar'] = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : "{$this->mybb->settings['bburl']}/images/default_avatar.png";
                $row['username'] = isset($row['uid'], $row['username'], $row['usergroup'], $row['displaygroup']) ? build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['uid']) : $this->lang->na;
                $row['original_message'] = isset($row['message']) ? base64_encode(htmlspecialchars_uni($row['message'])) : null;
                $row['message'] = isset($row['message']) ? $this->parser->parse_message($row['message'], $parser_options) : null;

                eval("\$message = \"".\rt\Chat\template('chat_message', true)."\";");
                $messages[] = [
                    'id' => $row['id'],
                    'uid' => $row['uid'],
                    'dateline' => $row['dateline'],
                    'html' => $message,
                ];
            }

            // Arrange array
            $this->messages = [
                'status' => true,
                'messages' => array_reverse($messages),
                'data' => $data,
            ];
        }

        $plugins->run_hooks('rt_chat_end_message_view', $this->messages);

        return $this->messages;
    }

    /**
     * Get messages before certain $mid
     *
     * @param int $messageId
     * @return array
     */
    public function getMessageBeforeId(int $messageId): array
    {
        global $plugins;

        $plugins->run_hooks('rt_chat_begin_message_view_with_id', $messageId);

        if (!Core::can_view_history())
        {
            $this->error($this->lang->rt_chat_no_perms_history);
        }

        if ($this->getError())
        {
            return $this->getError();
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

        // Get current cache
        $query = $this->db->write_query("
            SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar
            FROM ".TABLE_PREFIX."rtchat c
            LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = c.uid
            WHERE c.id < {$messageId}
            ORDER BY c.id DESC
            LIMIT {$this->mybb->settings['rt_chat_total_messages']}
        ");

        $data = $messages = [];
        foreach ($query as $key => $row)
        {
            // Find message first/last iterations
            if ($key === 0)
            {
                $data['first'] = $row['id'];
            }
            $data['last'] = $row['id'];
            $data['loaded'][] = $row['id'];

            $row['edit_message'] = $row['delete_message'] = '';
            if (Core::can_moderate() || $row['uid'] === $this->mybb->user['uid'])
            {
                $row['edit_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-edit" href="javascript:void(0);">'.$this->lang->rt_chat_edit.'</a>';
                $row['delete_message'] = '<a id="'.$row['id'].'" class="'.Core::get_plugin_info('prefix').'-delete" href="javascript:void(0);">'.$this->lang->rt_chat_delete.'</a>';
            }

            $row['dateline'] = $row['dateline'] ?? TIME_NOW;
            $row['avatar'] = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : "{$this->mybb->settings['bburl']}/images/default_avatar.png";
            $row['username'] = isset($row['uid'], $row['username'], $row['usergroup'], $row['displaygroup']) ? build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['uid']) : $this->lang->na;
            $row['original_message'] = isset($row['message']) ? base64_encode(htmlspecialchars_uni($row['message'])) : null;
            $row['message'] = isset($row['message']) ? $this->parser->parse_message($row['message'], $parser_options) : null;

            eval("\$message = \"".\rt\Chat\template('chat_message', true)."\";");

            $messages[] = [
                'id' => $row['id'],
                'uid' => $row['uid'],
                'dateline' => $row['dateline'],
                'html' => $message,
            ];
        }

        // Arrange array
        $final_data = [
            'status' => true,
            'cached' => my_date('c', TIME_NOW),
            'messages' => $messages,
            'data' => $data,
        ];

        // Check if we have at least 1 message
        if (empty($messages))
        {
            $final_data['status'] = false;
            $final_data['error'] = $this->lang->rt_chat_no_messages_found;
            unset($final_data['data'], $final_data['messages']);
        }

        $plugins->run_hooks('rt_chat_end_message_view_with_id', $final_data);

        return $final_data;
    }
}