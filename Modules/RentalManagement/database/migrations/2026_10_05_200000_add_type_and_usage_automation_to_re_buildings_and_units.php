<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Models\Building;

/**
 * أتمتة «نوع العقار والاستخدام» (docs/RealEstate_PropertyType_Usage.md، البندان 7 و8).
 *
 * الأعمدة: استخدام الوحدة ومواصفاتها التجارية وعلامة الوحدة التلقائية، وحقول
 * الأرض والصك والرخص على المبنى، و`ownership` إن لم يسبق إليه ترحيل المحاسبة.
 *
 * تعبئة القائم:
 *   1. «مجمع» و«مشروع» ← «عمارة».
 *   2. استخدام الوحدة من نوعها، وإلا من مبناها، والمختلط المجهول ← سكني (للمراجعة).
 *   3. الفلل والأراضي والمنشآت المستقلة: وحدتها الوحيدة تُعلَّم تلقائية، ومن لا وحدة له تُنشأ له.
 *   4. الأرض بلا طوابق، و«عدد الطوابق» يطابق صفوف `re_floors` في كل مبنى.
 *
 * كل ما يحتاج مراجعة يُكتب في السجل (`re-type-usage`) ويُطبع عند التشغيل.
 * قابلٌ للتكرار: كل خطوة تفحص قبل أن تكتب.
 */
return new class extends Migration
{
    private const COMMERCIAL = ['shop', 'office', 'showroom', 'warehouse', 'workshop'];

    private const RESIDENTIAL = ['apartment', 'villa', 'studio', 'floor', 'annex', 'room'];

    private const SINGLE = ['villa', 'land', 'standalone'];

    public function up(): void
    {
        if (! Schema::hasTable('re_units') || ! Schema::hasTable('re_buildings')) {
            return;
        }

        $this->addColumns();

        $review = [];

        // 1) مجمع/مشروع ← عمارة
        foreach (DB::table('re_buildings')->whereIn('entity_type', ['complex', 'project'])->get(['id', 'code', 'name_ar', 'entity_type']) as $row) {
            $review[] = sprintf('مبنى #%d (%s «%s»): النوع «%s» حُوِّل إلى «عمارة»', $row->id, $row->code, $row->name_ar, $row->entity_type);
        }

        DB::table('re_buildings')->whereIn('entity_type', ['complex', 'project'])->update(['entity_type' => 'building']);

        // ملكية المبنى: بلا مالك = مملوك للمنشأة
        DB::table('re_buildings')->whereNull('owner_id')->where('ownership', 'managed')->update(['ownership' => 'own']);

        // 2) استخدام الوحدة
        DB::table('re_units')->whereNull('usage_type')->whereIn('unit_type', self::COMMERCIAL)->update(['usage_type' => 'commercial']);
        DB::table('re_units')->whereNull('usage_type')->whereIn('unit_type', self::RESIDENTIAL)->update(['usage_type' => 'residential']);

        $unknown = DB::table('re_units as u')->join('re_buildings as b', 'b.id', '=', 'u.building_id')
            ->whereNull('u.usage_type')->get(['u.id', 'u.unit_no', 'u.unit_type', 'b.id as building_id', 'b.usage_type as building_usage']);

        foreach ($unknown as $row) {
            $usage = in_array($row->building_usage, ['residential', 'commercial'], true) ? $row->building_usage : 'residential';
            DB::table('re_units')->where('id', $row->id)->update(['usage_type' => $usage]);

            if ($row->building_usage === 'mixed') {
                $review[] = sprintf('وحدة #%d (رقم %s، نوع %s) في مبنى مختلط #%d: استخدامها غير معروف فجُعل «سكني»', $row->id, $row->unit_no, $row->unit_type, $row->building_id);
            }
        }

        // وحداتٌ يخالف استخدامُها مبنىً غير مختلط (محل في مبنى سكني…): تبقى كما هي وتُعرض للمراجعة
        $mismatch = DB::table('re_units as u')->join('re_buildings as b', 'b.id', '=', 'u.building_id')
            ->whereNull('u.deleted_at')->whereNull('b.deleted_at')
            ->whereIn('b.usage_type', ['residential', 'commercial'])->whereColumn('u.usage_type', '!=', 'b.usage_type')
            ->get(['u.id', 'u.unit_no', 'u.unit_type', 'u.usage_type', 'b.id as building_id', 'b.usage_type as building_usage']);

        foreach ($mismatch as $row) {
            $review[] = sprintf('وحدة #%d (رقم %s، نوع %s) استخدامها «%s» يخالف مبناها #%d «%s»', $row->id, $row->unit_no, $row->unit_type, $row->usage_type, $row->building_id, $row->building_usage);
        }

        // 3) + 4) الوحدة التلقائية والطوابق — عبر النموذج ليولد حساب الوحدة كما في أي إنشاء
        foreach (Building::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id')->get() as $building) {
            try {
                if (in_array($building->entity_type, self::SINGLE, true)) {
                    $units = DB::table('re_units')->where('building_id', $building->id)->whereNull('deleted_at')->get(['id', 'is_auto']);

                    if ($units->count() === 1) {
                        DB::table('re_units')->where('id', $units->first()->id)->update(['is_auto' => 1]);
                    } elseif ($units->isEmpty()) {
                        $unit = $building->ensureAutoUnit();
                        $review[] = sprintf('عقار #%d (%s) وحيد الوحدة بلا وحدة: أُنشئت له الوحدة التلقائية #%d', $building->id, $building->entity_type, $unit->id);
                    } elseif ($units->where('is_auto', 1)->isEmpty()) {
                        $review[] = sprintf('عقار #%d (%s) وحيد الوحدة وفيه %d وحدات: لم تُعلَّم وحدةٌ تلقائية', $building->id, $building->entity_type, $units->count());
                    }

                    if ($building->usage_type === 'mixed') {
                        $review[] = sprintf('عقار #%d (%s) وحيد الوحدة واستخدامه «مختلط»: اختر له سكني أو تجاري', $building->id, $building->entity_type);
                    }
                }

                $before = (int) $building->floors_count;
                $rows = DB::table('re_floors')->where('building_id', $building->id)->count();

                if ($building->entity_type === 'land') {
                    if ($rows > 0 || $before > 0) {
                        $building->syncFloors();
                        $review[] = sprintf('أرض #%d: حُذفت طوابقها (%d صفاً، العدد كان %d)', $building->id, $rows, $before);
                    }
                } elseif ($before !== $rows) {
                    // العدد المسجَّل أكبر من الصفوف ← تُنشأ الناقصة؛ أصغر ← يُصحَّح العدد
                    $after = $building->syncFloors($before > $rows ? $before : null);
                    $review[] = sprintf('مبنى #%d: عدد الطوابق %d والصفوف %d ← صار %d', $building->id, $before, $rows, $after);
                }
            } catch (Throwable $e) {
                $review[] = sprintf('مبنى #%d: تعذّرت المعالجة (%s)', $building->id, $e->getMessage());
            }
        }

        // العقود التي يخالف نوعها استخدام وحدتها: لا تُمسّ، تُعرض فقط
        if (Schema::hasTable('re_contracts')) {
            $contracts = DB::table('re_contracts as c')->join('re_units as u', 'u.id', '=', 'c.unit_id')
                ->whereNull('c.deleted_at')
                ->where(fn ($q) => $q
                    ->where(fn ($w) => $w->where('c.contract_type', 'residential_lease')->where('u.usage_type', 'commercial'))
                    ->orWhere(fn ($w) => $w->where('c.contract_type', 'commercial_lease')->where('u.usage_type', 'residential')))
                ->get(['c.id', 'c.code', 'c.contract_type', 'c.status', 'u.id as unit_id', 'u.usage_type']);

            foreach ($contracts as $row) {
                $review[] = sprintf('عقد #%d (%s، %s، %s) يخالف استخدام وحدته #%d «%s»', $row->id, $row->code, $row->contract_type, $row->status, $row->unit_id, $row->usage_type);
            }
        }

        if ($review !== []) {
            Log::info('re-type-usage: عناصر للمراجعة', $review);

            if (app()->runningInConsole()) {
                echo PHP_EOL.'  للمراجعة:'.PHP_EOL.'   - '.implode(PHP_EOL.'   - ', $review).PHP_EOL;
            }
        }
    }

    private function addColumns(): void
    {
        $unit = [
            'usage_type' => fn (Blueprint $t) => $t->string('usage_type', 20)->nullable()->index(),
            'frontage_m' => fn (Blueprint $t) => $t->decimal('frontage_m', 8, 2)->nullable(),
            'ceiling_height_m' => fn (Blueprint $t) => $t->decimal('ceiling_height_m', 6, 2)->nullable(),
            'openings_count' => fn (Blueprint $t) => $t->unsignedSmallInteger('openings_count')->nullable(),
            'permitted_activity' => fn (Blueprint $t) => $t->string('permitted_activity')->nullable(),
            'is_auto' => fn (Blueprint $t) => $t->boolean('is_auto')->default(false),
        ];

        $building = [
            'ownership' => fn (Blueprint $t) => $t->string('ownership', 20)->default('managed'),
            'plan_no' => fn (Blueprint $t) => $t->string('plan_no', 100)->nullable(),
            'plot_no' => fn (Blueprint $t) => $t->string('plot_no', 100)->nullable(),
            'deed_no' => fn (Blueprint $t) => $t->string('deed_no', 100)->nullable(),
            'deed_date' => fn (Blueprint $t) => $t->date('deed_date')->nullable(),
            'street_width' => fn (Blueprint $t) => $t->decimal('street_width', 8, 2)->nullable(),
            'boundaries' => fn (Blueprint $t) => $t->text('boundaries')->nullable(),
            'building_permit_no' => fn (Blueprint $t) => $t->string('building_permit_no', 100)->nullable(),
            'municipal_license_no' => fn (Blueprint $t) => $t->string('municipal_license_no', 100)->nullable(),
        ];

        foreach (['re_units' => $unit, 're_buildings' => $building] as $table => $columns) {
            foreach ($columns as $name => $define) {
                if (! Schema::hasColumn($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $define($t));
                }
            }
        }
    }

    public function down(): void
    {
        // `ownership` قد يكون من ترحيل المحاسبة، فلا يُحذف هنا. والتعبئة لا تُعكس.
        foreach ([
            're_units' => ['usage_type', 'frontage_m', 'ceiling_height_m', 'openings_count', 'permitted_activity', 'is_auto'],
            're_buildings' => ['plan_no', 'plot_no', 'deed_no', 'deed_date', 'street_width', 'boundaries', 'building_permit_no', 'municipal_license_no'],
        ] as $table => $columns) {
            foreach ($columns as $name) {
                if (Schema::hasColumn($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($name));
                }
            }
        }
    }
};
