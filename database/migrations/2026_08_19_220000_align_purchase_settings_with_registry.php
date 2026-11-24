<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يُوائم `purchase_invoice_settings` مع سجلّ `PurchaseSettings::catalog()`.
 *
 * يُسقط `custom_daily_entries` («وضع مخصص للقيود اليومية») — مفتاحٌ زرعه
 * ترحيل الجدول الأصلي ولم يقرأه سطرٌ في النظام قطّ، فكان يُبدَّل في الشاشة
 * بلا أثر. ويزرع مفاتيح المرتجعات والموردين التي لم تكن في الجدول أصلاً.
 * ويُصحّح تسميات المفاتيح الباقية إلى ما تعرضه الشاشة الجديدة، فقد كانت
 * تسميةٌ منها تصف سلوكاً غير الذي يقع.
 *
 * لا يمسّ حالات التفعيل — إلا مفتاحين، انظر `REVIVE` أدناه.
 */
return new class extends Migration
{
    /** التسميات — مطابِقة لـ`resources/lang/ar/purchase_settings.php` */
    private const LABELS = [
        // فاتورة الشراء
        'default_received_invoices' => 'اعتبار فواتير الشراء مستلمة بالكامل افتراضياً',
        'default_paid_invoices' => 'اعتبار فواتير الشراء مدفوعة بالكامل افتراضياً',
        'auto_payment' => 'الدفع التلقائي من رصيد المورد',
        'update_product_prices' => 'تحديث سعر شراء المنتج من الفاتورة',
        'manual_invoice_status' => 'حالة يدوية لفاتورة الشراء',

        // الطلبات والأوامر وعروض الأسعار
        'manual_purchase_orders' => 'حالة يدوية لطلب الشراء',
        'manual_purchase_orders_status' => 'حالة يدوية لأمر الشراء',
        'manual_quote_orders' => 'حالة يدوية لطلب عرض الأسعار',
        'manual_purchase_quotes' => 'حالة يدوية لعرض سعر المشتريات',

        // المرتجعات — جديدة
        'return_default_received' => 'اعتبار المرتجع مسلَّماً للمورد افتراضياً',
        'allow_multiple_returns' => 'السماح بأكثر من مرتجع للفاتورة الواحدة',

        // الإشعارات
        'enable_debit_notice' => 'تفعيل الإشعار المدين',

        // الموردون — جديدة
        'supplier_require_name' => 'إلزام اسم المورد',
        'supplier_require_mobile' => 'إلزام جوال المورد',
        'supplier_require_tax_number' => 'إلزام الرقم الضريبي للمورد',
        'supplier_require_commercial_registration' => 'إلزام السجل التجاري للمورد',
        'supplier_unique_tax_number' => 'منع تكرار الرقم الضريبي',
        'supplier_unique_mobile' => 'منع تكرار جوال المورد',
        'supplier_unique_email' => 'منع تكرار بريد المورد',
        'supplier_block_inactive' => 'منع المستندات على مورد موقوف',

        // كل المستندات
        'enable_settlement' => 'تبويب التسوية في نماذج الشراء',
        'total_discounts' => 'إظهار سطر إجمالي الخصومات',
    ];

    /**
     * الافتراضيات — تُستعمل لزرع مفتاحٍ ناقص فقط، لا لإعادة ضبط الموجود.
     *
     * كلّ ما ليس هنا يُزرع مطفأً: الافتراض يصف ما يفعله النظام اليوم، لا ما
     * نراه صواباً. مفاتيح الموردين والمرتجعات كلّها مطفأة لأن `SupplierRequest`
     * لا يُلزم بحقل ولا يمنع تكراراً، والمرتجع لا يُستلَم إلا بتأشير.
     */
    private const DEFAULTS = [
        'default_received_invoices' => true,
        'auto_payment' => true,
        'enable_settlement' => true,
        'total_discounts' => true,
        'enable_debit_notice' => true,
    ];

    /**
     * مفاتيح تُفعَّل ولو كانت مطفأة في الجدول.
     *
     * زُرعت `false` ولم يقرأها سطرٌ قطّ، فسطر إجمالي الخصومات يظهر اليوم
     * في كلّ مستند وشاشات الإشعار المدين تعمل بلا شرط. ولمّا صارت تُقرأ
     * فعلاً، إبقاؤها على قيمتها المزروعة كان سيُخفي الاثنين عمّن لم يطلب
     * ذلك — والقيمة لم تكن اختياراً حتى تُصان.
     */
    private const REVIVE = ['total_discounts', 'enable_debit_notice'];

    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoice_settings')) {
            return;
        }

        DB::table('purchase_invoice_settings')
            ->whereNotIn('setting_key', array_keys(self::LABELS))
            ->delete();

        $existing = DB::table('purchase_invoice_settings')->pluck('setting_key')->all();

        foreach (self::LABELS as $key => $label) {
            if (in_array($key, $existing, true)) {
                $changes = ['setting_name' => $label, 'updated_at' => now()];

                if (in_array($key, self::REVIVE, true)) {
                    $changes['is_active'] = true;
                }

                DB::table('purchase_invoice_settings')
                    ->where('setting_key', $key)
                    ->update($changes);

                continue;
            }

            DB::table('purchase_invoice_settings')->insertOrIgnore([
                'setting_key' => $key,
                'setting_name' => $label,
                'is_active' => self::DEFAULTS[$key] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * لا رجعة: المفتاح المحذوف لم يكن له أثر، وإعادته تعيد مفتاحاً معطَّلاً
     * إلى شاشةٍ لم تعد تعرضه.
     */
    public function down(): void
    {
        //
    }
};
