<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\PosSession;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Requests\StoreDeviceRequest;
use Modules\Pos\Http\Requests\UpdateDeviceRequest;

/**
 * أجهزةُ الكاشير.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * **بلا أيّ فحص صلاحية إطلاقاً**: لا `__construct`، ولا `middleware`، ولا
 * `hasPermission` في سطرٍ واحد. فإنشاءُ جهازٍ وتعديلُه وحذفُه — وهي أفعالٌ
 * تحدّد **المستودعَ الذي يُخصم منه البيع والفرعَ الذي يُنسب إليه** — كانت
 * مفتوحةً لكل من يجتاز مجموعة المسارات.
 *
 * و`destroy()` كانت أخطرَها: تحذف الجهازَ **بلا أيّ فحصٍ للجلسات المرتبطة
 * به**، و`try/catch` فيها معلَّقٌ بالكامل. فحذفُ جهازٍ له جلساتٌ مفتوحة
 * يترك جلساتٍ يتيمةً لا يُعرف مستودعُها ولا فرعُها.
 */
class DevicesController extends Controller
{
    use ScopesToPosAccess;

    public function __construct()
    {
        /*
            ═══════════════ بوّابةُ الوحدة ═══════════════

            الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع، لا لمن يملك صلاحيةً
            بعينها.

            كان الفحصُ بصلاحيةٍ واحدة، وهو يقلب المقصود: كاشيرٌ يملك البيعَ
            وحده يُردّ من باب الشاشة التي هي عمله، ومشرفٌ يملك التقارير
            وحدها كذلك. والحراسةُ الدقيقة موضعُها الدالّة لا الباب.

            والبادئاتُ ثلاثٌ لا واحدة (`pos.` و`pos_` و`points_sale_`):
            إغفالُ إحداها يردّ كلَّ من صلاحياتُه بالنمط القديم.
        */
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });

        /*
            ═══════════════ حراسةُ كل دالّة ═══════════════

            مطابقةٌ حرفياً لما يفرضه `Modules/Pos/routes/web.php`. والتكرارُ
            مقصود: الحارسُ على المسار وحده يسقط متى نُودي المتحكّمُ من مسارٍ
            جديد يُنسى تزويدُه، أو من وحدةٍ أخرى تستدعي الدالّة مباشرةً.
        */
        $this->middleware('permission.role:pos.devices.view|pos.devices.manage', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:pos.devices.manage', ['only' => [
            'create',
            'store',
            'edit',
            'update',
            'toggleStatus',
            'destroy',
        ]]);
    }

    public function index(Request $request)
    {
        $query = CashierDevice::with(['store:id,name', 'branch:id,name', 'user:id,name']);

        // النطاق: من لا يرى إلّا فرعَه لا يرى أجهزة غيره
        $this->scopeToBranch($query);

        // والجهازُ مسنَدٌ إلى كاشيرٍ مسؤول — انظر [ShiftController::index]
        $this->scopeToAssignedUser($query);

        $query
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('device_name', 'like', '%'.$request->search.'%')
                    ->orWhere('serial_number', 'like', '%'.$request->search.'%');
            }))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->store_id))
            ->when($request->filled('status'), fn ($q) => $q->where('device_status', $request->status))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id));

        /*
            عدُّ الجلسات المفتوحة مع الجهاز — بـ`withCount` لا باستعلامٍ
            لكل صفّ في القالب.
        */
        $devices = $query
            ->withCount(['sessions as open_sessions_count' => fn ($q) => $q->where('status', PosSession::STATUS_ACTIVE)])
            ->latest()
            // حجمُ الصفحة من الطلب: مُنتقي «عرض … سجلاً» كان يرسل `per_page`
            // فلا يُقرأ. ومقيَّدٌ بقائمةٍ مغلقة كي لا يُجلب الجدولُ كلُّه.
            ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
                ? (int) $request->input('per_page')
                : 15)
            ->withQueryString();

        return view('pos::settings.devices.index', [
            'devices' => $devices,
            // فلترُ المستودع: ما يبلغه المستخدم لا كلَّ مستودعات المنشأة
            'stores' => $this->accessibleStorehouses()->get(),
            'branches' => $this->branchOptions(),
            'statusOptions' => CashierDevice::getStatusOptions(),
            'canManage' => hasPermission('pos.devices.manage') || $this->hasFullPosScope(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('pos::settings.devices.create', $this->formData());
    }

    public function store(StoreDeviceRequest $request)
    {
        $data = $request->validated();

        // الفرعُ يُقفَل على فرع الموظف مهما أرسل الطلب
        $data['branch_id'] = $this->resolveWriteBranchId((int) $data['branch_id']);

        // القائمةُ في الشاشة مقيَّدة، والحارسُ هنا هو ما يمنع طلباً مصنوعاً
        $this->assertStorehouseAccess(isset($data['store_id']) ? (int) $data['store_id'] : null);

        // `null` صريحٌ إلى عمودٍ NOT NULL يُسقط الإدراج — انظر [ScopesToPosAccess]
        $data = $this->dropNullsForNotNullColumns('cashier_devices', $data);
        // الفرعُ والموظّفُ حقلان مستقلّان في التحقّق — والربطُ بينهما هنا
        $this->assertEmployeeBelongsToBranch(
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['branch_id'] ? (int) $data['branch_id'] : null
        );

        try {
            if ($request->hasFile('device_image')) {
                $data['device_image'] = $request->file('device_image')->store('cashier-devices', 'public');
            }

            $device = CashierDevice::create($data + ['created_by' => auth()->id()]);

            OperationLog::record('created', 'pos_device', $device, 'إضافة جهاز كاشير: '.$device->device_name);

            return redirect()->route('pos.settings.devices.index')
                ->with('success', 'تم إضافة الجهاز بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Devices', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ البيانات. يرجى المحاولة مرة أخرى.');
        }
    }

    public function show($id)
    {
        $device = $this->findInScope($id);

        return view('pos::settings.devices.show', [
            'device' => $device->load(['store:id,name', 'branch:id,name', 'user:id,name']),
            'recentSessions' => PosSession::where('device_id', $device->id)
                ->with('user:id,name')
                ->latest('started_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $device = $this->findInScope($id);

        return view('pos::settings.devices.edit', $this->formData($device->id) + ['device' => $device]);
    }

    public function update(UpdateDeviceRequest $request, $id)
    {
        $device = $this->findInScope($id);

        $data = $request->validated();
        $data['branch_id'] = $this->resolveWriteBranchId((int) $data['branch_id']);

        // القائمةُ في الشاشة مقيَّدة، والحارسُ هنا هو ما يمنع طلباً مصنوعاً
        $this->assertStorehouseAccess(isset($data['store_id']) ? (int) $data['store_id'] : null);

        // `null` صريحٌ إلى عمودٍ NOT NULL يُسقط الإدراج — انظر [ScopesToPosAccess]
        $data = $this->dropNullsForNotNullColumns('cashier_devices', $data);
        // الفرعُ والموظّفُ حقلان مستقلّان في التحقّق — والربطُ بينهما هنا
        $this->assertEmployeeBelongsToBranch(
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['branch_id'] ? (int) $data['branch_id'] : null
        );

        try {
            if ($request->hasFile('device_image')) {
                if ($device->device_image && Storage::disk('public')->exists($device->device_image)) {
                    Storage::disk('public')->delete($device->device_image);
                }

                $data['device_image'] = $request->file('device_image')->store('cashier-devices', 'public');
            }

            $device->update($data + ['updated_by' => auth()->id()]);

            OperationLog::record('updated', 'pos_device', $device, 'تعديل جهاز كاشير: '.$device->device_name);

            return redirect()->route('pos.settings.devices.index')
                ->with('success', 'تم تحديث الجهاز بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Devices', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث البيانات. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * حذفُ الجهاز — بعد التأكّد أنه لا يحمل تاريخاً.
     *
     * كانت الدالّة تحذف بلا أيّ فحص، و`try/catch` فيها معلَّقٌ بالكامل.
     * وجهازٌ له جلساتٌ يُحذف يترك جلساتٍ يتيمة لا يُعرف مستودعُها ولا فرعُها،
     * فتنكسر تقاريرُها كلُّها.
     */
    public function destroy($id)
    {
        $this->authorizeManage();

        $device = $this->findInScope($id);

        try {
            $openSessions = PosSession::where('device_id', $device->id)
                ->whereIn('status', [PosSession::STATUS_ACTIVE, PosSession::STATUS_SUSPENDED, PosSession::STATUS_PENDING_REVIEW])
                ->count();

            if ($openSessions > 0) {
                return back()->with('error', "لا يمكن حذف الجهاز: لديه {$openSessions} جلسة لم تُقفل أو لم تُؤكَّد بعد.");
            }

            $totalSessions = PosSession::where('device_id', $device->id)->count();

            if ($totalSessions > 0) {
                /*
                    جهازٌ له تاريخٌ لا يُحذف بل يُعطَّل.

                    حذفُه — ولو ناعماً — يقطع جلساتِه عن مستودعها وفرعها،
                    فتصير تقاريرُ الفترات الماضية غيرَ قابلة لإعادة الإنتاج.
                */
                $device->update(['device_status' => 'inactive']);

                return redirect()->route('pos.settings.devices.index')
                    ->with('success', "الجهاز «{$device->device_name}» له {$totalSessions} جلسة سابقة، فعُطِّل بدل حذفه حفاظاً على تقاريرها.");
            }

            if ($device->device_image && Storage::disk('public')->exists($device->device_image)) {
                Storage::disk('public')->delete($device->device_image);
            }

            $name = $device->device_name;
            $device->delete();

            OperationLog::record('deleted', 'pos_device', (int) $id, "حذف جهاز كاشير: {$name}");

            return redirect()->route('pos.settings.devices.index')
                ->with('success', "تم حذف الجهاز «{$name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Devices', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف الجهاز.');
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $this->authorizeManage();

        $device = $this->findInScope($id);

        $request->validate([
            'device_status' => 'required|in:active,inactive,maintenance,damaged',
        ], [
            'device_status.required' => 'حالة الجهاز مطلوبة',
            'device_status.in' => 'حالة الجهاز غير صحيحة',
        ]);

        // تعطيلُ جهازٍ عليه جلسةٌ مفتوحة يقطع البيع على الكاشير في منتصف الوردية
        if ($request->device_status !== 'active') {
            $hasOpen = PosSession::where('device_id', $device->id)
                ->where('status', PosSession::STATUS_ACTIVE)
                ->exists();

            if ($hasOpen) {
                return back()->with('error', 'لا يمكن تعطيل الجهاز: عليه جلسة نشطة. أغلق الجلسة أولاً.');
            }
        }

        try {
            $oldStatus = $device->status_text;
            $device->update(['device_status' => $request->device_status]);

            return redirect()->route('pos.settings.devices.index')
                ->with('success', "تم تغيير حالة الجهاز «{$device->device_name}» من {$oldStatus} إلى {$device->fresh()->status_text}");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Devices', 'toggleStatus');

            return back()->with('error', 'حدث خطأ أثناء تغيير حالة الجهاز.');
        }
    }

    /* ═══════════════════════ مساعدات ═══════════════════════ */

    private function authorizeManage(): void
    {
        if (! hasPermission('pos.devices.manage') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بإدارة أجهزة نقاط البيع.');
        }
    }

    /**
     * جلبُ الجهاز داخل نطاق المستخدم.
     *
     * `findOrFail($id)` وحدها تُسلّم أيَّ جهازٍ لأيِّ مستخدمٍ يعرف رقمه —
     * وهو ما كانت تفعله الدوالُّ الستّ كلُّها.
     */
    private function findInScope($id): CashierDevice
    {
        $device = CashierDevice::findOrFail($id);

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null && ! in_array((int) $device->branch_id, $branchIds, true)) {
            abort(403, 'هذا الجهاز خارج نطاق صلاحياتك.');
        }

        return $device;
    }

    /** بياناتُ النموذج — موضعٌ واحد لـ`create` و`edit`. */
    private function formData(?int $excludeId = null): array
    {
        return [
            'branches' => $this->branchOptions(),
            // الموظفون في نطاق المستخدم — انظر [ShiftController::formData] للعلّة نفسها
            'users' => User::whereNotNull('employee_id')
                ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
                ->with('employee:id,branch_id')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']),
            /*
                `store_id` إلزاميّ في النموذج، وما يُعرض هنا هو ما يُقبل حفظُه.
                فعرضُ كلِّ المستودعات يتيح ربطَ الجهاز بمخزنٍ ليس للمستخدم —
                فيُخصم بيعُه منه ويظهر العجزُ في جردِ قسمٍ آخر.
            */
            'storehouses' => $this->accessibleStorehouses()->get(),
            'statusOptions' => CashierDevice::getStatusOptions(),
            'printers' => $this->printerOptions(),
            'canSelectBranch' => $this->canSelectBranch(),
            'currentBranchId' => $this->currentEffectiveBranchId(),
        ];
    }

    private function branchOptions()
    {
        $query = Branch::query()->orderBy('name');

        $ids = $this->accessibleBranchIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get(['id', 'name']);
    }

    private function printerOptions()
    {
        try {
            return DB::table('pos_printers')
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'type']);
        } catch (\Throwable $e) {
            // جدولُ الطابعات غير مُرحَّل بعد يجب ألّا يكسر شاشة الأجهزة
            return collect();
        }
    }
}
