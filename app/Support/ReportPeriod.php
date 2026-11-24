<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * فترة التقارير واللوحات — مصدر واحد لحدود التاريخ في كل الوحدات.
 *
 * رُفِع من `Modules\Purchases\Support` إلى النواة المشتركة حين احتاجته
 * لوحة المبيعات: الوحدات لا تستورد بعضها في هذا المشروع (ولا سطر واحد
 * يفعل)، ونسخُ منطق الفترة مرّتين هو عين العلّة التي كُتب هذا الصنف
 * ليمنعها. و`Modules\Purchases\Support\ReportPeriod` صار وارثاً منه
 * فلم يتغيّر شيء في شيفرة المشتريات.
 *
 * كانت كل شاشة تقرير تحمل نسختها من `switch($period)`: واحدة تقرأ
 * `date_from`/`date_to` وأخرى `from_date`/`to_date`، و«سنوي» في إحداها
 * `subDays(365)` وفي الأخرى `startOfYear()`. فالرقم نفسه في رابطين
 * يعطي فترتين، وإجماليّا الشاشتين لا يتطابقان.
 *
 * المفاتيح المعتمدة: `today` و`week` و`month` و`year` — حدودٌ تقويمية
 * لأن «تقرير شهري» يعني الشهر الجاري لا آخر ثلاثين يوماً. وتبقى الأرقام
 * القديمة (1، 7، 30، 90، 180، 365) مفهومةً فلا ينكسر رابط محفوظ.
 */
class ReportPeriod
{
    /** المفاتيح المعروضة في أزرار الفترة */
    public const TABS = [
        'today' => 'يومي',
        'week' => 'أسبوعي',
        'month' => 'شهري',
        'quarter' => 'ربع سنوي',
        'year' => 'سنوي',
    ];

    /** الأرقام القديمة ← المفاتيح الجديدة */
    protected const LEGACY = [
        '1' => 'today',
        '7' => 'week',
        '30' => 'month',
        '90' => 'quarter',
        '180' => 'half',
        '365' => 'year',
    ];

    public function __construct(
        public readonly string $key,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $label,
    ) {}

    /**
     * الفترة كما طلبها الرابط أو النموذج.
     *
     * تاريخان صريحان يتقدّمان على المفتاح دائماً، ويُقبل الاسمان
     * المستعملان في الشاشات القائمة: `date_from`/`date_to` و
     * `from_date`/`to_date`.
     */
    public static function fromRequest(Request $request, string $default = 'month'): static
    {
        $from = $request->input('date_from') ?: $request->input('from_date');
        $to = $request->input('date_to') ?: $request->input('to_date');

        if ($from && $to) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();

            /* عكسُ التاريخين كان يُصحَّح صامتاً: من أدخل «إلى» قبل «من» يظنّ
               نفسَه يفلتر بمدىً لم يطلبه أصلاً، فتخرج له نتائجُ مدىً آخر
               بلا أي تنبيه. صارت شاشةً فارغةً (مدىً معكوسٌ لا يطابقه
               `whereBetween` شيء) — وهذا وحده كان يُترَك يُخمَّن سببَه.
               فتُقال الرسالةُ صراحةً هنا، وتُعاد فترةٌ صالحة بدل المعكوسة
               كي لا تبقى الشاشةُ فارغةً بلا تفسير أصلاً.

               الرسالةُ عبر `flash()` — القناة التي تعرضها `layouts.alerts.error`
               في كل شاشات التقارير أصلاً. هذا المصنعُ الساكن نقطةُ عبورٍ
               واحدة تقرؤها عشرات شاشات التقارير في المبيعات والمشتريات،
               فإصلاحه هنا يبلغها كلَّها بلا لمس كلّ متحكّمٍ على حدة. */
            if ($toDate->lt($fromDate)) {
                $request->session()->flash('error', 'تاريخ «إلى» لا يمكن أن يكون قبل تاريخ «من». عُرضت الفترةُ الافتراضية بدلاً من ذلك.');

                return self::make($request->input('period', $default) ?: $default);
            }

            return new static('custom', $fromDate, $toDate, 'فترة مخصّصة');
        }

        return self::make($request->input('period', $default) ?: $default);
    }

    /** الفترة من مفتاحها — أو الافتراضية إن كان المفتاح مجهولاً */
    public static function make(string $key): static
    {
        $key = self::LEGACY[$key] ?? $key;
        $now = Carbon::now();

        return match ($key) {
            'today' => new static('today', $now->copy()->startOfDay(), $now->copy()->endOfDay(), 'اليوم'),
            'yesterday' => new static('yesterday', $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'أمس'),
            'week' => new static('week', $now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'الأسبوع الحالي'),
            'quarter' => new static('quarter', $now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), 'الربع الحالي'),
            'half' => new static('half', $now->copy()->subDays(179)->startOfDay(), $now->copy()->endOfDay(), 'آخر ١٨٠ يوماً'),
            'year' => new static('year', $now->copy()->startOfYear(), $now->copy()->endOfYear(), 'السنة الحالية'),
            default => new static('month', $now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'الشهر الحالي'),
        };
    }

    /** أوّل لحظة قبل الفترة — لأرصدة ما قبل المدة */
    public function beforeStart(): Carbon
    {
        return $this->from->copy()->subDay()->endOfDay();
    }

    public function labelWithDates(): string
    {
        return $this->label.' ('.$this->from->format('d/m/Y').' — '.$this->to->format('d/m/Y').')';
    }

    /** وحدة التجميع الزمني المناسبة لطول الفترة: يوم أو شهر */
    public function trendUnit(): string
    {
        return $this->from->diffInDays($this->to) > 62 ? 'month' : 'day';
    }
}
