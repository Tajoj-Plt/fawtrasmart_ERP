<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Neighborhood;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * ترحيل بيانات جدول region_groubs إلى branches (عقد is_territory) ثم تحديث المراجع
 * حتى لا يبقى الاعتماد على جدول region_groubs.
 */
class RegionGroubsToBranchesMigrationService
{
    /**
     * @return array{
     *   success: bool,
     *   message: string,
     *   region_groubs_count: int,
     *   inserted: int,
     *   skipped_no_parent: int,
     *   reused_existing: int,
     *   fk_updates: array<string, int>,
     *   dropped_table: bool,
     *   clients_synced: int,
     *   dry_run_clients_linked: int
     * }
     */
    public function migrate(bool $dryRun = true, bool $dropTableWhenDone = true): array
    {
        $fkUpdates = [
            'neighborhoods' => 0,
            'clients_group' => 0,
            'employee_group' => 0,
            'group_merge_logs_source' => 0,
            'group_merge_logs_target' => 0,
        ];

        if (!Schema::hasTable('region_groubs')) {
            return [
                'success' => true,
                'message' => 'جدول region_groubs غير موجود — البيانات إما مُرحَّلة مسبقاً أو لم تُستخدم من الأساس.',
                'region_groubs_count' => 0,
                'inserted' => 0,
                'skipped_no_parent' => 0,
                'reused_existing' => 0,
                'fk_updates' => $fkUpdates,
                'dropped_table' => false,
                'clients_synced' => 0,
                'dry_run_clients_linked' => 0,
            ];
        }

        $this->ensureBranchTreeColumns();

        $regionGroups = DB::table('region_groubs')->orderBy('id')->get();
        $count = $regionGroups->count();

        if ($count === 0) {
            $dropped = false;
            if (!$dryRun && $dropTableWhenDone) {
                Schema::dropIfExists('region_groubs');
                $dropped = true;
                $this->tryAddParentForeignKey();
            }

            return [
                'success' => true,
                'message' => $dropped
                    ? 'جدول region_groubs كان فارغاً وتم حذفه.'
                    : 'جدول region_groubs فارغ — لا صفوف لترحيلها.',
                'region_groubs_count' => 0,
                'inserted' => 0,
                'skipped_no_parent' => 0,
                'reused_existing' => 0,
                'fk_updates' => $fkUpdates,
                'dropped_table' => $dropped,
                'clients_synced' => 0,
                'dry_run_clients_linked' => 0,
            ];
        }

        $map = [];
        $inserted = 0;
        $reused = 0;
        $skipped = 0;

        // ⚠️ منع التصادم: رفع AUTO_INCREMENT لـ branches فوق MAX(region_groubs.id)
        // قبل أي إدراج جديد، حتى لا تتطابق branch.id الجديدة مع region_groubs.id قديم.
        // التصادم يجعل neighborhoods.region_id ملتبس (يشير لـ rg القديم أو لـ branch الجديد).
        if (!$dryRun) {
            $this->bumpBranchesAutoIncrementAboveRegionGroubs();
        }

        foreach ($regionGroups as $rg) {
            $parentBranch = DB::table('branches')->where('id', $rg->branch_id)->first();
            if (!$parentBranch) {
                $skipped++;

                continue;
            }

            $prefix = 'TR-' . (int) $rg->id . '-';
            $existing = DB::table('branches')
                ->where('is_territory', true)
                ->where('code', 'like', $prefix . '%')
                ->first();

            if ($existing) {
                $map[(int) $rg->id] = (int) $existing->id;
                $reused++;

                continue;
            }

            if ($dryRun) {
                $inserted++;

                continue;
            }

            $creditLimitRg = property_exists($rg, 'credit_limit') ? $rg->credit_limit : null;
            $creditLimitParent = property_exists($parentBranch, 'credit_limit') ? $parentBranch->credit_limit : 0;
            $creditLimit = $creditLimitRg !== null ? $creditLimitRg : $creditLimitParent;

            $newId = DB::table('branches')->insertGetId([
                'name' => $rg->name,
                'code' => 'TR-' . $rg->id . '-' . substr(md5((string) $rg->id . $rg->name), 0, 8),
                'phone' => $parentBranch->phone ?? '-',
                'mobile' => $parentBranch->mobile ?? '-',
                'address1' => $parentBranch->address1 ?? '-',
                'address2' => $parentBranch->address2 ?? null,
                'city' => $parentBranch->city ?? '-',
                'region' => $parentBranch->region ?? null,
                'country' => $parentBranch->country ?? '-',
                'status' => $parentBranch->status ?? 0,
                'is_main' => false,
                'work_hours' => null,
                'description' => null,
                'location' => null,
                'credit_limit' => $creditLimit ?? 0,
                'parent_id' => $rg->branch_id,
                'directions_id' => $rg->directions_id,
                'is_territory' => true,
                'territory_office_id' => $rg->branch_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $map[(int) $rg->id] = $newId;
            $inserted++;
        }

        if ($dryRun) {
            $oldRegionIds = $regionGroups->pluck('id')->map(fn ($x) => (int) $x)->all();
            $dryLinked = $this->countClientsLinkedToRegionIds($oldRegionIds);

            return [
                'success' => true,
                'message' => "وضع تجريبي: يوجد {$count} سجل في region_groubs — سيُنشأ {$inserted} فرع منطقة جديد (أو يُعاد استخدام موجود)، متخطى بلا فرع أب: {$skipped}، معاد استخدامه: {$reused}. عملاء مربوطون بالمجموعات (حي أو group_id): {$dryLinked}.",
                'region_groubs_count' => $count,
                'inserted' => $inserted,
                'skipped_no_parent' => $skipped,
                'reused_existing' => $reused,
                'fk_updates' => $fkUpdates,
                'dropped_table' => false,
                'clients_synced' => 0,
                'dry_run_clients_linked' => $dryLinked,
            ];
        }

        // ⚠️ لا نُحدّث المراجع per-row لأن branches.id الجديد قد يتطابق مع region_groubs.id قديم
        // لم يُرحَّل بعد (تصادم AUTO_INCREMENT)، فيُسرَّب عملاء/أحياء بين المجموعات.
        // الحل: تحديث ذرّي بـ CASE WHEN يقرأ القيم الأصلية قبل أي كتابة.
        $validMap = collect($map)->filter(fn ($v) => (int) $v > 0)->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]);

        if ($validMap->isNotEmpty()) {
            $fkUpdates['neighborhoods'] += $this->bulkRemap('neighborhoods', 'region_id', $validMap);

            if (Schema::hasColumn('clients', 'group_id')) {
                $fkUpdates['clients_group'] += $this->bulkRemap('clients', 'group_id', $validMap);
            }

            $fkUpdates['employee_group'] += $this->bulkRemap('employee_group', 'group_id', $validMap);

            if (Schema::hasTable('group_merge_logs')) {
                $fkUpdates['group_merge_logs_source'] += $this->bulkRemap('group_merge_logs', 'source_group_id', $validMap);
                $fkUpdates['group_merge_logs_target'] += $this->bulkRemap('group_merge_logs', 'target_group_id', $validMap);
            }
        }

        $touchedBranchIds = collect($map)->values()->unique()->filter(fn ($id) => (int) $id > 0)->values();
        $clientsSynced = $touchedBranchIds->isNotEmpty()
            ? $this->syncClientsForRegionBranchIds($touchedBranchIds, true)
            : 0;

        $dropped = false;
        if ($dropTableWhenDone) {
            Schema::dropIfExists('region_groubs');
            $dropped = true;
        }

        $this->tryAddParentForeignKey();

        return [
            'success' => true,
            'message' => 'تم ترحيل مجموعات المناطق إلى جدول الفروع وتحديث المراجع.'
                . ($dropped ? ' تم حذف جدول region_groubs.' : ' لم يُحذف جدول region_groubs (حسب الخيار).')
                . ($clientsSynced > 0 ? " تم ضبط فرع المحاسبة والحي لـ {$clientsSynced} عميلاً مربوطاً بالمجموعات." : ''),
            'region_groubs_count' => $count,
            'inserted' => $inserted,
            'skipped_no_parent' => $skipped,
            'reused_existing' => $reused,
            'fk_updates' => $fkUpdates,
            'dropped_table' => $dropped,
            'clients_synced' => $clientsSynced,
            'dry_run_clients_linked' => 0,
        ];
    }

    /**
     * استيراد صفوف محددة من جدول region_groubs إلى branches تحت فرع أب تختاره،
     * ثم تحديث المراجع وحذف الصف من region_groubs.
     *
     * @param  array<int>  $regionGroubIds
     * @return array{success: bool, messages: array<int, string>, errors: array<int, string>, clients_updated: int}
     */
    public function importSelectedRegionGroubsUnderParent(
        int $parentBranchId,
        array $regionGroubIds,
        bool $dryRun = false,
        bool $skipClientSync = false,
        bool $dropTableIfEmpty = true,
    ): array {
        $messages = [];
        $errors = [];
        $clientsUpdated = 0;

        if (!Schema::hasTable('region_groubs')) {
            return [
                'success' => false,
                'messages' => [],
                'errors' => ['جدول region_groubs غير موجود — لا يوجد ما يُستورد منه.'],
                'clients_updated' => 0,
            ];
        }

        $this->ensureBranchTreeColumns();

        $parent = Branch::withoutGlobalScopes()->find($parentBranchId);
        if (!$parent) {
            return [
                'success' => false,
                'messages' => [],
                'errors' => ['الفرع الأب غير موجود.'],
                'clients_updated' => 0,
            ];
        }

        $officeId = $this->resolveTerritoryOfficeIdForParentBranch($parent);
        $ids = collect($regionGroubIds)->map(fn ($v) => (int) $v)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'success' => false,
                'messages' => [],
                'errors' => ['اختر مجموعة واحدة على الأقل من جدول region_groubs.'],
                'clients_updated' => 0,
            ];
        }

        $createdOrTouchedIds = collect();
        // ⚠️ نجمع كل (oldId → newId) ثم نُطبّق الـ remap في النهاية مرة واحدة بـ CASE WHEN ذرّي.
        // هذا يمنع تصادم AUTO_INCREMENT بين branch.id الجديد و region_groub.id قديم لم يُرحَّل بعد.
        $remapMap = collect();

        // منع التصادم: رفع AUTO_INCREMENT قبل أي إدراج
        if (!$dryRun) {
            $this->bumpBranchesAutoIncrementAboveRegionGroubs();
        }

        foreach ($ids as $oldId) {
            $rg = DB::table('region_groubs')->where('id', $oldId)->first();
            if (!$rg) {
                $messages[] = "تخطي {$oldId}: غير موجود في region_groubs.";

                continue;
            }

            if ($dryRun) {
                $messages[] = 'تجربة: «' . $rg->name . '» (معرف قديم ' . $oldId . ') → ستُضاف تحت «' . $parent->name . '».';

                continue;
            }

            $prefix = 'TR-' . (int) $rg->id . '-';
            $existingByCode = DB::table('branches')
                ->where('is_territory', '=', 1)
                ->where('code', 'like', $prefix . '%')
                ->first();

            try {
                if ($existingByCode) {
                    $bid = (int) $existingByCode->id;
                    $node = Branch::withoutGlobalScopes()->findOrFail($bid);
                    if ((int) $node->parent_id !== (int) $parentBranchId || (int) $node->territory_office_id !== (int) $officeId) {
                        $node->parent_id = $parentBranchId;
                        $node->territory_office_id = $officeId;
                        $node->is_territory = true;
                        $node->saveQuietly();
                        $node->regenerateSubtreeCodes();
                    }
                    $remapMap->put((int) $oldId, $bid);
                    $createdOrTouchedIds->push($bid);
                    $messages[] = 'تم ربط «' . $rg->name . '» الموجود مسبقاً في الفروع (سيتم تحديث المراجع دفعة واحدة في النهاية).';
                } else {
                    $code = Branch::generateTreeCode($parentBranchId);

                    $creditLimitRg = property_exists($rg, 'credit_limit') ? $rg->credit_limit : null;
                    $creditLimitParent = $parent->credit_limit ?? 0;
                    $creditLimit = $creditLimitRg !== null ? $creditLimitRg : $creditLimitParent;

                    $newId = DB::table('branches')->insertGetId([
                        'name' => $rg->name,
                        'code' => $code,
                        'phone' => $parent->phone ?? '-',
                        'mobile' => $parent->mobile ?? '-',
                        'address1' => $parent->address1 ?? '-',
                        'address2' => $parent->address2 ?? null,
                        'city' => $parent->city ?? '-',
                        'region' => $parent->region ?? null,
                        'country' => $parent->country ?? '-',
                        'status' => $parent->status ?? 0,
                        'is_main' => false,
                        'work_hours' => null,
                        'description' => null,
                        'location' => null,
                        'credit_limit' => $creditLimit ?? 0,
                        'parent_id' => $parentBranchId,
                        'directions_id' => $rg->directions_id ?? null,
                        'is_territory' => true,
                        'territory_office_id' => $officeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $remapMap->put((int) $oldId, (int) $newId);
                    $createdOrTouchedIds->push((int) $newId);
                    $messages[] = 'تم استيراد «' . $rg->name . '» إلى الفروع تحت «' . $parent->name . '».';
                }
            } catch (ValidationException $e) {
                return [
                    'success' => false,
                    'messages' => $messages,
                    'errors' => [collect($e->errors())->flatten()->implode(' ')],
                    'clients_updated' => 0,
                ];
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'messages' => $messages,
                    'errors' => ['فشل معرف region_groubs ' . $oldId . ': ' . $e->getMessage()],
                    'clients_updated' => 0,
                ];
            }
        }

        // remap نهائي ذرّي بعد إنشاء كل الفروع — يمنع تصادم AUTO_INCREMENT بين branch.id الجديد و region_groub.id القديم
        if (!$dryRun && $remapMap->isNotEmpty()) {
            $this->bulkRemapReferences($remapMap);
            // الآن نحذف صفوف region_groubs المُرحَّلة
            DB::table('region_groubs')->whereIn('id', $remapMap->keys()->all())->delete();
            $messages[] = 'تم تحديث ' . $remapMap->count() . ' مرجع بشكل ذرّي وحذف الصفوف من region_groubs.';
        }

        if (!$dryRun && $dropTableIfEmpty && DB::table('region_groubs')->count() === 0) {
            Schema::dropIfExists('region_groubs');
            $messages[] = 'تم حذف جدول region_groubs لأنه أصبح فارغاً.';
            $this->tryAddParentForeignKey();
        }

        if (!$dryRun && !$skipClientSync && $createdOrTouchedIds->isNotEmpty()) {
            $clientsUpdated = $this->syncClientsForRegionBranchIds($createdOrTouchedIds->unique()->values());
            $messages[] = 'تم تحديث فرع المحاسبة لـ ' . $clientsUpdated . ' عميلاً ذي صلة.';
        }

        if ($dryRun) {
            $messages[] = 'وضع تجريبي — لم يُحفظ شيء.';
        } else {
            $messages[] = 'تم تنفيذ الاستيراد من region_groubs.';
        }

        return [
            'success' => true,
            'messages' => $messages,
            'errors' => [],
            'clients_updated' => $clientsUpdated,
        ];
    }

    private function resolveTerritoryOfficeIdForParentBranch(Branch $parent): int
    {
        if ($parent->is_territory) {
            return (int) ($parent->territory_office_id ?: $parent->rootBranch()->id);
        }

        return (int) $parent->id;
    }

    private function remapOldRegionGroubReferencesToBranch(int $oldId, int $newId): void
    {
        $this->bulkRemapReferences(collect([$oldId => $newId]));
    }

    /**
     * رفع AUTO_INCREMENT لـ branches فوق MAX(region_groubs.id) — يمنع التصادم
     * بين branch.id الجديد و region_groubs.id قديم لم يُرحَّل بعد.
     *
     * **لماذا؟** بدون هذا، إذا كان region_groubs فيه IDs 1-100 وbranches فيه IDs 1-9
     * (auto_increment=10)، فإدراج الفروع الجديدة يولّد IDs 10-58 — تتطابق رقمياً مع
     * region_groubs.id قديم. ثم neighborhoods.region_id=10 صار له معنيان: rg.id قديم
     * أو branch.id جديد. أي UPDATE أو whereIn يلتقط الاثنين فيتلوّث الترحيل.
     */
    private function bumpBranchesAutoIncrementAboveRegionGroubs(): void
    {
        if (!Schema::hasTable('region_groubs')) {
            return;
        }

        $maxRgId = (int) (DB::table('region_groubs')->max('id') ?? 0);
        $maxBranchId = (int) (DB::table('branches')->max('id') ?? 0);

        // الهدف: التالي لإدراجه في branches > أي rg.id موجود
        $target = max($maxRgId, $maxBranchId) + 1;

        try {
            DB::statement('ALTER TABLE `branches` AUTO_INCREMENT = ' . (int) $target);
        } catch (\Throwable $e) {
            // فشل ALTER (صلاحيات أو قفل) — نُسجّل ونكمل (الاحتمال الكبير لا يوجد تصادم لاحقاً)
        }
    }

    /**
     * تطبيق mapping كامل (oldId → newId) دفعة واحدة على كل جداول المراجع.
     * يُستخدم في الترحيل التراكمي لتجنب تصادم AUTO_INCREMENT بين الـ branch.id الجديد و region_groub.id القديم.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $map
     */
    private function bulkRemapReferences(\Illuminate\Support\Collection $map): void
    {
        $clean = $map->filter(fn ($v) => (int) $v > 0)
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]);
        if ($clean->isEmpty()) {
            return;
        }

        $this->bulkRemap('neighborhoods', 'region_id', $clean);
        if (Schema::hasColumn('clients', 'group_id')) {
            $this->bulkRemap('clients', 'group_id', $clean);
        }
        $this->bulkRemap('employee_group', 'group_id', $clean);
        if (Schema::hasTable('group_merge_logs')) {
            $this->bulkRemap('group_merge_logs', 'source_group_id', $clean);
            $this->bulkRemap('group_merge_logs', 'target_group_id', $clean);
        }
    }

    /**
     * UPDATE ذرّي بـ CASE WHEN: يقرأ القيمة الأصلية للعمود قبل أي كتابة، فلا يحدث تسلسل/تسريب
     * حتى لو تطابق newId مع oldId آخر في نفس الـ map.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $map
     */
    private function bulkRemap(string $table, string $column, \Illuminate\Support\Collection $map): int
    {
        if ($map->isEmpty() || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        $oldIds = $map->keys()->map(fn ($k) => (int) $k)->all();
        $cases = '';
        foreach ($map as $oldId => $newId) {
            $cases .= ' WHEN ' . (int) $oldId . ' THEN ' . (int) $newId;
        }
        $idsList = implode(',', array_map('intval', $oldIds));
        $col = '`' . str_replace('`', '``', $column) . '`';
        $tbl = '`' . str_replace('`', '``', $table) . '`';

        return DB::update("UPDATE {$tbl} SET {$col} = CASE {$col}{$cases} ELSE {$col} END WHERE {$col} IN ({$idsList})");
    }

    /**
     * مزامنة شاملة لكل العملاء (كأمر clients:sync-group-links-to-branch-tree).
     *
     * @return array{updated: int, skipped: int}
     */
    public function syncAllClientsBranchTreeFromAnchors(bool $dryRun = false, bool $keepGroupId = false): array
    {
        $updated = 0;
        $skipped = 0;

        Client::query()->with(['neighborhood'])->chunkById(250, function ($clients) use ($dryRun, $keepGroupId, &$updated, &$skipped) {
            foreach ($clients as $client) {
                if ($this->applyClientBranchTreeSyncFromAnchors($client, $dryRun, $keepGroupId)) {
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * عدد العملاء المرتبطين بمعرفات مجموعات/مناطق (حي أو group_id).
     *
     * @param  array<int>  $regionIds
     */
    private function countClientsLinkedToRegionIds(array $regionIds): int
    {
        if ($regionIds === []) {
            return 0;
        }

        $fromNeighborhood = DB::table('neighborhoods')->whereIn('region_id', $regionIds)->pluck('client_id');
        $fromGroup = collect();
        if (Schema::hasColumn('clients', 'group_id')) {
            $fromGroup = DB::table('clients')->whereIn('group_id', $regionIds)->pluck('id');
        }

        return $fromNeighborhood->merge($fromGroup)->unique()->filter()->count();
    }

    /**
     * @param  array<int>|null  $anchorMustBeInIds  عند الترحيل: نفضّل مرساة ضمن عُقد المناطق المُحدَّثة فقط (حي أو group_id).
     * @return bool true عند وجود مرساة صالحة ويُحسب التعديل (أو يُنفَّذ فعلياً)
     */
    private function applyClientBranchTreeSyncFromAnchors(Client $client, bool $dryRun, bool $keepGroupId, ?array $anchorMustBeInIds = null, bool $useLeafAsBranch = false): bool
    {
        $hasGroupId = Schema::hasColumn('clients', 'group_id');
        $nRegion = $client->neighborhood?->region_id ? (int) $client->neighborhood->region_id : null;
        $gId = ($hasGroupId && $client->getAttribute('group_id') !== null && $client->getAttribute('group_id') !== '')
            ? (int) $client->getAttribute('group_id')
            : null;

        $anchorId = null;
        if ($anchorMustBeInIds !== null) {
            $set = array_flip(array_map('intval', $anchorMustBeInIds));
            if ($nRegion !== null && isset($set[$nRegion])) {
                $anchorId = $nRegion;
            } elseif ($gId !== null && isset($set[$gId])) {
                $anchorId = $gId;
            }
        } else {
            $anchorId = $nRegion ?: $gId;
            if (!$anchorId && $client->branch_id) {
                $anchorId = (int) $client->branch_id;
            }
        }

        if ($anchorMustBeInIds !== null && !$anchorId) {
            return false;
        }

        if (!$anchorId) {
            return false;
        }

        $node = Branch::withoutGlobalScopes()->find((int) $anchorId);
        if (!$node) {
            return false;
        }

        $territoryId = $node->resolveTerritoryBranchForNeighborhood()->id;
        $accountingId = $useLeafAsBranch
            ? (int) $node->id
            : $node->resolveAccountingBranchIdForClient();

        if ($dryRun) {
            return true;
        }

        DB::transaction(function () use ($client, $territoryId, $accountingId, $hasGroupId, $keepGroupId) {
            $payload = ['branch_id' => $accountingId];
            if ($hasGroupId && !$keepGroupId) {
                $payload['group_id'] = null;
            }
            DB::table('clients')->where('id', $client->id)->update($payload);

            $n = Neighborhood::where('client_id', $client->id)->first();
            if ($n) {
                $n->region_id = $territoryId;
                $n->saveQuietly();
            } else {
                Neighborhood::create([
                    'client_id' => $client->id,
                    'name' => 'غير محدد',
                    'region_id' => $territoryId,
                ]);
            }
        });

        return true;
    }

    /**
     * تحديث فرع المحاسبة والحي لكل عميل مربوط بعُقد المناطق المُرحَّلة (حي أو group_id).
     *
     * @param  Collection<int, int>  $regionBranchIds
     */
    private function syncClientsForRegionBranchIds(Collection $regionBranchIds, bool $clearGroupId = true): int
    {
        if ($regionBranchIds->isEmpty()) {
            return 0;
        }

        $ids = $regionBranchIds->map(fn ($v) => (int) $v)->unique()->values()->all();
        $hasGroupId = Schema::hasColumn('clients', 'group_id');

        $fromNeighborhood = Neighborhood::query()
            ->whereIn('region_id', $ids)
            ->pluck('client_id');

        $fromGroup = collect();
        if ($hasGroupId) {
            $fromGroup = Client::query()->whereIn('group_id', $ids)->pluck('id');
        }

        $clientIds = $fromNeighborhood->merge($fromGroup)->unique()->filter()->values();

        $n = 0;
        foreach ($clientIds as $clientId) {
            $client = Client::with('neighborhood')->find($clientId);
            if (!$client) {
                continue;
            }
            if ($this->applyClientBranchTreeSyncFromAnchors($client, false, !$clearGroupId, $ids, true)) {
                $n++;
            }
        }

        return $n;
    }

    private function ensureBranchTreeColumns(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('branches', 'directions_id')) {
                $table->unsignedBigInteger('directions_id')->nullable()->after('parent_id')->index();
            }
            if (!Schema::hasColumn('branches', 'is_territory')) {
                $table->boolean('is_territory')->default(false)->after('directions_id');
            }
            if (!Schema::hasColumn('branches', 'territory_office_id')) {
                $table->unsignedBigInteger('territory_office_id')->nullable()->after('is_territory')->index();
            }
        });
    }

    private function tryAddParentForeignKey(): void
    {
        if (!Schema::hasColumn('branches', 'parent_id')) {
            return;
        }
        Schema::table('branches', function (Blueprint $table) {
            try {
                $table->foreign('parent_id')->references('id')->on('branches')->nullOnDelete();
            } catch (\Throwable $e) {
                //
            }
        });
    }
}
