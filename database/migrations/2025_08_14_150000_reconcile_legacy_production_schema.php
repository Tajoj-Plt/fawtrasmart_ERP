<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يردم الفجوةَ بين سكيما التركيبات القائمة ومجموعة الهجرات الحالية.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * قواعدُ التركيبات العاملة بُنيت من نسخةٍ أقدم من هذه المجموعة ثم واصلت
 * العملَ بترقيعاتٍ مباشرة: جدولُ `migrations` فيها يذكر أسماءً لم تعد
 * موجودةً كملفات، وجداولُها موجودةٌ لكن تنقصها أعمدةٌ يعتمد عليها الكود.
 * فأيُّ `migrate` على قاعدةٍ كهذه يتوقّف عند أول انحراف.
 *
 * كلُّ ما هنا محروسٌ بـ hasTable/hasColumn، فعلى تركيبٍ جديدٍ تمرُّ الهجرةُ
 * بلا أثرٍ إطلاقاً — الأعمدةُ تخرج صحيحةً من هجرات الإنشاء.
 *
 * ── لماذا هذا التاريخُ بالذات ─────────────────────────────────────────
 *
 * الطابعُ الزمني `2025_08_14_150000` بعد دفعة الإنشاء (`…_140749`) ودفعة
 * المفاتيح الأجنبية (`…_140752`) مباشرةً، وقبل أولِ هجرةٍ تحتاج ما نصلحه
 * هنا (`2025_10_25_165914_add_sprint_id_to_tasks_table`). الترتيبُ في
 * Laravel باسم الملف، فالإصلاحُ يسبق مَن يعتمد عليه لا العكس.
 */
return new class extends Migration
{
    /**
     * أعمدةٌ يعرّفها المستودعُ وتغيب عن السكيما القديمة.
     *
     * التعريفاتُ منسوخةٌ حرفياً عن هجرات الإنشاء الأصلية — أيُّ اختلافٍ
     * هنا يعني جدولين بشكلين مختلفين حسب عمر القاعدة.
     */
    private function addMissingColumns(): void
    {
        // من `2025_08_14_140749_create_attendance_days_table`
        // بابُ الحضور التلقائي (AttendanceProcessorService) وشاشةُ الباركود
        // لا يقرآن سوى check_in_time/check_out_time.
        $this->on('attendance_days', [
            'check_in_time' => fn (Blueprint $t) => $t->time('check_in_time')->nullable(),
            'check_out_time' => fn (Blueprint $t) => $t->time('check_out_time')->nullable(),
            'check_in_method' => fn (Blueprint $t) => $t->enum('check_in_method', ['manual', 'barcode', 'qr'])->default('manual'),
            'check_out_method' => fn (Blueprint $t) => $t->enum('check_out_method', ['manual', 'barcode', 'qr'])->default('manual'),
            'scanned_via_barcode' => fn (Blueprint $t) => $t->boolean('scanned_via_barcode')->default(false),
            'ip_address' => fn (Blueprint $t) => $t->string('ip_address', 45)->nullable(),
            'user_agent' => fn (Blueprint $t) => $t->text('user_agent')->nullable(),
        ]);

        // من `2025_08_14_140749_create_client_relations_table`
        $this->on('client_relations', [
            'quotation_id' => fn (Blueprint $t) => $t->bigInteger('quotation_id')->nullable(),
        ]);

        // من `2025_08_14_140749_create_manufactur_orders_table`
        $this->on('manufactur_orders', [
            'finished_at' => fn (Blueprint $t) => $t->timestamp('finished_at')->nullable(),
            'main_warehouse_id' => fn (Blueprint $t) => $t->unsignedBigInteger('main_warehouse_id')->nullable(),
            'waste_warehouse_id' => fn (Blueprint $t) => $t->unsignedBigInteger('waste_warehouse_id')->nullable(),
            'actual_quantity' => fn (Blueprint $t) => $t->decimal('actual_quantity', 10, 2)->nullable(),
            'finish_notes' => fn (Blueprint $t) => $t->text('finish_notes')->nullable(),
        ]);

        // من `2024_01_01_000003_create_tasks_table`
        $this->on('tasks', [
            'assigned_to' => fn (Blueprint $t) => $t->unsignedBigInteger('assigned_to')->nullable()->comment(' اسناد إلى'),
        ]);

        // من `2025_08_14_140749_create_taxs_invoice_table`
        $this->on('taxs_invoice', [
            'purchase_quotation_view_id' => fn (Blueprint $t) => $t->bigInteger('purchase_quotation_view_id')->nullable(),
        ]);
    }

    /**
     * أعمدةُ ENUM وصلت من الإنتاج بقيمةٍ فارغةٍ مكرّرة — `enum('a','b','','')`.
     *
     * تعريفٌ تقبله MariaDB عند الاستيراد ثم ترفض بعده **أيَّ** ALTER على
     * الجدول كلِّه بالخطأ 1291، فتتعطّل هجراتٌ لا صلةَ لها بالعمود المعطوب.
     * لا صفَّ واحدٌ يحمل القيمةَ الفارغة في الجدولين، فتُحذف الزائدةُ فقط
     * وتبقى الأخرى تسامحاً مع صفوفٍ قد تحملها في تركيباتٍ أخرى.
     */
    private function repairDuplicateEmptyEnums(): void
    {
        $columns = [
            'client_relations' => [
                'type' => "ENUM('client','invoice','') NOT NULL DEFAULT 'client'",
            ],
            'employee_client_visits' => [
                'status' => "ENUM('active','unactive','') NOT NULL DEFAULT 'unactive'",
            ],
        ];

        foreach ($columns as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $column => $definition) {
                if (! SchemaGuard::hasDuplicateEmptyEnum($table, $column)) {
                    continue;
                }

                DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
            }
        }
    }

    /**
     * `tasks.sprint_id` وصل من الإنتاج `BIGINT` بإشارة، بينما `sprints.id`
     * بلا إشارة. المستودعُ يعرّفه `unsignedBigInteger` منذ هجرة الإنشاء —
     * فالانحرافُ في القاعدة وحدها. وما دامت الإشارةُ مختلفةً ترفض MySQL
     * المفتاحَ الأجنبي بالخطأ 150 في `2025_10_25_165914`.
     */
    private function alignSprintIdSignedness(): void
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'sprint_id')) {
            return;
        }

        $type = SchemaGuard::columnType('tasks', 'sprint_id');

        if ($type === null || str_contains($type, 'unsigned')) {
            return;
        }

        // لا صفَّ سالباً يمكن أن يفقد قيمته: العمودُ مفتاحٌ أجنبي لا عدّاد.
        DB::statement('ALTER TABLE `tasks` MODIFY COLUMN `sprint_id` BIGINT UNSIGNED NULL');
    }

    public function up(): void
    {
        // SQLite (بيئةُ الاختبارات وحدها) تُبنى من الصفر، فلا انحرافَ يُردم.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // الترتيبُ مقصود: ENUM المعطوب يشلُّ **كلَّ** ALTER على جدوله، ومنه
        // إضافةُ `client_relations.quotation_id` أدناه. يُصلَح أولاً.
        $this->repairDuplicateEmptyEnums();
        $this->addMissingColumns();
        $this->alignSprintIdSignedness();
    }

    /**
     * لا عكس.
     *
     * ما تضيفه هذه الهجرةُ هو ما تعرّفه هجراتُ الإنشاء أصلاً؛ حذفُه يعيد
     * القاعدةَ إلى حالةٍ يعطبُ فيها الكود، ولا يُميّز عمودٌ أُضيف هنا عن
     * عمودٍ خرج من هجرة الإنشاء على تركيبٍ جديد.
     */
    public function down(): void
    {
        // مقصود: لا شيء.
    }

    /**
     * يضيف من `$columns` ما يغيب عن الجدول فعلاً، في استدعاءٍ واحد.
     *
     * @param  array<string, callable(Blueprint): mixed>  $columns
     */
    private function on(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = array_filter(
            $columns,
            fn (string $column) => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );

        if (! $missing) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing) {
            foreach ($missing as $define) {
                $define($blueprint);
            }
        });
    }
};
