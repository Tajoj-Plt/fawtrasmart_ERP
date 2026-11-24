<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة الإشعار الدائن الناقصة.
 *
 * الجدول أُنشئ بعشرين عموداً، والشيفرة تكتب وتقرأ أكثر منها بكثير:
 *
 * **يُكتب ولا يوجد** — `CreditNotificationController::store()` يمرّر
 * `shipping_tax` و`tax_rate` إلى `CreditNotification::create()`، وليس
 * لهما عمود. ويمرّر `tax_total` وليس له عمود كذلك — العمود اسمه
 * `total_tax` ولا يُمرَّر إليه شيء، فضريبةُ **كل** إشعار دائن محفوظةٍ
 * حتى اليوم `null`. (وثلاثتها خارج `$fillable` أيضاً، فتُسقط قبل أن
 * تصل إلى الاستعلام أصلاً.)
 *
 * **يُقرأ ولا يوجد** — `index()` يفلتر على `currency` و`due_date`
 * و`source` و`custom_field` و`shipping_option` و`post_shift`
 * و`order_source`. لا عمود لأيٍّ منها، فأيّ فلتر منها يُسقط الشاشة
 * بخطأ SQL. تُحذف تلك الفلاتر من المتحكّم، ويُضاف هنا ما له معنى فعلاً.
 *
 * **مطلوب ولا يوجد** — الإشعار الدائن يصدر عن فاتورة (نظير «الإشعار
 * المدين» في المشتريات الذي يحمل `reference_id`)، ولا عمود يربطه بها؛
 * فلا يُعرف من أي فاتورة صدر إلّا بقراءة الملاحظات. و`employee_id`
 * تُعرِّفه علاقة `CreditNotification::employee()` وليس له عمود.
 *
 * ولا عمود عملة: تجميد عملة المستند عند حفظه هو ما يمنع تبديلَ العملة
 * الافتراضية لاحقاً من تغيير مستنداتٍ صدرت وأُرسلت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notifications', function (Blueprint $table) {
            // المستند المرجعي — الفاتورة التي صدر عنها الإشعار
            if (! Schema::hasColumn('credit_notifications', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('client_id')->index();
            }

            // العملة تُجمَّد مع المستند
            if (! Schema::hasColumn('credit_notifications', 'currency')) {
                $table->string('currency', 10)->nullable()->after('credit_number');
            }

            // مسؤول المبيعات — العلاقة معرَّفة في النموذج بلا عمود يقابلها
            if (! Schema::hasColumn('credit_notifications', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('created_by')->index();
            }

            // المستودع الذي تُردّ إليه البضاعة — يُختار في الشاشة ولا يُحفَظ
            if (! Schema::hasColumn('credit_notifications', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')->nullable()->after('employee_id')->index();
            }

            // ردّ المبلغ: طريقته وخزينته
            if (! Schema::hasColumn('credit_notifications', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable()->after('grand_total');
            }

            if (! Schema::hasColumn('credit_notifications', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('payment_method');
            }

            // ضريبة الشحن ونسبة الضريبة — تُحسبان في الحفظ ثمّ تُرميان
            if (! Schema::hasColumn('credit_notifications', 'shipping_tax')) {
                $table->decimal('shipping_tax', 10, 2)->nullable()->default(0)->after('shipping_cost');
            }

            if (! Schema::hasColumn('credit_notifications', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->nullable()->default(0)->after('tax_type');
            }

            if (! Schema::hasColumn('credit_notifications', 'attachments')) {
                $table->string('attachments')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_notifications', function (Blueprint $table) {
            foreach ([
                'invoice_id', 'currency', 'employee_id', 'store_house_id',
                'payment_method', 'treasury_id', 'shipping_tax', 'tax_rate', 'attachments',
            ] as $column) {
                if (Schema::hasColumn('credit_notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
