<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\ExpiryType;
use App\Enums\TransactionStatus;
use App\Enums\UpgradeType;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsExpirySetting;
use App\Models\Ecosystem\PointsGeneralSetting;
use App\Models\Ecosystem\PointsSetting;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Employee;
use App\Services\AchievementService;
use App\Services\ChallengeService;
use App\Services\PointsNotificationService;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PointsTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PointsTransaction::with(['employee', 'client', 'branch', 'creator']);

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', 'like', '%'.$request->action_type.'%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by points type (earned/spent)
        if ($request->filled('points_type')) {
            if ($request->points_type === 'earned') {
                $query->where('points', '>', 0);
            } elseif ($request->points_type === 'spent') {
                $query->where('points', '<', 0);
            }
        }

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by client
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by points range
        if ($request->filled('min_points')) {
            $query->where('points', '>=', $request->min_points);
        }
        if ($request->filled('max_points')) {
            $query->where('points', '<=', $request->max_points);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('trade_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total_transactions' => PointsTransaction::count(),
            'total_earned' => PointsTransaction::where('points', '>', 0)->sum('points'),
            'total_spent' => abs(PointsTransaction::where('points', '<', 0)->sum('points')),
            'pending_count' => PointsTransaction::where('status', TransactionStatus::PENDING->value)->count(),
        ];

        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $statuses = TransactionStatus::values();
        $employees = Employee::select('id', 'first_name', 'middle_name')->active()->get();
        $clients = Client::select('id', 'first_name', 'last_name')
            ->whereHas('status_client', function ($q) {
                $q->where('state', 1);
            })->get();

        return view('ecosystem::points.transactions.index', compact(
            'transactions',
            'branches',
            'userTypes',
            'statuses',
            'employees',
            'clients',
            'stats'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $statuses = TransactionStatus::values();
        $employees = Employee::select('id', 'first_name', 'middle_name')->active()->get();
        $clients = Client::select('id', 'first_name', 'last_name')
            ->whereHas('status_client', function ($q) {
                $q->where('state', 1);
            })->get();

        // جلب إعدادات النقاط النشطة مجمّعة حسب نوع المستخدم
        $pointsSettings = PointsSetting::active()
            ->orderBy('user_type')
            ->orderBy('action_name')
            ->get()
            ->groupBy('user_type');

        return view('ecosystem::points.transactions.create', compact(
            'branches',
            'userTypes',
            'statuses',
            'employees',
            'clients',
            'pointsSettings'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_type' => 'required|string|in:'.implode(',', UserType::values()),
            'employee_id' => 'required_if:user_type,employee|nullable|exists:employees,id',
            'client_id' => 'required_if:user_type,client|nullable|exists:clients,id',
            'action_type' => 'required|string|max:100',
            'transaction_amount' => 'nullable|numeric|min:0',
            'points' => 'nullable|integer',
            'description' => 'nullable|string',
            'status' => 'required|string|in:'.implode(',', TransactionStatus::values()),
            'branch_id' => 'nullable|exists:branches,id',
            'expires_at' => 'nullable|date|after:today',
        ]);

        // حساب النقاط تلقائياً من إعدادات النقاط
        $transactionAmount = $validated['transaction_amount'] ?? null;
        $calculatedPoints = PointsSetting::getPointsForAction(
            $validated['user_type'],
            $validated['action_type'],
            $transactionAmount,
            $validated['branch_id'] ?? null
        );

        // استخدام النقاط المحسوبة تلقائياً، أو النقاط اليدوية إذا لم يوجد إعداد
        $validated['points'] = $calculatedPoints ?? ($validated['points'] ?? 0);

        // =====================================================
        // تطبيق إعدادات النظام العامة (General Settings)
        // =====================================================
        try {
            $generalSettings = PointsGeneralSetting::instance();

            // فحص تفعيل نقاط الموظفين/العملاء
            if ($validated['user_type'] === 'employee' && ! $generalSettings->enable_employee_points) {
                return redirect()->back()->withInput()
                    ->with('error', 'نقاط الموظفين معطلة حالياً من الإعدادات العامة.');
            }
            if ($validated['user_type'] === 'client' && ! $generalSettings->enable_client_points) {
                return redirect()->back()->withInput()
                    ->with('error', 'نقاط العملاء معطلة حالياً من الإعدادات العامة.');
            }

            // فحص الحد الأقصى للنقاط لكل معاملة
            if ($generalSettings->max_points_per_transaction > 0 && abs($validated['points']) > $generalSettings->max_points_per_transaction) {
                return redirect()->back()->withInput()
                    ->with('error', 'تجاوز الحد الأقصى للنقاط لكل معاملة. الحد: '.number_format($generalSettings->max_points_per_transaction).' نقطة');
            }

            // فحص الحد الأقصى للكسب اليومي (فقط للنقاط الموجبة)
            if ($validated['points'] > 0 && $generalSettings->max_daily_earn > 0) {
                $idColumn = $validated['user_type'] === 'employee' ? 'employee_id' : 'client_id';
                $userId = $validated['user_type'] === 'employee' ? $validated['employee_id'] : $validated['client_id'];

                $todayEarned = PointsTransaction::where('user_type', $validated['user_type'])
                    ->where($idColumn, $userId)
                    ->where('status', TransactionStatus::COMPLETED)
                    ->where('points', '>', 0)
                    ->whereDate('created_at', today())
                    ->sum('points');

                if (($todayEarned + $validated['points']) > $generalSettings->max_daily_earn) {
                    $remaining = max(0, $generalSettings->max_daily_earn - $todayEarned);

                    return redirect()->back()->withInput()
                        ->with('error', 'تجاوز الحد الأقصى للكسب اليومي. الحد: '.number_format($generalSettings->max_daily_earn).' | المكتسب اليوم: '.number_format($todayEarned).' | المتبقي: '.number_format($remaining));
                }
            }

            // كشف النشاط المشبوه (Fraud Detection)
            if ($generalSettings->enable_fraud_detection && $validated['points'] > 0) {
                $idColumn = $validated['user_type'] === 'employee' ? 'employee_id' : 'client_id';
                $userId = $validated['user_type'] === 'employee' ? $validated['employee_id'] : $validated['client_id'];

                // عدد المعاملات خلال آخر ساعة
                $lastHourCount = PointsTransaction::where('user_type', $validated['user_type'])
                    ->where($idColumn, $userId)
                    ->where('created_at', '>=', now()->subHour())
                    ->count();

                $threshold = $generalSettings->suspicious_threshold ?: 10;

                if ($lastHourCount >= $threshold) {
                    \Log::warning("⚠️ نشاط مشبوه: المستخدم {$validated['user_type']}#{$userId} أجرى {$lastHourCount} معاملة خلال الساعة الأخيرة");

                    return redirect()->back()->withInput()
                        ->with('error', 'تم اكتشاف نشاط مشبوه. عدد المعاملات خلال الساعة الأخيرة: '.$lastHourCount.'. الحد المسموح: '.$threshold);
                }
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
        }

        // Get current balance from transactions for this month
        $userType = $validated['user_type'];
        $month = now()->month;
        $year = now()->year;

        $userTypeEnum = $userType === 'employee' ? UserType::EMPLOYEE : UserType::CLIENT;
        $idColumn = $userType === 'employee' ? 'employee_id' : 'client_id';
        $userId = $userType === 'employee' ? $validated['employee_id'] : $validated['client_id'];

        // حساب الرصيد الحالي من المعاملات
        $balanceBefore = PointsTransaction::where('user_type', $userTypeEnum)
            ->where($idColumn, $userId)
            ->where('points_month', $month)
            ->where('points_year', $year)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $validated['balance_before'] = $balanceBefore;
        $validated['balance_after'] = $balanceBefore + $validated['points'];
        $validated['created_by'] = Auth::id();

        // تسجيل الشهر والسنة تلقائياً
        $validated['points_month'] = $month;
        $validated['points_year'] = $year;

        $transaction = PointsTransaction::create($validated);

        // =====================================================
        // إنشاء دفعة نقاط (Batch) للنقاط الموجبة المكتملة
        // لتتبع انتهاء الصلاحية بدقة
        // =====================================================
        if ($validated['points'] > 0 && ($validated['status'] === TransactionStatus::COMPLETED->value)) {
            $this->createPointsBatch($transaction);
        }

        // تسجيل ترقية المستوى وفحص الإنجازات إذا كانت المعاملة مكتملة
        if ($validated['status'] === TransactionStatus::COMPLETED->value) {
            // إرسال إشعار كسب النقاط
            if ($validated['points'] > 0) {
                try {
                    $userName = '';
                    if ($userType === 'employee') {
                        $emp = Employee::find($validated['employee_id']);
                        $userName = $emp ? $emp->full_name : '';
                    } else {
                        $cli = Client::find($validated['client_id']);
                        $userName = $cli ? $cli->full_name : '';
                    }
                    app(PointsNotificationService::class)->notifyPointsEarned(
                        $validated['user_type'],
                        $validated['employee_id'] ?? null,
                        $validated['client_id'] ?? null,
                        [
                            'user_name' => $userName,
                            'points' => $validated['points'],
                            'action_type' => $validated['action_type'],
                            'balance_after' => $transaction->balance_after,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::warning('فشل إرسال إشعار كسب النقاط: '.$e->getMessage());
                }
            }

            $this->checkAndLogTierUpgrade($transaction);

            // فحص ومنح الإنجازات تلقائياً
            $achievementService = app(AchievementService::class);
            $awarded = $achievementService->checkAchievements($userId, $userTypeEnum);

            // تحديث تقدم التحديات
            try {
                app(ChallengeService::class)->updateProgressForTransaction(
                    $validated['action_type'],
                    $userId,
                    $userTypeEnum,
                    $transaction
                );
            } catch (\Throwable $e) {
                \Log::warning('Challenge progress update failed: '.$e->getMessage());
            }

            if (! empty($awarded)) {
                $achievementNames = collect($awarded)->pluck('achievement.name')->implode('، ');

                // إرسال إشعارات الإنجازات
                try {
                    $notificationService = new PointsNotificationService;
                    foreach ($awarded as $awardedItem) {
                        $achievement = $awardedItem['achievement'];
                        $userName = $userType === 'employee'
                            ? (Employee::find($userId)?->full_name ?? 'موظف')
                            : (Client::find($userId)?->full_name ?? Client::find($userId)?->trade_name ?? 'عميل');

                        $notificationService->notifyAchievementUnlocked(
                            $userType,
                            $userType === 'employee' ? $userId : null,
                            $userType === 'client' ? $userId : null,
                            [
                                'user_name' => $userName,
                                'achievement_name' => $achievement->name,
                                'bonus_points' => $achievement->bonus_points ?? 0,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    \Log::warning('فشل إرسال إشعار الإنجازات: '.$e->getMessage());
                }

                return redirect()
                    ->route('ecosystem.points.transactions.index')
                    ->with('success', 'تم إضافة معاملة النقاط بنجاح')
                    ->with('info', '🏆 تم تحقيق إنجازات جديدة: '.$achievementNames);
            }
        }

        return redirect()
            ->route('ecosystem.points.transactions.index')
            ->with('success', 'تم إضافة معاملة النقاط بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(PointsTransaction $transaction)
    {
        $transaction->load(['employee', 'client', 'branch', 'creator', 'reference']);

        return view('ecosystem::points.transactions.show', compact('transaction'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PointsTransaction $transaction)
    {
        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $statuses = TransactionStatus::values();
        $employees = Employee::select('id', 'first_name', 'middle_name')->active()->get();
        $clients = Client::select('id', 'first_name', 'last_name')
            ->whereHas('status_client', function ($q) {
                $q->where('state', 1);
            })->get();

        // جلب إعدادات النقاط النشطة
        $pointsSettings = PointsSetting::active()
            ->orderBy('user_type')
            ->orderBy('action_name')
            ->get()
            ->groupBy('user_type');

        // جلب إعداد الإجراء الحالي
        $currentSetting = PointsSetting::getSettingForAction(
            $transaction->user_type->value ?? $transaction->user_type,
            $transaction->action_type,
            $transaction->branch_id
        );

        return view('ecosystem::points.transactions.edit', compact(
            'transaction',
            'branches',
            'userTypes',
            'statuses',
            'employees',
            'clients',
            'pointsSettings',
            'currentSetting'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PointsTransaction $transaction)
    {
        $validated = $request->validate([
            'action_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|string|in:'.implode(',', TransactionStatus::values()),
            'expires_at' => 'nullable|date',
        ]);

        $oldStatus = $transaction->status;
        $transaction->update($validated);

        // If status changed to completed, check tier upgrade and achievements
        if (
            $oldStatus !== TransactionStatus::COMPLETED &&
            $validated['status'] === TransactionStatus::COMPLETED->value
        ) {
            $this->checkAndLogTierUpgrade($transaction);

            // فحص ومنح الإنجازات
            $userType = $transaction->user_type;
            $userId = $userType === UserType::EMPLOYEE
                ? $transaction->employee_id
                : $transaction->client_id;

            if ($userId) {
                app(AchievementService::class)->checkAchievements($userId, $userType);
            }
        }

        return redirect()
            ->route('ecosystem.points.transactions.index')
            ->with('success', 'تم تحديث معاملة النقاط بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsTransaction $transaction)
    {
        $transaction->delete();

        return redirect()
            ->route('ecosystem.points.transactions.index')
            ->with('success', 'تم حذف معاملة النقاط بنجاح');
    }

    /**
     * Toggle transaction status
     */
    public function toggleStatus(PointsTransaction $transaction)
    {
        $newStatus = $transaction->status === TransactionStatus::COMPLETED
            ? TransactionStatus::CANCELLED
            : TransactionStatus::COMPLETED;

        // تحديث الحالة أولاً ثم فحص الإنجازات (لضمان احتساب المعاملة في الاستعلامات)
        $transaction->update(['status' => $newStatus->value]);

        if ($newStatus === TransactionStatus::COMPLETED) {
            $this->checkAndLogTierUpgrade($transaction);

            // فحص ومنح الإنجازات
            $userType = $transaction->user_type;
            $userId = $userType === UserType::EMPLOYEE
                ? $transaction->employee_id
                : $transaction->client_id;

            if ($userId) {
                app(AchievementService::class)->checkAchievements($userId, $userType);

                // تحديث تقدم التحديات
                try {
                    app(ChallengeService::class)->updateProgressForTransaction(
                        $transaction->action_type,
                        $userId,
                        $userType,
                        $transaction
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Challenge progress update failed: '.$e->getMessage());
                }
            }
        } elseif ($newStatus === TransactionStatus::CANCELLED) {
            // عكس تقدم التحديات عند إلغاء المعاملة
            try {
                app(ChallengeService::class)->reverseProgressForTransaction($transaction);
            } catch (\Throwable $e) {
                \Log::warning('Challenge progress reversal failed: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة المعاملة بنجاح',
            'new_status' => $newStatus->value,
            'new_status_label' => $newStatus->label(),
        ]);
    }

    /**
     * Bulk delete transactions
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد أي معاملات']);
        }

        PointsTransaction::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف '.count($ids).' معاملة بنجاح',
        ]);
    }

    /**
     * Export transactions
     */
    public function export(Request $request)
    {
        // TODO: Implement export functionality
        return back()->with('info', 'سيتم إضافة خاصية التصدير قريباً');
    }

    /**
     * التحقق من ترقية المستوى وتسجيلها
     * يتم حساب المستوى بناءً على النقاط المكتسبة في الشهر الحالي
     */
    private function checkAndLogTierUpgrade(PointsTransaction $transaction): void
    {
        $userType = $transaction->user_type->value;
        $userId = $userType === 'employee' ? $transaction->employee_id : $transaction->client_id;

        if (! $userId) {
            return;
        }

        $userTypeEnum = $userType === 'employee' ? UserType::EMPLOYEE : UserType::CLIENT;
        $idColumn = $userType === 'employee' ? 'employee_id' : 'client_id';

        // حساب إجمالي النقاط المكتسبة في الشهر الحالي
        $monthlyPoints = PointsTransaction::where('user_type', $userTypeEnum)
            ->where($idColumn, $userId)
            ->where('points_month', $transaction->points_month)
            ->where('points_year', $transaction->points_year)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        // الحصول على المستوى المناسب للنقاط
        $newTier = PointsTier::getTierForPoints($userType, $monthlyPoints);

        if (! $newTier) {
            return;
        }

        // البحث عن آخر سجل ترقية لهذا المستخدم في هذا الشهر
        $lastUpgrade = TierUpgradeLog::where('user_type', $userType)
            ->where($idColumn, $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        $lastTierId = $lastUpgrade ? $lastUpgrade->new_tier_id : null;

        // تسجيل الترقية فقط إذا تغير المستوى
        if ($newTier->id !== $lastTierId) {
            try {
                TierUpgradeLog::create([
                    'user_type' => $userType,
                    'employee_id' => $userType === 'employee' ? $userId : null,
                    'client_id' => $userType === 'client' ? $userId : null,
                    'old_tier_id' => $lastTierId,
                    'new_tier_id' => $newTier->id,
                    'points_at_upgrade' => $monthlyPoints,
                    'upgrade_type' => UpgradeType::MANUAL->value,
                    'upgraded_by' => Auth::id(),
                    'created_at' => now(),
                ]);

                \Log::info('Tier upgrade logged', [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'old_tier_id' => $lastTierId,
                    'new_tier_id' => $newTier->id,
                    'monthly_points' => $monthlyPoints,
                ]);

                // إرسال إشعار ترقية المستوى
                try {
                    $userName = '';
                    if ($userType === 'employee') {
                        $emp = Employee::find($userId);
                        $userName = $emp ? $emp->full_name : '';
                    } else {
                        $cli = Client::find($userId);
                        $userName = $cli ? $cli->full_name : '';
                    }
                    $oldTier = $lastTierId ? PointsTier::find($lastTierId) : null;
                    app(PointsNotificationService::class)->notifyTierUpgraded(
                        $userType,
                        $userType === 'employee' ? $userId : null,
                        $userType === 'client' ? $userId : null,
                        [
                            'user_name' => $userName,
                            'old_tier' => $oldTier ? $oldTier->name : 'بدون مستوى',
                            'new_tier' => $newTier->name,
                            'points' => $monthlyPoints,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::warning('فشل إرسال إشعار ترقية المستوى: '.$e->getMessage());
                }
            } catch (\Exception $e) {
                \Log::error('Failed to log tier upgrade: '.$e->getMessage());
            }
        }
    }

    // =====================================================
    // إنشاء دفعة نقاط عند كسب نقاط جديدة
    // =====================================================

    /**
     * إنشاء دفعة نقاط (Batch) لتتبع انتهاء الصلاحية
     */
    private function createPointsBatch(PointsTransaction $transaction): void
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
                if ($expirySetting->isTierExcluded($currentTierId)) {
                    // المستوى مستثنى — لا تنتهي النقاط
                    $expiresAt = null;
                } else {
                    $expiresAt = $this->calculateExpiryDate($expirySetting, $transaction);
                }
            }

            PointsBatch::create([
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
            \Log::warning('فشل إنشاء دفعة النقاط: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * حساب تاريخ انتهاء صلاحية الدفعة بناءً على نوع الانتهاء
     */
    private function calculateExpiryDate(PointsExpirySetting $setting, PointsTransaction $transaction): ?Carbon
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
    private function getNextFixedDate(PointsExpirySetting $setting): Carbon
    {
        $month = $setting->expiry_month;
        $day = $setting->expiry_day;
        $year = now()->year;

        $date = Carbon::createFromDate($year, $month, $day)->startOfDay();

        // إذا مضى التاريخ هذا العام، انتقل للعام القادم
        if ($date->isPast()) {
            $date->addYear();
        }

        // إضافة فترة السماح
        if ($setting->grace_period_days) {
            $date->addDays($setting->grace_period_days);
        }

        return $date;
    }

    /**
     * حساب تاريخ الانتهاء بناءً على ذكرى انضمام المستخدم
     */
    private function getAnniversaryDate(PointsExpirySetting $setting, PointsTransaction $transaction): Carbon
    {
        // جلب تاريخ انضمام المستخدم
        $userType = $transaction->user_type instanceof UserType
            ? $transaction->user_type
            : UserType::from($transaction->user_type);

        $joinDate = null;
        if ($userType === UserType::EMPLOYEE && $transaction->employee) {
            $joinDate = $transaction->employee->created_at;
        } elseif ($userType === UserType::CLIENT && $transaction->client) {
            $joinDate = $transaction->client->created_at;
        }

        if (! $joinDate) {
            // إذا لم يتوفر تاريخ الانضمام، استخدم FIFO كبديل
            return now()->addDays($setting->expiry_days ?: 365);
        }

        // حساب أقرب ذكرى سنوية
        $month = $setting->expiry_month ?: $joinDate->month;
        $day = $setting->expiry_day ?: $joinDate->day;
        $anniversary = Carbon::createFromDate(now()->year, $month, $day)->startOfDay();

        if ($anniversary->isPast()) {
            $anniversary->addYear();
        }

        // إضافة فترة السماح
        if ($setting->grace_period_days) {
            $anniversary->addDays($setting->grace_period_days);
        }

        return $anniversary;
    }

    /**
     * جلب معرف المستوى الحالي للمستخدم
     */
    private function getCurrentTierId(PointsTransaction $transaction): ?int
    {
        try {
            $userType = $transaction->user_type instanceof UserType
                ? $transaction->user_type->value
                : $transaction->user_type;

            $idColumn = $userType === 'employee' ? 'employee_id' : 'client_id';
            $userId = $userType === 'employee' ? $transaction->employee_id : $transaction->client_id;

            // جلب آخر ترقية مستوى
            $lastUpgrade = TierUpgradeLog::where('user_type', $userType)
                ->where($idColumn, $userId)
                ->orderBy('id', 'desc')
                ->first();

            return $lastUpgrade?->new_tier_id;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.PointsTransaction', 'getCurrentTierId');

            return null;
        }
    }
}
