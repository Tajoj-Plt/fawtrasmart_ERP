<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السنوات المالية — الوعاء الذي تنتمي إليه الفترات المحاسبية.
 *
 * سنة مالية واحدة تحمل فتراتها (شهرية أو ربعية أو نصفية أو سنوية)، وقفلها
 * يقفل فتراتها جميعاً. الفصل بين الجدولين مقصود: التقارير الختامية تُبنى
 * على السنة، ومنع القيد يُبنى على الفترة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');

                // open: يُقبل القيد فيها — closed: لا يُقبل حتى تُعاد فتحها
                $table->enum('status', ['open', 'closed'])->default('open');

                // السنة الجارية: واحدة فقط، تُستعمل افتراضاً في الشاشات والتقارير
                $table->boolean('is_current')->default(false);

                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // البحث الأكثر تكراراً: «أي سنة يقع فيها هذا التاريخ؟»
                $table->index(['start_date', 'end_date'], 'fiscal_years_range_index');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
