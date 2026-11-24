<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\CronJob;

class SettingsController extends Controller
{
    public function __construct()
    {
        // صفحةُ الإعدادات تجمع بطاقاتٍ لكلٍّ منها صلاحيتُها — يدخلها من يملك أيَّها،
        // وكلُّ بطاقةٍ تُعرض لصاحب صلاحيتها وحده
        $this->middleware('permission.role:attendance_settings.manage|holidays.index|attendance_rules.index|leave_types.index|leave_policies.index');
    }

    public function index()
    {
        /*
            حالةُ الإغلاق التلقائي للأيام غير المكتملة.

            `attendance:process` هي التي تُغلق يومَ من لم يُسجِّل، وتحسب
            التأخيرَ والغياب، وتُنشئ الخصميات. وهي مهمّةٌ مجدولةٌ في
            `cron_jobs` — تُعطَّل أو يتوقّف `schedule:run` على الخادم فلا
            يظهر ذلك في أيّ شاشةٍ من شاشات الحضور: الأيامُ تبقى مفتوحةً
            بلا حساب، والرواتبُ تُصرف على بياناتٍ لم تُعالَج، ولا شيءَ
            يقول إن شيئاً توقّف.

            فتُقرأ حالتُها هنا — في لوحة قواعد الموديول — لا في شاشة
            المهام وحدها، حيث لا يبحث عنها من يدير الحضور.
        */
        $autoCloseJob = CronJob::where('handler', 'attendance:process')->first();

        return view('hr::attendance.settings.index', compact('autoCloseJob'));
    }
}
