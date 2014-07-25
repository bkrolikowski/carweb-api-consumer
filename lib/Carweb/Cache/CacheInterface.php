<?php

namespace Carweb\Cache;

interface CacheInterface
{
    /**
     * check if the current item is cached
     *
     * @param $key
     * @return bool
     */
    public function has($key);

    /**
     * gets cached value for current item
     *
     * @param $key
     * @return string|null
     */
    public function get($key);

    /**
     * Saves the value to cache
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function save($key, $value);

    /**
     * Clears the current value
     *
     * @param $key
     * @return void
     */
    public function clear($key);
}