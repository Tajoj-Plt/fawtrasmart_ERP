<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ملء «workspace_user» بصفوف مالكين لكل مساحة عمل موجودة مسبقاً، حتى لا
 * تختفي مساحات المستخدمين الحاليين من قوائمهم بعد تحويل النطاق من
 * «admin_id = المستخدم الحالي» إلى «عضوية حقيقية عبر workspace_user».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('workspace_user')) {
            return;
        }

        $now = now();

        DB::table('workspaces')
            ->whereNotNull('admin_id')
            ->orderBy('id')
            ->chunkById(200, function ($workspaces) use ($now) {
                foreach ($workspaces as $workspace) {
                    $exists = DB::table('workspace_user')
                        ->where('workspace_id', $workspace->id)
                        ->where('user_id', $workspace->admin_id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('workspace_user')->insertOrIgnore([
                        'workspace_id' => $workspace->id,
                        'user_id' => $workspace->admin_id,
                        'role' => 'owner',
                        'joined_at' => $workspace->created_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspace_user')) {
            return;
        }

        DB::table('workspace_user')->where('role', 'owner')->delete();
    }
};
