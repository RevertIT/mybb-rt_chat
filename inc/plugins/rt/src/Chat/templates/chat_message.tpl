<div class="rt_chat-message-avatar">
    <img src="{$row['avatar']}" alt="">
</div>
<div class="rt_chat-message-content">
    <div class="rt_chat-message-meta">
        <span class="rt_chat-message-author">{$row['username']}{$rt_chat_whisper}</span>
        <span class="rt_chat-message-timestamp" data-timestamp="{$row['dateline']}"></span>
    </div>
    <div class="rt_chat-message-text">
        {$row['message']}
        <input type="hidden" name="original_message" value="{$row['original_message']}">
    </div>
    <div class="rt_chat-message-action">
        {$rt_chat_actions}
    </div>
</div>