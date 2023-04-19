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
}