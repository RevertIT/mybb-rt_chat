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

namespace rt\Chat;

class Core
{
    public static array $PLUGIN_DETAILS = [
        'name' => 'RT Chat',
        'website' => 'https://github.com/RevertIT/mybb-rt_chat',
        'description' => 'RT Chat is a modern and responsive MyBB chat plugin which utilizes MyBB cache system when retrieving messages via ajax.',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '1.1',
        'compatibility' => '18*',
        'codename' => 'rt_chat',
        'prefix' => 'rt_chat',
    ];

    /**
     * Get plugin info
     *
     * @param string $info
     * @return string
     */
    public static function get_plugin_info(string $info): string
    {
        return match(isset(self::$PLUGIN_DETAILS[$info]))
        {
            true => self::$PLUGIN_DETAILS[$info],
            default => '',
        };
    }

    /**
     * Check if plugin is installed
     *
     * @return bool
     */
    public static function is_installed(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_chat_enabled']))
        {
            return true;
        }

        return false;
    }

    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public static function is_enabled(): bool
    {
        global $mybb;

        return match(isset($mybb->settings['rt_chat_enabled']) && (int) $mybb->settings['rt_chat_enabled'] === 1)
        {
            true => true,
            default => false,
        };
    }

    /**
     * Can view the chat
     *
     * @return bool
     */
    public static function can_view(): bool
    {
        $setting = \rt\Chat\get_settings_values('canview_chat');

        return is_member($setting) || in_array(-1, $setting);
    }

    /**
     * Can view the chat history
     *
     * @return bool
     */
    public static function can_view_history(): bool
    {
        $setting = \rt\Chat\get_settings_values('canview_history');

        return is_member($setting) || in_array(-1, $setting);
    }

    /**
     * Can moderate the chat
     *
     * @return bool
     */
    public static function can_moderate(): bool
    {
        $setting = \rt\Chat\get_settings_values('canmoderate');

        return is_member($setting) || in_array(-1, $setting);
    }

    /**
     * Can post in chat
     *
     * @return bool
     */
    public static function can_post(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_chat_minposts_chat']))
        {
            if ($mybb->user['postnum'] < (int) $mybb->settings['rt_chat_minposts_chat'])
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if bot is enabled
     *
     * @return bool
     */
    public static function is_bot_enabled(): bool
    {
        global $mybb;

        return isset($mybb->settings['rt_chat_bot_enabled']) && (int) $mybb->settings['rt_chat_bot_enabled'] === 1;
    }

    /**
     * Check if user is banned from chat
     *
     * @param int $uid
     * @return bool
     */
    public static function is_banned(int $uid = 0): bool
    {
        global $mybb, $rt_cache;

        if ($uid === 0)
        {
            $uid = $mybb->user['uid'];
        }

        $data = $rt_cache->get(Core::get_plugin_info('prefix') . '_bans');

        $uids = array_column((array) $data, 'uid');

        if (in_array($uid, $uids))
        {
            return true;
        }

        return false;
    }

    /**
     * Check banned details for the user
     *
     * @param int $uid
     * @return array|bool
     */
    public static function show_banned_details(int $uid = 0): array|bool
    {
        global $mybb, $rt_cache;

        if ($uid === 0)
        {
            $uid = $mybb->user['uid'];
        }

        $data = $rt_cache->get(Core::get_plugin_info('prefix') . '_bans');

        $uids = array_column((array) $data, 'uid');

        if (in_array($uid, $uids))
        {
            foreach ($data as $row)
            {
                if ($uid === (int) $row['uid'])
                {
                    return $row;
                }
            }
        }

        return false;
    }

    /**
     * Set plugin cache
     *
     * @return void
     */
    public static function set_cache(): void
    {
        global $cache;

        if (!empty(self::$PLUGIN_DETAILS))
        {
            $cache->update(self::$PLUGIN_DETAILS['prefix'], self::$PLUGIN_DETAILS);
        }
    }

    /**
     * Delete plugin cache
     *
     * @return void
     */
    public static function remove_cache(): void
    {
        global $cache, $rt_cache;

        if (!empty($cache->read(self::$PLUGIN_DETAILS['prefix'])))
        {
            $cache->delete(self::$PLUGIN_DETAILS['prefix']);
        }

        $rt_cache->delete(Core::get_plugin_info('prefix') . '_bans');
        $rt_cache->delete('rt_chat_messages');
    }

    /**
     * Add settings
     *
     * @return void
     */
    public static function add_settings(): void
    {
        global $PL;

        $PL->settings(self::$PLUGIN_DETAILS['prefix'],
            'RT Chat Settings',
            'General settings for the RT Chat',
            [
                "enabled" => [
                    'title' => 'Enable plugin?',
                    'description' => 'Useful way to disable plugin without deleting templates/settings.',
                    'optionscode' => 'yesno',
                    'value' => 1
                ],
                "bot_enabled" => [
                    'title' => 'Enable bot notifications?',
                    'description' => 'This setting will add new messages into chat as a bot when new action is detected.',
                    'optionscode' => 'yesno',
                    'value' => 1
                ],
                "bot_id" => [
                    'title' => 'Which user id should bot have?',
                    'description' => 'Set the desired user id for the bot when posting in the chat.',
                    'optionscode' => 'numeric',
                    'value' => 2
                ],
                "bot_forums"  => [
                    'title' => 'Which forums should bot check for new posts/threads?',
                    'description' => 'Set the desired forums which bot should check.',
                    'optionscode' => 'groupselect',
                    'value' => '-1'
                ],
                "bot_actions" => [
                    'title' => 'Select which actions should the bot watch?',
                    'description' => 'Comma separated actions.<br>
					0. None<br>
					1. Watch new replies<br>
					2. Watch new threads<br>
					3. Watch new user registrations<br>',
                    'optionscode' => "text",
                    'value' => '1,2,3'
                ],
                "total_messages" => [
                    'title' => 'Total messages',
                    'description' => 'Number of messages to be shown per ajax iteration.',
                    'optionscode' => 'numeric',
                    'value' => 10,
                ],
                "clear_after" => [
                    'title' => 'Delete messages older than (in days)',
                    'description' => 'To prevent having too many logs, we should remove old messages after certain period of time.',
                    'optionscode' => 'numeric',
                    'value' => 7,
                ],
                "height" => [
                    'title' => "Chat height",
                    'description' => 'Chat height in px/pt/etc.',
                    'optionscode' => 'text',
                    'value' => '300px'
                ],
                "msg_length" => [
                    'title' => 'Message length',
                    'description' => 'The message length for users',
                    'optionscode' => 'numeric',
                    'value' => 500,
                ],
                "anti_flood" => [
                    'title' => 'Anti-flood protection (in seconds)',
                    'description' => 'How many seconds needs to pass before same user can send another message? (O to disable this option)',
                    'optionscode' => 'numeric',
                    'value' => 10,
                ],
                "mycode_enabled" => [
                    'title' => 'Enable MyCode BBCodes?',
                    'description' => 'Support for native MyBB bbcodes',
                    'optionscode' => 'yesno',
                    'value' => 1
                ],
                "smilies_enabled" => [
                    'title' => 'Enable Smilies?',
                    'description' => 'Support for native MyBB smilies',
                    'optionscode' => 'yesno',
                    'value' => 1
                ],
                "refresh_interval" => [
                    'title' => 'Refresh interval',
                    'description' => 'How many seconds should pass between calling new ajax request? The lower the number, the better sync will be, but it will make more requests to the backend.',
                    'optionscode' => 'numeric',
                    'value' => 5,
                ],
                "away_interval" => [
                    'title' => 'Away interval',
                    'description' => 'Requests will be paused when no user click/key detected',
                    'optionscode' => 'numeric',
                    'value' => 300,
                ],
                "minposts_chat" => [
                    'title' => 'Minimum posts to send message',
                    'description' => 'How many posts user needs to have before being able to post chat messages.',
                    'optionscode' => 'numeric',
                    'value' => '0',
                ],
                "canview_chat" => [
                    'title' => 'Who can view the chat',
                    'description' => 'Groups that can view the chat.',
                    'optionscode' => 'groupselect',
                    'value' => '-1',
                ],
                "canview_history" => [
                    'title' => 'Who can view the chat history (Infinite scroll)',
                    'description' => 'Groups that can view the chat history when scrolling chat up.<br><b>Notice:</b> This option will query database and will not cache results.',
                    'optionscode' => 'groupselect',
                    'value' => '-1',
                ],
                "canmoderate" => [
                    'title' => 'Which usergroups can moderate chat?',
                    'description' => 'Groups that can moderate the chat and ban members. Those usergroups can post without minpost limitations',
                    'optionscode' => 'groupselect',
                    'value' => '4',
                ],

            ]
        );
    }

    /**
     * Delete settings
     *
     * @return void
     */
    public static function remove_settings(): void
    {
        global $PL;

        $PL->settings_delete(self::$PLUGIN_DETAILS['prefix'], true);
    }

    /**
     * Add custom database columns on existing tables
     *
     * @return void
     */
    public static function add_database_modifications(): void
    {
        global $db;

        $rt_chat_table = match ($db->type)
        {
            'pgsql' => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat (
                            id SERIAL PRIMARY KEY,
                            uid INTEGER,
                            message TEXT,
                            dateline INTEGER
                        );",
            'sqlite' => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            uid INTEGER,
                            message TEXT,
                            dateline INTEGER
                        );",
            default => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat(
                            id INT NOT NULL AUTO_INCREMENT,
                            uid INT NULL,
                            message TEXT NULL,
                            dateline INT NULL,
                            PRIMARY KEY(`id`)
                        ) ENGINE = InnoDB;"
        };

        $db->write_query($rt_chat_table);

        $rt_chat_ban_table = match ($db->type)
        {
            'pgsql' => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat_bans (
                            id SERIAL PRIMARY KEY,
                            uid INTEGER,
                            reason TEXT,
                            dateline INTEGER,
                            expires INTEGER,
                            UNIQUE KEY uid (uid),
                        );",
            'sqlite' => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat_bans (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            uid INTEGER UNIQUE,
                            reason TEXT,
                            dateline INTEGER,
                            expires INTEGER,
                            UNIQUE (uid) ON CONFLICT REPLACE
                        );",
            default => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat_bans (
                            id INT NOT NULL AUTO_INCREMENT,
                            uid INT NULL,
                            reason TEXT NULL,
                            dateline INT NULL,
                            expires INT NULL,
                            PRIMARY KEY(id),
                            UNIQUE (uid)
                        ) ENGINE = InnoDB;"
        };

        $db->write_query($rt_chat_ban_table);

        $db->insert_query('rtchat', [
            'uid' => 1,
            'message' => 'This is a first message!',
            'dateline' => TIME_NOW,
        ]);
    }

    /**
     * Remove custom database columns on existing tables
     *
     * @return void
     */
    public static function drop_database_modifications(): void
    {
        global $mybb, $db, $lang, $page;

        $prefix = self::$PLUGIN_DETAILS['prefix'];

        if ($mybb->request_method !== 'post')
        {
            $lang->load($prefix);

            $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=' . self::$PLUGIN_DETAILS['prefix'], $lang->{$prefix . '_uninstall_message'}, $lang->uninstall);
        }

        // Drop tables
        if (!isset($mybb->input['no']))
        {
            $db->drop_table('rtchat');
            $db->drop_table('rtchat_bans');
        }
    }

    /**
     * Add templates
     *
     * @return void
     */
    public static function add_templates(): void
    {
        global $PL;

        $PL->templates(
        // Prevent underscore on template prefix
            str_replace('_', '', self::$PLUGIN_DETAILS['prefix']),
            self::$PLUGIN_DETAILS['name'],
            load_template_files('inc/plugins/'.self::$PLUGIN_DETAILS['prefix'].'/templates/')
        );
    }

    /**
     * Remove templates
     *
     * @return void
     */
    public static function remove_templates(): void
    {
        global $PL;

        $PL->templates_delete(str_replace('_', '', self::$PLUGIN_DETAILS['prefix']), true);
    }

    /**
     * Add stylesheet
     *
     * @return void
     */
    public static function add_stylesheet(): void
    {
        global $PL;

        $styles = [
            self::$PLUGIN_DETAILS['prefix'] => [
                'attached_to' => [],
            ],
        ];

        foreach ($styles as $styleName => $styleProperties)
        {
            $file = file_exists(MYBB_ROOT . 'inc/plugins/' . self::$PLUGIN_DETAILS['prefix'] . '/stylesheets/' . $styleName . '.css') ?
                file_get_contents(MYBB_ROOT . 'inc/plugins/' . self::$PLUGIN_DETAILS['prefix'] . '/stylesheets/' . $styleName . '.css') :
                null;

            $PL->stylesheet($styleName, $file, $styleProperties['attached_to']);
        }
    }

    /**
     * Remove stylesheet
     *
     * @return void
     */
    public static function remove_stylesheet(): void
    {
        global $PL;

        $PL->stylesheet_delete(self::$PLUGIN_DETAILS['prefix'], true);
    }

    /**
     * Find and replace existing templates with new values
     *
     * @return void
     */
    public static function edit_installed_templates(): void
    {
        // header
        $replace = '{$rt_chat}{$forums}';
        edit_template("index", '{$forums}', $replace);
    }

    /**
     * Return existing templates to old values
     *
     * @return void
     */
    public static function revert_installed_templates_changes(): void
    {
        // header
        $find = '{$rt_chat}';
        edit_template("index", $find, '');
    }

    /**
     * Frontend head html injection
     *
     * @return string|null
     */
    public static function head_html_front(): ?string
    {
        global $mybb;

        $html = null;

        $html .= '<script src="'.$mybb->asset_url.'/jscripts/'.self::$PLUGIN_DETAILS['prefix'].'.js?ver='.self::$PLUGIN_DETAILS['version'].'"></script>' . PHP_EOL;

        $html .= '</head>';

        return $html;
    }

    /**
     * Frontend body html injection
     *
     * @return string|null
     * @throws \Exception
     */
    public static function body_html_front(): void
    {
    }
}