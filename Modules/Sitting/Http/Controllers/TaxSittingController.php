<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxSitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sitting\Http\Requests\TaxSettingsRequest;

class TaxSittingController extends Controller
{
    /**
     * الجداول التي تشير إلى ضريبة بمعرّفها.
     *
     * تُفحَص قبل الحذف: الضريبة المستعملة في فاتورة صادرة لا تُحذف، وإلا
     * صارت الفاتورة تشير إلى ضريبة لا وجود لها فيتعذّر إعادة احتساب
     * إجماليها أو تفسيره لمراجع.
     *
     * @var array<string, array<int, string>>
     */
    protected const REFERENCES = [
        /* أعمدةٌ تحمل **معرّفَ** الضريبة فعلاً. كانت القائمةُ أعمدةَ النِّسب
           (`invoice_items.tax_1` = 15.00): فيُقارَن معرّفٌ بنسبة — ضريبةُ الـ15%
           (معرّفها 2) تظهر «بلا استعمال» وتُحذف، وضريبةٌ معرّفُها 15 كانت
           ستُمنع بكل سطرٍ نسبتُه 15. والفواتيرُ تحفظ اسمَ الضريبة ونسبتَها
           لقطةً في `taxs_invoice`، فلا تتأثّر بالحذف ولا تمنعه. */
        'installments' => ['tax_id'],
        'reservations' => ['tax_id'],
        'reservation_services' => ['tax_id'],
        'payment_vouchers' => ['tax_id'],
        'online_order_items' => ['tax_1_id', 'tax_2_id'],
    ];

    public function __construct()
    {
        $this->middleware('permission:settings_edit_tax_settings');
    }

    public function index()
    {
        $taxes = TaxSitting::orderBy('id')->get();

        return view('sitting::tax_sitting.index', [
            'tax' => $taxes,
            'usage' => $taxes->mapWithKeys(
                fn (TaxSitting $tax) => [$tax->id => $this->usageCount($tax->id)]
            ),
        ]);
    }

    /**
     * حفظ جدول الضرائب دفعةً واحدة.
     *
     * داخل معاملة: الشاشة تحفظ عدّة صفوف معاً، وفشل الصفّ الخامس بعد نجاح
     * أربعة يترك جدول ضرائب نصفه قديم ونصفه جديد.
     */
    public function updateAll(TaxSettingsRequest $request)
    {
        $rows = $request->validated('rows');
        $comprehensiveId = $request->validated('comprehensive_tax_id');

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $comprehensiveId, &$created, &$updated) {
            foreach ($rows as $row) {
                $attributes = [
                    'name' => $row['name'],
                    'tax' => $row['tax'],
                    'type' => $row['type'],
                ];

                if ($row['id']) {
                    TaxSitting::whereKey($row['id'])->update($attributes);
                    $updated++;
                } else {
                    TaxSitting::create($attributes);
                    $created++;
                }
            }

            /* الضريبة الشاملة الافتراضية للمستندات — واحدةٌ فقط: يُطفَأ
               العلمُ عن الكلّ ثمّ يُرفَع عن المختارة، فاختيارُ «تعطيل»
               (فارغٌ) يُعيد ضريبةَ كلّ سطر في كلّ المستندات. */
            TaxSitting::query()->update(['is_comprehensive' => false]);

            if ($comprehensiveId) {
                TaxSitting::whereKey($comprehensiveId)->update(['is_comprehensive' => true]);
            }
        });

        return redirect()->route('TaxSitting.index')->with('success', match (true) {
            $created && $updated => __('settings.tax.saved_both', ['created' => $created, 'updated' => $updated]),
            (bool) $created => __('settings.tax.saved_created', ['count' => $created]),
            (bool) $updated => __('settings.tax.saved_updated', ['count' => $updated]),
            default => __('settings.common.no_changes'),
        });
    }

    public function destroy($id)
    {
        $tax = TaxSitting::find($id);

        if (! $tax) {
            return response()->json(['success' => false, 'message' => __('settings.tax.not_found')], 404);
        }

        $usage = $this->usageCount($tax->id);

        if ($usage > 0) {
            return response()->json([
                'success' => false,
                'message' => __('settings.tax.cannot_delete', ['name' => $tax->name, 'count' => number_format($usage)]),
            ], 422);
        }

        // الضريبةُ الشاملة تُطبَّق على المستندات كلِّها؛ حذفُها يطفئ الميزةَ صامتاً
        if ($tax->is_comprehensive) {
            return response()->json([
                'success' => false,
                'message' => __('settings.tax.cannot_delete_comprehensive', ['name' => $tax->name]),
            ], 422);
        }

        $name = $tax->name;

        try {
            $tax->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // مرجعٌ في جدولٍ لم يُحصَ أعلاه — قاعدةُ البيانات تمنع الحذف
            return response()->json([
                'success' => false,
                'message' => __('settings.tax.cannot_delete', ['name' => $name, 'count' => '1+']),
            ], 422);
        }

        Log::info('حُذفت ضريبة', ['name' => $name, 'user_id' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => __('settings.tax.deleted', ['name' => $name]),
        ]);
    }

    /** عدد السجلّات التي تستعمل هذه الضريبة عبر كل الجداول المرجعية. */
    protected function usageCount(int $taxId): int
    {
        $total = 0;

        foreach (self::REFERENCES as $table => $columns) {
            try {
                $query = DB::table($table);

                foreach ($columns as $index => $column) {
                    $index === 0
                        ? $query->where($column, $taxId)
                        : $query->orWhere($column, $taxId);
                }

                $total += $query->count();
            } catch (\Throwable) {
                // جدول غير موجود في هذا التركيب — لا يمنع فحص البقية
                continue;
            }
        }

        return $total;
    }
}
