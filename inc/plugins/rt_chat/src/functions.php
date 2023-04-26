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

/**
 * Autoload hooks via namespace
 *
 * @param string $namespace
 * @return void
 *@copyright MyBB-Group
 *
 */
function autoload_hooks_via_namespace(string $namespace): void
{
    global $plugins;

    $namespace = strtolower($namespace);
    $user_functions = get_defined_functions()['user'];

    foreach ($user_functions as $function)
    {
        $namespace_prefix = strlen($namespace) + 1;

        if (substr($function, 0, $namespace_prefix) === $namespace . '\\')
        {
            $hook_name = substr_replace($function, '', 0, $namespace_prefix);
            $plugins->add_hook($hook_name, $namespace . '\\' . $hook_name);
        }
    }
}

/**
 * Edit origin template
 *
 * @param string $title
 * @param string $find
 * @param string $replace
 * @return void
 */
function edit_template(string $title, string $find, string $replace): void
{
    // Include this file because it is where find_replace_templatesets is defined
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Edit the index template and add our variable to above {$forums}
    find_replace_templatesets($title, '#' . preg_quote($find) . '#', $replace);
}

/**
 * Template files content loader
 *
 * @param string $path
 * @param string $ext
 * @return array
 */
function load_template_files(string $path, string $ext = '.tpl'): array
{
    $path = MYBB_ROOT . $path;
    $templates = [];

    foreach (new \DirectoryIterator($path) as $tpl)
    {
        if (!$tpl->isFile() || $tpl->getExtension() !== pathinfo($ext, PATHINFO_EXTENSION))
        {
            continue;
        }
        $name = basename($tpl->getFilename(), $ext);
        $templates[$name] = file_get_contents($tpl->getPathname());
    }

    return $templates;
}

/**
 * Cache templates on demand
 *
 * @param string|array $templates
 * @return void
 */
function load_templatelist(string|array $templates): void
{
    global $templatelist;

    $templates = match (is_array($templates))
    {
        true => implode(',', array_map(function ($template) {
            return str_replace('_', '', Core::get_plugin_info('prefix')) . '_' . $template;
        }, $templates)),
        default => str_replace('_', '', Core::get_plugin_info('prefix')) . '_' . $templates
    };

    $templatelist .= ',' . $templates;
}

/**
 * Load templates
 *
 * @param string $name
 * @param bool $modal True if you want to load no html comments for modal
 * @return string
 */
function template(string $name, bool $modal = false): string
{
    global $templates;

    $name = str_replace('_', '', Core::get_plugin_info('prefix')) . '_' . $name;

    return match ($modal)
    {
        true => $templates->get($name, 1, 0),
        default => $templates->get($name)
    };
}

/**
 * PHP version check
 *
 * @return void
 */
function check_php_version(): void
{
    if (version_compare(PHP_VERSION, '8.0.0', '<'))
    {
        flash_message("PHP version must be at least 8.0.x due to security reasons.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * RT Extended cache loader
 *
 * @return void
 */
function load_rt_extendedcache(): void
{
    global $rt_cache, $config, $mybb;

    if (!defined('RT_EXTENDEDCACHE'))
    {
        define('RT_EXTENDEDCACHE', MYBB_ROOT . 'inc/plugins/rt_extendedcache.php');
    }

    if (file_exists(RT_EXTENDEDCACHE))
    {
        if (!$rt_cache)
        {
            require_once RT_EXTENDEDCACHE;
        }
        if (version_compare((string) $rt_cache->version, '2', '<'))
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: orange">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
				RT Extended Cache (ver-{$rt_cache->version}) is outdated. You can update it by <a href="https://community.mybb.com/mods.php?action=view&pid=1558" target="_blank">clicking here</a>.
			</b>
			DESC;
        }
        else
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: green">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png" alt="">
				RT Extended Cache (ver-{$rt_cache->version}) is installed.
			</b>
			DESC;
        }
    }
    else
    {
        Core::$PLUGIN_DETAILS['description'] .= <<<DESC
		<br/>
		<b style="color: orange">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
			RT Extended Cache is missing. You can download it by <a href="https://community.mybb.com/mods.php?action=view&pid=1558" target="_blank">clicking here</a>.
		</b>
		DESC;
    }
}

/**
 * RT Extended cache install checker
 *
 * @return void
 */
function check_rt_extendedcache(): void
{
    global $rt_cache;

    if (!defined('RT_EXTENDEDCACHE'))
    {
        define('RT_EXTENDEDCACHE', MYBB_ROOT . 'inc/plugins/rt_extendedcache.php');
    }

    if (file_exists(RT_EXTENDEDCACHE))
    {
        if (!$rt_cache)
        {
            require_once RT_EXTENDEDCACHE;
        }
        if (version_compare((string) $rt_cache->version, '2', '<'))
        {
            flash_message("RT Extended Cache version is outdated. You can update it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=1558\">clicking here</a>.", "error");
            admin_redirect("index.php?module=config-plugins");
        }
    }
    else
    {
        flash_message("RT Extended Cache is missing. You can download it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=1558\">clicking here</a>.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * PluginLibrary loader
 *
 * @return void
 */
function load_pluginlibrary(): void
{
    global $PL, $config, $mybb;

    if (!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (file_exists(PLUGINLIBRARY))
    {
        if (!$PL)
        {
            require_once PLUGINLIBRARY;
        }
        if (version_compare((string) $PL->version, '13', '<'))
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: orange">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
				PluginLibrary version is outdated. You can update it by <a href="https://community.mybb.com/mods.php?action=view&pid=573" target="_blank">clicking here</a>.
			</b>
			DESC;
        }
        else
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: green">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png" alt="">
				PluginLibrary (ver-{$PL->version}) is installed.
			</b>
			DESC;
        }
    }
    else
    {
        Core::$PLUGIN_DETAILS['description'] .= <<<DESC
		<br/>
		<b style="color: orange">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
			PluginLibrary is missing. You can download it by <a href="https://community.mybb.com/mods.php?action=view&pid=573" target="_blank">clicking here</a>.
		</b>
		DESC;
    }
}

/**
 * PluginLibrary install checker
 *
 * @return void
 */
function check_pluginlibrary(): void
{
    global $PL;

    if (!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (file_exists(PLUGINLIBRARY))
    {
        if (!$PL)
        {
            require_once PLUGINLIBRARY;
        }
        if (version_compare((string) $PL->version, '13', '<'))
        {
            flash_message("PluginLibrary version is outdated. You can update it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
            admin_redirect("index.php?module=config-plugins");
        }
    }
    else
    {
        flash_message("PluginLibrary is missing. You can download it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * Plugin version loader
 *
 * @return void
 */
function load_plugin_version(): void
{
    global $cache, $mybb, $config;

    $cached_version = $cache->read(Core::get_plugin_info('prefix'));
    $current_version = Core::get_plugin_info('version');

    if (isset($cached_version['version'], $current_version))
    {
        if (version_compare($cached_version['version'], Core::get_plugin_info('version'), '<'))
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: orange">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
			RT Chat version missmatch. You need to deactivate and activate plugin again.
			</b>
			DESC;
        }
        else
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: green">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png" alt="">
			RT Chat (ver-{$current_version}) is up-to-date and ready for use.
			</b>
			DESC;
        }
    }
}

/**
 * make comma separated permissions as array
 *
 * @param string $name Settings name
 * @return array
 */
function get_settings_values(string $name): array
{
    global $mybb;

    return array_filter(
        explode(',', $mybb->settings[Core::get_plugin_info('prefix') . '_' . $name] ?? ''),
        'strlen'
    );
}