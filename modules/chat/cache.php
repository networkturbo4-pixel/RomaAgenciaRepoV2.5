<?php
class ChatCache {
    private static $cacheDir;
    
    public static function init() {
        self::$cacheDir = sys_get_temp_dir() . '/chat_cache/';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (!file_exists($file)) return null;
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        return $data['value'];
    }
    
    public static function set($key, $value, $ttl = 30) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = ['value' => $value, 'expires' => time() + $ttl];
        file_put_contents($file, serialize($data));
    }
    
    public static function delete($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) unlink($file);
    }
    
    public static function flush($prefix = '') {
        self::init();
        if ($prefix) {
            // Delete keys matching prefix pattern
            foreach (glob(self::$cacheDir . '*.cache') as $file) {
                unlink($file);
            }
        }
    }
}
