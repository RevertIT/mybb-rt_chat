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

namespace rt\Chat;

use rt\Chat\ChatHandler\AbstractChatHandler;
use rt\Chat\Core;

class ChatActions extends AbstractChatHandler
{
    /**
     * Action handler for banning users via chat message
     *
     * Example: (username, reason, time in minutes): /ban "username" "reason" 50
     *
     * @param string $message
     * @return array|bool
     */
    protected function banUser(string $message): array|bool
    {
        global $rt_cache;

        if (preg_match('/^\/ban\s"([^"]+)"\s"([^"]+)"\s(\d+)?$/i', $message, $ban))
        {
            if (isset($ban[1], $ban[2], $ban[2]))
            {
                $user = get_user_by_username($ban[1]);
                $reason = $ban[2];
                $expire_time_minutes = TIME_NOW + (int) $ban[3] * 60;

                // Ban time too short
                if ($ban[3] < 5)
                {
                    $this->error($this->lang->rt_chat_ban_time_short);
                }
                if (!isset($user['uid']))
                {
                    $this->error($this->lang->rt_chat_moderate_user_not_found);
                }
                if ($user['uid'] === $this->mybb->user['uid'])
                {
                    $this->error($this->lang->rt_chat_ban_user_same_id);
                }

                if (!empty($this->getError()))
                {
                    return $this->getError();
                }

                $this->db->replace_query('rtchat_bans', [
                    'uid' => (int) $user['uid'],
                    'reason' => $this->db->escape_string($reason),
                    'dateline' => TIME_NOW,
                    'expires' => $expire_time_minutes,
                ]);

                $rt_cache->query('')->delete('rt_chat_bacheck');
                $rt_cache->query("SELECT uid FROM ".TABLE_PREFIX."rtchat_bans")->cache('rt_chat_bacheck', 604800);
                $this->lang->rt_chat_banned_message = $this->lang->sprintf($this->lang->rt_chat_banned_message, $ban[1], $ban[2], $ban[3], $this->mybb->user['username']);

                return true;
            }
        }
        return false;
    }

    /**
     * Action handler for unbanning users via chat message
     *
     * Example: (username): /unban "username"
     *
     * @param string $message
     * @return array|bool
     */
    protected function unbanUser(string $message): array|bool
    {
        global $rt_cache;

        if (preg_match('/^\/unban\s"([^"]+)"?$/i', $message, $unban))
        {
            if (isset($unban[1]))
            {
                $user = get_user_by_username($unban[1]);

                if (!isset($user['uid']))
                {
                    $this->error($this->lang->rt_chat_moderate_user_not_found);
                }

                if (!empty($this->getError()))
                {
                    return $this->getError();
                }

                $this->db->delete_query('rtchat_bans', "uid = '{$this->db->escape_string($user['uid'])}'");
                $rt_cache->query('')->delete('rt_chat_bacheck');
                $rt_cache->query("SELECT uid FROM ".TABLE_PREFIX."rtchat_bans")->cache('rt_chat_bacheck', 604800);
                $this->lang->rt_chat_unbanned_message = $this->lang->sprintf($this->lang->rt_chat_unbanned_message, $unban[1], $this->mybb->user['username']);

                return true;
            }
        }

        return false;
    }

    /**
     * Action handler for clearing chat via chat message
     *
     * Example: (command): /clear
     *
     * @param string $message
     * @return array|bool
     */
    protected function clearChat(string $message): array|bool
    {
        global $rt_cache;

        if (preg_match('/^\/clear$/i', $message, $clear))
        {
            if (isset($clear[0]))
            {
                $this->db->delete_query('rtchat');
                $rt_cache->delete('rt_chat_messages');
                $this->lang->rt_chat_cleared_messages = $this->lang->sprintf($this->lang->rt_chat_cleared_messages, $this->mybb->user['username']);
                return true;
            }
        }

        return true;
    }
}