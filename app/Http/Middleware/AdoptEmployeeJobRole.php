<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * يُسنِد لحساب الموظف دورَ ملفّه إن وُجد الحسابُ بلا دورٍ إطلاقاً.
 *
 * فحصُ الصلاحيات كلُّه يقرأ `users.role_id`، والمسؤول يمنح الصلاحيات
 * للدور من شاشة الأدوار ويتوقّع أن تصل كل من يحمله. لكن حساباتٍ قديمة
 * (قاعدةٌ مستوردة، حسابٌ أُنشئ قبل إصلاح ترتيب الإسناد) بقيت `role_id`
 * فيها فارغة — فيقول ملفُّ الموظف «مندوب مبيعات» والنظامُ يراه بلا أي
 * صلاحية: لا إعفاء تبريرات ولا غيره، مهما فُعِّل على الدور.
 *
 * هنا يُصلَح ذلك عند أول طلب، حتى للجلسات المفتوحة أصلاً، بلا أمرٍ على
 * السيرفر. وقبل `CheckMaintenanceMode` و`CheckIncompleteVisits` عمداً:
 * كلاهما يسأل عن صلاحية، فيجب أن يرى الدورَ المُسنَد في الطلب نفسه.
 *
 * لا يمسّ حساباً له دورٌ أصلاً — ملفّات الموظفين في القواعد القائمة تقول
 * «مندوب مبيعات» حتى لحسابي المالك والمدير. انظر `User::adoptEmployeeJobRole()`.
 */
class AdoptEmployeeJobRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role_id === null && $user->employee_id) {
            try {
                $user->adoptEmployeeJobRole();
            } catch (\Throwable $e) {
                // إسنادٌ فاشل لا يوقف الطلب — يبقى الحساب كما كان بلا دور
                \App\Support\OperationLog::failure($e, 'Users', 'adopt_employee_job_role', $user->id);
            }
        }

        return $next($request);
    }
}
