<?php

namespace Aha\LumenMicroCore\Services;

use Cache;

class CacheService
{
     /**
     * Insert an element at the end of the queue
     * @param unknown $key
     * @param unknown $value
     * Return queue length
     */
    public static function set($key, $value, $expire = null)
    {
        if (empty($expire)) {
            $expire =   7200;
        }
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return Cache::put($key, $value, $expire);
    }
    
    public static function get($key, $decode = false)
    {   
        $data = Cache::get($key);
        return $decode ? json_decode($data) : $data;
    }

     /**
     * remove items from the cache
     * @param unknown $key
     * Return queue length
     */
    public static function forget($key)
    {
        return Cache::forget($key);
    }
}
