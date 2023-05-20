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

use rt\Chat\ChatActions;
use rt\Chat\Core;

class Create extends ChatActions
{
    private int $messageId;

	/**
	 * Insert chat message handler
	 *
	 * @param int $uid User uid
	 * @param int $touid Message to be sent to specific user
	 * @param string $message Message to be inserted
	 * @param bool $overrideChecks Whether all data protection should be overridden or not.
	 * @return array|bool
	 */
    public function insertMessage(int $uid, int $touid = 0, string $message, bool $overrideChecks = false): array|bool
    {
        global $plugins;

        $message = trim_blank_chrs($message);

        $data = [
            'uid' => $uid,
            'message' => $message,
            'overrideChecks' => $overrideChecks,
        ];
        $plugins->run_hooks('rt_chat_begin_message_insert', $data);

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
        if (empty($message))
        {
            $this->error($this->lang->rt_chat_empty_msg);
        }
        if (isset($this->mybb->settings['rt_chat_msg_length']) && my_strlen($message) > (int) $this->mybb->settings['rt_chat_msg_length'])
        {
            $this->lang->rt_chat_too_long_msg = $this->lang->sprintf($this->lang->rt_chat_too_long_msg, my_strlen($message), $this->mybb->settings['rt_chat_msg_length']);
            $this->error($this->lang->rt_chat_too_long_msg);
        }
		if (!empty($touid))
		{
			if (!Core::can_send_whisper())
			{
				$this->error($this->lang->rt_chat_whisper_no_permission);
			}
			if (empty(get_user($touid)))
			{
				$this->error($this->lang->rt_chat_whisper_user_not_found);
			}
			if ($touid === $uid)
			{
				$this->error($this->lang->rt_chat_whisper_same_user);
			}
		}

        // Anti-flood protection
        $current_messages = $this->getCachedMessages();

        if (!empty($current_messages) &&
            isset($this->mybb->settings['rt_chat_anti_flood']) && (int) $this->mybb->settings['rt_chat_anti_flood'] > 0 &&
            !Core::can_moderate()
        )
        {
            foreach ($current_messages as $key => $row)
            {
                if ($uid === (int) $row['uid'])
                {
                    if ($key === array_key_first($current_messages))
                    {
                        if (TIME_NOW - $row['dateline'] < (int) $this->mybb->settings['rt_chat_anti_flood'])
                        {
                            $this->error($this->lang->sprintf($this->lang->rt_chat_anti_flood, (int) $this->mybb->settings['rt_chat_anti_flood']));
                        }
                    }
                }
            }
        }

        // Moderator actions
        if (Core::can_moderate())
        {
            switch (true)
            {
                case $this->banUser($message):
                    $uid = (int) $this->mybb->settings['rt_chat_bot_id'];
                    $message = $this->lang->rt_chat_banned_message;
                    break;
                case $this->unbanUser($message):
                    $uid = (int) $this->mybb->settings['rt_chat_bot_id'];
                    $message = $this->lang->rt_chat_unbanned_message;
                    break;
                case $this->clearChat($message):
                    $uid = (int) $this->mybb->settings['rt_chat_bot_id'];
                    $message = $this->lang->rt_chat_cleared_messages;
                    break;
                case $this->checkUser($message):
                    $uid = (int) $this->mybb->settings['rt_chat_bot_id'];
                    $message = $this->actionMessage;
                    return $this->renderTemplate(1, $uid, 0, $message, TIME_NOW); // Mock up message visible only to user which called the command.
            }
        }

        if (!empty($this->getError()) && $overrideChecks === false)
        {
            return $this->getError();
        }

        $this->messageId = $this->db->insert_query('rtchat', [
            'uid' => $uid,
			'touid' => $touid,
            'message' => $this->db->escape_string($message),
            'dateline' => TIME_NOW,
        ]);

        $data['message_id'] = (int) $this->messageId;

        $plugins->run_hooks('rt_chat_commit_message_insert', $data);

        // Set new cached messages
        $this->setCachedMessages();

        // We return mockup of current inserted message
        return $this->renderTemplate(
            (int) $this->messageId,
			$uid,
			$touid,
            $message,
            TIME_NOW
        );
    }
}