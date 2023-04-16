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

class Create
{

    /**
     * Generate error function to ease errors handling
     *
     * @param string $error
     * @return array
     */
    private function error(string $error): array
    {
        return [
            'status' => false,
            'error' => $error,
        ];
    }

    /**
     * Insert chat message handler
     *
     * @param string $message
     * @return mixed
     */
    public function insertMessage(string $message): mixed
    {
        global $mybb, $db, $rt_cache, $lang;

        $lang->load(Core::get_plugin_info('prefix'));

        $message = trim_blank_chrs($message);

        if (!Core::can_view())
        {
            return $this->error($lang->rt_chat_no_perms);
        }
        if (!Core::can_post() && !Core::can_moderate())
        {
            $lang->rt_chat_no_posts = $lang->sprintf($lang->rt_chat_no_posts, (int) $mybb->settings['rt_chat_minposts_chat'], $mybb->user['postnum']);
            return $this->error($lang->rt_chat_no_posts);
        }
        if (empty($message))
        {
            return $this->error($lang->rt_chat_empty_msg);
        }
        if (isset($mybb->settings['rt_chat_msg_length']) && my_strlen($message) > (int) $mybb->settings['rt_chat_msg_length'])
        {
            $lang->rt_chat_too_long_msg = $lang->sprintf($lang->rt_chat_too_long_msg, my_strlen($message), $mybb->settings['rt_chat_msg_length']);
            return $this->error($lang->rt_chat_too_long_msg);
        }

        $db->insert_query('rtchat', [
            'uid' => (int) $mybb->user['uid'],
            'message' => $db->escape_string($message),
            'dateline' => TIME_NOW,
        ]);

        $rt_cache->delete(Core::get_plugin_info('prefix') . '_messages');

        return (new Read())->getMessages();
    }
}