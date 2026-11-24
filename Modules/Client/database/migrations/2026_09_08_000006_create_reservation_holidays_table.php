<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * العُطَل والأيام الاستثنائية.
 *
 * صفٌّ واحد يخدم حالتين متعاكستين، والفارقُ عمودٌ منطقيّ واحد:
 *
 *   `is_working = false` ← عطلة: اليوم مغلق وإن كان ضمن الدوام الأسبوعي.
 *   `is_working = true`  ← يومٌ استثنائيّ مفتوح: الجمعة تُفتَح لمناسبة،
 *                          بأوقاتٍ خاصّة تتجاوز جدول الأسبوع.
 *
 * وبلا الحالة الثانية تُضطرّ المنشأةُ إلى تعديل جدول الأسبوع ثم إعادته —
 * وهو تعديلٌ بأثرٍ رجعيّ يغيّر تفسيرَ حجوزاتٍ ماضية.
 *
 * `branch_id` فارغاً ⇒ عطلةٌ عامّة لكل الفروع.
 * `employee_id` فارغاً ⇒ تسري على الجميع؛ ومملوءاً تكون إجازةً فردية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_holidays')) {
            return;
        }

        Schema::create('reservation_holidays', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();

            /* انظر ترويسة الملف */
            $table->boolean('is_working')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date'], 'reservation_holidays_range_index');
            $table->index('branch_id', 'reservation_holidays_branch_index');
            $table->index('employee_id', 'reservation_holidays_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_holidays');
    }
};
