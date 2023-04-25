<div class="rt_chat-message-avatar">
    <img src="{$row['avatar']}" alt="Avatar">
</div>
<div class="rt_chat-message-content">
    <div class="rt_chat-message-meta">
        <span class="rt_chat-message-author">{$row['username']}</span>
        <span class="rt_chat-message-timestamp" data-timestamp="{$row['dateline']}"></span>
    </div>
    <div class="rt_chat-message-text">
        {$row['message']}
        <input type="hidden" name="original_message" value="{$row['original_message']}">
    </div>
    <div class="rt_chat-message-action">
        {$row['edit_message']}{$row['delete_message']}
    </div>
</div>