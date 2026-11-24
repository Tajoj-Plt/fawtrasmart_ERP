<?php
$viewsDir = __DIR__ . '/../storage/framework/views/';
if (!is_dir($viewsDir)) {
    echo "No views dir";
    exit;
}

$files = glob($viewsDir . '*.php');
$found = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Search for a specific string from the deleted part
    if (strpos($content, 'const currentUserId =') !== false) {
        $found[] = $file;
    }
}

if (count($found) > 0) {
    echo "Found cached files: \n" . implode("\n", $found);
} else {
    echo "No cached file found :(";
}
