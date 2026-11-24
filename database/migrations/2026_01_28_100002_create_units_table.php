<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جدول الوحدات.
 *
 * **كان `Schema::create('units')` مكشوفاً** بينما يُنشئ الجدولَ قبله
 * `2025_08_14_140749_create_units_table.php` — فيسقط `migrate` على قاعدة
 * جديدة بـ «Table units already exists»، ولا يكمل ما بعده من ~40 هجرة.
 *
 * والشكلان مختلفان لا مكرّران:
 * - القديم: `unit_type_id`, `priority`, `status` — تستعمله وحدة التأجير
 *   (`RentalManagement/UnitsController`, `SeasonalPrice`).
 * - الجديد: `symbol`, `is_active` — وهو ما يعلنه `App\Models\Unit::$fillable`.
 *
 * فتخطّي هذه الهجرة يترك النموذج مشيراً إلى أعمدة غير موجودة، وحذفُ
 * القديمة يكسر وحدة التأجير. الحل: إنشاءٌ عند الغياب، وإكمالُ الناقص عند
 * الوجود — فيسع الجدولُ الشكلين معاً.
 */
return new class extends Migration
{
    /** الوحدات الافتراضية: الاسم ⇦ [الرمز، الوصف] */
    private const DEFAULTS = [
        'حبة' => ['pcs', 'وحدة القطعة الواحدة'],
        'كرتون' => ['ctn', 'كرتونة تحتوي على عدة قطع'],
        'علبة' => ['box', 'علبة صغيرة'],
        'كيلو' => ['kg', 'وحدة الوزن - كيلو جرام'],
        'جرام' => ['g', 'وحدة الوزن - جرام'],
        'لتر' => ['L', 'وحدة الحجم - لتر'],
        'ملي لتر' => ['mL', 'وحدة الحجم - ملي لتر'],
        'متر' => ['m', 'وحدة الطول - متر'],
        'درزن' => ['dzn', 'درزنة = 12 قطعة'],
        'باكت' => ['pkt', 'باكت / عبوة'],
        'طن' => ['ton', 'وحدة الوزن - طن'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique()->comment('اسم الوحدة (حبة، كرتون، كيلو)');
                $table->string('symbol', 50)->nullable()->comment('رمز الوحدة (pcs, ctn, kg)');
                $table->text('description')->nullable()->comment('وصف الوحدة');
                $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                $table->timestamps();

                $table->index('is_active');
            });
        } else {
            Schema::table('units', function (Blueprint $table) {
                if (! Schema::hasColumn('units', 'symbol')) {
                    $table->string('symbol', 50)->nullable()->comment('رمز الوحدة (pcs, ctn, kg)');
                }
                if (! Schema::hasColumn('units', 'is_active')) {
                    $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                }
            });

            // الشكل القديم يجعل `unit_type_id` إلزامياً، فإدراج وحدةٍ من
            // الشاشة الجديدة (التي لا تعرف أنواع الوحدات) يسقط عليه
            if (Schema::hasColumn('units', 'unit_type_id')) {
                Schema::table('units', function (Blueprint $table) {
                    $table->unsignedBigInteger('unit_type_id')->nullable()->change();
                });
            }
        }

        $this->seedDefaults();
    }

    /**
     * الوحدات الافتراضية — تُدرَج بالاسم مرةً واحدة.
     *
     * `insert` بالجملة كان يسقط على قاعدةٍ فيها وحدات مسبقاً (فهرس `name`
     * فريد). والإدراج صفّاً صفّاً بعد الفحص يجعل الهجرة صالحة على الحالتين.
     */
    private function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $name => [$symbol, $description]) {
            if (DB::table('units')->where('name', $name)->exists()) {
                continue;
            }

            $row = [
                'name' => $name,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('units', 'symbol')) {
                $row['symbol'] = $symbol;
            }
            if (Schema::hasColumn('units', 'is_active')) {
                $row['is_active'] = true;
            }

            DB::table('units')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        // لا `dropIfExists`: الجدول قد يكون من إنشاء الهجرة الأقدم، فإسقاطه
        // هنا يمحو ما لم تُنشئه هذه الهجرة ويكسر وحدة التأجير
        DB::table('units')->whereIn('name', array_keys(self::DEFAULTS))->delete();
    }
};
