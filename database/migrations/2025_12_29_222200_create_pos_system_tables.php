<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. pos_sessions
        if (!Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_number')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users'); // Assuming 'users' table exists
                $table->foreignId('device_id')->nullable(); // Assuming cashier_devices might not be in standard constrained format or we create index manually
                $table->foreignId('shift_id')->nullable();
                $table->datetime('started_at');
                $table->datetime('ended_at')->nullable();
                $table->decimal('opening_balance', 15, 2);
                $table->decimal('closing_balance', 15, 2)->nullable();
                $table->decimal('actual_closing_balance', 15, 2)->nullable();
                $table->decimal('difference', 15, 2)->nullable();
                $table->integer('total_transactions')->default(0);
                $table->decimal('total_sales', 15, 2)->default(0);
                $table->decimal('total_cash', 15, 2)->default(0);
                $table->decimal('total_card', 15, 2)->default(0);
                $table->decimal('total_returns', 15, 2)->default(0);
                $table->string('status')->default('active');
                $table->text('closing_notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 2. pos_session_details
        if (!Schema::hasTable('pos_session_details')) {
            Schema::create('pos_session_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('pos_sessions')->cascadeOnDelete();
                $table->string('transaction_type');
                $table->string('reference_number')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method')->nullable();
                $table->decimal('cash_amount', 15, 2)->default(0);
                $table->decimal('card_amount', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->datetime('transaction_time');
                $table->timestamps();
            });
        }

        // 3. pos_transactions
        if (!Schema::hasTable('pos_transactions')) {
            Schema::create('pos_transactions', function (Blueprint $table) {
                // Primary key defined as 'transaction_id' in model
                $table->integer('transaction_id')->autoIncrement();
                $table->datetime('transaction_date')->nullable();
                $table->integer('cashier_id')->nullable();
                $table->float('total_amount');
                $table->string('payment_method')->nullable();
                $table->string('status')->nullable();
                $table->integer('employee_id')->nullable();
                // No timestamps based on model
            });
        }

        // 4. pos_transaction_items
        if (!Schema::hasTable('pos_transaction_items')) {
            Schema::create('pos_transaction_items', function (Blueprint $table) {
                // Primary key defined as 'item_id' in model
                $table->integer('item_id')->autoIncrement();
                $table->integer('transaction_id')->index();
                $table->integer('product_id')->index();
                $table->integer('quantity');
                $table->float('unit_price')->nullable();
                $table->float('total_price')->nullable();
                // No timestamps based on model
            });
        }

        // 5. pos_general_settings
        if (!Schema::hasTable('pos_general_settings')) {
            Schema::create('pos_general_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('default_customer_id')->nullable(); // constrained to clients potentially
                $table->string('invoice_template')->nullable();
                $table->foreignId('active_payment_method_id')->nullable(); // constrained to payment_methods
                $table->foreignId('default_payment_method_id')->nullable(); // constrained to payment_methods
                $table->string('allowed_categories_type')->nullable();
                $table->json('allowed_categories_ids')->nullable();
                $table->boolean('enable_departments')->default(false);
                $table->boolean('apply_custom_fields_validation')->default(false);
                $table->boolean('show_product_images')->default(true);
                $table->boolean('show_print_window_after_confirm')->default(true);
                $table->boolean('accounting_system_per_invoice')->default(false);
                $table->boolean('enable_auto_settlement')->default(false);
                $table->boolean('enable_sales_settlement')->default(false);
                $table->foreignId('profit_account_id')->nullable(); // constrained to accounts
                $table->foreignId('loss_account_id')->nullable(); // constrained to accounts
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_general_settings');
        Schema::dropIfExists('pos_transaction_items');
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('pos_session_details');
        Schema::dropIfExists('pos_sessions');
    }
};
