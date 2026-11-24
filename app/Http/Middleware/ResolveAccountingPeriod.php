<?php

namespace App\Http\Middleware;

use App\Support\AccountingPeriodFilter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * فترةٌ مُختارة تصير تاريخين قبل أن يقرأ المتحكّم شيئاً.
 *
 * **المشكلة**
 *
 * تقارير وحدة الحسابات خمسةٌ وعشرون شاشة، وكلٌّ منها تسمّي حقلَي المدى
 * باسمها: `from_date`/`to_date` هنا، و`start_date`/`end_date` هناك،
 * و`date_from`/`date_to` في ثالثة. وإضافةُ «الفترة المحاسبية» إليها واحدةً
 * واحدةً تعني خمسةً وعشرين تعديلاً في المتحكّم، وخمسةً وعشرين موضعاً
 * ينساه من يضيف تقريراً جديداً.
 *
 * **الحل**
 *
 * الشاشة ترسل `period_id`، وهذا الوسيط يترجمه إلى حدَّي الفترة في كل زوجٍ
 * من أسماء الحقول المعروفة قبل وصول الطلب. فالمتحكّم يقرأ `from_date` كما
 * كان يقرؤه، ولا يعلم أنّ مصدره قائمةٌ لا حقلُ تاريخ.
 *
 * المُدخَل الصريح أقوى: إن كتب المستخدم تاريخاً بيده لم يُدهَس — هذا
 * الوسيط يملأ الزوجَ الفارغ وحده ويحترم ما وصله مملوءاً.
 */
class ResolveAccountingPeriod
{
    /**
     * أزواج أسماء الحقول المستعملة في تقارير الوحدة.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DATE_KEYS = [
        ['from_date', 'to_date'],
        ['start_date', 'end_date'],
        ['date_from', 'date_to'],
        ['issue_date_from', 'issue_date_to'],
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            $this->sanitizeRanges($request);
        }

        if (! $request->filled('period_id')) {
            return $next($request);
        }

        /*
            فترةٌ بعينها أو **سنةٌ كاملة** (`year:<id>`). كان البحث هنا
            `AccountingPeriod::find()` وحده، فاختيارُ السنة — وهو الخيار
            الأوّل في القائمة وافتراضيُّها — لا يطابق فترةً فيمرّ الطلب بلا
            تاريخين، ويُقرأ التقرير على افتراضيّه لا على السنة المختارة.
        */
        try {
            $resolved = AccountingPeriodFilter::resolve($request);
        } catch (Throwable $e) {
            // جدول الفترات غير مهيّأ: يمرّ الطلب كما هو
            return $next($request);
        }

        // «كل الفترات» و«بلا فترة» والقيمة المجهولة: لا مدى يُفرض
        if ($resolved['is_default'] || empty($resolved['from']) || empty($resolved['to'])) {
            return $next($request);
        }

        $period = $resolved['period'];
        $from = $resolved['from'];
        $to = $resolved['to'];

        /*
            الزوجُ الفارغ وحده يُملأ. كان يُكتب فوق ما وصل، والقائمةُ تُرسل
            السنةَ الجارية دائماً — فكلُّ مدًى كتبه المستخدم بيده في تقريرٍ
            يُدهَس بحدَّي السنة قبل أن يراه المتحكّم.
        */
        $merge = [];

        foreach (self::DATE_KEYS as [$fromKey, $toKey]) {
            if (! $request->filled($fromKey) && ! $request->filled($toKey)) {
                $merge[$fromKey] = $from;
                $merge[$toKey] = $to;
            }
        }

        $request->merge($merge);

        // اسم الفترة يُعرض في ترويسة التقرير المطبوع
        if ($period) {
            $request->attributes->set('accounting_period', $period);
        }
        $request->attributes->set('accounting_period_label', $resolved['label']);

        return $next($request);
    }

    /**
     * تاريخٌ في الرابط لا يُقرأ (`from_date=abc`، `31/31/2025`) كان يُسقط
     * التقرير بخطأ 500 في `Carbon::parse` — يُحذف هنا فيأخذ المتحكّم
     * افتراضيَّه. ومدىً مقلوبٌ (من بعد إلى) يُعدَل بدل أن يُرجع تقريراً فارغاً.
     * على GET وحدها: النماذج المُرسَلة تبقى لقواعد التحقق فيها.
     */
    private function sanitizeRanges(Request $request): void
    {
        foreach (self::DATE_KEYS as [$fromKey, $toKey]) {
            foreach ([$fromKey, $toKey] as $key) {
                if ($request->query->has($key) && $request->filled($key) && ! $this->readableDate($request->query($key))) {
                    $request->query->remove($key);
                }
            }

            $from = $request->query($fromKey);
            $to = $request->query($toKey);

            if (is_string($from) && is_string($to) && $from !== '' && $to !== ''
                && strtotime($from) > strtotime($to)) {
                $request->query->set($fromKey, $to);
                $request->query->set($toKey, $from);
            }
        }
    }

    private function readableDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            $year = Carbon::parse($value)->year;
        } catch (Throwable $e) {
            return false;
        }

        return $year >= 1900 && $year <= 2200;
    }
}
