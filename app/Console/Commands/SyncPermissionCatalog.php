<?php

namespace App\Console\Commands;

use App\Constants\permissions\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * مزامنة جدول `permissions` مع كتالوجات الأقسام.
 *
 * ثلاثة أعمال في اتجاهٍ واحد — الكتالوج مصدرٌ والجدول صورة:
 *
 * ١. **إنشاء الناقص**: كل صلاحيةٍ في كتالوج ولا صفَّ لها.
 * ٢. **كتابة التقسيم**: القسم والمجموعة والتسمية والترتيب في أعمدتها.
 * ٣. **التبليغ عن اليتيم**: صفٌّ في الجدول لا يعرفه أي كتالوج — يُعرَض ولا
 *    يُحذف. الحذف يسحب الصلاحية من كل دورٍ يحملها، وهذا قرارُ إنسانٍ لا
 *    أمرِ مزامنة. `--prune` لمن أراده صراحةً.
 *
 * لا يمنح هذا الأمر صلاحيةً لدور ولا ينزعها — المنح في السيدرات وشاشة
 * الأدوار.
 */
class SyncPermissionCatalog extends Command
{
    protected $signature = 'permissions:sync-catalog
                            {--prune : حذف الصلاحيات التي لا يعرفها أي كتالوج}
                            {--dry-run : عرض ما سيجري دون تنفيذه}';

    protected $description = 'مزامنة جدول الصلاحيات مع كتالوجات الأقسام: إنشاء الناقص وكتابة التقسيم والتسميات';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicates = PermissionCatalog::duplicates();

        if ($duplicates !== []) {
            $this->error('صلاحيات معرَّفة في أكثر من قسم — صحّح الكتالوجات أولاً:');

            foreach ($duplicates as $name => $modules) {
                $this->line("  {$name} → ".implode(' ، ', $modules));
            }

            return self::FAILURE;
        }

        $index = PermissionCatalog::index();
        $existing = Permission::pluck('id', 'name')->all();

        $created = $this->createMissing($index, $existing, $dryRun);
        $updated = $this->writeClassification($index, $dryRun);
        $orphans = array_values(array_diff(array_keys($existing), array_keys($index)));

        $this->report($index, $created, $updated, $orphans, $dryRun);

        if ($orphans !== [] && $this->option('prune') && ! $dryRun) {
            Permission::whereIn('name', $orphans)->delete();
            $this->warn('حُذفت '.count($orphans).' صلاحية يتيمة.');
        }

        if (! $dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return self::SUCCESS;
    }

    /** إنشاء صفوف الصلاحيات التي يعرّفها الكتالوج ولا وجود لها في الجدول. */
    private function createMissing(array $index, array $existing, bool $dryRun): array
    {
        $missing = array_values(array_diff(array_keys($index), array_keys($existing)));

        if ($missing === [] || $dryRun) {
            return $missing;
        }

        $now = now();
        $rows = [];

        foreach ($missing as $name) {
            $rows[] = array_merge($index[$name], [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('permissions')->insert($chunk);
        }

        return $missing;
    }

    /**
     * كتابة أعمدة التقسيم.
     *
     * التحديث مجموعةً مجموعة لا صفاً صفاً: ثماني مئة استعلامٍ مفرد على
     * اتصالٍ بعيد يجعل الأمر يمشي دقائق، وكل صلاحيات المجموعة الواحدة
     * تتشارك القسم والمجموعة والتسميتين — لا يختلف إلا `label` و`sort_order`.
     */
    private function writeClassification(array $index, bool $dryRun): int
    {
        if ($dryRun) {
            return count($index);
        }

        $byGroup = [];

        foreach ($index as $name => $place) {
            $byGroup[$place['module'].'|'.$place['group_key']][$name] = $place;
        }

        $updated = 0;

        foreach ($byGroup as $members) {
            $first = reset($members);
            $names = array_keys($members);

            // أعمدةُ المجموعة واحدةٌ لأعضائها كلهم
            $updated += DB::table('permissions')
                ->whereIn('name', $names)
                ->update([
                    'module' => $first['module'],
                    'module_label' => $first['module_label'],
                    'group_key' => $first['group_key'],
                    'group_label' => $first['group_label'],
                    'updated_at' => now(),
                ]);

            // التسمية والترتيب يختلفان داخل المجموعة — CASE واحدة لكلٍّ
            $labelCase = 'CASE `name`';
            $sortCase = 'CASE `name`';
            $labelBindings = [];
            $sortBindings = [];

            foreach ($members as $name => $place) {
                $labelCase .= ' WHEN ? THEN ?';
                $sortCase .= ' WHEN ? THEN ?';
                $labelBindings[] = $name;
                $labelBindings[] = $place['label'];
                $sortBindings[] = $name;
                $sortBindings[] = $place['sort_order'];
            }

            $labelCase .= ' ELSE `label` END';
            $sortCase .= ' ELSE `sort_order` END';

            DB::update(
                'UPDATE `permissions` SET `label` = '.$labelCase.', `sort_order` = '.$sortCase
                    .' WHERE `name` IN ('.implode(',', array_fill(0, count($names), '?')).')',
                array_merge($labelBindings, $sortBindings, $names)
            );
        }

        return $updated;
    }

    private function report(array $index, array $created, int $updated, array $orphans, bool $dryRun): void
    {
        $prefix = $dryRun ? '[تجريبي] ' : '';

        $this->info($prefix.'مزامنة كتالوج الصلاحيات');
        $this->newLine();

        $rows = [];

        foreach (PermissionCatalog::modules() as $module) {
            $count = 0;

            foreach ($module['groups'] as $group) {
                $count += count($group['permissions']);
            }

            $rows[] = [$module['key'], $module['label'], count($module['groups']), $count];
        }

        $this->table(['القسم', 'التسمية', 'المجموعات', 'الصلاحيات'], $rows);

        $this->line('  الإجمالي في الكتالوج: '.count($index));
        $this->line('  أُنشئت الآن: '.count($created));

        foreach ($created as $name) {
            $this->line('    + '.$name);
        }

        $this->line('  حُدِّث تصنيفها: '.$updated);

        if ($orphans === []) {
            $this->info('  لا صلاحيات يتيمة — الجدول والكتالوج متطابقان.');

            return;
        }

        $this->warn('  صلاحيات في الجدول بلا كتالوج: '.count($orphans));

        foreach ($orphans as $name) {
            $this->line('    ? '.$name);
        }

        $this->line('  أضفها إلى كتالوج قسمها، أو احذفها بـ--prune إن كانت ميتة.');
    }
}
