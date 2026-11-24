<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Neighborhood;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TerritoryGroupsMoveService
{
    /**
     * @param  array<int>  $groupIds
     * @return array{success: bool, messages: array<int, string>, errors: array<int, string>, clients_updated: int, employees_updated: int, neighborhoods_updated: int}
     */
    public function run(int $parentId, array $groupIds, bool $dryRun = false, bool $skipClientSync = false): array
    {
        $messages = [];
        $errors = [];
        $clientsUpdated = 0;

        $groupIds = collect($groupIds)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        if ($groupIds->isEmpty()) {
            return $this->fail('اختر مجموعة واحدة على الأقل للنقل.');
        }

        $parent = Branch::withoutGlobalScopes()->find($parentId);
        if (!$parent) {
            return $this->fail("الفرع الأب غير موجود (المعرّف: {$parentId}).");
        }

        $officeId = $this->resolveTerritoryOfficeIdForParent($parent);
        $movedSubtreeBranchIds = collect();

        foreach ($groupIds as $gid) {
            if ($gid === $parentId) {
                $messages[] = "تخطي {$gid}: نفس معرف الأب.";

                continue;
            }

            $node = Branch::withoutGlobalScopes()->find($gid);
            if (!$node) {
                $messages[] = "تخطي {$gid}: السجل غير موجود.";

                continue;
            }

            if ($parent->isUnderBranch($gid)) {
                return $this->fail("لا يمكن وضع الفرع {$gid} تحت {$parentId}: الأب ضمن شجرة العقدة (تعارض).", $messages);
            }

            $subtree = $node->descendantBranchIds()->push($gid)->unique();
            $movedSubtreeBranchIds = $movedSubtreeBranchIds->merge($subtree);

            $messages[] = "نقل «{$node->name}» ({$gid}) تحت «{$parent->name}» ({$parentId}) — مكتب المنطقة: {$officeId}";

            if ($dryRun) {
                continue;
            }

            try {
                DB::transaction(function () use ($node, $parent, $officeId, $gid) {
                    $node->parent_id = $parent->id;
                    if (!$node->is_territory) {
                        $node->is_territory = true;
                    }
                    $node->saveQuietly();

                    Branch::withoutGlobalScopes()
                        ->whereIn('id', $node->descendantBranchIds()->push($gid)->unique())
                        ->where('is_territory', true)
                        ->update(['territory_office_id' => $officeId]);

                    $node->refresh();
                    $node->regenerateSubtreeCodes();
                });

                // كل مجموعة تالية في الحلقة يجب أن ترى الشجرة بعد هذا النقل، لا قبله.
                Branch::flushTreeCaches();
            } catch (ValidationException $e) {
                return $this->fail(collect($e->errors())->flatten()->implode(' '), $messages);
            } catch (\Throwable $e) {
                return $this->fail('فشل نقل المجموعة ' . $gid . ': ' . $e->getMessage(), $messages);
            }
        }

        $movedSubtreeBranchIds = $movedSubtreeBranchIds->unique()->values();

        if ($dryRun) {
            $impactedClients = $this->countClientsAnchoredToTree($movedSubtreeBranchIds);
            $messages[] = "وضع تجريبي: لم يُحفظ شيء في قاعدة البيانات. الأثر المتوقع: {$impactedClients} عميل ضمن الشجرة المنقولة.";

            return [
                'success' => true,
                'messages' => $messages,
                'errors' => [],
                'clients_updated' => 0,
                'employees_updated' => 0,
                'neighborhoods_updated' => 0,
            ];
        }

        if (!$skipClientSync && $movedSubtreeBranchIds->isNotEmpty()) {
            $clientsUpdated = $this->syncClientsForRegions($movedSubtreeBranchIds);
            $messages[] = "تم تحديث فرع المحاسبة لـ {$clientsUpdated} عميلاً ضمن الشجرة المنقولة.";
        }

        $messages[] = 'تم تنفيذ النقل بنجاح.';

        return [
            'success' => true,
            'messages' => $messages,
            'errors' => [],
            'clients_updated' => $clientsUpdated,
            'employees_updated' => 0,
            'neighborhoods_updated' => 0,
        ];
    }

    /**
     * @return array{success: bool, messages: array<int, string>, errors: array<int, string>, clients_updated: int, employees_updated: int, neighborhoods_updated: int}
     */
    private function fail(string $error, array $messages = []): array
    {
        return [
            'success' => false,
            'messages' => $messages,
            'errors' => [$error],
            'clients_updated' => 0,
            'employees_updated' => 0,
            'neighborhoods_updated' => 0,
        ];
    }

    /**
     * عدّ العملاء المرتبطين فعلياً بالشجرة عبر الحي (anchor-based) — لا يُحسب
     * من branch_id وحده حتى لا نخلط عملاء حيّهم خارج الشجرة المنقولة.
     *
     * @param  Collection<int, int>  $regionBranchIds
     */
    private function countClientsAnchoredToTree(Collection $regionBranchIds): int
    {
        if ($regionBranchIds->isEmpty()) {
            return 0;
        }

        return Neighborhood::query()
            ->whereIn('region_id', $regionBranchIds->all())
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');
    }

    private function resolveTerritoryOfficeIdForParent(Branch $parent): int
    {
        if ($parent->is_territory) {
            return (int) ($parent->territory_office_id ?: $parent->rootBranch()->id);
        }

        return (int) $parent->id;
    }

    /**
     * مزامنة محافِظة: لا نلمس إلا العملاء الذين حيّهم region_id ضمن الشجرة المنقولة.
     * كل عميل: branch_id ← region_id لحيّه (الفرع الابن المباشر داخل الشجرة).
     * لا نلمس neighborhood.region_id ولا نُحرّك عملاء حيّهم خارج الشجرة المنقولة.
     *
     * @param  Collection<int, int>  $regionBranchIds
     */
    private function syncClientsForRegions(Collection $regionBranchIds): int
    {
        $hasGroupId = Schema::hasColumn('clients', 'group_id');
        $clientIds = Neighborhood::query()
            ->whereIn('region_id', $regionBranchIds->all())
            ->pluck('client_id')
            ->unique()
            ->filter();

        $n = 0;
        foreach ($clientIds as $clientId) {
            $client = Client::with('neighborhood')->find($clientId);
            if (!$client || !$client->neighborhood?->region_id) {
                continue;
            }

            $regionId = (int) $client->neighborhood->region_id;
            // أمان مزدوج: لا نُحدّث عميلاً انتقل حيّه خارج الشجرة بين الجلب والتعديل.
            if (!$regionBranchIds->contains($regionId)) {
                continue;
            }

            $payload = ['branch_id' => $regionId];
            if ($hasGroupId) {
                $payload['group_id'] = null;
            }
            DB::table('clients')->where('id', $client->id)->update($payload);
            $n++;
        }

        return $n;
    }
}
