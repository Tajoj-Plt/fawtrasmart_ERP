<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الدور الأساسي للمستخدم عمودَ علاقةٍ لا نصّاً وحده.
 *
 * كان `users.role` نصّاً حرّاً (`owner`, `manager`…) بلا رابط بجدول `roles`:
 * لا مفتاح أجنبي يمنع قيمةً لا وجود لها، ولا انضمامَ يقرأ اسم الدور
 * وصلاحياته في استعلام واحد، وتغييرُ اسم دورٍ يترك المستخدمين على اسمٍ ميّت.
 *
 * **ويبقى العمود النصّي كما هو**: عشرات الشاشات والاستعلامات تقرأه اليوم،
 * وحذفُه الآن يكسرها. فالجديد `role_id` مرجعاً موثوقاً، والنصّ يبقى مرآةً
 * له يُحدَّث معه (انظر `User::assignPrimaryRole`).
 *
 * والقيم تُملأ من جدول spatie الموجود: من له دورٌ مُسنَد يأخذ معرّفه، ومن
 * لا دور له يبقى `null` — لا تخمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            return;
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'role_id')) {
                    $table->unsignedBigInteger('role_id')->nullable()->after('role')
                        ->comment('الدور الأساسي — مرجع إلى roles.id');
                }

                if (! SchemaGuard::hasIndex('users', 'users_role_id_index')) {
                    $table->index('role_id', 'users_role_id_index');
                }
            });

            // المفتاح الأجنبي في خطوةٍ ثانية: بعض الاستضافات ترفض إضافة
            // عمودٍ وقيدٍ في عبارةٍ واحدة على جدولٍ كبير
            try {
                Schema::table('users', function (Blueprint $table) {
                    if (! SchemaGuard::hasForeignKey('users', 'users_role_id_foreign')) {
                        $table->foreign('role_id', 'users_role_id_foreign')
                            ->references('id')->on('roles')
                            ->nullOnDelete();
                    }
                });
            } catch (\Throwable) {
                // محرّك لا يدعم القيد (SQLite في الاختبارات مثلاً) — العمود
                // يبقى نافعاً والعلاقة تعمل، والسلامة تُحفظ في الشيفرة
            }
        }

        $this->backfill();
    }

    /**
     * ملء العمود من الأدوار المُسنَدة فعلاً.
     *
     * الترتيب مقصود: من له أكثر من دور يأخذ أقواها (المالك ثم المدير…)،
     * فلا يُختار دورٌ ثانوي أساساً بمحض ترتيب الإدراج.
     */
    protected function backfill(): void
    {
        try {
            $priority = ['owner', 'manager', 'admin', 'employee'];

            $roles = DB::table('roles')->pluck('id', 'name');

            foreach ($priority as $name) {
                if (! isset($roles[$name])) {
                    continue;
                }

                $roleId = $roles[$name];

                // من أُسنِد إليه هذا الدور في جدول spatie ولم يُملأ عموده بعد
                $userIds = DB::table('model_has_roles')
                    ->where('role_id', $roleId)
                    ->where('model_type', \App\Models\User::class)
                    ->pluck('model_id');

                if ($userIds->isNotEmpty()) {
                    DB::table('users')->whereIn('id', $userIds)->whereNull('role_id')
                        ->update(['role_id' => $roleId]);
                }

                // ومن يحمل الاسم في العمود النصّي القديم بلا إسناد
                DB::table('users')->where('role', $name)->whereNull('role_id')
                    ->update(['role_id' => $roleId]);
            }
        } catch (\Throwable) {
            // جداول spatie غير مهيّأة بعد على تركيبٍ جديد — الهجرة تمرّ،
            // والبذور تُسند الأدوار بعدها فتملأ العمود عبر النموذج
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropForeign('users_role_id_foreign');
            } catch (\Throwable) {
                // لم يُنشأ أصلاً
            }

            $table->dropColumn('role_id');
        });
    }
};
