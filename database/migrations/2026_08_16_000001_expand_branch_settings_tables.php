<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع جداول إعدادات الفروع.
 *
 * الجدول القديم كان يحمل مفتاحاً واسماً فقط، وقيمة كل إعداد بوليانية في
 * الجدول الوسيط (`status`). هذا يكفي لخمسة إعدادات مشاركة، ولا يكفي لإعداد
 * مثل «أقصى نسبة خصم» أو «وقت بداية الدوام». هنا نضيف:
 *
 *  - وصفاً وتصنيفاً ونوعاً وقيمة افتراضية لكل إعداد (`branch_settings`)
 *  - عموداً حرّاً للقيمة في الجدول الوسيط (`branch_setting_branch.value`)
 *  - فهرساً فريداً يمنع تكرار سطر الإعداد نفسه للفرع نفسه
 *
 * `status` يبقى كما هو للتوافق مع الإعدادات البوليانية القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_settings', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('branch_settings', 'group')) {
                $table->string('group')->default('general')->index()->after('description');
            }
            if (! Schema::hasColumn('branch_settings', 'type')) {
                $table->string('type', 30)->default('boolean')->after('group');
            }
            if (! Schema::hasColumn('branch_settings', 'options')) {
                $table->json('options')->nullable()->after('type');
            }
            if (! Schema::hasColumn('branch_settings', 'default_value')) {
                $table->string('default_value')->nullable()->after('options');
            }
            if (! Schema::hasColumn('branch_settings', 'is_inheritable')) {
                // إعداد قابل للتوريث: الفرع الذي لم تُضبط له قيمة يرث قيمة أبيه.
                $table->boolean('is_inheritable')->default(true)->after('default_value');
            }
            if (! Schema::hasColumn('branch_settings', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_inheritable');
            }
        });

        Schema::table('branch_setting_branch', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_setting_branch', 'value')) {
                $table->text('value')->nullable()->after('status');
            }
            if (! Schema::hasColumn('branch_setting_branch', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('value');
            }
        });

        // إزالة التكرارات قبل فرض الفهرس الفريد — `updateOrCreate` القديم كان
        // يعتمد على غياب القيد، وأي سطرين متطابقين يجعلان القراءة غير حتمية.
        $duplicates = DB::table('branch_setting_branch')
            ->select('branch_id', 'branch_setting_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('branch_id', 'branch_setting_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('branch_setting_branch')
                ->where('branch_id', $row->branch_id)
                ->where('branch_setting_id', $row->branch_setting_id)
                ->where('id', '<>', $row->keep_id)
                ->delete();
        }

        if (! $this->hasIndex('branch_setting_branch', 'branch_setting_branch_unique')) {
            Schema::table('branch_setting_branch', function (Blueprint $table) {
                if (! SchemaGuard::hasIndex('branch_setting_branch', 'branch_setting_branch_unique')) {
                    $table->unique(['branch_id', 'branch_setting_id'], 'branch_setting_branch_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('branch_setting_branch', 'branch_setting_branch_unique')) {
            Schema::table('branch_setting_branch', function (Blueprint $table) {
                $table->dropUnique('branch_setting_branch_unique');
            });
        }

        Schema::table('branch_setting_branch', function (Blueprint $table) {
            foreach (['value', 'updated_by'] as $column) {
                if (Schema::hasColumn('branch_setting_branch', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            foreach (['description', 'group', 'type', 'options', 'default_value', 'is_inheritable', 'sort_order'] as $column) {
                if (Schema::hasColumn('branch_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn ($i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
