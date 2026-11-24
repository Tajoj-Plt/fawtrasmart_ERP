<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerTarget;
use App\Services\CustomerTargetService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * أهداف العملاء: شرائحُ التصنيف التي يُقاس بها أداءُ العميل.
 *
 * **ما يأتي من الإعدادات**: `customer_targets_enabled` في «إعدادات وحدات
 * العملاء» — مطفأً تُمنع الشاشة وروابطُها المباشرة معاً.
 *
 * **الصلاحيات**: لم تكن على المتحكّم صلاحيةٌ واحدة، وفيه ما يعيد تقييم كلِّ
 * عملاء المنشأة بضغطة (`evaluateAll`) — عمليةٌ تغيّر تصنيفَ كلِّ عميل.
 * صارت على `clients.reports.targets` للعرض و`clients_edit_client_settings`
 * للكتابة، وهي الصلاحية التي تحرس بقيّة شاشات إعدادات العملاء.
 *
 * **السجلّات**: `OperationLog` في الإنشاء والتعديل والحذف وإعادة التقييم —
 * لم يكن على أيٍّ منها سطرُ سجلٍّ واحد، ولا التقاطُ استثناء.
 *
 * **`$request->all()` كان يُمرَّر كما هو** إلى `create()`/`update()`: أيُّ
 * حقلٍ في `fillable` يُدسّ في الطلب يُكتب، ولو لم يُذكر في التحقّق.
 */
class CustomerTargetController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientTargets';

    /** الحقول التي تُكتب فعلاً — لا `all()` */
    private const FIELDS = [
        'name', 'color', 'min_sales', 'min_collections',
        'max_avg_collection_days', 'priority',
    ];

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients.reports.targets|clients_edit_client_settings', ['only' => ['index']]);
        $this->middleware('permission:clients_edit_client_settings', ['only' => [
            'create', 'store', 'edit', 'update', 'destroy', 'evaluateAll',
        ]]);
    }

    /**
     * Display a listing of the targets.
     */
    public function index()
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $targets = CustomerTarget::orderBy('priority', 'desc')->get();

        return view('client::customer_targets.index', compact('targets'));
    }

    /**
     * Show the form for creating a new target.
     */
    public function create()
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        return view('client::customer_targets.create');
    }

    /**
     * Store a newly created target in storage.
     */
    public function store(Request $request)
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $target = CustomerTarget::create($this->payload($request));
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()->back()->withInput()->with('error', 'تعذّر حفظ الهدف: '.$e->getMessage());
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $target,
            'إضافة هدف عملاء «'.$target->name.'»',
            ['new' => $target->only(self::FIELDS)]
        );

        return redirect()->route('client.customer_targets.index')->with('success', 'تم إضافة الهدف بنجاح.');
    }

    /**
     * Show the form for editing the specified target.
     */
    public function edit($id)
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $target = CustomerTarget::findOrFail($id);

        return view('client::customer_targets.edit', compact('target'));
    }

    /**
     * Update the specified target in storage.
     */
    public function update(Request $request, $id)
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $target = CustomerTarget::findOrFail($id);
        $before = $target->only([...self::FIELDS, 'is_active']);

        try {
            $target->update($this->payload($request) + [
                // مربّعٌ غير مؤشَّر لا يُرسَل، فغيابُه إطفاء
                'is_active' => $request->has('is_active'),
            ]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', (int) $id);

            return redirect()->back()->withInput()->with('error', 'تعذّر تحديث الهدف: '.$e->getMessage());
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $target,
            'تعديل هدف العملاء «'.$target->name.'»',
            ['old' => $before, 'new' => $target->only([...self::FIELDS, 'is_active'])]
        );

        return redirect()->route('client.customer_targets.index')->with('success', 'تم تحديث الهدف بنجاح.');
    }

    /**
     * Remove the specified target from storage.
     */
    public function destroy($id)
    {
        $target = CustomerTarget::findOrFail($id);

        try {
            // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $target,
                'حذف هدف العملاء «'.$target->name.'»',
                ['old' => $target->only(self::FIELDS)]
            );

            $target->delete();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return redirect()->back()->with('error', 'تعذّر حذف الهدف: '.$e->getMessage());
        }

        return redirect()->route('client.customer_targets.index')->with('success', 'تم حذف الهدف بنجاح.');
    }

    /**
     * Trigger re-evaluation of all clients manually.
     */
    public function evaluateAll(Request $request, CustomerTargetService $service)
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->back()->with('error', $block);
        }

        $request->validate([
            'evaluation_type' => 'nullable|in:date,days',
            'days' => 'nullable|integer|min:1|max:3650',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $evaluationType = $request->input('evaluation_type', 'date');
        $dateFrom = null;
        $dateTo = null;
        $days = null;

        if ($evaluationType === 'days') {
            $days = $request->input('days');
        } else {
            $dateFrom = $request->filled('date_from') ? \Illuminate\Support\Carbon::parse($request->input('date_from'))->startOfDay() : null;
            $dateTo = $request->filled('date_to') ? \Illuminate\Support\Carbon::parse($request->input('date_to'))->endOfDay() : null;
        }

        try {
            $service->evaluateAllClients($dateFrom, $dateTo, $days);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'evaluate_all');

            return redirect()->back()->with('error', 'تعذّرت إعادة التقييم: '.$e->getMessage());
        }

        OperationLog::record(
            'evaluated',
            self::LOG_MODULE,
            null,
            'إعادة تقييم جميع العملاء على أهدافهم',
            ['new' => [
                'evaluation_type' => $evaluationType,
                'days' => $days,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ]]
        );

        return redirect()->route('client.customer_targets.index')->with('success', 'تمت إعادة تقييم جميع العملاء بنجاح.');
    }

    /**
     * قواعد التحقّق — واحدة للإنشاء والتعديل، فلا تفترقان مع أول تعديل.
     *
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'min_sales' => 'nullable|numeric|min:0',
            'min_collections' => 'nullable|numeric|min:0',
            'max_avg_collection_days' => 'nullable|integer|min:0',
            'priority' => 'nullable|integer',
        ];
    }

    /**
     * الحقول المكتوبة وحدها، بعد تصفير «متوسط أيام التحصيل» الفارغ.
     *
     * الصفر هنا مقصود لا افتراض: العمود لا يقبل `null` وقيمتُه صفراً تعني
     * «بلا شرطٍ على أيام التحصيل».
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = $request->only(self::FIELDS);

        if (! isset($data['max_avg_collection_days']) || trim((string) $data['max_avg_collection_days']) === '') {
            $data['max_avg_collection_days'] = 0;
        }

        return $data;
    }
}
