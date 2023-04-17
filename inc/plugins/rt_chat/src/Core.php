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
    private const PLUGIN_DETAILS = [
        'name' => 'RT Chat <span style="color: red">(Experimental)</span>',
        'website' => 'https://github.com/RevertIT/mybb-rt_chat',
        'description' => 'RT chat development version.',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '0.2',
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
        return match(isset(self::PLUGIN_DETAILS[$info]))
        {
            true => self::PLUGIN_DETAILS[$info],
            default => '',
        };
    }

    /**
     * Get plugin description
     *
     * @return string
     */
    public static function get_plugin_description(): string
    {
        return self::PLUGIN_DETAILS['description'];
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

        return isset($mybb->settings['rt_chat_minposts_chat']) && (int) $mybb->settings['rt_chat_minposts_chat'] > $mybb->user['postnum'];
    }

    /**
     * Set plugin cache
     *
     * @return void
     */
    public static function set_cache(): void
    {
        global $cache;

        if (!empty(self::PLUGIN_DETAILS))
        {
            $cache->update(self::PLUGIN_DETAILS['prefix'], self::PLUGIN_DETAILS);
        }
    }

    /**
     * Delete plugin cache
     *
     * @return void
     */
    public static function remove_cache(): void
    {
        global $cache;

        if (!empty($cache->read(self::PLUGIN_DETAILS['prefix'])))
        {
            $cache->delete(self::PLUGIN_DETAILS['prefix']);
        }
    }

    /**
     * Add settings
     *
     * @return void
     */
    public static function add_settings(): void
    {
        global $PL;

        $PL->settings(self::PLUGIN_DETAILS['prefix'],
            'RT Chat Settings',
            'General settings for the RT Chat',
            [
                "enabled" => [
                    'title' => 'Enable plugin?',
                    'description' => 'Useful way to disable plugin without deleting templates/settings.',
                    'optionscode' => 'yesno',
                    'value' => 1
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

        $PL->settings_delete(self::PLUGIN_DETAILS['prefix'], true);
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
                            mid INTEGER,
                            dateline INTEGER,
                            expires INTEGER,
                        );",
            'sqlite' => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat_bans (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            uid INTEGER,
                            mid INTEGER,
                            dateline INTEGER,
                            expires INTEGER
                        );",
            default => "CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rtchat_bans (
                            id INT NOT NULL AUTO_INCREMENT,
                            uid INT NULL,
                            mid INT NULL,
                            dateline INT NULL,
                            expires INT NULL,
                            PRIMARY KEY(`id`)
                        ) ENGINE = InnoDB;"
        };

        $db->write_query($rt_chat_ban_table);

        $db->insert_query('rtchat', [
            'uid' => 1,
            'message' => 'Hello world!',
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

        $prefix = self::PLUGIN_DETAILS['prefix'];

        if ($mybb->request_method !== 'post')
        {
            $lang->load($prefix);

            $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=' . self::PLUGIN_DETAILS['prefix'], $lang->{$prefix . '_uninstall_message'}, $lang->uninstall);
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
            str_replace('_', '', self::PLUGIN_DETAILS['prefix']),
            self::PLUGIN_DETAILS['name'],
            load_template_files('inc/plugins/'.self::PLUGIN_DETAILS['prefix'].'/templates/')
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

        $PL->templates_delete(str_replace('_', '', self::PLUGIN_DETAILS['prefix']), true);
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
            self::PLUGIN_DETAILS['prefix'] => [
                'attached_to' => [],
            ],
        ];

        foreach ($styles as $styleName => $styleProperties)
        {
            $file = file_exists(MYBB_ROOT . 'inc/plugins/' . self::PLUGIN_DETAILS['prefix'] . '/stylesheets/' . $styleName . '.css') ?
                file_get_contents(MYBB_ROOT . 'inc/plugins/' . self::PLUGIN_DETAILS['prefix'] . '/stylesheets/' . $styleName . '.css') :
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

        $PL->stylesheet_delete(self::PLUGIN_DETAILS['prefix'], true);
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

        $html .= '<script src="'.$mybb->asset_url.'/jscripts/'.self::PLUGIN_DETAILS['prefix'].'.js?ver='.self::PLUGIN_DETAILS['version'].'"></script>' . PHP_EOL;

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