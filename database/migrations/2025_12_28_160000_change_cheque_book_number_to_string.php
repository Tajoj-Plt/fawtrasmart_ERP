<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تغيير نوع حقل رقم دفتر الشيكات من integer إلى string
     */
    public function up(): void
    {
        Schema::table('cheque_books', function (Blueprint $table) {
            $table->string('cheque_book_number', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cheque_books', function (Blueprint $table) {
            $table->integer('cheque_book_number')->change();
        });
    }
};
