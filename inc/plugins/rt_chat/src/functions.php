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
    if (version_compare(PHP_VERSION, '8.1.0', '<'))
    {
        flash_message("PHP version must be at least 8.1 due to security reasons.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * PluginLibrary check loader
 *
 * @return void
 */
function load_pluginlibrary(): void
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
 * RT Extended cache check loader
 *
 * @return void
 */
function load_rt_extendedcache(): void
{
    global $rt_cache;

    if (!defined('RT_EXTENDEDCACHE'))
    {
        define('RT_EXTENDEDCACHE', MYBB_ROOT . 'inc/plugins/rt_extendedcache.php');
    }
    require_once RT_EXTENDEDCACHE;


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
        flash_message("RT Extended cache is missing. You can download it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=1558\">clicking here</a>.", "error");
        admin_redirect("index.php?module=config-plugins");
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

/**
 * Return hexed cache name from DB queries
 *
 * @param string $cache_name
 * @return string
 */
function get_rt_cache_query_name(string $cache_name): string
{
    return bin2hex($cache_name);
}