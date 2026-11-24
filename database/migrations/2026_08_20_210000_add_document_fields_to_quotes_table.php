<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ثلاثة أعمدة كانت الشاشة تُرسلها والمتحكّم يكتبها ولا وجود لها.
 *
 * `quotes` أُنشئ في هجرةٍ واحدة سنة ٢٠٢٥ ولم يُعدَّل بعدها قطّ، بينما
 * تطوّرت الشاشة فوقه:
 *
 *   • `expiry_date`  حقل «صالح حتى» في نموذج الإنشاء منذ البداية —
 *                    يكتبه المستخدم، ويُرسَل، ولا يُحفظ.
 *   • `shipping_tax` يحسبها `store()` و`update()` ويمرّرانها إلى
 *                    `Quote::create/update` — فتسقط في التعيين الجَماعي
 *                    لأنّها ليست في `$fillable`، ولو كانت لَسقطت في
 *                    قاعدة البيانات لأنّ العمود غير موجود.
 *   • `currency`     تُثبَّت عملة المستند عند الحفظ فلا يمسّها تبديل
 *                    العملة الافتراضية لاحقاً — وهي قاعدة
 *                    `UsesDocumentCurrency` التي تعمل بها وحدات المخزون
 *                    والمشتريات والحسابات. نظيرتها في المخزون:
 *                    `2026_08_19_200000_add_currency_to_stock_documents`.
 *
 * لا قيمة افتراضية لـ`currency`: الصفوف القديمة تبقى `null`، ويقرأها
 * `documentCurrency()` على أنّها العملة الافتراضية — وهو الصحيح، إذ لم
 * تكن للنظام عملةٌ أخرى حين أُنشئت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('quote_date');
            }

            if (! Schema::hasColumn('quotes', 'shipping_tax')) {
                $table->decimal('shipping_tax', 10, 2)->nullable()->after('shipping_cost');
            }

            if (! Schema::hasColumn('quotes', 'currency')) {
                $table->string('currency', 8)->nullable()->after('grand_total');
            }
        });

        /* فهرسٌ على `quote_date`: شاشة العروض تُصفّي به افتراضياً بنطاق
           الفترة المحاسبية، فبدونه مسحٌ كاملٌ للجدول عند كل فتحة.

           محروسٌ كالأعمدة: هجرةٌ أخفقت بعد إنشائه ثم أُعيدت تموت على
           `Duplicate key name` بدل أن تُكمل. */
        if (! $this->hasIndex('quotes', 'quotes_date_status_index')) {
            Schema::table('quotes', function (Blueprint $table) {
                if (! SchemaGuard::hasIndex('quotes', 'quotes_date_status_index')) {
                    $table->index(['quote_date', 'status'], 'quotes_date_status_index');
                }
            });
        }
    }

    /** أموجودٌ فهرسٌ بهذا الاسم؟ — `Schema` لا تقدّم فحصاً له */
    private function hasIndex(string $table, string $index): bool
    {
        try {
            // Schema::getIndexes بديلٌ محمولٌ عن SHOW INDEX الخاص بـMySQL
            return collect(\Illuminate\Support\Facades\Schema::getIndexes($table))
                ->contains(fn ($row) => ($row['name'] ?? null) === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('quotes', 'quotes_date_status_index')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropIndex('quotes_date_status_index');
            });
        }

        Schema::table('quotes', function (Blueprint $table) {
            foreach (['expiry_date', 'shipping_tax', 'currency'] as $column) {
                if (Schema::hasColumn('quotes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
