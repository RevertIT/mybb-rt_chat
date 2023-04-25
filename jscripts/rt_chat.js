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

let RT_Chat =
{
    oldestMessageId: null,
    isActive: true,
    isBottom: true,
    loader: spinner,
    insertUpdateMessageUrl: rootpath + '/xmlhttp.php?ext=rt_chat&action=insert_update_message',
    loadMessagesUrl: rootpath + '/xmlhttp.php?ext=rt_chat&action=load_messages',
    deleteMessageUrl: rootpath + '/xmlhttp.php?ext=rt_chat&action=delete_message',

    fetchMessages: async (url, postData = []) =>
    {
        let formData = new FormData();
        formData.append('my_post_key', my_post_key);
        if (postData)
        {
            formData.append('loaded', JSON.stringify(postData));
        }
        const response = await fetch(url, {
            method: 'post',
            body: formData,
        });

        const result = await response.json();

        if (result === null || result.status === false)
        {
            return false;
        }

        return result;
    },
    renderMessages: (selector, messages) =>
    {
        // Find chatbox
        const chatBox = document.querySelector(selector + '-messages');
        const selectorClass = selector.replace(/\./g, '');

        // Find current scroll
        const currentScrollHeight = chatBox.scrollHeight;

        for (let m of messages)
        {
            // Find message id
            const messageDiv = chatBox.querySelector(`${selector}-message[id="${m.id}"]`);
            if (messageDiv)
            {
                // Update the message if its HTML has changed
                if (messageDiv.innerHTML !== m.html)
                {
                    messageDiv.innerHTML = m.html;
                    let timestamp = messageDiv.querySelector(selector + '-message-timestamp');
                    let unixTime = timestamp.getAttribute('data-timestamp');
                    timestamp.innerHTML = RT_Chat.renderTime(unixTime);
                }
            }
            else
            {
                // Create a new message if we get new data into json
                const newMessageDiv = document.createElement('div');
                newMessageDiv.id = m.id;
                newMessageDiv.classList.add(`${selectorClass}-message`);
                newMessageDiv.innerHTML = m.html;
                chatBox.appendChild(newMessageDiv);

                let timestamp = newMessageDiv.querySelector(selector + '-message-timestamp');
                let unixTime = timestamp.getAttribute('data-timestamp');
                timestamp.innerHTML = RT_Chat.renderTime(unixTime);
            }
        }

        const newScrollHeight = chatBox.scrollHeight;
        chatBox.scrollTop += newScrollHeight - currentScrollHeight;
    },
    checkUserActivity: (seconds) =>
    {
        const milliseconds = seconds * 1000;

        let timeoutId = setTimeout(() =>
        {
            RT_Chat.isActive = false;
        }, milliseconds);

        const resetUserActivity = () =>
        {
            RT_Chat.isActive = true;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() =>
            {
                RT_Chat.isActive = false;
            }, milliseconds);
        }
        window.addEventListener('mousemove', resetUserActivity);
        window.addEventListener('keydown', resetUserActivity);
    },
    loadMoreMessages: async (selector, url) =>
    {
        const selectorClass = selector.replace(/\./g, '');

        let formData = new FormData();
        formData.append('my_post_key', my_post_key);
        formData.append('before', RT_Chat.oldestMessageId)

        const response = await fetch(url, {
            method: 'post',
            body: formData
        });
        const result = await response.json();

        // Find chatbox
        const chatBox = document.querySelector(selector + '-messages');

        // Find current scroll
        const currentScrollHeight = chatBox.scrollHeight;

        // No more messages to return
        if (!result.status)
        {
            return;
        }

        for (let m of result.messages)
        {
            // Find message id
            const messageDiv = chatBox.querySelector(`${selector}-message[id="${m.id}"]`);

            if (messageDiv)
            {
                // Update the message if its HTML has changed
                if (messageDiv.innerHTML !== m.html)
                {
                    messageDiv.innerHTML = m.html;
                    let timestamp = messageDiv.querySelector(selector + '-message-timestamp');
                    let unixTime = timestamp.getAttribute('data-timestamp');
                    timestamp.innerHTML = RT_Chat.renderTime(unixTime);
                }
            }
            else
            {
                // Create a new message if we get new data into json
                const newMessageDiv = document.createElement('div');
                newMessageDiv.id = m.id;
                newMessageDiv.classList.add(`${selectorClass}-message`);
                newMessageDiv.innerHTML = m.html;
                chatBox.insertBefore(newMessageDiv, chatBox.firstChild);

                let timestamp = newMessageDiv.querySelector(selector + '-message-timestamp');
                let unixTime = timestamp.getAttribute('data-timestamp');
                timestamp.innerHTML = RT_Chat.renderTime(unixTime);
            }
        }

        const newScrollHeight = chatBox.scrollHeight;
        chatBox.scrollTop += newScrollHeight - currentScrollHeight;

        // Set oldestMessageId to the date of the last message
        RT_Chat.oldestMessageId = result.data.first;
    },
    load: async (selector, awayInterval, refreshTime) =>
    {
        const selectorClass = selector.replace(/\./g, '');
        let loader = document.querySelector(`${selector}-messages`);
        const milliseconds = refreshTime * 1000;

        loader.innerHTML = RT_Chat.loader;

        try
        {
            const fetch = await RT_Chat.fetchMessages(RT_Chat.loadMessagesUrl);
            loader.innerHTML = '';

            if (fetch.status === true)
            {
                RT_Chat.oldestMessageId = ++fetch.data.last;

                RT_Chat.renderMessages(selector, fetch.messages);
                RT_Chat.checkUserActivity(awayInterval);

                // Interval for new messages
                setInterval(async () =>
                {
                    if (RT_Chat.isActive)
                    {
                        const fetch2 = await RT_Chat.fetchMessages(RT_Chat.loadMessagesUrl, fetch.data.loaded);

                        if (fetch2.status === true)
                        {
                            // If we have a new last id, insert new messages
                            if (fetch2.data.last !== RT_Chat.oldestMessageId)
                            {
                                RT_Chat.oldestMessageId = ++fetch2.data.last;
                                RT_Chat.renderMessages(selector, fetch2.messages);
                            }
                        }
                    }
                }, milliseconds);
            }

            // Scrolling top
            const chatBox = document.querySelector(`${selector}-messages`);
            chatBox.addEventListener('scroll', async () =>
            {
                if (chatBox.scrollTop === 0 && RT_Chat.isActive)
                {
                    await RT_Chat.loadMoreMessages(selector, RT_Chat.loadMessagesUrl);
                }
            });

            // Update interval for timestamp
            setInterval(() =>
            {
                let time = chatBox.querySelectorAll(selector + '-message-timestamp');

                for (let t of time)
                {
                    let unixTime = t.getAttribute('data-timestamp');
                    t.innerHTML = RT_Chat.renderTime(unixTime);
                }
            }, 1000);

            // Add listener for insterting message
            const insertMessage = document.querySelector(`${selector}-insert`);
            insertMessage.addEventListener('submit', (event) =>
            {
                event.preventDefault();
                RT_Chat.insertMessage(RT_Chat.insertUpdateMessageUrl, selector);
            });

            // Add listener for message actions
            document.addEventListener('click', (event) =>
            {
                const target = event.target;
                if (target.classList.contains(`${selectorClass}-delete`))
                {
                    RT_Chat.deleteMessage(RT_Chat.deleteMessageUrl, selector, target.id);
                }

                if (target.classList.contains(`${selectorClass}-edit`))
                {
                    RT_Chat.editMessage(selector, target.id);
                }
            });
        }
        catch (e)
        {
            console.log(`RT Chat Error: ${e}`);
        }
    },
    insertMessage: async (url, selector) =>
    {
        const message = document.querySelector(selector + '-input input[name="message"]').value;
        const myPostKey = document.querySelector(selector + '-input input[name="my_post_key"]').value;
        const editId = document.querySelector(selector + '-input input[name="edit_id"]').value;

        // Create a new form data object
        const formData = new FormData();
        formData.append('message', message);
        formData.append('my_post_key', myPostKey);
        formData.append('edit_id', editId);

        const response = await fetch(url, {
            method: 'post',
            body: formData,
        });

        const result = await response.json();

        if (!result.status)
        {
            $(".jGrowl").jGrowl("close");
            $.jGrowl(result.error, {theme:'jgrowl_error'});
        }
        else
        {
            document.querySelector(selector + '-input input[name="message"]').value = '';
            document.querySelector(selector + '-input > input[name="edit_id"]').value = '';
            RT_Chat.oldestMessageId = ++result.data.last;
            RT_Chat.renderMessages(selector, result.messages);
        }
    },
    deleteMessage: async(url, selector, id) =>
    {
        let deleteConfirm = confirm("Are you sure you want to delete this?");

        if (deleteConfirm === false)
        {
            return false;
        }

        // Create a new form data object
        const formData = new FormData();
        formData.append('message', id);
        formData.append('my_post_key', my_post_key);

        const response = await fetch(url, {
            method: 'post',
            body: formData,
        });

        const result = await response.json();

        if (!result.status)
        {
            $(".jGrowl").jGrowl("close");
            $.jGrowl(result.error, {theme:'jgrowl_error'});
        }
        else
        {
            let message = document.querySelector(`${selector}-messages > [id="${id}"]`);
            message.parentNode.removeChild(message);
        }
    },
    editMessage: async (selector, id) =>
    {
        // Set edit id
        document.querySelector(selector + '-input > input[name="edit_id"]').value = id;

        // Get message content
        let messageToEdit = document.querySelector(`${selector}-message[id="${id}"] ${selector}-message-text > input[name="original_message"]`).value;
        messageToEdit = atob(messageToEdit);

        document.querySelector(selector + '-input > input[name="message"]').value = messageToEdit;
        document.querySelector(selector + '-input > input[name="message"]').focus();
    },
    renderTime: (unixTimestamp) =>
    {
        const now = Date.now() / 1000;
        const diff = now - unixTimestamp;

        switch (true)
        {
            case (diff < 1):
                return `just now`;
            case (diff < 60):
                return `${Math.floor(diff)} seconds ago`;
            case (diff < 3600):
                const minutes = Math.floor(diff / 60);
                return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
            case (diff < 86400):
                const hours = Math.floor(diff / 3600);
                return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
            default:
                const date = new Date(unixTimestamp * 1000);
                return date.toDateString();
        }
    },
}