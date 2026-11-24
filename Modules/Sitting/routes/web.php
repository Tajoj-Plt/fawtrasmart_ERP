<?php

use Illuminate\Support\Facades\Route;
use Modules\Sitting\Http\Controllers\AccountingPeriodController;
use Modules\Sitting\Http\Controllers\AccountingSettingsController;
use Modules\Sitting\Http\Controllers\ActivityLogController;
use Modules\Sitting\Http\Controllers\ApiKeyController;
use Modules\Sitting\Http\Controllers\AppearanceController;
use Modules\Sitting\Http\Controllers\ApplicationManagementController;
use Modules\Sitting\Http\Controllers\BackupController;
use Modules\Sitting\Http\Controllers\BackupScheduleController;
use Modules\Sitting\Http\Controllers\CacheController;
use Modules\Sitting\Http\Controllers\CronExecutionController;
use Modules\Sitting\Http\Controllers\CronJobController;
use Modules\Sitting\Http\Controllers\CurrencyController;
use Modules\Sitting\Http\Controllers\DatabaseSizeController;
use Modules\Sitting\Http\Controllers\DataExportController;
use Modules\Sitting\Http\Controllers\DocumentTemplateController;
use Modules\Sitting\Http\Controllers\ErrorLogController;
use Modules\Sitting\Http\Controllers\EventLogController;
use Modules\Sitting\Http\Controllers\FawtraSubscriptionController;
use Modules\Sitting\Http\Controllers\LanguageController;
use Modules\Sitting\Http\Controllers\MaintenanceModeController;
use Modules\Sitting\Http\Controllers\NotificationSettingController;
use Modules\Sitting\Http\Controllers\PaymentMethodsController;
use Modules\Sitting\Http\Controllers\SequenceNumberingController;
use Modules\Sitting\Http\Controllers\SessionLogController;
use Modules\Sitting\Http\Controllers\SittingAccountController;
use Modules\Sitting\Http\Controllers\SittingInfoController;
use Modules\Sitting\Http\Controllers\SMPTController;
use Modules\Sitting\Http\Controllers\SmsController;
use Modules\Sitting\Http\Controllers\SmsLogController;
use Modules\Sitting\Http\Controllers\SmsTemplateController;
use Modules\Sitting\Http\Controllers\SubscriptionController;
use Modules\Sitting\Http\Controllers\SystemUpdateController;
use Modules\Sitting\Http\Controllers\TaxSittingController;
use Modules\Sitting\Http\Controllers\TelegramSettingsController;
use Modules\Sitting\Http\Controllers\UserManagement\PermissionsController;
use Modules\Sitting\Http\Controllers\UserManagement\RolesController;
use Modules\Sitting\Http\Controllers\UserManagement\UsersController;
use Modules\Sitting\Http\Controllers\UserSessionController;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch', 'auth'],
], function () {
    Route::prefix('accountInfo')->group(function () {
        Route::get('/index', [SittingInfoController::class, 'index'])->name('AccountInfo.index');
        Route::get('/create', [SittingInfoController::class, 'create'])->name('AccountInfo.create');
        Route::post('/store', [SittingInfoController::class, 'store'])->name('AccountInfo.store');
        Route::get('/backup', [SittingInfoController::class, 'backup'])->name('AccountInfo.backup');
        Route::post('/backup/download', [SittingInfoController::class, 'download'])->name('AccountInfo.download');
    });

    Route::prefix('SittingAccount')->group(function () {
        Route::get('/index', [SittingAccountController::class, 'index'])->name('SittingAccount.index');
        Route::get('/dashboard', [SittingAccountController::class, 'dashboard'])->name('SittingAccount.dashboard');
        Route::post('/create', [SittingAccountController::class, 'store'])->name('SittingAccount.store');
        Route::post('/Change_email', [SittingAccountController::class, 'Change_email'])->name('SittingAccount.Change_email');
        Route::post('/change_password', [SittingAccountController::class, 'change_password'])->name('SittingAccount.change_password');
        // المظهر انتقل إلى شاشته الخاصة — المساران القديمان يبقيان عاملَين
        Route::get('/backgroundColor', [AppearanceController::class, 'legacyColor'])->name('AccountInfo.backgroundColor');
        Route::post('/updateColor', [AppearanceController::class, 'legacyUpdateColor'])->name('AccountInfo.updateColor');

        // Currency routes
        Route::get('/currency', [CurrencyController::class, 'index'])->name('SittingAccount.currency');
        Route::post('/currency', [CurrencyController::class, 'storeAccountCurrency'])->name('SittingAccount.currency.store');
        Route::post('/currency/toggle', [CurrencyController::class, 'toggleActive'])->name('SittingAccount.currency.toggleActive');

        // Language routes (المسارات القائمة — أسماؤها مستخدمة في القائمة الجانبية والشاشات)
        Route::get('/language', [LanguageController::class, 'index'])->name('SittingAccount.language');
        Route::post('/language', [LanguageController::class, 'storeAccountLanguage'])->name('SittingAccount.language.store');
        Route::post('/language/toggle', [LanguageController::class, 'toggleActive'])->name('SittingAccount.language.toggleActive');
    });

    Route::prefix('appearance')->group(function () {
        Route::get('/', [AppearanceController::class, 'index'])->name('Appearance.index');
        Route::post('/update', [AppearanceController::class, 'update'])->name('Appearance.update');
        Route::post('/reset', [AppearanceController::class, 'reset'])->name('Appearance.reset');
    });

    // إدارة اللغات الكاملة — أسماء قياسية languages.* بجانب المسارات القديمة أعلاه
    Route::prefix('languages')->name('languages.')->group(function () {
        Route::get('/', [LanguageController::class, 'index'])->name('index');
        Route::get('/create', [LanguageController::class, 'create'])->name('create');
        Route::post('/', [LanguageController::class, 'store'])->name('store');
        Route::get('/{id}', [LanguageController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/edit', [LanguageController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [LanguageController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [LanguageController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/{id}/toggle-status', [LanguageController::class, 'toggleStatus'])->whereNumber('id')->name('toggle-status');
        Route::post('/{id}/set-default', [LanguageController::class, 'setDefault'])->whereNumber('id')->name('set-default');
        // جدول مفاتيح الترجمة
        Route::get('/{id}/translations/group', [LanguageController::class, 'showTranslationGroup'])->whereNumber('id')->name('translations.group');
        Route::post('/{id}/translations', [LanguageController::class, 'updateTranslations'])->whereNumber('id')->name('translations.update');
        Route::delete('/{id}/translations/group', [LanguageController::class, 'destroyTranslationGroup'])->whereNumber('id')->name('translations.group.destroy');
    });

    // Log Routes
    Route::prefix('logs')->group(function () {
        // Activity Logs
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('ActivityLog.index');
        Route::get('/activity/show/{id}', [ActivityLogController::class, 'show'])->name('ActivityLog.show');
        Route::delete('/activity/destroy/{id}', [ActivityLogController::class, 'destroy'])->name('ActivityLog.destroy');
        Route::post('/activity/bulk-delete', [ActivityLogController::class, 'bulkDelete'])->name('ActivityLog.bulk-delete');
        Route::post('/activity/cleanup', [ActivityLogController::class, 'cleanup'])->name('ActivityLog.cleanup');

        // Event Logs
        Route::get('/events', [EventLogController::class, 'index'])->name('EventLogs.index');
        Route::get('/events/show/{id}', [EventLogController::class, 'show'])->name('EventLogs.show');
        Route::delete('/events/destroy/{id}', [EventLogController::class, 'destroy'])->name('EventLogs.destroy');
        Route::post('/events/bulk-delete', [EventLogController::class, 'bulkDelete'])->name('EventLogs.bulk-delete');
        Route::post('/events/cleanup', [EventLogController::class, 'cleanup'])->name('EventLogs.cleanup');

        // User Sessions
        Route::get('/sessions', [UserSessionController::class, 'index'])->name('UserSessions.index');
        Route::get('/sessions/show/{id}', [UserSessionController::class, 'show'])->name('UserSessions.show');
        Route::delete('/sessions/destroy/{id}', [UserSessionController::class, 'destroy'])->name('UserSessions.destroy');
        Route::post('/sessions/bulk-delete', [UserSessionController::class, 'bulkDelete'])->name('UserSessions.bulk-delete');
        Route::post('/sessions/cleanup', [UserSessionController::class, 'cleanup'])->name('UserSessions.cleanup');

        // سجلّ الجلسات: تاريخ الدخول والخروج، مقابل شاشة الجلسات القائمة
        Route::get('/session-logs', [SessionLogController::class, 'index'])->name('SessionLogs.index');
        Route::get('/session-logs/{sessionLog}', [SessionLogController::class, 'show'])->name('SessionLogs.show');
        Route::post('/session-logs/{sessionLog}/terminate', [SessionLogController::class, 'terminate'])->name('SessionLogs.terminate');
        Route::delete('/session-logs/{sessionLog}', [SessionLogController::class, 'destroy'])->name('SessionLogs.destroy');
        Route::post('/session-logs/bulk-delete', [SessionLogController::class, 'bulkDelete'])->name('SessionLogs.bulk-delete');
        Route::post('/session-logs/cleanup', [SessionLogController::class, 'cleanup'])->name('SessionLogs.cleanup');

        // Error Logs
        Route::get('/errors', [ErrorLogController::class, 'index'])->name('ErrorLogs.index');
        Route::get('/errors/show/{id}', [ErrorLogController::class, 'show'])->name('ErrorLogs.show');
        Route::delete('/errors/destroy/{id}', [ErrorLogController::class, 'destroy'])->name('ErrorLogs.destroy');
        Route::post('/errors/bulk-delete', [ErrorLogController::class, 'bulkDelete'])->name('ErrorLogs.bulk-delete');
        Route::post('/errors/cleanup', [ErrorLogController::class, 'cleanup'])->name('ErrorLogs.cleanup');

        // SMS Logs
        Route::get('/sms', [SmsLogController::class, 'index'])->name('SmsLogs.index');
        Route::get('/sms/show/{id}', [SmsLogController::class, 'show'])->name('SmsLogs.show');
        Route::delete('/sms/destroy/{id}', [SmsLogController::class, 'destroy'])->name('SmsLogs.destroy');
        Route::post('/sms/bulk-delete', [SmsLogController::class, 'bulkDelete'])->name('SmsLogs.bulk-delete');
        Route::post('/sms/cleanup', [SmsLogController::class, 'cleanup'])->name('SmsLogs.cleanup');
    });

    Route::prefix('SMPT')->group(function () {
        Route::get('/index', [SMPTController::class, 'index'])->name('SMPT.index');
        Route::post('/store', [SMPTController::class, 'store'])->name('SMPT.store');
        Route::delete('/reset', [SMPTController::class, 'reset'])->name('SMPT.reset');
        Route::get('/sendTestMail', [SMPTController::class, 'sendTestMail'])->name('SMPT.sendTestMail');
        Route::post('/test-email', [SMPTController::class, 'sendTestEmailAjax'])->name('SMPT.test_email');
    });

    Route::prefix('telegram')->group(function () {
        Route::get('/index', [TelegramSettingsController::class, 'index'])->name('Telegram.index');
        Route::post('/store', [TelegramSettingsController::class, 'store'])->name('Telegram.store');
    });

    Route::prefix('apikeys')->name('apikeys.')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/', [ApiKeyController::class, 'store'])->name('store');
        Route::delete('/{id}', [ApiKeyController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle', [ApiKeyController::class, 'toggle'])->name('toggle');
    });

    Route::get('/database-sizes', [DatabaseSizeController::class, 'index'])->name('DatabaseSizes.index');

    /*
    | مسح الذاكرة المؤقتة — بديل سطر الأوامر على استضافةٍ بلا SSH.
    |
    | المفتاح في المسار لا في جسم الطلب، ومحصورٌ بتعبيرٍ نمطي فوق ما تفعله
    | `CacheClearService` من مطابقةٍ على قائمتها البيضاء: مسارٌ لا يطابق
    | يُعطي ٤٠٤ قبل أن يصل المتحكّم أصلاً.
    |
    | ومعدَّلٌ محدود: كل نقرةٍ تشغّل أمراً على الخادم، والنقر المتكرّر على
    | «مسح الكل» يُبقي النظام في إعادة بناءٍ دائمة.
    */
    Route::prefix('system-cache')->middleware('throttle:20,1')->group(function () {
        Route::get('/', [CacheController::class, 'index'])->name('SettingsCache.index');
        Route::post('/clear-all', [CacheController::class, 'clearAll'])->name('SettingsCache.clearAll');
        Route::post('/clear/{key}', [CacheController::class, 'clear'])
            ->where('key', '[a-z_]+')->name('SettingsCache.clear');
    });

    /*
    | وضع الصيانة — إقفال النظام على الجميع إلا من سُمّي.
    |
    | البادئة `maintenance-mode` مستثناةٌ في `CheckMaintenanceMode` نفسه: لولا
    | ذلك لصار الوضعُ بابَ سجنٍ بلا مفتاح — يُفعَّل من هذه الشاشة ثم يمنع
    | الوصولَ إلى الشاشة التي تُطفئه.
    |
    | والحراسة بالصلاحية في المتحكّم لا هنا، على نمط المسارات المجاورة.
    */
    Route::prefix('maintenance-mode')->group(function () {
        Route::get('/', [MaintenanceModeController::class, 'index'])->name('MaintenanceMode.index');
        Route::post('/enable', [MaintenanceModeController::class, 'enable'])->name('MaintenanceMode.enable');
        Route::post('/update', [MaintenanceModeController::class, 'update'])->name('MaintenanceMode.update');
        Route::post('/disable', [MaintenanceModeController::class, 'disable'])->name('MaintenanceMode.disable');
        Route::post('/rotate-key', [MaintenanceModeController::class, 'rotate'])->name('MaintenanceMode.rotate');
    });

    Route::prefix('PaymentMethods')->group(function () {
        Route::get('/index', [PaymentMethodsController::class, 'index'])->name('PaymentMethods.index');
        Route::get('/create', [PaymentMethodsController::class, 'create'])->name('PaymentMethods.create');
        Route::post('/store', [PaymentMethodsController::class, 'store'])->name('PaymentMethods.store');
        Route::put('/payments/update-status', [PaymentMethodsController::class, 'updatePaymentStatus'])->name('update.payment.status');
        Route::get('/{id}/edit', [PaymentMethodsController::class, 'edit'])
            ->whereNumber('id')->name('PaymentMethods.edit');
        Route::put('/{id}', [PaymentMethodsController::class, 'update'])
            ->whereNumber('id')->name('PaymentMethods.update');
        Route::post('/{id}/regenerate-file', [PaymentMethodsController::class, 'regenerateFile'])
            ->whereNumber('id')->name('PaymentMethods.regenerateFile');
        // لم يكن للشاشة إجراء حذف — طريقة تُضاف بالخطأ كانت تبقى أبداً
        Route::delete('/{id}', [PaymentMethodsController::class, 'destroy'])
            ->whereNumber('id')->name('PaymentMethods.destroy');
    });

    Route::prefix('ApplicationManagement')->group(function () {
        Route::get('/index', [ApplicationManagementController::class, 'index'])->name('Application.index');
        Route::post('/update', [ApplicationManagementController::class, 'update'])->name('Application.update');
    });

    // ================== الفترات المحاسبية ==================
    // السنة المالية وفتراتها وضوابط قفلها. الإقفال وإعادة الفتح مساران
    // منفصلان عن التعديل لأن صلاحيتيهما منفصلتان — والمنع في المتحكّم
    Route::prefix('accounting-periods')->group(function () {
        Route::get('/', [AccountingPeriodController::class, 'index'])->name('AccountingPeriods.index');
        Route::post('/settings', [AccountingPeriodController::class, 'updateSettings'])->name('AccountingPeriods.settings');

        // السنوات المالية
        Route::post('/years', [AccountingPeriodController::class, 'storeYear'])->name('AccountingPeriods.years.store');
        // ربطُ العمليات غير المصنَّفة بفتراتها بعد رسم التقويم
        Route::post('/attach-orphans', [AccountingPeriodController::class, 'attachOrphans'])->name('AccountingPeriods.attach_orphans');
        Route::put('/years/{fiscalYear}', [AccountingPeriodController::class, 'updateYear'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.update');
        Route::delete('/years/{fiscalYear}', [AccountingPeriodController::class, 'destroyYear'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.destroy');
        Route::post('/years/{fiscalYear}/current', [AccountingPeriodController::class, 'setCurrent'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.current');
        Route::post('/years/{fiscalYear}/close', [AccountingPeriodController::class, 'closeYear'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.close');
        Route::post('/years/{fiscalYear}/reopen', [AccountingPeriodController::class, 'reopenYear'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.reopen');
        // تقرير الإقفال السنوي — قيودُه وحساباتُه ومطابقتُه وسجلُّ إقفالاته وإعادات فتحه
        Route::get('/years/{fiscalYear}/closing-report', [AccountingPeriodController::class, 'closingReport'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.closing_report');
        Route::post('/years/{fiscalYear}/generate', [AccountingPeriodController::class, 'generate'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.years.generate');
        Route::post('/years/{fiscalYear}/periods', [AccountingPeriodController::class, 'storePeriod'])
            ->whereNumber('fiscalYear')->name('AccountingPeriods.periods.store');

        // الفترات
        Route::put('/periods/{accountingPeriod}', [AccountingPeriodController::class, 'updatePeriod'])
            ->whereNumber('accountingPeriod')->name('AccountingPeriods.periods.update');
        Route::delete('/periods/{accountingPeriod}', [AccountingPeriodController::class, 'destroyPeriod'])
            ->whereNumber('accountingPeriod')->name('AccountingPeriods.periods.destroy');
        Route::post('/periods/{accountingPeriod}/close', [AccountingPeriodController::class, 'closePeriod'])
            ->whereNumber('accountingPeriod')->name('AccountingPeriods.periods.close');
        Route::post('/periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopenPeriod'])
            ->whereNumber('accountingPeriod')->name('AccountingPeriods.periods.reopen');
    });

    // ================== إعدادات الحسابات المحاسبية ==================
    // ربط كل عملية بحساب من شجرة الحسابات. مسارات هذه الشاشة وحدها — لا
    // يستدعيها متحكّم آخر، والنظام ما زال يعمل بثوابت SystemAccountIds
    Route::prefix('accounting-settings')->group(function () {
        Route::get('/', [AccountingSettingsController::class, 'index'])->name('AccountingSettings.index');

        // قبل `{accountingSetting}` كي لا يبتلعه قيد الرقم لاحقاً
        Route::get('/accounts', [AccountingSettingsController::class, 'accounts'])->name('AccountingSettings.accounts');

        // صاحب كودٍ بعينه — يُستدعى أثناء الكتابة لبيان أن الكود محجوز ولمن
        Route::get('/code-check', [AccountingSettingsController::class, 'codeCheck'])->name('AccountingSettings.code-check');

        Route::get('/{accountingSetting}/edit', [AccountingSettingsController::class, 'edit'])
            ->whereNumber('accountingSetting')->name('AccountingSettings.edit');
        Route::put('/{accountingSetting}', [AccountingSettingsController::class, 'update'])
            ->whereNumber('accountingSetting')->name('AccountingSettings.update');

        // أكواد مقترحة من موضع الحساب في الشجرة — قراءة فقط
        Route::get('/{accountingSetting}/code-suggestions', [AccountingSettingsController::class, 'codeSuggestions'])
            ->whereNumber('accountingSetting')->name('AccountingSettings.code-suggestions');

        // تغيير كود الحساب في شجرة الحسابات — مسار منفصل عن حفظ الإعداد
        // لأنه يمسّ جدول `accounts` لا جدول الإعدادات
        Route::put('/{accountingSetting}/account-code', [AccountingSettingsController::class, 'updateAccountCode'])
            ->whereNumber('accountingSetting')->name('AccountingSettings.account-code');
    });

    Route::prefix('TaxSitting')->group(function () {
        Route::get('/index', [TaxSittingController::class, 'index'])->name('TaxSitting.index');
        Route::post('/tax-setting/update', [TaxSittingController::class, 'updateAll'])->name('TaxSitting.updateAll');
        Route::delete('/tax-sittings/{id}', [TaxSittingController::class, 'destroy'])->name('tax-sittings.destroy');
    });

    Route::prefix('Sms')->group(function () {
        Route::get('/index', [SmsController::class, 'index'])->name('Sms.index');
        Route::post('/store', [SmsController::class, 'store'])->name('Sms.store');
        Route::post('/test-sms', [SmsController::class, 'sendTestSmsAjax'])->name('Sms.test_sms');
        // فحص الرصيد يثبت صحة بيانات الاتصال دون استهلاك رسالة
        Route::post('/balance', [SmsController::class, 'checkBalance'])->name('Sms.balance');
    });

    // قوالب الرسائل: نصوصها كانت مكتوبة داخل الكود بلا وسيلة لتعديلها
    Route::prefix('sms-templates')->group(function () {
        Route::get('/', [SmsTemplateController::class, 'index'])->name('SmsTemplates.index');
        // `create` قبل `{smsTemplate}` وإلا فُسّرت الكلمة معرّفَ قالب
        Route::get('/create', [SmsTemplateController::class, 'create'])->name('SmsTemplates.create');
        Route::post('/', [SmsTemplateController::class, 'store'])->name('SmsTemplates.store');
        Route::get('/{smsTemplate}/edit', [SmsTemplateController::class, 'edit'])->name('SmsTemplates.edit');
        Route::put('/{smsTemplate}', [SmsTemplateController::class, 'update'])->name('SmsTemplates.update');
        Route::post('/{smsTemplate}/toggle', [SmsTemplateController::class, 'toggle'])->name('SmsTemplates.toggle');
        Route::post('/{smsTemplate}/duplicate', [SmsTemplateController::class, 'duplicate'])->name('SmsTemplates.duplicate');
        Route::post('/{smsTemplate}/test', [SmsTemplateController::class, 'test'])->name('SmsTemplates.test');
        Route::delete('/{smsTemplate}', [SmsTemplateController::class, 'destroy'])->name('SmsTemplates.destroy');
    });

    // إعدادات الإشعارات: البوابة التي تحدّد ما يُرسَل من الإشعارات وبأي قناة
    Route::prefix('notification-settings')->group(function () {
        Route::get('/', [NotificationSettingController::class, 'index'])->name('NotificationSettings.index');
        Route::get('/{notificationSetting}/edit', [NotificationSettingController::class, 'edit'])->name('NotificationSettings.edit');
        Route::put('/{notificationSetting}', [NotificationSettingController::class, 'update'])->name('NotificationSettings.update');
        Route::post('/{notificationSetting}/toggle', [NotificationSettingController::class, 'toggle'])->name('NotificationSettings.toggle');
        Route::post('/{notificationSetting}/test', [NotificationSettingController::class, 'test'])->name('NotificationSettings.test');
        // الفئة نصّ لا معرّف، فتُوضَع بعد المسارات ذات المعرّف تفادياً للالتباس
        Route::post('/category/{category}/toggle', [NotificationSettingController::class, 'toggleCategory'])->name('NotificationSettings.toggleCategory');
    });

    // ================== المهام المجدولة (Cron Jobs) ==================
    // الصلاحيات مفروضة داخل المتحكّم لكل إجراء على حدة — التشغيل والحذف
    // ليسا كالعرض
    Route::prefix('cron-jobs')->group(function () {
        Route::get('/', [CronJobController::class, 'index'])->name('CronJobs.index');

        // `create` و`executions` قبل `{cronJob}` وإلا فُسّرتا معرّفَ مهمة
        Route::get('/create', [CronJobController::class, 'create'])->name('CronJobs.create');
        Route::post('/', [CronJobController::class, 'store'])->name('CronJobs.store');

        Route::get('/executions', [CronExecutionController::class, 'index'])->name('CronJobs.executions');
        Route::get('/executions/{execution}', [CronExecutionController::class, 'show'])
            ->whereNumber('execution')->name('CronJobs.executions.show');
        Route::delete('/executions/{execution}', [CronExecutionController::class, 'destroy'])
            ->whereNumber('execution')->name('CronJobs.executions.destroy');
        Route::post('/executions/cleanup', [CronExecutionController::class, 'cleanup'])
            ->name('CronJobs.executions.cleanup');

        Route::get('/{cronJob}', [CronJobController::class, 'show'])->whereNumber('cronJob')->name('CronJobs.show');
        Route::get('/{cronJob}/edit', [CronJobController::class, 'edit'])->whereNumber('cronJob')->name('CronJobs.edit');
        Route::put('/{cronJob}', [CronJobController::class, 'update'])->whereNumber('cronJob')->name('CronJobs.update');
        Route::delete('/{cronJob}', [CronJobController::class, 'destroy'])->whereNumber('cronJob')->name('CronJobs.destroy');

        Route::post('/{cronJob}/toggle', [CronJobController::class, 'toggle'])->whereNumber('cronJob')->name('CronJobs.toggle');
        Route::post('/{cronJob}/pause', [CronJobController::class, 'pause'])->whereNumber('cronJob')->name('CronJobs.pause');
        Route::post('/{cronJob}/resume', [CronJobController::class, 'resume'])->whereNumber('cronJob')->name('CronJobs.resume');
        Route::post('/{cronJob}/run', [CronJobController::class, 'run'])->whereNumber('cronJob')->name('CronJobs.run');
    });

    // ================== إدارة المستخدمين والأدوار والصلاحيات ==================
    // الصلاحيات مفروضة هنا على مستوى التوجيه أيضاً — لا داخل الكنترولر وحده —
    // بميدل وير `permission.role` المخصَّص لهذه الشاشات الثلاث حصراً
    // (owner يتجاوز الفحص دائماً، بصرف النظر عمّا مُنح له صراحةً)
    Route::prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('settings.users.index')
            ->middleware('permission.role:settings.users.view');
        Route::get('/create', [UsersController::class, 'create'])->name('settings.users.create')
            ->middleware('permission.role:settings.users.create');
        Route::post('/', [UsersController::class, 'store'])->name('settings.users.store')
            ->middleware('permission.role:settings.users.create');
        Route::get('/{user}', [UsersController::class, 'show'])->whereNumber('user')->name('settings.users.show')
            ->middleware('permission.role:settings.users.view');
        Route::get('/{user}/edit', [UsersController::class, 'edit'])->whereNumber('user')->name('settings.users.edit')
            ->middleware('permission.role:settings.users.edit');
        Route::put('/{user}', [UsersController::class, 'update'])->whereNumber('user')->name('settings.users.update')
            ->middleware('permission.role:settings.users.edit');
        Route::delete('/{user}', [UsersController::class, 'destroy'])->whereNumber('user')->name('settings.users.destroy')
            ->middleware('permission.role:settings.users.delete');
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [RolesController::class, 'index'])->name('settings.roles.index')
            ->middleware('permission.role:settings.roles.view');
        Route::get('/create', [RolesController::class, 'create'])->name('settings.roles.create')
            ->middleware('permission.role:settings.roles.create');
        Route::post('/', [RolesController::class, 'store'])->name('settings.roles.store')
            ->middleware('permission.role:settings.roles.create');
        Route::get('/{role}', [RolesController::class, 'show'])->whereNumber('role')->name('settings.roles.show')
            ->middleware('permission.role:settings.roles.view');
        Route::get('/{role}/edit', [RolesController::class, 'edit'])->whereNumber('role')->name('settings.roles.edit')
            ->middleware('permission.role:settings.roles.edit');
        Route::put('/{role}', [RolesController::class, 'update'])->whereNumber('role')->name('settings.roles.update')
            ->middleware('permission.role:settings.roles.edit');
        Route::delete('/{role}', [RolesController::class, 'destroy'])->whereNumber('role')->name('settings.roles.destroy')
            ->middleware('permission.role:settings.roles.delete');
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionsController::class, 'index'])->name('settings.permissions.index')
            ->middleware('permission.role:settings.permissions.view');
        Route::get('/create', [PermissionsController::class, 'create'])->name('settings.permissions.create')
            ->middleware('permission.role:settings.permissions.create');
        Route::post('/', [PermissionsController::class, 'store'])->name('settings.permissions.store')
            ->middleware('permission.role:settings.permissions.create');
        Route::get('/{permission}/edit', [PermissionsController::class, 'edit'])->whereNumber('permission')->name('settings.permissions.edit')
            ->middleware('permission.role:settings.permissions.edit');
        Route::put('/{permission}', [PermissionsController::class, 'update'])->whereNumber('permission')->name('settings.permissions.update')
            ->middleware('permission.role:settings.permissions.edit');
        Route::delete('/{permission}', [PermissionsController::class, 'destroy'])->whereNumber('permission')->name('settings.permissions.destroy')
            ->middleware('permission.role:settings.permissions.delete');
    });

    // ================== اشتراك المنشأة في النظام ==================
    // لا يخلط باشتراكات العملاء: تلك تدير ما تبيعه المنشأة، وهذه ما تشتريه
    Route::prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('Subscription.index');
        // لا مسار حفظ: الاشتراك لا يُسجَّل ولا يُجدَّد من داخل هذا النظام —
        // كلّه في لوحة تحكّم فوترة، والشاشة هنا مرآة لا نموذج
    });

    // ================== الاشتراك المرتبط بلوحة فوترة ==================
    // مرآةٌ لما تقوله لوحة فوترة، لا سجلّاً يدوياً كالشاشة أعلاه
    Route::prefix('fawtra')->group(function () {
        Route::get('/', [FawtraSubscriptionController::class, 'index'])->name('Fawtra.index');

        // `link` و`sync` أفعالٌ لا صفحات (POST). ومن يكتب عنوانها في شريط
        // المتصفّح كان يرى صفحة خطأ لا تقول شيئاً — فتُعاد إلى الشاشة بدلها
        Route::get('link', fn () => redirect()->route('Fawtra.index'));
        Route::get('sync', fn () => redirect()->route('Fawtra.index'));
        // تحقّقٌ بلا حفظ: يجيب المستخدم فوراً «قُبل المفتاح أو رُفض ولماذا»
        Route::post('verify', [FawtraSubscriptionController::class, 'verify'])
            ->middleware('throttle:20,1')->name('Fawtra.verify');
        Route::post('link', [FawtraSubscriptionController::class, 'link'])->name('Fawtra.link');
        Route::post('unlink', [FawtraSubscriptionController::class, 'unlink'])->name('Fawtra.unlink');
        Route::post('sync', [FawtraSubscriptionController::class, 'syncNow'])->name('Fawtra.sync');
        Route::post('alerts/{alert}/dismiss', [FawtraSubscriptionController::class, 'dismissAlert'])
            ->name('Fawtra.alert.dismiss');
    });

    // ================== تصدير البيانات ==================
    // ليس نسخاً احتياطياً: ملفّ يُقرأ في برنامج جداول لا أرشيف يُستَرجَع
    // منه النظام. صلاحيته مستقلّة لأنه استخراج بيانات خام
    Route::prefix('data-export')->group(function () {
        Route::get('/', [DataExportController::class, 'index'])->name('DataExport.index');
        Route::get('/fields/{key}', [DataExportController::class, 'columns'])
            ->where('key', '[a-f0-9]{12}')->name('DataExport.columns');
        Route::post('/', [DataExportController::class, 'export'])->name('DataExport.run');
    });

    // ================== النسخ الاحتياطي ==================
    Route::prefix('backup')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('Backup.index');
        Route::get('/settings', [BackupController::class, 'settings'])->name('Backup.settings');
        Route::put('/settings', [BackupController::class, 'updateSettings'])->name('Backup.settings.update');

        Route::get('/history', [BackupController::class, 'history'])->name('Backup.history');
        Route::get('/restores', [BackupController::class, 'restores'])->name('Backup.restores');

        Route::post('/run', [BackupController::class, 'run'])->name('Backup.run');
        Route::post('/reconcile', [BackupController::class, 'reconcile'])->name('Backup.reconcile');
        Route::post('/cleanup', [BackupController::class, 'cleanup'])->name('Backup.cleanup');

        // جداول النسخ المتعدّدة — قبل `/{backup}` كي لا يُفسَّر «schedules»
        // معرّفَ نسخة إن سقط `whereNumber` يوماً
        Route::prefix('schedules')->group(function () {
            Route::get('/', [BackupScheduleController::class, 'index'])->name('Backup.schedules.index');
            Route::get('/create', [BackupScheduleController::class, 'create'])->name('Backup.schedules.create');
            Route::post('/', [BackupScheduleController::class, 'store'])->name('Backup.schedules.store');

            Route::get('/{cronJob}/edit', [BackupScheduleController::class, 'edit'])
                ->whereNumber('cronJob')->name('Backup.schedules.edit');
            Route::put('/{cronJob}', [BackupScheduleController::class, 'update'])
                ->whereNumber('cronJob')->name('Backup.schedules.update');
            Route::delete('/{cronJob}', [BackupScheduleController::class, 'destroy'])
                ->whereNumber('cronJob')->name('Backup.schedules.destroy');
            Route::post('/{cronJob}/toggle', [BackupScheduleController::class, 'toggle'])
                ->whereNumber('cronJob')->name('Backup.schedules.toggle');
            Route::post('/{cronJob}/run', [BackupScheduleController::class, 'run'])
                ->whereNumber('cronJob')->name('Backup.schedules.run');
        });

        Route::get('/{backup}', [BackupController::class, 'show'])->whereNumber('backup')->name('Backup.show');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->whereNumber('backup')->name('Backup.download');
        Route::post('/{backup}/lock', [BackupController::class, 'toggleLock'])->whereNumber('backup')->name('Backup.lock');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->whereNumber('backup')->name('Backup.restore');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->whereNumber('backup')->name('Backup.destroy');
    });

    Route::prefix('SequenceNumbering')->group(function () {
        // القسم اختياري: الرابط بلا قسم يفتح القسم الافتراضي بدل أن ينكسر
        Route::get('/index/{section?}', [SequenceNumberingController::class, 'index'])->name('SequenceNumbering.index');
        Route::post('/sequence-numbering', [SequenceNumberingController::class, 'store'])->name('SequenceNumbering.store');
        Route::post('/preview', [SequenceNumberingController::class, 'preview'])->name('SequenceNumbering.preview');
        // يرجع JSON بالرقم التالي — كان يشير إلى `index` فيرجع صفحة HTML
        Route::get('/get-current-number/{section}', [SequenceNumberingController::class, 'currentNumber'])->name('SequenceNumbering.current.number');
        // POST لا GET: الطلبُ يستهلك رقماً من التسلسل، ورابطٌ أو صورةٌ تشير
        // إليه كانت تحرق أرقامَ الفواتير بمجرّد فتح صفحة
        Route::post('/sequence-numbering/{section}/generate', [SequenceNumberingController::class, 'generate'])->name('SequenceNumbering.generate');
    });

    Route::prefix('document-templates')->group(function () {
        Route::get('/', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
        Route::get('/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
        Route::post('/store', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::get('/{id}/edit', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
        Route::put('/{id}/update', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
        Route::post('/{id}/duplicate', [DocumentTemplateController::class, 'duplicate'])->name('document-templates.duplicate');
        Route::post('/{id}/set-default', [DocumentTemplateController::class, 'setDefault'])->name('document-templates.set-default');
        Route::post('/{id}/toggle-active', [DocumentTemplateController::class, 'toggleActive'])->name('document-templates.toggle-active');
        Route::delete('/{id}/destroy', [DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy');
        Route::post('/preview-html', [DocumentTemplateController::class, 'previewHtml'])->name('document-templates.preview-html');
        Route::get('/{id}/preview', [DocumentTemplateController::class, 'previewPage'])->name('document-templates.preview');
    });
});

/*
|--------------------------------------------------------------------------
| تحديث النظام — خارج مجموعة `auth` عمداً
|--------------------------------------------------------------------------
|
| بديل سطر الأوامر لتركيبٍ على استضافةٍ مشتركة بلا SSH: بعد رفع ملفّات
| التحديث تبقى الترحيلات معلّقة والبيانات الجديدة غير مزروعة.
|
| ولماذا خارج `auth`؟ لأن الوسيط `CheckSystemUpdates` يحوّل كل صفحة — **حتى
| صفحة الدخول** — إلى هذه الشاشة ما دام ترحيلٌ معلَّقاً. فلو بقيت خلف `auth`
| لدارت الحلقة: الدخول ← التحديث ← الدخول. و`AllowPendingUpdateAccess` هو
| الحارس البديل: يفتحها للزائر ما دام العطل قائماً، ويقفلها في وجهه فور
| انتهاء الترحيلات. وأمّا مهامّ البيانات فتتطلّب تسجيل الدخول في المتحكّم.
|
| و`check.branch` ساقطةٌ عن هذه المجموعة كذلك: فحصُ الفرع يسأل جداولَ قد
| تكون هي نفسُها بانتظار الترحيل.
|
*/
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'update.access'],
], function () {
    Route::prefix('system-update')->group(function () {
        Route::get('/', [SystemUpdateController::class, 'index'])->name('SystemUpdate.index');

        // معدَّل محدود: التشغيل متاحٌ للزائر ما دام هناك ترحيلٌ معلَّق،
        // فلا يُترك بلا سقفٍ لعدد المحاولات
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/migrate', [SystemUpdateController::class, 'migrate'])->name('SystemUpdate.migrate');
            Route::post('/seed', [SystemUpdateController::class, 'seed'])->name('SystemUpdate.seed');
            // زرٌّ واحد: الترحيلات ثم كل مهمّة تُضيف الناقص بلا كتابةٍ فوق القائم
            Route::post('/run-all', [SystemUpdateController::class, 'runAll'])->name('SystemUpdate.runAll');
        });

        // تأجيل التحويل الإجباري إلى هذه الشاشة نصفَ ساعة
        Route::post('/snooze', [SystemUpdateController::class, 'snooze'])->name('SystemUpdate.snooze');
    });
});
