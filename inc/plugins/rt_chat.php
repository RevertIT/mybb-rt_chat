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

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

// Main files
require MYBB_ROOT . 'inc/plugins/rt_chat/src/Core.php';
require MYBB_ROOT . 'inc/plugins/rt_chat/src/functions.php';
require MYBB_ROOT . 'inc/plugins/rt_chat/src/ChatHandler/Read.php';
require MYBB_ROOT . 'inc/plugins/rt_chat/src/ChatHandler/Create.php';
require MYBB_ROOT . 'inc/class_parser.php';

// Hooks manager
require MYBB_ROOT . 'inc/plugins/rt_chat/src/Hooks/Backend.php';

if (\rt\Chat\Core::is_enabled())
{
	require MYBB_ROOT . 'inc/plugins/rt_chat/src/Hooks/Frontend.php';
}

\rt\Chat\autoload_hooks_via_namespace('rt\Chat\Hooks');
\rt\Chat\load_rt_extendedcache();
function rt_chat_info(): array
{
	return [
		'name' => \rt\Chat\Core::get_plugin_info('name'),
		'description' => \rt\Chat\Core::get_plugin_description(),
		'website' => \rt\Chat\Core::get_plugin_info('website'),
		'author' => \rt\Chat\Core::get_plugin_info('author'),
		'authorsite' => \rt\Chat\Core::get_plugin_info('authorsite'),
		'version' => \rt\Chat\Core::get_plugin_info('version'),
		'compatibility' => \rt\Chat\Core::get_plugin_info('compatibility'),
		'codename' => \rt\Chat\Core::get_plugin_info('codename'),
	];
}
function rt_chat_install(): void
{
	\rt\Chat\check_php_version();
	\rt\Chat\load_pluginlibrary();

    \rt\Chat\Core::edit_installed_templates();
    \rt\Chat\Core::add_database_modifications();
	 \rt\Chat\Core::add_settings();
	 \rt\Chat\Core::set_cache();
}
function rt_chat_is_installed(): bool
{
	return \rt\Chat\Core::is_installed();
}

function rt_chat_uninstall(): void
{
    \rt\Chat\check_php_version();
    \rt\Chat\load_pluginlibrary();

    \rt\Chat\Core::revert_installed_templates_changes();
    \rt\Chat\Core::drop_database_modifications();
    \rt\Chat\Core::remove_settings();
    \rt\Chat\Core::remove_cache();
}

function rt_chat_activate(): void
{
    \rt\Chat\check_php_version();
    \rt\Chat\load_pluginlibrary();

    \rt\Chat\Core::add_templates();
    \rt\Chat\Core::add_stylesheet();
	\rt\Chat\Core::add_settings();
	\rt\Chat\Core::set_cache();
}

function rt_chat_deactivate(): void
{
    \rt\Chat\check_php_version();
    \rt\Chat\load_pluginlibrary();

    \rt\Chat\Core::remove_templates();
    \rt\Chat\Core::remove_stylesheet();
}