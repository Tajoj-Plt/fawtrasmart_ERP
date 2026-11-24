<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        if (! Schema::hasColumn('projects', 'management_type')) {
            Schema::table('projects', function (Blueprint $table) {
                if (! Schema::hasColumn('projects', 'management_type')) {
                    $table->enum('management_type', ['manual', 'scrum'])
                        ->default('scrum')
                        ->after('priority')
                        ->comment('نوع إدارة المشروع: سكرم كامل أو مهام بسيطة');
                }
            });
        }

        DB::table('projects')->whereNull('management_type')->update(['management_type' => 'scrum']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'management_type')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('management_type');
        });
    }
};
