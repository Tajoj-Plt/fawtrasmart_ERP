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
        Schema::table('payment_methods', function (Blueprint $table) {
            // إضافة الحقول الجديدة إذا لم تكن موجودة
            if (!Schema::hasColumn('payment_methods', 'name_en')) {
                $table->string('name_en')->nullable()->after('name');
            }
            if (!Schema::hasColumn('payment_methods', 'code')) {
                $table->string('code', 50)->unique()->nullable()->after('name_en');
            }
            if (!Schema::hasColumn('payment_methods', 'icon')) {
                $table->string('icon', 100)->nullable()->after('description');
            }
            if (!Schema::hasColumn('payment_methods', 'logo')) {
                $table->string('logo')->nullable()->after('icon');
            }
            if (!Schema::hasColumn('payment_methods', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('logo');
            }
            if (!Schema::hasColumn('payment_methods', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_online');
            }
            if (!Schema::hasColumn('payment_methods', 'requires_card')) {
                $table->boolean('requires_card')->default(false)->after('is_default');
            }
            if (!Schema::hasColumn('payment_methods', 'requires_bank_details')) {
                $table->boolean('requires_bank_details')->default(false)->after('requires_card');
            }
            if (!Schema::hasColumn('payment_methods', 'min_amount')) {
                $table->decimal('min_amount', 10, 2)->nullable()->after('requires_bank_details');
            }
            if (!Schema::hasColumn('payment_methods', 'max_amount')) {
                $table->decimal('max_amount', 10, 2)->nullable()->after('min_amount');
            }
            if (!Schema::hasColumn('payment_methods', 'fee_type')) {
                $table->enum('fee_type', ['fixed', 'percentage'])->default('fixed')->after('max_amount');
            }
            if (!Schema::hasColumn('payment_methods', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->default(0)->after('fee_type');
            }
            if (!Schema::hasColumn('payment_methods', 'instructions')) {
                $table->text('instructions')->nullable()->after('fee_amount');
            }
            if (!Schema::hasColumn('payment_methods', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('instructions');
            }
            if (!Schema::hasColumn('payment_methods', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('payment_methods', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('payment_methods', 'bank_iban')) {
                $table->string('bank_iban')->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('payment_methods', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('bank_iban');
            }
            if (!Schema::hasColumn('payment_methods', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $columns = [
                'name_en', 'code', 'icon', 'logo', 'is_active', 'is_default',
                'requires_card', 'requires_bank_details', 'min_amount', 'max_amount',
                'fee_type', 'fee_amount', 'instructions', 'bank_name',
                'bank_account_name', 'bank_account_number', 'bank_iban', 'sort_order', 'deleted_at'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payment_methods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
