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

interface CacheExtensionInterface
{
    /**
     * Cache query and return value
     *
     * @param string $cache_name Cache name for your query
     * @param int $deletion_time Deletion time in seconds when cache will be deleted
     * @return DbCache
     */
    public function cache(string $cache_name, int $deletion_time = 0): CacheExtensionInterface;

    /**
     * Execute the cache and return results from the cache.
     *
     * @return mixed
     */
    public function execute(): mixed;

    /**
     * Delete cache
     *
     * @param string $cache_name Cache name
     * @return void
     */
    public function delete(string $cache_name): void;
}