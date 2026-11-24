<?php
$bladeFile = __DIR__ . '/../Modules/Client/resources/views/relestion_mang_client.blade.php';
$partialFile = __DIR__ . '/../Modules/Client/resources/views/partials/client_management_scripts.blade.php';
$mobileBladeFile = __DIR__ . '/../Modules/Client/resources/views/relestion_mang_client_mobile.blade.php';

$lines = file($bladeFile);

$start = -1;
$end = -1;
for ($i = count($lines) - 1; $i >= 0; $i--) {
    if (strpos($lines[$i], '</script>') !== false && $end === -1) {
        $end = $i;
    }
    if (strpos($lines[$i], '<script>') !== false && $end !== -1) {
        $start = $i;
        break; 
    }
}

if ($start !== -1 && $end !== -1) {
    $scriptsContent = implode("", array_slice($lines, $start, $end - $start + 1));
    if (!file_exists(dirname($partialFile))) {
        mkdir(dirname($partialFile), 0777, true);
    }
    file_put_contents($partialFile, $scriptsContent);

    array_splice($lines, $start, $end - $start + 1, ["    @include('client::partials.client_management_scripts')\n"]);
    file_put_contents($bladeFile, implode("", $lines));
}

$mobileLines = file($mobileBladeFile);
$newMobileLines = [];
foreach ($mobileLines as $line) {
    if (strpos($line, 'relestion_mang_client.js') !== false) {
        $newMobileLines[] = "    @include('client::partials.client_management_scripts')\n";
    } else {
        $newMobileLines[] = $line;
    }
}
file_put_contents($mobileBladeFile, implode("", $newMobileLines));

echo "<h1>تم إصلاح المشكلة بنجاح! يمكنك الآن تجربة الجوال</h1>";
