<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // فقط إذا كانت الجدول موجودة
        if (Schema::hasTable('products')) {
            // تحديث القيم الحالية أولاً
            DB::table('products')->where('status', '0')->update(['status' => 'active']);
            DB::table('products')->where('status', '1')->update(['status' => 'unactive']);
            DB::table('products')->whereIn('status', ['stopped', '2', 'inactive'])->update(['status' => 'unactive']);
            DB::table('products')->whereNull('status')->update(['status' => 'active']);

            // تعديل نوع العمود إلى enum
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['active', 'unactive'])->default('active')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->nullable()->change();
        });
    }
};
