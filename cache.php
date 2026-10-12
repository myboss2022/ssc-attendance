<?php
/**
 * Simple File-Based Caching Layer
 * Usage: $cache = new SimpleCache('/tmp/ssc-cache', 3600);
 *        $data = $cache->get('key') ?? $cache->set('key', fn() => expensive_operation());
 */
final class SimpleCache {
    private $cacheDir;
    private $ttl; // Time to live in seconds
    private $enabled;

    public function __construct($cacheDir = null, $ttl = 3600) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/ssc-cache';
        $this->ttl = $ttl;
        $this->enabled = @mkdir($this->cacheDir, 0755, true) || is_dir($this->cacheDir);
    }

    public function get($key) {
        if (!$this->enabled) return null;
        $file = $this->getFilePath($key);
        if (!file_exists($file)) return null;
        $data = @file_get_contents($file);
        if ($data === false) return null;
        $payload = @json_decode($data, true);
        if (!is_array($payload) || empty($payload['expires']) || time() > $payload['expires']) {
            @unlink($file);
            return null;
        }
        return $payload['data'] ?? null;
    }

    public function set($key, $value) {
        if (!$this->enabled) return false;
        $file = $this->getFilePath($key);
        $payload = [
            'data' => $value,
            'expires' => time() + $this->ttl,
            'created' => time()
        ];
        return @file_put_contents($file, json_encode($payload), LOCK_EX) !== false;
    }

    public function delete($key) {
        if (!$this->enabled) return false;
        return @unlink($this->getFilePath($key));
    }

    public function clear() {
        if (!$this->enabled) return false;
        $files = @glob($this->cacheDir . '/*.cache');
        if (!$files) return true;
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    private function getFilePath($key) {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . $safe . '.cache';
    }
}
