<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول عضوية مساحة العمل — يُفعِّل فعلياً Workspace::users()/hasMember() اللتين
 * كانتا تشيران إلى جدول غير موجود (workspace_user) قبل هذا الترحيل، فتفشلان
 * بخطأ "no such table" عند استدعائهما. admin_id يبقى في جدول workspaces
 * كمالك أصلي/منشئ، وهذا الجدول هو قائمة الفريق الفعلية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspace_user')) {
            return;
        }

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'member'])->default('member')->comment('owner: منشئ المساحة أو من مُنح صلاحية الإدارة | member: عضو عادي');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
