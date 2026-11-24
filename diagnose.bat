@echo off
chcp 65001 >nul
cd /d "%~dp0"

set OUT=storage\logs\_diag.txt

echo ============================================================ > "%OUT%" 2>&1
echo  1. migrate:status  (ما شُغّل وما لم يُشغّل) >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
php artisan migrate:status >> "%OUT%" 2>&1

echo. >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
echo  2. composer dump-autoload  (الأصناف الجديدة) >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
call composer dump-autoload >> "%OUT%" 2>&1
if errorlevel 1 echo [!] composer غير موجود في PATH >> "%OUT%" 2>&1

echo. >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
echo  3. migrate  (هجرتا أوامر التوريد والأقساط وحدهما) >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
php artisan migrate --force --path=database/migrations/2026_08_21_140000_upgrade_supply_orders_for_erp.php >> "%OUT%" 2>&1
php artisan migrate --force --path=database/migrations/2026_08_22_090000_upgrade_installments_for_erp.php >> "%OUT%" 2>&1

echo. >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
echo  4. permissions:sync  (صلاحيات installments_*) >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
php artisan permissions:sync >> "%OUT%" 2>&1

echo. >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
echo  5. optimize:clear >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
php artisan optimize:clear >> "%OUT%" 2>&1

echo. >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
echo  6. اخر 250 سطر من laravel.log >> "%OUT%" 2>&1
echo ============================================================ >> "%OUT%" 2>&1
powershell -NoProfile -Command "Get-Content 'storage\logs\laravel.log' -Tail 250" >> "%OUT%" 2>&1

echo.
echo ============================================================
echo  تم.  الملف:  storage\logs\_diag.txt
echo.
echo  الان: افتح الصفحتين مرة اخرى في المتصفح.
echo  ان بقي الخطا، شغّل هذا الملف مرة ثانية ثم قل لي "تم"
echo  حتى اقرا اخر الاستثناءات.
echo ============================================================
echo.
pause
