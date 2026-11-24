<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفترات المحاسبية — أصغر وحدة زمنية يُسمح أو يُمنع القيد فيها.
 *
 * ثلاث حالات لا اثنتان:
 *   open   — مفتوحة، يُقبل القيد.
 *   closed — مقفلة إدارياً، تُعاد فتحها بصلاحية.
 *   locked — مقفلة نهائياً بعد المراجعة أو التدقيق، لا تُعاد فتحها إطلاقاً.
 *
 * الفرق بين الأخيرتين هو الفرق بين «انتهى الشهر» و«صُدِّقت الميزانية»:
 * الأولى قرار داخلي قابل للتراجع، والثانية التزام أمام مدقّق خارجي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('fiscal_year_id');
                $table->string('name', 100);

                // ترتيب الفترة داخل سنتها — يُعتمد عليه في العرض لا على التاريخ،
                // فالفترات المضافة يدوياً قد تُدرَج بين فترتين مولَّدتين
                $table->unsignedSmallInteger('sequence')->default(1);

                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['open', 'closed', 'locked'])->default('open');

                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('fiscal_year_id')
                    ->references('id')->on('fiscal_years')
                    ->cascadeOnDelete();

                $table->unique(['fiscal_year_id', 'sequence'], 'accounting_periods_year_sequence_unique');
                $table->index(['start_date', 'end_date'], 'accounting_periods_range_index');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
