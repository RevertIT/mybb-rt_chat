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

namespace rt\ExtendedCache;

class Core
{
    private const PLUGIN_DETAILS = [
        'name' => 'RT Extended Cache',
        'description' => 'Powerful cache extender for MyBB. Can be used with plugins for easier caching logics.',
        'website' => 'https://github.com/RevertIT/mybb-rt_extendedcache',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '2.0',
        'compatibility' => '18*',
        'codename' => 'rt_extendedcache',
        'prefix' => 'rt_extendedcache',
    ];

    /**
     * Get plugin details
     *
     * @param string $info Plugin info to return
     * @return string|null
     */
    public static function get_plugin_info(string $info): ?string
    {
        if (isset(self::PLUGIN_DETAILS[$info]))
        {
            return self::PLUGIN_DETAILS[$info];
        }

        return null;
    }

    public static function installed(): void
    {
        flash_message("You have successfully installed " . self::PLUGIN_DETAILS['name'] . " version " . self::PLUGIN_DETAILS['version'] . ".", 'success');
        admin_redirect("?module=config-plugins");
    }
}
