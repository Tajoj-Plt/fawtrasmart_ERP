<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
    حساب «الأرباح والخسائر المرحّلة» في الحسابات الافتراضية.

    إليه يُرحِّل الإقفالُ السنوي صافي ربح السنة أو خسارتها
    (`YearEndClosingService`). يُربط بالحساب القائم إن وُجد — كودُه 32 في
    الشجرة الافتراضية («الأرباح و خسائر مرحلة») أو اسمٌ يدلّ عليه — وإلا بقي
    فارغاً يُضبط من الشاشة، ويمنع الإقفالَ حتى يُضبط.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings') || DB::table('accounting_settings')->where('key', 'retained_earnings')->exists()) {
            return;
        }

        $account = DB::table('accounts')->where('code', '32')->first(['id', 'code'])
            ?? DB::table('accounts')
                ->where(fn ($q) => $q->where('name', 'like', '%مرحل%')->orWhere('name', 'like', '%محتجز%'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('accounts as child')->whereColumn('child.parent_id', 'accounts.id'))
                ->orderBy('id')
                ->first(['id', 'code']);

        DB::table('accounting_settings')->insert([
            'key' => 'retained_earnings',
            'name' => 'الأرباح والخسائر المرحّلة',
            'description' => 'يُرحَّل إليه صافي ربح السنة المالية أو خسارتها عند الإقفال السنوي',
            'group' => 'حسابات رئيسية',
            'account_id' => $account->id ?? null,
            'account_code' => $account->code ?? null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('accounting_settings.map');
    }

    public function down(): void
    {
        DB::table('accounting_settings')->where('key', 'retained_earnings')->delete();
        Cache::forget('accounting_settings.map');
    }
};
