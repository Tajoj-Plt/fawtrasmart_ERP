<?php

namespace Modules\Sitting\Http\Requests;

use App\Models\BackupSetting;
use App\Models\CronJob;
use App\Services\Backup\DataSectionCatalog;
use App\Services\Cron\CronScheduleParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * التحقّق من جدول نسخ احتياطي واحد.
 *
 * الجدول هنا صفٌّ في `cron_jobs` مُعالِجه `backup:scheduled-run`، لكن
 * الشاشة تسأل بلغة النسخ الاحتياطي: ماذا يُنسَخ ومتى ومن أي الأقسام —
 * لا «اسم أمر» و«خيارات». الترجمة إلى معاملات الأمر تحدث في المتحكّم،
 * والتحقّق هنا يفحص ما رآه المستخدم لا ما سيُبنى منه.
 *
 * `scope_mode` = settings يعني «اتّبع إعدادات النسخ العامّة»، وهو ما
 * يفعله الأمر حين لا يُمرَّر إليه خيار نطاق أصلاً.
 */
class BackupScheduleRequest extends FormRequest
{
    /** أنماط الجدولة المعروضة — التعبير الحرّ منها للحالات النادرة. */
    public const SCOPE_MODES = ['settings', 'all', 'include', 'exclude'];

    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'skip_cleanup' => $this->boolean('skip_cleanup'),
            'notify_on_failure' => $this->boolean('notify_on_failure'),
            'notify_on_success' => $this->boolean('notify_on_success'),
            'schedule_day' => $this->filled('schedule_day') ? $this->input('schedule_day') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],

            // «حسب الإعدادات» قيمة صالحة لا غياباً: المسؤول اختارها صراحةً
            'backup_type' => ['required', Rule::in(array_merge(['settings'], array_keys(BackupSetting::TYPES)))],
            'scope_mode' => ['required', Rule::in(self::SCOPE_MODES)],
            'sections' => ['array', 'max:100'],
            'sections.*' => ['string', 'max:64'],
            'skip_cleanup' => ['boolean'],

            'schedule_type' => ['required', Rule::in(array_keys(CronJob::SCHEDULE_TYPES))],
            'schedule_time' => ['nullable', 'date_format:H:i'],
            'schedule_day' => ['nullable', 'integer', 'min:0', 'max:31'],
            'cron_expression' => ['nullable', 'string', 'max:120'],

            'status' => ['required', Rule::in(array_keys(CronJob::STATUSES))],
            'timeout' => ['required', 'integer', 'min:60', 'max:7200'],
            'notify_on_failure' => ['boolean'],
            'notify_on_success' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('schedule_type');
            $meta = CronJob::SCHEDULE_TYPES[$type] ?? null;

            if ($meta === null) {
                return;
            }

            if ($type === CronJob::SCHEDULE_CRON) {
                $expression = trim((string) $this->input('cron_expression'));

                if ($expression === '') {
                    $validator->errors()->add('cron_expression', __('settings.cron.errors.expression_required'));
                } elseif (! CronScheduleParser::isValid($expression)) {
                    $validator->errors()->add('cron_expression', __('settings.cron.errors.expression_invalid'));
                }
            }

            if ($meta['needs_time'] && ! $this->filled('schedule_time')) {
                $validator->errors()->add('schedule_time', __('settings.cron.errors.time_required'));
            }

            if ($meta['needs_day'] && $this->input('schedule_day') === null) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.day_required'));
            }

            if ($type === 'weekly' && (int) $this->input('schedule_day') > 6) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.weekday_range'));
            }

            if ($type === 'monthly' && $this->input('schedule_day') !== null
                && (int) $this->input('schedule_day') < 1) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.monthday_range'));
            }

            // ---- النطاق ----
            // اختيار فارغ مع نمط يتطلّب أقساماً يسقط في الأمر إلى «اتّبع
            // الإعدادات» صامتاً — فينتج جدول يظنّه المسؤول جزئياً وهو ليس كذلك
            if (in_array($this->input('scope_mode'), ['include', 'exclude'], true)) {
                $tables = DataSectionCatalog::tablesForKeys((array) $this->input('sections', []));

                if ($tables === []) {
                    $validator->errors()->add('sections', __('settings.backup.pick_one_section'));
                }
            }

            // نسخة مرفقات وحدها لا أقسام بيانات فيها — الجمع بينهما إعداد
            // متناقض يُنتج جدولاً لا يفعل ما تقوله شاشته
            if ($this->input('backup_type') === 'files'
                && in_array($this->input('scope_mode'), ['include', 'exclude'], true)) {
                $validator->errors()->add('scope_mode', __('settings.backup.schedules.errors.files_scope'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => __('settings.cron.name'),
            'backup_type' => __('settings.backup.fields.backup_type'),
            'scope_mode' => __('settings.backup.fields.table_mode'),
            'sections' => __('settings.backup.fields.included_tables'),
            'schedule_time' => __('settings.backup.fields.run_time'),
            'schedule_day' => __('settings.backup.fields.run_day'),
            'cron_expression' => __('settings.backup.fields.cron_expression'),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.cron.errors.name_required'),
            'schedule_time.date_format' => __('settings.cron.errors.time_format'),
            'timeout.min' => __('settings.cron.errors.timeout_min'),
            'timeout.max' => __('settings.cron.errors.timeout_max'),
        ];
    }
}
