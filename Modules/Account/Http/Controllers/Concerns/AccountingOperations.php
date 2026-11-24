<?php

namespace Modules\Account\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Support\AccountingPeriodGuard;
use App\Support\OperationLog;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ثلاثة ضوابط تلزم كل عملية مالية في وحدة الحسابات.
 *
 * **1) الفترة المحاسبية**
 *
 * كل ما يكتب قيداً — سند صرف، سند قبض، تحويل خزينة، شيك، أصل، قيد يدوي —
 * كان يكتبه في أي تاريخ: شهرٌ أُقفل عمداً في شاشة «الفترات المحاسبية» لا
 * يمنع شيئاً. ولا معنى لإقفالٍ لا يَقفل.
 *
 * `guardPeriod()` يُنادى قبل أول كتابة، ويرمي خطأ تحقّقٍ يظهر فوق النموذج
 * كأي خطأ حقل — لا رسالة نظام خام ولا صفحة بيضاء.
 *
 * **2) السجلّات**
 *
 * كانت الشاشات تكتب `Log::create([...])` بيدها حين تكتب: بلا عنوان IP ولا
 * متصفّح ولا فرق قيم، ولا سطر في «سجلّ الأحداث»، ولا شيء في «سجلّ الأخطاء»
 * حين تفشل العملية — فثلاث شاشات في الإعدادات تعرض ما لم يُكتب فيها قطّ.
 * `recordOperation()` و`recordFailure()` يوزّعان على السجلّات الثلاثة معاً.
 *
 * **3) الأخطاء**
 *
 * الاستثناء الذي يُبتلع في `catch` بلا رسالة يترك المستخدم أمام شاشةٍ لم
 * يتغيّر فيها شيء ولا يدري لماذا. `failed()` يكتب الخطأ في سجلّ الأخطاء
 * ويُعيده رسالةً مقروءة.
 */
trait AccountingOperations
{
    /**
     * منع الكتابة في فترة مقفلة.
     *
     * @param  mixed  $date  تاريخ العملية
     * @param  string  $field  الحقل الذي تُعلَّق عليه الرسالة في النموذج
     *
     * @throws ValidationException
     */
    protected function guardPeriod($date, string $field = 'date'): void
    {
        $reason = AccountingPeriodGuard::blockReason($date);

        if ($reason !== null) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }

    /**
     * منع تكرار الحفظ: نقرتان سريعتان على «حفظ» — أو ضغطٌ ثانٍ قبل أن
     * يستجيب الزرّ لأول نقرة، أو إعادة إرسالٍ يسبّبها بطء الشبكة — كانت
     * تُنشئ سندي صرفٍ أو قبضٍ متطابقين: أحدهما زائد لا يقابله صرفٌ أو
     * قبضٌ فعلي، فيختلّ رصيدُ الخزينة والحساب المقابل بمقداره بصمت.
     *
     * القفلُ ذرّي (`Cache::lock`) على بصمة الطلب — المستخدم + الحقول
     * التي تُميّز السند فعلياً — لا على الطلب كلّه ولا على المستخدم
     * وحده: تعديلٌ في وصفٍ حرّ لا يبطل الحماية، وسندان مختلفان فعلاً من
     * نفس المستخدم في نفس الثانية يمرّان معاً.
     *
     * النجاح **لا يُفكّ القفل صراحةً**: يبقى ممسوكاً حتى تنقضي مهلته،
     * فتُغلَق نافذة التكرار تلقائياً بعد ثوانٍ قليلة لا إلى الأبد. الفشلُ
     * (تحقّقٌ مرفوض أو استثناء) يُفكّه المستدعي فوراً في `catch` —
     * ليعيد المندوب المحاولة بلا انتظار مهلةٍ لم يكن سببها تكراره.
     *
     * @param  array<string>  $fields  حقول الطلب التي تُميّز السند
     *
     * @throws ValidationException حين يُمسك قفلٌ آخر بنفس البصمة بالفعل
     */
    protected function guardDuplicateSubmission(Request $request, string $prefix, array $fields, int $seconds = 15): Lock
    {
        $fingerprint = collect($fields)
            ->map(fn ($field) => $request->input($field))
            ->implode('|');

        $key = sprintf('duplicate_submission:%s:%s:%s', $prefix, auth()->id() ?? 'guest', sha1($fingerprint));

        $lock = Cache::lock($key, $seconds);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'amount' => 'تم استلام هذا الطلب بالفعل قبل لحظات — برجاء الانتظار قليلاً قبل إعادة المحاولة، تجنّباً لتكرار السند.',
            ]);
        }

        return $lock;
    }

    /**
     * الفترة المحاسبية الجارية — مرجع المدى الافتراضي في التقارير.
     *
     * التقارير كانت تفتح على «أول السنة الميلادية إلى اليوم» أو على الشهر
     * الجاري، بلا صلة بالسنة المالية المضبوطة في الإعدادات. منشأةٌ سنتها
     * المالية تبدأ في يوليو ترى تقاريرها مقطوعةً عند يناير.
     *
     * @return array{from: string, to: string, label: string|null}
     */
    /**
     * حدّا السنة المالية التي يقع فيها تاريخ.
     *
     * التقاريرُ التي تقول «هذه السنة» تعني السنةَ **المالية** لا الميلادية:
     * منشأةٌ سنتُها تبدأ في يوليو تُقرأ تقاريرها مقطوعةً عند يناير إن حُسبت
     * بالتقويم الميلادي.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function fiscalYearBounds(?DateTimeInterface $date = null): array
    {
        /*
            الوسيطُ يقبل أيَّ تاريخ: المستدعون يمرّرون `Carbon\Carbon` تارةً
            و`Illuminate\Support\Carbon` تارةً — والثانيةُ ترث الأولى، فاشتراطُ
            الوارثة يردّ الأمَّ بـ`TypeError` يُسقط ثمانيةَ تقارير.
        */
        $date = $date ? Carbon::instance($date) : Carbon::today();

        try {
            $year = FiscalYear::whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->orderByDesc('start_date')
                ->first() ?: FiscalYear::current();

            if ($year && $year->start_date && $year->end_date) {
                return [
                    'start' => Carbon::parse($year->start_date)->startOfDay(),
                    'end' => Carbon::parse($year->end_date)->endOfDay(),
                ];
            }
        } catch (Throwable $e) {
            // جدول السنوات غير مهيّأ: تعود السنة الميلادية
        }

        return [
            'start' => $date->copy()->startOfYear(),
            'end' => $date->copy()->endOfYear(),
        ];
    }

    /** بداية السنة المالية التي يقع فيها تاريخ — أو سنةِ اليوم. */
    protected function fiscalYearStart(?DateTimeInterface $date = null): Carbon
    {
        return $this->fiscalYearBounds($date)['start'];
    }

    /** نهاية السنة المالية التي يقع فيها تاريخ — أو سنةِ اليوم. */
    protected function fiscalYearEnd(?DateTimeInterface $date = null): Carbon
    {
        return $this->fiscalYearBounds($date)['end'];
    }

    protected function defaultAccountingRange(): array
    {
        try {
            // ١) فترةٌ محاسبية تحوي اليوم — أدقُّ ما يوصف به «الجاري»
            $period = AccountingPeriod::forDate(today());

            if ($period && $period->start_date && $period->end_date) {
                return [
                    'from' => $period->start_date->toDateString(),
                    'to' => $period->end_date->toDateString(),
                    'label' => $period->name,
                    'unconfigured' => false,
                ];
            }

            // ٢) سنةٌ مالية **تحوي اليوم**
            $year = FiscalYear::current();

            if ($year && $year->start_date && $year->end_date) {
                $start = Carbon::parse($year->start_date)->startOfDay();
                $end = Carbon::parse($year->end_date)->startOfDay();

                /*
                    **السنةُ «الجارية» قد لا تحوي اليوم.**

                    `FiscalYear::current()` تعيد السنةَ المفتوحة، وقد تكون لم
                    تبدأ بعد. وكان المدى يُقصّ عند اليوم (`min(end, today)`)
                    فيخرج **مقلوباً**: من الغد إلى الأمس، وكلُّ شاشةٍ تُبنى
                    عليه تعرض صفر سجلّات بلا أن تقول إنها فلترت.
                */
                if (today()->betweenIncluded($start, $end)) {
                    return [
                        'from' => $start->toDateString(),
                        // السنة تمتدّ في المستقبل: لا تُعرض حركةُ غدٍ
                        'to' => today()->toDateString(),
                        'label' => $year->name,
                        'unconfigured' => false,
                    ];
                }
            }
        } catch (Throwable $e) {
            // جداول الفترات غير مهيّأة: يُعامَل معاملةَ غير المضبوط
        }

        /*
            **لا فترةَ تحوي اليوم — والصوابُ حدٌّ لا انفلات.**

            جرّبتُ أوّلاً أن أترك المدى مفتوحاً في هذه الحال، فراراً من إخفاء
            البيانات بصمت. وهو خطأ: دفترُ يوميةٍ يُعرَض كاملاً بلا فترة ليس
            عرضاً محاسبياً — إنما هو تفريغُ جدول. تُجمَع فيه سنواتٌ في رقمٍ
            واحد، ويثقل بمئات الآلاف من البنود، ويفقد الشاشةُ معناها.

            و«لا فترةَ تحوي اليوم» خللٌ في الضبط لا حالةٌ يُبنى عليها: فيُحدّ
            العرضُ بالشهر الجاري — أضيقُ مدىً معقول يحوي اليوم يقيناً —
            **ويُعلَن الخللُ في الشاشة** ليُصلَح، بدل أن يُداوى بعرضٍ منفلت.
        */
        return [
            'from' => today()->copy()->startOfMonth()->toDateString(),
            'to' => today()->copy()->endOfMonth()->toDateString(),
            'label' => 'الشهر الجاري',
            'unconfigured' => true,
        ];
    }

    /**
     * مدى العرض المطبَّق — ما اختاره المستخدم، وإلا الفترة المحاسبية الجارية.
     *
     * **لماذا الفترة لا الشهر؟**
     *
     * كانت الشاشات تفتح على مدًى مكتوبٍ في الشيفرة — الشهر الجاري أو أول
     * السنة الميلادية — فما تعرضه لا علاقة له بالفترة التي تعمل المنشأة
     * عليها. ومحاسبٌ يقفل الربع الأول ويعمل في الثاني يفتح الشاشة فيرى
     * خليطاً من الاثنين.
     *
     * الأولوية: فترةٌ اختارها المستخدم بمعرّفها ← تاريخان كتبهما ←
     * الفترة المحاسبية الجارية ← السنة المالية ← السنة الميلادية.
     *
     * @return array{from: ?string, to: ?string, period_id: ?int, label: ?string, is_default: bool, all: bool}
     */
    protected function accountingRange(
        Request $request,
        string $fromKey = 'from_date',
        string $toKey = 'to_date'
    ): array {
        /*
            **مخرجٌ صريح من الفترة.**

            الافتراضُ أن تُقرأ الشاشةُ على الفترة الجارية، لكنّ المستخدم قد
            يريد التاريخَ كلَّه — بحثاً عن سندٍ قديم أو مراجعةً لسنةٍ مضت.
            وبغير مخرجٍ معلَن يبقى يُقلّب الفتراتِ واحدةً واحدة، أو يظنّ أنّ
            السجلّ ضاع.
        */
        if ($request->input('period_id') === 'all') {
            return [
                'from' => null,
                'to' => null,
                'period_id' => 'all',
                'label' => 'كل الفترات',
                'is_default' => false,
                'all' => true,
                'unconfigured' => false,
            ];
        }

        // ١) فترةٌ مُختارة بمعرّفها تَجُبّ ما عداها
        if ($request->filled('period_id')) {
            try {
                $period = AccountingPeriod::find($request->input('period_id'));

                if ($period) {
                    return [
                        'from' => $period->start_date->toDateString(),
                        'to' => $period->end_date->toDateString(),
                        'period_id' => $period->id,
                        'label' => $period->name,
                        'is_default' => false,
                        'all' => false,
                        'unconfigured' => false,
                    ];
                }
            } catch (Throwable $e) {
                // فترة غير موجودة: يُكمَل إلى التاريخين
            }
        }

        // ٢) تاريخان كتبهما المستخدم
        $hasFrom = $request->filled($fromKey);
        $hasTo = $request->filled($toKey);

        if ($hasFrom || $hasTo) {
            $from = $request->input($fromKey);
            $to = $request->input($toKey);

            // مدًى مقلوب يُصحَّح بدل أن يُرجع شاشةً فارغة بلا تفسير
            if ($from && $to && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            return [
                'from' => $from ?: $this->defaultAccountingRange()['from'],
                'to' => $to ?: $this->defaultAccountingRange()['to'],
                'period_id' => null,
                'label' => null,
                'is_default' => false,
                'all' => false,
                'unconfigured' => false,
            ];
        }

        // ٣) الافتراضي: الفترة المحاسبية الجارية — إن وُجدت
        $default = $this->defaultAccountingRange();

        return [
            'from' => $default['from'],
            'to' => $default['to'],
            'period_id' => null,
            'label' => $default['label'],
            // مدًى فُرِض بلا طلبٍ من المستخدم، فيُعلَن له في الشاشة
            'is_default' => true,
            'all' => false,
            // لا فترةَ محاسبية تحوي اليوم: خللُ ضبطٍ يُعرَض ليُصلَح
            'unconfigured' => $default['unconfigured'] ?? false,
        ];
    }

    /**
     * كود الحساب الفرعي التالي تحت أبٍ بعينه.
     *
     * **العطل الذي يعالجه**
     *
     * `accounts.code` عمودٌ **صحيح** (`int`) عليه فهرسٌ فريد، وشجرة
     * الحسابات تبني التدرّج بالأرقام لا بالنقاط: ابنُ `11` هو `111` ثم
     * `112`. وشاشة الأصول كانت تولّد `113.1`، فتقصّه قاعدة البيانات إلى
     * `113` وتردّ «القيمة مكرّرة» — فلا يُحفظ أصلٌ واحد تحت أبٍ له ابن.
     *
     * الابن هنا يبدأ دائماً ببادئة أبيه: تُجرَّب خانةٌ واحدة (`11` ← `111`)
     * فإن امتلأت العشر خاناتٍ جُرِّبت خانتان (`1101`)، وهكذا. والفراغات
     * المتروكة من حذفٍ سابق تُملأ بدل أن يُقفَز فوقها.
     *
     * @throws \RuntimeException إن امتلأ المدى كلّه
     */
    protected function nextChildAccountCode($parentId): int
    {
        $parent = Account::findOrFail($parentId);
        $prefix = (int) $parent->code;

        if ($prefix <= 0) {
            throw new \RuntimeException('الحساب الأب بلا كود صالح — راجع شجرة الحسابات.');
        }

        for ($width = 1; $width <= 4; $width++) {
            $span = 10 ** $width;
            $base = $prefix * $span;

            // كل الأكواد المشغولة في المدى دفعةً واحدة: استعلامٌ لكل عرض
            // لا استعلامٌ لكل محاولة
            $taken = Account::whereBetween('code', [$base + 1, $base + $span - 1])
                ->pluck('code')
                ->map(fn ($code) => (int) $code)
                ->flip();

            for ($i = 1; $i < $span; $i++) {
                if (! $taken->has($base + $i)) {
                    return $base + $i;
                }
            }
        }

        throw new \RuntimeException('تعذّر توليد كود فرعي تحت الحساب '.$prefix.' — المدى ممتلئ.');
    }

    /**
     * قصر النتائج على مركز تكلفةٍ واحد.
     *
     * **أين يسكن مركز التكلفة؟**
     *
     * لا في `expenses` ولا في `receipts`: هجرة
     * `move_cost_center_to_journal_entry_details` نقلته إلى سطور القيد،
     * ولا يحمل السند إلا رايةً (`cost_centers_enabled`) تقول إنّه مُوزَّع.
     * فالفلترة تمرّ بالقيد المرتبط بالسند لا بعمودٍ فيه.
     *
     * والربط بين السند وقيده ليس واحداً في الوحدة: سند الصرف يُربَط
     * بـ`reference_id`، وسند القبض بـ`reference_number` مقابل كوده — لذا
     * يأخذ التابع اسم العمودين.
     *
     * @param  string  $referenceType  قيمة `journal_entries.reference_type`
     * @param  string  $journalColumn  عمود الربط في القيد
     * @param  string  $localColumn  عمود الربط في جدول السند (مؤهَّلاً)
     */
    protected function scopeCostCenter(
        $query,
        $costCenterId,
        string $referenceType,
        string $journalColumn,
        string $localColumn
    ) {
        if (! $costCenterId) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($costCenterId, $referenceType, $journalColumn, $localColumn) {
            $sub->selectRaw('1')
                ->from('journal_entry_details as jed')
                ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                ->whereColumn('je.'.$journalColumn, $localColumn)
                ->where('je.reference_type', $referenceType)
                ->where('jed.cost_center_id', $costCenterId);
        });
    }

    /**
     * تسجيل عملية ناجحة في السجلّات الثلاثة.
     *
     * @param  string  $action  created|updated|deleted|cancelled|collected…
     * @param  array{old?: array, new?: array}  $changes
     */
    protected function recordOperation(
        string $action,
        string $module,
        Model|int|null $subject,
        string $description,
        array $changes = []
    ): void {
        OperationLog::record($action, $module, $subject, $description, $changes);
    }

    /** فرق القيم بين ما كان وما صار — للحقول المعنيّة وحدها */
    protected function operationDiff(Model $model, array $fields): array
    {
        return OperationLog::diff($model, $fields);
    }

    /**
     * عملية فشلت: تُكتب في سجلّ الأخطاء وسجلّ الأحداث، وتُعاد رسالةً مقروءة.
     *
     * @return string نصّ جاهز للعرض في `with('error', …)`
     */
    protected function recordFailure(
        Throwable $exception,
        string $module,
        string $action,
        Model|int|null $subject = null,
        ?string $userMessage = null
    ): string {
        OperationLog::failure($exception, $module, $action, $subject);

        // رسالة المطوّر لا تُعرض للمستخدم إلا حين تكون مقصودة: استثناءٌ
        // رماه الكود بنصّ عربي مفهوم يُعرض، وخطأُ قاعدةٍ خام لا يُعرض
        return $userMessage ?? $this->readableMessage($exception);
    }

    /**
     * أخطاء المحرّك الخام لا تُعرض كما هي — تُترجَم إلى سببٍ مفهوم.
     */
    protected function readableMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($exception instanceof QueryException) {
            return match ((int) ($exception->errorInfo[1] ?? 0)) {
                1062 => 'القيمة المدخلة مستخدمة مسبقاً — راجع الرقم أو الكود.',
                1451, 1452 => 'العملية مرتبطة بسجلّات أخرى — لا يمكن إتمامها قبل فكّ الارتباط.',
                1364 => 'حقل إلزامي في قاعدة البيانات لم يُملأ — راجع البيانات المدخلة.',
                default => 'تعذّر حفظ العملية في قاعدة البيانات. رُوجعت في سجلّ الأخطاء.',
            };
        }

        // نصّ عربي رماه الكود عمداً: يُعرض كما هو
        if ($message !== '' && preg_match('/\p{Arabic}/u', $message)) {
            return $message;
        }

        return 'تعذّر إتمام العملية. رُوجعت في سجلّ الأخطاء.';
    }
}
