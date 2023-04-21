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

class Delete extends AbstractChatHandler
{
    public function deleteMessage(int $messageId): array|bool
    {
        global $rt_cache, $plugins;

        $plugins->run_hooks('rt_chat_begin_message_delete', $messageId);

        if ($this->mybb->user['uid'] < 1)
        {
            $this->error($this->lang->rt_chat_not_logged_in);
        }
        if (!Core::can_view())
        {
            $this->error($this->lang->rt_chat_no_perms);
        }
        if (Core::is_banned())
        {
            $this->error($this->lang->rt_chat_banned);
        }

        $query = $this->db->simple_select('rtchat', 'uid', "id = '{$this->db->escape_string($messageId)}'");
        $row = $this->db->fetch_field($query, 'uid');

        if (empty($row) || isset($row['uid']) && $row['uid'] !== $this->mybb->user['uid'] && !Core::can_moderate())
        {
            $this->error($this->lang->rt_chat_selected_message_not_found);
        }

        if (!empty($this->getError()))
        {
            return $this->getError();
        }

        $this->db->delete_query('rtchat', "id = '{$this->db->escape_string($messageId)}'");

        $plugins->run_hooks('rt_chat_commit_message_delete', $messageId);

        $rt_cache->delete(Core::get_plugin_info('prefix') . '_messages');

        return [
            'status' => true,
        ];
    }
}