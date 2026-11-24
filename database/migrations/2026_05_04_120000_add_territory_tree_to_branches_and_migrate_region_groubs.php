<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        if (!Schema::hasTable('region_groubs')) {
            $this->addBranchesParentForeignKey();

            return;
        }

        $regionGroups = DB::table('region_groubs')->orderBy('id')->get();
        if ($regionGroups->isEmpty()) {
            Schema::dropIfExists('region_groubs');
            $this->addBranchesParentForeignKey();

            return;
        }

        $map = [];

        foreach ($regionGroups as $rg) {
            $parentBranch = DB::table('branches')->where('id', $rg->branch_id)->first();
            if (!$parentBranch) {
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
        }

        foreach ($map as $oldId => $newId) {
            DB::table('neighborhoods')->where('region_id', $oldId)->update(['region_id' => $newId]);

            if (Schema::hasColumn('clients', 'group_id')) {
                DB::table('clients')->where('group_id', $oldId)->update(['group_id' => $newId]);
            }

            DB::table('employee_group')->where('group_id', $oldId)->update(['group_id' => $newId]);

            if (Schema::hasTable('group_merge_logs')) {
                DB::table('group_merge_logs')->where('source_group_id', $oldId)->update(['source_group_id' => $newId]);
                DB::table('group_merge_logs')->where('target_group_id', $oldId)->update(['target_group_id' => $newId]);
            }
        }

        // نقل العملاء فعلياً إلى الفرع الابن الجديد: تحديث clients.branch_id لكل عميل مربوط بأي مجموعة مُرحَّلة
        // (عبر حيّه أو عبر group_id) حتى يصبح branch_id = الابن (الـleaf) لا الأب القديم.
        foreach ($map as $oldId => $newId) {
            $clientIdsFromNeighborhood = DB::table('neighborhoods')
                ->where('region_id', $newId)
                ->pluck('client_id');

            $clientIdsFromGroup = collect();
            if (Schema::hasColumn('clients', 'group_id')) {
                $clientIdsFromGroup = DB::table('clients')
                    ->where('group_id', $newId)
                    ->pluck('id');
            }

            $clientIds = $clientIdsFromNeighborhood
                ->merge($clientIdsFromGroup)
                ->filter()
                ->unique()
                ->values();

            if ($clientIds->isNotEmpty()) {
                DB::table('clients')
                    ->whereIn('id', $clientIds)
                    ->update(['branch_id' => $newId]);
            }
        }

        Schema::dropIfExists('region_groubs');

        $this->addBranchesParentForeignKey();
    }

    private function addBranchesParentForeignKey(): void
    {
        if (!Schema::hasColumn('branches', 'parent_id')) {
            return;
        }

        // القيدُ موجودٌ سلفاً في القواعد التي سبقت هذه الهجرة، وإضافتُه
        // ثانيةً ترمي 1005/121. الفحصُ قبل التسجيل لا بعده: `foreign()`
        // تُسجّل الأمرَ في الـBlueprint فحسب، والتنفيذُ يقع بعد خروج
        // المغلّف — فأيُّ try/catch حوله لا يلتقط شيئاً.
        if (SchemaGuard::hasForeignKey('branches', 'branches_parent_id_foreign')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('branches', 'branches_parent_id_foreign')) {
                $table->foreign('parent_id')->references('id')->on('branches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('region_groubs')) {
            return;
        }

        Schema::create('region_groubs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->bigInteger('branch_id')->nullable();
            $table->bigInteger('directions_id')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->timestamps();
        });

        if (Schema::hasColumn('branches', 'parent_id')) {
            try {
                Schema::table('branches', function (Blueprint $table) {
                    $table->dropForeign(['parent_id']);
                });
            } catch (\Throwable $e) {
                //
            }
        }

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'directions_id')) {
                $table->dropColumn('directions_id');
            }
            if (Schema::hasColumn('branches', 'is_territory')) {
                $table->dropColumn('is_territory');
            }
            if (Schema::hasColumn('branches', 'territory_office_id')) {
                $table->dropColumn('territory_office_id');
            }
            if (Schema::hasColumn('branches', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};
