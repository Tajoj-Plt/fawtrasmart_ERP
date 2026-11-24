<?php

namespace Modules\Sitting\Http\Requests;

use App\Models\CronJob;
use App\Services\Cron\CronRegistry;
use App\Services\Cron\CronScheduleParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * التحقّق من مدخلات المهمة المجدولة.
 *
 * أهمّ قاعدة هنا: `handler` يُطابَق على `CronRegistry` — لا يصل إلى قاعدة
 * البيانات اسم أمر لم نضعه نحن في القائمة. التحقّق يتكرّر في
 * `CronJobService` لأن المتحكّم ليس المدخل الوحيد للخدمة.
 */
class CronJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم عبر middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'handler' => [
                'required',
                'string',
                // القائمة البيضاء هي قاعدة التحقّق نفسها — لا قائمة موازية.
                // والمخفيّ مستثنى: يُنفَّذ ولا يُنشأ من الشاشة
                Rule::in(CronRegistry::selectableHandlers()),
            ],
            'schedule_type' => ['required', Rule::in(array_keys(CronJob::SCHEDULE_TYPES))],
            'schedule_time' => ['nullable', 'date_format:H:i'],
            'schedule_day' => ['nullable', 'integer', 'min:0', 'max:31'],
            'cron_expression' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(CronJob::STATUSES))],
            'timeout' => ['required', 'integer', 'min:10', 'max:7200'],
            'prevent_overlap' => ['nullable', 'boolean'],
            'notify_on_failure' => ['nullable', 'boolean'],
            'notify_on_success' => ['nullable', 'boolean'],
            'parameters' => ['nullable', 'array'],
        ];
    }

    /**
     * تحقّق مركّب لا تعبّر عنه القواعد المفردة.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('schedule_type');
            $meta = CronJob::SCHEDULE_TYPES[$type] ?? null;

            if ($meta === null) {
                return;
            }

            // ---- التعبير المخصّص ----
            if ($type === CronJob::SCHEDULE_CRON) {
                $expression = trim((string) $this->input('cron_expression'));

                if ($expression === '') {
                    $validator->errors()->add('cron_expression', __('settings.cron.errors.expression_required'));
                } elseif (! CronScheduleParser::isValid($expression)) {
                    $validator->errors()->add(
                        'cron_expression',
                        __('settings.cron.errors.expression_invalid')
                    );
                }
            }

            // ---- الوقت واليوم حين يتطلّبهما النمط ----
            if ($meta['needs_time'] && ! $this->filled('schedule_time')) {
                $validator->errors()->add('schedule_time', __('settings.cron.errors.time_required'));
            }

            if ($meta['needs_day'] && $this->input('schedule_day') === null) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.day_required'));
            }

            // يوم الأسبوع 0-6 ويوم الشهر 1-31 — الخلط بينهما يعطي جدولة
            // صامتة لا تعمل أبداً بدل خطأ ظاهر
            if ($type === 'weekly' && $this->input('schedule_day') !== null
                && ((int) $this->input('schedule_day') > 6)) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.weekday_range'));
            }

            if ($type === 'monthly' && $this->input('schedule_day') !== null
                && ((int) $this->input('schedule_day') < 1)) {
                $validator->errors()->add('schedule_day', __('settings.cron.errors.monthday_range'));
            }

            // ---- المعاملات ----
            $parameters = $this->input('parameters');

            if (is_array($parameters) && $parameters !== []) {
                $allowed = CronRegistry::allowedOptions($this->input('handler'));

                foreach (array_keys($parameters) as $option) {
                    if (! in_array(ltrim((string) $option, '-'), $allowed, true)) {
                        $validator->errors()->add(
                            'parameters',
                            __('settings.cron.errors.option_not_allowed', ['option' => $option])
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.cron.errors.name_required'),
            'handler.required' => __('settings.cron.errors.handler_required'),
            'handler.in' => __('settings.cron.errors.handler_in'),
            'schedule_type.required' => __('settings.cron.errors.schedule_type_required'),
            'schedule_type.in' => __('settings.cron.errors.schedule_type_in'),
            'schedule_time.date_format' => __('settings.cron.errors.time_format'),
            'timeout.min' => __('settings.cron.errors.timeout_min'),
            'timeout.max' => __('settings.cron.errors.timeout_max'),
        ];
    }
}
