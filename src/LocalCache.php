<?php

namespace AppKit\Cache\Local;

use AppKit\Cache\CacheInterface;

class LocalCache implements CacheInterface {
    private $maxSize;
    private $items = [];
    private $timeouts = [];

    function __construct($maxSize = null) {
        $this -> maxSize = $maxSize;
    }

    public function has($key) {
        $this -> gc();

        if(! isset($this -> items[$key]))
            return false;

        if($this -> isExpired($key)) {
            $this -> remove($key);
            return false;
        }

        return true;
    }

    public function get($key) {
        $this -> gc();

        if(! isset($this -> items[$key]))
            return null;

        if($this -> isExpired($key)) {
            $this -> remove($key);
            return null;
        }

        $value = $this -> items[$key];
        unset($this -> items[$key]);
        $this -> items[$key] = $value;

        return $value;
    }

    public function set($key, $value, $ttl = 0, $get = false) {
        $this -> gc();

        $oldValue = $this -> items[$key] ?? null;
        $this -> remove($key);

        $this -> items[$key] = $value;
        if($ttl)
            $this -> timeouts[$key] = time() + $ttl;

        $this -> evict();

        if($get)
            return $oldValue;
    }

    public function increment($key, $by = 1) {
        $value = $this -> get($key) ?? 0; // does gc, touch
        $this -> items[$key] = $value + $by;

        $this -> evict();
    }

    public function decrement($key, $by = 1) {
        $value = $this -> get($key) ?? 0; // does gc, touch
        $this -> items[$key] = $value - $by;

        $this -> evict();
    }

    public function delete($key) {
        $this -> gc();
        $this -> remove($key);
    }

    public function clear() {
        $this -> items = [];
        $this -> timeouts = [];
    }

    private function isExpired($key) {
        if(! isset($this -> timeouts[$key]))
            return false;
        return $this -> timeouts[$key] <= time();
    }

    private function remove($key) {
        unset($this -> items[$key]);
        unset($this -> timeouts[$key]);
    }

    private function evict() {
        if($this -> maxSize && count($this -> items) > $this -> maxSize)
            $this -> remove(array_key_first($this -> items));
    }

    private function gc() {
        for($i = 0; $i < min(3, count($this -> timeouts)); $i++) {
            $key = array_rand($this -> timeouts);
            if($this -> isExpired($key))
                $this -> remove($key);
        }
    }
}
