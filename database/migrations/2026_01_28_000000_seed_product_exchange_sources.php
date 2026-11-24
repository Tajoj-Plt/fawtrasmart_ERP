<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sources = [
            [
                'id' => 27,
                'name' => 'تبديل تالف',
                'category' => 'تبديل بضاعة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 28,
                'name' => 'ارجاع تبديل تالف',
                'category' => 'تبديل بضاعة',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($sources as $source) {
            DB::table('permission_sources')->updateOrInsert(['id' => $source['id']], $source);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permission_sources')->whereIn('id', [27, 28])->delete();
    }
};
