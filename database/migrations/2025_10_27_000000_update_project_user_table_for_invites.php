<?php

use App\Support\SchemaGuard;
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
        Schema::table('project_user', function (Blueprint $table) {
            // التأكد من وجود الأعمدة المطلوبة للدعوات
            if (!Schema::hasColumn('project_user', 'email')) {
                $table->string('email')->nullable()->comment('البريد الإلكتروني للدعوات (للمستخدمين غير المسجلين)');
            }
            
            if (!Schema::hasColumn('project_user', 'invite_token')) {
                $table->string('invite_token', 64)->nullable()->unique()->comment('رمز الدعوة');
            }
            
            if (!Schema::hasColumn('project_user', 'status')) {
                $table->enum('status', ['active', 'pending', 'declined', 'expired'])->default('active')->comment('حالة العضوية/الدعوة');
            } else {
                // تحديث القيم الافتراضية إذا كانت موجودة
                $table->string('status')->default('active')->change();
            }
            
            if (!Schema::hasColumn('project_user', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->comment('تاريخ إرسال الدعوة');
            }
            
            if (!Schema::hasColumn('project_user', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->comment('تاريخ انتهاء الدعوة');
            }
            
            if (!Schema::hasColumn('project_user', 'invited_by')) {
                $table->unsignedBigInteger('invited_by')->nullable()->comment('من أرسل الدعوة');
            }
            
            if (!Schema::hasColumn('project_user', 'invite_message')) {
                $table->text('invite_message')->nullable()->comment('رسالة الدعوة الاختيارية');
            }
            
            // إضافة عمود joined_at إذا لم يكن موجوداً
            if (!Schema::hasColumn('project_user', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->comment('تاريخ انضمام المستخدم للمشروع');
            }
            
            // إضافة فهارس إذا لم تكن موجودة
            if (! SchemaGuard::hasIndex('project_user', 'project_user_project_id_status_index')) {
                $table->index(['project_id', 'status']);
            }
            if (! SchemaGuard::hasIndex('project_user', 'project_user_invite_token_index')) {
                $table->index(['invite_token']);
            }
            if (! SchemaGuard::hasIndex('project_user', 'project_user_email_index')) {
                $table->index(['email']);
            }
            if (! SchemaGuard::hasIndex('project_user', 'project_user_expires_at_index')) {
                $table->index(['expires_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            // لا نقوم بإزالة الأعمدة الموجودة لأنها قد تكون قيد الاستخدام
            // فقط نقوم بإزالة الفهارس المضافة
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['invite_token']);
            $table->dropIndex(['email']);
            $table->dropIndex(['expires_at']);
        });
    }
};