<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
    جداول إدارة العقارات — كلّها ببادئة `re_`.

    البادئة ليست زينة: `units` و`contracts` و`reservations` أسماءٌ محجوزة في
    النظام لمعانٍ أخرى (وحدات القياس، عقود الموظفين، إعدادات الحجز)، فأيّ
    اسمٍ عارٍ هنا يصطدم بها.

    والأطراف (مستأجر، مالك، مقدّم بلاغ) مراجعُ إلى `clients` لا جداولَ
    جديدة: العميل يُستدعى من ملفّه المركزي وتُضاف إليه علاقته العقارية فقط.
    ومراجعُ الجداول القديمة (`clients`, `users`, `accounts`, `branches`) بلا
    قيد مفتاحٍ أجنبي — أنواعُ مفاتيحها ليست موحّدة عبر قواعد العملاء — أمّا
    ما بين جداول `re_` نفسها فمقيَّد.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('re_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('re_complexes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('usage_type', 30)->default('residential');
            $table->unsignedBigInteger('owner_client_id')->nullable()->index();
            $table->unsignedBigInteger('manager_user_id')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complex_id')->nullable()->constrained('re_complexes')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('entity_type', 30)->default('building');
            $table->string('usage_type', 30)->default('residential');
            $table->unsignedBigInteger('owner_client_id')->nullable()->index();
            $table->unsignedBigInteger('manager_user_id')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('floors_count')->default(0);
            $table->unsignedInteger('expected_units_count')->default(0);
            $table->decimal('total_area', 12, 2)->nullable();
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('description')->nullable();
            $table->text('images')->nullable();
            $table->text('attachments')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->integer('number');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['building_id', 'number']);
        });

        Schema::create('re_amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('re_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('re_floors')->nullOnDelete();
            $table->string('unit_no', 50);
            $table->string('unit_type', 30)->default('apartment')->index();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->default(0);
            $table->unsignedSmallInteger('bathrooms')->default(0);
            $table->unsignedSmallInteger('living_rooms')->default(0);
            $table->unsignedSmallInteger('kitchens')->default(0);
            $table->unsignedSmallInteger('parking_count')->default(0);
            $table->string('orientation', 30)->nullable();
            $table->string('furnished_status', 30)->default('unfurnished');
            $table->string('unit_condition', 30)->default('good');
            // حالة الإشغال مستقلّة عن حالة العرض: قد تكون الوحدة شاغرة وغير منشورة
            $table->string('occupancy_status', 30)->default('vacant')->index();
            $table->date('availability_from')->nullable();
            $table->date('vacant_since')->nullable();
            $table->decimal('base_rent', 15, 2)->default(0);
            $table->string('rent_period', 20)->default('yearly');
            $table->boolean('is_marketable')->default(true);
            $table->string('electricity_meter')->nullable();
            $table->string('water_meter')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('images')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            // رقم الوحدة فريد داخل المبنى لا على مستوى الشركة
            $table->unique(['building_id', 'unit_no']);
        });

        Schema::create('re_unit_amenity', function (Blueprint $table) {
            $table->foreignId('unit_id')->constrained('re_units')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('re_amenities')->cascadeOnDelete();
            $table->primary(['unit_id', 'amenity_id']);
        });

        Schema::create('re_listings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('unit_id')->nullable()->constrained('re_units')->nullOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('re_buildings')->nullOnDelete();
            $table->string('listing_type', 20)->default('rent')->index();
            $table->string('title');
            $table->decimal('asking_price', 15, 2)->default(0);
            $table->string('rent_period', 20)->nullable();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_value', 15, 2)->nullable();
            $table->date('publish_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('assigned_agent_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('features')->nullable();
            $table->text('images')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('inquiries_count')->default(0);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('contract_type', 30)->default('residential_lease')->index();
            $table->unsignedBigInteger('tenant_client_id')->index();
            $table->unsignedBigInteger('owner_client_id')->nullable()->index();
            $table->foreignId('unit_id')->constrained('re_units');
            $table->foreignId('building_id')->nullable()->constrained('re_buildings')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('re_listings')->nullOnDelete();
            // التجديد عقدٌ جديد يشير إلى سابقه — لا تعديلٌ على القديم
            $table->foreignId('previous_contract_id')->nullable()->constrained('re_contracts')->nullOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->unsignedSmallInteger('duration_months')->default(12);
            $table->decimal('annual_value', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->string('payment_frequency', 20)->default('monthly');
            $table->decimal('security_deposit', 15, 2)->default(0);
            $table->boolean('tax_included')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('signed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->decimal('deposit_refunded', 15, 2)->default(0);
            $table->decimal('deposit_deducted', 15, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->text('notes')->nullable();
            $table->text('attachments')->nullable();
            $table->dateTime('last_alert_sent_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_contract_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // أيّ تغييرٍ في القيمة أو المدّة بعد الاعتماد يُسجَّل هنا بمن غيّره ومتى
        Schema::create('re_contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('field', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('re_contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->date('new_start_date');
            $table->date('new_end_date');
            $table->decimal('new_rent_value', 15, 2);
            $table->decimal('increase_percentage', 7, 2)->default(0);
            $table->string('payment_frequency', 20)->default('monthly');
            $table->decimal('deposit_adjustment', 15, 2)->default(0);
            $table->string('renewal_status', 30)->default('draft')->index();
            $table->foreignId('renewed_contract_id')->nullable()->constrained('re_contracts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('re_customer_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->text('roles')->nullable();
            $table->foreignId('building_id')->nullable()->constrained('re_buildings')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('re_units')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('re_listings')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('re_contracts')->nullOnDelete();
            $table->string('relationship_status', 30)->default('active')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->dateTime('last_activity_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('re_viewings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->nullable()->constrained('re_listings')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('re_units')->nullOnDelete();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_phone', 40)->nullable();
            $table->string('kind', 20)->default('viewing'); // inquiry | viewing
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('re_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->string('kind', 20)->default('rent'); // rent | deposit
            $table->date('due_date')->index();
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('scheduled')->index();
            $table->dateTime('last_alert_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('re_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('contract_id')->constrained('re_contracts');
            $table->unsignedBigInteger('client_id')->index();
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('unallocated_amount', 15, 2)->default(0);
            $table->string('payment_method', 20)->default('cash');
            // كما في سندات القبض: العمود يحمل `accounts.id` لا `treasuries.id`
            $table->unsignedBigInteger('treasury_account_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->text('attachments')->nullable();
            $table->string('posting_status', 20)->default('draft')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->string('currency', 10)->nullable();
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('re_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('re_payments')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('re_payment_schedules')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('re_maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('building_id')->nullable()->constrained('re_buildings')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('re_units')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('re_contracts')->nullOnDelete();
            $table->unsignedBigInteger('reported_by_client_id')->nullable()->index();
            $table->string('category', 30)->default('other')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 30)->default('open')->index();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
            $table->unsignedBigInteger('contractor_supplier_id')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->string('charge_to', 20)->default('owner');
            $table->text('resolution_notes')->nullable();
            $table->text('attachments')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_maintenance_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('re_maintenance_requests')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('cost_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /*
            مهام العقارات في جدولها: `tasks` المركزي مربوطٌ بمشروع ولا يحمل
            علاقةً متعدّدة الأشكال، وسابقةُ النظام في مثل هذا جدولٌ مخصَّص
            (`visit_follow_up_tasks`) لا عمودٌ يُحشر في `tasks`.
        */
        Schema::create('re_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('activity_type', 30)->default('follow_up');
            $table->string('related_type', 30)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->string('priority', 20)->default('medium');
            $table->dateTime('due_at')->nullable()->index();
            $table->string('status', 30)->default('new')->index();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('re_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name');
            $table->string('report_key', 60);
            $table->text('filters')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            're_saved_reports', 're_tasks', 're_maintenance_costs', 're_maintenance_requests',
            're_payment_allocations', 're_payments', 're_payment_schedules', 're_viewings',
            're_customer_relationships', 're_contract_renewals', 're_contract_amendments',
            're_contract_terms', 're_contracts', 're_listings', 're_unit_amenity', 're_units',
            're_amenities', 're_floors', 're_buildings', 're_complexes', 're_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
