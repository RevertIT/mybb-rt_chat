<?php
/**
 * RT Extended Cache
 *
 * Is a plugin which extends native MyBB cache handler with new functionalities to ease the users and developers work.
 *
 * New functions:
 * - Set auto expiration time to the cache.
 * - Auto increment (convenient method for incrementing value)
 * - Auto decrement (convenient method for decrementing value)
 * - Cache database queries on fly
 *
 * @package rt_extendedcache
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Main files
require MYBB_ROOT . 'inc/plugins/rt_extendedcache/src/Core.php';
require MYBB_ROOT . 'inc/plugins/rt_extendedcache/src/Cache.php';
require MYBB_ROOT . 'inc/plugins/rt_extendedcache/src/CacheExtensions/CacheExtensionInterface.php';
require MYBB_ROOT . 'inc/plugins/rt_extendedcache/src/CacheExtensions/DbCache.php';
require MYBB_ROOT . 'inc/plugins/rt_extendedcache/src/CacheExtensions/ApiCache.php';

function rt_extendedcache_info(): array
{
    return [
        'name' => \rt\ExtendedCache\Core::get_plugin_info('name'),
        'description' => \rt\ExtendedCache\Core::get_plugin_info('description'),
        'website' => \rt\ExtendedCache\Core::get_plugin_info('website'),
        'author' => \rt\ExtendedCache\Core::get_plugin_info('author'),
        'authorsite' => \rt\ExtendedCache\Core::get_plugin_info('authorsite'),
        'version' => \rt\ExtendedCache\Core::get_plugin_info('version'),
        'compatibility' => \rt\ExtendedCache\Core::get_plugin_info('compatibility'),
        'codename' => \rt\ExtendedCache\Core::get_plugin_info('codename'),
    ];
}

function rt_extendedcache_install(): void
{
    \rt\ExtendedCache\Core::installed();
}

function rt_extendedcache_is_installed(): bool
{
    return false;
}

function rt_extendedcache_uninstall(): void
{
}

function rt_extendedcache_activate(): void
{
}

function rt_extendedcache_deactivate(): void
{
}

global $rt_cache;
$rt_cache = new \rt\ExtendedCache\Cache();