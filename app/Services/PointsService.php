<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\ExpiryType;
use App\Enums\TransactionStatus;
use App\Enums\UpgradeType;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsDailySummary;
use App\Models\Ecosystem\PointsExpirySetting;
use App\Models\Ecosystem\PointsGeneralSetting;
use App\Models\Ecosystem\PointsSetting;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PointsService — خدمة مركزية لجميع عمليات النقاط
 * ========================================================
 * 
 * هذا السرفيز يُغلِّف كل العمليات المطلوبة لمنح أو خصم النقاط في عملية واحدة:
 * 1. حساب النقاط تلقائياً من الإعدادات (PointsSetting)
 * 2. تطبيق مضاعف المستوى (points_multiplier من PointsTier)
 * 3. التحقق من الإعدادات العامة (PointsGeneralSetting)
 * 4. إنشاء المعاملة (PointsTransaction) مع metadata
 * 5. إنشاء دفعة الصلاحية (PointsBatch)
 * 6. فحص وتسجيل ترقية المستوى (TierUpgradeLog)
 * 7. فحص ومنح الإنجازات (AchievementService)
 * 8. تحديث تقدم التحديات (ChallengeService)
 * 9. إرسال الإشعارات (PointsNotificationService)
 * 10. تحديث الملخص اليومي (PointsDailySummary)
 * 
 * الاستخدام:
 *   $result = app(PointsService::class)->awardEmployee([...]);
 *   $result = app(PointsService::class)->awardClient([...]);
 *   $result = app(PointsService::class)->award([...]);
 *   $result = app(PointsService::class)->deduct([...]);
 *   $result = app(PointsService::class)->reverse($transaction, 'سبب العكس');
 */
class PointsService
{
    // =====================================================
    // المتغيرات والاعتمادات
    // =====================================================

    protected AchievementService $achievementService;
    protected ChallengeService $challengeService;
    protected PointsNotificationService $notificationService;

    public function __construct(
        AchievementService $achievementService,
        ChallengeService $challengeService,
        PointsNotificationService $notificationService
    ) {
        $this->achievementService = $achievementService;
        $this->challengeService = $challengeService;
        $this->notificationService = $notificationService;
    }

    // =====================================================
    // الطرق العامة — الموظفين (Employee API)
    // =====================================================

    /**
     * منح نقاط لموظف
     * 
     * @param int    $employeeId معرف الموظف
     * @param string $actionType نوع العملية (invoice_created, visit_completed, ...)
     * @param array  $options خيارات إضافية:
     *   - amount        (float|null): قيمة المعاملة المالية
     *   - points        (int|null): تحديد النقاط يدوياً (يتجاوز الحساب التلقائي)
     *   - description   (string|null): وصف المعاملة
     *   - branch_id     (int|null): معرف الفرع
     *   - reference_type (string|null): نوع المرجع
     *   - reference_id  (int|null): معرف المرجع
     *   - metadata      (array|null): بيانات إضافية (JSON)
     *   - created_by    (int|null): المُنشئ
     * 
     * @return PointsResult
     */
    public function awardEmployee(int $employeeId, string $actionType, array $options = []): PointsResult
    {
        return $this->award(array_merge($options, [
            'user_type'   => 'employee',
            'employee_id' => $employeeId,
            'action_type' => $actionType,
        ]));
    }

    /**
     * خصم نقاط من موظف
     */
    public function deductEmployee(int $employeeId, string $actionType, array $options = []): PointsResult
    {
        return $this->deduct(array_merge($options, [
            'user_type'   => 'employee',
            'employee_id' => $employeeId,
            'action_type' => $actionType,
        ]));
    }

    /**
     * معاينة نقاط الموظف قبل المنح
     */
    public function canAwardEmployee(int $employeeId, string $actionType, array $options = []): PointsResult
    {
        return $this->canAward(array_merge($options, [
            'user_type'   => 'employee',
            'employee_id' => $employeeId,
            'action_type' => $actionType,
        ]));
    }

    // =====================================================
    // الطرق العامة — العملاء (Client API)
    // =====================================================

    /**
     * منح نقاط لعميل
     * 
     * @param int    $clientId  معرف العميل
     * @param string $actionType نوع العملية (invoice_paid, referral, order_completed, ...)
     * @param array  $options خيارات إضافية (نفس awardEmployee)
     * 
     * @return PointsResult
     */
    public function awardClient(int $clientId, string $actionType, array $options = []): PointsResult
    {
        return $this->award(array_merge($options, [
            'user_type'  => 'client',
            'client_id'  => $clientId,
            'action_type' => $actionType,
        ]));
    }

    /**
     * خصم نقاط من عميل
     */
    public function deductClient(int $clientId, string $actionType, array $options = []): PointsResult
    {
        return $this->deduct(array_merge($options, [
            'user_type'  => 'client',
            'client_id'  => $clientId,
            'action_type' => $actionType,
        ]));
    }

    /**
     * معاينة نقاط العميل قبل المنح
     */
    public function canAwardClient(int $clientId, string $actionType, array $options = []): PointsResult
    {
        return $this->canAward(array_merge($options, [
            'user_type'  => 'client',
            'client_id'  => $clientId,
            'action_type' => $actionType,
        ]));
    }

    // =====================================================
    // الطرق العامة (General API)
    // =====================================================

    /**
     * منح نقاط لمستخدم (عام — يحدد user_type تلقائياً)
     * 
     * @param array $params المعاملات المطلوبة:
     *   - user_type     (string|UserType) required: 'employee' أو 'client'
     *   - employee_id   (int|null): مطلوب إذا user_type = employee
     *   - client_id     (int|null): مطلوب إذا user_type = client
     *   - action_type   (string) required: نوع العملية
     *   - amount        (float|null): قيمة المعاملة المالية
     *   - points        (int|null): تحديد النقاط يدوياً
     *   - description   (string|null): وصف المعاملة
     *   - branch_id     (int|null): معرف الفرع
     *   - reference_type (string|null): نوع المرجع
     *   - reference_id  (int|null): معرف المرجع
     *   - metadata      (array|null): بيانات إضافية
     *   - created_by    (int|null): المُنشئ
     * 
     * @return PointsResult
     */
    public function award(array $params): PointsResult
    {
        return $this->processTransaction($params, 'award');
    }

    /**
     * خصم نقاط من مستخدم (عام)
     * 
     * @param array $params نفس معاملات award()
     * @return PointsResult
     */
    public function deduct(array $params): PointsResult
    {
        return $this->processTransaction($params, 'deduct');
    }

    /**
     * فحص إمكانية منح النقاط بدون تنفيذ
     * مفيد لعرض معاينة قبل التأكيد
     * 
     * @param array $params نفس معاملات award()
     * @return PointsResult success مع النقاط المحسوبة، أو fail مع سبب الرفض
     */
    public function canAward(array $params): PointsResult
    {
        try {
            $normalized = $this->normalizeParams($params);
        } catch (\InvalidArgumentException $e) {
            return PointsResult::fail($e->getMessage());
        }

        // حساب النقاط
        $resolved = $this->resolvePoints($normalized);
        $points = $resolved['points'];
        if ($points === 0) {
            return PointsResult::fail($resolved['detail']);
        }

        // التحقق من الإعدادات العامة
        $validation = $this->validateGeneralSettings($normalized, abs($points));
        if ($validation !== null) {
            return $validation;
        }

        // إنشاء نتيجة وهمية للمعاينة
        $fakeTransaction = new PointsTransaction();
        $fakeTransaction->points = $points;
        $fakeTransaction->action_type = $normalized['action_type'];

        return PointsResult::success(
            "يمكن منح {$points} نقطة لهذه العملية.",
            $fakeTransaction,
            $points
        );
    }

    /**
     * عكس معاملة نقاط سابقة (إنشاء معاملة عكسية)
     * 
     * @param PointsTransaction $transaction المعاملة الأصلية
     * @param string|null $reason سبب العكس
     * @return PointsResult
     */
    public function reverse(PointsTransaction $transaction, ?string $reason = null): PointsResult
    {
        if ($transaction->status !== TransactionStatus::COMPLETED) {
            return PointsResult::fail('يمكن عكس المعاملات المكتملة فقط.');
        }

        $userTypeValue = $transaction->user_type instanceof UserType
            ? $transaction->user_type->value
            : $transaction->user_type;

        $params = [
            'user_type' => $userTypeValue,
            'employee_id' => $transaction->employee_id,
            'client_id' => $transaction->client_id,
            'action_type' => $transaction->action_type . '_reversed',
            'points' => -$transaction->points,
            'description' => $reason ?? 'عكس المعاملة #' . $transaction->id,
            'branch_id' => $transaction->branch_id,
            'reference_type' => get_class($transaction),
            'reference_id' => $transaction->id,
        ];

        $result = $this->processTransaction($params, 'reverse');

        // عكس تقدم التحديات
        if ($result->success) {
            try {
                $this->challengeService->reverseProgressForTransaction($transaction);
            } catch (\Throwable $e) {
                $result->addWarning('فشل عكس تقدم التحديات: ' . $e->getMessage());
                Log::warning('PointsService: فشل عكس تقدم التحديات', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * تقييم اكتمال خط السير اليومي للموظف
     * - إذا اكتملت جميع الزيارات → منح نقاط (itinerary_completed)
     * - إذا لم تكتمل جميع الزيارات → خصم نقاط (itinerary_incomplete)
     * 
     * @param int    $employeeId
     * @param string $date (Y-m-d)
     * @return PointsResult|null
     */
    public function evaluateItineraryCompletion(int $employeeId, string $date): ?PointsResult
    {
        try {
            // جلب جميع زيارات الموظف في هذا التاريخ
            $visits = \App\Models\EmployeeClientVisit::where('employee_id', $employeeId)
                ->whereDate('visit_date', $date)
                ->get();

            if ($visits->isEmpty()) {
                return null; // لا يوجد خط سير لهذا اليوم
            }

            $completedCount = $visits->where('status', 'active')->count();
            $totalCount = $visits->count();
            $allCompleted = $completedCount === $totalCount;

            if ($allCompleted) {
                // ──────────────────────────────────────────────
                // ✅ جميع الزيارات مكتملة → منح نقاط
                // ──────────────────────────────────────────────
                $alreadyAwarded = PointsTransaction::where('employee_id', $employeeId)
                    ->where('action_type', 'itinerary_completed')
                    ->whereDate('created_at', $date)
                    ->exists();

                if ($alreadyAwarded) {
                    return null; // تم المنح مسبقاً
                }

                // إذا كان قد تم خصم نقاط سابقاً لهذا اليوم، نعكسها أولاً
                $previousDeduction = PointsTransaction::where('employee_id', $employeeId)
                    ->where('action_type', 'itinerary_incomplete')
                    ->whereDate('created_at', $date)
                    ->where('status', \App\Enums\TransactionStatus::COMPLETED)
                    ->first();

                if ($previousDeduction) {
                    $this->reverse($previousDeduction, "عكس خصم عدم اكتمال خط السير — تم اكتمال جميع الزيارات ليوم {$date}");
                }

                return $this->awardEmployee($employeeId, 'itinerary_completed', [
                    'description'    => "اكتمال خط السير ليوم {$date} ({$totalCount} زيارة)",
                    'metadata'       => [
                        'date'            => $date,
                        'visit_count'     => $totalCount,
                        'completed_count' => $completedCount,
                    ],
                    'reference_type' => 'itinerary_daily_completion',
                    'reference_id'   => Carbon::parse($date)->timestamp,
                ]);
            } else {
                // ──────────────────────────────────────────────
                // ❌ الزيارات غير مكتملة → خصم نقاط
                // ──────────────────────────────────────────────
                $alreadyDeducted = PointsTransaction::where('employee_id', $employeeId)
                    ->where('action_type', 'itinerary_incomplete')
                    ->whereDate('created_at', $date)
                    ->exists();

                if ($alreadyDeducted) {
                    return null; // تم الخصم مسبقاً
                }

                // لا نخصم إلا إذا انتهى اليوم (أو إذا كان تاريخ الزيارة قبل اليوم)
                $isToday = Carbon::parse($date)->isToday();
                if ($isToday) {
                    return null; // لا نخصم في نفس اليوم — قد يكمل الموظف زياراته لاحقاً
                }

                $incompleteCount = $totalCount - $completedCount;

                return $this->processAction('itinerary_incomplete', [
                    'employee_id'    => $employeeId,
                    'description'    => "عدم اكتمال خط السير ليوم {$date} ({$completedCount}/{$totalCount} مكتملة)",
                    'metadata'       => [
                        'date'             => $date,
                        'visit_count'      => $totalCount,
                        'completed_count'  => $completedCount,
                        'incomplete_count' => $incompleteCount,
                    ],
                    'reference_type' => 'itinerary_daily_incomplete',
                    'reference_id'   => Carbon::parse($date)->timestamp,
                ])['employee'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('PointsService: فشل تقييم اكتمال خط السير: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * عكس جميع معاملات النقاط المرتبطة بمرجع معين (فاتورة، سند قبض، سند صرف، إلخ)
     * 
     * بدلاً من تكرار كود البحث والعكس في كل كونترولر، استخدم هذه الدالة مباشرة.
     * 
     * @param string $referenceType نوع المرجع (Expense::class, Receipt::class, Invoice::class, ...)
     * @param int    $referenceId   معرف المرجع
     * @param string|null $reason   سبب العكس
     * @return array{reversed: int, failed: int, results: array<PointsResult>}
     * 
     * مثال:
     *   $result = app(PointsService::class)->reverseByReference(Expense::class, $expense->id, 'إلغاء سند صرف');
     *   // $result['reversed'] = 2  (عدد المعاملات المعكوسة بنجاح)
     *   // $result['failed'] = 0
     */
    public function reverseByReference(string $referenceType, int $referenceId, ?string $reason = null): array
    {
        $transactions = PointsTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', TransactionStatus::COMPLETED)
            ->get();

        $results = [];
        $reversed = 0;
        $failed = 0;

        foreach ($transactions as $transaction) {
            $result = $this->reverse($transaction, $reason);
            $results[] = $result;

            if ($result->success) {
                $reversed++;
            } else {
                $failed++;
            }
        }

        return [
            'reversed' => $reversed,
            'failed'   => $failed,
            'total'    => $transactions->count(),
            'results'  => $results,
        ];
    }

    /**
     * منح نقاط لعدة موظفين دفعة واحدة
     * 
     * @param array $employeeIds مصفوفة معرّفات الموظفين
     * @param string $actionType نوع العملية
     * @param array $options خيارات مشتركة
     * @return array<PointsResult>
     */
    public function awardEmployeesBulk(array $employeeIds, string $actionType, array $options = []): array
    {
        $results = [];
        foreach ($employeeIds as $employeeId) {
            $results[] = $this->awardEmployee($employeeId, $actionType, $options);
        }
        return $results;
    }

    /**
     * منح نقاط لعدة عملاء دفعة واحدة
     * 
     * @param array $clientIds مصفوفة معرّفات العملاء
     * @param string $actionType نوع العملية
     * @param array $options خيارات مشتركة
     * @return array<PointsResult>
     */
    public function awardClientsBulk(array $clientIds, string $actionType, array $options = []): array
    {
        $results = [];
        foreach ($clientIds as $clientId) {
            $results[] = $this->awardClient($clientId, $actionType, $options);
        }
        return $results;
    }

    /**
     * منح نقاط لعدة مستخدمين دفعة واحدة (عام)
     * 
     * @param array $paramsList مصفوفة من مصفوفات المعاملات (كل واحدة مثل award)
     * @return array<PointsResult>
     */
    public function awardBulk(array $paramsList): array
    {
        $results = [];
        foreach ($paramsList as $params) {
            $results[] = $this->award($params);
        }
        return $results;
    }

    // =====================================================
    // معالجة الإجراءات التلقائية (Auto Action Processing)
    // =====================================================

    /**
     * معالجة إجراء لجميع الإعدادات المطابقة (موظف + عميل)
     * 
     * عند إنشاء سند قبض مثلاً، يبحث عن جميع إعدادات النقاط المطابقة لـ receipt_created
     * ويُنشئ معاملات لكل إعداد نشط (إعداد الموظف + إعداد العميل).
     * 
     * @param string $actionType نوع العملية (receipt_created, invoice_created, ...)
     * @param array  $context السياق:
     *   - employee_id   (int|null): معرف الموظف
     *   - client_id     (int|null): معرف العميل
     *   - amount        (float|null): قيمة المعاملة
     *   - branch_id     (int|null): معرف الفرع
     *   - description   (string|null): وصف
     *   - reference_type (string|null): نوع المرجع (Invoice, Receipt, ...)
     *   - reference_id  (int|null): معرف المرجع
     *   - metadata      (array|null): بيانات إضافية
     * 
     * @return array ['employee' => PointsResult|null, 'client' => PointsResult|null, 'settings_found' => int]
     * 
     * مثال:
     *   $results = app(PointsService::class)->processAction('receipt_created', [
     *       'employee_id' => $receipt->employee_id,
     *       'client_id'   => $receipt->client_id,
     *       'amount'      => $receipt->amount,
     *       'branch_id'   => $receipt->branch_id,
     *       'reference_type' => get_class($receipt),
     *       'reference_id'   => $receipt->id,
     *   ]);
     *   
     *   if ($results['employee']?->success) { ... }
     *   if ($results['client']?->success) { ... }
     */
    public function processAction(string $actionType, array $context = []): array
    {
        $branchId = $context['branch_id'] ?? null;
        $amount = $context['amount'] ?? null;

        // البحث عن جميع الإعدادات المطابقة لهذا الإجراء
        $allSettings = PointsSetting::active()
            ->forAction($actionType)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->get();

        // تجميع حسب نوع المستخدم مع أولوية الفرع
        $settingsByUserType = [];
        foreach ($allSettings as $setting) {
            $userType = $setting->user_type instanceof UserType
                ? $setting->user_type->value
                : (string) $setting->user_type;

            // الإعداد الخاص بالفرع يأخذ الأولوية
            if (!isset($settingsByUserType[$userType]) || $setting->branch_id !== null) {
                $settingsByUserType[$userType] = $setting;
            }
        }

        $results = [
            'employee' => null,
            'client'   => null,
            'settings_found' => count($settingsByUserType),
        ];

        // معالجة إعداد الموظف
        if (isset($settingsByUserType['employee'])) {
            if (empty($context['employee_id'])) {
                // لا يوجد employee_id — تخطي
            } elseif (!Employee::where('id', $context['employee_id'])->exists()) {
                $results['employee'] = PointsResult::fail("الموظف غير موجود (ID: {$context['employee_id']})");
            } else {
                $setting = $settingsByUserType['employee'];
                if ($setting->points < 0) {
                    $results['employee'] = $this->deductEmployee(
                        (int) $context['employee_id'],
                        $actionType,
                        $context
                    );
                } else {
                    $results['employee'] = $this->awardEmployee(
                        (int) $context['employee_id'],
                        $actionType,
                        $context
                    );
                }
            }
        }

        // معالجة إعداد العميل
        if (isset($settingsByUserType['client'])) {
            if (empty($context['client_id'])) {
                // لا يوجد client_id — تخطي
            } elseif (!Client::where('id', $context['client_id'])->exists()) {
                $results['client'] = PointsResult::fail("العميل غير موجود (ID: {$context['client_id']})");
            } else {
                $setting = $settingsByUserType['client'];
                if ($setting->points < 0) {
                    $results['client'] = $this->deductClient(
                        (int) $context['client_id'],
                        $actionType,
                        $context
                    );
                } else {
                    $results['client'] = $this->awardClient(
                        (int) $context['client_id'],
                        $actionType,
                        $context
                    );
                }
            }
        }

        return $results;
    }

    /**
     * معالجة عدة إجراءات دفعة واحدة
     * مثال: عند إنشاء فاتورة، يمكن تشغيل invoice_created + payment_received
     * 
     * @param array $actions مصفوفة من ['action_type' => string, 'context' => array]
     * @return array<string, array> مفتاح = action_type، قيمة = نتيجة processAction
     */
    public function processActions(array $actions): array
    {
        $results = [];
        foreach ($actions as $action) {
            $actionType = $action['action_type'] ?? $action[0] ?? null;
            $context = $action['context'] ?? $action[1] ?? [];
            if ($actionType) {
                $results[$actionType] = $this->processAction($actionType, $context);
            }
        }
        return $results;
    }

    /**
     * الحصول على جميع إعدادات النقاط المطابقة لإجراء معين
     * مفيد لمعاينة ما سيحدث قبل التنفيذ
     * 
     * @param string   $actionType نوع العملية
     * @param int|null $branchId   معرف الفرع
     * @return array ['employee' => PointsSetting|null, 'client' => PointsSetting|null]
     */
    public function getSettingsForAction(string $actionType, ?int $branchId = null): array
    {
        $allSettings = PointsSetting::active()
            ->forAction($actionType)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->get();

        $result = ['employee' => null, 'client' => null];

        foreach ($allSettings as $setting) {
            $userType = $setting->user_type instanceof UserType
                ? $setting->user_type->value
                : (string) $setting->user_type;

            // الأول من كل نوع (مع أولوية الفرع بسبب ORDER BY)
            if ($result[$userType] === null) {
                $result[$userType] = $setting;
            }
        }

        return $result;
    }

    // =====================================================
    // المعالجة الأساسية (Core Processing)
    // =====================================================

    /**
     * المعالجة المركزية لجميع أنواع المعاملات
     */
    protected function processTransaction(array $params, string $operation): PointsResult
    {
        // توحيد المعاملات
        try {
            $normalized = $this->normalizeParams($params);
        } catch (\InvalidArgumentException $e) {
            return PointsResult::fail($e->getMessage());
        }

        // حساب النقاط
        $resolved = $this->resolvePoints($normalized, $operation);
        $points = $resolved['points'];

        if ($points === 0) {
            return PointsResult::fail($resolved['detail']);
        }

        // تطبيق مضاعف المستوى (points_multiplier) للنقاط الموجبة فقط
        $multiplierApplied = false;
        $originalPoints = $points;
        if ($points > 0 && $operation === 'award') {
            $multiplierData = $this->applyTierMultiplier($normalized, $points);
            $points = $multiplierData['points'];
            $multiplierApplied = $multiplierData['applied'];
        }

        // التأكد من أن الخصم سالب
        if ($operation === 'deduct' && $points > 0) {
            $points = -$points;
        }

        // التحقق من الإعدادات العامة
        $validation = $this->validateGeneralSettings($normalized, abs($points));
        if ($validation !== null) {
            return $validation;
        }

        // تنفيذ المعاملة داخل Transaction قاعدة البيانات
        try {
            return DB::transaction(function () use ($normalized, $points, $operation, $multiplierApplied, $originalPoints) {
                // 1. حساب الرصيد قبل وبعد
                $balanceBefore = $this->calculateCurrentBalance($normalized);
                $balanceAfter = $balanceBefore + $points;

                // 2. بناء metadata
                $metadata = $normalized['metadata'] ?? [];
                if ($normalized['amount'] !== null) {
                    $metadata['transaction_amount'] = $normalized['amount'];
                }
                if ($multiplierApplied) {
                    $metadata['original_points'] = $originalPoints;
                    $metadata['multiplier_applied'] = true;
                }
                $metadata['operation'] = $operation;

                // 3. إنشاء معاملة النقاط
                $transaction = PointsTransaction::create([
                    'user_type' => $normalized['user_type_value'],
                    'employee_id' => $normalized['employee_id'],
                    'client_id' => $normalized['client_id'],
                    'action_type' => $normalized['action_type'],
                    'points' => $points,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => $normalized['description'],
                    'status' => TransactionStatus::COMPLETED->value,
                    'branch_id' => $normalized['branch_id'],
                    'reference_type' => $normalized['reference_type'],
                    'reference_id' => $normalized['reference_id'],
                    'created_by' => $normalized['created_by'],
                    'points_month' => now()->month,
                    'points_year' => now()->year,
                    'metadata' => !empty($metadata) ? $metadata : null,
                ]);

                // بناء النتيجة
                $result = PointsResult::success(
                    $this->getSuccessMessage($operation, $points),
                    $transaction,
                    $points
                );

                if ($multiplierApplied) {
                    $result->addWarning("تم تطبيق مضاعف المستوى: {$originalPoints} → {$points} نقطة");
                }

                // 4. إنشاء دفعة النقاط (فقط للنقاط الموجبة)
                if ($points > 0) {
                    $batch = $this->createPointsBatch($transaction);
                    $result->withBatch($batch);
                }

                // 5. إرسال إشعار كسب/خصم النقاط
                $this->sendPointsNotification($transaction, $normalized, $result);

                // 6. فحص ترقية المستوى
                $tierChange = $this->checkAndLogTierUpgrade($transaction, $normalized);
                if ($tierChange) {
                    $result->withTierChange($tierChange);
                }

                // 7. فحص ومنح الإنجازات
                $achievements = $this->checkAchievements($transaction, $normalized, $result);
                if (!empty($achievements)) {
                    $result->withAchievements($achievements);
                }

                // 8. تحديث تقدم التحديات (ليس للعمليات العكسية)
                if ($operation !== 'reverse') {
                    $this->updateChallengeProgress($transaction, $normalized, $result);
                }

                // 9. تحديث الملخص اليومي (points_daily_summary)
                $this->updateDailySummary($transaction, $normalized, $result);

                return $result;
            });
        } catch (\Throwable $e) {
            Log::error('PointsService: خطأ في معالجة المعاملة', [
                'operation' => $operation,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return PointsResult::fail('حدث خطأ أثناء معالجة المعاملة: ' . $e->getMessage());
        }
    }

    // =====================================================
    // التوحيد والتحقق (Normalization & Validation)
    // =====================================================

    /**
     * توحيد المعاملات المُمررة وتحويلها لصيغة موحدة
     * 
     * @throws \InvalidArgumentException
     */
    protected function normalizeParams(array $params): array
    {
        // تحويل user_type
        $userType = $params['user_type'] ?? null;
        if ($userType instanceof UserType) {
            $userTypeEnum = $userType;
            $userTypeValue = $userType->value;
        } elseif (is_string($userType)) {
            $userTypeEnum = UserType::tryFrom($userType);
            if (!$userTypeEnum) {
                throw new \InvalidArgumentException("نوع المستخدم غير صالح: {$userType}");
            }
            $userTypeValue = $userType;
        } else {
            throw new \InvalidArgumentException('user_type مطلوب');
        }

        // التحقق من وجود معرف المستخدم حسب النوع فقط
        // كل معاملة تخص نوعاً واحداً فقط: إما موظف أو عميل — لا يُعبَّأ الحقلان معاً
        $employeeId = null;
        $clientId = null;

        if ($userTypeValue === 'employee') {
            $employeeId = $params['employee_id'] ?? null;
            if (!$employeeId) {
                throw new \InvalidArgumentException('employee_id مطلوب عندما يكون user_type = employee');
            }
            if (!Employee::where('id', $employeeId)->exists()) {
                throw new \InvalidArgumentException("الموظف غير موجود (ID: {$employeeId})");
            }
        } else {
            $clientId = $params['client_id'] ?? null;
            if (!$clientId) {
                throw new \InvalidArgumentException('client_id مطلوب عندما يكون user_type = client');
            }
            if (!Client::where('id', $clientId)->exists()) {
                throw new \InvalidArgumentException("العميل غير موجود (ID: {$clientId})");
            }
        }

        // التحقق من action_type
        $actionType = $params['action_type'] ?? null;
        if (empty($actionType)) {
            throw new \InvalidArgumentException('action_type مطلوب');
        }

        return [
            'user_type_enum' => $userTypeEnum,
            'user_type_value' => $userTypeValue,
            'employee_id' => $employeeId,
            'client_id' => $clientId,
            'user_id' => $userTypeValue === 'employee' ? $employeeId : $clientId,
            'id_column' => $userTypeValue === 'employee' ? 'employee_id' : 'client_id',
            'action_type' => $actionType,
            'amount' => $params['amount'] ?? $params['transaction_amount'] ?? null,
            'manual_points' => $params['points'] ?? null,
            'description' => $params['description'] ?? null,
            'branch_id' => $params['branch_id'] ?? null,
            'reference_type' => $params['reference_type'] ?? null,
            'reference_id' => $params['reference_id'] ?? null,
            'metadata' => $params['metadata'] ?? null,
            'created_by' => $params['created_by'] ?? Auth::id(),
        ];
    }

    /**
     * حساب النقاط: يدوي أو تلقائي من الإعدادات
     * 
     * @return array{points: int, reason: string, detail: string}
     *   reason: manual|calculated|no_setting|min_amount_not_met|tier_not_matched|amount_too_low|zero_calculated
     */
    protected function resolvePoints(array $normalized, string $operation = 'award'): array
    {
        $userLabel = $normalized['user_type_value'] === 'employee' ? 'الموظف' : 'العميل';
        $actionType = $normalized['action_type'];

        // إذا تم تحديد النقاط يدوياً — استخدمها مباشرة
        if ($normalized['manual_points'] !== null) {
            $points = (int) $normalized['manual_points'];
            return [
                'points' => $points,
                'reason' => 'manual',
                'detail' => "تم تحديد {$points} نقطة يدوياً",
            ];
        }

        // البحث عن الإعداد المطابق
        $setting = PointsSetting::getSettingForAction(
            $normalized['user_type_value'],
            $normalized['action_type'],
            $normalized['branch_id']
        );

        if (!$setting) {
            // التحقق مما إذا كان الإعداد موجوداً لكنه معطّل
            $disabledSetting = PointsSetting::getSettingForActionIncludingDisabled(
                $normalized['user_type_value'],
                $normalized['action_type'],
                $normalized['branch_id']
            );

            if ($disabledSetting && $disabledSetting->isDisabled()) {
                $disabledDetail = "إعداد النقاط معطّل للإجراء '{$disabledSetting->action_name}'";
                if ($disabledSetting->disabled_reason) {
                    $disabledDetail .= " — السبب: {$disabledSetting->disabled_reason}";
                }
                if ($disabledSetting->disabled_at) {
                    $disabledDetail .= " (معطّل منذ: {$disabledSetting->disabled_at->format('Y-m-d')})";
                }

                return [
                    'points' => 0,
                    'reason' => 'setting_disabled',
                    'detail' => $disabledDetail,
                ];
            }

            return [
                'points' => 0,
                'reason' => 'no_setting',
                'detail' => "لا يوجد إعداد نقاط نشط لنوع '{$userLabel}' وإجراء '{$actionType}'"
                    . ($normalized['branch_id'] ? " (الفرع: {$normalized['branch_id']})" : ''),
            ];
        }

        // فحص الحد الأدنى للمبلغ
        $amount = $normalized['amount'];
        if ($setting->min_amount !== null && $amount !== null && $amount < $setting->min_amount) {
            return [
                'points' => 0,
                'reason' => 'min_amount_not_met',
                'detail' => "المبلغ (" . number_format($amount, 2) . ") أقل من الحد الأدنى المطلوب ("
                    . number_format($setting->min_amount, 2) . ") للإجراء '{$setting->action_name}'",
            ];
        }

        // حساب النقاط من الإعداد
        $calculated = $setting->calculatePoints($amount);

        if ($calculated === 0) {
            // تحديد السبب التفصيلي
            $calcType = $setting->calculation_type instanceof \App\Enums\CalculationType
                ? $setting->calculation_type->value
                : (string) $setting->calculation_type;

            if ($calcType === 'tiered') {
                $reason = 'tier_not_matched';
                $detail = "المبلغ (" . number_format($amount ?? 0, 2) . ") لا يطابق أي مستوى في التدرج للإجراء '{$setting->action_name}'";
            } elseif (in_array($calcType, ['percentage', 'per_amount']) && ($amount === null || $amount <= 0)) {
                $reason = 'amount_too_low';
                $detail = "المبلغ غير محدد أو يساوي صفر — الإجراء '{$setting->action_name}' يحتاج مبلغ المعاملة (نوع: {$calcType})";
            } else {
                $reason = 'zero_calculated';
                $detail = "النقاط المحسوبة = 0 للإجراء '{$setting->action_name}' (نوع الحساب: {$calcType})";
            }

            return ['points' => 0, 'reason' => $reason, 'detail' => $detail];
        }

        return [
            'points' => $calculated,
            'reason' => 'calculated',
            'detail' => "تم حساب {$calculated} نقطة من إعداد '{$setting->action_name}' ({$setting->getCalculationDescription()})",
        ];
    }

    /**
     * التحقق من الإعدادات العامة (PointsGeneralSetting)
     * 
     * @return PointsResult|null null = تم التحقق بنجاح، PointsResult = يوجد مشكلة
     */
    protected function validateGeneralSettings(array $normalized, int $absPoints): ?PointsResult
    {
        try {
            $settings = PointsGeneralSetting::instance();

            // 1. فحص تفعيل النظام بالكامل
            if (!PointsGeneralSetting::isSystemEnabled()) {
                return PointsResult::fail('نظام النقاط معطل حالياً.');
            }

            // 2. فحص تفعيل نقاط الموظفين/العملاء
            if ($normalized['user_type_value'] === 'employee' && !$settings->enable_employee_points) {
                return PointsResult::fail('نقاط الموظفين معطلة حالياً من الإعدادات العامة.');
            }
            if ($normalized['user_type_value'] === 'client' && !$settings->enable_client_points) {
                return PointsResult::fail('نقاط العملاء معطلة حالياً من الإعدادات العامة.');
            }

            // 3. فحص الحد الأقصى للنقاط لكل معاملة
            if ($settings->max_points_per_transaction > 0 && $absPoints > $settings->max_points_per_transaction) {
                return PointsResult::fail(
                    'تجاوز الحد الأقصى للنقاط لكل معاملة. الحد: '
                    . number_format($settings->max_points_per_transaction) . ' نقطة'
                );
            }

            // 4. فحص الحد الأقصى للكسب اليومي (فقط للنقاط الموجبة)
            if ($absPoints > 0 && $settings->max_daily_earn > 0) {
                $todayEarned = PointsTransaction::where('user_type', $normalized['user_type_value'])
                    ->where($normalized['id_column'], $normalized['user_id'])
                    ->where('status', TransactionStatus::COMPLETED)
                    ->where('points', '>', 0)
                    ->whereDate('created_at', today())
                    ->sum('points');

                if (($todayEarned + $absPoints) > $settings->max_daily_earn) {
                    $remaining = max(0, $settings->max_daily_earn - $todayEarned);
                    return PointsResult::fail(
                        'تجاوز الحد الأقصى للكسب اليومي. الحد: '
                        . number_format($settings->max_daily_earn)
                        . ' | المكتسب اليوم: ' . number_format($todayEarned)
                        . ' | المتبقي: ' . number_format($remaining)
                    );
                }
            }

            // 5. كشف النشاط المشبوه
            if ($settings->enable_fraud_detection && $absPoints > 0) {
                $lastHourCount = PointsTransaction::where('user_type', $normalized['user_type_value'])
                    ->where($normalized['id_column'], $normalized['user_id'])
                    ->where('created_at', '>=', now()->subHour())
                    ->count();

                $threshold = $settings->suspicious_threshold ?: 10;

                if ($lastHourCount >= $threshold) {
                    Log::warning("⚠️ PointsService: نشاط مشبوه — المستخدم {$normalized['user_type_value']}#{$normalized['user_id']} أجرى {$lastHourCount} معاملة خلال الساعة الأخيرة");
                    return PointsResult::fail(
                        'تم اكتشاف نشاط مشبوه. عدد المعاملات خلال الساعة الأخيرة: '
                        . $lastHourCount . '. الحد المسموح: ' . $threshold
                    );
                }
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
            Log::warning('PointsService: تعذر قراءة الإعدادات العامة — يتم المتابعة بالقيم الافتراضية', [
                'error' => $e->getMessage(),
            ]);
        }

        return null; // تم التحقق بنجاح
    }

    // =====================================================
    // العمليات الفرعية (Sub-operations)
    // =====================================================

    /**
     * حساب الرصيد الحالي للمستخدم في الشهر الحالي
     */
    protected function calculateCurrentBalance(array $normalized): int
    {
        return (int) PointsTransaction::where('user_type', $normalized['user_type_enum'])
            ->where($normalized['id_column'], $normalized['user_id'])
            ->where('points_month', now()->month)
            ->where('points_year', now()->year)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');
    }

    /**
     * إنشاء دفعة نقاط (Batch) لتتبع انتهاء الصلاحية
     */
    protected function createPointsBatch(PointsTransaction $transaction): ?PointsBatch
    {
        try {
            $userType = $transaction->user_type instanceof UserType
                ? $transaction->user_type
                : UserType::from($transaction->user_type);

            $branchId = $transaction->branch_id;

            // جلب إعدادات الانتهاء المناسبة
            $expirySetting = PointsExpirySetting::getSettingsForUser($userType, $branchId);

            // حساب تاريخ الانتهاء
            $expiresAt = null;

            if ($expirySetting && $expirySetting->isPointsExpiryEnabled()) {
                // التحقق من استثناء المستوى
                $currentTierId = $this->getCurrentTierId($transaction);
                if (!$expirySetting->isTierExcluded($currentTierId)) {
                    $expiresAt = $this->calculateExpiryDate($expirySetting, $transaction);
                }
            }

            return PointsBatch::create([
                'transaction_id' => $transaction->id,
                'user_type' => $userType->value,
                'employee_id' => $transaction->employee_id,
                'client_id' => $transaction->client_id,
                'original_points' => $transaction->points,
                'remaining_points' => $transaction->points,
                'used_points' => 0,
                'expired_points' => 0,
                'earned_at' => now(),
                'expires_at' => $expiresAt,
                'status' => BatchStatus::ACTIVE->value,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PointsService: فشل إنشاء دفعة النقاط', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * إرسال إشعار كسب/خصم النقاط
     */
    protected function sendPointsNotification(
        PointsTransaction $transaction,
        array $normalized,
        PointsResult $result
    ): void {
        try {
            $userName = $this->getUserName($normalized);

            if ($transaction->points > 0) {
                $logs = $this->notificationService->notifyPointsEarned(
                    $normalized['user_type_value'],
                    $normalized['employee_id'],
                    $normalized['client_id'],
                    [
                        'user_name' => $userName,
                        'points' => $transaction->points,
                        'action_type' => $transaction->action_type,
                        'balance_after' => $transaction->balance_after,
                    ]
                );
                if (!empty($logs)) {
                    $result->withNotifications($logs);
                }
            }
        } catch (\Throwable $e) {
            $result->addWarning('فشل إرسال إشعار النقاط: ' . $e->getMessage());
            Log::warning('PointsService: فشل إرسال الإشعار', ['error' => $e->getMessage()]);
        }
    }

    /**
     * فحص وتسجيل ترقية المستوى
     * 
     * @return array|null مصفوفة بيانات الترقية أو null إذا لم يتغير المستوى
     */
    protected function checkAndLogTierUpgrade(
        PointsTransaction $transaction,
        array $normalized
    ): ?array {
        try {
            if (!$normalized['user_id']) {
                return null;
            }

            // فحص تفعيل نظام المستويات
            if (!PointsGeneralSetting::isSubsystemEnabled('tiers')) {
                return null;
            }

            // حساب إجمالي النقاط المكتسبة في الشهر الحالي
            $monthlyPoints = (int) PointsTransaction::where('user_type', $normalized['user_type_enum'])
                ->where($normalized['id_column'], $normalized['user_id'])
                ->where('points_month', $transaction->points_month)
                ->where('points_year', $transaction->points_year)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');

            // الحصول على المستوى المناسب
            $newTier = PointsTier::getTierForPoints($normalized['user_type_value'], $monthlyPoints);
            if (!$newTier) {
                return null;
            }

            // البحث عن آخر ترقية
            $lastUpgrade = TierUpgradeLog::where('user_type', $normalized['user_type_value'])
                ->where($normalized['id_column'], $normalized['user_id'])
                ->orderBy('created_at', 'desc')
                ->first();

            $lastTierId = $lastUpgrade?->new_tier_id;

            // تسجيل فقط إذا تغير المستوى
            if ($newTier->id === $lastTierId) {
                return null;
            }

            $oldTier = $lastTierId ? PointsTier::find($lastTierId) : null;

            TierUpgradeLog::create([
                'user_type' => $normalized['user_type_value'],
                'employee_id' => $normalized['employee_id'],
                'client_id' => $normalized['client_id'],
                'old_tier_id' => $lastTierId,
                'new_tier_id' => $newTier->id,
                'points_at_upgrade' => $monthlyPoints,
                'upgrade_type' => UpgradeType::MANUAL->value,
                'upgraded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            Log::info('PointsService: تم تسجيل ترقية المستوى', [
                'user_type' => $normalized['user_type_value'],
                'user_id' => $normalized['user_id'],
                'old_tier' => $oldTier?->name ?? 'بدون مستوى',
                'new_tier' => $newTier->name,
                'monthly_points' => $monthlyPoints,
            ]);

            $tierChange = [
                'old_tier_id' => $lastTierId,
                'old_tier_name' => $oldTier?->name ?? 'بدون مستوى',
                'new_tier_id' => $newTier->id,
                'new_tier_name' => $newTier->name,
                'monthly_points' => $monthlyPoints,
            ];

            // إرسال إشعار ترقية المستوى
            try {
                $userName = $this->getUserName($normalized);
                $this->notificationService->notifyTierUpgraded(
                    $normalized['user_type_value'],
                    $normalized['employee_id'],
                    $normalized['client_id'],
                    [
                        'user_name' => $userName,
                        'old_tier' => $tierChange['old_tier_name'],
                        'new_tier' => $tierChange['new_tier_name'],
                        'points' => $monthlyPoints,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('PointsService: فشل إرسال إشعار ترقية المستوى', ['error' => $e->getMessage()]);
            }

            return $tierChange;

        } catch (\Throwable $e) {
            Log::error('PointsService: خطأ في فحص ترقية المستوى', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * فحص ومنح الإنجازات
     * 
     * @return array الإنجازات المحققة
     */
    protected function checkAchievements(
        PointsTransaction $transaction,
        array $normalized,
        PointsResult $result
    ): array {
        try {
            // فحص تفعيل نظام الإنجازات
            if (!PointsGeneralSetting::isSubsystemEnabled('achievements')) {
                return [];
            }

            $awarded = $this->achievementService->checkAchievements(
                $normalized['user_id'],
                $normalized['user_type_enum']
            );

            if (empty($awarded)) {
                return [];
            }

            // إرسال إشعارات الإنجازات
            try {
                $userName = $this->getUserName($normalized);
                foreach ($awarded as $awardedItem) {
                    $achievement = $awardedItem['achievement'];
                    $this->notificationService->notifyAchievementUnlocked(
                        $normalized['user_type_value'],
                        $normalized['employee_id'],
                        $normalized['client_id'],
                        [
                            'user_name' => $userName,
                            'achievement_name' => $achievement->name,
                            'bonus_points' => $achievement->bonus_points ?? 0,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $result->addWarning('فشل إرسال إشعارات الإنجازات: ' . $e->getMessage());
                Log::warning('PointsService: فشل إرسال إشعارات الإنجازات', ['error' => $e->getMessage()]);
            }

            return $awarded;

        } catch (\Throwable $e) {
            $result->addWarning('فشل فحص الإنجازات: ' . $e->getMessage());
            Log::warning('PointsService: خطأ في فحص الإنجازات', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * تحديث تقدم التحديات
     */
    protected function updateChallengeProgress(
        PointsTransaction $transaction,
        array $normalized,
        PointsResult $result
    ): void {
        try {
            // فحص تفعيل نظام التحديات
            if (!PointsGeneralSetting::isSubsystemEnabled('challenges')) {
                return;
            }

            $this->challengeService->updateProgressForTransaction(
                $normalized['action_type'],
                $normalized['user_id'],
                $normalized['user_type_enum'],
                $transaction
            );
        } catch (\Throwable $e) {
            $result->addWarning('فشل تحديث تقدم التحديات: ' . $e->getMessage());
            Log::warning('PointsService: فشل تحديث تقدم التحديات', ['error' => $e->getMessage()]);
        }
    }

    // =====================================================
    // الطرق المساعدة (Helpers)
    // =====================================================

    /**
     * جلب اسم المستخدم (موظف أو عميل)
     */
    protected function getUserName(array $normalized): string
    {
        try {
            if ($normalized['user_type_value'] === 'employee') {
                $emp = Employee::find($normalized['employee_id']);
                return $emp?->full_name ?? 'موظف';
            } else {
                $cli = Client::find($normalized['client_id']);
                return $cli?->full_name ?? $cli?->trade_name ?? 'عميل';
            }
        } catch (\Throwable $e) {
            return $normalized['user_type_value'] === 'employee' ? 'موظف' : 'عميل';
        }
    }

    /**
     * رسالة النجاح المناسبة حسب العملية
     */
    protected function getSuccessMessage(string $operation, int $points): string
    {
        $absPoints = abs($points);
        return match ($operation) {
            'award' => "تم منح {$absPoints} نقطة بنجاح.",
            'deduct' => "تم خصم {$absPoints} نقطة بنجاح.",
            'reverse' => "تم عكس {$absPoints} نقطة بنجاح.",
            default => "تمت العملية بنجاح ({$absPoints} نقطة).",
        };
    }

    /**
     * جلب معرف المستوى الحالي للمستخدم
     */
    protected function getCurrentTierId(PointsTransaction $transaction): ?int
    {
        try {
            $userType = $transaction->user_type instanceof UserType
                ? $transaction->user_type->value
                : $transaction->user_type;

            $idColumn = $userType === 'employee' ? 'employee_id' : 'client_id';
            $userId = $userType === 'employee' ? $transaction->employee_id : $transaction->client_id;

            return TierUpgradeLog::where('user_type', $userType)
                ->where($idColumn, $userId)
                ->orderBy('id', 'desc')
                ->value('new_tier_id');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * حساب تاريخ انتهاء صلاحية الدفعة بناءً على نوع الانتهاء
     */
    protected function calculateExpiryDate(PointsExpirySetting $setting, PointsTransaction $transaction): ?Carbon
    {
        return match ($setting->expiry_type) {
            ExpiryType::FIFO => now()->addDays($setting->expiry_days + ($setting->grace_period_days ?? 0)),
            ExpiryType::FIXED_DATE => $this->getNextFixedDate($setting),
            ExpiryType::ANNIVERSARY => $this->getAnniversaryDate($setting, $transaction),
            ExpiryType::NEVER => null,
            default => null,
        };
    }

    /**
     * حساب تاريخ الانتهاء للتاريخ الثابت (أقرب تاريخ قادم)
     */
    protected function getNextFixedDate(PointsExpirySetting $setting): Carbon
    {
        $month = $setting->expiry_month;
        $day = $setting->expiry_day;
        $year = now()->year;

        $date = Carbon::createFromDate($year, $month, $day)->startOfDay();

        if ($date->isPast()) {
            $date->addYear();
        }

        if ($setting->grace_period_days) {
            $date->addDays($setting->grace_period_days);
        }

        return $date;
    }

    /**
     * حساب تاريخ الانتهاء بناءً على ذكرى انضمام المستخدم
     */
    protected function getAnniversaryDate(PointsExpirySetting $setting, PointsTransaction $transaction): Carbon
    {
        $userType = $transaction->user_type instanceof UserType
            ? $transaction->user_type
            : UserType::from($transaction->user_type);

        $joinDate = null;
        if ($userType === UserType::EMPLOYEE && $transaction->employee) {
            $joinDate = $transaction->employee->created_at;
        } elseif ($userType === UserType::CLIENT && $transaction->client) {
            $joinDate = $transaction->client->created_at;
        }

        if (!$joinDate) {
            return now()->addDays($setting->expiry_days ?: 365);
        }

        $month = $setting->expiry_month ?: $joinDate->month;
        $day = $setting->expiry_day ?: $joinDate->day;
        $anniversary = Carbon::createFromDate(now()->year, $month, $day)->startOfDay();

        if ($anniversary->isPast()) {
            $anniversary->addYear();
        }

        if ($setting->grace_period_days) {
            $anniversary->addDays($setting->grace_period_days);
        }

        return $anniversary;
    }

    // =====================================================
    // مضاعف المستوى (Tier Multiplier)
    // =====================================================

    /**
     * تطبيق مضاعف المستوى على النقاط
     * مثال: مستوى ذهبي بمضاعف 1.5x → 10 نقاط تصبح 15
     * 
     * @return array{points: int, applied: bool}
     */
    protected function applyTierMultiplier(array $normalized, int $points): array
    {
        try {
            if (!PointsGeneralSetting::isSubsystemEnabled('tiers')) {
                return ['points' => $points, 'applied' => false];
            }

            // جلب المستوى الحالي للمستخدم
            $currentTierId = null;
            $lastUpgrade = TierUpgradeLog::where('user_type', $normalized['user_type_value'])
                ->where($normalized['id_column'], $normalized['user_id'])
                ->orderBy('id', 'desc')
                ->first();

            if ($lastUpgrade) {
                $currentTierId = $lastUpgrade->new_tier_id;
            }

            if (!$currentTierId) {
                return ['points' => $points, 'applied' => false];
            }

            $tier = PointsTier::find($currentTierId);
            if (!$tier || !$tier->hasMultiplier()) {
                return ['points' => $points, 'applied' => false];
            }

            $multipliedPoints = (int) floor($points * $tier->points_multiplier);

            Log::info('PointsService: تم تطبيق مضاعف المستوى', [
                'user_type' => $normalized['user_type_value'],
                'user_id' => $normalized['user_id'],
                'tier' => $tier->name,
                'multiplier' => $tier->points_multiplier,
                'original' => $points,
                'multiplied' => $multipliedPoints,
            ]);

            return ['points' => $multipliedPoints, 'applied' => true];

        } catch (\Throwable $e) {
            Log::warning('PointsService: فشل تطبيق مضاعف المستوى', ['error' => $e->getMessage()]);
            return ['points' => $points, 'applied' => false];
        }
    }

    // =====================================================
    // تحديث الملخص اليومي (Daily Summary)
    // =====================================================

    /**
     * تحديث جدول points_daily_summary بعد كل معاملة
     * يُسجِّل إحصائيات اليوم الحالي لكل نوع مستخدم وفرع
     */
    protected function updateDailySummary(
        PointsTransaction $transaction,
        array $normalized,
        PointsResult $result
    ): void {
        try {
            $today = now()->toDateString();
            $branchId = $normalized['branch_id'];

            // جلب أو إنشاء سجل الملخص اليومي
            $summary = PointsDailySummary::firstOrCreate(
                [
                    'date' => $today,
                    'user_type' => $normalized['user_type_value'],
                    'branch_id' => $branchId,
                ],
                [
                    'total_earned' => 0,
                    'total_spent' => 0,
                    'total_expired' => 0,
                    'total_adjusted' => 0,
                    'transactions_count' => 0,
                    'unique_users' => 0,
                    'new_tier_upgrades' => 0,
                    'redemptions_count' => 0,
                    'redemptions_value' => 0,
                    'achievements_unlocked' => 0,
                    'challenges_completed' => 0,
                    'referrals_confirmed' => 0,
                ]
            );

            // تحديث العدادات
            $summary->transactions_count += 1;

            if ($transaction->points > 0) {
                $summary->total_earned += $transaction->points;
            } else {
                $summary->total_spent += abs($transaction->points);
            }

            // تحديث عدد المستخدمين الفريدين
            $uniqueUsers = PointsTransaction::where('user_type', $normalized['user_type_value'])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                // مدى اليوم لا `whereDate()`: الدالّة على العمود تُسقط فهرس `created_at`
                // فيصير العدُّ مسحاً للجدول كلّه (~ثانيتان) مع كل نقطةٍ تُمنح
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                ->where('status', TransactionStatus::COMPLETED)
                ->select($normalized['id_column'])
                ->distinct()
                ->count($normalized['id_column']);
            $summary->unique_users = $uniqueUsers;

            // تحديث الترقيات
            if ($result->hasTierChange()) {
                $summary->new_tier_upgrades += 1;
            }

            // تحديث الإنجازات
            if ($result->hasAchievements()) {
                $summary->achievements_unlocked += count($result->achievements);
            }

            $summary->save();

        } catch (\Throwable $e) {
            Log::warning('PointsService: فشل تحديث الملخص اليومي', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
