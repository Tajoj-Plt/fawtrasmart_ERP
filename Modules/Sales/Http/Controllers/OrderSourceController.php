<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;
use App\Http\Controllers\Controller;
use App\Models\OrderSource;
use App\Models\settingsOrderSource;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Throwable;

/**
 * مصادر الطلب — الطريقة التي أتى بها العميل.
 *
 * ثلاث وصلات بإعدادات النظام لم تكن هنا:
 *
 *   • **السجلّات الثلاثة** — لم يكن إضافةُ مصدر ولا تعديله ولا حذفه يترك
 *     أثراً في «سجلّ النشاطات» ولا «سجلّ الأحداث»، ولا يُكتب شيء في «سجلّ
 *     الأخطاء» حين يفشل الحفظ. وهذه شاشة تُحفَظ دفعةً واحدة: صفوفٌ تُضاف
 *     وتُعدَّل وترتيبٌ يتغيّر في إرسالٍ واحد، فغياب الأثر يعني أن «مَن
 *     غيّر هذه القائمة؟» بلا جواب.
 *
 *   • **الافتراضي المعلَّق** — حذفُ مصدرٍ كان يترك `default_order_source_id`
 *     يشير إليه. الإعداد يبقى في الجدول برقمٍ لا صفَّ له، فتُرسِله الشاشة
 *     في الحفظ التالي فيسقط على قاعدة `exists` — رسالةُ خطأ عن حقلٍ لم
 *     يمسّه المستخدم.
 *
 *   • **الفشل مردود لا مرميّ** — الحفظ يكتب صفوفاً وإعدادَين، وأيّ سطرٍ
 *     منها قد يرمي. كان الاستثناء يبلغ المستخدم صفحةَ خطأ بيضاء بعد أن
 *     كُتِب بعضُ الصفوف وبقي بعضها.
 */
class OrderSourceController extends Controller
{
    use Concerns\ChecksSubscriptionLimits;

    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesOrderSources';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.order_sources.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.order_sources.view', ['only' => ['index']]);
        $this->middleware('permission:sales.order_sources.manage', ['only' => ['storeOrUpdate', 'destroy', 'sort']]);
    }

    public function index()
    {
        $sources = OrderSource::orderBy('sort_order')->get();

        return view('sales::sitting.order_sources.index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::ORDER_SOURCES),
            'sources' => $sources,
            'default_id' => settingsOrderSource::get('default_order_source_id'),
            'is_mandatory' => settingsOrderSource::get('order_source_mandatory', '0'),
            'stats' => [
                'total' => $sources->count(),
                'active' => $sources->where('active', true)->count(),
            ],
        ]);
    }

    /**
     * حفظ الصفوف والإعدادات معاً.
     *
     * الترتيب يُؤخذ من موضع الصفّ في الإرسال لا من حقل `sort_order` القادم
     * من الصفحة: السحب يعيد ترتيب الصفوف في DOM، فترتيب الإرسال هو ما رآه
     * المستخدم — وقراءة الحقل القديم كانت تُعيد الترتيب إلى ما كان.
     */
    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'ids' => ['array'],
            'ids.*' => ['nullable', 'integer', 'exists:order_sources,id'],
            'name' => ['array'],
            'name.*' => ['required', 'string', 'max:255'],
            'active' => ['array'],
            'default_order_source_id' => ['nullable', 'integer', 'exists:order_sources,id'],
        ], [
            'name.*.required' => __('sales_settings.order_sources.name_required'),
        ]);

        $ids = $data['ids'] ?? [];
        $actives = $data['active'] ?? [];

        /* ** سقف اشتراك فوترة ** — الشاشةُ تحفظ الجدول كلَّه دفعةً واحدة،
           فالكمّية المطلوبة هي عددُ الصفوف **الجديدة** (بلا معرّف) لا عددُ
           الصفوف كلِّها؛ وتعديلُ صفٍّ قائمٍ لا يشغل مقعداً جديداً.

           والفحصُ قبل الحلقة لا داخلها: `PlanLimitObserver` يرمي عند الصفّ
           المتجاوِز فيترك ما سبقه محفوظاً — نصفَ حفظٍ لا يفهمه المستخدم.
           فيُردّ الحفظ كلُّه برسالةٍ واحدةٍ قبل أن يُكتب شيء. */
        $newRows = 0;

        foreach ($data['name'] ?? [] as $i => $unused) {
            if (empty($ids[$i])) {
                $newRows++;
            }
        }

        if ($newRows > 0 && $blocked = $this->subscriptionBlock(SalesPlanFeatures::ORDER_SOURCES, $newRows)) {
            return back()->withInput()->with('error', $blocked);
        }

        // للسجلّ: كم أُضيف وكم عُدّل. سردُ اثنين وعشرين صفّاً في وصفٍ واحد
        // لا يُقرأ، والعددان يقولان ما جرى
        $added = 0;
        $updated = 0;

        try {
            foreach ($data['name'] ?? [] as $i => $name) {
                $attributes = [
                    'name' => $name,
                    'active' => (bool) ($actives[$i] ?? false),
                    'sort_order' => $i,
                ];

                if (! empty($ids[$i])) {
                    OrderSource::where('id', $ids[$i])->update($attributes);
                    $updated++;
                } else {
                    OrderSource::create($attributes);
                    $added++;
                }
            }

            settingsOrderSource::set('default_order_source_id', $data['default_order_source_id'] ?? '');
            settingsOrderSource::set('order_source_mandatory', $request->boolean('order_source_mandatory') ? '1' : '0');
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'storeOrUpdate');

            return back()->withInput()->with('error', __('sales_settings.order_sources.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            null,
            __('sales_settings.order_sources.log_saved', ['added' => $added, 'updated' => $updated]),
            ['new' => [
                'added' => $added,
                'updated' => $updated,
                'default_order_source_id' => $data['default_order_source_id'] ?? null,
                'order_source_mandatory' => $request->boolean('order_source_mandatory'),
            ]]
        );

        return redirect()->route('order_sources.index')
            ->with('success', __('sales_settings.order_sources.saved'));
    }

    /**
     * حذف مصدر — تُستدعى من الصفحة بـfetch، فالردّ JSON لا إعادة توجيه.
     */
    public function destroy($id)
    {
        $source = OrderSource::find($id);

        if (! $source) {
            return response()->json(['status' => 'missing'], 404);
        }

        $name = $source->name;

        try {
            $source->delete();

            // الإعداد يشير إلى صفٍّ لم يعد موجوداً: يُمسَح معه، وإلّا أسقط
            // الحفظَ التالي على قاعدة `exists`
            if ((string) settingsOrderSource::get('default_order_source_id') === (string) $id) {
                settingsOrderSource::set('default_order_source_id', '');
            }
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return response()->json(['status' => 'failed'], 500);
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            __('sales_settings.order_sources.log_deleted', ['name' => $name]),
            ['old' => ['name' => $name]]
        );

        return response()->json(['status' => 'deleted']);
    }

    /** ترتيب السحب — يُستدعى من الصفحة عند إفلات الصفّ. */
    public function sort(Request $request)
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['nullable', 'integer'],
        ]);

        foreach ($request->input('order') as $i => $id) {
            if ($id) {
                OrderSource::where('id', $id)->update(['sort_order' => $i]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
