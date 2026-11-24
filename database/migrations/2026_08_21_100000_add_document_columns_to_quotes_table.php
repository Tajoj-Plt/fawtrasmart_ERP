<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة عرض السعر الناقصة.
 *
 * `QuoteController` و`App\Models\Quote` يكتبان أعمدةً لا وجود لها في
 * الجدول، فيسقط الحفظ بـ:
 *
 *     SQLSTATE[42S22]: Unknown column 'tax_rate' in 'field list'
 *
 * والنموذج يعدّها في `$fillable` و`$casts`، والمتحكّم يمرّرها إلى
 * `create()` و`update()`، والتحقّق يقبلها في `rules()` — فلا شيء ينبّه
 * إلى غيابها قبل أن يصل الاستعلام إلى القاعدة.
 *
 * الناقص:
 *
 *   • `tax_rate`      نسبة الضريبة المطبَّقة
 *   • `employee_id`   مسؤول المبيعات
 *   • `store_house_id` المستودع الذي تُحجز منه البضاعة عند التحويل
 *   • `payment_method` و`treasury_id`  طريقة السداد وخزينته
 *   • `attachments`   المرفقات
 *   • `invoice_id`    الفاتورة التي حُوِّل إليها العرض — الحالة
 *     `Converted_to_Invoice` موجودة في `enum` الحالات ولا عمود يربطها
 *     بالفاتورة، فلا يُعرف إلى أيّ فاتورة تحوّل العرض
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            /*
                `expiry_date` و`currency` و`shipping_tax` **ليست هنا**:
                يُنشئها الترحيلُ الذي يسبق هذا بيومٍ واحد —
                `2026_08_20_210000_add_document_fields_to_quotes_table` —
                وكانت مُعادةً هنا بتعريفٍ مختلفٍ قليلاً (`currency` بطول ١٠
                لا ٨، و`shipping_tax` بقيمةٍ افتراضية). فالعمودُ يُنشئه أوّلُ
                الترحيلين ويتخطّاه الثاني بحارسه، فيبقى التعريفُ رهنَ ترتيب
                التنفيذ لا رهنَ قرار — وهو أسوأ ما يكون في وصف عمود.

                فصار المُعلِن واحداً: ذاك الترحيل. وما بقي هنا هو الجديدُ
                وحدَه. (وتعبئةُ العملة للصفوف القديمة أسفلَ الدالة باقيةٌ:
                هي عملٌ على البيانات لا تعريفٌ لعمود.)
            */
            if (! Schema::hasColumn('quotes', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->nullable()->default(0)->after('tax_type');
            }

            if (! Schema::hasColumn('quotes', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('created_by')->index();
            }

            if (! Schema::hasColumn('quotes', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')->nullable()->after('employee_id')->index();
            }

            if (! Schema::hasColumn('quotes', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable()->after('grand_total');
            }

            if (! Schema::hasColumn('quotes', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('quotes', 'attachments')) {
                $table->string('attachments')->nullable()->after('notes');
            }

            // الفاتورة الناتجة عن تحويل العرض
            if (! Schema::hasColumn('quotes', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('client_id')->index();
            }
        });

        // العملة للعروض القائمة: تُملأ بعملة النظام لا تُترك فارغة، وإلا
        // طُبعت بلا رمز وقُرئت في التقارير كأنّها بلا عملة
        if (Schema::hasColumn('quotes', 'currency')) {
            try {
                $default = \App\Support\CurrencySettings::code();

                \Illuminate\Support\Facades\DB::table('quotes')
                    ->whereNull('currency')
                    ->update(['currency' => $default]);
            } catch (\Throwable $e) {
                // إعدادات العملة غير مهيّأة بعد — تبقى فارغةً وتُقرأ
                // بالافتراضية وقت العرض
            }
        }
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // بلا `expiry_date` و`currency` و`shipping_tax`: لم تعد هذه
            // الهجرة تُنشئها، فلا تملك إسقاطها
            foreach ([
                'tax_rate', 'employee_id', 'store_house_id',
                'payment_method', 'treasury_id', 'attachments', 'invoice_id',
            ] as $column) {
                if (Schema::hasColumn('quotes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
