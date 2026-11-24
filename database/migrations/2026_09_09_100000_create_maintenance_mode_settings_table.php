<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وضع الصيانة: إقفال النظام في وجه الجميع إلا مستخدمين محدَّدين.
 *
 * صفٌّ واحد لا جدولُ إعدادات بمفاتيح: الحالة كلُّها سؤالٌ واحد له جوابٌ واحد
 * في كل لحظة — أمفتوحٌ النظام أم مقفل، ولمن. وتوزيعُها على مفاتيح `key/value`
 * يجعل قراءتها ثلاثة استعلامات ويسمح بحالةٍ نصفِ مكتوبة: «مقفل» محفوظة
 * و«المسموح لهم» لم تُحفظ بعد — أي إقفالٌ بلا أحدٍ يملك فتحه.
 *
 * والصفّ يُنشَأ هنا بقيمه المبدئية (مفتوح) لا عند أوّل حفظ: جدولٌ فارغ يعني
 * أن كل قراءةٍ للحالة تحتاج فرعاً لحالة «لا صفّ»، والوسيط الذي يقرؤها يعمل
 * على كل طلبٍ في النظام.
 */
return new class extends Migration
{
    /**
     * الصلاحيتان الجديدتان والأدوارُ التي تنالهما.
     *
     * تُزرع في الترحيل نفسه لا في سيدرٍ منفصل — والسبب تجربةٌ سابقة في هذا
     * النظام: صلاحيةٌ جديدة تُنشَأ ولا تُربط بدور، فيبقى بندُها مخفيّاً عن
     * الجميع بمن فيهم المالك، فتُفتح الشاشةُ بتعطيل حارسها (كما وقع لشاشة
     * «تحديث النظام»). ووضعُ الصيانة أسوأ موضعٍ لهذا: شاشةٌ لا يفتحها أحدٌ
     * هي وضعُ صيانةٍ لا يُطفَأ.
     */
    private const PERMISSIONS = [
        'settings.maintenance_mode.view' => 'عرض شاشة وضع الصيانة',
        'settings.maintenance_mode.manage' => 'تفعيل وضع الصيانة وإيقافه',
    ];

    private const ROLES = ['owner', 'manager', 'admin'];

    public function up(): void
    {
        if (! Schema::hasTable('maintenance_mode_settings')) {
            Schema::create('maintenance_mode_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(false);

                // ما يراه المحجوب. `null` يعني الرسالة المبدئية في الشاشة —
                // لا رسالةً فارغة
                $table->text('message')->nullable();

                /*
                    المسموح لهم — معرّفات مستخدمين في عمود JSON لا جدولِ ربط.

                    جدولُ ربطٍ هو الصواب حين تُستعلَم العلاقة أو تُصفَّى أو
                    يُعرَض عليها تقرير. وهذه لا تُستعلَم أبداً: تُقرأ كاملةً مع
                    الصفّ في كل مرّة (والقراءة نفسها من الكاش لا من القاعدة)،
                    ويُسأل عنها سؤالٌ واحد — أهذا المعرّف فيها؟ فجدولٌ ثانٍ
                    وقيدُ مفتاحٍ أجنبي وترحيلٌ ثالث ثمنٌ بلا مقابل.
                */
                $table->json('allowed_user_ids')->nullable();

                /*
                    مفتاح تجاوزٍ في العنوان — مخرجُ الطوارئ.

                    إن قُفل النظام ثم تعذّر دخول كل من في القائمة (حسابٌ عُطّل،
                    أو كلمةُ مرورٍ نُسيت، أو خطأٌ في الاختيار)، فلا سطرَ أوامر
                    على استضافةٍ مشتركة يُطفئ الوضع. فيبقى هذا: رابطٌ يحمل
                    المفتاح يمنح جلسةً واحدةً حقَّ المرور، فيدخل صاحبُه ويُطفئ
                    الوضع من الشاشة.
                */
                $table->string('secret', 64)->nullable();

                $table->unsignedBigInteger('activated_by')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }

        // الصفّ الوحيد — بحالة «مفتوح»
        if (DB::table('maintenance_mode_settings')->count() === 0) {
            DB::table('maintenance_mode_settings')->insert([
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seedPermissions();
    }

    /**
     * إنشاء الصلاحيتين وربطهما بالأدوار الإدارية.
     *
     * كلُّ خطوة مشروطةٌ بوجود جدولها: قواعدُ الإنتاج هنا تسبق مجموعةَ
     * الترحيلات، وترحيلٌ يسقط على جدولٍ غير موجودٍ يُوقف التحديث كلَّه
     * (`SchemaGuard` أُنشئ لهذا السبب نفسه). وإنشاءُ الجدول أعلاه هو أصل
     * هذا الترحيل، أمّا الصلاحيات فتحسينٌ لا يجوز أن يُسقطه.
     */
    private function seedPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        foreach (self::PERMISSIONS as $name => $label) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $linked = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $linked) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * التراجع يحذف الجدول ولا يمسّ الصلاحيات.
     *
     * حذفُ صفٍّ من `permissions` يسحب معه روابطَه في `role_has_permissions`
     * — وقد يكون مسؤولٌ منحها لدورٍ آخر بيده بعد الترحيل، فيضيع عملُه في
     * تراجعٍ عن ترحيلٍ لا علاقة له بالأدوار. والصلاحيةُ اليتيمة لا تضرّ:
     * لا مسارَ يطلبها بعد حذف الجدول.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_mode_settings');
    }
};
