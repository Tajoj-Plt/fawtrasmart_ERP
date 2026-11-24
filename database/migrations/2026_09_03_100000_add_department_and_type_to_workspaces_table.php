<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يجعل مساحة العمل حاوية فريق حقيقية: تصنيفها كمساحة قسم واحد أو مساحة
 * مشتركة بين عدة أقسام، وربطها اختيارياً بقسم محدد عند كونها من النوع الأول.
 * لا قيد قاعدة بيانات يفرض تطابق أعضاء «workspace_user» مع القسم — يُطبَّق
 * ذلك في طبقة الخدمة (WorkspaceService) أسوةً بقواعد التسلسل الهرمي
 * للمهام المطبَّقة في PHP لا في قيود الجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('admin_id')
                    ->comment('القسم المالك — إلزامي عملياً عندما workspace_type = department');
            }

            if (! Schema::hasColumn('workspaces', 'workspace_type')) {
                $table->enum('workspace_type', ['department', 'shared'])->default('shared')->after('department_id')
                    ->comment('department: أعضاؤها من قسم واحد فقط | shared: مفتوحة لعدة أقسام');
            }
        });

        if (Schema::hasColumn('workspaces', 'department_id')) {
            try {
                Schema::table('workspaces', function (Blueprint $table) {
                    if (! SchemaGuard::hasForeignKey('workspaces', 'workspaces_department_id_foreign')) {
                        $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                    }
                });
            } catch (\Throwable $e) {
                // SQLite لا يدعم إضافة قيد FK على عمود موجود عبر ALTER TABLE؛
                // العلاقة تبقى منطقية (Eloquent) بلا قيد صارم على هذا المحرك فقط.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'workspace_type')) {
                $table->dropColumn('workspace_type');
            }
        });

        if (Schema::hasColumn('workspaces', 'department_id')) {
            try {
                Schema::table('workspaces', function (Blueprint $table) {
                    $table->dropForeign(['department_id']);
                });
            } catch (\Throwable $e) {
                // بلا قيد على SQLite أصلاً — لا شيء لإسقاطه.
            }

            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });
        }
    }
};
