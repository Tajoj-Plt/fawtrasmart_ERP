<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رابطُ عرض السعر بالفاتورة، والكوبونات في مستندات المبيعات.
 *
 * ── `invoices.quote_id` ──
 *
 * `QuoteController::convertToInvoice()` كانت تمرّر `'quote_id' => $quote->id`
 * إلى `Invoice::create()` منذ كُتبت — والعمودُ لا وجودَ له في الجدول ولا
 * المفتاحُ في `$fillable`، فيُسقَط بصمت. والنتيجةُ أنّ الفاتورة المُحوَّلة
 * لا تعرف عن أيّ عرضٍ صدرت: لا في شاشتها، ولا في تقرير، ولا حتى لمنع
 * تحويل العرض مرّتين. والأثرُ الوحيدُ الباقي كان جملةً في `notes`.
 *
 * و`quotes.invoice_id` كان موجوداً ولا يُكتب أيضاً — الحالة وحدها
 * (`Converted_to_Invoice`) هي ما يُحفَظ. فالطرفان يُملآن الآن معاً.
 *
 * ── الكوبونات ──
 *
 * `store_coupons` محرّكٌ كاملٌ (نسبة/مبلغ/شحن مجاني، حدُّ أدنى للطلب، سقفُ
 * خصم، حدودُ استعمال للعميل وللكوبون، منتجاتٌ وفئاتٌ وعملاءُ مسموحون
 * ومستثنون، نافذةُ صلاحية) — ولم يكن يقرؤه إلّا المتجر الإلكتروني.
 * و`store_coupon_usages.invoice_id` موجودٌ في الجدول منذ إنشائه، أي أنّ
 * الربط بالفاتورة كان متوقَّعاً ولم يُوصَل.
 *
 * العمودان هنا يحفظان **ما طُبِّق فعلاً** لا ما يُعاد حسابه: كوبونٌ تنتهي
 * صلاحيتُه أو يُعدَّل بعد إصدار الفاتورة يجب ألّا يغيّر خصماً صدر به
 * مستند. وهي القاعدةُ نفسُها المتَّبعة في نسخ الضريبة إلى `tax_invoices`.
 *
 * ولا يُحذف عمودٌ في `down()`: القاعدةُ في هذا المشروع أن يُتقاعد العمود
 * ولا يُسقَط.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'quote_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                /* بلا مفتاحٍ أجنبيّ: قواعدُ الإنتاج تسبق مجموعةَ الترحيلات
                   وقد تحمل صفوفاً يتيمة — انظر `SchemaGuard`. */
                $table->unsignedBigInteger('quote_id')->nullable()->after('reference_id')->index();
            });
        }

        foreach (['invoices', 'quotes'] as $documentTable) {
            if (! Schema::hasTable($documentTable)) {
                continue;
            }

            if (! Schema::hasColumn($documentTable, 'coupon_id')) {
                Schema::table($documentTable, function (Blueprint $table) {
                    $table->unsignedBigInteger('coupon_id')->nullable()->index();
                });
            }

            if (! Schema::hasColumn($documentTable, 'coupon_code')) {
                Schema::table($documentTable, function (Blueprint $table) {
                    // الرمزُ كما كُتب لحظةَ الإصدار — يُطبَع على المستند
                    // ولو حُذف الكوبون من الشاشة بعدها
                    $table->string('coupon_code', 64)->nullable();
                });
            }

            if (! Schema::hasColumn($documentTable, 'coupon_discount')) {
                Schema::table($documentTable, function (Blueprint $table) {
                    $table->decimal('coupon_discount', 15, 2)->default(0);
                });
            }
        }

        if (Schema::hasTable('sales_invoice_settings') && ! Schema::hasColumn('sales_invoice_settings', 'enable_coupons')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->boolean('enable_coupons')->default(false);
            });
        }
    }

    public function down(): void
    {
        // يُترك كما هو — لا إسقاطَ لعمودٍ قد يحمل بيانات
    }
};
