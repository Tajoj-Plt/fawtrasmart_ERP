<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اشتراك المنشأة في النظام.
 *
 * الجدول القائم `subscriptions` يخصّ اشتراكات **العملاء** في باقات المنشأة
 * (`client_id` + `package_id`)، ولا يصلح لهذا الغرض. أمّا اشتراك المنشأة
 * نفسها في النظام فلم يكن له أثر في قاعدة البيانات إطلاقاً — لا جدول ولا
 * عمود — فلم يكن ممكناً معرفة متى ينتهي.
 *
 * صفّ لكل مدّة اشتراك لا صفّ واحد يُحدَّث: التجديد يُنشئ مدّةً جديدة
 * ويُبقي سابقتها، فيبقى تاريخ الاشتراك قابلاً للقراءة — وهو ما يُسأل عنه
 * عند أي خلاف على فترة أو مبلغ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_subscriptions')) {
            Schema::create('system_subscriptions', function (Blueprint $table) {
                $table->id();

                $table->string('plan_name', 120);
                $table->string('plan_code', 60)->nullable();

                // active | expired | suspended | trial | cancelled
                $table->string('status', 20)->default('active');

                $table->date('started_at');
                $table->date('expires_at')->nullable(); // null = اشتراك دائم

                $table->decimal('price', 12, 2)->default(0);
                $table->string('currency', 10)->default('SAR');
                // monthly | quarterly | semi_annual | yearly | lifetime
                $table->string('billing_cycle', 20)->default('yearly');

                // حدود الباقة — null يعني بلا حدّ، وهو ما يفرّق باقةً عن أخرى
                $table->unsignedInteger('max_users')->nullable();
                $table->unsignedInteger('max_branches')->nullable();
                $table->unsignedInteger('max_clients')->nullable();

                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                // الاستعلام الغالب: «أي مدّة تغطّي اليوم؟»
                $table->index(['status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('system_subscription_invoices')) {
            Schema::create('system_subscription_invoices', function (Blueprint $table) {
                $table->id();

                $table->foreignId('subscription_id')->nullable()
                    ->constrained('system_subscriptions')->nullOnDelete();

                $table->string('invoice_number', 60)->unique();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('SAR');

                // paid | unpaid | overdue | refunded
                $table->string('status', 20)->default('unpaid');

                $table->date('issued_at');
                $table->date('due_at')->nullable();
                $table->date('paid_at')->nullable();

                $table->string('payment_method', 60)->nullable();
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['status', 'issued_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_subscription_invoices');
        Schema::dropIfExists('system_subscriptions');
    }
};
