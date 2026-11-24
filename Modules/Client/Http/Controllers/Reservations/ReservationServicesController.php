<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Http\Controllers\Reservations\Concerns\ScopesReservations;
use Modules\Client\Http\Requests\Reservations\StoreReservationServiceRequest;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Services\ServiceProductCatalog;
use Throwable;

/**
 * خدماتُ الحجز = **المنتجاتُ الخدمية** — وهذه الشاشةُ تضبط «ملفَّ حجز» كلٍّ منها.
 *
 * ═══════════ ما كان ═══════════
 *
 * كتالوجاً ثانياً للخدمات (`reservation_services`) باسمٍ وسعرٍ وضريبةٍ
 * خاصّة، والمنتجُ فيه اختياريّ. فكان نموذجُ الحجز يقرأ منه وحده — وجدولٌ
 * فارغ يعني «لا خدمات» ولو كان في المنتجات مئةُ منتجٍ خدمي.
 *
 * ═══════════ ما صار ═══════════
 *
 * القائمةُ هنا هي المنتجاتُ من نوع «خدمي» نفسُها، ويُحجَز أيٌّ منها فوراً
 * بالمدّة الافتراضية. والملفُّ (صفٌّ واحد لكل منتج) اختياريّ، يضيف ما لا
 * يعرفه جدول المنتجات: المدّة، ووقتا التحضير والتنظيف، والحدّ اليومي،
 * والفرع، والموظفون المؤهَّلون — **ولا يحمل اسماً ولا سعراً ولا ضريبة**:
 * تلك من المنتج، فتوافق الفاتورة دائماً.
 */
class ReservationServicesController extends Controller
{
    use ScopesReservations;

    public function __construct()
    {
        $this->middleware('permission:reservation_services.view', ['only' => ['index']]);
        $this->middleware('permission:reservation_services.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:reservation_services.edit', ['only' => ['edit', 'update', 'toggle']]);
        $this->middleware('permission:reservation_services.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $products = ServiceProductCatalog::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->orderBy('name')
            ->paginate(25, ['id', 'name', 'sale_price', 'tax1'])
            ->withQueryString();

        $ids = $products->getCollection()->pluck('id');

        return view('client::reservations.services.index', [
            'products' => $products,
            'profiles' => BookableService::with(['branch:id,name'])->withCount('employees')
                ->whereIn('product_id', $ids)->get()->keyBy('product_id'),
            // عددُ الحجوز على المنتج — دفعةً واحدة لا استعلاماً لكل صفّ
            'bookings' => Reservation::whereIn('product_id', $ids)
                ->selectRaw('product_id, COUNT(*) as c')->groupBy('product_id')->pluck('c', 'product_id'),
            'defaultDuration' => (int) ReservationSetting::current()->default_duration,
            // ملفّاتٌ قديمة بلا منتج — تبقى تعمل لحجوزها، ويُنبَّه إليها
            'legacyCount' => BookableService::whereNull('product_id')->count(),
        ]);
    }

    public function create(Request $request)
    {
        /*
            «خدمة جديدة» بلا منتج = إنشاءُ منتجٍ خدمي من شاشة المخزون نفسِها
            (طلب المستخدم 2026-09-29)، ثمّ يعود الحفظُ إلى ملفّ حجزه هنا
            (`return_to=reservations` في `ProductsController::store_services`).
        */
        if (! $request->filled('product_id') && \Illuminate\Support\Facades\Route::has('products.create_services')) {
            return redirect()->route('products.create_services', ['return_to' => 'reservations']);
        }

        try {
            $product = ServiceProductCatalog::resolve($request->integer('product_id'));
        } catch (ReservationException $e) {
            return redirect()->route('Reservations.services.index')->with('error', $e->getMessage());
        }

        // الملفُّ واحدٌ لكل منتج — الموجودُ يُعدَّل لا يُنشأ ثانيةً
        if ($existing = BookableService::forProduct($product->id)->first()) {
            return redirect()->route('Reservations.services.edit', $existing->id);
        }

        return view('client::reservations.services.create', $this->formData() + [
            'product' => $product,
            'service' => BookableService::profileFor($product),
        ]);
    }

    public function store(StoreReservationServiceRequest $request)
    {
        try {
            $service = DB::transaction(function () use ($request) {
                $service = BookableService::create($request->safe()->except('employees') + ['created_by' => auth()->id()]);
                $service->employees()->sync($request->input('employees', []));

                return $service;
            });
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'service_store'));
        }

        $service->load('product');

        OperationLog::record('created', 'ReservationServices', $service, "ضبط ملفّ حجز الخدمة «{$service->displayName()}»");

        return redirect()->route('Reservations.services.index')->with('success', __('reservations.messages.profile_saved'));
    }

    public function edit($id)
    {
        $service = BookableService::with(['employees', 'product'])->findOrFail($id);

        return view('client::reservations.services.edit', $this->formData() + [
            'service' => $service,
            'product' => $service->product,
        ]);
    }

    public function update(StoreReservationServiceRequest $request, $id)
    {
        $service = BookableService::with('product')->findOrFail($id);

        try {
            $service->fill($request->safe()->except('employees') + ['updated_by' => auth()->id()]);
            $changes = OperationLog::diff($service, ['duration', 'buffer_before', 'buffer_after', 'max_daily', 'branch_id', 'is_active']);

            DB::transaction(function () use ($service, $request) {
                $service->save();
                $service->employees()->sync($request->input('employees', []));
            });
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'service_update'));
        }

        OperationLog::record('updated', 'ReservationServices', $service, "تعديل ملفّ حجز الخدمة «{$service->displayName()}»", $changes);

        return redirect()->route('Reservations.services.index')->with('success', __('reservations.messages.profile_saved'));
    }

    /**
     * إيقافُ حجز الخدمة دون المساس بالمنتج — يبقى يُباع ويُفوتَر، ولا يُحجَز.
     */
    public function toggle($id)
    {
        $service = BookableService::with('product')->findOrFail($id);
        $service->update(['is_active' => ! $service->is_active, 'updated_by' => auth()->id()]);

        OperationLog::record('updated', 'ReservationServices', $service,
            ($service->is_active ? 'تفعيل' : 'إيقاف')." حجز الخدمة «{$service->displayName()}»");

        return back()->with('success', $service->is_active
            ? __('reservations.messages.profile_enabled')
            : __('reservations.messages.profile_disabled'));
    }

    /**
     * حذفُ الملفّ يُعيد الخدمةَ إلى المدّة الافتراضية — ولا يمسّ المنتجَ ولا الحجوز.
     *
     * والملفُّ المرتبطُ بحجوزٍ قديمة (`service_id`) لا يُحذف: تلك الحجوزُ تقرأ
     * منه مدّتها وتحضيرها. يُوقَف بدل ذلك.
     */
    public function destroy($id)
    {
        $service = BookableService::with('product')->withCount('reservations')->findOrFail($id);

        if ($service->reservations_count > 0) {
            return back()->with('error', __('reservations.errors.profile_in_use', ['n' => $service->reservations_count]));
        }

        $name = $service->displayName();
        $service->delete();

        OperationLog::record('deleted', 'ReservationServices', $service, "حذف ملفّ حجز الخدمة «{$name}»");

        return back()->with('success', __('reservations.messages.profile_deleted'));
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'treeNodes' => DataScope::filterNodes(AffiliationTree::nodes()),
            // المؤهَّلون يُختارون من قسمِ الإعدادات أو مسمّاها وحدَه
            'employees' => ReservationSetting::current()
                ->constrainEmployees(\App\Models\Employee::query()->whereIn('id', $this->scopedEmployees()->pluck('id')))
                ->orderBy('first_name')
                ->get(),
        ];
    }
}
