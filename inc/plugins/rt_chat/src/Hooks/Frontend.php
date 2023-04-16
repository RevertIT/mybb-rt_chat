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

use rt\Chat\ChatHandler\Create;
use rt\Chat\Core;
use rt\Chat\ChatHandler\Read;

/**
 * Hook: global_start
 *
 * @return void
 */
function global_start(): void
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
                \rt\Chat\load_templatelist(['chat_layout', 'chat']);
            }
            break;
        case 'xmlhttp.php':
            if ($mybb->get_input('action') === Core::get_plugin_info('prefix'))
            {
                \rt\Chat\load_templatelist(['chat_message']);
            }
    };
}

/**
 * Hook: index_start
 *
 * @return void
 */
function index_start(): void
{
    global $mybb, $lang, $rt_chat;
    if (Core::can_view())
    {

        $lang->load('rt_chat');
        eval('$rt_chat = "' . \rt\Chat\template('chat') . '";');
    }
}

/**
 * Hook: pre_output_page
 *
 * @param string $content
 * @return string
 * @throws \Exception
 */
function pre_output_page(string $content): string
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
function misc_start(): void
{
    global $mybb, $lang, $header, $headerinclude, $footer;

    if ($mybb->get_input('ext') === Core::get_plugin_info('prefix'))
    {
        // View chat layout
        if (Core::can_view())
        {
            $lang->load('rt_chat');

            add_breadcrumb($lang->rt_chat_name, 'misc.php?ext=' . Core::get_plugin_info('prefix'));

            eval('$rt_chat = "' . \rt\Chat\template('chat') . '";');

            eval('$template = "' . \rt\Chat\template('chat_layout') . '";');
            output_page($template);
        }
    }
}

/**
 * Hook: xmlhttp
 *
 * @return void
 */
function xmlhttp(): void
{
    global $mybb, $lang;

    if ($mybb->get_input('ext') === Core::get_plugin_info('prefix'))
    {
        if (Core::can_view())
        {
            // View messages in chat
            if ($mybb->get_input('action') === 'load_messages' && empty($mybb->get_input('before')))
            {
                if ($mybb->request_method !== 'post')
                {
                    header('Content-type: application/json');
                    echo json_encode(['status' => false, 'error' => 'Invalid method']);
                    exit;
                }

                $messages = new Read();

                header('Content-type: application/json');
                echo json_encode($messages->getMessages());
                exit;
            }

            // View chat history on scroll
            if (Core::can_view_history())
            {
                if ($mybb->get_input('action') === 'load_messages' && !empty($mybb->get_input('before', \MyBB::INPUT_INT)))
                {
                    $messages = new Read();

                    header('Content-type: application/json');
                    echo json_encode($messages->getMessageBeforeId($mybb->get_input('before', \MyBB::INPUT_INT)));
                    exit;
                }
            }

            // Insert message
            if ($mybb->get_input('action') === 'insert_message')
            {
                $insert = new Create();

                $data = $insert->insertMessage($mybb->get_input('message'));

                header('Content-type: application/json');
                echo json_encode($data);
                exit;
            }
        }
    }
}