<?php

// استيرادٌ لفئةٍ غير موجودة ولا يُستعمل في هذا الملفّ — أُزيل.
use App\Http\Controllers\EmployeeTargetController;
use App\Http\Controllers\StatisticsController;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Client\Http\Controllers\ClientSettingController;
use Modules\Client\Http\Controllers\VisitController;

// ==================== Debug Routes ====================
// Debug route to check logo paths
Route::get('/debug-logo', function () {
    $account_setting = \App\Models\AccountSetting::first();
    $client = \App\Models\Client::first();

    return [
        'account_setting' => $account_setting ? $account_setting->toArray() : null,
        'client' => $client ? $client->toArray() : null,
        'request_base_url' => request()->getBaseUrl(),
        'request_root' => request()->getRoot(),
        'asset_url' => asset('storage/'.($account_setting->attachments ?? '')),
        'request_base_url_logo' => request()->getBaseUrl().'/storage/'.($account_setting->attachments ?? ''),
    ];
});

Route::get('/debug-client-relations', function () {
    $client = Client::with([
        'clientRelations' => function ($query) {
            $query->with(['employee', 'location'])
                ->orderBy('created_at', 'desc');
        },
    ])->first();

    if (! $client) {
        return response()->json(['error' => 'No clients found']);
    }

    $clientRelations = $client->clientRelations->map(function ($relation) {
        // معالجة نوع الموقع لعرضه بالعربية
        $siteTypeText = '';
        switch ($relation->site_type) {
            case 'independent_booth':
                $siteTypeText = 'بسطة مستقلة';
                break;
            case 'grocery':
                $siteTypeText = 'بقالة';
                break;
            case 'supplies':
                $siteTypeText = 'تموينات';
                break;
            case 'markets':
                $siteTypeText = 'أسواق';
                break;
            case 'station':
                $siteTypeText = 'محطة';
                break;
            default:
                $siteTypeText = $relation->site_type;
        }

        // معالجة المرفقات
        $attachmentsArray = [];
        if ($relation->attachments) {
            if (is_string($relation->attachments)) {
                $decoded = json_decode($relation->attachments, true);
                if (is_array($decoded)) {
                    $attachmentsArray = $decoded;
                } else {
                    $attachmentsArray = [$relation->attachments];
                }
            } elseif (is_array($relation->attachments)) {
                $attachmentsArray = $relation->attachments;
            }
        }

        return [
            'id' => $relation->id,
            'client_id' => $relation->client_id,
            'status' => $relation->status,
            'quotation_id' => $relation->quotation_id,
            'invoice_id' => $relation->invoice_id,
            'description' => $relation->description,
            'process' => $relation->process,
            'type' => $relation->type,
            'date' => $relation->date,
            'time' => $relation->time,
            'employee_id' => $relation->employee_id,
            'employee' => $relation->employee->name ?? 'غير محدد',
            'employee_email' => $relation->employee->email ?? null,
            'location_id' => $relation->location_id,
            'location' => $relation->location ? [
                'id' => $relation->location->id,
                'latitude' => $relation->location->latitude,
                'longitude' => $relation->location->longitude,
                'address' => $relation->location->address,
            ] : null,
            'deposit_count' => $relation->deposit_count,
            'employee_view_status' => $relation->employee_view_status,
            'site_type' => $relation->site_type,
            'site_type_text' => $siteTypeText,
            'competitor_documents' => $relation->competitor_documents,
            'additional_data' => $relation->additional_data,
            'attachments' => $relation->attachments,
            'attachments_array' => $attachmentsArray,
            'created_at' => $relation->created_at,
            'updated_at' => $relation->updated_at,
        ];
    });

    return response()->json([
        'client_id' => $client->id,
        'client_name' => $client->trade_name,
        'relations_count' => $clientRelations->count(),
        'relations' => $clientRelations,
    ]);
});

// Test route for checking incomplete visits
Route::get('/test-incomplete-visits', function () {
    if (! \Illuminate\Support\Facades\Auth::check()) {
        return response()->json(['message' => 'Not authenticated']);
    }

    $user = \Illuminate\Support\Facades\Auth::user();

    if (! $user->isEmployee()) { // ✅ isEmployee() بدلاً من عمود role النصي
        return response()->json(['message' => 'Not an employee']);
    }

    $incompleteVisits = \App\Models\EmployeeClientVisit::where('employee_id', $user->id)
        ->needsJustification()
        ->get();

    return response()->json([
        'user' => $user->name,
        'incomplete_visits_count' => $incompleteVisits->count(),
        'incomplete_visits' => $incompleteVisits,
    ]);
})->middleware('auth');

// ==================== Test & Reports Routes ====================
// حُذف `/test-send` و`/test/send`: مساران عامّان بلا `auth` ينفّذان
// `ClientSettingController::test()` — تقرأ مبيعاتِ كل موظف ومقبوضاتِه
// لليوم وترسلها إلى قناة تيليجرام ثم `dd()` الرد. أيُّ زائرٍ على الشبكة
// كان يقدر ينشر أرقام المنشأة اليومية بفتح رابط.
//
// نفس السبب حُذفت له `/send-daily-report`/`/send-weekly-report`/
// `/send-monthly-report` هنا (كانت بلا `auth` أيضاً) — النسخة المحروسة
// داخل `Route::prefix('visits')->middleware('client.feature:visits')`
// (`routes/web.php` أسفل، أو `Modules\Client\routes\web.php`) تبقى
// للتشغيل اليدوي/الإداري، والمسار الحي الآن هو الإرسال عند إقفال يومية
// الموظف — راجع `DailyClosingController::store()`.

// مسار لتقييم أهداف العملاء آلياً عبر رابط ويب (Web Cron)
Route::get('/evaluate-customer-targets', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('clients:evaluate-targets');

        return response()->json(['success' => true, 'message' => 'تم تقييم أهداف العملاء بنجاح وإرسال الإشعار للإدارة.']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'حدث خطأ: '.$e->getMessage()]);
    }
});

// ==================== Client Data Route ====================
Route::get('/client-data/{clientId}', function ($clientId) {
    $client = Client::with(['latestStatus'])->findOrFail($clientId);

    $invoices = Invoice::where('client_id', $clientId)
        ->with(['items', 'payments_process'])
        ->orderBy('created_at', 'desc')
        ->get();

    $notes = ClientRelation::with(['employee', 'location'])
        ->where('client_id', $clientId)
        ->latest()
        ->get();

    return response()->json([
        'client' => $client,
        'invoices' => $invoices,
        'notes' => $notes,
    ]);
})->name('client.data');

/* ==================== بوابة النقاط القديمة — أُحيلت إلى البوابة الواحدة ====================

   كانت `/client-portal` بوابةً ثانيةً للعميل: عنوانٌ آخر وقالبٌ آخر ودخولٌ
   **برقم الهاتف وحده** — فمن يعرف رقم عميلٍ يفتح حسابه ويقرأ نقاطه وإحالاته
   بلا كلمة مرور. وطلب المستخدم (2026-09-19) بوابةً واحدة، فصار قسمُ النقاط
   داخل بوابة العميل (`clients.portal.points`) بدخولها وحارسها وتصميمها.

   الأسماءُ تبقى مسجَّلةً لا تُحذف: روابطُ قديمةٌ محفوظةٌ عند العملاء، ورسائلُ
   سابقةٌ تحملها — تحويلٌ إلى القسم الجديد خيرٌ من صفحة «غير موجود». ومن لم
   يسجّل دخوله يقع على شاشة الدخول الموحّدة.

   والجلسةُ القديمة (`client_portal_id`) تُنسى في التحويل: مفتاحُ دخولٍ
   بلا كلمة مرور لا يُترك حيّاً في جلسات من دخلوا قبل التوحيد. */
Route::prefix('client-portal')->group(function () {
    $toUnifiedPortal = function (\Illuminate\Http\Request $request) {
        $request->session()->forget(['client_portal_id', 'client_portal_name']);

        if (! auth()->check()) {
            return redirect()->route('login')
                ->with('error', 'بوابة العميل صارت واحدة — سجّل الدخول بحسابك لترى نقاطك وفواتيرك وحجوزاتك.');
        }

        return redirect()->route(
            \App\Support\ClientPortalPermissions::enabled('view_points_rewards')
                ? 'clients.portal.points'
                : 'clients.personal'
        );
    };

    Route::get('/', $toUnifiedPortal)->name('client-portal.login');
    Route::post('/login', $toUnifiedPortal)->name('client-portal.login.submit');
    Route::get('/dashboard', $toUnifiedPortal)->name('client-portal.dashboard');
    Route::post('/referral', $toUnifiedPortal)->name('client-portal.referral.submit');

    // الخروجُ يخرج فعلاً — لا يحوّل إلى شاشةٍ تطلب دخولاً بحسابٍ ما زال قائماً
    Route::get('/logout', function (\Illuminate\Http\Request $request) {
        $request->session()->forget(['client_portal_id', 'client_portal_name']);

        return redirect()->route('login');
    })->name('client-portal.logout');
});

/* ==================== تبريرات الزيارات — انتقلت إلى Modules/Client ====================
   كانت هنا ثلاث كتل: الجزء الذاتي-الخدمة الحقيقي (IncompleteVisitsController،
   أسماء الروابط employee.visit-justifications و incomplete.visits) — انتقل
   بأسماء روابطه نفسها؛ الجزء الإداري (Admin\VisitJustificationController —
   index/approve/reject/approveAll/approveByEmployee/adminShow) — انتقل
   أيضاً بأسماء روابطه نفسها، وصلاحيته صارت permission:employee_visit_justifications_view
   بدل role:admin|manager الخام؛ وكتلةٌ ثالثة كاملة (أسماء روابطها
   my-visit-justifications، وكذلك incomplete.visits.submit.post، على نفس
   المتحكّم الإداري) تأكَّد أنها ميتة كلياً — لا رابط في أي شاشة يشير إلى
   أيٍّ من أسماء روابطها — فحُذفت بلا نقل.
   راجع Modules/Client/routes/web.php وModules/Client/Http/Controllers/
   IncompleteVisitsController.php وVisitJustificationController.php. */
// ==================== Auth Routes ====================
require __DIR__.'/auth.php';

// ==================== Text Editor Route ====================
Route::get('/text/editor', function () {
    return view('text_editor');
});

// ==================== Localized Routes ====================
Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch'],
    ],
    function () {
        // Client Access Routes
        Route::middleware(['auth', 'client.access'])->group(function () {
            Route::get('/personal', [ClientSettingController::class, 'personal'])->name('clients.personal');
            Route::get('/invoice/client', [ClientSettingController::class, 'invoice_client'])->name('clients.invoice_client');
            Route::get('/appointments/client', [ClientSettingController::class, 'appointments_client'])->name('clients.appointments_client');
            Route::get('/questions/client', [ClientSettingController::class, 'questions_client'])->name('clients.questions_client');
            Route::get('/edit/profile', [ClientSettingController::class, 'profile'])->name('clients.profile');

            // Employee Targets
            Route::get('/employee-targets', [EmployeeTargetController::class, 'index'])->name('employee_targets.index');
            Route::post('/employee-targets', [EmployeeTargetController::class, 'storeOrUpdate'])->name('employee_targets.store');
            Route::get('/general-target', [EmployeeTargetController::class, 'showGeneralTarget'])->name('target.show');
            Route::post('/general-target', [EmployeeTargetController::class, 'updateGeneralTarget'])->name('target.update');
            Route::get('/client-target', [EmployeeTargetController::class, 'client_target'])->name('target.client');

            // التحصيل اليومي
            Route::get('/daily_closing_entry', [EmployeeTargetController::class, 'daily_closing_entry'])->name('daily_closing_entry');

            // احصائيات الزيارات
            Route::get('/visitTarget', [EmployeeTargetController::class, 'visitTarget'])->name('visitTarget');
            Route::post('/visitTarget', [EmployeeTargetController::class, 'updatevisitTarget'])->name('target.visitTarget');

            // احصائيات الفروع
            Route::get('/statistics_branch', [StatisticsController::class, 'StatisticsGroup'])->name('statistics.group');

            // احصائيات المجموعات
            Route::get('/statistics_group', [StatisticsController::class, 'Group'])->name('statistics.groupall');

            // احصائيات الاحياء
            Route::get('/statistics_neighborhood', [StatisticsController::class, 'neighborhood'])->name('statistics.neighborhood');
        });

        // Visits Routes
        Route::prefix('visits')->middleware('client.feature:visits')->group(function () {
            Route::post('/visits', [VisitController::class, 'storeEmployeeLocation'])->name('visits.storeEmployeeLocation');
            Route::put('/visits/{id}', [VisitController::class, 'update'])
                ->middleware('auth')
                ->name('visits.update');
            Route::get('/visits/today', [VisitController::class, 'getTodayVisits'])
                ->middleware('auth')
                ->name('visits.today');

            // الانصراف اليدوي
            Route::post('/manual-departure/{visitId}', [VisitController::class, 'manualDeparture'])->name('visits.manualDeparture');

            // Routes للتحسين
            Route::post('/clear-visits-data', [VisitController::class, 'clearVisitsData'])->name('visits.clearData');
        });

        // Logs Routes
        Route::prefix('logs')
            ->middleware(['auth'])
            ->group(function () {
                // Route::get('/index', [LogController::class, 'index'])->name('logs.index');
            });

        // Task Manager Routes
        Route::prefix('task-manager')
            ->middleware(['auth'])
            ->group(function () {
                Route::post('tasks/{task}/move-to-sprint', [\Modules\TaskManager\Http\Controllers\TaskController::class, 'moveToSprint'])
                    ->name('task-manager.moveToSprint');
            });

        // Stock Dashboard Route
        Route::get('/stock', function () {
            return redirect()->route('stock.dashboard');
        })->name('stock.department.dashboard');

        // Purchases Dashboard Route
        Route::get('/purchases', function () {
            return redirect()->route('purchases.dashboard');
        })->name('purchases.department.dashboard');

        // Clear Cache Route - مسح الكاش
        Route::post('/clear-cache', function () {
            try {
                \Artisan::call('cache:clear');
                \Artisan::call('config:clear');
                \Artisan::call('view:clear');
                \Artisan::call('route:clear');

                return response()->json([
                    'success' => true,
                    'message' => 'تم مسح جميع أنواع الكاش بنجاح',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage(),
                ], 500);
            }
        })->name('clear-cache');

        // System Sync Route - مزامنة قاعدة البيانات والصلاحيات
        Route::post('/system-sync', function () {
            if (! auth()->user()->hasRole('admin') && ! auth()->user()->hasRole('manager') && ! auth()->user()->hasRoleName('main')) { // ✅ hasRoleName() بدلاً من عمود role النصي
                return response()->json(['success' => false, 'message' => 'غير مصرح لك بإجراء هذه العملية.'], 403);
            }
            try {
                // استيراد أي أعمدة أو جداول جديدة
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

                // استيراد الصلاحيات والأدوار الأساسية إن وجدت في الـ Seeder
                if (class_exists('Database\\Seeders\\JobRolesTableSeeder')) {
                    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'JobRolesTableSeeder', '--force' => true]);
                }

                // استيراد أو تحديث الصلاحيات من موديل JobRole
                try {
                    \Illuminate\Support\Facades\Artisan::call('permissions:sync');
                } catch (\Exception $e) {
                    // Ignore if command doesn't exist
                }

                return response()->json([
                    'success' => true,
                    'message' => 'تم استيراد التحديثات والأعمدة والصلاحيات بنجاح',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('System Sync Error: '.$e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine(),
                ], 500);
            }
        })->name('system.sync');

        // Product Exchange Routes - تم نقلها إلى Modules/Sales
        // Route::prefix('product-exchanges')
        //     ->middleware(['auth'])
        //     ->group(function () {
        //         Route::get('/', [\App\Http\Controllers\ProductExchangeController::class, 'index'])->name('product-exchanges.index');
        //         Route::get('/create', [\App\Http\Controllers\ProductExchangeController::class, 'create'])->name('product-exchanges.create');
        //         Route::post('/', [\App\Http\Controllers\ProductExchangeController::class, 'store'])->name('product-exchanges.store');
        //         Route::get('/{id}', [\App\Http\Controllers\ProductExchangeController::class, 'show'])->name('product-exchanges.show');
        //         Route::get('/{id}/edit', [\App\Http\Controllers\ProductExchangeController::class, 'edit'])->name('product-exchanges.edit');
        //         Route::put('/{id}', [\App\Http\Controllers\ProductExchangeController::class, 'update'])->name('product-exchanges.update');
        //         Route::delete('/{id}', [\App\Http\Controllers\ProductExchangeController::class, 'destroy'])->name('product-exchanges.destroy');
        //     });

        // ==================== Employee Profile Routes ====================
        Route::prefix('employee/profile')
            ->middleware(['auth'])
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\EmployeeProfileController::class, 'index'])->name('employee.profile');
                Route::post('/request-reward', [\App\Http\Controllers\EmployeeProfileController::class, 'requestReward'])->name('employee.profile.request-reward');
            });
    }
);
