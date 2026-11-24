<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ عمليات الاسترجاع.
 *
 * الاسترجاع هو العملية الوحيدة في النظام التي تكتب فوق قاعدة البيانات
 * كاملة، فيلزمها أثر مستقلّ عن سجلّ النسخ: من نفّذها، ومن أي نسخة، وأي
 * نسخة أمان أُخذت قبلها (`safety_backup_id`) — وهي طريق العودة إن ساء
 * الأمر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_restores')) {
            Schema::create('backup_restores', function (Blueprint $table) {
                $table->id();

                $table->foreignId('backup_id')->constrained('backups')->cascadeOnDelete();
                // النسخة التلقائية المأخوذة قبل الاسترجاع — قد تفشل هي نفسها
                $table->foreignId('safety_backup_id')->nullable()->constrained('backups')->nullOnDelete();

                // pending | running | completed | failed
                $table->string('status', 20)->default('pending');

                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('duration_ms')->nullable();

                $table->unsignedInteger('statements_executed')->nullable();
                $table->text('error_message')->nullable();
                $table->longText('output')->nullable();

                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();

                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
    }
};
