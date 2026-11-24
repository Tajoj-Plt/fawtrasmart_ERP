<?php

namespace Modules\Sitting\Http\Requests;

use App\Models\BackupSetting;
use App\Services\Backup\BackupTableRegistry;
use App\Services\Backup\DataSectionCatalog;
use App\Services\Cron\CronScheduleParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من إعدادات النسخ الاحتياطي.
 *
 * المسارات تُكتب في مربّع نصّ سطراً لكل مسار — أسهل من حقول متعدّدة
 * وأقرب لما يفهمه المسؤول. تحويلها إلى مصفوفة يحدث هنا لا في المتحكّم،
 * فيصل إليه ما هو مطابق لِما تتوقّعه أعمدة الـ JSON في الجدول.
 *
 * الحدّ من نطاق المسار (منعُ الخروج عن جذر المشروع) ليس هنا بل في
 * `BackupService::resolvePaths` — لأنه يجب أن يُطبَّق على الصفوف القديمة
 * أيضاً وقت التشغيل، لا على المدخل الجديد وحده.
 */
class BackupSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'included_paths' => $this->splitLines($this->input('included_paths')),
            'excluded_paths' => $this->splitLines($this->input('excluded_paths')),
            // الشاشة ترسل مفاتيح أقسام معتَّمة لا أسماء جداول. الترجمة هنا
            // لا في المتحكّم لأن قواعد التحقّق أدناه تفحص أسماء الجداول
            // الناتجة — فيبقى الحاجز الأخير على ما يصل إلى أمر النسخ نفسه.
            // مربّعات الاختيار غير المؤشَّرة لا تُرسَل أصلاً، فتصل القيمة
            // null وتسقط في قاعدة `array`
            'included_tables' => DataSectionCatalog::tablesForKeys(
                (array) $this->input('included_sections', [])
            ),
            'excluded_tables' => DataSectionCatalog::tablesForKeys(
                (array) $this->input('excluded_sections', [])
            ),
            'is_enabled' => $this->boolean('is_enabled'),
            'encrypt_archives' => $this->boolean('encrypt_archives'),
            'notify_on_success' => $this->boolean('notify_on_success'),
            'notify_on_failure' => $this->boolean('notify_on_failure'),
            'notify_on_cleanup_failure' => $this->boolean('notify_on_cleanup_failure'),
            'notify_on_storage_full' => $this->boolean('notify_on_storage_full'),
            // حقل فارغ في نموذج HTML يصل نصّاً فارغاً لا null، فيسقط في
            // `integer` بدل أن يُعامَل «بلا حدّ»
            'max_storage_mb' => $this->filled('max_storage_mb') ? $this->input('max_storage_mb') : null,
            'run_day' => $this->filled('run_day') ? $this->input('run_day') : null,
        ]);
    }

    public function rules(): array
    {
        $frequency = $this->input('frequency');
        $needsTime = in_array($frequency, ['hourly', 'daily', 'weekly', 'monthly'], true);

        return [
            'is_enabled' => ['boolean'],

            'backup_type' => ['required', Rule::in(array_keys(BackupSetting::TYPES))],

            'table_mode' => ['required', Rule::in(array_keys(BackupSetting::TABLE_MODES))],
            // `Rule::in` على أسماء الجداول لا على نمط نصّي: أسماء الجداول
            // تصل إلى أمر `mysqldump` المبنيّ نصّاً، فلا يجوز قبول إلا ما
            // هو موجود فعلاً في القاعدة
            'included_tables' => [Rule::requiredIf($this->input('table_mode') === 'include'), 'array', 'max:500'],
            'included_tables.*' => ['string', Rule::in($this->knownTables())],
            'excluded_tables' => [Rule::requiredIf($this->input('table_mode') === 'exclude'), 'array', 'max:500'],
            'excluded_tables.*' => ['string', Rule::in($this->knownTables())],
            // القرص يجب أن يكون معرَّفاً فعلاً، وإلا انهار التشغيل عند أول
            // نسخة برسالة لا تدلّ على مصدرها
            'disk' => ['required', Rule::in(array_keys((array) config('filesystems.disks', [])))],

            // اسم المنشأة يصير اسم مجلَّد على القرص: تُمنع فواصل المسارات
            // والنقطتان المتتاليتان صراحةً، فاسمٌ مثل `../../etc` يكتب خارج
            // وجهة النسخ كلّها
            'establishment_name' => ['nullable', 'string', 'max:80', 'not_regex:#[\\\\/:*?"<>|]#', 'not_regex:#\\.{2,}#'],

            // ---- تعشية الأرشيف ----
            // الحقل فارغاً يعني «أبقِ الكلمة المحفوظة» لا «امحُها» — وإلا
            // فقدَ كل حفظٍ للإعدادات تعشيةَ النسخ القادمة صامتاً. لذلك
            // `required` مشروط بغياب كلمة محفوظة أصلاً
            'archive_password' => [
                Rule::requiredIf(
                    fn () => $this->boolean('encrypt_archives') && ! BackupSetting::current()->archive_password
                ),
                'nullable',
                'string',
                'min:8',
                'max:120',
                'confirmed',
            ],
            'encrypt_archives' => ['boolean'],

            'included_paths' => ['array', 'max:50'],
            'included_paths.*' => ['string', 'max:255'],
            'excluded_paths' => ['array', 'max:50'],
            'excluded_paths.*' => ['string', 'max:255'],

            'frequency' => ['required', Rule::in(array_keys(BackupSetting::FREQUENCIES))],
            'cron_expression' => [
                Rule::requiredIf($frequency === 'custom'),
                'nullable',
                'string',
                'max:120',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && ! CronScheduleParser::isValid($value)) {
                        $fail(__('settings.backup.errors.cron_invalid'));
                    }
                },
            ],
            'run_time' => [Rule::requiredIf($needsTime), 'nullable', 'date_format:H:i'],
            'run_day' => [
                Rule::requiredIf(in_array($frequency, ['weekly', 'monthly'], true)),
                'nullable',
                'integer',
                // الأسبوعي يوم أسبوع (0-6) والشهري يوم شهر (1-31)
                $frequency === 'weekly' ? 'between:0,6' : 'between:1,31',
            ],

            'retention_strategy' => ['required', Rule::in(array_keys(BackupSetting::RETENTION_STRATEGIES))],
            'retention_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_storage_mb' => ['nullable', 'integer', 'min:1', 'max:10000000'],

            'notify_on_success' => ['boolean'],
            'notify_on_failure' => ['boolean'],
            'notify_on_cleanup_failure' => ['boolean'],
            'notify_on_storage_full' => ['boolean'],
        ];
    }

    /**
     * أسماء الجداول الموجودة فعلاً — تُقرأ مرّة واحدة لكل طلب.
     *
     * @return array<int, string>
     */
    protected function knownTables(): array
    {
        return $this->knownTables ??= app(BackupTableRegistry::class)->names();
    }

    /** @var array<int, string>|null */
    protected ?array $knownTables = null;

    public function attributes(): array
    {
        return [
            'backup_type' => __('settings.backup.fields.backup_type'),
            'table_mode' => __('settings.backup.fields.table_mode'),
            'included_tables' => __('settings.backup.fields.included_tables'),
            'excluded_tables' => __('settings.backup.fields.excluded_tables'),
            'disk' => __('settings.backup.fields.disk'),
            'archive_password' => __('settings.backup.fields.archive_password'),
            'frequency' => __('settings.backup.fields.frequency'),
            'cron_expression' => __('settings.backup.fields.cron_expression'),
            'run_time' => __('settings.backup.fields.run_time'),
            'run_day' => __('settings.backup.fields.run_day'),
            'retention_strategy' => __('settings.backup.fields.retention_strategy'),
            'retention_count' => __('settings.backup.fields.retention_count'),
            'retention_days' => __('settings.backup.fields.retention_days'),
            'max_storage_mb' => __('settings.backup.fields.max_storage_mb'),
        ];
    }

    public function messages(): array
    {
        return [
            // `in` غير مترجَم في `resources/lang/ar/validation.php`، فرسالته
            // الافتراضية تخرج إنجليزية داخل شاشة عربية
            'backup_type.in' => __('settings.backup.errors.type_unknown'),
            'disk.in' => __('settings.backup.errors.disk_unknown'),
            'frequency.in' => __('settings.backup.errors.frequency_unknown'),
            'retention_strategy.in' => __('settings.backup.errors.retention_unknown'),
            'table_mode.in' => __('settings.backup.errors.table_mode_unknown'),
            'included_tables.required' => __('settings.backup.errors.included_required'),
            'excluded_tables.required' => __('settings.backup.errors.excluded_required'),
            'included_tables.*.in' => __('settings.backup.errors.table_unknown'),
            'excluded_tables.*.in' => __('settings.backup.errors.excluded_unknown'),
            'run_time.date_format' => __('settings.backup.errors.run_time_format'),
            'archive_password.required' => __('settings.backup.errors.password_required'),
            'archive_password.min' => __('settings.backup.errors.password_min'),
            'archive_password.confirmed' => __('settings.backup.errors.password_confirmed'),
            'run_day.between' => $this->input('frequency') === 'weekly'
                ? __('settings.backup.errors.weekday_required')
                : __('settings.backup.errors.monthday_range'),
            'retention_count.min' => __('settings.backup.errors.retention_count_min'),
            'retention_days.min' => __('settings.backup.errors.retention_days_min'),
        ];
    }

    /**
     * تفكيك مربّع نصّ إلى مصفوفة مسارات، سطراً لكل مسار.
     *
     * @return array<int, string>
     */
    protected function splitLines(mixed $value): array
    {
        if (is_array($value)) {
            $lines = $value;
        } elseif (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        } else {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            $lines
        ), fn ($line) => $line !== ''));
    }
}
