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

use \datacache;
use rt\ExtendedCache\CacheExtensions\ApiCache;
use rt\ExtendedCache\CacheExtensions\CacheExtensionInterface;
use rt\ExtendedCache\CacheExtensions\DbCache;

class Cache
{
    private datacache $cache;
    private string $cache_prefix;
    private string $cache_prefix_int;

    public int $version;

    public function __construct()
    {
        $this->cache = new datacache();
        $this->cache->cache();

        $this->cache_prefix = Core::get_plugin_info('prefix') . '-';
        $this->cache_prefix_int = 'int-';

        $this->version = $this->version();
    }

    /**
     * Plugin version as integer
     *
     * @return int
     */
    private function version(): int
    {
        return (int) Core::get_plugin_info('version');
    }

    /**
     * Set cache
     *
     * @param string $name Cache name
     * @param mixed $data Cache data
     * @param int $deletion_time When cache should be deleted in seconds | 0 = never expire
     * @return void
     */
    public function set(string $name, mixed $data, int $deletion_time = 0): void
    {
        $name = $this->cache_prefix . $name;

        $contents = [
            'cached_at' => TIME_NOW,
            'deletion_time' => $deletion_time,
            'data' => $data,
        ];

        $this->cache->update($name, $contents);
    }

    /**
     * Get cache data
     *
     * @param string $name name of the cache key
     * @return mixed
     */
    public function get(string $name): mixed
    {
        $name = $this->cache_prefix . $name;

        if (empty($this->cache->read($name)))
        {
            return null;
        }

        $cache = $this->cache->read($name);

        // Delete cache if expired
        if (isset($cache['cached_at'], $cache['deletion_time']) && (int) $cache['deletion_time'] > 0)
        {
            if ($cache['cached_at'] < TIME_NOW - $cache['deletion_time'])
            {
                $this->cache->delete($name);
            }
        }

        return $this->cache->read($name)['data'];
    }

    /**
     * Delete cache
     *
     * @param string $name
     * @return void
     */
    public function delete(string $name): void
    {
        $name = $this->cache_prefix . $name;
        $this->cache->delete($name);
    }

    /**
     * increase the key number in cache by amount.
     *
     * @param string $name Name of the cache key
     * @param int $amount Increase amount of number by X times. 0 = delete cache
     * @return int
     */
    public function increment(string $name, int $amount = 1): int
    {
        $name = $this->cache_prefix . $this->cache_prefix_int . $name;

        if ($amount <= 0)
        {
            $this->cache->delete($name);
            return 0;
        }

        $this->cache->update($name, (int) $this->cache->read($name) + $amount);

        return $this->cache->read($name);
    }

    /**
     * Decrease the number in cache by amount.
     *
     * @param string $name
     * @param int $amount Decrease amount of number by X times. 0 = delete cache
     * @return int
     */
    public function decrement(string $name, int $amount = 1): int
    {
        $name = $this->cache_prefix . $this->cache_prefix_int . $name;

        if ($amount === 0)
        {
            $this->cache->delete($name);
            return 0;
        }

        $this->cache->update($name, (int) $this->cache->read($name) - $amount);

        return $this->cache->read($name);
    }

    /**
     * Database query to run. Make sure to escape all user input before passing data
     *
     * @example Example on how to use query cache
     * $uid = 1;
     * $user = $rt_cache->query("select * from ".TABLE_PREFIX."users WHERE uid = '{$db->escape_string($uid)}'")->cache('cached_user_data', 3600);
     *
     * @param string $query SQL Query
     * @return CacheExtensionInterface DBCache object for chaining further options
     */
    public function query(string $query): CacheExtensionInterface
    {
        return new DbCache($query);
    }

    /**
     * Fetch remote API requests and get data from cache. Extending native MyBB remote api
     *
     * @param string $url
     * @param array $post_data
     * @param int $max_redirects
     * @return CacheExtensionInterface ApiCache object for chaining further options
     */
    public function api(string $url, array $post_data = [], int $max_redirects = 20): CacheExtensionInterface
    {
        return new ApiCache($url, $post_data, $max_redirects);
    }

}
