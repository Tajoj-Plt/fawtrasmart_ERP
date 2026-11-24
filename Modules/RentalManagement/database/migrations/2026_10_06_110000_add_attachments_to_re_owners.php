<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** مرفقات المالك: صورة الهوية/السجل، اتفاقية الإدارة، الوكالة… */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('re_owners', 'attachments')) {
            Schema::table('re_owners', fn (Blueprint $table) => $table->text('attachments')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('re_owners', 'attachments')) {
            Schema::table('re_owners', fn (Blueprint $table) => $table->dropColumn('attachments'));
        }
    }
};
