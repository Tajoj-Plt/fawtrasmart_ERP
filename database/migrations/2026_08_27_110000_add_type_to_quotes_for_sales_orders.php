<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أوامر البيع — نوعُ مستندٍ في جدول عروض الأسعار.
 *
 * «تفعيل أوامر البيع» كان مفتاحاً في «إعدادات الفواتير» بلا شاشةٍ يفتحها:
 * لا نموذج `SalesOrder`، ولا متحكّم، ولا مسار. والمستخدم يطلبها كما هي في
 * المشتريات — أمرٌ يُعتمد ثمّ يُحوَّل إلى فاتورة.
 *
 * ولم يُنشأ لها جدولٌ جديد، بل صارت **نوعاً** في `quotes`. والسبب أنّ أمر
 * البيع وعرض السعر مستندٌ واحدٌ في بنيته: عميلٌ وبنودٌ وأسعارٌ وخصمٌ وضريبةٌ
 * وإجمالٌ وحالةٌ، ثمّ تحويلٌ إلى فاتورة. وجدولٌ ثانٍ يعني نسخَ
 * `convertToInvoice()` — سبعمئة سطرٍ فيها القيدُ المحاسبي وإذنُ الصرف
 * وخصمُ المخزون وحدُّ الائتمان — نسخةً ثانية تفترق عن أصلها عند أوّل
 * تعديل. وهذا هو نمط المشتريات نفسه: «أمر الشراء» صفٌّ في
 * `purchase_invoices` بـ`type = 'Requested'`.
 *
 * فهرسٌ مركَّب على (`type`, `quote_date`): كلّ شاشةٍ تقرأ نوعَها وحده
 * مرتَّباً بالتاريخ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotes', 'type')) {
            Schema::table('quotes', function (Blueprint $table) {
                // نصٌّ لا `enum`: إضافةُ نوعٍ ثالث لاحقاً لا تحتاج تعديل عمود
                if (! Schema::hasColumn('quotes', 'type')) {
                    $table->string('type', 20)->default('quote')->after('id')->index();
                }
            });

            // الصفوف القائمة كلّها عروض أسعار — لا أوامر بيع قبل هذه الهجرة
            DB::table('quotes')->whereNull('type')->update(['type' => 'quote']);
        }

        if (! $this->hasIndex('quotes', 'quotes_type_date_index')) {
            Schema::table('quotes', function (Blueprint $table) {
                if (! SchemaGuard::hasIndex('quotes', 'quotes_type_date_index')) {
                    $table->index(['type', 'quote_date'], 'quotes_type_date_index');
                }
            });
        }
    }

    /** أموجودٌ فهرسٌ بهذا الاسم؟ — `Schema` لا تقدّم فحصاً له */
    private function hasIndex(string $table, string $index): bool
    {
        try {
            // Schema::getIndexes بديلٌ محمولٌ عن SHOW INDEX الخاص بـMySQL
            return collect(Schema::getIndexes($table))
                ->contains(fn ($row) => ($row['name'] ?? null) === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('quotes', 'quotes_type_date_index')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropIndex('quotes_type_date_index');
            });
        }

        if (Schema::hasColumn('quotes', 'type')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
