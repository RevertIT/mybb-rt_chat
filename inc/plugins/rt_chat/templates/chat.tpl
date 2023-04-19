<div class="rt_chat-container" style="height: {$mybb->settings['rt_chat_height']}">
    <div class="rt_chat-header">
        <b>{$lang->rt_chat_name}</b>
    </div>
    <div class="rt_chat-messages"></div>
    <form class="rt_chat-insert">
        <div class="rt_chat-input">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}"/>
            <input type="hidden" name="edit_id" value=""/>
            <input{$is_disabled} type="text" name="message" placeholder="{$lang->rt_chat_enter_message}" />
            <button{$is_disabled}>{$lang->rt_chat_send}</button>
        </div>
    </form>
</div>
<br>
<script>RT_Chat.load(".rt_chat", {$mybb->settings['rt_chat_away_interval']}, {$mybb->settings['rt_chat_refresh_interval']});</script>