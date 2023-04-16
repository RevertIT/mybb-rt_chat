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

    fetchMessages: async (url) =>
    {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
        });

        const result = await response.json();

        if (result.status === false)
        {
            throw new Error(result.error);
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
            const messageDiv = chatBox.querySelector(`[id="${m.id}"]`);
            if (messageDiv)
            {
                // Update the message if its HTML has changed
                if (messageDiv.innerHTML !== m.html)
                {
                    messageDiv.innerHTML = m.html;
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

        const data = {'my_post_key': my_post_key}
        const response = await fetch(`${url}&before=${RT_Chat.oldestMessageId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
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
            const messageDiv = chatBox.querySelector(`[id="${m.id}"]`);

            if (messageDiv)
            {
                // Update the message if its HTML has changed
                if (messageDiv.innerHTML !== m.html)
                {
                    messageDiv.innerHTML = m.html;
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
            }
        }

        const newScrollHeight = chatBox.scrollHeight;
        chatBox.scrollTop += newScrollHeight - currentScrollHeight;

        // Set oldestMessageId to the date of the last message
        RT_Chat.oldestMessageId = result.data.first;
    },
    load: async (fetchMessagesUrl, selector, awayInterval, refreshTime) =>
    {
        let loader = document.querySelector(`${selector}-messages`);
        const milliseconds = refreshTime * 1000;

        loader.innerHTML = RT_Chat.loader;

        try
        {
            const fetch = await RT_Chat.fetchMessages(fetchMessagesUrl);
            RT_Chat.oldestMessageId = ++fetch.data.last;
            loader.innerHTML = '';

            RT_Chat.renderMessages(selector, fetch.messages);
            RT_Chat.checkUserActivity(awayInterval);

            // Interval for new messages
            setInterval(async () =>
            {
                if (RT_Chat.isActive)
                {
                    const fetch2 = await RT_Chat.fetchMessages(fetchMessagesUrl);

                    // If we have a new last id, insert new messages
                    if (fetch2.data.last !== RT_Chat.oldestMessageId)
                    {
                        RT_Chat.oldestMessageId = ++fetch2.data.last;
                        RT_Chat.renderMessages(selector, fetch2.messages);
                    }
                }
            }, milliseconds);

            // Scrolling top
            const chatBox = document.querySelector(`${selector}-messages`);
            chatBox.addEventListener('scroll', async () =>
            {
                if (chatBox.scrollTop === 0 && RT_Chat.isActive)
                {
                    await RT_Chat.loadMoreMessages(selector, fetchMessagesUrl);
                }
            });
        }
        catch (e)
        {
            console.log(`RT Chat Error: ${e}`);
        }
    },
    insertMessage: async (url, selector, e = event) =>
    {
        e.preventDefault();

        const message = document.querySelector(selector + '-input input[name="message"]').value;
        const myPostKey = document.querySelector(selector + '-input input[name="my_post_key"]').value;

        // Create a new form data object
        const formData = new FormData();
        formData.append('message', message);
        formData.append('my_post_key', myPostKey);

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();

        if (!result.status)
        {
            alert(result.error);
        }
        else
        {
            document.querySelector(selector + '-input input[name="message"]').value = '';
        }
    }
}