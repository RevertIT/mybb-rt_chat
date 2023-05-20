<div class="modal">
    <div style="overflow-y: auto; max-height: 400px;">
        <form method="get" class="rt_chat_statistics">
            <table width="100%" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" border="0" class="tborder">
                <tr>
                    <td class="thead" colspan="2"><strong>{$lang->rt_chat_statistics}</strong></td>
                </tr>
                <tr>
                    <td class="tcat"><span class="smalltext"><strong>{$lang->rt_chat_username}</strong></span></td>
                    <td class="tcat" align="center"><span class="smalltext"><strong>{$lang->rt_chat_num_of_messages}</strong></span></td>
                </tr>
                {$rt_chat_statistics_row}
            </table>
    </div>
</div>