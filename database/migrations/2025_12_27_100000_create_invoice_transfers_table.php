<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoice_transfers')) {
            Schema::create('invoice_transfers', function (Blueprint $table) {
                $table->id();

                // معرف الفاتورة
                $table->unsignedBigInteger('invoice_id');

                // الموظف المرسل (من)
                $table->unsignedBigInteger('from_employee_id');

                // الموظف المستلم (إلى)
                $table->unsignedBigInteger('to_employee_id');

                // العميل صاحب الفاتورة
                $table->unsignedBigInteger('client_id')->nullable();

                // حالة التسليم: pending, accepted, rejected
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');

                // تاريخ الإرسال
                $table->timestamp('sent_at')->nullable();

                // تاريخ الاستلام/الرفض
                $table->timestamp('responded_at')->nullable();

                // ملاحظات المرسل
                $table->text('sender_notes')->nullable();

                // ملاحظات المستلم (سبب القبول/الرفض)
                $table->text('receiver_notes')->nullable();

                // مبلغ الفاتورة للمرجعية
                $table->decimal('invoice_amount', 15, 2)->default(0);

                // رقم الفاتورة للمرجعية
                $table->string('invoice_code')->nullable();

                $table->timestamps();

                // الفهارس
                $table->index('invoice_id');
                $table->index('from_employee_id');
                $table->index('to_employee_id');
                $table->index('client_id');
                $table->index('status');
                $table->index('sent_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_transfers');
    }
};
