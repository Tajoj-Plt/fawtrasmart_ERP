<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'node_type')) {
                $table->enum('node_type', ['branch', 'region', 'group', 'outlet'])
                    ->default('branch')
                    ->after('is_territory')
                    ->index();
            }
        });

        // تعبئة السجلات الموجودة: territory = مجموعة، غيرها = فرع
        DB::table('branches')->where('is_territory', true)->update(['node_type' => 'group']);
        DB::table('branches')->where('is_territory', false)->update(['node_type' => 'branch']);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'node_type')) {
                $table->dropColumn('node_type');
            }
        });
    }
};
