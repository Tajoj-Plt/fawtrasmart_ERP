<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * أنواع الصور المطلوب التقاطها عند الحضور/الانصراف.
 * مرنة: تستطيع الإضارة (شخصية، موقع، مركبة، نموذج، توقيع، ...) بدون migration جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_photo_types')) {
            Schema::create('attendance_photo_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique()->comment('selfie / location / vehicle / signature / ...');
                $table->string('name')->comment('الاسم المعروض للمستخدم');
                $table->string('icon', 50)->nullable()->comment('FontAwesome icon class');
                $table->text('description')->nullable();
                $table->enum('phase', ['checkin', 'checkout', 'both'])->default('both')
                    ->comment('متى تُلتقط: عند الحضور أم الانصراف أم كليهما');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_required')->default(false)->comment('إجبارية أم اختيارية');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'phase'], 'idx_photo_types_active_phase');
            });
        }

        // === Seed default photo types ===
        $defaults = [
            ['code' => 'selfie',    'name' => 'صورة شخصية',    'icon' => 'fa-user',          'phase' => 'both',     'sort_order' => 1, 'is_required' => true],
            ['code' => 'location',  'name' => 'صورة الموقع',    'icon' => 'fa-map-marker',    'phase' => 'checkin',  'sort_order' => 2, 'is_required' => false],
            ['code' => 'vehicle',   'name' => 'صورة المركبة',   'icon' => 'fa-car',           'phase' => 'both',     'sort_order' => 3, 'is_required' => false],
            ['code' => 'signature', 'name' => 'توقيع/إيصال',    'icon' => 'fa-signature',     'phase' => 'checkout', 'sort_order' => 4, 'is_required' => false],
        ];

        foreach ($defaults as $row) {
            if (!DB::table('attendance_photo_types')->where('code', $row['code'])->exists()) {
                DB::table('attendance_photo_types')->insertOrIgnore(array_merge($row, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_photo_types');
    }
};
