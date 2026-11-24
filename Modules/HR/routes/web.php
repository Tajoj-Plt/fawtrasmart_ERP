<?php

/*
 * الحذفُ لا يكون على GET.
 *
 * كانت عشرةُ مساراتٍ في هذه الوحدة تحذف سجلاتٍ عبر Route::get، أي بلا حماية
 * CSRF ومكشوفةً لأيّ جلبٍ مسبقٍ من المتصفّح أو زاحفٍ أو <img src> في صفحةٍ
 * أخرى. صارت كلّها Route::delete.
 *
 * وقد كانت أربعةٌ من واجهاتها ترسل DELETE أصلاً (أزرار حذف الورديات وكشوف
 * الحضور وقوائم العطلات والمحدِّدات)، فكانت تصطدم بـ405 ولا تحذف شيئاً —
 * فالتحويل يُصلح الثغرة ويُعيد تلك الأزرار إلى العمل معاً.
 */

use App\Constants\planFeatures\HrPlanFeatures;
use App\Http\Controllers\Task\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\HR\Http\Controllers\Attendance\AttendanceCorrectionApprovalController;
use Modules\HR\Http\Controllers\Attendance\AttendanceCorrectionRequestController;
use Modules\HR\Http\Controllers\Attendance\AttendanceDaysController;
use Modules\HR\Http\Controllers\Attendance\AttendancePunchesController;
use Modules\HR\Http\Controllers\Attendance\AttendanceSheetsController;
use Modules\HR\Http\Controllers\Attendance\BarcodeAttendanceController;
use Modules\HR\Http\Controllers\Attendance\CustomShiftsController;
use Modules\HR\Http\Controllers\Attendance\DeviceMonitorController;
use Modules\HR\Http\Controllers\Attendance\FieldAttendanceController;
use Modules\HR\Http\Controllers\Attendance\LeavePermissionsController;
use Modules\HR\Http\Controllers\Attendance\LeaveRequestsController;
use Modules\HR\Http\Controllers\Attendance\Settings\AttendanceDeterminantsController;
use Modules\HR\Http\Controllers\Attendance\Settings\AttendanceRuleController;
use Modules\HR\Http\Controllers\Attendance\Settings\AttendanceV2SettingsController;
use Modules\HR\Http\Controllers\Attendance\Settings\BasicController;
use Modules\HR\Http\Controllers\Attendance\Settings\EmployeeLeaveBalanceController;
use Modules\HR\Http\Controllers\Attendance\Settings\GeofencesController;
use Modules\HR\Http\Controllers\Attendance\Settings\HolidayController;
use Modules\HR\Http\Controllers\Attendance\Settings\LeavePoliciesController;
use Modules\HR\Http\Controllers\Attendance\Settings\LeaveTypesController;
use Modules\HR\Http\Controllers\Attendance\Settings\MachinesController;
use Modules\HR\Http\Controllers\Attendance\SettingsController as AttendanceSettingsController;
use Modules\HR\Http\Controllers\Attendance\TeamLiveStatusController;
use Modules\HR\Http\Controllers\Attendance\UnifiedAttendanceLogController;
use Modules\HR\Http\Controllers\CommissionsReportController;
use Modules\HR\Http\Controllers\DashboardController;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\EmployeeDocumentController;
use Modules\HR\Http\Controllers\EmployeePerformanceController;
use Modules\HR\Http\Controllers\EmployeeReportController;
use Modules\HR\Http\Controllers\EmployeeSettingsController;
use Modules\HR\Http\Controllers\HRReportsController;
use Modules\HR\Http\Controllers\OrganizationalStructure\DepartmentController;
use Modules\HR\Http\Controllers\OrganizationalStructure\JobTitleController;
use Modules\HR\Http\Controllers\OrganizationalStructure\ManagingFunctionalLevelsController;
use Modules\HR\Http\Controllers\OrganizationalStructure\ManagingJobTypesController;
use Modules\HR\Http\Controllers\PayrollReportController;
use Modules\HR\Http\Controllers\Salaries\AdvanceApprovalController;
use Modules\HR\Http\Controllers\Salaries\AdvanceRequestController;
use Modules\HR\Http\Controllers\Salaries\AdvanceSettingsController;
use Modules\HR\Http\Controllers\Salaries\AncestorController;
use Modules\HR\Http\Controllers\Salaries\ContractsController;
use Modules\HR\Http\Controllers\Salaries\DeductionController;
use Modules\HR\Http\Controllers\Salaries\EmployeeTreasuryApiController;
use Modules\HR\Http\Controllers\Salaries\EndOfServiceBenefitController;
use Modules\HR\Http\Controllers\Salaries\HrVoucherPrintController;
use Modules\HR\Http\Controllers\Salaries\OvertimeApprovalController;
use Modules\HR\Http\Controllers\Salaries\OvertimeRequestController;
use Modules\HR\Http\Controllers\Salaries\OvertimeSettingsController;
use Modules\HR\Http\Controllers\Salaries\PayrollAccountsController;
use Modules\HR\Http\Controllers\Salaries\PayrollBankFileController;
use Modules\HR\Http\Controllers\Salaries\PayrollBatchController;
use Modules\HR\Http\Controllers\Salaries\PayrollProcessController;
use Modules\HR\Http\Controllers\Salaries\PayrollRunController;
use Modules\HR\Http\Controllers\Salaries\RelatedModelsController;
use Modules\HR\Http\Controllers\Salaries\SalaryComponentTypeController;
use Modules\HR\Http\Controllers\Salaries\SalaryItemsController;
use Modules\HR\Http\Controllers\Salaries\SalarySittingController;
use Modules\HR\Http\Controllers\Salaries\SalarySlipController;
use Modules\HR\Http\Controllers\Salaries\SalaryTemplatesController;
use Modules\HR\Http\Controllers\Salaries\SocialInsuranceSettingsController;
use Modules\HR\Http\Controllers\ShiftManagementController;

Route::middleware(['auth'])->group(function () {
    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch'],
        ],
        function () {
            /*
                ══════════ أين تعيش الحراسة في هذه الوحدة؟ ══════════

                يقرأ الناظرُ هذا الملفَّ فيرى `plan:` و`plan.limit:` ولا يرى
                `permission.role:` فيظنّ أن صلاحيات النظام غير مطبَّقة. وليس
                كذلك:

                  • **حدودُ الباقة** (`plan:` و`plan.limit:`) تُكتب هنا،
                    لأنها تلزم مجموعةَ مساراتٍ كاملة ولا تفرّق بين أفعالها.

                  • **صلاحياتُ النظام** (`permission.role:`) تُكتب في باني
                    كلِّ متحكّم، لأنها تفرّق فعلاً فعلاً: `create` غيرُ
                    `index` غيرُ `approve`. والكتابةُ هناك تُبقي الحارسَ
                    ملاصقاً للفعل الذي يحرسه، فلا يُنسى عند إضافة فعلٍ جديد.

                    والوسيطُ `permission.role` لا `permission`: الأولُ يقرأ
                    `users.role_id ← role_has_permissions` — مصدرَ الحقيقة
                    الذي تقرؤه `hasPermission()` وموجِّهُ `@permission` في
                    القوالب. والثاني (Spatie) يقرأ `model_has_roles`
                    و`model_has_permissions`، فكان خادمُ هذه الوحدة يسأل
                    غيرَ ما تسأله شاشاتُها. انظر [\App\Http\Middleware\CheckPermissionRole].
                    والمسارات القليلة التي كانت على `can:` — وهو Gate يقرأ
                    Spatie كذلك — نُقلت إلى الوسيط نفسه بأسمائها كما هي.

                لارافل يجمع الاثنين على الطلب الواحد؛ فالحراسةُ مكتملة وإن
                لم تظهر كلُّها في هذا الملف. ولا تُكرَّر الصلاحيةُ هنا وهناك
                معاً: نسختان تفترقان مع الوقت، وتفترقان صامتتَين.

                للتحقّق:  php artisan route:list --path=hr -v
            */
            Route::prefix('hr')
                /*
                    سورُ الباقة على الوحدة كلّها بشرط «واحدةٌ على الأقل»:
                    باقةٌ لا تشمل شيئاً من الموارد البشرية تُردّ من هنا بـ402
                    قبل أن يصل الطلب إلى متحكّم، وحارسُ كل شاشةٍ داخلَها
                    يتكفّل بالباقي.
                */
                ->middleware(['auth', 'plan:'.implode('|', HrPlanFeatures::all())])
                ->group(function () {
                    /*
                        شاشة التطبيقات (application_settings) لا تحجب شيئاً من
                        HR فعلياً: تفويض module_for_path() التلقائي بالمسار
                        معطَّلٌ بنيوياً لأي مسارٍ مسبوقٍ بمقطع اللغة (كل مسارات
                        هذه الوحدة كذلك، وhideDefaultLocaleInURL=false يجعله
                        كل طلبٍ لا استثناءً) — request()->path() لطلبٍ حقيقي
                        يُرجع 'ar/hr/...' لا 'hr/...'، فلا تطابق أيَّ بادئةٍ
                        مسجَّلة إطلاقاً. الحلّ هنا: تسمية المفتاح صراحةً
                        `module:<key>` بدل الاعتماد على الاكتشاف التلقائي —
                        نفس ما فعلته وحدة نقاط البيع بالضبط.
                    */
                    /*
                        `PlanLock` (app/Support/PlanLock.php) يوثّق صراحةً أن
                        المنع الفعلي في وسيط `plan:` على المسارات — "فمن نسخ
                        العنوان ولصقه يُردّ بـ402 مهما فعلت هذه الشاشة". لم يكن
                        صحيحاً هنا: القفل في الشريط الجانبي وحده
                        (`$plan['dashboard']`/`$plan['performance']`) بلا أيّ
                        `plan:` على الراوت — أي مستأجرٍ يملك أيّ ميزة HR واحدة
                        (سور الوحدة العام) يفتح لوحة الأداء وتصديرها بلا
                        اشتراكٍ فيهما تحديداً.
                    */
                    Route::middleware(['module:employees', 'plan:'.HrPlanFeatures::DASHBOARD])->group(function () {
                        // Dashboard route
                        Route::get('/dashboard', [DashboardController::class, 'index'])->name('hr.dashboard.index');
                        /*
                            قائمةُ موظفي شريحةٍ من اللوحة — تُطلَب بـAJAX عند
                            النقر على أيّ رقم. تُبنى من الخريطة التي عُدَّ منها
                            الرقمُ نفسُه، فطولُها يساوي ما يُعرَض (نظيرُ
                            `clients.dashboard.segment`).
                        */
                        Route::get('/dashboard/segment', [DashboardController::class, 'segment'])->name('hr.dashboard.segment');
                    });

                    Route::middleware(['module:employees', 'plan:'.HrPlanFeatures::PERFORMANCE])->group(function () {
                        // employee performance routes
                        Route::get('/employee-performance', [EmployeePerformanceController::class, 'index'])->name('hr.employee_performance.index');
                        Route::post('/employee-performance/export', [EmployeePerformanceController::class, 'export'])->name('hr.employee_performance.export');
                    });

                    // ══════════ الإقفال اليومي — انتقل إلى وحدة المبيعات ══════════
                    // الشاشة صارت تحت `sales/daily-closing`، لكن الروابط المحفوظة
                    // وسجلّ المتصفّح ما زالت تفتح المسار القديم فتردّ 404. هذا
                    // التحويل يحفظها — بالمسار الفرعي وسلسلة الاستعلام معاً،
                    // فلا يضيع `?from_date=…` من رابطٍ محفوظ لتقرير.
                    Route::get('/daily-closing/{path?}', function (Request $request, ?string $path = null) {
                        $target = rtrim(route('daily-closing.index'), '/').($path ? '/'.$path : '');
                        $query = $request->getQueryString();

                        return redirect()->to($target.($query ? '?'.$query : ''));
                    })->where('path', '.*');

                    // employee routes
                    Route::prefix('employee')->middleware(['plan:'.HrPlanFeatures::EMPLOYEES, 'module:employees'])->group(function () {
                        Route::get('/index', [EmployeeController::class, 'index'])->name('employee.index');
                        /*
                            إعداداتُ ملفّ الموظف وقواعدُ الهيكل التنظيمي — شاشةٌ
                            واحدة بثلاثة تبويبات. حارسُها `employee_settings.manage`
                            في مُنشئ المتحكّم كبقيّة شاشات هذه الوحدة.

                            وهي تحت بادئة `employee` قصداً لا تحت
                            `OrganizationalStructure`: الإعدادُ الواحد يحكم
                            النموذجَين — إلزاميّةُ «القسم» في ملفّ الموظف
                            وإلزاميّةُ وصفِ القسم في شاشته — فلو سكنت الهيكلَ
                            لاحتجبت عمّن أطفأ وحدةَ الهيكل التنظيمي وبقيت
                            حقولُ الموظف تحت حكمها.
                        */
                        Route::get('/settings', [EmployeeSettingsController::class, 'index'])->name('hr.employee_settings.index');
                        Route::post('/settings', [EmployeeSettingsController::class, 'update'])->name('hr.employee_settings.update');
                        /*
                            كان هذا المسار يشير إلى `EmployeeController::report()`
                            — دالّةٍ **جسدُها فارغ** (`{}`). فبندُ «تقرير
                            الموظفين» في الشريط الجانبي يفتح صفحةً بيضاء.

                            و`EmployeeReportController` قائمٌ لهذا الغرض
                            وحده، يعرض `hr::employee.report.index` ويحرسه
                            `hr_reports_view` — ولا مسارَ يبلغه. فالخللُ
                            توصيلةٌ لا نقص.
                        */
                        Route::get('/employee-report', [EmployeeReportController::class, 'index'])->name('employeeReports.index');
                        Route::get('/create', [EmployeeController::class, 'create'])->name('employee.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_EMPLOYEES);
                        Route::get('/edit/{id}', [EmployeeController::class, 'edit'])->name('employee.edit');
                        Route::get('/show/{id}', [EmployeeController::class, 'show'])->name('employee.show');
                        Route::get('/updateStatus/{id}', [EmployeeController::class, 'updateStatus'])->name('employee.updateStatus');
                        // إنهاءُ الخدمة `POST` لا `GET`: أثرُه لا يُسترجَع، فلا يقع بزيارة رابط
                        Route::post('/terminate/{id}', [EmployeeController::class, 'terminate'])->name('employee.terminate');
                        Route::get('/toggleSystemAccess/{id}', [EmployeeController::class, 'toggleSystemAccess'])->name('employee.toggleSystemAccess');
                        Route::post('/store', [EmployeeController::class, 'store'])->name('employee.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_EMPLOYEES);
                        Route::post('/update/{id}', [EmployeeController::class, 'update'])->name('employee.update');
                        Route::post('/updatePassword/{id}', [EmployeeController::class, 'updatePassword'])->name('employee.updatePassword');
                        /*
                            `POST` لا `GET`: كلاهما يغيّر حالَ صفّ، فلا يقع بزيارة
                            رابطٍ أو بجلبٍ مسبقٍ من المتصفّح. وكان `delete` مساراً
                            `GET` بينما مودالُ القائمة يرسل `POST` — فلم يعمل زرُّ
                            الحذف فيها قطّ (405).

                            و`delete` تحذف نهائياً الآن ولا تؤرشف: كانت تؤرشف ثم
                            تعود إلى بطاقة الموظف، و`show()` لا ترى المؤرشَف —
                            فصفحةُ 404 بعد كلّ «حذف».
                        */
                        Route::post('/delete/{id}', [EmployeeController::class, 'delete'])->name('employee.delete');

                        /*
                            الأرشفةُ معلَّقةٌ بطلبٍ لا محذوفة: `EmployeeController::archive()`
                            وشاشةُ الأرشيف واستردادُها باقيةٌ كما هي، ومودالُ القائمة
                            كذلك — فرفعُ التعليق عن هذا السطر وحدَه يُعيد الإجراء.
                        Route::post('/archive/{id}', [EmployeeController::class, 'archive'])->name('employee.archive');
                        */
                        // الأرشيف واستردادُه — والاستردادُ `POST` لأنه يُعيد صفّاً إلى الحياة
                        Route::get('/archived', [EmployeeController::class, 'archived'])->name('employee.archived');

                        /*
                            وثائقُ الموظف — الرسمية بأرقامها وانتهائها، لا
                            المرفقاتُ العامّة (`employee.attachments.*`) التي
                            هي دلوُ ملفّاتٍ بلا نوع.
                        */
                        Route::prefix('{employee}/documents')->group(function () {
                            Route::get('/', [EmployeeDocumentController::class, 'index'])->name('employee.documents.index');
                            Route::post('/', [EmployeeDocumentController::class, 'store'])->name('employee.documents.store');
                            Route::get('/{document}/download', [EmployeeDocumentController::class, 'download'])->name('employee.documents.download');
                            Route::put('/{document}', [EmployeeDocumentController::class, 'update'])->name('employee.documents.update');
                            Route::delete('/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employee.documents.destroy');
                        });
                        Route::post('/restore/{id}', [EmployeeController::class, 'restore'])->name('employee.restore');
                        Route::get('/login/to/{id}', [EmployeeController::class, 'login_to'])->name('employee.login_to');
                        Route::get('/export/view', [EmployeeController::class, 'export_view'])->name('employee.export_view');
                        Route::post('/export', [EmployeeController::class, 'export'])->name('employee.export');
                        Route::get('/send_email/{id}', [EmployeeController::class, 'send_email'])->name('employee.send_email');

                        // مرفقات الموظف
                        Route::get('/attachments/{id}/download-zip', [EmployeeController::class, 'downloadAttachmentsZip'])->name('employee.attachments.download_zip');
                        Route::get('/attachments/{attachmentId}/download', [EmployeeController::class, 'downloadAttachment'])->name('employee.attachments.download');
                        Route::delete('/attachments/{attachmentId}', [EmployeeController::class, 'deleteAttachment'])->name('employee.attachments.delete');
                    });

                    // HR Reports routes
                    Route::prefix('reports')->middleware('plan:'.HrPlanFeatures::REPORTS)->group(function () {
                        Route::get('/', [HRReportsController::class, 'index'])->name('hr.reports.index');
                        Route::get('/attendance', [HRReportsController::class, 'attendanceByDepartment'])->name('hr.reports.attendance');
                        // تقاريرُ الحضور بتصميم تقارير العملاء
                        /*
                            `module:employee_attendance` صريحاً كما في تقرير
                            الهيكل التنظيمي أدناه: الاكتشافُ التلقائي بالمسار
                            لا يعمل على مسارٍ مسبوقٍ بمقطع اللغة (وكلُّ مسارات
                            هذه الوحدة كذلك)، فتقريرٌ بلا حارسٍ يُفتح ولو أُطفئت
                            وحدةُ الحضور من «شاشة التطبيقات».
                        */
                        Route::middleware('module:employee_attendance')->group(function () {
                            Route::get('/attendance/summary', [\Modules\HR\Http\Controllers\Reports\AttendanceReportController::class, 'summary'])->name('hr.reports.attendance.summary');
                            Route::get('/attendance/punches', [\Modules\HR\Http\Controllers\Reports\AttendancePunchReportController::class, 'index'])->name('hr.reports.attendance.punches');
                        });
                        Route::get('/leaves', [\Modules\HR\Http\Controllers\Reports\LeaveReportController::class, 'index'])->name('hr.reports.leaves');
                        Route::get('/salaries', [HRReportsController::class, 'salariesByDepartment'])->name('hr.reports.salaries');
                        Route::get('/payments', [HRReportsController::class, 'paymentsReport'])->name('hr.reports.payments');
                        // مستنداتُ الرواتب بتصميم تقارير العملاء: القسائم (اتفاقات الأجر) والمسيرات
                        Route::middleware('module:salaries')->group(function () {
                            Route::get('/payroll/slips-register', [\Modules\HR\Http\Controllers\Reports\PayrollDocumentsReportController::class, 'slipsRegister'])->name('hr.reports.payroll.slips_register');
                            Route::get('/payroll/sheets', [\Modules\HR\Http\Controllers\Reports\PayrollDocumentsReportController::class, 'sheets'])->name('hr.reports.payroll.sheets');
                        });

                        /*
                            تقاريرُ الموظفين (§11.2) — كانت فهرساً ساكناً
                            بروابط فارغة (`href=""`) عن «التحصيل». الصلاحيةُ
                            على الفعل في المتحكّم لا هنا، وفق نمط الوحدة.
                            والتصديرُ الموحَّد مؤجَّل إلى الجولة 3.4.
                        */
                        Route::get('/employees', [EmployeeReportController::class, 'employees'])->name('hr.reports.employees');
                        Route::get('/employees/by-department', [EmployeeReportController::class, 'byDepartment'])->name('hr.reports.employees.by_department');
                        Route::get('/employees/by-job-title', [EmployeeReportController::class, 'byJobTitle'])->name('hr.reports.employees.by_job_title');
                        Route::get('/employees/by-status', [EmployeeReportController::class, 'byStatus'])->name('hr.reports.employees.by_status');
                        Route::get('/employees/turnover', [EmployeeReportController::class, 'turnover'])->name('hr.reports.employees.turnover');
                        Route::get('/employees/contracts', [EmployeeReportController::class, 'contracts'])->name('hr.reports.employees.contracts');
                        Route::get('/employees/documents', [EmployeeReportController::class, 'documents'])->name('hr.reports.employees.documents');
                        Route::get('/employees/statistics', [EmployeeReportController::class, 'statistics'])->name('hr.reports.employees.statistics');
                        /*
                            وحدها بين تقارير هذا القسم تحمل `module:` صراحةً:
                            محتواها هيكلٌ إداريٌّ لا تقرير نشاط، فتعطيل
                            "الهيكل التنظيمي" من شاشة التطبيقات يجب أن
                            يُغلقها كما يُغلق شاشات الأقسام والمسمّيات
                            (web.php أعلاه)، لا أن يبقيها مفتوحة بحجة أنها
                            داخل مجموعة `reports` العامة.
                        */
                        Route::get('/employees/organization', [EmployeeReportController::class, 'organization'])->name('hr.reports.employees.organization')->middleware('module:organizational_structure');

                        /*
                            تقاريرُ الرواتب (§11.3) على `payroll_runs` —
                            حقيقةُ الصرف لا قسيمة الاتفاق. التصدير إلى 3.4.

                            و`module:salaries` صريحاً: كانت هذه الاثنتا عشرةَ
                            مفتوحةً ولو أُطفئت وحدةُ الرواتب — تُظهر الصافيَ
                            والخصميات وكشفَ الحساب. والسببُ نفسُ ما في تقرير
                            الهيكل التنظيمي: الاكتشافُ التلقائي بالمسار معطَّلٌ
                            مع بادئة اللغة.
                        */
                        Route::middleware('module:salaries')->group(function () {
                            Route::get('/payroll/summary', [PayrollReportController::class, 'summary'])->name('hr.reports.payroll.summary');
                            Route::get('/payroll/by-period', [PayrollReportController::class, 'byPeriod'])->name('hr.reports.payroll.by_period');
                            Route::get('/payroll/by-department', [PayrollReportController::class, 'byDepartment'])->name('hr.reports.payroll.by_department');
                            Route::get('/payroll/components', [PayrollReportController::class, 'components'])->name('hr.reports.payroll.components');
                            Route::get('/payroll/allowances', [PayrollReportController::class, 'allowances'])->name('hr.reports.payroll.allowances');
                            Route::get('/payroll/net', [PayrollReportController::class, 'netSalary'])->name('hr.reports.payroll.net');
                            Route::get('/payroll/status', [PayrollReportController::class, 'disbursementStatus'])->name('hr.reports.payroll.status');
                            Route::get('/payroll/deductions', [PayrollReportController::class, 'deductions'])->name('hr.reports.payroll.deductions');
                            Route::get('/payroll/payslips', [PayrollReportController::class, 'payslips'])->name('hr.reports.payroll.payslips');
                            Route::get('/payroll/advances', [PayrollReportController::class, 'advances'])->name('hr.reports.payroll.advances');
                            Route::get('/payroll/statement', [PayrollReportController::class, 'statement'])->name('hr.reports.payroll.statement');
                            Route::get('/payroll/reconciliation', [PayrollReportController::class, 'reconciliation'])->name('hr.reports.payroll.reconciliation');
                        });
                    });

                    // employee shift management
                    Route::prefix('shift_management')->middleware(['plan:'.HrPlanFeatures::SHIFTS, 'module:employee_attendance'])->group(function () {
                        Route::get('/index', [ShiftManagementController::class, 'index'])->name('shift_management.index');
                        Route::post('/search', [ShiftManagementController::class, 'search'])->name('shift_management.search');
                        Route::get('/create', [ShiftManagementController::class, 'create'])->name('shift_management.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_SHIFTS);
                        Route::post('/store', [ShiftManagementController::class, 'store'])->name('shift_management.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_SHIFTS);
                        Route::get('/edit/{id}', [ShiftManagementController::class, 'edit'])->name('shift_management.edit');
                        Route::post('/update/{id}', [ShiftManagementController::class, 'update'])->name('shift_management.update');
                        Route::post('/export', [ShiftManagementController::class, 'export'])->name('shift_management.export');
                        Route::delete('/delete/{id}', [ShiftManagementController::class, 'delete'])->name('shift_management.delete');
                        Route::get('/show/{id}', [ShiftManagementController::class, 'show'])->name('shift_management.show');
                    });

                    // employee routes
                    Route::prefix('employee')->middleware(['plan:'.HrPlanFeatures::EMPLOYEES, 'module:employees'])->group(function () {
                        Route::get('/employee_role_management', [EmployeeController::class, 'employee_management'])->name('employee.employee_role_management');
                        Route::get('/manage_shifts', [EmployeeController::class, 'manage_shifts'])->name('employee.shifts');
                        Route::get('/add_shift', [EmployeeController::class, 'add_shift'])->name('add_shift');
                        Route::get('/employee_role_management/add_new_role', [EmployeeController::class, 'add_new_role'])->name('employee.employee_role_management.add_new_role');
                    });
                    // task
                    Route::prefix('task')->group(function () {
                        Route::get('/task', [TaskController::class, 'index'])->name('task.index');
                    });

                    Route::prefix('department')->middleware(['plan:'.HrPlanFeatures::DEPARTMENTS, 'module:organizational_structure'])->group(function () {
                        Route::get('/index', [DepartmentController::class, 'index'])->name('department.index');
                        Route::get('/create', [DepartmentController::class, 'create'])->name('department.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_DEPARTMENTS);
                        Route::get('/show/{id}', [DepartmentController::class, 'show'])->name('department.show');
                        Route::get('/edit/{id}', [DepartmentController::class, 'edit'])->name('department.edit');
                        Route::post('/store', [DepartmentController::class, 'store'])->name('department.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_DEPARTMENTS);
                        Route::post('/update/{id}', [DepartmentController::class, 'update'])->name('department.update');
                        Route::delete('/delete/{id}', [DepartmentController::class, 'delete'])->name('department.delete');
                        Route::get('/updateStatus/{id}', [DepartmentController::class, 'updateStatus'])->name('department.updateStatus');
                        Route::get('/export/view', [DepartmentController::class, 'export_view'])->name('department.export_view');
                        Route::post('/export', [DepartmentController::class, 'export'])->name('department.export');
                    });

                    Route::prefix('JobTitles')->middleware(['plan:'.HrPlanFeatures::JOB_TITLES, 'module:organizational_structure'])->group(function () {
                        Route::get('/index', [JobTitleController::class, 'index'])->name('JobTitles.index');
                        Route::get('/create', [JobTitleController::class, 'create'])->name('JobTitles.create');
                        Route::get('/show/{id}', [JobTitleController::class, 'show'])->name('JobTitles.show');
                        Route::get('/edit/{id}', [JobTitleController::class, 'edit'])->name('JobTitles.edit');
                        Route::post('/store', [JobTitleController::class, 'store'])->name('JobTitles.store');
                        Route::post('/update/{id}', [JobTitleController::class, 'update'])->name('JobTitles.update');
                        Route::delete('/delete/{id}', [JobTitleController::class, 'delete'])->name('JobTitles.delete');
                        Route::get('/updateStatus/{id}', [JobTitleController::class, 'updateStatus'])->name('JobTitles.updateStatus');
                    });

                    Route::prefix('ManagingFunctionalLevels')->middleware(['plan:'.HrPlanFeatures::FUNCTIONAL_LEVELS, 'module:organizational_structure'])->group(function () {
                        Route::get('/index', [ManagingFunctionalLevelsController::class, 'index'])->name('ManagingFunctionalLevels.index');
                        Route::get('/create', [ManagingFunctionalLevelsController::class, 'create'])->name('ManagingFunctionalLevels.create');
                        Route::get('/show/{id}', [ManagingFunctionalLevelsController::class, 'show'])->name('ManagingFunctionalLevels.show');
                        Route::get('/edit/{id}', [ManagingFunctionalLevelsController::class, 'edit'])->name('ManagingFunctionalLevels.edit');
                        Route::post('/store', [ManagingFunctionalLevelsController::class, 'store'])->name('ManagingFunctionalLevels.store');
                        Route::post('/update/{id}', [ManagingFunctionalLevelsController::class, 'update'])->name('ManagingFunctionalLevels.update');
                        Route::delete('/delete/{id}', [ManagingFunctionalLevelsController::class, 'delete'])->name('ManagingFunctionalLevels.delete');
                        Route::get('/updateStatus/{id}', [ManagingFunctionalLevelsController::class, 'updateStatus'])->name('ManagingFunctionalLevels.updateStatus');
                    });

                    Route::prefix('ManagingJobTypes')->middleware(['plan:'.HrPlanFeatures::FUNCTIONAL_LEVELS, 'module:organizational_structure'])->group(function () {
                        Route::get('/index', [ManagingJobTypesController::class, 'index'])->name('ManagingJobTypes.index');
                        Route::get('/create', [ManagingJobTypesController::class, 'create'])->name('ManagingJobTypes.create');
                        Route::get('/show/{id}', [ManagingJobTypesController::class, 'show'])->name('ManagingJobTypes.show');
                        Route::get('/edit/{id}', [ManagingJobTypesController::class, 'edit'])->name('ManagingJobTypes.edit');
                        Route::post('/store', [ManagingJobTypesController::class, 'store'])->name('ManagingJobTypes.store');
                        Route::post('/update/{id}', [ManagingJobTypesController::class, 'update'])->name('ManagingJobTypes.update');
                        Route::delete('/delete/{id}', [ManagingJobTypesController::class, 'delete'])->name('ManagingJobTypes.delete');
                        Route::get('/updateStatus/{id}', [ManagingJobTypesController::class, 'updateStatus'])->name('ManagingJobTypes.updateStatus');
                    });
                    /*
                        حُذفت شاشتا «جلسات الحضور» (`attendance-records`)
                        و«سجل جلسات الحضور» (`attendance-sessions-record`).

                        `AttendanceStampService` تكتب الختمَ الخام
                        (`attendance_punches`) وسجلَّ اليوم (`attendance_days`)
                        ولا تكتب جلسةً. فالشاشتان كانتا تُدخلان صفوفاً إلى
                        طبقةٍ وسطى لا يقرؤها حسابٌ ولا راتبٌ ولا تقرير —
                        وإدخالُ أوقاتٍ يدوياً على سجلٍّ لا أثرَ له أسوأُ من
                        عدمه: يوهم من أدخله أنه صحّح شيئاً.

                        والثانيةُ فرعٌ عن الأولى: جلساتُها تنتمي إلى
                        `AttendanceRecord` الذي لم يعد يُنشَأ، ونموذجُ
                        إنشائها كان يحيل إلى `attendance_records.create`.

                        وتصحيحُ أثرِ الحضور مسارُه واحد: «طلبات تصحيح الحضور»
                        ثم اعتمادُها — طلبٌ ومعتمِدٌ وسجلُّ تدقيق.

                        الجداول باقيةٌ ببياناتها التاريخية ولم تُمسّ، ومعها
                        `attendance_records` التي يقيس عليها `UsageReporter`
                        حدَّ الباقة.
                    */

                    Route::prefix('attendance-sheets')->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])->group(function () {
                        Route::get('/index', [AttendanceSheetsController::class, 'index'])->name('attendance_sheets.index');
                        Route::get('/create', [AttendanceSheetsController::class, 'create'])->name('attendance_sheets.create');
                        Route::get('/edit/{id}', [AttendanceSheetsController::class, 'edit'])->name('attendance_sheets.edit');
                        Route::get('/show/{id}', [AttendanceSheetsController::class, 'show'])->name('attendance_sheets.show');
                        Route::get('/attendance-sheets/ajax', [AttendanceSheetsController::class, 'getAttendanceSheetsAjax'])->name('attendance_sheets.ajax.index');
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */

                        Route::post('/store', [AttendanceSheetsController::class, 'store'])->name('attendance_sheets.store');
                        Route::put('/update/{id}', [AttendanceSheetsController::class, 'update'])->name('attendance_sheets.update');
                        Route::delete('/delete/{id}', [AttendanceSheetsController::class, 'delete'])->name('attendance_sheets.delete');
                    });

                    Route::prefix('attendanceDays')->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])->group(function () {
                        Route::get('/index', [AttendanceDaysController::class, 'index'])->name('attendanceDays.index');

                        Route::get('/attendance-days/filter', [AttendanceDaysController::class, 'filter'])->name('attendanceDays.filter');
                        Route::get('/create', [AttendanceDaysController::class, 'create'])->name('attendanceDays.create');
                        Route::get('/edit/{id}', [AttendanceDaysController::class, 'edit'])->name('attendanceDays.edit');
                        Route::get('/show/{id}', [AttendanceDaysController::class, 'show'])->name('attendanceDays.show');
                        Route::post('/store', [AttendanceDaysController::class, 'store'])->name('attendanceDays.store');
                        Route::put('/update/{id}', [AttendanceDaysController::class, 'update'])->name('attendanceDays.update');
                        Route::get('/calculation', [AttendanceDaysController::class, 'calculation'])->name('attendanceDays.calculation');
                        Route::post('/calculation', [AttendanceDaysController::class, 'runCalculation'])->name('attendanceDays.runCalculation');
                        Route::delete('/delete/{id}', [AttendanceDaysController::class, 'delete'])->name('attendanceDays.delete');
                    });

                    // نظام طلبات تصحيح الحضور — طلب → اعتماد → أثر، لا تعديلٌ مباشر
                    Route::prefix('attendance-corrections')
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'permission.role:attendance_corrections.index|attendance_corrections.create', 'module:employee_attendance'])
                        ->group(function () {
                            Route::get('/', [AttendanceCorrectionRequestController::class, 'index'])->name('hr.attendance-corrections.index');
                            Route::get('/create', [AttendanceCorrectionRequestController::class, 'create'])->name('hr.attendance-corrections.create');
                            Route::post('/', [AttendanceCorrectionRequestController::class, 'store'])->name('hr.attendance-corrections.store');
                            Route::get('/{id}', [AttendanceCorrectionRequestController::class, 'show'])->name('hr.attendance-corrections.show');
                            Route::put('/{id}/cancel', [AttendanceCorrectionRequestController::class, 'cancel'])->name('hr.attendance-corrections.cancel');
                        });

                    // نظام اعتماد طلبات تصحيح الحضور — صلاحيةٌ مستقلة عن التقديم
                    Route::prefix('attendance-correction-approvals')
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'permission.role:attendance_corrections.approve', 'module:employee_attendance'])
                        ->group(function () {
                            Route::get('/', [AttendanceCorrectionApprovalController::class, 'index'])->name('hr.attendance-correction-approvals.index');
                            Route::get('/{id}', [AttendanceCorrectionApprovalController::class, 'show'])->name('hr.attendance-correction-approvals.show');
                            Route::post('/{id}/approve', [AttendanceCorrectionApprovalController::class, 'approve'])->name('hr.attendance-correction-approvals.approve');
                            Route::post('/{id}/reject', [AttendanceCorrectionApprovalController::class, 'reject'])->name('hr.attendance-correction-approvals.reject');
                        });

                    Route::prefix('leave-permissions')->middleware(['plan:'.HrPlanFeatures::LEAVE_PERMISSIONS, 'module:employee_attendance'])->group(function () {
                        Route::get('/ajax', [LeavePermissionsController::class, 'getLeavePermissionsAjax'])->name('leave_permissions.ajax.index');
                        Route::get('/index', [LeavePermissionsController::class, 'index'])->name('leave_permissions.index');
                        Route::get('/create', [LeavePermissionsController::class, 'create'])->name('leave_permissions.create');
                        Route::post('/store', [LeavePermissionsController::class, 'store'])->name('leave_permissions.store');
                        Route::get('/show/{id}', [LeavePermissionsController::class, 'show'])->name('leave_permissions.show');
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */
                        Route::get('/edit/{id}', [LeavePermissionsController::class, 'edit'])->name('leave_permissions.edit');
                        Route::put('/update/{id}', [LeavePermissionsController::class, 'update'])->name('leave_permissions.update');
                        // الدالّة في المتحكّم اسمها delete لا destroy — وثلاثةُ قوالبَ تنادي هذا المسار
                        Route::delete('/delete/{id}', [LeavePermissionsController::class, 'delete'])->name('leave_permissions.destroy');
                    });

                    Route::prefix('leave_requests')->middleware(['plan:'.HrPlanFeatures::LEAVES, 'module:employee_attendance'])->group(function () {
                        // المسارات الأساسية الموجودة
                        Route::get('/index', [LeaveRequestsController::class, 'index'])->name('attendance.leave_requests.index');
                        Route::post('store', [LeaveRequestsController::class, 'store'])->name('attendance.leave_requests.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_LEAVE_REQUESTS);
                        Route::get('create', [LeaveRequestsController::class, 'create'])->name('attendance.leave_requests.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_LEAVE_REQUESTS);
                        Route::get('edit/{leaveRequest}', [LeaveRequestsController::class, 'edit'])->name('attendance.leave_requests.edit');
                        Route::get('show/{id}', [LeaveRequestsController::class, 'show'])->name('attendance.leave_requests.show');
                        Route::put('/update/{leaveRequest}', [LeaveRequestsController::class, 'update'])->name('attendance.leave_requests.update');
                        Route::delete('/delete/{id}', [LeaveRequestsController::class, 'destroy'])->name('attendance.leave_requests.destroy');

                        // مسارات البحث والتصفية
                        Route::get('/search', [LeaveRequestsController::class, 'search'])->name('attendance.leave_requests.search');
                        /*
                            حُذف من هنا ثلاثةُ مسارات:
                              /list        — تكرارُ اسمٍ مع /attendance/employees/list أدناه
                              /balance     ⎫ كلاهما يشير إلى getEmployeeLeaveBalance($employeeId)
                              leave-balance/show ⎭ بلا تمرير المُعامل، فكلُّ طلبٍ عليهما ينتهي بـ
                                             «Too few arguments» أي 500. ولا يشير إليهما قالبٌ واحد.
                            الصيغةُ العاملة هي /attendance/employees/{id}/leave-balance أدناه.
                        */

                        // المسارات الأساسية للموافقة والرفض (المحدثة)
                        Route::get('/attendance/employees/list', [LeaveRequestsController::class, 'getEmployeesList'])->name('attendance.employees.list');
                        Route::get('/attendance/employees/{id}/leave-balance', [LeaveRequestsController::class, 'getEmployeeLeaveBalance'])->name('attendance.employees.leave_balance');
                        // تاريخُ نهاية الإجازة بأيام العمل — المعادلةُ نفسُها التي يحفظ بها الخادم
                        Route::get('/attendance/employees/{id}/leave-end-date', [LeaveRequestsController::class, 'leaveEndDate'])->name('attendance.employees.leave_end_date');
                        //
                        //  Route::resource('leave-requests', LeaveRequestsController::class);

                        /*
                            approve/reject/cancelApproval تُلمِّح بالنوع
                            LeaveRequest $leaveRequest، وربطُ Laravel الضمني يطابق
                            بالاسم لا بالموضع — فمع {id} كان يصل عددٌ صحيحٌ لدالّةٍ
                            تنتظر نموذجاً وتُرمى TypeError.
                        */
                        Route::post('/{leaveRequest}/approve', [LeaveRequestsController::class, 'approve'])->name('attendance.leave_requests.approve');
                        Route::post('/{leaveRequest}/reject', [LeaveRequestsController::class, 'reject'])->name('attendance.leave_requests.reject');
                        Route::post('/{leaveRequest}/cancel-approval', [LeaveRequestsController::class, 'cancelApproval'])->name('attendance.leave_requests.cancelApproval');

                        // الشاشةُ تنادي هذا المسار لاعتماد الطلب ورفضه معاً، وترسل
                        // PATCH — وكان المسار POST وحدها، فحتى لو وُجدت الدالّة
                        // لاصطدم الطلبُ بـ405. نقبل الاثنين.
                        Route::match(['post', 'patch'], '/update-status/{leaveRequest}', [LeaveRequestsController::class, 'updateStatus'])->name('attendance.leave_requests.update_status');

                        Route::get('employees/{id}/leave-balance', [LeaveRequestsController::class, 'getEmployeeLeaveBalance'])
                            ->name('employees.leave_balance');

                        /*
                            حُذفت undo-rejection (مكرَّرةً مرّتين) و add-note و
                            notes/{noteId}: لا وجود لدوالّها في المتحكّم ولا قالبَ
                            يستدعيها، وكل طلبٍ عليها 500.
                        */
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */

                        Route::post('return-from-rejection/{leaveRequest}', [LeaveRequestsController::class, 'returnFromRejection'])->name('attendance.leave_requests.return_from_rejection');
                        // حُذف تكرارُ POST /update-status/{id}: كان يطغى على المسار أعلاه فيصل للدالّة نموذجٌ فارغ بدل الطلب (الربطُ الضمنيّ بالاسم {leaveRequest}).
                        // معالج موحد للعمليات (اختياري - يمكن استخدامه بدلاً من المسارات المنفصلة)
                        Route::post('process-action/{leaveRequest}', [LeaveRequestsController::class, 'processAction'])->name('attendance.leave_requests.process_action');
                    });
                    Route::prefix('employee-leave-balances')
                        ->middleware(['plan:'.HrPlanFeatures::LEAVE_BALANCES, 'module:employee_attendance'])
                        ->name('employee_leave_balances.')
                        ->group(function () {
                            Route::get('/', [EmployeeLeaveBalanceController::class, 'index'])->name('index');

                            Route::get('show/{id}', [EmployeeLeaveBalanceController::class, 'show'])->name('show');

                            Route::get('/create', [EmployeeLeaveBalanceController::class, 'create'])->name('create');

                            Route::post('/', [EmployeeLeaveBalanceController::class, 'store'])->name('store');

                            Route::get('/{employeeLeaveBalance}/edit', [EmployeeLeaveBalanceController::class, 'edit'])->name('edit');

                            Route::put('/{employeeLeaveBalance}', [EmployeeLeaveBalanceController::class, 'update'])->name('update');

                            Route::delete('/{employeeLeaveBalance}', [EmployeeLeaveBalanceController::class, 'destroy'])->name('destroy');

                            // Routes إضافية للوظائف المساعدة
                            Route::post('/check-existing', [EmployeeLeaveBalanceController::class, 'checkExisting'])->name('check_existing');

                            Route::post('/recalculate', [EmployeeLeaveBalanceController::class, 'recalculateBalances'])->name('recalculate');
                        });
                    Route::prefix('custom-shifts')->middleware(['plan:'.HrPlanFeatures::SHIFTS, 'module:employee_attendance'])->group(function () {
                        Route::get('/index', [CustomShiftsController::class, 'index'])->name('custom_shifts.index');
                        Route::get('custom_shifts/filter', [CustomShiftsController::class, 'filter'])->name('custom_shifts.filter');
                        Route::get('/create', [CustomShiftsController::class, 'create'])->name('custom_shifts.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_SHIFTS);
                        Route::post('/store', [CustomShiftsController::class, 'store'])->name('custom_shifts.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_SHIFTS);
                        Route::get('/show/{id}', [CustomShiftsController::class, 'show'])->name('custom_shifts.show');
                        Route::get('/edit/{id}', [CustomShiftsController::class, 'edit'])->name('custom_shifts.edit');
                        Route::put('/update/{id}', [CustomShiftsController::class, 'update'])->name('custom_shifts.update');
                        Route::delete('/delete/{id}', [CustomShiftsController::class, 'delete'])->name('custom_shifts.delete');
                    });

                    Route::prefix('barcode')->middleware(['plan:'.HrPlanFeatures::BARCODE_ATTENDANCE, 'module:employee_attendance'])->group(function () {
                        Route::get('/scan', [BarcodeAttendanceController::class, 'scanPage'])->name('barcode.scan');

                        // اقترانُ الشاشة بجهاز فرعٍ مُسجَّل — يُدخَل مرّةً ويُحفظ في الجلسة
                        Route::post('/kiosk/pair', [BarcodeAttendanceController::class, 'pairKiosk'])->name('barcode.kiosk.pair');
                        Route::post('/kiosk/unpair', [BarcodeAttendanceController::class, 'unpairKiosk'])->name('barcode.kiosk.unpair');
                        // «رمز المكتب» المتغيّر — لا يُصدَر إلا للشاشة المقترنة
                        Route::get('/office-code', [BarcodeAttendanceController::class, 'officeCode'])->name('barcode.office_code');

                        // مسحةٌ واحدة: تعريفٌ + اشتقاقُ النوع + ختمٌ في نداءٍ واحد
                        Route::post('/scan', [BarcodeAttendanceController::class, 'scan'])->name('barcode.scan.punch');

                        // معالجة الباركود الممسوح — تعريفٌ فقط (المسار اليدوي)
                        Route::post('/process', [BarcodeAttendanceController::class, 'processBarcode'])->name('barcode.process');

                        // تسجيل حضور
                        Route::post('/check-in', [BarcodeAttendanceController::class, 'checkIn'])->name('barcode.checkin');

                        // تسجيل انصراف
                        Route::post('/check-out', [BarcodeAttendanceController::class, 'checkOut'])->name('barcode.checkout');

                        // إحصائيات الداشبورد
                        Route::get('/stats', [BarcodeAttendanceController::class, 'dashboardStats'])->name('barcode.stats');

                        // توليد باركود لموظف واحد
                        // توليدُ بطاقةٍ يُبطل القديمة — تغييرٌ لا يُحمَّل برابطٍ يُفتح أو يُحمَّل مسبقاً
                        Route::post('/employee/{id}/generate', [BarcodeAttendanceController::class, 'generateBarcode'])->name('barcode.generate');

                        // توليد باركود لجميع الموظفين
                        Route::post('/generate-all', [BarcodeAttendanceController::class, 'generateAllBarcodes'])->name('barcode.generate.all');

                        // تقرير الحضور اليومي
                        Route::get('/daily-report', [BarcodeAttendanceController::class, 'dailyReport'])->name('barcode.daily.report');

                        // البحث عن موظف بالباركود (للاختبار)
                        Route::post('/search-employee', [BarcodeAttendanceController::class, 'searchEmployeeByBarcode'])->name('barcode.search.employee');

                        // طباعة باركود الموظف
                        Route::get('/employee/{id}/print', [BarcodeAttendanceController::class, 'printEmployeeBarcode'])->name('barcode.print');

                        // تصدير تقرير إكسل
                        Route::get('/export/{date?}', [BarcodeAttendanceController::class, 'exportAttendance'])->name('barcode.export');

                        /*
                            إدارةُ البطاقات: إصدارٌ وطباعةٌ وسحبٌ فوري.
                            الوسائلُ الثلاث كانت مكتوبةً في المتحكّم بلا
                            مسارٍ يؤدّي إليها — شاشةٌ كاملةٌ لا تُفتح.
                        */
                        Route::get('/cards', [BarcodeAttendanceController::class, 'cards'])->name('barcode.cards');
                        // طباعةُ دفعةٍ بنفس تصفية الشاشة — حلّت محلّ «المولّد الجماعي» اليتيم
                        Route::get('/cards/print', [BarcodeAttendanceController::class, 'printCards'])->name('barcode.cards.print');
                        Route::post('/employee/{id}/toggle', [BarcodeAttendanceController::class, 'toggleEmployeeBarcode'])->name('barcode.card.toggle');
                        Route::post('/employee/{id}/channel', [BarcodeAttendanceController::class, 'setEmployeeChannel'])->name('barcode.card.channel');
                        Route::delete('/employee/{id}/card', [BarcodeAttendanceController::class, 'deleteEmployeeBarcode'])->name('barcode.card.delete');

                        /*
                            الطباعةُ الجماعية: بطاقاتُ من تُصفّيهم الشاشةُ الآن
                            في ورقةٍ واحدة. ورثت هذه الوظيفةَ عن شاشة «مولّد
                            الباركود الجماعي» المحذوفة — وكانت هناك تُركَّب
                            نصّاً في المتصفّح (`window.open` ثم بناءُ HTML
                            بالسلاسل)، فتخرج بتصميمٍ ثانٍ لا يشبه البطاقة
                            المعتمدة. وهي الآن تُصيَّر في الخادم بجزئيّة
                            البطاقة نفسِها التي تطبعها الشاشةُ المفردة.
                        */
                        Route::get('/cards/print', [BarcodeAttendanceController::class, 'printCards'])->name('barcode.cards.print');
                    });

                    /*
                        المدخلُ الميداني: الموظفُ يسجّل لنفسه من جواله.
                        لا `employee_id` في أيّ مسارٍ هنا — يُشتقّ من الجلسة،
                        وإلا سجّل كلُّ موظفٍ حضورَ زميلِه بتبديل رقم.
                    */
                    Route::prefix('field-attendance')
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->group(function () {
                            Route::get('/', [FieldAttendanceController::class, 'index'])->name('hr.field-attendance.index');
                            Route::get('/status', [FieldAttendanceController::class, 'status'])->name('hr.field-attendance.status');
                            Route::post('/check-in', [FieldAttendanceController::class, 'checkIn'])->name('hr.field-attendance.checkin');
                            Route::post('/check-out', [FieldAttendanceController::class, 'checkOut'])->name('hr.field-attendance.checkout');
                            // الاستراحة أثناء الدوام — بدءٌ وإنهاء
                            Route::post('/break', [FieldAttendanceController::class, 'toggleBreak'])->name('hr.field-attendance.break');
                            // «رمز المكتب»: الموظفُ يمسح رمزَ المكتب بجواله
                            Route::post('/office-scan', [FieldAttendanceController::class, 'officeScan'])->name('hr.field-attendance.office_scan');
                        });

                    // لوحةُ الحضور — مدخلُ القسم: خطواتُ التجهيز وأرقامُ اليوم
                    Route::get('/attendance', [\Modules\HR\Http\Controllers\Attendance\AttendanceHubController::class, 'index'])
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->name('hr.attendance.hub');

                    // سجلُّ اليوم الموحَّد — كل الحركات لكل الموظفين في يومٍ واحد
                    Route::get('/attendance-log', [UnifiedAttendanceLogController::class, 'index'])
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->name('hr.attendance-log.index');

                    // سجلُّ جلسات الحضور: حالةُ الأجهزة لا حالةُ الموظفين
                    Route::get('/device-monitor', [DeviceMonitorController::class, 'index'])
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->name('hr.device-monitor.index');

                    // لوحةُ متابعة الفريق — نطاقُها من الهيكل لا من الصلاحية
                    Route::prefix('team-board')
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->group(function () {
                            Route::get('/', [TeamLiveStatusController::class, 'index'])->name('hr.team-board.index');
                            Route::get('/feed', [TeamLiveStatusController::class, 'feed'])->name('hr.team-board.feed');
                        });

                    // سجلُّ الأختام — قراءةٌ فقط؛ لا مسارَ تعديلٍ ولا حذف
                    Route::get('/attendance-punches', [AttendancePunchesController::class, 'index'])
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE, 'module:employee_attendance'])
                        ->name('hr.attendance-punches.index');

                    Route::prefix('settings')->middleware('module:employee_attendance')->group(function () {
                        Route::get('/index', [AttendanceSettingsController::class, 'index'])->name('attendance.settings.index');

                        // ===== أماكن التسجيل — قائمة، إضافة، عرض، تعديل =====
                        Route::prefix('geofences')->group(function () {
                            Route::get('/', [GeofencesController::class, 'index'])->name('attendance.settings.geofences.index');
                            Route::get('/create', [GeofencesController::class, 'create'])->name('attendance.settings.geofences.create');
                            Route::post('/', [GeofencesController::class, 'storeGeofence'])->name('attendance.settings.geofences.store');
                            Route::get('/{id}', [GeofencesController::class, 'show'])->whereNumber('id')->name('attendance.settings.geofences.show');
                            Route::get('/{id}/edit', [GeofencesController::class, 'edit'])->whereNumber('id')->name('attendance.settings.geofences.edit');
                            Route::put('/{id}', [GeofencesController::class, 'updateGeofence'])->whereNumber('id')->name('attendance.settings.geofences.update');
                            Route::delete('/{id}', [GeofencesController::class, 'destroyGeofence'])->whereNumber('id')->name('attendance.settings.geofences.delete');
                        });

                        // ===== أجهزة تسجيل الحضور في الفروع — قائمة، إضافة، عرض، تعديل =====
                        Route::prefix('kiosks')->group(function () {
                            Route::get('/', [GeofencesController::class, 'kioskIndex'])->name('attendance.settings.kiosks.index');
                            Route::get('/create', [GeofencesController::class, 'kioskCreate'])->name('attendance.settings.kiosks.create');
                            Route::post('/', [GeofencesController::class, 'storeKiosk'])->name('attendance.settings.kiosks.store');
                            Route::get('/{id}', [GeofencesController::class, 'kioskShow'])->whereNumber('id')->name('attendance.settings.kiosks.show');
                            Route::get('/{id}/edit', [GeofencesController::class, 'kioskEdit'])->whereNumber('id')->name('attendance.settings.kiosks.edit');
                            Route::put('/{id}', [GeofencesController::class, 'updateKiosk'])->whereNumber('id')->name('attendance.settings.kiosks.update');
                            Route::post('/{id}/rotate', [GeofencesController::class, 'rotateKioskCode'])->whereNumber('id')->name('attendance.settings.kiosks.rotate');
                            Route::post('/{id}/release', [GeofencesController::class, 'releaseKioskDevice'])->whereNumber('id')->name('attendance.settings.kiosks.release');
                            // «رمز المكتب» المطبوع
                            Route::get('/{id}/office-code', [GeofencesController::class, 'printOfficeCode'])->whereNumber('id')->name('attendance.settings.kiosks.office_code');
                            Route::post('/{id}/office-code/rotate', [GeofencesController::class, 'rotateOfficeCode'])->whereNumber('id')->name('attendance.settings.kiosks.office_code.rotate');
                            // الموظفون المربوطون بالجهاز
                            Route::post('/{id}/employees', [GeofencesController::class, 'attachKioskEmployees'])->whereNumber('id')->name('attendance.settings.kiosks.employees.attach');
                            Route::delete('/{id}/employees/{employeeId}', [GeofencesController::class, 'detachKioskEmployee'])->whereNumber(['id', 'employeeId'])->name('attendance.settings.kiosks.employees.detach');
                            Route::delete('/{id}', [GeofencesController::class, 'destroyKiosk'])->whereNumber('id')->name('attendance.settings.kiosks.delete');
                        });

                        // ===== Attendance V2 Settings =====
                        Route::prefix('attendance-v2')->middleware('plan:'.HrPlanFeatures::ATTENDANCE_RULES)->group(function () {
                            Route::get('/', [AttendanceV2SettingsController::class, 'index'])->name('attendance.v2.settings.index');
                            Route::post('/general', [AttendanceV2SettingsController::class, 'updateGeneral'])->name('attendance.v2.settings.general');
                            Route::post('/photo-types', [AttendanceV2SettingsController::class, 'storePhotoType'])->name('attendance.v2.settings.photo_types.store');
                            Route::post('/photo-types/{id}/toggle', [AttendanceV2SettingsController::class, 'togglePhotoType'])->name('attendance.v2.settings.photo_types.toggle');
                            Route::delete('/photo-types/{id}', [AttendanceV2SettingsController::class, 'deletePhotoType'])->name('attendance.v2.settings.photo_types.delete');
                            Route::get('/employee-config/{employeeId}', [AttendanceV2SettingsController::class, 'employeeConfig'])->name('attendance.v2.settings.employee_config.show');
                            Route::post('/employee-config', [AttendanceV2SettingsController::class, 'storeEmployeeConfig'])->name('attendance.v2.settings.employee_config.store');
                            Route::delete('/employee-config/{id}', [AttendanceV2SettingsController::class, 'deleteEmployeeConfig'])->name('attendance.v2.settings.employee_config.delete');
                        });
                        // Holiday lists
                        Route::prefix('holiday-lists')->middleware('plan:'.HrPlanFeatures::HOLIDAYS)->group(function () {
                            Route::get('/index', [HolidayController::class, 'index'])->name('holiday_lists.index');
                            Route::get('/create', [HolidayController::class, 'create'])->name('holiday_lists.create');

                            Route::get('/search', [HolidayController::class, 'search'])->name('holiday_lists.search');
                            Route::get('/employees_count/{id}', [HolidayController::class, 'employeesCount'])->name('holiday_lists.employees_count');

                            Route::get('/employees_list/{id}', [HolidayController::class, 'employeesList'])->name('holiday_lists.employees_list');
                            Route::delete('/remove_employee/{id}', [HolidayController::class, 'removeEmployee'])->name('holiday_lists.remove_employee');

                            Route::post('/store', [HolidayController::class, 'store'])->name('holiday_lists.store');
                            Route::get('/show/{id}', [HolidayController::class, 'show'])->name('holiday_lists.show');
                            Route::get('/edit/{id}', [HolidayController::class, 'edit'])->name('holiday_lists.edit');
                            Route::post('/update/{id}', [HolidayController::class, 'update'])->name('holiday_lists.update');
                            Route::delete('/delete/{id}', [HolidayController::class, 'delete'])->name('holiday_lists.delete');
                            Route::get('/holyday_employees/{id}', [HolidayController::class, 'holyday_employees'])->name('holiday_lists.holyday_employees');
                            Route::post('/holyday_employees/add/{id}', [HolidayController::class, 'add_holyday_employees'])->name('holiday_lists.add_holyday_employees');
                            Route::delete('/holyday_employees/{id}/remove/{assignmentId}', [HolidayController::class, 'removeAssignment'])->whereNumber(['id', 'assignmentId'])->name('holiday_lists.remove_assignment');
                        });
                        // Leave Types
                        Route::prefix('leave-types')->middleware('plan:'.HrPlanFeatures::LEAVE_TYPES)->group(function () {
                            Route::get('/index', [LeaveTypesController::class, 'index'])->name('leave_types.index');
                            Route::get('/create', [LeaveTypesController::class, 'create'])->name('leave_types.create');
                            Route::post('/store', [LeaveTypesController::class, 'store'])->name('leave_types.store');
                            Route::get('/show/{id}', [LeaveTypesController::class, 'show'])->name('leave_types.show');
                            Route::get('/edit/{id}', [LeaveTypesController::class, 'edit'])->name('leave_types.edit');
                            Route::put('/update/{id}', [LeaveTypesController::class, 'update'])->name('leave_types.update');
                            Route::delete('/delete/{id}', [LeaveTypesController::class, 'delete'])->name('leave_types.delete');
                        });
                        // Leave Policy
                        Route::prefix('leave-policies')->middleware('plan:'.HrPlanFeatures::LEAVE_TYPES)->group(function () {
                            Route::get('/index', [LeavePoliciesController::class, 'index'])->name('leave_policy.index');
                            Route::get('/search', [LeavePoliciesController::class, 'search'])->name('leave_policy.search');
                            Route::get('/employees_count/{id}', [LeavePoliciesController::class, 'employeesCount'])->name('leave_policy.employeesCount');
                            Route::get('/getStatistics/{id}', [LeavePoliciesController::class, 'getStatistics'])->name('leave_policy.getStatistics');

                            Route::get('/employees_list-leave/{id}', [LeavePoliciesController::class, 'employeesList'])->name('leave_policy.employeesList');
                            Route::delete('/remove_employee/{id}', [LeavePoliciesController::class, 'removeEmployee'])->name('leave_policy.removeEmployee');

                            Route::get('create', [LeavePoliciesController::class, 'create'])->name('leave_policy.create');
                            Route::post('store', [LeavePoliciesController::class, 'store'])->name('leave_policy.store');
                            Route::get('show/{id}', [LeavePoliciesController::class, 'show'])->name('leave_policy.show');
                            Route::get('edit/{id}', [LeavePoliciesController::class, 'edit'])->name('leave_policy.edit');
                            Route::post('update/{id}', [LeavePoliciesController::class, 'update'])->name('leave_policy.update');
                            Route::delete('delete/{id}', [LeavePoliciesController::class, 'delete'])->name('leave_policy.delete');
                            Route::get('updateStatus/{id}', [LeavePoliciesController::class, 'updateStatus'])->name('leave_policy.updateStatus');
                            Route::get('leave_policy_employees/{id}', [LeavePoliciesController::class, 'leave_policy_employees'])->name('leave_policy.leave_policy_employees');
                            Route::post('/leave_policy_employees/add/{id}', [LeavePoliciesController::class, 'add_leave_policy_employees'])->name('leave_policy.add_leave_policy_employees');
                        });
                        // Basic settings
                        Route::prefix('basic-settings')->middleware('plan:'.HrPlanFeatures::ATTENDANCE_RULES)->group(function () {
                            Route::get('/index', [BasicController::class, 'index'])->name('settings_basic.index');
                            Route::post('/update', [BasicController::class, 'update'])->name('settings_basic.update');
                        });
                        // Attendance Determinants
                        Route::prefix('attendance_determinants')->middleware('plan:'.HrPlanFeatures::ATTENDANCE_RULES)->group(function () {
                            Route::get('/index', [AttendanceDeterminantsController::class, 'index'])->name('attendance_determinants.index');
                            Route::get('/ajax', [AttendanceDeterminantsController::class, 'ajax'])->name('attendance_determinants.ajax');
                            Route::get('/create', [AttendanceDeterminantsController::class, 'create'])->name('attendance_determinants.create');
                            Route::post('/store', [AttendanceDeterminantsController::class, 'store'])->name('attendance_determinants.store');
                            Route::get('/show/{id}', [AttendanceDeterminantsController::class, 'show'])->name('attendance_determinants.show');
                            /*
                                اسمُ المُعامل هنا ليس اعتباطاً: edit() و update()
                                تُلمِّحان بالنوع AttendanceDeterminant $attendance_determinants،
                                وربطُ Laravel الضمني لا يعمل إلا إذا حمل مُعاملُ
                                المسار الاسمَ نفسه. مع {id} كان يصل عددٌ صحيحٌ
                                لدالّةٍ تنتظر نموذجاً، فترمي TypeError أي 500.
                            */
                            Route::get('/edit/{attendance_determinants}', [AttendanceDeterminantsController::class, 'edit'])->name('attendance_determinants.edit');

                            Route::get('/{id}/manage-employees', [AttendanceDeterminantsController::class, 'manageEmployees'])->name('attendance_determinants.manage_employees');
                            Route::get('/{id}/employees-count', [AttendanceDeterminantsController::class, 'employeesCount'])->name('attendance_determinants.employees_count');

                            Route::post('/{id}/add-employees', [AttendanceDeterminantsController::class, 'addEmployees'])->name('attendance_determinants.add_employees');
                            Route::post('/{id}/employees-list', [AttendanceDeterminantsController::class, 'employeesList'])->name('attendance_determinants.employees_list');
                            Route::post('/{id}/remove-employee', [AttendanceDeterminantsController::class, 'removeEmployee'])->name('attendance_determinants.remove_employee');
                            Route::get('/{id}/statistics', [AttendanceDeterminantsController::class, 'getStatistics'])->name('attendance_determinants.statistics');
                            Route::post('/preview-rules', [AttendanceDeterminantsController::class, 'previewRules'])->name('attendance_determinants.preview_rules');
                            /* حُذف delete_customization: لا دالّة له ولا قالبَ يستدعيه */
                            /* حُذف مسار apply: المحدِّد يُطبَّق تلقائياً عند التسجيل */
                            Route::post('/update/{attendance_determinants}', [AttendanceDeterminantsController::class, 'update'])->name('attendance_determinants.update');
                            Route::delete('/delete/{id}', [AttendanceDeterminantsController::class, 'destroy'])->name('attendance_determinants.destroy');

                            /*
                                حُذف updateStatus: الدالّة غير موجودة في المتحكّم
                                فكل طلبٍ عليه 500، ولا قالبَ يستدعيه. وكان GET
                                يغيّر حالةً — وهو ما لا يجوز أصلاً.
                            */
                        });
                    });

                    Route::prefix('machines')->middleware(['plan:'.HrPlanFeatures::ATTENDANCE_DEVICES, 'module:employee_attendance'])->group(function () {
                        Route::get('/index', [MachinesController::class, 'index'])->name('machines.index');
                        Route::get('/search', [MachinesController::class, 'search'])->name('machines.search');

                        Route::get('create', [MachinesController::class, 'create'])->name('machines.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_DEVICES);
                        Route::post('store', [MachinesController::class, 'store'])->name('machines.store')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_DEVICES);
                        Route::get('edit/{id}', [MachinesController::class, 'edit'])->name('machines.edit');
                        Route::put('update/{id}', [MachinesController::class, 'update'])->name('machines.update');
                        Route::get('show/{id}', [MachinesController::class, 'show'])->name('machines.show');
                        Route::delete('destroy/{id}', [MachinesController::class, 'destroy'])->name('machines.destroy');
                        Route::post('toggle-status/{id}', [MachinesController::class, 'toggleStatus'])->name('machines.toggle-status');

                        // إضافة المسارات الجديدة
                        Route::get('test-connection/{id}', [MachinesController::class, 'testConnection'])->name('machines.test-connection');
                        Route::post('pull-data/{id}', [MachinesController::class, 'pullData'])->name('machines.pull-data');
                        Route::post('connect/{id}', [MachinesController::class, 'connect'])->name('machines.connect');
                    });

                    /*
                        «قوالب الطباعة» في الموارد البشرية أُحيلت (2026-09-24): كانت نسخةً
                        ثانيةً من «قوالب المستندات» في الإعدادات العامة. المساراتُ تبقى
                        بأسمائها كي لا تنكسر روابطُ قديمة، وتُحوِّل إلى الشاشة الموحَّدة
                        مصفّاةً على مستندات الموارد البشرية (`?group=hr`).
                        `PrintableTemplatesController` لم يعد موصولاً بمسار.
                    */
                    Route::prefix('printable-templates')->group(function () {
                        $toGlobal = fn () => redirect()->route('document-templates.index', ['group' => 'hr']);
                        Route::get('/index', $toGlobal)->name('attendance.settings.printable-templates.index');
                        Route::get('/create', $toGlobal)->name('attendance.settings.printable-templates.create');
                        Route::post('/store', $toGlobal)->name('attendance.settings.printable-templates.store');
                    });
                    Route::prefix('Contracts')->middleware(['plan:'.HrPlanFeatures::CONTRACTS, 'module:salaries'])->group(function () {
                        Route::get('/index', [ContractsController::class, 'index'])->name('Contracts.index')->middleware('permission.role:contracts.index');
                        Route::get('/create', [ContractsController::class, 'create'])->name('Contracts.create')->middleware(['permission.role:contracts.create', 'plan.limit:'.HrPlanFeatures::LIMIT_CONTRACTS]);
                        Route::post('/store', [ContractsController::class, 'store'])->name('Contracts.store')->middleware(['permission.role:contracts.create', 'plan.limit:'.HrPlanFeatures::LIMIT_CONTRACTS]);
                        Route::get('/show/{id}', [ContractsController::class, 'show'])->name('Contracts.show')->middleware('permission.role:contracts.show');
                        Route::get('/edit/{id}', [ContractsController::class, 'edit'])->name('Contracts.edit')->middleware('permission.role:contracts.edit');
                        Route::put('/update/{id}', [ContractsController::class, 'update'])->name('Contracts.update')->middleware('permission.role:contracts.edit');
                        Route::delete('/destroy/{id}', [ContractsController::class, 'destroy'])->name('Contracts.destroy')->middleware('permission.role:contracts.destroy');
                        // «اعتماد العقد» — المسودّةُ تصير معتمدة
                        Route::post('/approve/{id}', [ContractsController::class, 'approve'])->name('Contracts.approve')->middleware('permission.role:contracts.approve');
                        // بياناتُ الموظف لملء العقد تلقائياً (المسمّى والمستوى والفرع والقسم وعقودُه السابقة)
                        Route::get('/employee-data/{employee}', [ContractsController::class, 'employeeData'])->name('Contracts.employeeData')->middleware('permission.role:contracts.create|contracts.edit');
                        // الطباعةُ من «قوالب المستندات» وحدها — والمساران القديمان يُحوَّلان إليها كي لا تنكسر الروابط
                        Route::get('/print-template/{id}/{template?}', [ContractsController::class, 'printTemplate'])->name('Contracts.printTemplate')->middleware('permission.role:contracts.print');
                        Route::get('/contracts/print/{id}', [ContractsController::class, 'printContract'])->name('Contracts.print')->middleware('permission.role:contracts.print');
                        Route::get('/contracts/print1/{id}', [ContractsController::class, 'printContract1'])->name('Contracts.print1')->middleware('permission.role:contracts.print');
                    });
                    Route::prefix('PayrollProcess')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/index', [PayrollProcessController::class, 'index'])->name('PayrollProcess.index')->middleware('permission.role:payroll.index');
                        Route::get('/create', [PayrollProcessController::class, 'create'])->name('PayrollProcess.create')->middleware('permission.role:payroll.create');
                        Route::post('/store', [PayrollProcessController::class, 'store'])->name('PayrollProcess.store')->middleware('permission.role:payroll.create');
                        Route::get('/show/{id}', [PayrollProcessController::class, 'show'])->name('PayrollProcess.show')->middleware('permission.role:payroll.show');
                        Route::get('/edit/{id}', [PayrollProcessController::class, 'edit'])->name('PayrollProcess.edit')->middleware('permission.role:payroll.edit');
                        Route::put('/edit/{id}', [PayrollProcessController::class, 'update'])->name('PayrollProcess.update')->middleware('permission.role:payroll.edit');
                        Route::delete('/destroy/{id}', [PayrollProcessController::class, 'destroy'])->name('PayrollProcess.destroy')->middleware('permission.role:payroll.destroy');
                        Route::get('/approve-all/{id}', [PayrollProcessController::class, 'approveAll'])->name('PayrollProcess.approveAll')->middleware('permission.role:payslips.approve');
                        Route::post('/approve-runs/{id}', [PayrollProcessController::class, 'approveRuns'])->name('PayrollProcess.approveRuns')->middleware('permission.role:payroll.approve');
                        Route::post('/approve/{id}', [PayrollProcessController::class, 'approve'])->name('PayrollProcess.approve')->middleware('permission.role:payroll.approve');
                        // «صرف المستحق»: اعتمادُ المسير يكفي — لا «مسير صرفات»
                        Route::post('/disburse-due/{id}', [PayrollProcessController::class, 'disburseDue'])->name('PayrollProcess.disburseDue')->middleware('permission.role:payslips.disburse');
                        Route::post('/deactivate/{id}', [PayrollProcessController::class, 'deactivate'])->name('PayrollProcess.deactivate')->middleware('permission.role:payroll.approve');
                        Route::post('/activate/{id}', [PayrollProcessController::class, 'activate'])->name('PayrollProcess.activate')->middleware('permission.role:payroll.approve');
                        Route::get('/export-slips/{id}', [PayrollProcessController::class, 'exportSlips'])->name('PayrollProcess.exportSlips')->middleware('permission.role:payroll.export');
                        // طباعةُ كشف المسير بقوالب المستندات، ومعاينةُ من ستضمّهم القواعد
                        Route::get('/print/{id}', [PayrollProcessController::class, 'print'])->name('PayrollProcess.print')->middleware('permission.role:payroll.print');
                        Route::get('/preview-employees', [PayrollProcessController::class, 'previewEmployees'])->name('PayrollProcess.previewEmployees')->middleware('permission.role:payroll.create|payroll.edit');
                    });
                    // ============== قسائم الرواتب — اتفاقيات مستمرة ==============
                    Route::prefix('SalarySlip')->middleware(['plan:'.HrPlanFeatures::PAYSLIPS, 'module:salaries'])->group(function () {
                        Route::get('/index', [SalarySlipController::class, 'index'])->name('salarySlip.index')->middleware('permission.role:payslips.index');
                        Route::get('/create', [SalarySlipController::class, 'create'])->name('salarySlip.create')->middleware('permission.role:payslips.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_PAYSLIPS);
                        Route::post('/store', [SalarySlipController::class, 'store'])->name('salarySlip.store')->middleware('permission.role:payslips.create')->middleware('plan.limit:'.HrPlanFeatures::LIMIT_PAYSLIPS);
                        Route::get('/show/{id}', [SalarySlipController::class, 'show'])->name('salarySlip.show')->middleware('permission.role:payslips.show');

                        // ⚠️ الموافقة لم تعد تصرف الراتب — فقط تنقل القسيمة إلى approved
                        Route::post('/{id}/approve', [SalarySlipController::class, 'approve'])->name('salarySlip.approve')->middleware('permission.role:payslips.approve');
                        Route::post('/{id}/stop', [SalarySlipController::class, 'stop'])->name('salarySlip.stop')->middleware('permission.role:payslips.approve');

                        // ⚠️ ====== TEST ONLY — مُجمَّد، أزل التعليق لإعادة التفعيل ======
                        // Route::post('/{id}/test-disburse', [SalarySlipController::class, 'testDisburse'])->name('salarySlip.testDisburse');

                        Route::get('/edit/{id}', [SalarySlipController::class, 'edit'])->name('salarySlip.edit')->middleware('permission.role:payslips.edit');
                        Route::put('/update/{id}', [SalarySlipController::class, 'update'])->name('salarySlip.update')->middleware('permission.role:payslips.edit');
                        Route::delete('/destroy/{id}', [SalarySlipController::class, 'destroy'])->name('salarySlip.destroy')->middleware('permission.role:payslips.destroy');

                        // AJAX: جلب بيانات الموظف (عقد + سلف + خصميات)
                        Route::get('/employee-payroll-data/{employeeId}', [SalarySlipController::class, 'getEmployeePayrollData'])->name('salarySlip.employeePayrollData')->middleware('permission.role:payslips.create|payslips.edit');
                        // AJAX: موظفو المسير المختار وحدهم — لقائمة الموظف في نموذج القسيمة
                        Route::get('/payroll-employees/{payrollId}', [SalarySlipController::class, 'payrollEmployees'])->name('salarySlip.payrollEmployees')->middleware('permission.role:payslips.create|payslips.edit');
                        // طباعةُ القسيمة بقوالب المستندات
                        Route::get('/print/{id}', [SalarySlipController::class, 'print'])->name('salarySlip.print')->middleware('permission.role:payslips.print');
                    });

                    // ============== عمليات صرف الرواتب الشهرية (Payroll Runs) ==============
                    Route::prefix('payroll-runs')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/', [PayrollRunController::class, 'index'])->name('payroll-runs.index')->middleware('permission.role:disbursements.index');
                        Route::get('/{id}', [PayrollRunController::class, 'show'])->name('payroll-runs.show')->middleware('permission.role:disbursements.show');
                        Route::post('/{id}/disburse', [PayrollRunController::class, 'disburse'])->name('payroll-runs.disburse')->middleware('permission.role:payslips.disburse');
                        Route::post('/{id}/cancel', [PayrollRunController::class, 'cancel'])->name('payroll-runs.cancel')->middleware('permission.role:disbursements.cancel');
                        Route::post('/{id}/reverse', [PayrollRunController::class, 'reverse'])->name('payroll-runs.reverse')->middleware('permission.role:disbursements.reverse');
                        // الإيقافُ ورفعُه بصلاحية، والتسويةُ الرجعية بصلاحيةٍ أخرى مستقلّة
                        Route::post('/{id}/hold', [PayrollRunController::class, 'hold'])->name('payroll-runs.hold')->middleware('permission.role:disbursements.stop');
                        Route::post('/{id}/release', [PayrollRunController::class, 'release'])->name('payroll-runs.release')->middleware('permission.role:disbursements.stop');
                        Route::post('/{id}/retro', [PayrollRunController::class, 'addRetro'])->name('payroll-runs.retro.add')->middleware('permission.role:disbursements.settle');
                        Route::post('/{id}/retro/{item}', [PayrollRunController::class, 'removeRetro'])->name('payroll-runs.retro.remove')->middleware('permission.role:disbursements.settle');
                        Route::get('/{id}/print', [PayrollRunController::class, 'print'])->name('payroll-runs.print')->middleware('permission.role:payslips.print');
                    });
                    Route::prefix('payroll-batches')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/', [PayrollBatchController::class, 'index'])->name('hr.payroll-batches.index')->middleware('permission.role:disbursements.index|payroll.approve');
                        Route::get('/create', [PayrollBatchController::class, 'create'])->name('hr.payroll-batches.create')->middleware('permission.role:disbursements.index|payroll.approve');
                        Route::post('/', [PayrollBatchController::class, 'store'])->name('hr.payroll-batches.store')->middleware('permission.role:disbursements.index|payroll.approve');
                        Route::get('/{id}', [PayrollBatchController::class, 'show'])->name('hr.payroll-batches.show')->middleware('permission.role:disbursements.index|payroll.approve');
                        Route::post('/{id}/approve', [PayrollBatchController::class, 'approve'])->name('hr.payroll-batches.approve')->middleware('permission.role:disbursements.index|payroll.approve');
                    });
                    Route::prefix('end-of-service')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/', [EndOfServiceBenefitController::class, 'index'])->name('hr.eos.index')->middleware('permission.role:eos.index');
                        Route::post('/', [EndOfServiceBenefitController::class, 'store'])->name('hr.eos.store')->middleware('permission.role:eos.create');
                        Route::get('/settings', [EndOfServiceBenefitController::class, 'settings'])->name('hr.eos.settings')->middleware('permission.role:eos.settings');
                        Route::post('/settings', [EndOfServiceBenefitController::class, 'updateSettings'])->name('hr.eos.settings.update')->middleware('permission.role:eos.settings');
                        Route::get('/{id}', [EndOfServiceBenefitController::class, 'show'])->name('hr.eos.show')->middleware('permission.role:eos.show');
                        Route::post('/{id}/recalculate', [EndOfServiceBenefitController::class, 'recalculate'])->name('hr.eos.recalculate')->middleware('permission.role:eos.create');
                        Route::post('/{id}/approve', [EndOfServiceBenefitController::class, 'approve'])->name('hr.eos.approve')->middleware('permission.role:eos.approve');
                        // التسويةُ إلى الذمّة ثمّ الصرفُ النقدي — خطوتان لا واحدة
                        Route::post('/{id}/settle', [EndOfServiceBenefitController::class, 'settle'])->name('hr.eos.settle')->middleware('permission.role:eos.settle');
                        Route::post('/{id}/pay', [EndOfServiceBenefitController::class, 'pay'])->name('hr.eos.pay')->middleware('permission.role:eos.pay');
                    });
                    Route::prefix('social-insurance')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/settings', [SocialInsuranceSettingsController::class, 'edit'])->name('hr.gosi.settings')->middleware('permission.role:insurance.settings');
                        Route::post('/settings', [SocialInsuranceSettingsController::class, 'update'])->name('hr.gosi.settings.update')->middleware('permission.role:insurance.settings');

                        // سدادُ ما حُجز: الذمّتان تُقفلان نقداً من خزينة.
                        // الاطّلاعُ (وكشفُ الاشتراكات وتصديرُه) مفصولٌ عن إخراج النقد.
                        Route::get('/remittances', [SocialInsuranceSettingsController::class, 'remittances'])->name('hr.gosi.remittances')->middleware('permission.role:insurance.index|insurance.show');
                        Route::post('/remittances', [SocialInsuranceSettingsController::class, 'remit'])->name('hr.gosi.remit')->middleware('permission.role:insurance.pay');
                    });
                    Route::prefix('payroll-bank-files')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        Route::get('/', [PayrollBankFileController::class, 'index'])->name('hr.bank-files.index')->middleware('permission.role:bank_files.index');
                        Route::post('/', [PayrollBankFileController::class, 'store'])->name('hr.bank-files.store')->middleware('permission.role:bank_files.create');
                        Route::get('/settings', [PayrollBankFileController::class, 'settings'])->name('hr.bank-files.settings')->middleware('permission.role:payroll_settings.manage');
                        Route::post('/settings', [PayrollBankFileController::class, 'updateSettings'])->name('hr.bank-files.settings.update')->middleware('permission.role:payroll_settings.manage');
                        Route::get('/{id}', [PayrollBankFileController::class, 'show'])->name('hr.bank-files.show')->middleware('permission.role:bank_files.show');
                        Route::get('/{id}/download', [PayrollBankFileController::class, 'download'])->name('hr.bank-files.download')->middleware('permission.role:bank_files.download');
                    });
                    Route::prefix('ancestor')->middleware(['plan:'.HrPlanFeatures::PAYROLL, 'module:salaries'])->group(function () {
                        // جلب خزينة الموظف عند اختياره (استخدام AJAX) - يبقى للاستخدام الداخلي
                        Route::get('/employee-treasury', [EmployeeTreasuryApiController::class, 'getEmployeeTreasury'])->name('ancestor.employeeTreasury')->middleware('permission.role:loans.create|loans.disburse');

                        // ⚠️ بعد دمج النظامين (2026-05-11): /hr/ancestor/index و /create يُعاد توجيههما
                        // إلى /hr/advance-approvals/* — الواجهة الموحَّدة للمدير.
                        // البقية تبقى للقراءة + دفع الأقساط من السلف القديمة.
                        Route::get('/index', function () {
                            return redirect()->route('hr.advance-approvals.index');
                        })->name('ancestor.index');

                        Route::get('/create', function () {
                            return redirect()->route('hr.advance-approvals.create');
                        })->name('ancestor.create');

                        Route::post('/store', [AncestorController::class, 'store'])->name('ancestor.store')->middleware('permission.role:loans.create');
                        Route::get('/show/{id}', [AncestorController::class, 'show'])->name('ancestor.show')->middleware('permission.role:loans.show');
                        Route::get('/edit/{id}', [AncestorController::class, 'edit'])->name('ancestor.edit')->middleware('permission.role:loans.edit');
                        Route::get('/pay/{id}', [AncestorController::class, 'pay'])->name('ancestor.pay')->middleware('permission.role:loans.settle');
                        Route::get('/salary-advance/{id}/pay', [AncestorController::class, 'pay'])->name('salary-advance.pay')->middleware('permission.role:loans.settle');
                        Route::post('/salary-advance/{id}/pay', [AncestorController::class, 'storePayments'])->name('salary-advance.store-payments')->middleware('permission.role:loans.settle');
                        Route::put('/update/{id}', [AncestorController::class, 'update'])->name('ancestor.update')->middleware('permission.role:loans.edit');
                        Route::delete('/destroy/{id}', [AncestorController::class, 'destroy'])->name('ancestor.destroy')->middleware('permission.role:loans.destroy');
                        // الإلغاءُ بقيدٍ عكسيّ — بدلُ الحذف الذي كان يُبقي قيدَ الصرف
                        Route::post('/cancel/{id}', [AncestorController::class, 'cancel'])->name('ancestor.cancel')->middleware('permission.role:loans.cancel');
                    });

                    // سندُ السلفة (قوالب المستندات) وتصديرُ «إدارة السلف» — `hr.advances.*`.
                    // الطباعةُ لطلبات `salary_advance_requests` وحدها، ولمن يرى شاشةَ
                    // الإدارة أو شاشةَ طلباته؛ والنطاقُ في المتحكّم.
                    Route::prefix('advances')->middleware(['module:salaries', 'plan:'.HrPlanFeatures::ADVANCES])->group(function () {
                        Route::get('/export', [AdvanceApprovalController::class, 'export'])->name('hr.advances.export')->middleware('permission.role:loans.index');
                        Route::get('/{id}/print', [HrVoucherPrintController::class, 'advance'])->name('hr.advances.print')->middleware('permission.role:loans.show');
                    });

                    // نظام طلبات السلف للموظفين
                    Route::prefix('advance-requests')->middleware(['permission.role:loans.create|loans.show', 'module:salaries', 'plan:'.HrPlanFeatures::ADVANCES])->group(function () {
                        Route::get('/', [AdvanceRequestController::class, 'index'])->name('hr.advance-requests.index')->middleware('permission.role:loans.create');
                        Route::get('/create', [AdvanceRequestController::class, 'create'])->name('hr.advance-requests.create')->middleware(['permission.role:loans.create', 'plan.limit:'.HrPlanFeatures::LIMIT_ADVANCE_REQUESTS]);
                        Route::post('/', [AdvanceRequestController::class, 'store'])->name('hr.advance-requests.store')->middleware(['permission.role:loans.create', 'plan.limit:'.HrPlanFeatures::LIMIT_ADVANCE_REQUESTS]);
                        Route::get('/{id}', [AdvanceRequestController::class, 'show'])->name('hr.advance-requests.show')->middleware('permission.role:loans.show');
                        Route::put('/{id}/cancel', [AdvanceRequestController::class, 'cancel'])->name('hr.advance-requests.cancel')->middleware('permission.role:loans.create');
                    });

                    // نظام الموافقة على السلف للمشرفين
                    Route::prefix('advance-approvals')->middleware(['permission.role:loans.index|loans.show|loans.approve|loans.disburse|loans.settle|loans.cancel', 'module:salaries', 'plan:'.HrPlanFeatures::ADVANCES])->group(function () {
                        Route::get('/', [AdvanceApprovalController::class, 'index'])->name('hr.advance-approvals.index')->middleware('permission.role:loans.index');
                        Route::get('/create', [AdvanceApprovalController::class, 'create'])->name('hr.advance-approvals.create')->middleware(['permission.role:loans.create', 'permission.role:loans.approve', 'permission.role:loans.disburse']);
                        Route::post('/store', [AdvanceApprovalController::class, 'store'])->name('hr.advance-approvals.store')->middleware(['permission.role:loans.create', 'permission.role:loans.approve', 'permission.role:loans.disburse']);
                        Route::get('/{id}', [AdvanceApprovalController::class, 'show'])->name('hr.advance-approvals.show')->middleware('permission.role:loans.show');
                        /*
                            خياراتُ الصرف لبوب الإشعار (خزائنُ المعتمِد وأرصدتُها)
                            — يسألها البوبُ قبل أن يعرض «اعتماد وصرف»، فلا يصرف
                            من خزينةٍ مفترَضة.
                        */
                        Route::get('/{id}/disbursement-options', [AdvanceApprovalController::class, 'disbursementOptions'])->name('hr.advance-approvals.disbursement_options')->middleware('permission.role:loans.disburse');
                        Route::post('/{id}/approve', [AdvanceApprovalController::class, 'approve'])->name('hr.advance-approvals.approve')->middleware('permission.role:loans.approve');
                        Route::post('/{id}/reject', [AdvanceApprovalController::class, 'reject'])->name('hr.advance-approvals.reject')->middleware('permission.role:loans.approve');
                        Route::post('/{id}/settle', [AdvanceApprovalController::class, 'settle'])->name('hr.advance-approvals.settle')->middleware('permission.role:loans.settle');
                        // الصرفُ والإلغاءُ عمليتان مستقلّتان عن الاعتماد والتسوية
                        Route::post('/{id}/disburse', [AdvanceApprovalController::class, 'disburse'])->name('hr.advance-approvals.disburse')->middleware('permission.role:loans.disburse');
                        Route::post('/{id}/cancel', [AdvanceApprovalController::class, 'cancel'])->name('hr.advance-approvals.cancel')->middleware('permission.role:loans.cancel');
                    });

                    // ============== نظام الخصميات (الحسومات/الجزاءات) ==============
                    Route::prefix('deductions')->middleware(['plan:'.HrPlanFeatures::DEDUCTIONS, 'module:salaries'])->group(function () {
                        // عرض القائمة + التفاصيل + السجل: متاحة للجميع داخل HR (الموظف يرى خصمياته فقط)
                        Route::get('/', [DeductionController::class, 'index'])->name('hr.deductions.index')->middleware('permission.role:deductions.index');
                        Route::get('/{id}/history', [DeductionController::class, 'history'])->name('hr.deductions.history')->middleware('permission.role:deductions.show');
                        Route::get('/{id}', [DeductionController::class, 'show'])->name('hr.deductions.show')->middleware('permission.role:deductions.show');

                        // الإنشاء (يحتاج صلاحية)
                        Route::middleware(['permission.role:deductions.create'])->group(function () {
                            Route::get('/create/new', [DeductionController::class, 'create'])->name('hr.deductions.create');
                            Route::post('/', [DeductionController::class, 'store'])->name('hr.deductions.store');
                        });

                        // التعديل
                        Route::middleware(['permission.role:deductions.edit'])->group(function () {
                            Route::get('/{id}/edit', [DeductionController::class, 'edit'])->name('hr.deductions.edit');
                            Route::put('/{id}', [DeductionController::class, 'update'])->name('hr.deductions.update');
                            Route::post('/{id}/cancel', [DeductionController::class, 'cancel'])->name('hr.deductions.cancel');
                        });

                        // الحذف
                        Route::middleware(['permission.role:deductions.destroy'])->group(function () {
                            Route::delete('/{id}', [DeductionController::class, 'destroy'])->name('hr.deductions.destroy');
                        });

                        // الاعتماد / الرفض / التسوية (صلاحيات منفصلة)
                        Route::middleware(['permission.role:deductions.approve'])->post('/{id}/approve', [DeductionController::class, 'approve'])->name('hr.deductions.approve');
                        Route::middleware(['permission.role:deductions.approve'])->post('/{id}/reject', [DeductionController::class, 'reject'])->name('hr.deductions.reject');
                        Route::middleware(['permission.role:deductions.settle'])->post('/{id}/settle', [DeductionController::class, 'settle'])->name('hr.deductions.settle');
                    });

                    // تقرير العمولات المصروفة
                    Route::prefix('commissions-report')->middleware(['plan:'.HrPlanFeatures::COMMISSIONS, 'module:salaries'])->group(function () {
                        Route::get('/', [CommissionsReportController::class, 'index'])->name('hr.commissions-report.index')->middleware('permission.role:commissions.index');
                        Route::get('/export', [CommissionsReportController::class, 'export'])->name('hr.commissions.export')->middleware('permission.role:commissions.export');
                        Route::get('/{id}/print', [HrVoucherPrintController::class, 'commission'])->name('hr.commissions.print')->middleware('permission.role:commissions.print');
                        Route::get('/{id}', [CommissionsReportController::class, 'show'])->name('hr.commissions-report.show')->middleware('permission.role:commissions.show');
                    });

                    Route::prefix('SalaryItems')->middleware(['plan:'.HrPlanFeatures::SALARY_ITEMS, 'module:salaries'])->group(function () {
                        Route::get('/index', [SalaryItemsController::class, 'index'])->name('SalaryItems.index')->middleware('permission.role:salary_items.index');
                        Route::get('/create', [SalaryItemsController::class, 'create'])->name('SalaryItems.create')->middleware('permission.role:salary_items.create');
                        Route::post('/store', [SalaryItemsController::class, 'store'])->name('SalaryItems.store')->middleware('permission.role:salary_items.create');
                        Route::get('/show/{id}', [SalaryItemsController::class, 'show'])->name('SalaryItems.show')->middleware('permission.role:salary_items.show');
                        Route::get('/edit/{id}', [SalaryItemsController::class, 'edit'])->name('SalaryItems.edit')->middleware('permission.role:salary_items.edit');
                        Route::put('/update/{id}', [SalaryItemsController::class, 'update'])->name('SalaryItems.update')->middleware('permission.role:salary_items.edit');
                        Route::delete('/destroy/{id}', [SalaryItemsController::class, 'destroy'])->name('SalaryItems.destroy')->middleware('permission.role:salary_items.destroy');
                        Route::put('SalaryItems/{id}/toggle-status', [SalaryItemsController::class, 'toggleStatus'])->name('SalaryItems.toggleStatus')->middleware('permission.role:salary_items.edit');
                    });
                    /*
                        «كتالوج البدلات والاستقطاعات» تقاعد (2026-09-24): خصائصُه
                        (الخضوع للتأمينات وأجر نهاية الخدمة) صارت على «بنود الرواتب».
                        الأسماءُ تبقى كي لا ينكسر رابطٌ محفوظ أو مرجعٌ قديم، وكلُّها
                        تُحوِّل إلى بنود الرواتب. الجدولُ وبياناتُه باقيان.
                    */
                    Route::prefix('SalaryComponentTypes')->middleware(['plan:'.HrPlanFeatures::SALARY_ITEMS, 'module:salaries'])->group(function () {
                        Route::get('/index', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.index')->middleware('permission.role:salary_items.index');
                        Route::get('/create', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.create')->middleware('permission.role:salary_items.index');
                        Route::post('/store', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.store')->middleware('permission.role:salary_items.index');
                        Route::get('/edit/{id}', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.edit')->middleware('permission.role:salary_items.index');
                        Route::put('/update/{id}', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.update')->middleware('permission.role:salary_items.index');
                        Route::delete('/destroy/{id}', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.destroy')->middleware('permission.role:salary_items.index');
                        Route::put('/toggle-status/{id}', [SalaryComponentTypeController::class, 'retired'])->name('SalaryComponentTypes.toggleStatus')->middleware('permission.role:salary_items.index');
                    });
                    /*
                        «قوالب الرواتب» أُلغيت (2026-09-24): بنودُ الراتب تُضاف مباشرةً
                        في العقد. الأسماءُ تبقى كي لا ينكسر رابطٌ محفوظ، وكلُّها تُحوِّل
                        إلى بنود الرواتب. الجدولُ وبياناتُه باقيان.
                    */
                    Route::prefix('SalaryTemplates')->middleware(['plan:'.HrPlanFeatures::SALARY_ITEMS, 'module:salaries'])->group(function () {
                        Route::get('/index', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.index');
                        Route::get('/create', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.create');
                        Route::post('/store', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.store');
                        Route::get('/show/{id}', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.show');
                        Route::get('/edit/{id}', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.edit');
                        Route::put('/update/{id}', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.update');
                        Route::delete('/destroy/{id}', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.destroy');
                        Route::put('toggle-status/{id}', [SalaryTemplatesController::class, 'retired'])->name('SalaryTemplates.toggleStatus');
                    });

                    Route::prefix('SalarySittings')->middleware(['plan:'.HrPlanFeatures::SALARY_ITEMS, 'module:salaries'])->group(function () {
                        Route::get('/index', [SalarySittingController::class, 'index'])->name('SalarySittings.index')->middleware('permission.role:payroll_settings.manage|contracts.notifications|salary_items.index|overtime.settings|insurance.settings|insurance.index|bank_files.index|eos.settings');
                        // General payroll settings
                        Route::get('/general', [SalarySittingController::class, 'general'])->name('SalarySittings.general')->middleware('permission.role:payroll_settings.manage');
                        Route::post('/general', [SalarySittingController::class, 'updateGeneral'])->name('SalarySittings.general.update')->middleware('permission.role:payroll_settings.manage');
                        // Contract notifications settings
                        Route::get('/notifications', [SalarySittingController::class, 'notifications'])->name('SalarySittings.notifications')->middleware('permission.role:contracts.notifications|payroll_settings.manage');
                        Route::post('/notifications', [SalarySittingController::class, 'updateNotifications'])->name('SalarySittings.notifications.update')->middleware('permission.role:contracts.notifications|payroll_settings.manage');
                        // Commission settings
                        Route::get('/commissions', [SalarySittingController::class, 'commissions'])->name('SalarySittings.commissions')->middleware('permission.role:payroll_settings.manage');
                        Route::post('/commissions', [SalarySittingController::class, 'updateCommissions'])->name('SalarySittings.commissions.update')->middleware('permission.role:payroll_settings.manage');
                        // إعدادات السلف: أقسامُ «الموظف المعتمد»
                        Route::get('/advances', [AdvanceSettingsController::class, 'edit'])->name('SalarySittings.advances');
                        Route::post('/advances', [AdvanceSettingsController::class, 'update'])->name('SalarySittings.advances.update');
                        Route::get('/overtime', [OvertimeSettingsController::class, 'edit'])->name('SalarySittings.overtime')->middleware('permission.role:overtime.settings');
                        Route::post('/overtime', [OvertimeSettingsController::class, 'update'])->name('SalarySittings.overtime.update')->middleware('permission.role:overtime.settings');

                        /*
                            الاعتمادُ المسبق للعمل الإضافي: الطلبُ يفترق عن
                            البتّ فيه بصلاحيتين مستقلّتين، فلا يعتمد أحدٌ
                            طلبَ نفسه.
                        */
                        Route::prefix('overtime-requests')->group(function () {
                            Route::get('/', [OvertimeRequestController::class, 'index'])->name('hr.overtime-requests.index')->middleware('permission.role:overtime.index');
                            Route::get('/create', [OvertimeRequestController::class, 'create'])->name('hr.overtime-requests.create')->middleware('permission.role:overtime.create');
                            Route::post('/', [OvertimeRequestController::class, 'store'])->name('hr.overtime-requests.store')->middleware('permission.role:overtime.create');
                            Route::get('/{id}', [OvertimeRequestController::class, 'show'])->name('hr.overtime-requests.show')->middleware('permission.role:overtime.show');
                            Route::put('/{id}/cancel', [OvertimeRequestController::class, 'cancel'])->name('hr.overtime-requests.cancel')->middleware('permission.role:overtime.create');
                        });

                        Route::prefix('overtime-approvals')->group(function () {
                            Route::get('/', [OvertimeApprovalController::class, 'index'])->name('hr.overtime-approvals.index')->middleware('permission.role:overtime.approve');
                            Route::get('/{id}', [OvertimeApprovalController::class, 'show'])->name('hr.overtime-approvals.show')->middleware('permission.role:overtime.approve');
                            Route::post('/{id}/approve', [OvertimeApprovalController::class, 'approve'])->name('hr.overtime-approvals.approve')->middleware('permission.role:overtime.approve');
                            Route::post('/{id}/reject', [OvertimeApprovalController::class, 'reject'])->name('hr.overtime-approvals.reject')->middleware('permission.role:overtime.approve');
                        });
                        Route::get('/accounts', [PayrollAccountsController::class, 'edit'])->name('SalarySittings.accounts')->middleware('permission.role:payroll_settings.manage');
                        Route::post('/accounts', [PayrollAccountsController::class, 'update'])->name('SalarySittings.accounts.update')->middleware('permission.role:payroll_settings.manage');
                    });
                    Route::prefix('RelatedModels')->middleware(['plan:'.HrPlanFeatures::SALARY_ITEMS, 'module:salaries'])->group(function () {
                        Route::get('/index', [RelatedModelsController::class, 'index'])->name('RelatedModels.index')->middleware('permission.role:salary_items.index');
                        Route::get('/create', [RelatedModelsController::class, 'create'])->name('RelatedModels.create')->middleware('permission.role:salary_items.create');
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */
                        /*
                            حُذف مسارٌ كان مربوطاً بدالّةٍ غير موجودة في المتحكّم،
                            فكل طلبٍ عليه ينتهي بـ«Method … does not exist» أي 500.
                            ولا قالبَ في النظام يستدعيه.
                        */
                    });
                    Route::prefix('attendance-rules')
                        ->middleware(['plan:'.HrPlanFeatures::ATTENDANCE_RULES, 'module:employee_attendance'])
                        ->name('attendance-rules.')
                        ->group(function () {
                            Route::get('/', [AttendanceRuleController::class, 'index'])->name('index');
                            Route::get('/search', [AttendanceRuleController::class, 'search'])->name('search');
                            Route::get('/create', [AttendanceRuleController::class, 'create'])->name('create');
                            Route::post('/', [AttendanceRuleController::class, 'store'])->name('store');
                            Route::get('/{attendanceRule}', [AttendanceRuleController::class, 'show'])->name('show');
                            Route::get('/{attendanceRule}/edit', [AttendanceRuleController::class, 'edit'])->name('edit');
                            Route::put('/{attendanceRule}', [AttendanceRuleController::class, 'update'])->name('update');
                            Route::delete('/{attendanceRule}', [AttendanceRuleController::class, 'destroy'])->name('destroy');
                            Route::patch('/{attendanceRule}/toggle-status', [AttendanceRuleController::class, 'toggleStatus'])->name('toggle-status');
                        });
                });
        },
    );
});

Route::get('/dashboardhr', [EmployeeController::class, 'dashboardhr'])->name('dashboardhr');
