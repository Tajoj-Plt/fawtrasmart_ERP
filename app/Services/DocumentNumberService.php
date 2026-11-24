<?php

namespace App\Services;

use App\Models\SerialSetting;
use App\Support\DocumentSections;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * توليد أرقام المستندات المتسلسلة.
 *
 * نقطة الاستدعاء الوحيدة لأي جزء في النظام يحتاج رقم مستند:
 *
 *     $number = next_document_number('invoice');      // يستهلك الرقم
 *     $preview = peek_document_number('invoice');     // معاينة بلا استهلاك
 *
 * ثلاث ضمانات لم تكن موجودة في `SerialSetting::generateNextNumber` السابقة:
 *
 * 1. **التزامن**: التوليد داخل معاملة مع `lockForUpdate`، فطلبان متزامنان
 *    لا ينتجان الرقم نفسه. السابق كان `increment()` مكشوفاً.
 *
 * 2. **إعادة التصفير**: السابق كان يضبط `current_number = 0` في الذاكرة ثم
 *    ينادي `increment()` الذي يترجم إلى `SET current_number = current_number + 1`
 *    في SQL — أي أن قيمة قاعدة البيانات هي المستعملة والتصفير يُفقد تماماً.
 *    هنا التصفير يُكتب فعلياً قبل الزيادة.
 *
 * 3. **دورة التصفير**: تعتمد `last_reset_at` لا `updated_at`، لأن `updated_at`
 *    يتغيّر مع كل توليد فلا يصلح مرجعاً لبداية الدورة.
 *
 * 4. **تخطّي المحجوز**: العدّاد قد يتخلّف عن الجدول (مستندات أُنشئت بترقيم
 *    قديم، أو بادئة تغيّرت، أو استيراد بيانات)، فيسلّم رقماً محفوظاً أصلاً
 *    فيرفضه التحقّق برسالة «الكود مستخدم من قبل» أو يصطدم بفهرس `unique`.
 *    هنا يُفحص الرقم في جدول القسم ويُقفَز إلى أول رقم شاغر.
 */
class DocumentNumberService
{
    /** أقصى عدد أرقام تُجرَّب بحثاً عن رقم شاغر قبل التسليم بالرقم كما هو */
    protected const MAX_PROBE = 500;

    /** حجم دفعة الفحص الواحدة — استعلام `whereIn` واحد لكل دفعة */
    protected const PROBE_BATCH = 50;

    /**
     * يولّد الرقم التالي ويستهلكه.
     *
     * @param  string  $section  مفتاح القسم من DocumentSections
     * @return string الرقم المنسّق جاهزاً للحفظ
     */
    public function next(string $section): string
    {
        $section = DocumentSections::resolve($section);

        return DB::transaction(function () use ($section) {
            $setting = SerialSetting::where('section', $section)->lockForUpdate()->first();

            if (! $setting) {
                $setting = $this->createDefault($section);
                $setting = SerialSetting::where('id', $setting->id)->lockForUpdate()->first();
            }

            $setting = $this->applyResetIfDue($setting);

            // أول رقم شاغر فعلاً — لا مجرّد التالي في العدّاد
            [$number, $formatted] = $this->firstFreeNumber($setting, $section, (int) $setting->current_number + 1);

            $setting->current_number = $number;
            $setting->save();

            return $formatted;
        });
    }

    /**
     * معاينة الرقم التالي بلا استهلاك — للعرض في الشاشات والنماذج.
     */
    public function peek(string $section): string
    {
        $section = DocumentSections::resolve($section);
        $setting = SerialSetting::where('section', $section)->first() ?? $this->makeDefault($section);

        // التصفير يُحتسب في المعاينة أيضاً وإلا أظهرت رقماً لن يُستعمل
        $base = $this->isResetDue($setting) ? 0 : (int) $setting->current_number;

        // المعاينة تتخطّى المحجوز كذلك، وإلا عرضت الشاشة رقماً يُرفض عند الحفظ
        return $this->firstFreeNumber($setting, $section, $base + 1)[1];
    }

    /**
     * الرقم الحالي المستهلَك آخر مرة — بلا زيادة.
     */
    public function current(string $section): string
    {
        $section = DocumentSections::resolve($section);
        $setting = SerialSetting::where('section', $section)->first() ?? $this->makeDefault($section);

        return $this->format($setting, (int) $setting->current_number);
    }

    /**
     * إعدادات قسم — تُنشأ بالقيم المقترحة إن لم تكن محفوظة.
     */
    public function settingsFor(string $section): SerialSetting
    {
        $section = DocumentSections::resolve($section);

        return SerialSetting::where('section', $section)->first() ?? $this->makeDefault($section);
    }

    /**
     * تنسيق رقم خام حسب إعدادات القسم — تُستعمل أيضاً في المعاينة الحية.
     */
    public function format(SerialSetting $setting, int $number): string
    {
        $digits = max(1, (int) $setting->number_of_digits);
        $body = $this->encode($number, $digits, (int) $setting->mode);

        return $this->expandPlaceholders((string) $setting->prefix)
            .(string) $setting->separator
            .$body
            .$this->expandPlaceholders((string) $setting->suffix);
    }

    /**
     * ترميز الرقم حسب النمط (0–6).
     */
    protected function encode(int $number, int $digits, int $mode): string
    {
        return match ($mode) {
            1 => str_pad(dechex($number), $digits, '0', STR_PAD_LEFT),
            2 => strtoupper(str_pad(dechex($number), $digits, '0', STR_PAD_LEFT)),
            3 => $this->toLetters($number, $digits, false),
            4 => $this->toLetters($number, $digits, true),
            5, 6 => $this->toLetters(intdiv($number, 1000), 2, $mode === 6)
                .str_pad((string) ($number % 1000), max(1, $digits - 2), '0', STR_PAD_LEFT),
            default => str_pad((string) $number, $digits, '0', STR_PAD_LEFT),
        };
    }

    /**
     * تحويل رقم إلى أحرف: 0→a، 25→z، 26→ba …
     */
    protected function toLetters(int $number, int $minLength, bool $uppercase): string
    {
        $letters = '';
        $number = max(0, $number);

        do {
            $letters = chr(97 + ($number % 26)).$letters;
            $number = intdiv($number, 26);
        } while ($number > 0);

        $letters = str_pad($letters, max(1, $minLength), 'a', STR_PAD_LEFT);

        return $uppercase ? strtoupper($letters) : $letters;
    }

    /**
     * يستبدل رموز التاريخ داخل البادئة واللاحقة.
     *
     * المدعوم: {YYYY} {YY} {MM} {DD} — مثال: `INV-{YYYY}-` ينتج `INV-2026-000001`
     */
    public function expandPlaceholders(string $text): string
    {
        if ($text === '' || ! str_contains($text, '{')) {
            return $text;
        }

        $now = Carbon::now();

        return strtr($text, [
            '{YYYY}' => $now->format('Y'),
            '{YY}' => $now->format('y'),
            '{MM}' => $now->format('m'),
            '{DD}' => $now->format('d'),
        ]);
    }

    /**
     * هل حانت دورة التصفير؟ يقارن `last_reset_at` بالدورة المضبوطة.
     */
    public function isResetDue(SerialSetting $setting): bool
    {
        $period = $setting->reset_period ?: ($setting->reset_yearly ? 'yearly' : 'never');

        if ($period === 'never') {
            return false;
        }

        $last = $setting->last_reset_at ? Carbon::parse($setting->last_reset_at) : null;

        if (! $last) {
            return false; // بلا مرجع لا تصفير — يُضبط المرجع عند أول حفظ
        }

        $now = Carbon::now();

        return match ($period) {
            'yearly' => $last->year !== $now->year,
            'monthly' => $last->year !== $now->year || $last->month !== $now->month,
            'daily' => ! $last->isSameDay($now),
            default => false,
        };
    }

    /**
     * يصفّر العدّاد فعلياً إن حانت الدورة — ويثبّت تاريخ الدورة الجديدة.
     */
    protected function applyResetIfDue(SerialSetting $setting): SerialSetting
    {
        if ($this->isResetDue($setting)) {
            $setting->current_number = 0;
            $setting->last_reset_at = Carbon::now();
        }

        return $setting;
    }

    /**
     * أول رقم غير محجوز في جدول القسم ابتداءً من `$start`.
     *
     * **مقيَّد بنوع المستند:** أربعة أقسام تتشارك `purchase_invoices`، وكان
     * الفحص يمسح الجدول كلّه. فإذا تشابهت صيغتا نوعين — بادئة فارغة مثلاً —
     * رأى القسمُ الثاني رقمَ الأول محجوزاً فقفز عنه: أمر شراء `0001` ثم
     * فاتورة `0002`. الآن لا يرى كلُّ قسمٍ إلا مستنداته هو، فعدّاده مستقلّ.
     *
     * وحتى لا يُخرق فهرس `unique` على العمود حين يتشابه النوعان في الصيغة،
     * تمنع شاشة «تسلسل أرقام المستندات» تكرار الصيغة بين أقسام الجدول
     * الواحد — انظر `SequenceNumberingController::store()`.
     *
     * الفحص على دفعات بـ `whereIn` لا استعلام لكل رقم: الحالة الغالبة أن
     * الرقم الأول شاغر فتكفي دفعة واحدة، والفجوة الكبيرة تُقطع في دفعات
     * بدل خمسمئة استعلام.
     *
     * حين لا يُعرف جدول القسم أو عموده — أو تعذّر الاستعلام — يُسلَّم الرقم
     * كما هو، فالسلوك يبقى كما كان بلا فحص.
     *
     * @return array{0: int, 1: string} الرقم الخام والرقم المنسّق
     */
    protected function firstFreeNumber(SerialSetting $setting, string $section, int $start): array
    {
        $number = max(1, $start);
        $table = DocumentSections::table($section);
        $column = DocumentSections::column($section);

        if (! $table || ! $column) {
            return [$number, $this->format($setting, $number)];
        }

        for ($probed = 0; $probed < self::MAX_PROBE; $probed += self::PROBE_BATCH) {
            $candidates = [];

            for ($i = 0; $i < self::PROBE_BATCH; $i++) {
                $candidates[$number + $i] = $this->format($setting, $number + $i);
            }

            try {
                $query = DB::table($table)->whereIn($column, array_values($candidates));

                // الجدول المشترك: مستندات هذا النوع وحدها تحجز أرقامه
                foreach (DocumentSections::scope($section) as $scopeColumn => $value) {
                    $query->where($scopeColumn, $value);
                }

                $taken = $query->pluck($column)->all();
            } catch (\Throwable $e) {
                // جدول أو عمود غير موجود في هذا التثبيت: بلا فحص
                return [$number, $candidates[$number]];
            }

            $taken = array_flip(array_map('strval', $taken));

            foreach ($candidates as $candidate => $formatted) {
                if (! isset($taken[$formatted])) {
                    return [$candidate, $formatted];
                }
            }

            $number += self::PROBE_BATCH;
        }

        // فجوة أكبر من الحد: يُسلَّم الرقم الحالي ويتولّى التحقّق ما بعده
        return [$number, $this->format($setting, $number)];
    }

    /**
     * سجل إعدادات محفوظ بالقيم المقترحة للقسم.
     */
    protected function createDefault(string $section): SerialSetting
    {
        /*
            `lockForUpdate()->first()` لا يقفل صفّاً غير موجود، فطلبان متزامنان
            لقسمٍ لم يُضبط بعد يريان الغياب نفسه ويحاولان الإنشاء معاً — فيرفع
            الثاني «Duplicate entry … serial_settings_section_unique».

            القيد الفريد هو الحَكَم: مَن سبق أنشأ، ومَن تأخّر يقرأ ما أنشأه
            الأوّل بدل أن يسقط.
        */
        try {
            return SerialSetting::create($this->defaults($section));
        } catch (UniqueConstraintViolationException $e) {
            return SerialSetting::where('section', $section)->firstOrFail();
        } catch (QueryException $e) {
            // بعض التعريفات لا تُصنَّف استثناءً خاصاً — نفحص رمز SQLSTATE
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return SerialSetting::where('section', $section)->firstOrFail();
        }
    }

    /**
     * سجل غير محفوظ — للمعاينة قبل أن يضبط المستخدم القسم لأول مرة.
     */
    protected function makeDefault(string $section): SerialSetting
    {
        return new SerialSetting($this->defaults($section));
    }

    protected function defaults(string $section): array
    {
        return [
            'section' => $section,
            'current_number' => $this->lastNumberFromTable($section),
            'number_of_digits' => 6,
            'prefix' => DocumentSections::suggestedPrefix($section),
            'separator' => '-',
            'suffix' => null,
            'mode' => 0,
            'reset_yearly' => false,
            'reset_period' => 'never',
            'last_reset_at' => Carbon::now(),
            'is_active' => true,
        ];
    }

    /**
     * آخر رقم استعمله هذا القسم فعلاً — نقطة بداية عدّاده حين يُضبط لأول مرة.
     *
     * **ما تغيّر:** كانت تُرجِع `max(id)` من جدول القسم. وأربعة أقسام في
     * المشتريات تتشارك جدولاً واحداً (`purchase_invoices`): الفاتورة
     * والمرتجع وأمر الشراء والإشعار المدين. فمن أنشأ فاتورتين ثم أوّل أمر
     * شراء، بدأ عدّاد أوامر الشراء من 3 — لأنه قرأ `max(id)` للجدول كلّه
     * لا أرقام أوامر الشراء وحدها. وهذا هو الاختلاط الظاهر في الشاشة.
     *
     * صارت تُقرأ أرقامُ مستندات القسم نفسه: يُقيَّد الاستعلام بـ`scope`
     * القسم (العمود `type`)، ويُستخرج آخر مقطع رقمي من رمز كل مستند.
     * فلكل نوعٍ عدّادُه المستقلّ ويبدأ كلٌّ منها من 1.
     */
    public function lastNumberFromTable(string $section): int
    {
        $section = DocumentSections::resolve($section);
        $table = DocumentSections::table($section);
        $column = DocumentSections::column($section);

        if (! $table || ! $column) {
            return 0;
        }

        try {
            $query = DB::table($table)->whereNotNull($column);

            // الجدول المشترك: مستندات هذا النوع وحدها تُحتسب
            foreach (DocumentSections::scope($section) as $scopeColumn => $value) {
                $query->where($scopeColumn, $value);
            }

            $codes = $query->pluck($column);
        } catch (\Throwable $e) {
            // جدول أو عمود غير موجود في هذا التثبيت: البداية من الصفر
            return 0;
        }

        $last = 0;

        foreach ($codes as $code) {
            // رقم المستند هو آخر مقطع رقمي في الرمز: `PUR-00002` ← 2
            if (preg_match('/(\d+)\D*$/', (string) $code, $matches)) {
                $last = max($last, (int) $matches[1]);
            }
        }

        return $last;
    }
}
