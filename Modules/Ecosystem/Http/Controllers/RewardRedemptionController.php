<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Enums\BatchStatus;
use App\Enums\DeliveryMethod;
use App\Enums\LinkingType;
use App\Enums\RedemptionStatus;
use App\Enums\ReferenceType;
use App\Enums\UserType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\DefaultWarehouses;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsGeneralSetting;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\Reward;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PermissionSource;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Services\PointsNotificationService;
use App\Services\Rewards\EmployeeRewardFulfillment;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RewardRedemptionController extends Controller
{
    /**
     * عرض قائمة طلبات الاستبدال
     */
    public function index(Request $request)
    {
        $query = RewardRedemption::with(['reward', 'employee', 'client', 'branch', 'approver']);

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب المكافأة
        if ($request->filled('reward_id')) {
            $query->where('reward_id', $request->reward_id);
        }

        // فلترة حسب الفرع
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('redemption_number', 'like', "%{$search}%")
                    ->orWhere('voucher_code', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $redemptions = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => RewardRedemption::count(),
            'pending' => RewardRedemption::where('status', RedemptionStatus::PENDING->value)->count(),
            'approved' => RewardRedemption::where('status', RedemptionStatus::APPROVED->value)->count(),
            'delivered' => RewardRedemption::where('status', RedemptionStatus::DELIVERED->value)->count(),
            'cancelled' => RewardRedemption::where('status', RedemptionStatus::CANCELLED->value)->count(),
        ];

        $rewards = Reward::where('is_active', true)->get();
        $branches = Branch::all();

        return view('ecosystem::points.redemptions.index', compact(
            'redemptions',
            'stats',
            'rewards',
            'branches'
        ));
    }

    /**
     * صفحة إنشاء طلب استبدال جديد
     */
    public function create(Request $request)
    {
        $rewards = Reward::where('is_active', true)->get();
        $branches = Branch::all();
        $userTypes = UserType::values();

        // جلب فقط الموظفين الذين لديهم نقاط (مثل PointsUsersController)
        $employeeIdsWithPoints = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->distinct()
            ->pluck('employee_id')
            ->filter()
            ->toArray();

        $employees = Employee::active()
            ->whereIn('id', $employeeIdsWithPoints)
            ->get()
            ->map(function ($employee) {
                // حساب رصيد النقاط الحالي
                $latestTx = PointsTransaction::where('employee_id', $employee->id)
                    ->where('user_type', UserType::EMPLOYEE)
                    ->orderBy('id', 'desc')
                    ->first();
                $employee->current_points_balance = $latestTx ? $latestTx->balance_after : 0;

                return $employee;
            })
            ->filter(function ($employee) {
                return $employee->current_points_balance > 0;
            })
            ->values();

        // جلب فقط العملاء الذين لديهم نقاط
        $clientIdsWithPoints = PointsTransaction::where('user_type', UserType::CLIENT)
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->toArray();

        $clients = Client::whereIn('id', $clientIdsWithPoints)
            ->get()
            ->map(function ($client) {
                $latestTx = PointsTransaction::where('client_id', $client->id)
                    ->where('user_type', UserType::CLIENT)
                    ->orderBy('id', 'desc')
                    ->first();
                $client->current_points_balance = $latestTx ? $latestTx->balance_after : 0;

                return $client;
            })
            ->filter(function ($client) {
                return $client->current_points_balance > 0;
            })
            ->values();

        // جلب المستودعات
        $storeHouses = StoreHouse::all();

        // جلب الخزائن (حسابات نقدية)
        // الخزائنُ التي يملك المستخدمُ الصرفَ منها — القرارُ نفسُه الذي يُسأل عند الحفظ
        $treasuries = TreasuryBalanceGuard::withdrawableAccounts();

        return view('ecosystem::points.redemptions.create', compact(
            'rewards',
            'branches',
            'userTypes',
            'employees',
            'clients',
            'storeHouses',
            'treasuries'
        ));
    }

    /**
     * حفظ طلب استبدال جديد
     */
    public function store(Request $request)
    {
        $reward = Reward::findOrFail($request->reward_id);

        // التحقق من أن المكافأة مرتبطة بنوع المستخدم المحدد
        if (! in_array($reward->user_type->value, [$request->user_type, 'both'])) {
            return redirect()->back()->withInput()
                ->with('error', 'هذه المكافأة غير متاحة لنوع المستخدم المحدد ('.($request->user_type === 'employee' ? 'موظف' : 'عميل').').');
        }

        $request->validate([
            'reward_id' => 'required|exists:rewards,id',
            'user_type' => 'required|in:'.implode(',', UserType::values()),
            'employee_id' => 'required_if:user_type,employee|nullable|exists:employees,id',
            'client_id' => 'required_if:user_type,client|nullable|exists:clients,id',
            'quantity' => 'required|integer|min:1',
            'delivery_method' => 'nullable|in:'.implode(',', DeliveryMethod::values()),
            'delivery_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'branch_id' => 'nullable|exists:branches,id',
            'store_house_id' => [
                'nullable',
                'exists:store_houses,id',
                function ($attribute, $value, $fail) use ($reward) {
                    // المستودع مطلوب فقط إذا كانت هناك منتج مرتبط
                    if ($reward->product_id && ! $value) {
                        $fail('المستودع مطلوب للمكافآت المرتبطة بمنتج.');
                    }
                },
            ],
            'treasury_account_id' => [
                'nullable',
                'exists:accounts,id',
                function ($attribute, $value, $fail) use ($reward, $request) {
                    // الخزينة مطلوبة للمكافآت النقدية والقسائم (لأنها تحتاج صرف عند التسليم)
                    // — إلا المكافأة النقدية للموظف: تُصرف مع راتبه لا من الخزينة
                    $type = $reward->reward_type->value;
                    $needsTreasury = $type === 'voucher'
                        || ($type === 'cash' && $request->user_type !== 'employee');
                    if ($needsTreasury && ! $value) {
                        $fail('الخزينة مطلوبة للمكافآت النقدية والقسائم.');
                    }
                },
            ],
        ]);

        // =====================================================
        // تطبيق إعدادات النظام العامة على الاستبدال
        // =====================================================
        $autoApprove = false;
        try {
            $generalSettings = PointsGeneralSetting::instance();

            // فحص تفعيل نقاط الموظفين/العملاء
            if ($request->user_type === 'employee' && ! $generalSettings->enable_employee_points) {
                return redirect()->back()->withInput()
                    ->with('error', 'نقاط الموظفين معطلة حالياً من الإعدادات العامة.');
            }
            if ($request->user_type === 'client' && ! $generalSettings->enable_client_points) {
                return redirect()->back()->withInput()
                    ->with('error', 'نقاط العملاء معطلة حالياً من الإعدادات العامة.');
            }

            // إذا كانت الموافقة غير مطلوبة، يتم الموافقة تلقائياً
            if (! $generalSettings->require_redemption_approval) {
                $autoApprove = true;
            }

            // فحص الحد الأقصى لطلبات الاستبدال اليومية
            if ($generalSettings->max_daily_redemptions > 0) {
                $todayRedemptions = RewardRedemption::where('user_type', $request->user_type)
                    ->when($request->user_type === 'employee', fn ($q) => $q->where('employee_id', $request->employee_id))
                    ->when($request->user_type === 'client', fn ($q) => $q->where('client_id', $request->client_id))
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', [RedemptionStatus::CANCELLED->value])
                    ->count();

                if ($todayRedemptions >= $generalSettings->max_daily_redemptions) {
                    return redirect()->back()->withInput()
                        ->with('error', 'تم الوصول للحد الأقصى لطلبات الاستبدال اليومية ('.$generalSettings->max_daily_redemptions.' طلبات). يرجى المحاولة غداً.');
                }
            }

            // فحص فترة الانتظار بين الطلبات (Cooldown)
            if ($generalSettings->redemption_cooldown_hours > 0) {
                $lastRedemption = RewardRedemption::where('user_type', $request->user_type)
                    ->when($request->user_type === 'employee', fn ($q) => $q->where('employee_id', $request->employee_id))
                    ->when($request->user_type === 'client', fn ($q) => $q->where('client_id', $request->client_id))
                    ->whereNotIn('status', [RedemptionStatus::CANCELLED->value])
                    ->latest()
                    ->first();

                if ($lastRedemption && $lastRedemption->created_at->diffInHours(now()) < $generalSettings->redemption_cooldown_hours) {
                    $remainingHours = $generalSettings->redemption_cooldown_hours - $lastRedemption->created_at->diffInHours(now());

                    return redirect()->back()->withInput()
                        ->with('error', 'يجب الانتظار '.$remainingHours.' ساعة على الأقل بين كل طلب استبدال. فترة الانتظار: '.$generalSettings->redemption_cooldown_hours.' ساعة');
                }
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
        }

        // التحقق من أن الكمية = 1 للأنواع التي لا تدعم التكرار
        if ($reward->singleQuantityOnly() && $request->quantity > 1) {
            return redirect()->back()->withInput()
                ->with('error', 'هذا النوع من المكافآت ('.$reward->reward_type->label().') لا يدعم كمية أكثر من 1.');
        }

        // التحقق من توفر الكمية
        if ($reward->quantity_available !== null) {
            $remaining = $reward->getRemainingQuantity();
            if ($remaining !== null && $remaining < $request->quantity) {
                return redirect()->back()->withInput()
                    ->with('error', 'الكمية المطلوبة غير متاحة. الكمية المتبقية: '.$remaining);
            }
        }

        // التحقق من كفاية رصيد النقاط قبل إنشاء الطلب
        $pointsToDeduct = $reward->points_required * $request->quantity;
        if ($request->user_type === 'employee') {
            $latestTx = PointsTransaction::where('employee_id', $request->employee_id)
                ->where('user_type', UserType::EMPLOYEE)
                ->orderBy('id', 'desc')->first();
        } else {
            $latestTx = PointsTransaction::where('client_id', $request->client_id)
                ->where('user_type', UserType::CLIENT)
                ->orderBy('id', 'desc')->first();
        }
        $currentBalance = $latestTx ? $latestTx->balance_after : 0;

        if ($currentBalance < $pointsToDeduct) {
            return redirect()->back()->withInput()
                ->with('error', 'رصيد النقاط غير كافٍ لهذا الاستبدال. الرصيد الحالي: '.number_format($currentBalance).' نقطة | المطلوب: '.number_format($pointsToDeduct).' نقطة');
        }

        // التحقق من الحد الأقصى لكل مستخدم
        if ($reward->max_per_user) {
            $userRedemptions = RewardRedemption::where('reward_id', $reward->id)
                ->where('user_type', $request->user_type);

            if ($request->user_type === 'employee') {
                $userRedemptions->where('employee_id', $request->employee_id);
            } else {
                $userRedemptions->where('client_id', $request->client_id);
            }

            $count = $userRedemptions->whereNotIn('status', [RedemptionStatus::CANCELLED->value])->sum('quantity');

            if (($count + $request->quantity) > $reward->max_per_user) {
                return redirect()->back()->withInput()
                    ->with('error', 'تم تجاوز الحد الأقصى للاستبدال لهذا المستخدم. الحد: '.$reward->max_per_user.' | السابق: '.$count);
            }
        }

        DB::beginTransaction();

        try {
            // حساب قيمة المكافأة
            $rewardValue = $reward->reward_value * $request->quantity;

            // إنشاء طلب الاستبدال
            $redemption = RewardRedemption::create([
                'reward_id' => $reward->id,
                'user_type' => $request->user_type,
                'employee_id' => $request->user_type === 'employee' ? $request->employee_id : null,
                'client_id' => $request->user_type === 'client' ? $request->client_id : null,
                'quantity' => $request->quantity,
                'points_used' => $pointsToDeduct,
                'reward_value' => $rewardValue,
                // يُنشأ معلَّقاً دائماً؛ الاعتمادُ التلقائي بعد الحفظ يمرّ بمسار الاعتماد نفسِه
                // (كان يضع «موافق عليه» مباشرةً بلا قيدٍ ولا سطرِ راتبٍ ولا رصيدِ إجازة)
                'status' => RedemptionStatus::PENDING->value,
                'delivery_method' => $request->delivery_method,
                'delivery_address' => $request->delivery_address,
                'notes' => $request->notes,
                'branch_id' => $request->branch_id,
                'store_house_id' => $request->store_house_id,
                // المكافأة النقدية للموظف تُصرف مع الراتب — لا خزينة لها
                'treasury_account_id' => ($reward->reward_type->value === 'cash' && $request->user_type === 'employee')
                    ? null
                    : $request->treasury_account_id,
                // توليد كود القسيمة للأنواع التي تحتاجه
                'voucher_code' => $reward->needsVoucherCode()
                    ? 'VCH-'.strtoupper(Str::random(8))
                    : null,
                // الرقم التسلسلي الفريد (أكثر أماناً)
                'voucher_serial' => $reward->needsVoucherCode()
                    ? 'SRL-'.date('Ymd').'-'.strtoupper(Str::random(6))
                    : null,
                // رمز PIN (4 أرقام)
                'voucher_pin' => $reward->needsVoucherCode()
                    ? str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                    : null,
                // تاريخ انتهاء الصلاحية
                'voucher_expires_at' => $reward->needsVoucherCode() && $reward->validity_days
                    ? now()->addDays($reward->validity_days)->toDateString()
                    : null,
                'voucher_status' => 'active',
            ]);

            // إنشاء معاملة خصم نقاط (الرصيد والنقاط تم حسابها قبل الـ transaction)
            // إعادة جلب الرصيد داخل الـ DB transaction لضمان التزامن
            if ($request->user_type === 'employee') {
                $latestTx = PointsTransaction::where('employee_id', $request->employee_id)
                    ->where('user_type', UserType::EMPLOYEE)
                    ->orderBy('id', 'desc')->first();
            } else {
                $latestTx = PointsTransaction::where('client_id', $request->client_id)
                    ->where('user_type', UserType::CLIENT)
                    ->orderBy('id', 'desc')->first();
            }
            $currentBalance = $latestTx ? $latestTx->balance_after : 0;

            if ($currentBalance < $pointsToDeduct) {
                throw new \Exception('رصيد النقاط غير كافٍ. الرصيد الحالي: '.number_format($currentBalance).' | المطلوب: '.number_format($pointsToDeduct));
            }

            $transaction = PointsTransaction::create([
                'user_type' => $request->user_type,
                'employee_id' => $request->user_type === 'employee' ? $request->employee_id : null,
                'client_id' => $request->user_type === 'client' ? $request->client_id : null,
                'action_type' => 'redeem',
                'points' => -$pointsToDeduct,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance - $pointsToDeduct,
                'description' => 'استبدال مكافأة: '.$reward->name.' (x'.$request->quantity.')',
                'reference_type' => 'reward_redemption',
                'reference_id' => $redemption->id,
                'branch_id' => $request->branch_id,
                'created_by' => Auth::id(),
                'status' => 'completed',
                'points_month' => now()->month,
                'points_year' => now()->year,
            ]);

            // ربط المعاملة بطلب الاستبدال
            $redemption->update([
                'metadata' => array_merge($redemption->metadata ?? [], [
                    'transaction_id' => $transaction->id,
                ]),
            ]);

            // تحديث كمية الاستبدال في المكافأة
            $reward->increment('quantity_redeemed', $request->quantity);

            // =====================================================
            // خصم النقاط من الدفعات (FIFO) — الأقدم أولاً
            // =====================================================
            $this->deductFromBatches(
                $request->user_type,
                $request->user_type === 'employee' ? $request->employee_id : null,
                $request->user_type === 'client' ? $request->client_id : null,
                $pointsToDeduct,
                $redemption->id
            );

            // ملاحظة: قيد الالتزام يُنشأ عند الموافقة وليس عند الإنشاء
            // لأن الطلب قد يُرفض قبل الموافقة

            DB::commit();

            // الاعتمادُ التلقائي (الموافقة غير مطلوبة في الإعدادات العامة)
            $autoNote = '';
            if ($autoApprove) {
                $block = app(EmployeeRewardFulfillment::class)->approvalBlockReason($redemption->fresh('reward'));
                if ($block) {
                    $autoNote = ' — بقي معلَّقاً ولم يُعتمد تلقائياً: '.$block;
                } else {
                    try {
                        DB::transaction(function () use ($redemption) {
                            $this->markApproved($redemption->fresh('reward'));
                        });
                        $autoNote = ' — اعتُمد تلقائياً';
                    } catch (\Throwable $e) {
                        OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'auto-approve');
                        $autoNote = ' — بقي معلَّقاً ولم يُعتمد تلقائياً: '.$e->getMessage();
                    }
                }
            }

            // إرسال إشعار استبدال مكافأة
            try {
                $userName = '';
                if ($request->user_type === 'employee') {
                    $emp = Employee::find($request->employee_id);
                    $userName = $emp ? $emp->full_name : '';
                } else {
                    $cli = Client::find($request->client_id);
                    $userName = $cli ? $cli->full_name : '';
                }
                app(PointsNotificationService::class)->notifyRewardRedeemed(
                    $request->user_type,
                    $request->user_type === 'employee' ? $request->employee_id : null,
                    $request->user_type === 'client' ? $request->client_id : null,
                    [
                        'user_name' => $userName,
                        'reward_name' => $reward->name,
                        'points_used' => $pointsToDeduct,
                        'redemption_number' => $redemption->redemption_number,
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('فشل إرسال إشعار استبدال المكافأة: '.$e->getMessage());
            }

            return redirect()->route('ecosystem.points.redemptions.show', $redemption)
                ->with('success', 'تم إنشاء طلب الاستبدال بنجاح. رقم الطلب: '.$redemption->redemption_number.$autoNote);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'store');

            DB::rollBack();

            return redirect()->back()->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * عرض تفاصيل طلب استبدال
     */
    public function show(RewardRedemption $redemption)
    {
        $redemption->load(['reward', 'employee', 'client', 'branch', 'approver', 'processor', 'transaction']);

        return view('ecosystem::points.redemptions.show', compact('redemption'));
    }

    /**
     * الموافقة على طلب الاستبدال
     */
    public function approve(RewardRedemption $redemption)
    {
        if (! $redemption->isPending()) {
            return redirect()->back()->with('error', 'لا يمكن الموافقة على هذا الطلب. الحالة الحالية: '.$redemption->status->label());
        }

        // مكافأة الموظف النقدية/الإجازة: شروطُها تُسأل قبل أيّ كتابة
        if ($block = app(EmployeeRewardFulfillment::class)->approvalBlockReason($redemption)) {
            return redirect()->back()->with('error', $block);
        }

        DB::beginTransaction();

        try {
            $this->markApproved($redemption);
            $reward = $redemption->reward;

            DB::commit();

            // إرسال إشعار الموافقة
            try {
                $userName = '';
                if ($redemption->user_type->value === 'employee' && $redemption->employee) {
                    $userName = $redemption->employee->full_name;
                } elseif ($redemption->client) {
                    $userName = $redemption->client->full_name;
                }
                app(PointsNotificationService::class)->notifyRedemptionApproved(
                    $redemption->user_type->value,
                    $redemption->employee_id,
                    $redemption->client_id,
                    [
                        'user_name' => $userName,
                        'reward_name' => $reward->name,
                        'redemption_number' => $redemption->redemption_number,
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('فشل إرسال إشعار الموافقة: '.$e->getMessage());
            }

            $fulfillment = app(EmployeeRewardFulfillment::class);
            $message = 'تمت الموافقة على طلب الاستبدال بنجاح';
            if ($fulfillment->isPayrollCash($redemption)) {
                $message .= ' — أُضيفت المكافأة استحقاقاً لمرّةٍ واحدة على راتب الموظف التالي، وتُصرف مع صرفة الراتب';
            } elseif ($fulfillment->isLeaveDay($redemption)) {
                $message .= ' — أُضيفت '.(int) ($redemption->fresh()->metadata['leave_days'] ?? 0).' يوم إلى رصيد إجازات الموظف';
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'approve');

            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء الموافقة: '.$e->getMessage());
        }
    }

    /**
     * وضعُ «موافق عليه» وأثرُه — يُستدعى داخل معاملة (الاعتماد اليدوي والتلقائي).
     *
     * المنطق:
     * - مكافأة الموظف النقدية: سطرُ استحقاقٍ لمرّةٍ واحدة على قسيمة راتبه — لا صرفَ من الخزينة
     * - مكافأة الموظف «إجازة»: تُضاف الأيامُ إلى رصيد إجازاته (والطلبُ «تم التسليم»)
     * - نقدية العميل: صرف مباشر من الخزينة (لا يحتاج التزام)
     * - القسائم، الخصومات: القيد مؤجَّل حتى استخدام القسيمة
     * - الهدايا/المنتجات، أخرى: قيد التزام عند الموافقة، تسديد عند التسليم
     */
    private function markApproved(RewardRedemption $redemption): void
    {
        $redemption->update([
            'status' => RedemptionStatus::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        $reward = $redemption->reward;
        $fulfillment = app(EmployeeRewardFulfillment::class);

        if ($fulfillment->handles($redemption)) {
            $fulfillment->applyApproval($redemption);
        } elseif ($reward->reward_type->value === 'cash') {
            // النقدية للعميل: صرف مباشر من الخزينة
            $this->createAccountingEntry($redemption);
        } elseif ($reward->needsVoucherCode()) {
            // الخصومات والقسائم: لا قيد محاسبي عند الموافقة
            // القيد يُنشأ عند استخدام القسيمة فعلياً من صفحة التحقق (useVoucher)
            // لأن قيمة الخصم الفعلية غير معروفة حتى يتم تحديد مبلغ الفاتورة
            Log::info('موافقة على استبدال خصم/قسيمة - القيد المحاسبي مؤجل حتى استخدام القسيمة', [
                'redemption_id' => $redemption->id,
                'reward_type' => $reward->reward_type->value,
                'redemption_number' => $redemption->redemption_number,
            ]);
        } else {
            // باقي الأنواع (هدايا، منتجات، إلخ): إنشاء قيد التزام
            $this->createLiabilityEntry($redemption, $reward);
        }
    }

    /**
     * كفايةُ رصيد الخزينة وشرطُ السحب منها — قبل قيدٍ يُخرج منها نقداً.
     *
     * مخرجُ المكافآت كان المخرجَ النقديَّ الذي لا يسأل الخزينة شيئاً: يُقيَّد
     * عليها دائناً ولو لم يكفِ رصيدُها. والحارسُ المشترك يقفل صفَّ حسابها
     * (`accounts.id`) داخل المعاملة ويحتسب رصيدها من الدفتر.
     */
    private function guardTreasuryOutflow(int $treasuryAccountId, float $amount, RewardRedemption $redemption): void
    {
        TreasuryBalanceGuard::assertWithdrawable(
            $treasuryAccountId,
            $amount,
            'صرف مكافأة '.$redemption->redemption_number
        );
    }

    /**
     * تحديث حالة الطلب إلى قيد التجهيز
     */
    public function process(RewardRedemption $redemption)
    {
        if (! $redemption->isApproved()) {
            return redirect()->back()->with('error', 'لا يمكن تجهيز هذا الطلب. يجب أن يكون موافقاً عليه أولاً.');
        }

        $redemption->update([
            'status' => RedemptionStatus::PROCESSING->value,
            'processed_at' => now(),
            'processed_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'تم تحديث الحالة إلى قيد التجهيز');
    }

    /**
     * تحديث حالة الطلب إلى تم التسليم
     * عند التسليم: إذا كانت المكافأة منتج/هدية يتم خصم المخزون وإنشاء إذن صرف مخزني
     */
    public function deliver(RewardRedemption $redemption)
    {
        if (! in_array($redemption->status->value, [RedemptionStatus::APPROVED->value, RedemptionStatus::PROCESSING->value])) {
            return redirect()->back()->with('error', 'لا يمكن تسليم هذا الطلب بالحالة الحالية.');
        }

        // منع التسليم اليدوي للخصومات والقسائم - يجب استخدام صفحة التحقق من القسائم
        $rewardCheck = $redemption->reward;
        if ($rewardCheck && $rewardCheck->needsVoucherCode()) {
            return redirect()->back()->with('error', 'مكافآت الخصم والقسائم يتم تسليمها عند استخدام كود القسيمة من صفحة "التحقق من قسيمة". يجب تحديد الفاتورة ومبلغها أولاً لحساب الخصم الفعلي.');
        }

        // مكافأة الموظف النقدية تُسلَّم بصرف الراتب وحده — لا تسليمَ يدويّاً يُخرجها مرّةً ثانية
        $fulfillment = app(EmployeeRewardFulfillment::class);
        if ($fulfillment->isPayrollCash($redemption) && ! $fulfillment->paidFromTreasuryLegacy($redemption)) {
            return redirect()->back()->with('error', 'المكافأة النقدية للموظف تُصرف مع راتبه: تظهر استحقاقاً على قسيمته وتصير «تم التسليم» تلقائياً عند صرف الراتب.');
        }

        DB::beginTransaction();

        try {
            $redemption->update([
                'status' => RedemptionStatus::DELIVERED->value,
                'delivered_at' => now(),
            ]);

            // معالجة التسليم حسب نوع المكافأة
            $reward = $redemption->reward;

            // النقدية: لا تحتاج معالجة إضافية (تم الصرف عند الموافقة)
            if ($reward->reward_type->value === 'cash') {
                // لا شيء - تم الصرف مباشرة عند الموافقة
            }
            // هدية/منتج مع product_id: خصم المخزون ثم تسديد الالتزام
            elseif ($reward && in_array($reward->reward_type->value, ['gift', 'product']) && $reward->product_id) {
                // 1) خصم المخزون أولاً
                $this->deductInventory($redemption, $reward);
                $redemption->refresh();

                // 2) تسديد الالتزام من المخزون
                $this->settleLiability($redemption, $reward);
            }
            // باقي الأنواع (قسيمة، خصم، هدية بدون منتج، أخرى): تسديد الالتزام من الخزينة
            else {
                $this->settleLiability($redemption, $reward);
            }

            DB::commit();

            // إرسال إشعار التسليم
            try {
                $userName = '';
                if ($redemption->user_type->value === 'employee' && $redemption->employee) {
                    $userName = $redemption->employee->full_name;
                } elseif ($redemption->client) {
                    $userName = $redemption->client->full_name;
                }
                app(PointsNotificationService::class)->notifyRedemptionDelivered(
                    $redemption->user_type->value,
                    $redemption->employee_id,
                    $redemption->client_id,
                    [
                        'user_name' => $userName,
                        'reward_name' => $reward->name,
                        'redemption_number' => $redemption->redemption_number,
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('فشل إرسال إشعار التسليم: '.$e->getMessage());
            }

            return redirect()->back()->with('success', 'تم تسليم المكافأة بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'deliver');

            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء التسليم: '.$e->getMessage());
        }
    }

    /**
     * إلغاء طلب الاستبدال
     */
    public function cancel(Request $request, RewardRedemption $redemption)
    {
        if ($redemption->isDelivered()) {
            return redirect()->back()->with('error', 'لا يمكن إلغاء طلب تم تسليمه.');
        }

        if ($redemption->isCancelled()) {
            return redirect()->back()->with('error', 'هذا الطلب ملغي بالفعل.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $redemption->update([
                'status' => RedemptionStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->cancellation_reason,
            ]);

            // استرجاع النقاط
            $reward = $redemption->reward;
            $pointsToReturn = $reward->points_required * $redemption->quantity;

            if ($redemption->user_type->value === 'employee') {
                $latestTx = PointsTransaction::where('employee_id', $redemption->employee_id)
                    ->where('user_type', 'employee')
                    ->orderBy('id', 'desc')->first();
                $currentBalance = $latestTx ? $latestTx->balance_after : 0;
            } else {
                $latestTx = PointsTransaction::where('client_id', $redemption->client_id)
                    ->where('user_type', 'client')
                    ->orderBy('id', 'desc')->first();
                $currentBalance = $latestTx ? $latestTx->balance_after : 0;
            }

            // معاملة إرجاع النقاط
            PointsTransaction::create([
                'user_type' => $redemption->user_type->value,
                'employee_id' => $redemption->employee_id,
                'client_id' => $redemption->client_id,
                'action_type' => 'refund',
                'points' => $pointsToReturn,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance + $pointsToReturn,
                'description' => 'استرجاع نقاط - إلغاء استبدال: '.$reward->name.' #'.$redemption->redemption_number,
                'reference_type' => 'reward_redemption',
                'reference_id' => $redemption->id,
                'branch_id' => $redemption->branch_id,
                'created_by' => Auth::id(),
                'status' => 'completed',
                'points_month' => now()->month,
                'points_year' => now()->year,
            ]);

            // =====================================================
            // استرجاع النقاط إلى الدفعات (عكس FIFO)
            // =====================================================
            $this->restoreBatchPoints(
                $redemption->user_type->value,
                $redemption->employee_id,
                $redemption->client_id,
                $pointsToReturn
            );

            // إرجاع الكمية
            $reward->decrement('quantity_redeemed', $redemption->quantity);

            // إرجاع المخزون إذا كان تم تسليمه وكان منتج/هدية
            if ($redemption->delivered_at && in_array($reward->reward_type->value, ['gift', 'product']) && $reward->product_id) {
                $this->returnInventory($redemption, $reward);
            }

            // إزالة سطر المكافأة من قسيمة الراتب إن لم تُصرف بعد
            app(EmployeeRewardFulfillment::class)->withdraw($redemption);

            // عكس القيد المحاسبي إن وُجد
            $this->reverseAccountingEntry($redemption);

            DB::commit();

            // إرسال إشعار إلغاء الاستبدال
            try {
                $userName = $redemption->user_type->value === 'employee'
                    ? ($redemption->employee->full_name ?? 'موظف')
                    : ($redemption->client->full_name ?? $redemption->client->trade_name ?? 'عميل');

                $notificationService = new PointsNotificationService;
                $notificationService->notifyRedemptionCancelled(
                    $redemption->user_type->value,
                    $redemption->employee_id,
                    $redemption->client_id,
                    [
                        'user_name' => $userName,
                        'reward_name' => $reward->name,
                        'points_returned' => $pointsToReturn,
                        'redemption_number' => $redemption->redemption_number,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('فشل إرسال إشعار إلغاء الاستبدال: '.$e->getMessage());
            }

            return redirect()->back()->with('success', 'تم إلغاء طلب الاستبدال واسترجاع النقاط بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'cancel');

            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء الإلغاء: '.$e->getMessage());
        }
    }

    /**
     * إنشاء القيد المحاسبي لاستبدال المكافأة
     *
     * القاعدة المحاسبية:
     * - مكافآت نقدية: مدين: مصروفات مكافآت نقدية / دائن: الخزينة
     * - خصومات: مدين: مصروفات خصومات مكافآت / دائن: التزامات مكافآت معلقة
     * - قسائم: مدين: مصروفات قسائم / دائن: قسائم مكافآت صادرة
     * - هدايا/منتجات: مدين: مصروفات هدايا / دائن: المخزون
     * - أخرى: مدين: مصروفات مكافآت أخرى / دائن: التزامات مكافآت معلقة
     */
    private function createAccountingEntry(RewardRedemption $redemption): void
    {
        $reward = $redemption->reward;
        $amount = $redemption->reward_value;

        // لا ننشئ قيداً إذا كانت القيمة صفراً
        if (! $amount || $amount <= 0) {
            return;
        }

        // تحديد الحسابات حسب نوع المكافأة
        $debitAccountId = null;
        $creditAccountId = null;
        $description = '';

        // حساب سعر الوحدة
        $unitPrice = $reward->reward_value;
        $quantity = $redemption->quantity;

        // صيغة الكمية والسعر للبيان
        $qtyPriceInfo = ' - الكمية: '.$quantity.' × '.number_format($unitPrice, 2).' ريال';
        if ($quantity > 1) {
            $qtyPriceInfo .= ' = '.number_format($amount, 2).' ريال';
        }

        switch ($reward->reward_type->value) {
            case 'cash':
                $debitAccountId = $this->settingsAccountId('rewards_cash_expense');
                // استخدام الخزينة المحددة من طلب الاستبدال أو الخزينة الرئيسية كافتراضي
                $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                $description = 'مكافأة نقدية - '.$reward->name.$qtyPriceInfo;
                break;

            case 'discount_percentage':
            case 'discount_amount':
                $debitAccountId = $this->settingsAccountId('rewards_discount_expense');
                $creditAccountId = $this->settingsAccountId('rewards_pending_liability');
                $description = 'خصم مكافأة - '.$reward->name.$qtyPriceInfo;
                break;

            case 'voucher':
                $debitAccountId = $this->settingsAccountId('rewards_voucher_expense');
                $creditAccountId = $this->settingsAccountId('rewards_voucher_liability');
                $description = 'قسيمة مكافأة - '.$reward->name.$qtyPriceInfo;
                break;

            case 'gift':
            case 'product':
                $debitAccountId = $this->settingsAccountId('rewards_gift_product_expense');
                $description = 'هدية/منتج مكافأة - '.$reward->name.$qtyPriceInfo;

                // === حساب التكلفة الفعلية للمنتج (مثل تكلفة المبيعات في الفواتير) ===
                if ($reward->product_id) {
                    $product = $reward->product;
                    // استخدام المستودع المحدد من طلب الاستبدال أو من metadata
                    $storeHouseId = $redemption->store_house_id ?? ($redemption->metadata['store_house_id'] ?? null);

                    // 1) البحث عن حساب مخزون المستودع المحدد
                    $inventoryAccountId = null;
                    if ($storeHouseId) {
                        $storeInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                        if ($storeInventoryAccount) {
                            $inventoryAccountId = $storeInventoryAccount->id;
                        }
                    }

                    // 2) إن لم يوجد، نبحث عن المستودع الرئيسي
                    if (! $inventoryAccountId) {
                        $mainStoreHouse = StoreHouse::where('major', 1)->first();
                        if ($mainStoreHouse) {
                            $storeInventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                            if ($storeInventoryAccount) {
                                $inventoryAccountId = $storeInventoryAccount->id;
                            }
                        }
                    }

                    // 3) إن لم يوجد، نستخدم حساب المخزون العام
                    $creditAccountId = $inventoryAccountId ?? $this->settingsAccountId('inventory');

                    // === حساب سعر التكلفة الفعلي ===
                    $purchaseUnitCost = null;

                    // أولاً: من ProductDetails (unit_price)
                    if ($storeHouseId) {
                        $productDetails = ProductDetails::where('product_id', $reward->product_id)
                            ->where('store_house_id', $storeHouseId)
                            ->first();
                        if ($productDetails && isset($productDetails->unit_price) && $productDetails->unit_price > 0) {
                            $purchaseUnitCost = (float) $productDetails->unit_price;
                        }
                    }

                    // ثانياً: من purchase_price في المنتج
                    if ($purchaseUnitCost === null && $product && $product->purchase_price > 0) {
                        $purchaseUnitCost = (float) $product->purchase_price;
                    }

                    // ثالثاً: متوسط التكلفة من أذونات المستودع
                    if ($purchaseUnitCost === null) {
                        try {
                            $avgCost = WarehousePermitsProducts::where('product_id', $reward->product_id)->avg('unit_price');
                            if ($avgCost && $avgCost > 0) {
                                $purchaseUnitCost = (float) $avgCost;
                            }
                        } catch (\Exception $e) {
                            Log::warning('فشل حساب متوسط تكلفة المنتج للمكافأة', [
                                'product_id' => $reward->product_id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    // إذا وُجد سعر تكلفة فعلي، نستخدمه بدلاً من reward_value
                    if ($purchaseUnitCost !== null && $purchaseUnitCost > 0) {
                        $amount = $purchaseUnitCost * $redemption->quantity;
                        Log::info('تم استخدام سعر التكلفة الفعلي لقيد مكافأة هدية/منتج', [
                            'redemption_id' => $redemption->id,
                            'product_id' => $reward->product_id,
                            'unit_cost' => $purchaseUnitCost,
                            'quantity' => $redemption->quantity,
                            'total_cost' => $amount,
                            'original_reward_value' => $redemption->reward_value,
                        ]);
                    } else {
                        Log::warning('لم يتم العثور على سعر تكلفة للمنتج، تم استخدام reward_value', [
                            'redemption_id' => $redemption->id,
                            'product_id' => $reward->product_id,
                        ]);
                    }
                } else {
                    // هدية بدون منتج مرتبط - تُعامل كمصروف نقدي من الخزينة
                    $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                }
                break;

            default:
                $debitAccountId = $this->settingsAccountId('rewards_other_expense');
                $creditAccountId = $this->settingsAccountId('rewards_pending_liability');
                $description = 'مكافأة - '.$reward->name.$qtyPriceInfo;
                break;
        }

        // الحصول على اسم المستفيد
        $beneficiary = '';
        if ($redemption->user_type->value === 'employee' && $redemption->employee) {
            $beneficiary = $redemption->employee->full_name;
        } elseif ($redemption->client) {
            $beneficiary = $redemption->client->full_name;
        }

        // الخزينة الدائنة (نقدية العميل، هدية بلا منتج): كفايةُ رصيدها قبل القيد
        if ($creditAccountId && ($reward->reward_type->value === 'cash'
            || (in_array($reward->reward_type->value, ['gift', 'product']) && ! $reward->product_id))) {
            $this->guardTreasuryOutflow((int) $creditAccountId, (float) $amount, $redemption);
        }

        // إنشاء قيد اليومية (نفس حقول InvoicesController)
        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::REWARD_REDEMPTION),
            'date' => now(),
            'description' => $description.' - المستفيد: '.$beneficiary.' - #'.$redemption->redemption_number,
            'status' => 1,
            'currency' => 'SAR',
            'client_id' => $redemption->client_id,
            'reference_type' => ReferenceType::REWARD_REDEMPTION->value,
            'reference_id' => $redemption->id,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id() ?? 1,
            'approved_by_employee' => Auth::id() ?? 1,
            'branch_id' => $redemption->branch_id,
            'is_posted' => 1,
        ]);

        // طرف مدين - المصروفات
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $debitAccountId,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // طرف دائن - المقابل
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $creditAccountId,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // ربط القيد بالاستبدال في metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'journal_entry_id' => $journalEntry->id,
            ]),
        ]);
    }

    /**
     * إنشاء قيد الالتزام عند الموافقة
     * المنطق: مدين: المصروف حسب النوع ← دائن: الالتزام حسب النوع
     *
     * - قسيمة: مدين: مصروفات قسائم (3378) ← دائن: التزام قسائم (3383)
     * - خصم: مدين: مصروفات خصومات (3377) ← دائن: التزام معلق (3382)
     * - هدية/منتج: مدين: مصروفات هدايا (3379) ← دائن: التزام معلق (3382)
     * - أخرى: مدين: مصروفات أخرى (3380) ← دائن: التزام معلق (3382)
     */
    private function createLiabilityEntry(RewardRedemption $redemption, Reward $reward): void
    {
        $amount = $redemption->reward_value;
        if (! $amount || $amount <= 0) {
            return;
        }

        // تحديد الحسابات حسب نوع المكافأة
        $expenseAccountId = null;
        $liabilityAccountId = null;
        $typeLabel = '';
        $unitPrice = $reward->reward_value;
        $quantity = $redemption->quantity;

        switch ($reward->reward_type->value) {
            case 'voucher':
                $expenseAccountId = $this->settingsAccountId('rewards_voucher_expense');
                $liabilityAccountId = $this->settingsAccountId('rewards_voucher_liability');
                $typeLabel = 'قسيمة مكافأة';
                break;

            case 'discount_percentage':
            case 'discount_amount':
                $expenseAccountId = $this->settingsAccountId('rewards_discount_expense');
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');
                $typeLabel = 'خصم مكافأة';
                break;

            case 'gift':
            case 'product':
                $expenseAccountId = $this->settingsAccountId('rewards_gift_product_expense');
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');
                $typeLabel = 'هدية/منتج مكافأة';

                // === حساب سعر التكلفة الفعلي عند الارتباط بمنتج ===
                if ($reward->product_id) {
                    $purchaseUnitCost = null;
                    $product = $reward->product;
                    $storeHouseId = $redemption->store_house_id;

                    // أولاً: من ProductDetails (unit_price) في المستودع المحدد
                    if ($storeHouseId) {
                        $productDetails = ProductDetails::where('product_id', $reward->product_id)
                            ->where('store_house_id', $storeHouseId)
                            ->first();
                        if ($productDetails && isset($productDetails->unit_price) && $productDetails->unit_price > 0) {
                            $purchaseUnitCost = (float) $productDetails->unit_price;
                        }
                    }

                    // ثانياً: من purchase_price في المنتج
                    if ($purchaseUnitCost === null && $product && $product->purchase_price > 0) {
                        $purchaseUnitCost = (float) $product->purchase_price;
                    }

                    // ثالثاً: متوسط التكلفة من أذونات المستودع
                    if ($purchaseUnitCost === null) {
                        try {
                            $avgCost = WarehousePermitsProducts::where('product_id', $reward->product_id)->avg('unit_price');
                            if ($avgCost && $avgCost > 0) {
                                $purchaseUnitCost = (float) $avgCost;
                            }
                        } catch (\Exception $e) {
                            Log::warning('فشل حساب متوسط تكلفة المنتج لقيد الالتزام', [
                                'product_id' => $reward->product_id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    // استخدام سعر التكلفة الفعلي بدلاً من reward_value
                    if ($purchaseUnitCost !== null && $purchaseUnitCost > 0) {
                        $unitPrice = $purchaseUnitCost;
                        $amount = $purchaseUnitCost * $quantity;
                        Log::info('تم استخدام سعر التكلفة الفعلي لقيد التزام هدية/منتج', [
                            'redemption_id' => $redemption->id,
                            'product_id' => $reward->product_id,
                            'unit_cost' => $purchaseUnitCost,
                            'quantity' => $quantity,
                            'total_cost' => $amount,
                            'original_reward_value' => $redemption->reward_value,
                        ]);
                    }
                }
                break;

            default:
                $expenseAccountId = $this->settingsAccountId('rewards_other_expense');
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');
                $typeLabel = 'مكافأة';
                break;
        }

        // صيغة الكمية والسعر للبيان (بعد تحديد السعر الصحيح)
        $qtyPriceInfo = ' - الكمية: '.$quantity.' × '.number_format($unitPrice, 2).' ريال';
        if ($quantity > 1) {
            $qtyPriceInfo .= ' = '.number_format($amount, 2).' ريال';
        }

        // الحصول على اسم المستفيد
        $beneficiary = '';
        if ($redemption->user_type->value === 'employee' && $redemption->employee) {
            $beneficiary = $redemption->employee->full_name;
        } elseif ($redemption->client) {
            $beneficiary = $redemption->client->full_name;
        }

        $description = 'التزام '.$typeLabel.' - '.$reward->name.$qtyPriceInfo;

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::REWARD_REDEMPTION),
            'date' => now(),
            'description' => $description.' - المستفيد: '.$beneficiary.' - #'.$redemption->redemption_number,
            'status' => 1,
            'currency' => 'SAR',
            'client_id' => $redemption->client_id,
            'reference_type' => ReferenceType::REWARD_REDEMPTION->value,
            'reference_id' => $redemption->id,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id() ?? 1,
            'approved_by_employee' => Auth::id() ?? 1,
            'branch_id' => $redemption->branch_id,
            'is_posted' => 1,
        ]);

        // طرف مدين - المصروف
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $expenseAccountId,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // طرف دائن - الالتزام
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $liabilityAccountId,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // ربط القيد بالاستبدال في metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'liability_journal_entry_id' => $journalEntry->id,
            ]),
        ]);
    }

    /**
     * تسديد الالتزام عند التسليم
     * المنطق: مدين: الالتزام حسب النوع ← دائن: الخزينة أو المخزون
     *
     * - قسيمة: مدين: التزام قسائم (3383) ← دائن: الخزينة
     * - خصم: مدين: التزام معلق (3382) ← دائن: الخزينة
     * - هدية/منتج مع product_id: مدين: التزام معلق (3382) ← دائن: المخزون
     * - هدية بدون product_id: مدين: التزام معلق (3382) ← دائن: الخزينة
     * - أخرى: مدين: التزام معلق (3382) ← دائن: الخزينة
     */
    private function settleLiability(RewardRedemption $redemption, Reward $reward): void
    {
        $amount = $redemption->reward_value;
        if (! $amount || $amount <= 0) {
            return;
        }

        // حساب سعر الوحدة والكمية
        $unitPrice = $reward->reward_value;
        $quantity = $redemption->quantity;
        $qtyPriceInfo = ' - الكمية: '.$quantity.' × '.number_format($unitPrice, 2).' ريال';
        if ($quantity > 1) {
            $qtyPriceInfo .= ' = '.number_format($amount, 2).' ريال';
        }

        // تحديد حساب الالتزام وحساب التسديد حسب نوع المكافأة
        $liabilityAccountId = null;
        $creditAccountId = null;
        $typeLabel = '';

        switch ($reward->reward_type->value) {
            case 'voucher':
                $liabilityAccountId = $this->settingsAccountId('rewards_voucher_liability');
                $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                $typeLabel = 'قسيمة مكافأة';
                break;

            case 'discount_percentage':
            case 'discount_amount':
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');
                $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                $typeLabel = 'خصم مكافأة';
                break;

            case 'gift':
            case 'product':
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');

                // إذا كان المنتج مرتبط → تسديد من المخزون (حساب مخزون المستودع)
                if ($reward->product_id) {
                    $storeHouseId = $redemption->store_house_id ?? ($redemption->metadata['store_house_id'] ?? null);

                    // البحث عن حساب مخزون المستودع
                    $inventoryAccountId = null;
                    if ($storeHouseId) {
                        $storeInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                        if ($storeInventoryAccount) {
                            $inventoryAccountId = $storeInventoryAccount->id;
                        }
                    }

                    // إن لم يوجد، نبحث عن المستودع الرئيسي
                    if (! $inventoryAccountId) {
                        $mainStoreHouse = StoreHouse::where('major', 1)->first();
                        if ($mainStoreHouse) {
                            $storeInventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                            if ($storeInventoryAccount) {
                                $inventoryAccountId = $storeInventoryAccount->id;
                            }
                        }
                    }

                    $creditAccountId = $inventoryAccountId ?? $this->settingsAccountId('inventory');

                    // === حساب سعر التكلفة الفعلي ===
                    $purchaseUnitCost = null;
                    $product = $reward->product;

                    // أولاً: من ProductDetails (unit_price)
                    if ($storeHouseId) {
                        $productDetails = ProductDetails::where('product_id', $reward->product_id)
                            ->where('store_house_id', $storeHouseId)
                            ->first();
                        if ($productDetails && isset($productDetails->unit_price) && $productDetails->unit_price > 0) {
                            $purchaseUnitCost = (float) $productDetails->unit_price;
                        }
                    }

                    // ثانياً: من purchase_price في المنتج
                    if ($purchaseUnitCost === null && $product && $product->purchase_price > 0) {
                        $purchaseUnitCost = (float) $product->purchase_price;
                    }

                    // ثالثاً: متوسط التكلفة من أذونات المستودع
                    if ($purchaseUnitCost === null) {
                        try {
                            $avgCost = WarehousePermitsProducts::where('product_id', $reward->product_id)->avg('unit_price');
                            if ($avgCost && $avgCost > 0) {
                                $purchaseUnitCost = (float) $avgCost;
                            }
                        } catch (\Exception $e) {
                            Log::warning('فشل حساب متوسط تكلفة المنتج للمكافأة', [
                                'product_id' => $reward->product_id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    // استخدام سعر التكلفة الفعلي
                    if ($purchaseUnitCost !== null && $purchaseUnitCost > 0) {
                        $amount = $purchaseUnitCost * $redemption->quantity;
                    }
                } else {
                    // هدية بدون منتج → تسديد من الخزينة
                    $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                }
                $typeLabel = 'هدية/منتج مكافأة';
                break;

            default:
                $liabilityAccountId = $this->settingsAccountId('rewards_pending_liability');
                $creditAccountId = $this->rewardTreasuryAccountId($redemption);
                $typeLabel = 'مكافأة';
                break;
        }

        // الحصول على اسم المستفيد
        $beneficiary = '';
        if ($redemption->user_type->value === 'employee' && $redemption->employee) {
            $beneficiary = $redemption->employee->full_name;
        } elseif ($redemption->client) {
            $beneficiary = $redemption->client->full_name;
        }

        $description = 'تسديد التزام '.$typeLabel.' - '.$reward->name.$qtyPriceInfo;

        // التسديدُ من الخزينة (لا من المخزون): كفايةُ رصيدها قبل القيد
        if ($creditAccountId && ! ($reward->product_id && in_array($reward->reward_type->value, ['gift', 'product']))) {
            $this->guardTreasuryOutflow((int) $creditAccountId, (float) $amount, $redemption);
        }

        // إنشاء قيد تسديد الالتزام
        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::REWARD_REDEMPTION),
            'date' => now(),
            'description' => $description.' - المستفيد: '.$beneficiary.' - #'.$redemption->redemption_number,
            'status' => 1,
            'currency' => 'SAR',
            'client_id' => $redemption->client_id,
            'reference_type' => ReferenceType::REWARD_REDEMPTION->value,
            'reference_id' => $redemption->id,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id() ?? 1,
            'approved_by_employee' => Auth::id() ?? 1,
            'branch_id' => $redemption->branch_id,
            'is_posted' => 1,
        ]);

        // مدين - تسديد الالتزام
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $liabilityAccountId,
            'description' => 'تسديد التزام - '.$reward->name.$qtyPriceInfo,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // دائن - من الخزينة أو المخزون
        $creditDescription = ($reward->product_id && in_array($reward->reward_type->value, ['gift', 'product']))
            ? 'صرف من المخزون - '.$reward->name.$qtyPriceInfo
            : 'صرف من الخزينة - '.$reward->name.$qtyPriceInfo;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $creditAccountId,
            'description' => $creditDescription,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // ربط القيد بالاستبدال في metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'settlement_journal_entry_id' => $journalEntry->id,
            ]),
        ]);
    }

    /**
     * عكس القيد المحاسبي عند إلغاء الاستبدال
     */
    private function reverseAccountingEntry(RewardRedemption $redemption): void
    {
        $metadata = $redemption->metadata ?? [];

        // جمع كل معرفات القيود التي تحتاج عكس
        $journalEntryIds = array_filter([
            $metadata['journal_entry_id'] ?? null,               // قيد النقدية المباشر
            $metadata['liability_journal_entry_id'] ?? null,     // قيد الالتزام عند الموافقة
            $metadata['settlement_journal_entry_id'] ?? null,    // قيد تسديد الالتزام عند التسليم
            $metadata['voucher_usage_journal_entry_id'] ?? null, // قيد استخدام القسيمة/الخصم
            // للتوافق مع القيود القديمة
            $metadata['voucher_liability_journal_entry_id'] ?? null,
            $metadata['voucher_settlement_journal_entry_id'] ?? null,
        ]);

        if (empty($journalEntryIds)) {
            return;
        }

        $reverseEntryIds = [];

        foreach ($journalEntryIds as $journalEntryId) {
            $originalEntry = JournalEntry::with('details')->find($journalEntryId);
            if (! $originalEntry) {
                continue;
            }

            // إنشاء قيد عكسي (نفس حقول القيد الأصلي)
            $reverseEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::REWARD_REDEMPTION),
                'date' => now(),
                'description' => 'عكس قيد استبدال مكافأة - إلغاء #'.$redemption->redemption_number,
                'status' => 1,
                'currency' => 'SAR',
                'client_id' => $redemption->client_id,
                'reference_type' => ReferenceType::REWARD_REDEMPTION->value,
                'reference_id' => $redemption->id,
                'linking_type' => LinkingType::SINGLE,
                'created_by_employee' => Auth::id() ?? 1,
                'approved_by_employee' => Auth::id() ?? 1,
                'branch_id' => $redemption->branch_id,
                'is_posted' => 1,
            ]);

            // عكس تفاصيل القيد (تبديل المدين والدائن)
            foreach ($originalEntry->details as $detail) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $reverseEntry->id,
                    'account_id' => $detail->account_id,
                    'description' => 'عكس: '.$detail->description,
                    'debit' => $detail->credit,
                    'credit' => $detail->debit,
                    'is_debit' => $detail->credit > 0,
                ]);
            }

            $reverseEntryIds[] = $reverseEntry->id;
        }

        // تحديث metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'reverse_journal_entry_ids' => $reverseEntryIds,
            ]),
        ]);
    }

    /**
     * تصدير طلبات الاستبدال
     */
    public function export(Request $request)
    {
        // يمكن إضافة التصدير لاحقاً
        return redirect()->back()->with('info', 'ميزة التصدير قيد التطوير');
    }

    /**
     * خصم المخزون عند تسليم مكافأة منتج/هدية
     * يتبع نفس آلية InvoicesController:
     * 1. جلب المخزن الافتراضي للموظف المسجل
     * 2. خصم الكمية من ProductDetails
     * 3. إنشاء WarehousePermits (إذن صرف مخزني)
     * 4. إنشاء WarehousePermitsProducts (تفاصيل الإذن)
     */
    private function deductInventory(RewardRedemption $redemption, Reward $reward): void
    {
        $product = $reward->product;
        if (! $product) {
            throw new \Exception('المنتج المرتبط بالمكافأة غير موجود.');
        }

        // جلب مصدر الإذن المخزني
        $permissionSource = PermissionSource::find(PermissionSources::REWARD_REDEMPTION);
        if (! $permissionSource) {
            // إنشاء مصدر الإذن إذا لم يكن موجوداً
            $permissionSource = PermissionSource::create([
                'id' => PermissionSources::REWARD_REDEMPTION,
                'name' => 'صرف مكافأة',
                'category' => 'صرف',
            ]);
        }

        // جلب المستودع من طلب الاستبدال أولاً
        $storeHouseId = $redemption->store_house_id;

        // إذا لم يكن محدداً في الطلب، جلب المخزن الافتراضي للموظف المسجل
        if (! $storeHouseId) {
            $user = Auth::user();
            if ($user && $user->employee_id) {
                $defaultWarehouse = DefaultWarehouses::where('employee_id', $user->employee_id)->first();
                if ($defaultWarehouse) {
                    $storeHouseId = $defaultWarehouse->storehouse_id;
                }
            }
        }

        // إذا لم يوجد مخزن افتراضي، ابحث عن أي مخزن يحتوي على المنتج
        if (! $storeHouseId) {
            $productDetail = ProductDetails::where('product_id', $product->id)
                ->where('quantity', '>=', $redemption->quantity)
                ->first();

            if ($productDetail) {
                $storeHouseId = $productDetail->store_house_id;
            } else {
                throw new \Exception('لا يوجد مخزن يحتوي على كمية كافية من المنتج: '.$product->name);
            }
        }

        // جلب تفاصيل المنتج في المخزن
        $productDetails = ProductDetails::where('product_id', $product->id)
            ->where('store_house_id', $storeHouseId)
            ->first();

        if (! $productDetails) {
            $productDetails = ProductDetails::create([
                'store_house_id' => $storeHouseId,
                'product_id' => $product->id,
                'quantity' => 0,
            ]);
        }

        $quantityToDeduct = $redemption->quantity;

        // التحقق من الكمية المتاحة
        if ($product->type == 'products' || $product->type == 'compiled') {
            if ((float) $quantityToDeduct > (float) $productDetails->quantity) {
                throw new \Exception(sprintf(
                    'الكمية المطلوبة (%s) غير متاحة في المخزون. الكمية المتاحة: %s',
                    $quantityToDeduct,
                    $productDetails->quantity
                ));
            }
        }

        // حفظ المخزون قبل وبعد
        $stockBefore = $productDetails->quantity;
        $stockAfter = $stockBefore - $quantityToDeduct;

        // خصم الكمية من المخزون
        $productDetails->decrement('quantity', $quantityToDeduct);

        // === حساب سعر التكلفة الفعلي (مثل تكلفة المبيعات في InvoicesController) ===
        $purchaseUnitCost = null;

        // أولاً: من ProductDetails (unit_price) في نفس المستودع
        if (isset($productDetails->unit_price) && $productDetails->unit_price > 0) {
            $purchaseUnitCost = (float) $productDetails->unit_price;
        }

        // ثانياً: من purchase_price في المنتج
        if ($purchaseUnitCost === null && $product->purchase_price > 0) {
            $purchaseUnitCost = (float) $product->purchase_price;
        }

        // ثالثاً: متوسط التكلفة من أذونات المستودع
        if ($purchaseUnitCost === null) {
            try {
                $avgCost = WarehousePermitsProducts::where('product_id', $product->id)->avg('unit_price');
                if ($avgCost && $avgCost > 0) {
                    $purchaseUnitCost = (float) $avgCost;
                }
            } catch (\Exception $e) {
                Log::warning('فشل حساب متوسط تكلفة المنتج لإذن المكافأة', [
                    'product_id' => $product->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // إذا لم يُوجد سعر تكلفة، نستخدم صفراً
        if ($purchaseUnitCost === null) {
            $purchaseUnitCost = 0.0;
            Log::warning('لم يتم العثور على سعر تكلفة للمنتج في إذن المكافأة، تم التعيين إلى صفر', [
                'product_id' => $product->id,
            ]);
        }

        $costTotal = $purchaseUnitCost * $quantityToDeduct;

        // إنشاء إذن صرف مخزني (بسعر التكلفة الفعلي مثل المبيعات)
        $warehousePermit = new WarehousePermits;
        $warehousePermit->permission_source_id = $permissionSource->id;
        $warehousePermit->permission_date = now();
        $warehousePermit->number = $redemption->id;
        $warehousePermit->grand_total = $costTotal;
        $warehousePermit->store_houses_id = $storeHouseId;
        $warehousePermit->client_id = $redemption->client_id;
        $warehousePermit->reference_type = 'RewardRedemption';
        $warehousePermit->reference_id = $redemption->id;
        $warehousePermit->created_by = Auth::id();
        $warehousePermit->save();

        // تسجيل تفاصيل المنتج في الإذن (بسعر التكلفة)
        WarehousePermitsProducts::create([
            'quantity' => $quantityToDeduct,
            'total' => $costTotal,
            'unit_price' => $purchaseUnitCost,
            'product_id' => $product->id,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'warehouse_permits_id' => $warehousePermit->id,
        ]);

        Log::info('تم إنشاء إذن صرف مخزني لمكافأة', [
            'redemption_id' => $redemption->id,
            'product_id' => $product->id,
            'quantity' => $quantityToDeduct,
            'unit_cost' => $purchaseUnitCost,
            'total_cost' => $costTotal,
            'store_house_id' => $storeHouseId,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
        ]);

        // حفظ معرفات الإذن وسعر التكلفة في metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'warehouse_permit_id' => $warehousePermit->id,
                'store_house_id' => $storeHouseId,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $purchaseUnitCost,
                'total_cost' => $costTotal,
            ]),
        ]);
    }

    /**
     * إرجاع المخزون عند إلغاء استبدال منتج/هدية تم تسليمها
     * 1. إضافة الكمية مرة أخرى إلى ProductDetails
     * 2. إنشاء WarehousePermits (إذن إضافة مخزني)
     * 3. إنشاء WarehousePermitsProducts
     */
    private function returnInventory(RewardRedemption $redemption, Reward $reward): void
    {
        $product = $reward->product;
        if (! $product) {
            return;
        }

        $metadata = $redemption->metadata ?? [];
        $storeHouseId = $metadata['store_house_id'] ?? null;

        if (! $storeHouseId) {
            // محاولة إيجاد المخزن من الإذن السابق
            $prevPermit = WarehousePermits::where('reference_type', 'RewardRedemption')
                ->where('reference_id', $redemption->id)
                ->where('permission_source_id', PermissionSources::REWARD_REDEMPTION)
                ->latest()
                ->first();

            if ($prevPermit) {
                $storeHouseId = $prevPermit->store_houses_id;
            } else {
                // أول مخزن يحتوي المنتج
                $pd = ProductDetails::where('product_id', $product->id)->first();
                $storeHouseId = $pd ? $pd->store_house_id : null;
            }
        }

        if (! $storeHouseId) {
            return;
        }

        $productDetails = ProductDetails::where('product_id', $product->id)
            ->where('store_house_id', $storeHouseId)
            ->first();

        if (! $productDetails) {
            $productDetails = ProductDetails::create([
                'store_house_id' => $storeHouseId,
                'product_id' => $product->id,
                'quantity' => 0,
            ]);
        }

        $quantityToReturn = $redemption->quantity;
        $stockBefore = $productDetails->quantity;
        $stockAfter = $stockBefore + $quantityToReturn;

        // إضافة الكمية مرة أخرى
        $productDetails->increment('quantity', $quantityToReturn);

        // جلب سعر التكلفة من إذن الصرف الأصلي
        $originalPermitId = $metadata['warehouse_permit_id'] ?? null;
        $originalUnitCost = 0;
        if ($originalPermitId) {
            $originalPermitProduct = WarehousePermitsProducts::where('warehouse_permits_id', $originalPermitId)
                ->where('product_id', $product->id)
                ->first();
            if ($originalPermitProduct) {
                $originalUnitCost = (float) $originalPermitProduct->unit_price;
            }
        }
        $costTotal = $originalUnitCost * $quantityToReturn;

        // إنشاء إذن إضافة مخزني (وارد) بنفس سعر التكلفة
        $warehousePermit = new WarehousePermits;
        $warehousePermit->permission_source_id = PermissionSources::WAREHOUSE_ADD;
        $warehousePermit->permission_date = now();
        $warehousePermit->number = $redemption->id;
        $warehousePermit->grand_total = $costTotal;
        $warehousePermit->store_houses_id = $storeHouseId;
        $warehousePermit->client_id = $redemption->client_id;
        $warehousePermit->reference_type = 'RewardRedemption';
        $warehousePermit->reference_id = $redemption->id;
        $warehousePermit->details = 'إرجاع مخزون - إلغاء استبدال مكافأة #'.$redemption->redemption_number;
        $warehousePermit->created_by = Auth::id();
        $warehousePermit->save();

        // تسجيل تفاصيل المنتج بسعر التكلفة
        WarehousePermitsProducts::create([
            'quantity' => $quantityToReturn,
            'total' => $costTotal,
            'unit_price' => $originalUnitCost,
            'product_id' => $product->id,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'warehouse_permits_id' => $warehousePermit->id,
        ]);

        // تحديث metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'return_warehouse_permit_id' => $warehousePermit->id,
                'return_stock_before' => $stockBefore,
                'return_stock_after' => $stockAfter,
            ]),
        ]);
    }

    /**
     * إنشاء القيد المحاسبي عند استخدام القسيمة/الخصم فعلياً
     * يُنشأ بمبلغ الخصم الفعلي بعد تحديد مبلغ الفاتورة
     *
     * القاعدة المحاسبية:
     * - خصم نسبة: مدين: مصروفات خصومات (REWARDS_DISCOUNT_EXPENSE) ← دائن: الخزينة
     * - خصم مبلغ: مدين: مصروفات خصومات (REWARDS_DISCOUNT_EXPENSE) ← دائن: الخزينة
     * - قسيمة: مدين: مصروفات قسائم (REWARDS_VOUCHER_EXPENSE) ← دائن: الخزينة
     */
    /**
     * خزينةُ صرف المكافأة: المختارةُ في الطلب، وإلا «الخزينة الرئيسية» من
     * الحسابات المحاسبية. لا ثابتَ احتياطيّاً — غيرُ المربوط يُرفض برسالة.
     */
    private function rewardTreasuryAccountId(RewardRedemption $redemption): int
    {
        $id = $redemption->treasury_account_id
            ?: \App\Support\AccountingSettings::id('main_treasury');

        if (! $id) {
            throw new \RuntimeException('لا خزينةَ للصرف: اختر خزينةً في الطلب، أو اربط «الخزينة الرئيسية» في إعدادات الحسابات المحاسبية.');
        }

        return (int) $id;
    }

    private function createVoucherUsageEntry(RewardRedemption $redemption, Reward $reward, float $discountAmount, float $invoiceAmount): void
    {
        if ($discountAmount <= 0) {
            return;
        }

        // تحديد حساب المصروف حسب نوع المكافأة
        $debitAccountId = null;
        $typeLabel = '';

        switch ($reward->reward_type->value) {
            case 'discount_percentage':
                $debitAccountId = $this->settingsAccountId('rewards_discount_expense');
                $typeLabel = 'خصم نسبة '.$reward->reward_value.'%';
                break;

            case 'discount_amount':
                $debitAccountId = $this->settingsAccountId('rewards_discount_expense');
                $typeLabel = 'خصم مبلغ ثابت';
                break;

            case 'voucher':
                $debitAccountId = $this->settingsAccountId('rewards_voucher_expense');
                $typeLabel = 'قسيمة مكافأة';
                break;

            default:
                $debitAccountId = $this->settingsAccountId('rewards_other_expense');
                $typeLabel = 'مكافأة';
                break;
        }

        // الخزينة: المحددة في طلب الاستبدال أو الرئيسية
        $creditAccountId = $this->rewardTreasuryAccountId($redemption);

        // اسم المستفيد
        $beneficiary = '';
        if ($redemption->user_type->value === 'employee' && $redemption->employee) {
            $beneficiary = $redemption->employee->full_name;
        } elseif ($redemption->client) {
            $beneficiary = $redemption->client->full_name;
        }

        $description = 'استخدام '.$typeLabel.' - '.$reward->name
            .' - مبلغ الفاتورة: '.number_format($invoiceAmount, 2).' ريال'
            .' - قيمة الخصم: '.number_format($discountAmount, 2).' ريال';

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::REWARD_REDEMPTION),
            'date' => now(),
            'description' => $description.' - المستفيد: '.$beneficiary.' - #'.$redemption->redemption_number,
            'status' => 1,
            'currency' => 'SAR',
            'client_id' => $redemption->client_id,
            'reference_type' => ReferenceType::REWARD_REDEMPTION->value,
            'reference_id' => $redemption->id,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id() ?? 1,
            'approved_by_employee' => Auth::id() ?? 1,
            'branch_id' => $redemption->branch_id,
            'is_posted' => 1,
        ]);

        // طرف مدين - المصروفات
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $debitAccountId,
            'description' => $description,
            'debit' => $discountAmount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // طرف دائن - الخزينة
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $creditAccountId,
            'description' => $description,
            'debit' => 0,
            'credit' => $discountAmount,
            'is_debit' => false,
        ]);

        // ربط القيد بالاستبدال في metadata
        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'voucher_usage_journal_entry_id' => $journalEntry->id,
            ]),
        ]);

        Log::info('تم إنشاء قيد محاسبي لاستخدام قسيمة/خصم', [
            'redemption_id' => $redemption->id,
            'redemption_number' => $redemption->redemption_number,
            'reward_type' => $reward->reward_type->value,
            'invoice_amount' => $invoiceAmount,
            'discount_amount' => $discountAmount,
            'journal_entry_id' => $journalEntry->id,
        ]);
    }

    // =====================================================
    // نظام التحقق من القسائم واستخدامها
    // =====================================================

    /**
     * صفحة التحقق من القسيمة
     */
    public function verifyVoucherPage()
    {
        return view('ecosystem::points.redemptions.verify-voucher');
    }

    /**
     * التحقق من كود القسيمة (AJAX)
     */
    public function verifyVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string|max:50',
        ]);

        $redemption = RewardRedemption::with(['reward', 'employee', 'client'])
            ->where('voucher_code', $request->voucher_code)
            ->first();

        if (! $redemption) {
            return response()->json([
                'success' => false,
                'message' => 'كود القسيمة غير صالح أو غير موجود',
            ], 404);
        }

        $reward = $redemption->reward;

        // التحقق من حالة القسيمة
        if ($redemption->voucher_used) {
            return response()->json([
                'success' => false,
                'message' => 'هذه القسيمة مستخدمة بالفعل',
                'used_at' => $redemption->voucher_used_at?->format('Y/m/d H:i'),
                'used_in_invoice' => $redemption->used_in_invoice_id,
            ], 400);
        }

        if ($redemption->voucher_status === 'expired' ||
            ($redemption->voucher_expires_at && $redemption->voucher_expires_at->isPast())) {
            return response()->json([
                'success' => false,
                'message' => 'هذه القسيمة منتهية الصلاحية',
                'expired_at' => $redemption->voucher_expires_at?->format('Y/m/d'),
            ], 400);
        }

        if ($redemption->voucher_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'هذه القسيمة ملغاة',
            ], 400);
        }

        if (! in_array($redemption->status->value, ['approved', 'processing', 'delivered'])) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الاستبدال لم يتم الموافقة عليه بعد. الحالة: '.$redemption->status->label(),
            ], 400);
        }

        // القسيمة صالحة
        $beneficiary = '';
        if ($redemption->user_type->value === 'employee' && $redemption->employee) {
            $beneficiary = $redemption->employee->full_name;
        } elseif ($redemption->client) {
            $beneficiary = $redemption->client->full_name;
        }

        // جلب فواتير العميل مباشرة مع الاستجابة
        $invoices = [];
        if ($redemption->client_id) {
            $invoiceRecords = Invoice::where('client_id', $redemption->client_id)
                ->whereIn('type', ['normal', 'pos', 'online'])
                ->orderBy('invoice_date', 'desc')
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get(['id', 'code', 'grand_total', 'invoice_date', 'type', 'payment_status', 'is_paid', 'created_at']);

            $invoices = $invoiceRecords->map(function ($invoice) {
                $displayCode = $invoice->code ?: 'INV-'.$invoice->id;
                $typeLabel = match ($invoice->type) {
                    'pos' => 'نقطة بيع',
                    'online' => 'متجر إلكتروني',
                    default => 'عادية'
                };
                $dateDisplay = $invoice->invoice_date
                    ? $invoice->invoice_date->format('Y/m/d')
                    : ($invoice->created_at ? $invoice->created_at->format('Y/m/d') : '-');

                return [
                    'id' => $invoice->id,
                    'code' => $displayCode,
                    'grand_total' => (float) $invoice->grand_total,
                    'invoice_date' => $dateDisplay,
                    'type_label' => $typeLabel,
                    'is_paid' => $invoice->is_paid,
                ];
            })->values()->toArray();
        }

        return response()->json([
            'success' => true,
            'message' => 'القسيمة صالحة للاستخدام',
            'data' => [
                'redemption_id' => $redemption->id,
                'redemption_number' => $redemption->redemption_number,
                'voucher_code' => $redemption->voucher_code,
                'voucher_serial' => $redemption->voucher_serial,
                'voucher_expires_at' => $redemption->voucher_expires_at?->format('Y/m/d'),
                'reward_name' => $reward->name,
                'reward_type' => $reward->reward_type->value,
                'reward_type_label' => $reward->reward_type->label(),
                'reward_type_icon' => $reward->reward_type->icon(),
                'reward_value' => $reward->reward_value,
                'max_discount_value' => $reward->max_discount_value,
                'min_order_amount' => $reward->min_order_amount,
                'needs_invoice_amount' => $reward->needsInvoiceAmount(),
                'beneficiary' => $beneficiary,
                'user_type' => $redemption->user_type->label(),
                'user_type_value' => $redemption->user_type->value,
                'client_id' => $redemption->client_id,
                'has_pin' => ! empty($redemption->voucher_pin),
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * جلب فواتير العميل (غير المرتجعة) عبر AJAX
     * يدعم البحث برقم الفاتورة
     */
    public function getClientInvoices(Request $request, $clientId)
    {
        $query = Invoice::where('client_id', $clientId)
            ->whereIn('type', ['normal', 'pos', 'online'])
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc');

        // البحث برقم الفاتورة
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $invoices = $query->limit(50)
            ->get(['id', 'code', 'grand_total', 'invoice_date', 'type', 'payment_status', 'is_paid', 'created_at']);

        $data = $invoices->map(function ($invoice) {
            // استخدام code أو id كبديل
            $displayCode = $invoice->code ?: 'INV-'.$invoice->id;

            $typeLabel = match ($invoice->type) {
                'pos' => 'نقطة بيع',
                'online' => 'متجر إلكتروني',
                default => 'عادية'
            };

            $statusLabel = $invoice->is_paid ? 'مدفوعة' : ($invoice->payment_status ?? 'غير مدفوعة');

            // استخدام invoice_date أو created_at كبديل
            $dateDisplay = $invoice->invoice_date
                ? $invoice->invoice_date->format('Y/m/d')
                : ($invoice->created_at ? $invoice->created_at->format('Y/m/d') : '-');

            return [
                'id' => $invoice->id,
                'code' => $displayCode,
                'grand_total' => (float) $invoice->grand_total,
                'invoice_date' => $dateDisplay,
                'type' => $invoice->type,
                'type_label' => $typeLabel,
                'status_label' => $statusLabel,
                'is_paid' => $invoice->is_paid,
                'text' => '#'.$displayCode.' - '.number_format($invoice->grand_total, 2).' ريال ('.$dateDisplay.') - '.$typeLabel,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /**
     * استخدام القسيمة (تطبيق الخصم)
     */
    public function useVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string|max:50',
            'voucher_pin' => 'nullable|string|max:10',
            'invoice_amount' => 'nullable|numeric|min:0',
            'invoice_id' => 'nullable|integer',
        ]);

        $redemption = RewardRedemption::with(['reward'])
            ->where('voucher_code', $request->voucher_code)
            ->first();

        if (! $redemption) {
            return response()->json([
                'success' => false,
                'message' => 'كود القسيمة غير صالح',
            ], 404);
        }

        // التحقق من أن القسيمة نشطة
        if (! $redemption->isVoucherActive()) {
            $reason = 'القسيمة غير قابلة للاستخدام';
            if ($redemption->voucher_used) {
                $reason = 'القسيمة مستخدمة بالفعل';
            } elseif ($redemption->voucher_expires_at && $redemption->voucher_expires_at->isPast()) {
                $reason = 'القسيمة منتهية الصلاحية';
            } elseif ($redemption->voucher_status === 'cancelled') {
                $reason = 'القسيمة ملغاة';
            }

            return response()->json([
                'success' => false,
                'message' => $reason,
            ], 400);
        }

        // التحقق من رمز PIN إذا كان موجوداً
        if ($redemption->voucher_pin && $request->voucher_pin !== $redemption->voucher_pin) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق PIN غير صحيح',
            ], 400);
        }

        $reward = $redemption->reward;

        // التحقق من الحد الأدنى للطلب
        $invoiceAmount = (float) ($request->invoice_amount ?? 0);
        if ($reward->min_order_amount && $invoiceAmount < $reward->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'مبلغ الفاتورة أقل من الحد الأدنى المطلوب ('.number_format($reward->min_order_amount, 2).' ريال)',
                'min_order_amount' => $reward->min_order_amount,
            ], 400);
        }

        DB::beginTransaction();

        try {
            // حساب الخصم
            $discount = $reward->calculateDiscount($invoiceAmount);

            // استخدام القسيمة
            $redemption->update([
                'voucher_used' => true,
                'voucher_used_at' => now(),
                'voucher_status' => 'used',
                'applied_to_amount' => $invoiceAmount,
                'calculated_discount' => $discount,
                'used_in_invoice_id' => $request->invoice_id,
            ]);

            // تحديث حالة الاستبدال إلى مسلّم إذا لم يكن كذلك
            if ($redemption->status->value !== 'delivered') {
                $redemption->update([
                    'status' => RedemptionStatus::DELIVERED->value,
                    'delivered_at' => now(),
                ]);
            }

            // إنشاء القيد المحاسبي بمبلغ الخصم الفعلي
            if ($discount > 0) {
                $redemption->load(['employee', 'client']); // تحميل العلاقات للقيد
                $this->createVoucherUsageEntry($redemption, $reward, $discount, $invoiceAmount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استخدام القسيمة بنجاح',
                'data' => [
                    'voucher_code' => $redemption->voucher_code,
                    'reward_name' => $reward->name,
                    'reward_type' => $reward->reward_type->value,
                    'reward_type_label' => $reward->reward_type->label(),
                    'invoice_amount' => $invoiceAmount,
                    'discount_amount' => $discount,
                    'final_amount' => $invoiceAmount - $discount,
                    'discount_percentage' => $reward->reward_type->value === 'discount_percentage' ? $reward->reward_value : null,
                    'max_discount_applied' => $reward->max_discount_value && $discount >= $reward->max_discount_value,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Ecosystem.RewardRedemption', 'useVoucher');

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // خصم واسترجاع النقاط من/إلى الدفعات (FIFO)
    // =====================================================

    /**
     * خصم النقاط من الدفعات الأقدم أولاً (FIFO)
     */
    private function deductFromBatches(string $userType, ?int $employeeId, ?int $clientId, int $pointsToDeduct, int $redemptionId): void
    {
        try {
            $query = PointsBatch::where('status', BatchStatus::ACTIVE->value)
                ->where('remaining_points', '>', 0)
                ->where('user_type', $userType);

            if ($userType === 'employee' && $employeeId) {
                $query->where('employee_id', $employeeId);
            } elseif ($userType === 'client' && $clientId) {
                $query->where('client_id', $clientId);
            }

            // ترتيب بتاريخ الكسب (الأقدم أولاً) — ثم الأقرب انتهاءً
            $batches = $query->orderBy('earned_at', 'asc')
                ->orderBy('expires_at', 'asc')
                ->get();

            $remaining = $pointsToDeduct;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct = min($remaining, $batch->remaining_points);
                $batch->usePoints($deduct);
                $remaining -= $deduct;
            }

            if ($remaining > 0) {
                \Log::warning('خصم دفعات: لم تكف الدفعات لتغطية الخصم', [
                    'redemption_id' => $redemptionId,
                    'unmatched_points' => $remaining,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('فشل خصم النقاط من الدفعات: '.$e->getMessage(), [
                'redemption_id' => $redemptionId,
            ]);
        }
    }

    /**
     * استرجاع النقاط إلى الدفعات عند الإلغاء (الأحدث أولاً)
     */
    private function restoreBatchPoints(string $userType, ?int $employeeId, ?int $clientId, int $pointsToRestore): void
    {
        try {
            // استرجاع إلى الدفعات المستنفدة/المخصومة (الأحدث أولاً — عكس FIFO)
            $query = PointsBatch::where('user_type', $userType)
                ->where('used_points', '>', 0)
                ->whereIn('status', [BatchStatus::ACTIVE->value, BatchStatus::DEPLETED->value]);

            if ($userType === 'employee' && $employeeId) {
                $query->where('employee_id', $employeeId);
            } elseif ($userType === 'client' && $clientId) {
                $query->where('client_id', $clientId);
            }

            $batches = $query->orderBy('earned_at', 'desc')->get();

            $remaining = $pointsToRestore;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $restore = min($remaining, $batch->used_points);
                $batch->remaining_points += $restore;
                $batch->used_points -= $restore;

                // إعادة تنشيط الدفعة إذا كانت مستنفدة
                if ($batch->status === BatchStatus::DEPLETED && $batch->remaining_points > 0) {
                    $batch->status = BatchStatus::ACTIVE;
                }

                $batch->save();
                $remaining -= $restore;
            }
        } catch (\Throwable $e) {
            \Log::warning('فشل استرجاع النقاط إلى الدفعات: '.$e->getMessage());
        }
    }

    /**
     * حسابُ القيد من «الحسابات المحاسبية» وحدها (مجموعة «نظام المكافآت»).
     *
     * كانت الحساباتُ ثوابتَ `SystemAccountIds` مكتوبةً في كل فرع — فتغييرُ
     * المحاسب لحساب مصروف المكافآت (في «حسابات الرواتب» أو «الحسابات
     * المحاسبية») لا يبلغ القيد. لا احتياط: مفتاحٌ غير مربوط يرفض القيد
     * برسالةٍ تدلّ على الشاشة بدل الترحيل إلى حسابٍ لم يُختر.
     */
    private function settingsAccountId(string $key): int
    {
        $id = \App\Support\AccountingSettings::id($key);
        if (! $id) {
            throw new \RuntimeException('حساب «'.$key.'» غير مربوط — اربطه في الإعدادات ← الحسابات المحاسبية (نظام المكافآت) قبل الاستبدال.');
        }

        return $id;
    }
}
