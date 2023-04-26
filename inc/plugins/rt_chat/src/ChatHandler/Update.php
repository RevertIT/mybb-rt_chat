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

class Update extends AbstractChatHandler
{
    /**
     * Update message based on id
     *
     * @param int $messageId
     * @param string $message
     * @return bool|array
     */
    public function updateMessage(int $messageId, string $message): bool|array
    {
        global $plugins;

        $data = [
            'messageId' => $messageId,
            'message' => $message,
        ];
        $plugins->run_hooks('rt_chat_begin_message_update', $data);

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
        if (!Core::can_post() && !Core::can_moderate())
        {
            $this->lang->rt_chat_no_posts = $this->lang->sprintf($this->lang->rt_chat_no_posts, (int) $this->mybb->settings['rt_chat_minposts_chat'], $this->mybb->user['postnum']);
            $this->error($this->lang->rt_chat_no_posts);
        }

        $query = $this->db->simple_select('rtchat', 'uid, message, dateline', "id = '{$this->db->escape_string($messageId)}'");
        $row = $this->db->fetch_array($query);

        if (empty($row) || isset($row['uid']) && $row['uid'] !== $this->mybb->user['uid'] && !Core::can_moderate())
        {
            $this->error($this->lang->rt_chat_selected_message_not_found);
        }
        if (isset($row['message']) && $row['message'] === $message)
        {
            $this->error($this->lang->rt_chat_message_same);
        }

        if (!empty($this->getError()))
        {
            return $this->getError();
        }

        $this->db->update_query('rtchat', [
            'message' => $this->db->escape_string($message),
        ], "id = '{$this->db->escape_string($messageId)}'");

        $plugins->run_hooks('rt_chat_commit_message_update', $data);

        $this->setCachedMessages();

        return $this->renderTemplate(
            $messageId,
            (int) $this->mybb->user['uid'],
            $this->db->escape_string($message),
            (int) $row['dateline'],
        );
    }
}