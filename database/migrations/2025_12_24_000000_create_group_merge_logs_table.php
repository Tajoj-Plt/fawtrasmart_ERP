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
        if (! Schema::hasTable('group_merge_logs')) {
            Schema::create('group_merge_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_group_id')->comment('المجموعة المضمومة');
                $table->unsignedBigInteger('target_group_id')->comment('المجموعة المستهدفة');
                $table->string('source_group_name')->comment('اسم المجموعة المضمومة');
                $table->string('target_group_name')->comment('اسم المجموعة المستهدفة');
                $table->integer('moved_count')->comment('عدد العملاء المنقولين');
                $table->longText('clients_backup')->comment('بيانات العملاء قبل النقل (JSON)');
                $table->boolean('is_reversed')->default(false)->comment('هل تم التراجع عن الضم');
                $table->timestamp('reversed_at')->nullable()->comment('وقت التراجع');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_merge_logs');
    }
};
