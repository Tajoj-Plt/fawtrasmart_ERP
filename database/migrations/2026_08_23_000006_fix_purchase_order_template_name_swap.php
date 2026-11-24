<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يصحّح تسميات قوالب زرعتها `2026_08_23_000004_seed_missing_purchase_cycle_templates`
 * قبل أن يُحسم مع المستخدم أيّهما «طلب الشراء» وأيّهما «أمر الشراء»:
 *
 *   - `purchase_order` (شاشة ordersPurchase) = «طلب الشراء» — زُرعت باسم
 *     «أمر شراء - …» خطأً.
 *   - `purchase_order_request` (PurchaseOrdersRequestsController) = «أمر
 *     الشراء» — زُرعت باسم «طلب شراء - …» خطأً، معكوسةً تماماً.
 *
 * التصحيحات الأخرى بعد الحسم (settings.php، purchase_orders.php،
 * purchase_cycle.php، ملفّات PDF المدمجة في purchasing_order_requests)
 * طُبِّقت كلّها فوراً؛ هذا الترحيل وحده فات وقتها لأن قوالب `document_templates`
 * كانت مزروعة سلفاً في القاعدة قبل الحسم، فلم يعد كودُ الزرع يعمل عليها مرّة
 * أخرى — التصحيح هنا يعمل على البيانات القائمة مباشرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap('purchase_order', 'أمر شراء', 'طلب شراء');
        $this->swap('purchase_order_request', 'طلب شراء', 'أمر شراء');
    }

    public function down(): void
    {
        $this->swap('purchase_order', 'طلب شراء', 'أمر شراء');
        $this->swap('purchase_order_request', 'أمر شراء', 'طلب شراء');
    }

    protected function swap(string $documentType, string $from, string $to): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->where('document_type', $documentType)
            ->where('name', 'like', $from.'%')
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($from, $to) {
                foreach ($templates as $template) {
                    $name = str_replace($from, $to, (string) $template->name);

                    if ($name === $template->name) {
                        continue;
                    }

                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update(['name' => $name, 'updated_at' => now()]);
                }
            });
    }
};
