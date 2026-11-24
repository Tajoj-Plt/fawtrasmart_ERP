<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Purchases\Services\PurchaseSettings;

/**
 * إعدادات قسم المشتريات — شاشة واحدة لخيارات الأقسام كلّها.
 *
 * المتحكّم السابق كان يطلب حقل «رقم الفاتورة التالي» إلزامياً ثم لا يحفظه —
 * والرقم يأتي أصلاً من `DocumentNumberService`. وكان يعرّف `toggle` و`reset`
 * و`export` بلا مسارٍ لأيٍّ منها، فثلاث دوالّ لا يبلغها طلب. وشاشته لم تكن
 * موصولة بالقائمة الجانبية أصلاً: رابط «إعدادات فواتير الشراء» فيها كان
 * يشير إلى شاشة القوالب.
 *
 * @see PurchaseSettings سجلّ المفاتيح ومصدر القراءة المخزَّنة
 */
class PurchaseSettingsController extends Controller
{
    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً رغم أن مجموعة
     * `settings` في كتالوج `PurchasesPermissions` معرَّفة له صراحةً.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.settings.view', ['only' => ['index']]);
        $this->middleware('permission:purchases.settings.edit', ['only' => ['update', 'reset']]);
    }

    /**
     * شاشة خيارات المشتريات.
     *
     * `sync()` قبل العرض: إضافة مفتاح إلى `PurchaseSettings::catalog()` تكفي
     * وحدها ليظهر ويُحفَظ، بلا ترحيلٍ يرافقها.
     */
    public function index()
    {
        PurchaseSettings::sync();

        return view('purchases::purchases.settings.index', [
            'catalog' => PurchaseSettings::catalog(),
            'active' => PurchaseSettings::all(),
            'activeCount' => PurchaseSettings::activeCount(),
            'totalCount' => count(PurchaseSettings::keys()),
            // أقسامُ «موظّفي المشتريات» — القائمةُ والمختارُ منها
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
            'selectedDepartments' => PurchaseSettings::departmentIds(),
        ]);
    }

    /**
     * حفظ الخيارات.
     *
     * المربّع غير المؤشَّر لا يُرسَل أصلاً، فالحفظ يُطفئ الكلّ ثم يُشعل
     * المرسَل — ولا يكفي تحديث المرسَل وحده وإلّا تعذّر إطفاء إعداد.
     * والقيد على المفاتيح من السجلّ لا من الجدول: ما ليس في `catalog()`
     * لا يُقبل ولو كان في قاعدة البيانات.
     */
    public function update(Request $request)
    {
        $known = array_keys(PurchaseSettings::keys());

        $validated = $request->validate([
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['string', 'in:'.implode(',', $known)],
            // أقسامُ موظّفي المشتريات — قائمةُ معرّفات لا مفتاحُ تشغيل
            'departments' => ['sometimes', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],
        ], [
            'settings.array' => __('purchase_settings.errors.array'),
            'settings.*.in' => __('purchase_settings.errors.unknown_key'),
            'departments.*.exists' => __('purchase_settings.errors.unknown_department'),
        ]);

        $chosen = array_values(array_unique($validated['settings'] ?? []));
        $departments = $validated['departments'] ?? [];

        try {
            DB::transaction(function () use ($chosen, $known, $departments) {
                PurchaseInvoiceSetting::whereIn('setting_key', $known)->update(['is_active' => false]);

                if ($chosen !== []) {
                    PurchaseInvoiceSetting::whereIn('setting_key', $chosen)->update(['is_active' => true]);
                }

                PurchaseSettings::saveDepartmentIds($departments);
            });

            PurchaseSettings::flush();

            return redirect()
                ->route('purchases.settings.index')
                ->with('success', __('purchase_settings.messages.saved'));
        } catch (\Throwable $e) {
            Log::error('تعذّر حفظ إعدادات المشتريات: '.$e->getMessage());

            return redirect()->back()
                ->with('error', __('purchase_settings.messages.save_failed'))
                ->withInput();
        }
    }

    /** إعادة المفاتيح كلّها إلى افتراضيّاتها في `catalog()`. */
    public function reset()
    {
        try {
            DB::transaction(function () {
                foreach (PurchaseSettings::defaults() as $key => $default) {
                    PurchaseInvoiceSetting::where('setting_key', $key)->update(['is_active' => $default]);
                }
            });

            PurchaseSettings::flush();

            return redirect()
                ->route('purchases.settings.index')
                ->with('success', __('purchase_settings.messages.reset'));
        } catch (\Throwable $e) {
            Log::error('تعذّرت إعادة تعيين إعدادات المشتريات: '.$e->getMessage());

            return redirect()->back()->with('error', __('purchase_settings.messages.reset_failed'));
        }
    }
}
