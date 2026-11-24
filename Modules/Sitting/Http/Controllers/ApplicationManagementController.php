<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\Log as ModelsLog;
use App\Services\Settings\ApplicationRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Sitting\Http\Requests\ApplicationSettingsRequest;

/**
 * تفعيل وتعطيل وحدات النظام.
 *
 * المفاتيح لم تعد مكتوبة هنا: `ApplicationRegistry` هو مصدرها الوحيد،
 * يقرأ منه هذا المتحكّم والقالب والوسيط معاً. إضافة وحدة صارت سطراً في
 * السجلّ لا تعديلاً في ثلاثة ملفّات.
 */
class ApplicationManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        $statuses = ApplicationRegistry::statuses();
        $permits = ApplicationRegistry::permissions();

        /*
            الوحدات تنقسم ثلاثةً لا اثنين، والأقسام لا تتداخل:

              • خارج الباقة  — لا تعمل ولا يملك أحدٌ هنا فتحها.
              • مشمولة ومفعَّلة — تعمل.
              • مشمولة ومعطَّلة — يفتحها المسؤول بنقرة.

            وعدّ المفعَّل من مفتاح الشاشة وحده كان يكذب: وحدةٌ مفتاحُها
            مرفوع وباقتُها لا تشملها تُعدّ «مفعَّلة» وهي لا تُفتح.
        */
        $locked = array_keys(array_filter($permits, fn ($permitted) => ! $permitted));

        $activeCount = count(array_filter(
            $statuses,
            fn ($status, $key) => $status === ApplicationRegistry::ACTIVE && ($permits[$key] ?? true),
            ARRAY_FILTER_USE_BOTH
        ));

        return view('sitting::Application.index', [
            'groups' => ApplicationRegistry::groups(),
            'statuses' => $statuses,
            'permits' => $permits,
            'activeCount' => $activeCount,
            'lockedCount' => count($locked),
            'offCount' => count(ApplicationRegistry::keys()) - $activeCount - count($locked),
            'totalCount' => count(ApplicationRegistry::keys()),
        ]);
    }

    public function update(ApplicationSettingsRequest $request)
    {
        /*
            الوحدات خارج الباقة تُستبعَد من الحفظ استبعاداً — لا تُكتب
            `inactive` ولا `active`.

            **ولماذا لا تُكتب `inactive`؟** لأن قرار المسؤول ليس ملكاً
            لحالة الاشتراك: من رقّى باقته بعد شهر يجد وحداته كما تركها لا
            مطفأةً جميعاً بيدٍ لم تُطفئها. ومفتاحُها في الشاشة معطَّلٌ مغطّى
            أصلاً، فما يصل منها هنا إمّا حقلٌ مخفيّ رافقه مربّعٌ معطَّل
            (والمعطَّل لا يُرسَل) أو طلبٌ مصنوع باليد — وكلاهما يُتجاهل.
        */
        $statuses = array_filter(
            $request->statuses(),
            fn ($key) => ApplicationRegistry::isPermitted($key),
            ARRAY_FILTER_USE_KEY
        );

        $before = ApplicationRegistry::statuses();

        // ما تغيّر فعلاً — يُحسَب قبل الكتابة ليُذكر في السجلّ
        $changed = [];

        foreach ($statuses as $key => $status) {
            if (($before[$key] ?? ApplicationRegistry::ACTIVE) !== $status) {
                $changed[$key] = $status;
            }
        }

        DB::transaction(function () use ($statuses) {
            foreach ($statuses as $key => $status) {
                ApplicationSetting::updateOrCreate(['key' => $key], ['status' => $status]);
            }
        });

        ApplicationRegistry::flush();

        // السجلّ يُكتب **بعد** الحفظ لا قبله: كان يُكتب أولاً، فإن فشل
        // الحفظ بقي في السجلّ أن التغيير جرى وهو لم يجرِ
        if ($changed !== []) {
            ModelsLog::create([
                'type' => 'setting',
                'type_log' => 'log',
                'description' => __('settings.messages.apps_log', ['summary' => $this->describe($changed)]),
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('Application.index')->with(
            'success',
            $changed === []
                ? __('settings.messages.apps_unchanged')
                : __('settings.messages.apps_saved', ['count' => count($changed)])
        );
    }

    /**
     * وصف ما تغيّر بالعربية — «فُعِّلت س، عُطِّلت ص».
     *
     * @param  array<string, string>  $changed
     */
    protected function describe(array $changed): string
    {
        $on = [];
        $off = [];

        foreach ($changed as $key => $status) {
            $label = ApplicationRegistry::labelFor($key);
            $status === ApplicationRegistry::ACTIVE ? $on[] = $label : $off[] = $label;
        }

        $parts = [];

        if ($on !== []) {
            $parts[] = __('settings.messages.apps_enabled', ['list' => implode(__('settings.common.list_separator'), $on)]);
        }

        if ($off !== []) {
            $parts[] = __('settings.messages.apps_disabled', ['list' => implode(__('settings.common.list_separator'), $off)]);
        }

        return implode(' — ', $parts);
    }
}
