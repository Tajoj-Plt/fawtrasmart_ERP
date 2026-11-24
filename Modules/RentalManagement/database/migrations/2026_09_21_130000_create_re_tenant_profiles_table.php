<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات المستأجر الشخصية — ما لا يحمله ملفّ العميل المركزي: الهوية
 * والجنسية وجهة العمل وجهة اتصال الطوارئ. صفٌّ واحد لكل عميل، والاسم
 * والجوال يبقيان في `clients` مصدراً واحداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('re_tenant_profiles')) {
            return;
        }

        Schema::create('re_tenant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->unique();
            $table->string('id_type', 30)->default('national_id');
            $table->string('id_number', 50)->nullable()->index();
            $table->date('id_issue_date')->nullable();
            $table->date('id_expiry_date')->nullable();
            $table->string('id_issue_place', 100)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->unsignedSmallInteger('family_members')->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('employer', 150)->nullable();
            $table->string('employer_phone', 30)->nullable();
            $table->decimal('monthly_income', 14, 2)->nullable();
            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_relation', 50)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->text('attachments')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_tenant_profiles');
    }
};
