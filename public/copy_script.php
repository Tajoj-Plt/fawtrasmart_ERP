<?php
$desktopFile = __DIR__ . '/../Modules/Client/resources/views/relestion_mang_client.blade.php';
$mobileFile = __DIR__ . '/../Modules/Client/resources/views/relestion_mang_client_mobile.blade.php';

if (!file_exists($desktopFile) || !file_exists($mobileFile)) {
    die("Files not found.");
}

$desktopContent = file_get_contents($desktopFile);
$mobileContent = file_get_contents($mobileFile);

if (preg_match('/@section\(\'scripts\'\)(.*?)@endsection/s', $desktopContent, $matches)) {
    $scriptContent = $matches[1];
    
    $pattern = '/<script src="\{\{\s*asset\(\'js\/client\/relestion_mang_client\.js\'\)\s*\}\}\?v=\d+"><\/script>/';
    
    if (preg_match($pattern, $mobileContent)) {
        $newMobileContent = preg_replace($pattern, $scriptContent, $mobileContent);
        file_put_contents($mobileFile, $newMobileContent);
        echo "<h1 style='color: green; text-align: center; margin-top: 50px;'>تم نقل الأكواد بنجاح! قم بتحديث صفحة الجوال الآن.</h1>";
    } else {
        echo "<h1 style='color: orange; text-align: center; margin-top: 50px;'>تم نقل الأكواد مسبقاً، لا حاجة لعمل ذلك مرة أخرى.</h1>";
    }
} else {
    echo "<h1 style='color: red; text-align: center; margin-top: 50px;'>عذراً، لم يتم العثور على أكواد الجافاسكربت في ملف اللابتوب.</h1>";
}
