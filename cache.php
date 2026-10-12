<?php
class SimpleCache {
    private $dir;

    public function __construct($dir = null) {
        $this->dir = $dir ?: __DIR__ . '/cache';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function get($key, $ttl = 300) {
        $file = $this->dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.cache';
        if (!is_file($file)) {
            return null;
        }

        if (time() - filemtime($file) > $ttl) {
            @unlink($file);
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        return json_decode($contents, true);
    }

    public function set($key, $value) {
        $file = $this->dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.cache';
        return @file_put_contents($file, json_encode($value), LOCK_EX) !== false;
    }

    public function delete($key) {
        $file = $this->dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.cache';
        if (is_file($file)) {
            return @unlink($file);
        }
        return true;
    }
}
