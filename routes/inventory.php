<?php

use Illuminate\Support\Facades\Route;

/*
    كان هذا الملفّ يعلن تسعة مسارات باسم `reports.inventory.*` تشير كلّها
    إلى `App\Http\Controllers\Inventory\InventoryReportController` — وهي فئةٌ لا
    وجود لها في المشروع، لا في `app/` ولا في أي وحدة. فكلّ طلبٍ على أيٍّ
    منها يُنتج 500، و`route:list` كان يسقط كلّه عند محاولة تحليلها.

    ولا يشير إليها شيء: لا رابطٌ في قالب، ولا استدعاءٌ باسمها في المشروع.
    وتقارير المخزون الحيّة تعيش في وحدة المخزون تحت `StockReport.*` مع
    فئاتها في `Modules/Stock/Http/Controllers/Reports/`.

    فبقيت المسارات معلَّقةً هنا لا محذوفةً، لتُستأنف إن كُتبت الفئة يوماً.
    ويُحمَّل الملفّ من `RouteServiceProvider` ثلاث مرّات — وهو تكرارٌ قائمٌ
    من قبل، تُركت معالجته لتنظيفٍ مقصود.

    require __DIR__ . '/auth.php';

    Route::group([...], function () {
        Route::prefix('reports/inventory')->group(function () {
            Route::get('/', [InventoryReportController::class, 'index'])
                ->name('reports.inventory.index');
            // ... inventoryCount, inventorySummary, inventoryMovement,
            //     inventoryValue, inventoryBalanceSummary, productTrialBalance,
            //     productMovementDetails, trackProductsByBatchAndExpiry
        });
    });
*/
