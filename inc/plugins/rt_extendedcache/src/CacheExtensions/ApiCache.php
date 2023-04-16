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

final class ApiCache implements CacheExtensionInterface
{
    private string $cacheName;

    /**
     * Data from the parent cache extender
     *
     * @param string $url
     * @param array $post_data
     * @param int $max_redirects
     */
    public function __construct(private string $url, private array $post_data = [], private int $max_redirects = 20)
    {
        $this->cacheName = '';
    }

    /**
     * Cache remote API query
     *
     * @param string $cache_name Cache name for your query
     * @param int $deletion_time Deletion time in seconds when cache will be deleted
     * @return mixed
     */
    public function cache(string $cache_name, int $deletion_time = 0): ApiCache
    {

        $this->cacheName = bin2hex($cache_name);
        $extendedCache = new Cache();

        $current_cache = $extendedCache->get($this->cacheName);

        // No cache found or expired
        if (empty($current_cache))
        {
            $api = fetch_remote_file($this->url, $this->post_data, $this->max_redirects);

            $extendedCache->set($this->cacheName, $api, $deletion_time);
        }

        return $this;
    }

    /**
     * Execute the cache and return results from the cache.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        return (new Cache())->get($this->cacheName);
    }

    /**
     * Delete cache
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
