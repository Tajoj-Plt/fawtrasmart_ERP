<?php
$dir = __DIR__ . '/../';
chdir($dir);
echo "<pre>";
echo "Restoring blade file...\n";
echo shell_exec('git checkout Modules/Client/resources/views/relestion_mang_client.blade.php 2>&1');
echo "\nRestoring mobile blade file...\n";
echo shell_exec('git checkout Modules/Client/resources/views/relestion_mang_client_mobile.blade.php 2>&1');
echo "\nChecking status:\n";
echo shell_exec('git status 2>&1');
echo "</pre>";
