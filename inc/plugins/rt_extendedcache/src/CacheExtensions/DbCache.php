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

namespace rt\ExtendedCache\CacheExtensions;

use rt\ExtendedCache\Cache;

final class DbCache implements CacheExtensionInterface
{
    private string $cacheName;

    /**
     * Query name from parent CacheExtender
     *
     * @param string $cache_query
     */
    public function __construct(private string $cache_query)
    {
        $this->cacheName = '';
    }

    /**
     * Cache database query and return value
     *
     * @param string $cache_name Cache name for your query
     * @param int $deletion_time Deletion time in seconds when cache will be deleted
     * @return mixed
     */
    public function cache(string $cache_name, int $deletion_time = 0): DbCache
    {
        global $db;

        $this->cacheName = bin2hex($cache_name);
        $extendedCache = new Cache();

        $current_cache = $extendedCache->get($this->cacheName);

        // We have current cache, return it
        if (empty($current_cache))
        {
            // No cache found or expired
            $query = $db->write_query($this->cache_query);
            $cache = [];
            foreach ($query as $row)
            {
                $cache[] = $row;
            }
            $extendedCache->set($this->cacheName, $cache, $deletion_time);
        }

        return $this;
    }

    /**
     * Execute the cache query and return results from the cache.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        return (new Cache())->get($this->cacheName);
    }

    /**
     * Delete cache database query
     *
     * @param string $cache_name Cache name
     * @return void
     */
    public function delete(string $cache_name): void
    {
        $cache_name = bin2hex($cache_name);

        (new Cache())->delete($cache_name);
    }

}
