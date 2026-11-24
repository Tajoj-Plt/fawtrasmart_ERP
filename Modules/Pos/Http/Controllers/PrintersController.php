<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Models\PosPrinter;

/**
 * طابعاتُ نقاط البيع.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * `pos_general_settings.invoice_template` — خيارٌ واحدٌ عالميّ:
 * `thermal` أو `electronic`. لا عرضَ ورق، ولا عددَ نسخ، ولا طابعةَ مطبخٍ
 * منفصلة، ولا ربطَ طابعةٍ بجهاز. فمنشأةٌ بجهازين وطابعتين لا تستطيع
 * التمييز بينهما، ومطعمٌ يحتاج نسخةً للمطبخ لا سبيل له إليها.
 *
 * والطابعةُ ليست تفصيلاً شكلياً: عرضُ الورق يحدّد ما يُطبع أصلاً — إيصالٌ
 * بعرض ٥٨ ملّيمتراً لا يسع الأعمدةَ التي يسعها ٨٠.
 */
class PrintersController extends Controller
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
        $this->middleware('permission.role:pos.printers.manage|pos.devices.view', ['only' => ['index']]);
        $this->middleware('permission.role:pos.printers.manage', ['only' => [
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ]]);
    }

    public function index(Request $request)
    {
        $query = PosPrinter::with(['device:id,device_name', 'branch:id,name']);

        $this->scopeToBranch($query);

        $query
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));

        return view('pos::settings.printers.index', [
            'printers' => $query->latest()->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
                ? (int) $request->input('per_page')
                : 15)->withQueryString(),
            'types' => $this->typeOptions(),
            'devices' => $this->accessibleDevices()->get(['id', 'device_name']),
            'branches' => $this->branchOptions(),
            'canManage' => hasPermission('pos.printers.manage') || $this->hasFullPosScope(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('pos::settings.printers.create', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $this->validated($request);

        try {
            $printer = PosPrinter::create($data);

            $this->clearOtherDefaults($printer);

            OperationLog::record('created', 'pos_printer', $printer, 'إضافة طابعة: '.$printer->name);

            return redirect()->route('pos.settings.printers.index')
                ->with('success', 'تم إضافة الطابعة بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Printers', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ الطابعة.');
        }
    }

    public function edit($id)
    {
        $this->authorizeManage();

        return view('pos::settings.printers.edit', $this->formData() + ['printer' => $this->findInScope($id)]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();

        $printer = $this->findInScope($id);

        try {
            $printer->update($this->validated($request));

            $this->clearOtherDefaults($printer);

            OperationLog::record('updated', 'pos_printer', $printer, 'تعديل طابعة: '.$printer->name);

            return redirect()->route('pos.settings.printers.index')
                ->with('success', 'تم تحديث الطابعة بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Printers', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث الطابعة.');
        }
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        $printer = $this->findInScope($id);

        try {
            /*
                طابعةٌ مضبوطةٌ افتراضيةً لجهازٍ لا تُحذف قبل فكّ ارتباطها —
                وإلّا بقي الجهازُ يشير إلى طابعةٍ لا وجود لها، فيفشل الطبع
                بخطأٍ لا يدلّ على سببه.
            */
            $linked = CashierDevice::where('default_printer_id', $printer->id)->count();

            if ($linked > 0) {
                return back()->with('error', "لا يمكن حذف الطابعة: هي الافتراضية لـ{$linked} جهاز. غيّر طابعتها أولاً.");
            }

            $name = $printer->name;
            $printer->delete();

            OperationLog::record('deleted', 'pos_printer', (int) $id, "حذف طابعة: {$name}");

            return redirect()->route('pos.settings.printers.index')
                ->with('success', "تم حذف الطابعة «{$name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Printers', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف الطابعة.');
        }
    }

    /* ═══════════════════════ مساعدات ═══════════════════════ */

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'device_id' => 'nullable|exists:cashier_devices,id',
            'branch_id' => 'nullable|exists:branches,id',
            'type' => 'required|in:receipt,a4,kitchen,label',
            'connection' => 'required|in:usb,network,bluetooth,cloud',
            'address' => 'nullable|string|max:255',
            'paper_width' => 'required|integer|min:38|max:250',
            'copies' => 'required|integer|min:1|max:5',
        ], [
            'name.required' => 'اسم الطابعة مطلوب',
            'type.in' => 'نوع الطابعة غير صحيح',
            'connection.in' => 'نوع الاتصال غير صحيح',
            'paper_width.min' => 'عرض الورق يجب ألا يقل عن 38 ملّيمتراً',
            'paper_width.max' => 'عرض الورق يجب ألا يتجاوز 250 ملّيمتراً',
            'copies.max' => 'عدد النسخ يجب ألا يتجاوز 5',
        ]);

        // الـ checkboxes الغائبة تعني «مطفأة» لا «بلا تغيير»
        foreach (['auto_cut', 'open_drawer', 'is_default', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // الفرعُ يُقفَل على فرع الموظف كما في بقيّة شاشات نقاط البيع
        $data['branch_id'] = $this->resolveWriteBranchId(
            $data['branch_id'] ? (int) $data['branch_id'] : null
        );

        /*
            `null` صريحٌ إلى عمودٍ NOT NULL يُسقط الإدراج — و`pos_printers`
            فيه ثمانيةُ أعمدةٍ بهذه الصفة. انظر [ScopesToPosAccess].
        */
        $data = $this->dropNullsForNotNullColumns('pos_printers', $data);

        return $data;
    }

    /**
     * افتراضيةٌ واحدة لكل نطاق.
     *
     * ضبطُ طابعتين افتراضيتين لنفس الجهاز ونفس النوع يجعل اختيارَ الطابعة
     * تابعاً لترتيب الصفوف — فيطبع الإيصالُ على طابعةٍ اليوم وأخرى غداً.
     */
    private function clearOtherDefaults(PosPrinter $printer): void
    {
        if (! $printer->is_default) {
            return;
        }

        PosPrinter::where('id', '!=', $printer->id)
            ->where('type', $printer->type)
            ->when($printer->device_id, fn ($q) => $q->where('device_id', $printer->device_id))
            ->when(! $printer->device_id, fn ($q) => $q->whereNull('device_id')->where('branch_id', $printer->branch_id))
            ->update(['is_default' => false]);
    }

    private function authorizeManage(): void
    {
        if (! hasPermission('pos.printers.manage') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بإدارة الطابعات.');
        }
    }

    private function findInScope($id): PosPrinter
    {
        $printer = PosPrinter::findOrFail($id);

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null && $printer->branch_id && ! in_array((int) $printer->branch_id, $branchIds, true)) {
            abort(403, 'هذه الطابعة خارج نطاق صلاحياتك.');
        }

        return $printer;
    }

    private function formData(): array
    {
        return [
            'types' => $this->typeOptions(),
            'connections' => [
                'usb' => 'USB',
                'network' => 'شبكة',
                'bluetooth' => 'بلوتوث',
                'cloud' => 'سحابية',
            ],
            'devices' => $this->accessibleDevices()->get(['id', 'device_name']),
            'branches' => $this->branchOptions(),
            'canSelectBranch' => $this->canSelectBranch(),
            'currentBranchId' => $this->currentEffectiveBranchId(),
        ];
    }

    private function typeOptions(): array
    {
        return [
            PosPrinter::TYPE_RECEIPT => PosPrinter::typeLabel(PosPrinter::TYPE_RECEIPT),
            PosPrinter::TYPE_A4 => PosPrinter::typeLabel(PosPrinter::TYPE_A4),
            PosPrinter::TYPE_KITCHEN => PosPrinter::typeLabel(PosPrinter::TYPE_KITCHEN),
            PosPrinter::TYPE_LABEL => PosPrinter::typeLabel(PosPrinter::TYPE_LABEL),
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
}
