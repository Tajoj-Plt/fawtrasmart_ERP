<?php
// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully.<br>";
} else {
    echo "OPcache is not enabled or opcache_reset() is disabled.<br>";
}

// Clear Laravel views
$viewPath = __DIR__ . '/../storage/framework/views';
if (is_dir($viewPath)) {
    $files = glob($viewPath . '/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Laravel compiled views cleared successfully.<br>";
} else {
    echo "Laravel view storage path not found.<br>";
}

echo "All caches cleared!";
