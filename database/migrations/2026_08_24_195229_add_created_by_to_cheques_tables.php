<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الشيكات ودفاتر الشيكات بمن أنشأها.
 *
 * الجداول الثلاثة (`payable_cheques`، `received_cheques`، `cheque_books`)
 * لم تحمل عمود منشئٍ إطلاقاً — لا سبيل لمعرفة من أصدر شيكاً أو استلمه أو
 * أنشأ دفتراً، ولا لتقييد ما يراه موظفٌ بحسب تسلسله الإداري كما في بقية
 * وحدة الحسابات (`ScopesToEmployeeHierarchy`، عمودها المعتاد `created_by`
 * على `users.id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payable_cheques', 'received_cheques', 'cheque_books'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'created_by')) {
                    $blueprint->foreignId('created_by')->nullable()->after('id')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['payable_cheques', 'received_cheques', 'cheque_books'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'created_by')) {
                    $blueprint->dropConstrainedForeignId('created_by');
                }
            });
        }
    }
};
