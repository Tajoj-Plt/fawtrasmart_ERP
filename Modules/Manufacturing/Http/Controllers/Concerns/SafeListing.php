<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * ترتيب وترقيم آمنان لنقاط getData، ورسائل خطأ لا تسرّب شيئاً.
 *
 * ── لماذا هذا الملف ──────────────────────────────────────────────────
 *
 * ثلاثة متحكّمات تكرّر نفس العيب بثلاثة أسماء معاملات مختلفة:
 *
 *   MOC:93-99             sort_by / sort_order / per_page
 *   Workstations:47-53    sort_by / sort_order / per_page
 *   IndirectCosts:97-102  sort_field / sort_direction / per_page
 *
 * والعيب ثلاثي:
 *
 *   sort_order  →  Laravel يرمي InvalidArgumentException لأي قيمة غير
 *                  asc|desc ⇒ 500 غير معالج بمجرد `?sort_order=x`
 *
 *   sort_by     →  ليس حقن SQL (الـ Grammar يغلّف الاسم)، لكن عموداً
 *                  مختلَقاً يعطي Unknown column يُعرض نصّه للمستخدم
 *                  ⇒ تعداد أعمدة وجداول عبر فروق الاستجابة
 *
 *   per_page    →  بلا سقف. `?per_page=1000000` يحمّل الجدول كاملاً
 *                  في الذاكرة ⇒ DoS بسطر واحد
 *
 * الـ trait يقبل الاسمين معاً (`sort_by` و`sort_field`) كي لا تنكسر
 * الواجهات القائمة، ويمكنك توحيدهما لاحقاً بهدوء.
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 *     use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
 *
 *     class IndirectCostsController extends Controller
 *     {
 *         use ResolvesAccountingAccounts, SafeListing, UsesDocumentCurrency;
 */
trait SafeListing
{
    /** السقف الأعلى لعدد الصفوف في الصفحة الواحدة. */
    protected int $maxPerPage = 100;

    /**
     * ترتيب بعمود من قائمة بيضاء واتجاه من قيمتين لا ثالث لهما.
     *
     * @param  array<string>  $allowed  أسماء الأعمدة المسموح الترتيب بها
     */
    protected function applySafeSort($query, Request $request, array $allowed, string $default = 'created_at')
    {
        $field = $request->get('sort_field', $request->get('sort_by', $default));
        $dir = strtolower((string) $request->get('sort_direction', $request->get('sort_order', 'desc')));

        if (! in_array($field, $allowed, true)) {
            $field = $default;
        }

        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return $query->orderBy($field, $dir);
    }

    /**
     * عدد صفوف الصفحة، بسقف.
     */
    protected function safePerPage(Request $request, int $default = 15, ?int $max = null): int
    {
        $max = $max ?? $this->maxPerPage;
        $perPage = (int) $request->get('per_page', $default);

        return $perPage > 0 ? min($perPage, $max) : $default;
    }

    /**
     * رسالة خطأ للمستخدم بلا تفاصيل داخلية.
     *
     * الموديول يعيد `$e->getMessage()` خاماً في 20+ موضعاً — يشمل
     * أجزاء استعلامات SQL وأسماء جداول وأعمدة ومسارات ملفات.
     *
     * والنمط الصحيح موجود في الملف نفسه (receiveMaterials:2303):
     *     if (config('app.debug')) { $errorMessage .= ': '.$e->getMessage(); }
     */
    protected function safeError(\Throwable $e, string $message): string
    {
        return $message.(config('app.debug') ? ' ('.$e->getMessage().')' : '');
    }

    /**
     * استجابة JSON موحّدة للخطأ في نقاط AJAX.
     */
    protected function safeErrorJson(\Throwable $e, string $message, int $status = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $this->safeError($e, $message),
        ], $status);
    }

    /**
     * ملخّص تجميعي محسوب على نسخة من الاستعلام قبل الترقيم.
     *
     * IndirectCostsController:103-121 يستدعي sum/avg على نفس الـ builder
     * بعد paginate() — و paginate يضيف LIMIT/OFFSET إلى الـ builder
     * نفسه، فيرثهما التجميع. في الصفحة الأولى تصحّ النتيجة صدفةً،
     * ومن الصفحة الثانية يتجاوز الـ OFFSET صفَّ التجميع فيعود فارغاً
     * ⇒ الإجمالي والمتوسط صفران بينما البيانات موجودة.
     *
     * الاستعمال — انسخ الاستعلام قبل الترقيم:
     *
     *     $summary = $this->aggregateBefore($query, 'total');
     *     $rows = $query->paginate($this->safePerPage($request));
     *
     * @return array{count:int, sum:float, avg:float}
     */
    protected function aggregateBefore($query, string $column): array
    {
        $row = (clone $query)
            ->selectRaw("COUNT(*) AS agg_count, COALESCE(SUM(`$column`), 0) AS agg_sum, COALESCE(AVG(`$column`), 0) AS agg_avg")
            ->first();

        return [
            'count' => (int) ($row->agg_count ?? 0),
            'sum' => (float) ($row->agg_sum ?? 0),
            'avg' => (float) ($row->agg_avg ?? 0),
        ];
    }
}
