<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRule;
use App\Models\SalaryComponentType;
use App\Support\OperationLog;
use App\Support\PayrollSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الأجر الإضافي: تفعيل الاحتساب والمضاعف والحد الأقصى.
 *
 * الصلاحية `overtime.settings`. الخطة هي
 * بنود الراتب — لا مفتاح باقة جديد.
 */
class OvertimeSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:overtime.settings');
    }

    public function edit()
    {
        $settings = PayrollSettings::row();
        $rules = OvertimeRule::query()->orderBy('id')->get()->keyBy('applies_on');

        /*
            اسمُ بند الأجر الإضافي المولَّد في القسيمة — كان يُختار من «كتالوج
            البدلات» المتقاعد. القيمةُ المحفوظة تبقى وتُعرض للقراءة فقط.
        */
        $typeId = $settings->overtime_component_type_id ?? null;
        $itemName = $typeId && Schema::hasTable('salary_component_types')
            ? SalaryComponentType::query()->whereKey($typeId)->value('name')
            : null;

        return view('hr::salaries.sitting.overtime', [
            'overtimeSettings' => $settings ? (array) $settings : [],
            'weekday' => $rules->get(OvertimeRule::APPLIES_WEEKDAY),
            'holiday' => $rules->get(OvertimeRule::APPLIES_HOLIDAY),
            'overtimeItemName' => $itemName ?: 'أجر إضافي',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'overtime_monthly_hours' => 'required|numeric|min:1|max:744',
            'overtime_hourly_rate' => 'nullable|numeric|min:0',
            'overtime_max_minutes_per_period' => 'nullable|integer|min:1|max:100000',
            'weekday_multiplier' => 'required|numeric|min:1|max:5',
            'holiday_multiplier' => 'required|numeric|min:1|max:5',
            'weekday_max_minutes_per_day' => 'nullable|integer|min:1|max:1440',
            'holiday_max_minutes_per_day' => 'nullable|integer|min:1|max:1440',
            'weekday_hourly_rate' => 'nullable|numeric|min:0',
            'holiday_hourly_rate' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->saveSettings($request, $validated);
            /*
                سعرُ الساعة لكلّ قاعدة (`*_hourly_rate`) ليس في الشاشة — فلا
                يُصفَّر عند كلّ حفظ: يُكتب فقط إن أُرسل، وإلّا تبقى قيمتُه.
            */
            $this->saveRule(OvertimeRule::APPLIES_WEEKDAY, 'يوم عمل', [
                'multiplier' => $validated['weekday_multiplier'],
                'max_minutes_per_day' => $validated['weekday_max_minutes_per_day'] ?? null,
            ] + ($request->has('weekday_hourly_rate') ? ['hourly_rate' => $validated['weekday_hourly_rate'] ?? null] : []));
            $this->saveRule(OvertimeRule::APPLIES_HOLIDAY, 'عطلة / راحة أسبوعية', [
                'multiplier' => $validated['holiday_multiplier'],
                'max_minutes_per_day' => $validated['holiday_max_minutes_per_day'] ?? null,
            ] + ($request->has('holiday_hourly_rate') ? ['hourly_rate' => $validated['holiday_hourly_rate'] ?? null] : []));

            return redirect()->back()->with('success', 'تم تحديث إعدادات الأجر الإضافي');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.OvertimeSettings', 'update');

            return redirect()->back()->with('error', 'حدث خطأ أثناء حفظ الإعدادات')->withInput();
        }
    }

    /** @param  array<string, mixed>  $validated */
    private function saveSettings(Request $request, array $validated): void
    {
        PayrollSettings::put([
            'overtime_enabled' => $request->boolean('overtime_enabled'),
            // الاعتمادُ المسبق: لا يُصرف إضافيٌّ بلا طلبٍ معتمَد
            'overtime_requires_approval' => $request->boolean('overtime_requires_approval'),
            'overtime_monthly_hours' => $validated['overtime_monthly_hours'],
            'overtime_hourly_rate' => $validated['overtime_hourly_rate'] ?: null,
            'overtime_max_minutes_per_period' => $validated['overtime_max_minutes_per_period'] ?: null,
            // `overtime_component_type_id` لم يعد يُحرَّر (تقاعد كتالوج البدلات) — يبقى كما هو
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function saveRule(string $appliesOn, string $defaultName, array $values): void
    {
        $attributes = [
            'name' => $defaultName,
            'applies_on' => $appliesOn,
            'multiplier' => $values['multiplier'],
            'max_minutes_per_day' => $values['max_minutes_per_day'] ?: null,
            'is_system' => true,
            'status' => OvertimeRule::STATUS_ACTIVE,
        ];
        if (array_key_exists('hourly_rate', $values)) {
            $attributes['hourly_rate'] = $values['hourly_rate'] ?: null;
        }

        OvertimeRule::query()->updateOrCreate(['code' => $appliesOn], $attributes);
    }
}
