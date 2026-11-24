<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Maintenance\MaintenanceModeService;
use App\Support\OperationLog;
use Illuminate\Http\Request;

/**
 * شاشة «وضع الصيانة» — إقفال النظام على الجميع إلا من سُمّي.
 *
 * الصلاحيات مفصولة عن `settings_edit_general_settings` لسبب الفصل نفسه في
 * النسخ الاحتياطي وتحديث النظام: من يعدّل شعار المنشأة لا يجوز أن يُخرج
 * الموظّفين كلَّهم من النظام بضغطة.
 *
 * والصلاحيتان تُزرعان في ترحيل الجدول نفسه ممنوحتين لـ`owner`/`manager`/`admin`
 * — كي لا تتكرّر حكايةُ «تحديث النظام»: شاشةٌ بصلاحيةٍ لا يملكها أحد، فتُفتَح
 * آخرَ الأمر بتعطيل حارسها.
 */
class MaintenanceModeController extends Controller
{
    public function __construct(protected MaintenanceModeService $maintenance)
    {
        $this->middleware('permission:settings.maintenance_mode.view')->only('index');
        $this->middleware('permission:settings.maintenance_mode.manage')
            ->only(['enable', 'disable', 'update', 'rotate']);
    }

    public function index()
    {
        $setting = $this->maintenance->current();

        return view('sitting::maintenance_mode.index', [
            'setting' => $setting,
            'allowedIds' => $setting->allowedIds(),
            'users' => $this->selectableUsers(),
            'bypassUrl' => $this->maintenance->bypassUrl(),
        ]);
    }

    /** تفعيل الوضع. */
    public function enable(Request $request)
    {
        $data = $this->validated($request);

        $setting = $this->maintenance->enable(
            $data['allowed_user_ids'],
            $data['message'],
            auth()->id()
        );

        OperationLog::record(
            'updated',
            'MaintenanceMode',
            null,
            'تفعيل وضع الصيانة — المسموح لهم: '.count($setting->allowedIds()).' مستخدماً.'
        );

        return redirect()->route('MaintenanceMode.index')->with(
            'success',
            'فُعِّل وضع الصيانة. لا يدخل النظام الآن إلا '.count($setting->allowedIds()).' مستخدماً، وأنت منهم.'
        );
    }

    /** تعديل القائمة أو الرسالة والوضعُ مفعَّل. */
    public function update(Request $request)
    {
        $data = $this->validated($request);

        $setting = $this->maintenance->updateAllowed(
            $data['allowed_user_ids'],
            $data['message'],
            auth()->id()
        );

        OperationLog::record(
            'updated',
            'MaintenanceMode',
            null,
            'تعديل قائمة وضع الصيانة — '.count($setting->allowedIds()).' مستخدماً.'
        );

        return back()->with('success', 'حُفظت قائمة المسموح لهم والرسالة.');
    }

    /** إيقاف الوضع — عودة النظام إلى الجميع. */
    public function disable()
    {
        $this->maintenance->disable();

        OperationLog::record('updated', 'MaintenanceMode', null, 'إيقاف وضع الصيانة.');

        return redirect()->route('MaintenanceMode.index')
            ->with('success', 'أُوقف وضع الصيانة — النظام مفتوح لكل المستخدمين.');
    }

    /**
     * توليد مفتاح طوارئ جديد.
     *
     * يُبطل كلَّ رابطٍ قديم وكلَّ جلسةٍ دخلت به — وهو المقصود: المفتاح يُبدَّل
     * حين يُشكّ في أنه تسرّب، ورابطٌ مسرَّب يبقى صالحاً ما لم يُبدَّل.
     */
    public function rotate()
    {
        $this->maintenance->rotateSecret();

        OperationLog::record('updated', 'MaintenanceMode', null, 'توليد مفتاح طوارئ جديد لوضع الصيانة.');

        return back()->with('success', 'وُلِّد مفتاح طوارئ جديد — الرابط القديم لم يعُد يعمل.');
    }

    /**
     * التحقّق المشترك بين التفعيل والتعديل.
     *
     * `exists` على `users` شرطٌ لا زينة: القائمة هي كلُّ ما يفصل بين نظامٍ
     * يُفتَح ونظامٍ لا يُفتَح، ومعرّفٌ لمستخدمٍ محذوف فيها هو اسمٌ لن يدخل أبداً.
     *
     * و`allowed_user_ids` مطلوبةٌ بمستخدمٍ واحد على الأقلّ: قائمةٌ فارغة تعني
     * إقفالاً لا يملك أحدٌ فتحه — و`enable()` تضيف المفعِّل قسراً، لكنّ الاعتماد
     * على ذلك وحده يجعل الشاشة تقبل ظاهراً ما لا تفعله باطناً.
     *
     * @return array{allowed_user_ids: array<int, int>, message: string|null}
     */
    private function validated(Request $request): array
    {
        /*
            القيمة الفارغة تُنزَع قبل التحقّق.

            القالب يسبق مجموعة الخانات بـ`<input type="hidden" name="...[]" value="">`
            — وهو لازم: نموذجُ HTML لا يرسل مفتاحاً لمجموعةٍ لم يُعلَّم منها شيء،
            فتصل القائمة **غائبةً** لا فارغة، و`required` تقول حينها «الحقل مطلوب»
            بينما المستخدم أرسل بالفعل قراره بإفراغها.

            لكنّ ذلك السطر يُدخل نصّاً فارغاً في أوّل المصفوفة، وقاعدة `integer`
            على العناصر تسقط عليه. فيُنزَع هنا: يصل الغياب فارغاً، ويبقى المعنى.
        */
        $request->merge([
            'allowed_user_ids' => array_values(array_filter(
                (array) $request->input('allowed_user_ids', []),
                fn ($id) => $id !== null && $id !== ''
            )),
        ]);

        $data = $request->validate([
            'allowed_user_ids' => ['required', 'array', 'min:1'],
            'allowed_user_ids.*' => ['integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'allowed_user_ids.required' => 'اختر مستخدماً واحداً على الأقلّ يستطيع الدخول أثناء الصيانة.',
            'allowed_user_ids.min' => 'اختر مستخدماً واحداً على الأقلّ يستطيع الدخول أثناء الصيانة.',
        ]);

        return [
            'allowed_user_ids' => $data['allowed_user_ids'],
            'message' => $data['message'] ?? null,
        ];
    }

    /**
     * المستخدمون المعروضون للاختيار — موظّفو النظام لا حسابات العملاء.
     *
     * حساب العميل مستخدمٌ في هذا الجدول أيضاً (بوّابة العملاء تسجّل دخولها به،
     * ويميّزه `client_id`). وعرضُه هنا يوحي بأن عميلاً يمكن أن يُسمح له بالعمل
     * أثناء الصيانة — وهو لا يملك شاشةً واحدة يعمل فيها أصلاً.
     */
    private function selectableUsers()
    {
        return User::query()
            ->whereNull('client_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id']);
    }
}
