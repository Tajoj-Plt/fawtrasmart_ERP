<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تحويل `color_sittings` من «لون واحد» إلى إعدادات مظهر كاملة.
 *
 * الجدول والعمود `color` يبقيان كما هما لأن 32 ملفاً يقرأ `$backgroundColorr`
 * المشتق منهما — التوسعة إضافية بحتة ولا تكسر شيئاً قائماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('color_sittings')) {
            return;
        }

        Schema::table('color_sittings', function (Blueprint $table) {
            // ==================== الألوان ====================
            if (! Schema::hasColumn('color_sittings', 'primary_color')) {
                $table->string('primary_color', 20)->nullable()->after('color')
                    ->comment('اللون الأساسي للأزرار والروابط');
            }
            if (! Schema::hasColumn('color_sittings', 'sidebar_color')) {
                $table->string('sidebar_color', 20)->nullable()->after('primary_color')
                    ->comment('لون خلفية القائمة الجانبية');
            }
            if (! Schema::hasColumn('color_sittings', 'sidebar_text_color')) {
                $table->string('sidebar_text_color', 20)->nullable()->after('sidebar_color');
            }
            if (! Schema::hasColumn('color_sittings', 'topbar_color')) {
                $table->string('topbar_color', 20)->nullable()->after('sidebar_text_color')
                    ->comment('لون الشريط العلوي');
            }
            if (! Schema::hasColumn('color_sittings', 'success_color')) {
                $table->string('success_color', 20)->nullable()->after('topbar_color');
            }
            if (! Schema::hasColumn('color_sittings', 'danger_color')) {
                $table->string('danger_color', 20)->nullable()->after('success_color');
            }

            // ==================== الخط ====================
            if (! Schema::hasColumn('color_sittings', 'font_family')) {
                $table->string('font_family', 100)->nullable()->after('danger_color')
                    ->comment('اسم الخط: Cairo, Tajawal, ...');
            }
            if (! Schema::hasColumn('color_sittings', 'font_size')) {
                $table->string('font_size', 10)->nullable()->after('font_family')
                    ->comment('حجم الخط الأساسي مثل 14px');
            }
            if (! Schema::hasColumn('color_sittings', 'heading_font_weight')) {
                $table->string('heading_font_weight', 10)->nullable()->after('font_size');
            }

            // ==================== الشكل العام ====================
            if (! Schema::hasColumn('color_sittings', 'border_radius')) {
                $table->string('border_radius', 10)->nullable()->after('heading_font_weight')
                    ->comment('استدارة الحواف مثل 6px');
            }
            if (! Schema::hasColumn('color_sittings', 'layout_mode')) {
                $table->string('layout_mode', 20)->default('light')->after('border_radius')
                    ->comment('light|dark|semi-dark');
            }
            if (! Schema::hasColumn('color_sittings', 'sidebar_position')) {
                $table->string('sidebar_position', 20)->default('fixed')->after('layout_mode')
                    ->comment('fixed|static');
            }
            if (! Schema::hasColumn('color_sittings', 'compact_mode')) {
                $table->boolean('compact_mode')->default(false)->after('sidebar_position')
                    ->comment('تقليل التباعد لعرض بيانات أكثر');
            }

            // ==================== الهوية ====================
            if (! Schema::hasColumn('color_sittings', 'login_logo')) {
                $table->string('login_logo')->nullable()->after('compact_mode');
            }
            if (! Schema::hasColumn('color_sittings', 'favicon')) {
                $table->string('favicon')->nullable()->after('login_logo');
            }
            if (! Schema::hasColumn('color_sittings', 'app_title')) {
                $table->string('app_title', 100)->nullable()->after('favicon')
                    ->comment('الاسم الظاهر في تبويب المتصفح والقائمة');
            }

            if (! Schema::hasColumn('color_sittings', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('app_title');
            }
        });

        // صفّ واحد دائماً: الشاشة تقرأ الأول وتكتب عليه
        if (DB::table('color_sittings')->count() === 0) {
            DB::table('color_sittings')->insertOrIgnore([
                'color' => '#ffffff',
                'primary_color' => '#7367F0',
                'sidebar_color' => '#ffffff',
                'sidebar_text_color' => '#565656',
                'topbar_color' => '#ffffff',
                'success_color' => '#28C76F',
                'danger_color' => '#EA5455',
                'font_family' => 'Cairo',
                'font_size' => '14px',
                'heading_font_weight' => '600',
                'border_radius' => '6px',
                'layout_mode' => 'light',
                'sidebar_position' => 'fixed',
                'compact_mode' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('color_sittings')) {
            return;
        }

        Schema::table('color_sittings', function (Blueprint $table) {
            $columns = [
                'primary_color', 'sidebar_color', 'sidebar_text_color', 'topbar_color',
                'success_color', 'danger_color', 'font_family', 'font_size',
                'heading_font_weight', 'border_radius', 'layout_mode', 'sidebar_position',
                'compact_mode', 'login_logo', 'favicon', 'app_title', 'updated_by',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('color_sittings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
