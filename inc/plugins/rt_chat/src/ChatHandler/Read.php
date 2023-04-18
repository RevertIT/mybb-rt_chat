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
     * @return mixed
     */
    public function getMessages(): mixed
    {
        global $rt_cache;

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

        if ($this->getError())
        {
            return $this->getError();
        }

        if (isset($this->mybb->settings['rt_chat_mycode_enabled']) && (int) $this->mybb->settings['rt_chat_mycode_enabled'] === 1)
        {
            $parser_options['allow_mycode'] = 1;
        }
        if (isset($this->mybb->settings['rt_chat_smilies_enabled']) && (int) $this->mybb->settings['rt_chat_smilies_enabled'] === 1)
        {
            $parser_options['allow_smilies'] = 1;
        }

        // Get current cache
        $this->messages = $rt_cache->get(Core::get_plugin_info('prefix') . '_messages');

        $messages = [];
        // No messages found in cache
        if (empty($this->messages['messages']))
        {
            // Query DB for latest data
            $query = $this->db->write_query("
                SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar
                FROM ".TABLE_PREFIX."rtchat c
                LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = c.uid
                ORDER BY c.id DESC
                LIMIT {$this->mybb->settings['rt_chat_total_messages']}
            ");

            $first = $last = 0;
            $data = [];
            foreach ($query as $key => $row)
            {
                if ($key === 0)
                {
                    $first = $row['id'];
                }
                $last = $row['id'];

                $row['dateline'] = $row['dateline'] ?? null;
                $row['date'] = isset($row['dateline']) ? my_date('relative', $row['dateline']) : null;
                $row['avatar'] = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : "{$this->mybb->settings['bburl']}/images/default_avatar.png";
                $row['username'] = isset($row['uid'], $row['username'], $row['usergroup'], $row['displaygroup']) ? build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['uid']) : $this->lang->na;
                $row['original_message'] = isset($row['message']) ? base64_encode(htmlspecialchars_uni($row['message'])) : null;
                $row['message'] = isset($row['message']) ? $this->parser->parse_message($row['message'], $parser_options) : null;

                eval("\$message = \"".\rt\Chat\template('chat_message', true)."\";");

                $data['first'] = $first;
                $data['last'] = $last;
                $data['loaded'][] = $row['id'];

                $messages[] = [
                    'id' => $row['id'],
                    'html' => $message,
                ];
            }

            // Arrange array
            $final_data = [
                'status' => true,
                'cached' => my_date('c', TIME_NOW),
                'messages' => array_reverse($messages),
                'data' => $data,
            ];

            // Check if we have at least 1 message
            if (empty($messages))
            {
                $final_data['status'] = false;
                $final_data['error'] = $this->lang->rt_chat_no_messages_found;
            }

            // Set new cache
            $rt_cache->set(Core::get_plugin_info('prefix') . '_messages', $final_data, 360);

            // Return new cache
            $this->messages = $rt_cache->get(Core::get_plugin_info('prefix') . '_messages');
        }

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
        global $rt_cache;

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
        $cached_query = $this->db->write_query("
            SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar
            FROM ".TABLE_PREFIX."rtchat c
            LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = c.uid
            WHERE c.id < {$messageId}
            ORDER BY c.id DESC
            LIMIT {$this->mybb->settings['rt_chat_total_messages']}
        ");

        $first = $last = 0;
        $data = [];
        $messages = [];
        foreach ($cached_query as $key => $row)
        {
            if ($key === 0)
            {
                $first = $row['id'];
            }
            $last = $row['id'];

            $row['dateline'] = $row['dateline'] ?? null;
            $row['date'] = isset($row['dateline']) ? my_date('relative', $row['dateline']) : null;
            $row['avatar'] = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : "{$this->mybb->settings['bburl']}/images/default_avatar.png";
            $row['username'] = isset($row['uid'], $row['username'], $row['usergroup'], $row['displaygroup']) ? build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['uid']) : $this->lang->na;
            $row['original_message'] = isset($row['message']) ? base64_encode(htmlspecialchars_uni($row['message'])) : null;
            $row['message'] = isset($row['message']) ? $this->parser->parse_message($row['message'], $parser_options) : null;

            eval("\$message = \"".\rt\Chat\template('chat_message', true)."\";");

            $data['first'] = $first;
            $data['last'] = $last;

            $messages[] = [
                'id' => $row['id'],
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

        return $final_data;
    }
}