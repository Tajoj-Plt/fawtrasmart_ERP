<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as ModelsLog;
use App\Models\ManualManufacturingOrderStatuses;
use App\Models\ManufacturingGeneralSetting;
use App\Models\ManufacturingOrderStatuses;
use App\Models\ProductionPath;
use App\Models\ProductionStage;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;

class SettingsController extends Controller
{
    use GeneratesDocumentNumbers, ScopesToBranch;

    /**
     * حارسٌ ثانٍ داخل المتحكّم — إلى جانب حارس المسار.

     * وهو ليس تكراراً بلا فائدة: المسارُ يُحرَس في ملفٍّ، والدالّةُ تُستدعى
     * من الملفّ ومن غيره. وهذا هو نمطُ
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController]
     * — الحارسان معاً لا أحدهما.
     *
     * ومساراتُ الإنتاج تُفصَل عن بقيّة الإعدادات: صلاحيتُها
     * `stock.production_paths.*` بأفعالها الأربعة، لا
     * `stock.settings.manufacturing` العامّة.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.settings.manufacturing')
            ->only(['index', 'General', 'general_settings', 'Manual', 'order_manual_status']);

        $this->middleware('permission.role:stock.production_paths.view')->only(['paths_index', 'paths_ajax', 'paths_show']);
        $this->middleware('permission.role:stock.production_paths.create')->only(['paths_create', 'paths_store']);
        $this->middleware('permission.role:stock.production_paths.edit')->only(['paths_edit', 'paths_update']);
        $this->middleware('permission.role:stock.production_paths.delete')->only(['paths_destroy']);
    }

    public function index()
    {
        return view('manufacturing::settings.index');
    }

    public function General()
    {
        $general_settings = ManufacturingGeneralSetting::first();

        return view('manufacturing::settings.general', compact('general_settings'));
    }

    public function Manual()
    {
        $order_manual_status = ManufacturingOrderStatuses::with('manualOrderStatus')->first();

        return view('manufacturing::settings.manual.index', compact('order_manual_status'));
    }

    public function general_settings(Request $request)
    {
        /*
            تغييرُ الإعداد يُسجَّل بقيمتيه — القديمة والجديدة.

            كان الحفظ يمرّ بلا أثرٍ في أيّ سجلّ. والمفتاحان هنا يغيّران سلوك
            الوحدة كلَّها: `quantity_exceeded` يسمح بتجاوز الكمية المخطَّطة،
            و`reserve_materials_on_order` يوقف حجزَ المواد عند إنشاء الأمر.
            فحين يظهر أمرٌ صُرفت مواده مرّتين، أو أمرٌ لم تُحجز مواده، لا
            يكون في النظام ما يقول من بدّل المفتاح ولا متى — والشاشةُ تعرض
            الحالة الراهنة وحدها.
        */
        $before = ManufacturingGeneralSetting::first();

        $settings = ManufacturingGeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'quantity_exceeded' => $request->has('quantity_exceeded') ? 1 : 0,
                // حجز المواد — انظر ReservationService
                'reserve_materials_on_order' => $request->has('reserve_materials_on_order') ? 1 : 0,
                // حقولُ نموذج الأمر وأقسامُه — ما يظهر وما يُلزَم
                'order_form_settings' => \Modules\Manufacturing\Support\OrderFormSettings::normalise(
                    (array) $request->input('order_fields', []),
                    (array) $request->input('order_sections', [])
                ),
            ]
        );

        \Modules\Manufacturing\Support\OrderFormSettings::flush();

        OperationLog::record(
            'updated',
            ManufacturingSources::LOG_SETTINGS,
            $settings->id,
            'تم تحديث **الإعدادات العامة للتصنيع**',
            [
                'old' => [
                    'quantity_exceeded' => (int) ($before->quantity_exceeded ?? 0),
                    'reserve_materials_on_order' => (int) ($before->reserve_materials_on_order ?? 0),
                ],
                'new' => [
                    'quantity_exceeded' => (int) $settings->quantity_exceeded,
                    'reserve_materials_on_order' => (int) $settings->reserve_materials_on_order,
                ],
            ]
        );

        return redirect()->route('manufacturing.settings.general')->with(['success' => 'تم تحديث الإعدادات بنجاح']);
    }

    public function order_manual_status(Request $request)
    {
        /*
            التحقّق قبل الحذف.

            `foreach ($request->name …)` كانت تُنفَّذ **بعد** حذف الحالات
            اليدوية كلِّها. فطلبٌ بلا `name` — أو بحقلٍ ليس مصفوفة — يحذف
            الحالات ثمّ يرمي على `foreach`، فيخرج المستخدم بشاشةٍ فارغة
            وأوامرُ تصنيعٍ تشير إلى حالاتٍ لم تعد موجودة.
        */
        $request->validate([
            'name' => 'nullable|array',
            'name.*' => 'required|string|max:255',
            'color' => 'nullable|array',
            'color.*' => 'nullable|string|max:32',
        ]);

        $order_manual_status = ManufacturingOrderStatuses::first();

        if (! $order_manual_status) {
            $order_manual_status = ManufacturingOrderStatuses::create([
                'active' => $request->has('active') ? 1 : 0,
            ]);
        } else {
            $order_manual_status->update([
                'active' => $request->has('active') ? 1 : 0,
            ]);
        }

        $previous = ManualManufacturingOrderStatuses::where('order_status_id', $order_manual_status->id)
            ->pluck('name')
            ->all();

        ManualManufacturingOrderStatuses::where('order_status_id', $order_manual_status->id)->delete();

        foreach ((array) $request->name as $index => $manualName) {
            ManualManufacturingOrderStatuses::updateOrCreate(
                [
                    'order_status_id' => $order_manual_status->id,
                    'name' => $manualName,
                ],
                [
                    'color' => $request->color[$index] ?? '#ffffff',
                ]
            );
        }

        OperationLog::record(
            'updated',
            ManufacturingSources::LOG_SETTINGS,
            $order_manual_status->id,
            'تم تحديث **حالات أمر التصنيع اليدوية**',
            [
                'old' => ['statuses' => $previous, 'active' => (int) ! empty($previous)],
                'new' => ['statuses' => array_values((array) $request->name), 'active' => (int) $order_manual_status->active],
            ]
        );

        return redirect()->route('Manufacturing.settings.index')->with(['success' => 'تم تحديث الإعداد بنجاح']);
    }

    public function paths_index(Request $request)
    {
        /*
            الجدولُ يُرسَل مع الصفحة نفسِها.

            كانت الشاشةُ تُفتح بجدولٍ فارغ («لا مسارات إنتاج») ولا تُحمَّل
            المسارات إلّا بعد أوّل بحث — فيظنّ المستخدم أن لا مسارات عنده.
            صارت كشاشة الفواتير: الصفحةُ الأولى مع الطلب الأوّل، والبحثُ
            والتنقّل بعدها يستبدلان الجدول وحدَه.
        */
        $paths = $this->pathsQuery($request)->paginate($this->pathsPerPage($request));

        if ($request->ajax()) {
            return response()->json([
                'html' => view('manufacturing::settings.Paths.table', compact('paths'))->render(),
                'total' => $paths->total(),
            ]);
        }

        // مراحلُ المسارات الظاهرة للمستخدم وحدها — لا مراحلُ جهاتٍ لا يراها
        $production_stages = ProductionStage::select('id', 'stage_name')
            ->whereIn('production_paths_id', $this->scopeToActiveBranch(ProductionPath::query())->select('id'))
            ->orderBy('stage_name')
            ->get();

        return view('manufacturing::settings.Paths.index', compact('production_stages', 'paths'));
    }

    // المسارُ القديم (POST) — يبقى لمن يناديه، ويردّ ما يردّه الفهرس
    public function paths_ajax(Request $request)
    {
        $paths = $this->pathsQuery($request)->paginate($this->pathsPerPage($request));

        return response()->json([
            'html' => view('manufacturing::settings.Paths.table', compact('paths'))->render(),
            'total' => $paths->total(),
        ]);
    }

    private function pathsPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return $perPage > 0 ? min($perPage, 100) : 10;
    }

    /**
     * استعلامُ القائمة — نطاقُ الجهة أوّلاً ثمّ البحث.
     *
     * مسارُ الإنتاج ترتيبُ مراحل خطٍّ بعينه في مصنعٍ بعينه، ويُختار عند
     * إنشاء أمر التصنيع. فمن وقف على جهةٍ رأى مساراتِها وما تحتها، ومن
     * وقف على الفرع الرئيسيّ رأى المنشأةَ كلَّها — انظر [ScopesToBranch].
     */
    private function pathsQuery(Request $request)
    {
        $query = $this->applyBranchFilter(
            ProductionPath::with(['stages:id,production_paths_id,stage_name', 'createdBy:id,name']),
            $request
        );

        // البحث بالاسم أو الكود
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('code', 'LIKE', "%{$searchTerm}%");
            });
        }

        // فلترة حسب مرحلة الإنتاج
        if ($request->filled('production_stage_id')) {
            $query->whereHas('stages', function ($q) use ($request) {
                $q->where('id', $request->production_stage_id);
            });
        }

        // تاريخ الإضافة
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function paths_create()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('production-routes');

        return view('manufacturing::settings.Paths.create', compact('serial_number'));
    }

    public function paths_store(Request $request)
    {
        /*
            `code` لم يعد يُقبل من الطلب — ولا يُتحقَّق منه.

            كانت `paths_create()` تعرض الرقم التالي بـ`previewDocumentNumber`
            — معاينةٌ لا تستهلك — ثمّ يُحفظ ما في الحقل، والحقلُ قابل
            للتحرير. فبادئةُ «الترقيم المتسلسل» وطولُها وعدّادُها تحكم
            العرضَ لا التخزين، والتسلسلُ ينكسر بأوّل تعديلٍ يدوي.

            وأثرٌ ثانٍ: `unique` تحقُّقٌ لا قفل — طلبان متزامنان يقرآن الرقم
            المعروض نفسه ويجتازان التحقّق معاً، فيسقط أحدهما على قيد
            الجدول. و`issueDocumentNumber` تعمل داخل معاملةٍ بقفلٍ وتتخطّى
            المحجوز فعلاً، فلا تصادم.

            وهي قاعدةُ [\Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers]
            نفسُها: «ولا يُقبل رقمٌ من الطلب».
        */
        $request->validate([
            'name' => 'required|string|max:255',
            'stage_name' => 'required|array|min:1',
            'stage_name.*' => 'required|string|max:255',
        ]);

        $path = ProductionPath::create([
            'name' => $request->name,
            'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_ROUTES),
            'created_by' => auth()->user()->id,
            // الفرع يُختم مع المستند — انظر ScopesToBranch
        ] + $this->newDocumentBranchAttributes());

        foreach ($request->stage_name as $stageName) {
            ProductionStage::create([
                'production_paths_id' => $path->id,
                'stage_name' => $stageName,
            ]);
        }

        /*
            `created` لا `logged`.

            `logged` ليست عملية — هي كلمةٌ واحدة تُكتب لكل فعلٍ في هذه
            الشاشة: الإضافةُ والتعديل والحذف ثلاثتُها تُسجَّل بها. فشاشةُ
            «سجلّ النشاط» تعرض ثلاثة أسطرٍ متطابقةِ الأيقونة واللون لا
            يميّزها إلّا نصُّ الوصف، والتصفيةُ بالعملية عديمةُ الجدوى.
            و`OperationLog` يشتقّ منها مفتاحَ الحدث في «سجلّ الأحداث»
            (`pathMan.logged`) — فيصير الجميع حدثاً واحداً.
        */
        OperationLog::record(
            'created',
            ManufacturingSources::LOG_PATH,
            $path->id,
            'تم إضافة مسار الإنتاج **'.$path->name.'** برقم '.$path->code,
        );

        return redirect()->route('manufacturing.paths.show', $path->id)
            ->with(['success' => 'تمت إضافة مسار الإنتاج بنجاح']);
    }

    public function paths_show($id)
    {
        $path = ProductionPath::findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `show/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($path->branch_id);

        $paths = $this->scopeToActiveBranch(ProductionPath::with('stages'))
            ->orderBy('created_at', 'desc')
            ->get();
        $logs = ModelsLog::where('type', ManufacturingSources::LOG_PATH)
            ->where('type_id', $id)
            ->whereHas('pathMan') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('manufacturing::settings.Paths.show', compact('logs', 'path', 'paths'));
    }

    public function paths_edit($id)
    {
        $path = ProductionPath::findOrFail($id);
        $this->authorizeBranchScope($path->branch_id);

        return view('manufacturing::settings.Paths.edit', compact('path'));
    }

    public function paths_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'stage_name' => 'required|array|min:1',
            'stage_name.*' => 'required|string|max:255',
        ]);

        $path = ProductionPath::findOrFail($id);
        $this->authorizeBranchScope($path->branch_id);

        $previousStages = ProductionStage::where('production_paths_id', $id)->pluck('stage_name')->all();
        $previousName = $path->name;

        /*
            الرقمُ كما صدر، والفرعُ كما خُتم.

            تعديلُ المستند لا يعيد ترقيمه — الرقم مرجعٌ خارجي تحمله
            الشاشاتُ والمطبوعات وسجلُّ العمليات. ولا ينقله إلى فرعٍ آخر:
            نقلُ مسارٍ ينقل معه أوامرَ التصنيع التي تشير إليه وقيودَها،
            وهي لا تُنقل. و`keepDocumentNumber` تُصدر رقماً لسجلٍّ قديمٍ
            وصل بلا رقمٍ أصلاً — ولا تمسّ ما له رقم.
        */
        $path->update([
            'name' => $request->name,
            'code' => $this->keepDocumentNumber($path->code, ManufacturingSources::SECTION_ROUTES),
            // «تابع لـ» — المسارُ بيانٌ مرجعيّ بلا قيود، فيُنقل بين الجهات
            'branch_id' => $this->editedBranchId($path->branch_id),
            'updated_by' => auth()->user()->id,
        ]);

        /*
            المراحلُ تُحدَّث في مواضعها ولا تُمسح وتُعاد.

            كان التعديلُ يحذف المراحلَ كلَّها ثمّ يُنشئها من جديد، فتتبدّل
            معرّفاتُها — وبنودُ أوامر التصنيع وقوائمِ المواد تشير إلى تلك
            المعرّفات. فتعديلُ اسم مرحلةٍ واحدة كان يقطع مراحلَ كلِّ أمرٍ
            على هذا المسار. الآن: المرحلةُ الموجودة يُحدَّث اسمُها، والزائدةُ
            تُضاف، والمحذوفةُ تُحذف وحدَها.
        */
        $existing = ProductionStage::where('production_paths_id', $id)->orderBy('id')->get()->values();
        $names = array_values($request->stage_name);

        foreach ($names as $index => $stageName) {
            if (isset($existing[$index])) {
                $existing[$index]->update(['stage_name' => $stageName]);
            } else {
                ProductionStage::create([
                    'production_paths_id' => $path->id,
                    'stage_name' => $stageName,
                ]);
            }
        }

        foreach ($existing->slice(count($names)) as $surplus) {
            try {
                $surplus->delete();
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with([
                    'error' => 'تعذّر حذف المرحلة «'.$surplus->stage_name.'» — مستعملة في أوامر تصنيع أو قوائم مواد.',
                ]);
            }
        }

        OperationLog::record(
            'updated',
            ManufacturingSources::LOG_PATH,
            $path->id,
            'تم تحديث مسار الإنتاج **'.$path->name.'**',
            [
                'old' => ['name' => $previousName, 'stages' => $previousStages],
                'new' => ['name' => $path->name, 'stages' => array_values($request->stage_name)],
            ]
        );

        return redirect()->route('manufacturing.paths.show', $path->id)
            ->with(['success' => 'تم تحديث مسار الإنتاج بنجاح']);
    }

    public function paths_destroy($id)
    {
        $path = ProductionPath::findOrFail($id);
        $this->authorizeBranchScope($path->branch_id);

        // الاسم والرقم قبل الحذف — بعده يبقى الكائن في الذاكرة لكن السجلّ
        // يُقرأ لاحقاً من `logs` وحدها، فيجب أن يحمل ما يعرّف المحذوف
        $name = $path->name;
        $code = $path->code;

        try {
            ProductionStage::where('production_paths_id', $id)->delete();
            $path->delete();

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_PATH,
                $id,
                'تم حذف مسار الإنتاج **'.$name.'**'.($code ? ' برقم '.$code : ''),
            );
        } catch (\Throwable $e) {
            /*
                الحذفُ قد يُردّ بقيدٍ مرجعي: مراحلُ الإنتاج مرجعٌ إلزامي في
                بنود أوامر التصنيع. وكان يُترك بلا `catch` فيخرج المستخدم
                بصفحة 500، ولا يبقى منه إلّا سطرٌ في `laravel.log`.
            */
            OperationLog::failure($e, ManufacturingSources::LOG_PATH, 'paths_destroy', $id);

            return redirect()->route('manufacturing.paths.index')
                ->with(['error' => 'تعذّر حذف مسار الإنتاج — قد تكون مراحله مستعملة في أوامر تصنيع قائمة.']);
        }

        /*
            `success` لا `error`.

            كان الحذفُ الناجح يُبلَّغ في خانة الخطأ، فيظهر شريطٌ أحمر يقول
            «تم الحذف بنجاح» — فيظنّ المستخدم أن العملية أخفقت ويعيدها.
        */
        return redirect()->route('manufacturing.paths.index')
            ->with(['success' => 'تم حذف مسار الإنتاج بنجاح']);
    }
}
