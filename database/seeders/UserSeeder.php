<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * مستخدمو التجربة والتطوير.
 *
 * **هذا سيدر بيانات تجريبية — لا يُشغَّل على نظامٍ إنتاجي.** حساباته
 * بكلمة مرور واحدة معروفة، ومعالج التثبيت يستبعده عمداً وينشئ المدير من
 * نموذجه بكلمة مرور صاحبه.
 *
 * **ثلاثة أعطال كانت هنا:**
 *
 * ١. `updateOrCreate` مع `'password' => Hash::make('123456')` — أي أن كل
 *    تشغيل لـ `db:seed` يُعيد كلمات مرور الحسابات الخمسة إلى `123456`،
 *    ولو كان أصحابها قد غيّروها. سيدرٌ يُشغَّل لإضافة بذرةٍ جديدة كان
 *    يفتح خمسة حسابات بكلمة مرور معروفة، صامتاً. الآن: كلمة المرور تُكتب
 *    عند الإنشاء فقط، ولا تُمسّ لحسابٍ قائم.
 *
 * ٢. `alrwies@gmail.com` كان عمود `role` فيه `manager` بينما يُسنَد إليه
 *    دور Spatie اسمه `owner` — والتعليق فوقه يقول «(owner)». مصدرا الدور
 *    يتناقضان في نفس الصفّ. صار العمود والدور يُشتقّان من مصدرٍ واحد.
 *
 * ٣. `Permission::pluck('id','id')` ثم `syncPermissions(...)` على الدورين.
 *    مشكلتان: المزامنة تمحو ما ضبطه صاحب النظام من شاشة الأدوار، وقائمةُ
 *    الصلاحيات تُلتقط وقتَ التشغيل فتختلف باختلاف ترتيب السيدرات. بناء
 *    الأدوار انتقل كلّه إلى [RolesSeeder] بقائمةٍ صريحة ومنحٍ إضافي.
 */
class UserSeeder extends Seeder
{
    /**
     * حسابات التجربة.
     *
     * `role` هو عمود النص في جدول `users` (يقارَن به في ~137 موضعاً)،
     * و`spatie_role` هو دور الصلاحيات. يُذكران معاً في مكانٍ واحد كي لا
     * يفترقا كما افترقا سابقاً.
     */
    protected const DEMO_USERS = [
        [
            'name' => 'عبدالمنعم',
            'email' => 'admin@example.com',
            'phone' => '0966123456789',
            'role' => 'manager',
            'spatie_role' => 'manager',
        ],
        [
            'name' => 'مشتاق قايد الوتر',
            'email' => 'moshtaq@gmail.com',
            'phone' => '0534781240',
            'role' => 'manager',
            'spatie_role' => 'manager',
        ],
        [
            'name' => 'علي محمد',
            'email' => 'alimohmed@gmail.com',
            'phone' => '0534781243',
            'role' => 'manager',
            'spatie_role' => 'manager',
        ],
        [
            'name' => 'محمد فالح العتيبي',
            'email' => 'alrwies@gmail.com',
            'phone' => '0534781241',
            'role' => 'owner',
            'spatie_role' => 'owner',
        ],
        [
            'name' => 'مالك اثنين',
            'email' => 'owner2@example.com',
            'phone' => '0534781242',
            'role' => 'owner',
            'spatie_role' => 'owner',
        ],
    ];

    /** كلمة المرور الأولى — للحسابات المُنشأة حديثاً فقط. */
    protected const INITIAL_PASSWORD = '123456';

    public function run(): void
    {
        // الأدوار شرطٌ لا نتيجة: بلا `manager` و`owner` يفشل الإسناد أدناه
        // برمي استثناء `RoleDoesNotExist`. واستدعاؤه هنا يجعل هذا السيدر
        // قابلاً للتشغيل وحده دون معرفة ترتيبه في `DatabaseSeeder`
        $this->ensureRolesExist();

        $created = 0;
        $updated = 0;

        foreach (self::DEMO_USERS as $definition) {
            $user = User::where('email', $definition['email'])->first();

            if ($user) {
                // كلمة المرور غائبة عن التحديث عمداً — انظر العطل ١ أعلاه
                $user->fill([
                    'name' => $definition['name'],
                    'phone' => $definition['phone'],
                ])->save();

                $updated++;
            } else {
                $user = User::create([
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'phone' => $definition['phone'],
                    'password' => Hash::make(self::INITIAL_PASSWORD),
                ]);

                $created++;
            }

            // `role_id` هو ما تقرأه القائمة والوسيط (`hasPermission`).
            // `syncRoles` وحدها تملأ Spatie وتترك `role_id` فارغاً فيختفي
            // كل قسم محروس بصلاحية — وهذا ما كان يحدث لحسابات التجربة.
            $role = Role::findByName($definition['spatie_role'], RolesSeeder::GUARD);
            $user->assignPrimaryRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("  المستخدمون: {$created} أُنشئ، {$updated} حُدِّث (بلا مساس بكلمات المرور القائمة).");

        if ($created > 0) {
            $this->command?->warn('  كلمة المرور الأولى للحسابات الجديدة: '.self::INITIAL_PASSWORD.' — غيّرها فوراً.');
        }
    }

    /**
     * التأكد من وجود الأدوار المطلوبة، وزرعها إن غابت.
     */
    protected function ensureRolesExist(): void
    {
        $required = array_unique(array_column(self::DEMO_USERS, 'spatie_role'));

        $present = Role::where('guard_name', RolesSeeder::GUARD)
            ->whereIn('name', $required)
            ->pluck('name')
            ->all();

        if (array_diff($required, $present) === []) {
            return;
        }

        $this->command?->warn('  أدوار ناقصة — يُشغَّل RolesSeeder أولاً.');

        $this->call(RolesSeeder::class);
    }
}
