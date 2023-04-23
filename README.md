## RT Chat
Is a modern and responsive MyBB chat plugin which utilizes MyBB cache system when retrieving messages via ajax. For high performance and no database queries, you can speed up ajax requests to even 1-2 seconds per request for better message sync.

**This is work-in-progress plugin**. Testing and requesting additional features is highly appreciated.

### Table of contents

1. [❗ Dependencies](#-dependencies)
2. [📃 Features](#-features)
3. [➕ Installation](#-installation)
4. [🔼 Update](#-update)
5. [➖ Removal](#-removal)
6. [📜 Usage](#-usage)
7. [💡 Feature request](#-feature-request)
8. [🙏 Questions](#-questions)
9. [🐞 Bug reports](#-bug-reports)
10. [📷 Preview](#-preview)

### ❗ Dependencies
- MyBB 1.8.x
- https://github.com/frostschutz/MyBB-PluginLibrary (>= 13)
- https://github.com/RevertIT/mybb-rt_extendedcache (>= 2.0)
- PHP >= 8.0

### 📃 Features
- Responsive design (CSS, templates, and settings included)
- Ajax chat with cached messages. (**No database stress!**)
- Chat bot (Get configurable notifications from bot in chat)
- Chat actions like (/clear, /ban, /unban)
- Infinite scroll for older messages
- Set refresh time
- Set away time (Ajax won't be called when user is afk)
- Supports BBCodes and Smiles
- Max message length
- Edit/delete messages
- Groups which can access chat
- Separated chat page at _/misc.php?ext=rt_chat_
- and many more features.

### ➕ Installation
1. Copy the directories from the plugin inside your root MyBB installation.
2. Settings for the plugin are located in the "Plugin Settings" tab. (`/admin/index.php?module=config-settings`)

### 🔼 Update
1. Deactivate the plugin.
2. Replace the plugin files with the new files.
3. Activate the plugin again.

### ➖ Removal
1. Uninstall the plugin from your plugin manager.
2. _Optional:_ Delete all the RT Chat plugin files from your MyBB folder.

### 📜 Usage
#### Chat actions
All chat actions are case-insensitive.

- **Ban user via chat action**
```php
/ban "username" "ban reason" 60
```
Will ban the user with username `username` and set reason as `ban reason` for `60` minutes.

- **Unban user via chat action**
```php
/unban "username"
```
Will unban the user with username `username`.

- **Clear chat via chat action**
```php
/clear
```
Will clear all the chat messages.

### 💡 Feature request
Open a new idea by [clicking here](https://github.com/RevertIT/mybb-rt_chat/discussions/new?category=ideas)

### 🙏 Questions
Open a new question by [clicking here](https://github.com/RevertIT/mybb-rt_chat/discussions/new?category=q-a)

### 🐞 Bug reports
Open a new bug report by [clicking here](https://github.com/RevertIT/mybb-rt_chat/issues/new)

### 📷 Preview
<img src="https://i.postimg.cc/j5CZLRqV/ss1.png" alt="ss1">
<img src="https://i.postimg.cc/QxdV5GWT/ss1.png" alt="ss1">
<img src="https://i.postimg.cc/xjg4wTQB/ss5.png" alt="ss1">
<img src="https://i.postimg.cc/G2NzD694/ss2.png" alt="ss2">
<img src="https://i.postimg.cc/tJxkNDV2/ss3.png" alt="ss3">
