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

namespace rt\Chat\Hooks;

use Exception;
use rt\Chat\Core;
use rt\Chat\ChatHandler\Create;
use rt\Chat\ChatHandler\Delete;
use rt\Chat\ChatHandler\Update;
use rt\Chat\ChatHandler\Read;


final class Frontend
{
    /**
     * Hook: global_start
     *
     * @return void
     * @throws Exception
     */
    public function global_start(): void
    {
        global $mybb;

        // Cache templates
        switch(\THIS_SCRIPT)
        {
            case 'index.php':
                \rt\Chat\load_templatelist(['chat']);
                break;
            case 'misc.php':
                if ($mybb->get_input('ext') === Core::get_plugin_info('prefix'))
                {
                    \rt\Chat\load_templatelist([
                        'chat_layout',
                        'chat',
                        'chat_statistics',
                        'chat_statistics_row'
                    ]);
                }
                break;
            case 'xmlhttp.php':
                if ($mybb->get_input('action') === Core::get_plugin_info('prefix'))
                {
                    \rt\Chat\load_templatelist([
                        'chat_message',
                        'chat_whisper_meta',
                        'chat_actions',
                        'chat_action_edit',
                        'chat_action_delete',
                        'chat_action_whisper'
                    ]);
                }
        };
    }

    /**
     * Hook: index_start
     *
     * @return void
     */
    public function index_start(): void
    {
        global $mybb, $lang, $rt_chat;

        if (Core::can_view())
        {
            $is_disabled = '';
            if ($mybb->user['uid'] < 1 || !Core::can_post() && !Core::can_moderate())
            {
                $is_disabled = ' disabled="disabled"';
            }
            $lang->load('rt_chat');
            eval('$rt_chat = "' . \rt\Chat\template('chat') . '";');
        }
    }

    /**
     * Hook: pre_output_page
     *
     * @param string $content
     * @return string
     * @throws Exception
     */
    public function pre_output_page(string $content): string
    {
        global $mybb;

        $head = Core::head_html_front();
        $content = str_replace('</head>', $head, $content);

        // $body = Core::body_html_front();
        // $content = str_replace('</body>', $body, $content);

        return $content;
    }

    /**
     * Hook: misc_start
     *
     * @return void
     */
    public function misc_start(): void
    {
        global $mybb, $lang, $header, $headerinclude, $footer, $theme, $rt_cache;

        if ($mybb->get_input('ext') === Core::get_plugin_info('prefix'))
        {
            // View chat layout
            $lang->load('rt_chat');

            // Top 5 chat posters
            if ($mybb->get_input('action') === 'statistics')
            {
                $top5 = $rt_cache->query("
                    SELECT
                        COUNT(message) AS total_messages,
                        c.uid,
                        u.username,
                        u.usergroup,
                        u.displaygroup
                    FROM
                        ".TABLE_PREFIX."rtchat c
                    LEFT JOIN ".TABLE_PREFIX."users u ON
                        u.uid = c.uid
                    GROUP BY
                        u.uid
                    ORDER BY
                        total_messages
                    DESC
                    LIMIT 10;
                    ")->cache('top_10_posters', 1800)->execute();

                // Placeholder when no messages found in chat
                if (empty($top5))
                {
                    $top5[] = [
                        'username' => $lang->na,
                        'total_messages' => 0
                    ];
                }

                $rt_chat_statistics_row = '';
                foreach ($top5 as $row)
                {
                    $row['username'] = isset($row['uid'], $row['username'], $row['usergroup'], $row['displaygroup']) ? build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['uid']) : $lang->na;
                    $row['total_messages'] = number_format((int) $row['total_messages']);

                    eval('$rt_chat_statistics_row .= "' . \rt\Chat\template('chat_statistics_row', true) . '";');
                }

                eval('$rt_chat_statistics = "' . \rt\Chat\template('chat_statistics',  true) . '";');
                output_page($rt_chat_statistics);
                exit;
            }

            if (Core::can_view())
            {
                $is_disabled = '';
                if ($mybb->user['uid'] < 1 || !Core::can_post() && !Core::can_moderate())
                {
                    $is_disabled = ' disabled="disabled"';
                }

                add_breadcrumb($lang->rt_chat_name, 'misc.php?ext=' . Core::get_plugin_info('prefix'));

                eval('$rt_chat = "' . \rt\Chat\template('chat') . '";');

                eval('$template = "' . \rt\Chat\template('chat_layout') . '";');
                output_page($template);
                exit;
            }
        }
    }

    /**
     * Hook: xmlhttp
     *
     * @return void
     */
    public function xmlhttp(): void
    {
        global $mybb, $lang;

        if ($mybb->get_input('ext') === Core::get_plugin_info('prefix'))
        {

            // View messages in chat
            if ($mybb->get_input('action') === 'load_messages' &&
                empty($mybb->get_input('before'))
            )
            {
                $messages = new Read();

                $loaded_messages = json_decode($mybb->get_input('loaded'));
                header('Content-type: application/json');
                echo json_encode($messages->getMessages($loaded_messages));
                exit;
            }

            // View chat history on scroll
            if ($mybb->get_input('action') === 'load_messages' && !empty($mybb->get_input('before', \MyBB::INPUT_INT)))
            {
                $messages = new Read();

                header('Content-type: application/json');
                echo json_encode($messages->getMessageBeforeId($mybb->get_input('before', \MyBB::INPUT_INT)));
                exit;
            }

            // Insert message
            if ($mybb->get_input('action') === 'insert_update_message' && empty($mybb->get_input('edit_id')))
            {
                $insert = new Create();
                $uid = (int) $mybb->user['uid'];
                $touid = $mybb->get_input('to_uid', \MyBB::INPUT_INT);

                $data = $insert->insertMessage($uid, $touid, $mybb->get_input('message'));

                header('Content-type: application/json');
                echo json_encode($data);
                exit;
            }

            // Update message
            if ($mybb->get_input('edit_id', \MyBB::INPUT_INT) !== 0)
            {
                $edit = new Update();

                $message_id = $mybb->get_input('edit_id', \MyBB::INPUT_INT);
                $message = $mybb->get_input('message');

                $data = $edit->updateMessage($message_id, $message);
                header('Content-type: application/json');
                echo json_encode($data);
                exit;
            }

            // Delete message
            if ($mybb->get_input('action') === 'delete_message')
            {
                $delete = new Delete();
                $message_id = (int) $mybb->get_input('message');

                $data = $delete->deleteMessage($message_id);

                header('Content-type: application/json');
                echo json_encode($data);
                exit;
            }

        }
    }

    /**
     * Hook: task_hourlycleanup
     *
     * @param $args
     * @return void
     */
    public function task_hourlycleanup(&$args): void
    {
        global $mybb, $db, $rt_cache;

        if (isset($mybb->settings['rt_chat_clear_after']) && (int) $mybb->settings['rt_chat_clear_after'] > 0)
        {
            $rt_chat_deletion_time = TIME_NOW - (60 * 60 * 24 * (int) $mybb->settings['rt_chat_clear_after']);

            $db->delete_query("rtchat", "dateline < '{$rt_chat_deletion_time}'");
        }

        // Delete expired bans
        $rt_chat_clear = $db->delete_query("rtchat_bans", "dateline > expires");

        $rt_chat_num_deleted = (int) $db->affected_rows($rt_chat_clear);

        if ($rt_chat_num_deleted >= 1)
        {
            $query = $db->write_query("SELECT * FROM ".TABLE_PREFIX."rtchat_bans");

            $cached =  [];
            foreach ($query as $row)
            {
                $cached[] = $row;
            }

            // Set new cache
            $rt_cache->set(Core::get_plugin_info('prefix') . '_bans', $cached, 604800);
        }
    }

    /**
     * Hook: newreply_do_newreply_end
     *
     * @return void
     */
    public function newreply_do_newreply_end(): void
    {
        global $mybb, $lang, $post, $tid, $pid, $thread_subject;

        $watch_replies = 1;
        if (Core::is_bot_enabled() &&
            in_array($watch_replies, \rt\Chat\get_settings_values('bot_actions')) && // watch settings
            (in_array($post['fid'], \rt\Chat\get_settings_values('bot_forums')) || in_array(-1, \rt\Chat\get_settings_values('bot_forums')))
        )
        {
            $insert = new Create();

            $post_link = $mybb->settings['bburl'] . '/' . get_post_link($pid, $tid)."#pid{$pid}";
            $thread_link = $mybb->settings['bburl'] . '/' . get_thread_link($tid);
            $user_link = $mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $post['uid'];
            $forum_link = $mybb->settings['bburl'] . '/' . get_forum_link($post['fid']);
            $forum_name = isset(get_forum($post['fid'])['name']) ? htmlspecialchars_uni(get_forum($post['fid'])['name']) : $lang->na;

            $lang->rt_chat_new_post = $lang->sprintf($lang->rt_chat_new_post, $post_link, $thread_link, $thread_subject, $user_link, $post['username'], $forum_link, $forum_name);
            $insert->insertMessage((int) $mybb->settings['rt_chat_bot_id'], 0, $lang->rt_chat_new_post, true);
        }
    }

    /**
     * Hook: newthread_do_newthread_end
     *
     * @return void
     */
    public function newthread_do_newthread_end(): void
    {
        global $mybb, $lang, $new_thread, $tid;

        $watch_threads = 2;
        if (Core::is_bot_enabled() &&
            in_array($watch_threads, \rt\Chat\get_settings_values('bot_actions')) &&
            (in_array($new_thread['fid'], \rt\Chat\get_settings_values('bot_forums')) || in_array(-1, \rt\Chat\get_settings_values('bot_forums')))
        )
        {
            $insert = new Create();

            $thread_link = $mybb->settings['bburl'] . '/' . get_thread_link($tid);
            $user_link = $mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $new_thread['uid'];
            $forum_link = $mybb->settings['bburl'] . '/' . get_forum_link($new_thread['fid']);
            $forum_name = isset(get_forum($new_thread['fid'])['name']) ? htmlspecialchars_uni(get_forum($new_thread['fid'])['name']) : $lang->na;

            $lang->rt_chat_new_thread = $lang->sprintf($lang->rt_chat_new_thread, $thread_link, $new_thread['subject'], $user_link, $new_thread['username'], $forum_link, $forum_name);
            $insert->insertMessage((int) $mybb->settings['rt_chat_bot_id'], 0, $lang->rt_chat_new_thread, true);
        }
    }

    /**
     * Hook: member_do_register_end
     *
     * @return void
     */
    public function member_do_register_end(): void
    {
        global $mybb, $lang, $user_info;

        $watch_users = 3;
        if (Core::is_bot_enabled() && in_array($watch_users, \rt\Chat\get_settings_values('bot_actions')))
        {
            $lang->load('rt_chat');

            $insert = new Create();

            $user_link = $mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $user_info['uid'];

            $lang->rt_chat_new_user = $lang->sprintf($lang->rt_chat_new_user, $user_link, $user_info['username']);
            $insert->insertMessage((int) $mybb->settings['rt_chat_bot_id'], 0, $lang->rt_chat_new_user, true);
        }
    }
}