<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استلام المشتريات — المستند الجديد بين أمر الشراء وفاتورة الشراء.
 *
 * أمر الشراء وفاتورة الشراء صفّان في `purchase_invoices` اليوم (النوع
 * `Requested`/`invoice`)، وبنودهما في `invoice_items` الموحّد — لكن لا
 * عمود «كمية مستلمة» ولا مستند استلامٍ حقيقي في أيٍّ منهما (انظر توثيق
 * `Modules\Purchases\Support\ReceivingLedger`). هذان الجدولان يضيفان ذلك:
 * حبيبةُ الاستلام هي **سطر أمر الشراء** (`purchase_order_item_id` يشير
 * إلى صفٍّ في `invoice_items`)، لا الصنف مجرَّداً — فلو تكرّر الصنف في
 * سطرين لا يلتبس أيُّهما استُلم.
 *
 * لا `unit_price`/`ordered_quantity` هنا: السعر والكمية المطلوبة صفتان
 * لسطر الأمر نفسه، تُقرآن منه حيّتين وقت التحويل إلى فاتورة، فلا يختلّ
 * شيء لو عُدِّل الأمر قبل الاستلام (وهو ممنوعٌ عملياً بعد الاعتماد، لكن
 * لا داعي لتخزين ما يمكن قراءته).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receivings')) {
            Schema::create('purchase_receivings', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->foreignId('purchase_order_id')->constrained('purchase_invoices')->restrictOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('store_house_id')->constrained('store_houses')->restrictOnDelete();
                $table->date('receiving_date');
                $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
                $table->foreignId('warehouse_permit_id')->nullable()->constrained('warehouse_permits')->nullOnDelete();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['purchase_order_id', 'status']);
                $table->index(['status', 'receiving_date']);
            });
        }

        if (! Schema::hasTable('purchase_receiving_items')) {
            Schema::create('purchase_receiving_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_receiving_id')->constrained('purchase_receivings')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained('invoice_items')->restrictOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->decimal('received_quantity', 15, 4);
                // مجموع ما فُوتر فعلاً من هذا السطر — يتراكم مع كل تحويل جزئي
                $table->decimal('invoiced_quantity', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['purchase_receiving_id', 'purchase_order_item_id'], 'pri_receiving_order_item_unique');
                $table->index('purchase_order_item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receiving_items');
        Schema::dropIfExists('purchase_receivings');
    }
};
