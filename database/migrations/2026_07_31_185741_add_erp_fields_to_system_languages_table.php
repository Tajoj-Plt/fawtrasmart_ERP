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
        Schema::table('system_languages', function (Blueprint $table) {
            if (! Schema::hasColumn('system_languages', 'native_name')) {
                $table->string('native_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('system_languages', 'locale')) {
                $table->string('locale')->nullable()->after('code');
            }
            if (! Schema::hasColumn('system_languages', 'direction')) {
                $table->enum('direction', ['ltr', 'rtl'])->default('ltr')->after('flag');
            }
            if (! Schema::hasColumn('system_languages', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
            if (! Schema::hasColumn('system_languages', 'date_format')) {
                $table->string('date_format')->default('Y-m-d');
            }
            if (! Schema::hasColumn('system_languages', 'time_format')) {
                $table->string('time_format')->default('H:i:s');
            }
            if (! Schema::hasColumn('system_languages', 'number_format')) {
                $table->string('number_format')->default('1,234.56');
            }
            if (! Schema::hasColumn('system_languages', 'timezone')) {
                $table->string('timezone')->default('UTC');
            }
            if (! Schema::hasColumn('system_languages', 'calendar_type')) {
                $table->string('calendar_type')->default('gregorian'); // gregorian, hijri, etc
                if (! Schema::hasColumn('system_languages', 'thousands_separator')) {
                    $table->string('thousands_separator', 5)->default(',');
                }
            }
            if (! Schema::hasColumn('system_languages', 'decimal_separator')) {
                $table->string('decimal_separator', 5)->default('.');
            }
            if (! Schema::hasColumn('system_languages', 'font_family')) {
                $table->string('font_family')->nullable();
            }
            if (! Schema::hasColumn('system_languages', 'font_size')) {
                $table->string('font_size')->nullable();
            }
            if (! Schema::hasColumn('system_languages', 'color')) {
                $table->string('color', 20)->nullable();
            }
            if (! Schema::hasColumn('system_languages', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_languages', function (Blueprint $table) {
            $table->dropColumn([
                'native_name', 'locale', 'direction', 'sort_order', 'date_format',
                'time_format', 'number_format', 'timezone', 'calendar_type',
                'thousands_separator', 'decimal_separator', 'font_family', 'font_size',
                'color', 'description'
            ]);
        });
    }
};
