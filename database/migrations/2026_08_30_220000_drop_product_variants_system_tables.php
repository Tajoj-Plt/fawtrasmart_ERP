<?php

use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;

/**
 * حذف نظام متغيرات المنتجات وخصائصها (الألوان/المقاسات) من المتجر الإلكتروني.
 *
 * يُلغي بالكامل ما أنشأته 2026_01_11_100001_create_product_variants_system_tables.php:
 * لا شاشة إدارة خصائص، ولا زرّ متغيرات في شاشات المنتج، ولا حقل variant_id
 * في سلّة الشراء أو الطلبات — يُحذف الأخير كودياً في نفس الدفعة (لا عمود
 * حقيقي له، كان مخزَّناً داخل عمود options الحرّ في cart_items/online_order_items).
 */
return new class extends Migration
{
    public function up(): void
    {
        /* `products.has_variants` و`variant_display_type` يبقيان.
           كانا يُحذفان هنا. وعمودان منطقيّان في جدول المنتجات لا يكلّفان
           شيئاً، بينما حذفُهما يمحو ما فيهما ويجعل هذا الملفّ «متلفاً»
           فتقف عنده شاشةُ التحديث ولا ينزل ما بعده. */

        /* والجداول: تقاعدٌ لا حذف — انظر [\App\Support\SchemaRetire].
           الفارغُ منها يُسقَط، وما فيه صفوفٌ يُعاد تسميته إلى `retired_*`
           فيبقى ما أدخله العميل من متغيّرات وخصائص، **ويتحرّر الاسم**
           لـ`2026_08_30_230000_create_product_options_and_variants_tables`
           التي تُنشئ `product_variants` بشكلٍ جديد بعد هذا الملفّ مباشرة.
           ولولا التحرير لوجدت الجدولَ قائماً بالشكل القديم فتخطّته بحارس
           `hasTable`، وبقي النظام يقرأ أعمدةً لا وجود لها.

           والترتيب من التابع إلى المتبوع: مفتاحُ التفاصيل على رأسه. */
        SchemaRetire::tables([
            'product_attribute_mappings',
            'product_variant_stock',
            'product_variant_attributes',
            'product_variants',
            'product_attribute_values',
            'product_attributes',
        ]);
    }

    public function down(): void
    {
        // لا عودة تلقائية — أُعيد إنشاء الجداول من
        // 2026_01_11_100001_create_product_variants_system_tables.php نفسها
        // إن استُدعيت up() عليها من جديد؛ بياناتٌ حُذفت هنا لا تُستعاد.
    }
};
