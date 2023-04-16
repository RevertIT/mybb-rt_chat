<div class="rt_chat-container" style="height: {$mybb->settings['rt_chat_height']}">
    <div class="rt_chat-header">
        <b>{$lang->rt_chat_name}</b>
    </div>
    <div class="rt_chat-messages"></div>
    <form onsubmit="RT_Chat.insertMessage('{$mybb->settings['bburl']}/xmlhttp.php?ext=rt_chat&action=insert_message', '.rt_chat');">
        <div class="rt_chat-input">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
            <input type="text" name="message" placeholder="{$lang->rt_chat_enter_message}" onkeydown="event">
            <button>{$lang->rt_chat_send}</button>
        </div>
    </form>
</div>
<br>
<script>RT_Chat.load('{$mybb->settings['bburl']}/xmlhttp.php?ext=rt_chat&action=load_messages', ".rt_chat", {$mybb->settings['rt_chat_away_interval']}, {$mybb->settings['rt_chat_refresh_interval']});</script>