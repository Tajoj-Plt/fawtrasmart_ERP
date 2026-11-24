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
        Schema::table('system_currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('system_currencies', 'arabic_name')) {
                $table->string('arabic_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('system_currencies', 'english_name')) {
                $table->string('english_name')->nullable()->after('arabic_name');
            }
            if (! Schema::hasColumn('system_currencies', 'numeric_code')) {
                $table->string('numeric_code')->nullable()->after('code');
            }
            if (! Schema::hasColumn('system_currencies', 'native_symbol')) {
                $table->string('native_symbol')->nullable()->after('symbol');
            }
            if (! Schema::hasColumn('system_currencies', 'rate_source')) {
                $table->enum('rate_source', ['manual', 'api'])->default('manual')->after('exchange_rate');
            }
            if (! Schema::hasColumn('system_currencies', 'country')) {
                $table->string('country')->nullable()->after('rate_source');
            }
            if (! Schema::hasColumn('system_currencies', 'flag')) {
                $table->string('flag')->nullable()->after('country');
            }
            if (! Schema::hasColumn('system_currencies', 'decimal_places')) {
                $table->integer('decimal_places')->default(2);
            }
            if (! Schema::hasColumn('system_currencies', 'rounding_method')) {
                $table->enum('rounding_method', ['round', 'ceil', 'floor'])->default('round');
            }
            if (! Schema::hasColumn('system_currencies', 'thousands_separator')) {
                $table->string('thousands_separator', 5)->default(',');
            }
            if (! Schema::hasColumn('system_currencies', 'decimal_separator')) {
                $table->string('decimal_separator', 5)->default('.');
            }
            if (! Schema::hasColumn('system_currencies', 'negative_format')) {
                $table->string('negative_format')->default('standard'); // standard, parentheses
                if (! Schema::hasColumn('system_currencies', 'positive_format')) {
                    $table->string('positive_format')->default('standard');
                }
            }
            if (! Schema::hasColumn('system_currencies', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
            if (! Schema::hasColumn('system_currencies', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_currencies', function (Blueprint $table) {
            $table->dropColumn([
                'arabic_name', 'english_name', 'numeric_code', 'native_symbol',
                'rate_source', 'country', 'flag', 'decimal_places', 'rounding_method',
                'thousands_separator', 'decimal_separator', 'negative_format',
                'positive_format', 'sort_order', 'description'
            ]);
        });
    }
};
