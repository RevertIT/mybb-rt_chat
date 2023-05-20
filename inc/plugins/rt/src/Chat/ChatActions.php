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
    public string $actionMessage = '';

    /**
     * Action handler for banning users via chat message
     *
     * Example: (username, reason, time in minutes): /ban "username" "reason" 50
     *
     * @param string $message
     * @return bool
     */
    protected function banUser(string $message): bool
    {

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
                if (Core::is_banned((int) $user['uid']))
                {
                    $this->error($this->lang->rt_chat_moderate_user_already_banned);
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

                $banned_user_link = $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $user['uid'];
                $banned_by_user_link = $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $this->mybb->user['uid'];
                $this->lang->rt_chat_banned_message = $this->lang->sprintf($this->lang->rt_chat_banned_message, $banned_user_link, $ban[1], $ban[2], $ban[3], $banned_by_user_link, $this->mybb->user['username']);

                $this->setBannedUsers();

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
     * @return bool
     */
    protected function unbanUser(string $message): bool
    {

        if (preg_match('/^\/unban\s"([^"]+)"?$/i', $message, $unban))
        {
            if (isset($unban[1]))
            {
                $user = get_user_by_username($unban[1]);

                if (!isset($user['uid']))
                {
                    $this->error($this->lang->rt_chat_moderate_user_not_found);
                }

                if (!Core::is_banned((int) $user['uid']))
                {
                    $this->error($this->lang->rt_chat_moderate_user_not_banned);
                }

                if (!empty($this->getError()))
                {
                    return $this->getError();
                }

                $user_link = $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $user['uid'];
                $user_unbanned_by_link = $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $this->mybb->user['uid'];

                $this->db->delete_query('rtchat_bans', "uid = '{$this->db->escape_string($user['uid'])}'");
                $this->lang->rt_chat_unbanned_message = $this->lang->sprintf($this->lang->rt_chat_unbanned_message, $user_link, $unban[1], $user_unbanned_by_link, $this->mybb->user['username']);

                $this->setBannedUsers();

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
     * @return bool
     */
    protected function clearChat(string $message): bool
    {

        if (preg_match('/^\/clear$/i', $message, $clear))
        {
            if (isset($clear[0]))
            {
                $this->db->delete_query('rtchat');
                $user_link = $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $this->mybb->user['uid'];
                $this->lang->rt_chat_cleared_messages = $this->lang->sprintf($this->lang->rt_chat_cleared_messages, $user_link, $this->mybb->user['username']);

                $this->setCachedMessages();

                return true;
            }
        }

        return false;
    }

    /**
     * Action handler for checking if user is banned
     *
     * @param string $message
     * @return bool
     */
    protected function checkUser(string $message): bool
    {
        if (preg_match('/^\/check\s"([^"]+)"?$/i', $message, $check))
        {
            if (isset($check[1]))
            {
                $user = get_user_by_username($check[1]);

                switch (true)
                {
                    case !isset($user['uid']):
                        $this->actionMessage = $this->lang->sprintf($this->lang->rt_chat_check_user_not_found, $check[1]) . $this->lang->rt_chat_private_note;
                        break;
                    case Core::is_banned((int) $user['uid']):
                        $data = Core::show_banned_details((int) $user['uid']);
                        $ban_length = floor(($data['expires'] - TIME_NOW) / 60);
                        $reason = $data['reason'];
                        $this->actionMessage = $this->lang->sprintf($this->lang->rt_chat_check_user_ban, $check[1], $ban_length, $reason) . $this->lang->rt_chat_private_note;
                        break;
                    default:
                        $this->actionMessage = $this->lang->sprintf($this->lang->rt_chat_check_user, $check[1]) . $this->lang->rt_chat_private_note;
                        break;
                }

                return true;
            }
        }
        return false;
    }
}