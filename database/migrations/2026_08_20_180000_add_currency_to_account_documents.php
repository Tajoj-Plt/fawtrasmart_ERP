<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عملة المستند المالي تُحفظ معه.
 *
 * **ما كان**
 *
 * مستندات وحدة الحسابات — المصروف وسند القبض وسند الصرف والشيكات — لا تحمل
 * عمود عملة أصلاً، فالمبلغ رقمٌ بلا وحدة. والقيود التي تولّدها كانت تكتب
 * `'currency' => 'SAR'` نصّاً في الشيفرة: تبديل العملة الافتراضية في شاشة
 * «العملات» لا يغيّر شيئاً، ومنشأةٌ تعمل بالدرهم تجد قيودها بالريال.
 *
 * وثلاثة جداول تحفظ العملة رقماً صغيراً بمعنًى مكتوب في القالب: خزينةٌ
 * عملتها `0` أو `1` («ريال» و«جنيه»)، وأصلٌ عملته `1` أو `2` («ريال»
 * و«دولار») — بينما جدول العملات في النظام يحمل عشرين عملة. الرقم بلا
 * معنى خارج القالب الذي كتبه، ولا يُقرأ ولا يُقارَن ولا يُجمَع عليه.
 *
 * **ما صار**
 *
 * عمود `currency` نصّي برمز ISO في كل مستند، يُملأ عند الإنشاء من العملة
 * الافتراضية ثم يُجمَّد (`App\Models\Concerns\HasDocumentCurrency`).
 * والأعمدة الرقمية القديمة تُحوَّل إلى رموز بخريطة صريحة، فلا يضيع ما حُفظ.
 */
return new class extends Migration
{
    /** الجداول التي تحتاج عمود عملة جديداً */
    private const NEW_COLUMN = [
        'expenses' => 'amount',
        'receipts' => 'amount',
        'payment_vouchers' => 'amount',
        'receipt_vouchers' => 'amount',
        'received_cheques' => 'amount',
        'payable_cheques' => 'amount',
    ];

    /** الأعمدة الرقمية القديمة: الجدول => [القيمة => الرمز] */
    private const LEGACY_NUMERIC = [
        // كان القالب يسمّيهما «ريال» و«جنيه»
        'treasuries' => [0 => 'SAR', 1 => 'EGP'],
        // وهنا «ريال سعودي (SAR)» و«دولار أمريكي (USD)»
        'asset_depreciations' => [1 => 'SAR', 2 => 'USD'],
    ];

    public function up(): void
    {
        $default = $this->defaultCode();

        foreach (self::NEW_COLUMN as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'currency')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after, $table) {
                $column = $t->string('currency', 3)->nullable();

                if (Schema::hasColumn($table, $after)) {
                    $column->after($after);
                }
            });

            // المستندات القائمة حُرّرت بالعملة السارية وقتها — وهي الافتراضية
            DB::table($table)->whereNull('currency')->update(['currency' => $default]);
        }

        foreach (self::LEGACY_NUMERIC as $table => $map) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            if ($this->isTextColumn($table, 'currency')) {
                continue; // حُوِّل سابقاً
            }

            // القيم تُقرأ قبل تغيير النوع، فتحويلُ العمود يمحو أرقامها
            $existing = DB::table($table)
                ->whereNotNull('currency')
                ->pluck('currency', 'id');

            Schema::table($table, function (Blueprint $t) {
                $t->string('currency', 3)->nullable()->change();
            });

            foreach ($existing as $id => $value) {
                DB::table($table)
                    ->where('id', $id)
                    ->update(['currency' => $map[(int) $value] ?? $default]);
            }

            DB::table($table)->whereNull('currency')->update(['currency' => $default]);
        }

        // القيود التي كُتبت بـ`'SAR'` نصّاً بينما الافتراضية شيء آخر:
        // لا تُلمَس. عملة مستندٍ محفوظ لا تُغيَّر بأثر رجعي — يُصحَّح ما
        // يُنشأ بعد اليوم، ويبقى ما مضى كما حُرِّر.
    }

    public function down(): void
    {
        foreach (array_keys(self::NEW_COLUMN) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('currency'));
            }
        }

        // الأعمدة الرقمية لا تُعاد: الرجوع إلى ترميزٍ يسع عملتين
        // يُتلف كل صفٍّ حُرِّر بعملةٍ ثالثة.
    }

    private function defaultCode(): string
    {
        try {
            return CurrencySettings::code();
        } catch (\Throwable $e) {
            return 'SAR';
        }
    }

    private function isTextColumn(string $table, string $column): bool
    {
        try {
            $type = Schema::getColumnType($table, $column);
        } catch (\Throwable $e) {
            return false;
        }

        return in_array($type, ['string', 'varchar', 'char', 'text'], true);
    }
};
