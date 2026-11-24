<?php

namespace Modules\Installer\Services;

use App\Models\FawtraSubscription;
use App\Services\Fawtra\FawtraCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * فحص توافق النظام مع الاشتراك — بوّابة لا يتمّ التثبيت قبل اجتيازها.
 *
 * **لماذا بوّابة لا تنبيه؟** لأن تركيباً يُنهيه صاحبُه ثم يكتشف أن اشتراكه
 * منتهٍ، أو أن باقته تسمح بثلاثة مستخدمين وقد زرع خمسة، أو أن الدومين
 * المسجَّل غير دومين خادمه — كلّها حالات يتبيّن فيها الخلل بعد أن تصير
 * المعالجة أصعب. والفحص هنا يقع **قبل تسجيل تمام التثبيت** فيبقى المعالج
 * مفتوحاً لإصلاح السبب.
 *
 * **ولا يُطبَّق إلا على نظامٍ مربوط.** من ثبّت بلا ربط يمرّ بلا فحص: الربط
 * ميزةٌ تُضاف لا شرطٌ للتشغيل، وفرضُ فحصٍ على من لا اشتراك له بلا معنى.
 */
class CompatibilityChecker
{
    public function __construct(protected FawtraCredentials $credentials) {}

    /**
     * تشغيل كل الفحوص.
     *
     * @return array<int, array{key:string, label:string, ok:bool, critical:bool, detail:string}>
     */
    public function all(): array
    {
        if (! $this->credentials->configured()) {
            return [[
                'key' => 'linked',
                'label' => 'الربط بلوحة فوترة',
                'ok' => true,
                'critical' => false,
                'detail' => 'غير مربوط — تُخطّى فحوص الاشتراك.',
            ]];
        }

        $record = FawtraSubscription::current();

        if (! $record || ! $record->snapshot) {
            // سبب الإخفاق محفوظ من محاولة المزامنة نفسها — وذكرُه هنا يوفّر
            // على المستخدم البحث: «تعذّرت المزامنة» وحدها لا تقول إن كان
            // المفتاح مرفوضاً أم الشبكة منقطعة أم الربط لم يُضبط أصلاً
            $reason = $record?->last_error ?: null;
            $code = $record?->last_error_code ?: null;

            return [[
                'key' => 'snapshot',
                'label' => 'بيانات الاشتراك',
                'ok' => false,
                'critical' => true,
                'detail' => 'لم تصل بيانات الاشتراك من لوحة فوترة.'
                    .($reason ? ' السبب: '.$reason.($code ? " ({$code})" : '') : ' لم تُنفَّذ المزامنة بعد.')
                    .' أعد المحاولة بالضغط على «استئناف»، أو نفّذ php artisan fawtra:sync --force لمعرفة التفصيل.',
            ]];
        }

        return array_merge(
            [$this->checkStatus($record)],
            [$this->checkExpiry($record)],
            [$this->checkDomain($record)],
            $this->checkSeats($record),
            [$this->checkExhausted($record)],
            [$this->checkFreshness($record)],
        );
    }

    /** هل اجتاز كل فحصٍ حرج؟ */
    public function passed(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['critical'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** أسماء ما سقط من الفحوص الحرجة — لرسالة التوقّف. */
    public function failures(array $checks): array
    {
        return array_values(array_map(
            fn ($c) => $c['label'].': '.$c['detail'],
            array_filter($checks, fn ($c) => $c['critical'] && ! $c['ok'])
        ));
    }

    /* ==================== الفحوص ==================== */

    /** الاشتراك نشط — الفحص الأهمّ. */
    protected function checkStatus(FawtraSubscription $record): array
    {
        return [
            'key' => 'status',
            'label' => 'حالة الاشتراك',
            'ok' => $record->is_active,
            'critical' => true,
            'detail' => $record->is_active
                ? 'نشط ('.$record->statusLabel().')'
                : 'غير نشط — الحالة: '.$record->statusLabel().'. راجع لوحة فوترة قبل إتمام التثبيت.',
        ];
    }

    /**
     * لم ينتهِ — ويحذّر إن بقي أقلّ من أسبوع.
     *
     * الانتهاء حرج، وقرب الانتهاء ليس كذلك: من يثبّت قبل التجديد بيومين لا
     * يجوز حبسه.
     */
    protected function checkExpiry(FawtraSubscription $record): array
    {
        $days = $record->days_remaining;

        return [
            'key' => 'expiry',
            'label' => 'مدّة الاشتراك',
            'ok' => ! $record->is_expired,
            'critical' => true,
            'detail' => match (true) {
                $record->is_expired => 'منتهٍ منذ '.($record->end_date?->toDateString() ?? '—'),
                $days === null => 'بلا تاريخ انتهاء',
                $days <= 7 => 'يتبقّى '.$days.' يوماً — جدّد قريباً',
                default => 'يتبقّى '.$days.' يوماً (حتى '.$record->end_date?->toDateString().')',
            },
        ];
    }

    /**
     * الدومين المسجَّل يطابق دومين هذا الخادم.
     *
     * تحذيرٌ لا منع: التثبيت قد يجري على `localhost` أو خلف وسيط عكسي باسمٍ
     * داخلي، والدومين المسجَّل هو ما سيُستعمل في الإنتاج. ومنعُ التثبيت لهذا
     * يوقف كل تركيب محلّي.
     */
    protected function checkDomain(FawtraSubscription $record): array
    {
        $registered = (string) config('fawtra.domain');
        $current = request()->getHost();
        $matches = strcasecmp($registered, $current) === 0;

        return [
            'key' => 'domain',
            'label' => 'مطابقة الدومين',
            'ok' => $matches,
            'critical' => false,
            'detail' => $matches
                ? 'مطابق ('.$registered.')'
                : 'المسجَّل «'.$registered.'» وهذا الخادم «'.$current.'». تأكّد أن هذا مقصود قبل النشر.',
        ];
    }

    /**
     * ما زُرع في القاعدة مقابل ما تسمح به الباقة.
     *
     * يُقارَن العدد الفعلي لا المتوقَّع: البيانات التجريبية قد تكون زُرعت،
     * والحدّ يُقاس على ما في القاعدة الآن.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function checkSeats(FawtraSubscription $record): array
    {
        // مفتاح الميزة عند فوترة ⇦ [الجدول، الوصف]
        $map = [
            'users' => ['users', 'المستخدمون'],
            'employees' => ['employees', 'الموظفون'],
            'branches' => ['branches', 'الفروع'],
            'clients' => ['clients', 'العملاء'],
            'products' => ['products', 'المنتجات'],
            'warehouses' => ['storehouses', 'المستودعات'],
        ];

        $checks = [];

        foreach ($record->limits() as $limit) {
            $key = $limit['key'] ?? null;

            if ($key === null || ! isset($map[$key])) {
                continue;
            }

            // `-1` يعني بلا حدّ — والوثيقة تحذّر صراحةً من مقارنتها بصفر
            if (($limit['unlimited'] ?? false) || (int) ($limit['allowance'] ?? 0) < 0) {
                continue;
            }

            [$table, $label] = $map[$key];

            $actual = $this->countRows($table);

            if ($actual === null) {
                continue;
            }

            $allowance = (int) $limit['allowance'];
            $within = $actual <= $allowance;

            $checks[] = [
                'key' => 'seats.'.$key,
                'label' => 'حدّ '.$label,
                'ok' => $within,
                'critical' => true,
                'detail' => $within
                    ? $actual.' من '.$allowance
                    : 'في النظام '.$actual.' والباقة تسمح بـ'.$allowance.' — احذف الزائد أو اشترِ إضافة.',
            ];
        }

        return $checks;
    }

    /** لا ميزة مستنفدة قبل أن يبدأ العمل أصلاً. */
    protected function checkExhausted(FawtraSubscription $record): array
    {
        $exhausted = array_filter(
            $record->usage(),
            fn ($item) => ($item['exhausted'] ?? false) === true
        );

        $names = array_map(fn ($i) => $i['label'] ?? $i['key'] ?? '—', $exhausted);

        return [
            'key' => 'exhausted',
            'label' => 'الميزات المستنفدة',
            'ok' => $exhausted === [],
            'critical' => false,
            'detail' => $exhausted === []
                ? 'لا ميزة مستنفدة'
                : 'مستنفدة: '.implode('، ', $names),
        ];
    }

    /** اللقطة حديثة — تحذيرٌ لا منع، فالمزامنة الدورية تتكفّل بها. */
    protected function checkFreshness(FawtraSubscription $record): array
    {
        $stale = $record->isStale();

        return [
            'key' => 'freshness',
            'label' => 'حداثة البيانات',
            'ok' => ! $stale,
            'critical' => false,
            'detail' => $stale
                ? 'اللقطة قديمة — آخر مزامنة '.($record->last_synced_at?->diffForHumans() ?? 'لم تتم')
                : 'محدَّثة '.($record->last_synced_at?->diffForHumans() ?? '—'),
        ];
    }

    /** عدّ صفوف جدول — أو null إن لم يوجد. */
    protected function countRows(string $table): ?int
    {
        try {
            return Schema::hasTable($table) ? DB::table($table)->count() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
