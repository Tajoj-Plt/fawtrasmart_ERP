<?php

namespace Modules\Branches\Console;

use App\Models\BranchSetting;
use Illuminate\Console\Command;
use Modules\Branches\Services\BranchSettingsCatalog;
use Modules\Branches\Services\BranchSettingsService;

/**
 * مزامنة كتالوج إعدادات الفروع مع قاعدة البيانات.
 *
 * تُشغَّل بعد كل إضافة إعداد في `BranchSettingsCatalog::definitions()`.
 * آمنة للتكرار: لا تلمس قيم الفروع المحفوظة، تحدّث التعريفات فقط.
 */
class SyncBranchSettings extends Command
{
    protected $signature = 'branches:sync-settings';

    protected $description = 'Sync branch setting definitions from the catalogue into the database';

    public function handle(): int
    {
        $result = BranchSettingsCatalog::sync();

        BranchSettingsService::forget();

        $this->info("Synced — {$result['created']} setting(s) added, {$result['updated']} updated.");

        // المفاتيح الموجودة في قاعدة البيانات ولا تعريف لها في الكتالوج:
        // تبقى تعمل، لكن من الأفضل أن يعرف المطوّر بوجودها.
        $known = collect(BranchSettingsCatalog::definitions())->pluck('key');
        $orphans = BranchSetting::whereNotIn('key', $known)->pluck('key');

        if ($orphans->isNotEmpty()) {
            $this->warn('Keys not defined in the catalogue (left as they are): ' . $orphans->implode('، '));
        }

        return self::SUCCESS;
    }
}
