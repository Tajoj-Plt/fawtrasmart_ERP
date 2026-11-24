<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات النسخ الاحتياطي — صفّ واحد لكل النظام.
 *
 * تُقرأ في كل شاشة تعرض حالة النسخ الاحتياطي، فهي محفوظة في الذاكرة
 * المؤقتة وتُسقَط عند أي حفظ.
 */
class BackupSetting extends Model
{
    use HasTranslatedLabels;

    protected $table = 'backup_settings';

    protected $fillable = [
        'is_enabled', 'backup_type', 'table_mode', 'included_tables', 'excluded_tables',
        'disk', 'establishment_name', 'establishment_folder',
        'encrypt_archives', 'archive_password', 'included_paths', 'excluded_paths',
        'frequency', 'cron_expression', 'run_time', 'run_day',
        'retention_strategy', 'retention_count', 'retention_days', 'max_storage_mb',
        'notify_on_success', 'notify_on_failure', 'notify_on_cleanup_failure',
        'notify_on_storage_full', 'notification_recipients', 'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'encrypt_archives' => 'boolean',
        // معشّى بـ`APP_KEY`: العمود يعيش في قاعدة بيانات تُنسَخ احتياطياً،
        // وكلمة صريحة فيه تعني أرشيفاً معشّى وكلمته مرفقة معه
        'archive_password' => 'encrypted',
        'included_tables' => 'array',
        'excluded_tables' => 'array',
        'included_paths' => 'array',
        'excluded_paths' => 'array',
        'notification_recipients' => 'array',
        'notify_on_success' => 'boolean',
        'notify_on_failure' => 'boolean',
        'notify_on_cleanup_failure' => 'boolean',
        'notify_on_storage_full' => 'boolean',
        'retention_count' => 'integer',
        'retention_days' => 'integer',
        'max_storage_mb' => 'integer',
        'run_day' => 'integer',
    ];

    protected const CACHE_KEY = 'backup_settings.current';

    /** المجلَّد المستعمل قبل وجود هذه الميزة — تبقى نسخه القديمة فيه */
    public const LEGACY_FOLDER = 'erp-backups';

    /**
     * مجلَّد النسخ على القرص.
     *
     * يعود إلى المجلَّد القديم ما لم تُضبط منشأة، فلا تختفي نسخ نظامٍ
     * قائم لمجرّد تحديث الشفرة.
     */
    public function archiveFolder(): string
    {
        return $this->establishment_folder ?: self::LEGACY_FOLDER;
    }

    /**
     * كل المجلَّدات التي قد تحوي نسخاً لهذا النظام.
     *
     * المطابقة مع القرص والتنظيف يمسحان هذه كلّها: نسخةٌ أُخذت قبل ضبط
     * المنشأة ما زالت على القرص، وتجاهلها يعني ملفاً لا يعرفه أحد يستهلك
     * المساحة إلى الأبد.
     *
     * @return array<int, string>
     */
    public function archiveFolders(): array
    {
        return array_values(array_unique([$this->archiveFolder(), self::LEGACY_FOLDER]));
    }

    /**
     * يثبّت مجلَّد المنشأة عند أوّل ضبط ولا يغيّره بعدها.
     *
     * تغييرُه لاحقاً يترك النسخ السابقة في مجلَّد لا يقرأه النظام — وهي
     * سليمة على القرص لكنها مفقودة من الواجهة. الاسم المعروض حرٌّ يتغيّر
     * كما يشاء صاحبه؛ المجلَّد وحده هو الثابت.
     */
    public function rememberEstablishmentFolder(?string $name): void
    {
        if (filled($this->establishment_folder) || blank($name)) {
            return;
        }

        // `Str::slug` يُفرغ الاسم العربيّ بالكامل (لا يبقي حرفاً لاتينياً
        // واحداً)، فيصير المجلَّد فارغاً. لذا يُستعمل الاسم بعد تنقيته من
        // فواصل المسارات فقط — أسماء المجلَّدات تقبل الحروف العربية.
        $this->establishment_folder = $this->folderNameFrom($name);
    }

    /**
     * اسم مجلَّد آمن من اسم المنشأة.
     *
     * يُمنع كل ما يخرج من المجلَّد أو يكسر المسار: الفواصل، النقطتان
     * المتتاليتان، ومحارف ويندوز المحجوزة.
     */
    public function folderNameFrom(string $name): string
    {
        $clean = preg_replace('#[\\\\/:*?"<>|]+#u', '-', trim($name)) ?? '';
        $clean = preg_replace('/\.{2,}/u', '-', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '', " .-");

        return $clean === '' ? self::LEGACY_FOLDER : mb_substr($clean, 0, 80);
    }

    public const TYPES = [
        'database' => ['label' => 'قاعدة البيانات فقط', 'icon' => 'database', 'hint' => 'الأسرع والأصغر — يكفي لاسترجاع كل البيانات'],
        'files' => ['label' => 'الملفات فقط', 'icon' => 'folder', 'hint' => 'المرفقات والصور المرفوعة دون البيانات'],
        'both' => ['label' => 'قاعدة البيانات + الملفات', 'icon' => 'hard-drive', 'hint' => 'نسخة كاملة — الأكبر حجماً والأطول وقتاً'],
    ];

    /**
     * أنماط اختيار الجداول.
     *
     * `exclude` هو الخيار الآمن للاستعمال اليومي: نسخة كاملة ناقصة جداول
     * الحركات الضخمة تبقى قابلة للاسترجاع الكامل. أمّا `include` فينتج
     * نسخة جزئية لا تصلح لإعادة بناء النظام وحدها.
     */
    public const TABLE_MODES = [
        'all' => [
            'label' => 'كل الجداول',
            'hint' => 'نسخة كاملة — الخيار الوحيد الذي يعيد بناء النظام وحده.',
        ],
        'exclude' => [
            'label' => 'كل الجداول عدا المحدَّدة',
            'hint' => 'لاستبعاد جداول السجلّات الضخمة وإبقاء النسخة قابلة للاسترجاع الكامل.',
        ],
        'include' => [
            'label' => 'الجداول المحدَّدة فقط',
            'hint' => 'نسخة جزئية — لا تصلح وحدها لإعادة بناء النظام.',
        ],
    ];

    public const FREQUENCIES = [
        'manual' => ['label' => 'يدوي فقط', 'expression' => null],
        'hourly' => ['label' => 'كل ساعة', 'expression' => '{m} * * * *'],
        'daily' => ['label' => 'يومياً', 'expression' => '{m} {H} * * *'],
        'weekly' => ['label' => 'أسبوعياً', 'expression' => '{m} {H} * * {d}'],
        'monthly' => ['label' => 'شهرياً', 'expression' => '{m} {H} {d} * *'],
        'custom' => ['label' => 'تعبير Cron مخصّص', 'expression' => null],
    ];

    public const RETENTION_STRATEGIES = [
        'count' => ['label' => 'بعدد النسخ', 'hint' => 'يُحتفَظ بآخر عدد محدَّد من النسخ ويُحذف ما قبلها'],
        'days' => ['label' => 'بعمر النسخة', 'hint' => 'تُحذف كل نسخة تجاوز عمرها المدّة المحدَّدة'],
    ];

    /**
     * القوائم الأربع بتسمياتها وتلميحاتها المترجَمة.
     *
     * الثوابت أعلاه تحمل النصّ العربي احتياطاً لا غير — الشاشات تقرأ من
     * هذه الدوالّ، ومفتاح الترجمة يبني اسمه من مفتاح العنصر نفسه.
     */
    public static function types(): array
    {
        return self::translateList(self::TYPES, 'backup.types.', ['icon']);
    }

    public static function tableModes(): array
    {
        return self::translateList(self::TABLE_MODES, 'backup.table_modes.');
    }

    public static function frequencies(): array
    {
        return self::translateList(self::FREQUENCIES, 'backup.frequencies.', ['expression'], false);
    }

    public static function retentionStrategies(): array
    {
        return self::translateList(self::RETENTION_STRATEGIES, 'backup.retention_modes.');
    }

    /**
     * @param  array<string, array<string, mixed>>  $list
     * @param  array<int, string>  $keep  مفاتيح تُنقَل كما هي (أيقونة، تعبير cron)
     */
    private static function translateList(array $list, string $prefix, array $keep = [], bool $hints = true): array
    {
        $out = [];

        foreach ($list as $key => $meta) {
            $row = ['label' => self::tr($prefix.$key, $meta['label'])];

            if ($hints && isset($meta['hint'])) {
                $row['hint'] = self::tr($prefix.$key.'_hint', $meta['hint']);
            }

            foreach ($keep as $extra) {
                $row[$extra] = $meta[$extra] ?? null;
            }

            $out[$key] = $row;
        }

        return $out;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * الإعدادات الحالية، وتُنشَأ بالقيم الافتراضية إن لم تكن موجودة.
     *
     * لا تُخزَّن نسخة null في الذاكرة: الجدول قد لا يكون مُهجَّراً بعد في
     * بيئة اختبار، وحينها يجب أن نعيد كائناً غير محفوظ لا أن ننهار.
     */
    public static function current(): self
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof self) {
            return $cached;
        }

        try {
            $settings = static::query()->first() ?: static::create([]);
        } catch (\Throwable) {
            return new static;
        }

        Cache::put(self::CACHE_KEY, $settings, 3600);

        return $settings;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function typeLabel(): string
    {
        return isset(self::TYPES[$this->backup_type])
            ? self::tr('backup.types.'.$this->backup_type, self::TYPES[$this->backup_type]['label'])
            : $this->backup_type;
    }

    public function frequencyLabel(): string
    {
        return isset(self::FREQUENCIES[$this->frequency])
            ? self::tr('backup.frequencies.'.$this->frequency, self::FREQUENCIES[$this->frequency]['label'])
            : $this->frequency;
    }

    /** وصف سياسة الاحتفاظ بجملة واحدة تُعرَض في لوحة المعلومات. */
    public function retentionLabel(): string
    {
        return $this->retention_strategy === 'days'
            ? __('settings.backup.retention_days_label', ['days' => $this->retention_days])
            : __('settings.backup.retention_count_label', ['count' => $this->retention_count]);
    }

    public function tableModeLabel(): string
    {
        return isset(self::TABLE_MODES[$this->table_mode])
            ? self::tr('backup.table_modes.'.$this->table_mode, self::TABLE_MODES[$this->table_mode]['label'])
            : self::tr('backup.table_modes.all', 'كل الجداول');
    }

    /** هل تنتج هذه السياسة نسخة ناقصة جداول؟ */
    public function selectsTables(): bool
    {
        return in_array($this->table_mode, ['include', 'exclude'], true);
    }

    /** وصف اختيار الجداول بجملة واحدة للوحة المعلومات. */
    public function tableSelectionLabel(): string
    {
        return match ($this->table_mode) {
            'include' => __('settings.backup.scope_include', ['count' => count($this->included_tables ?? [])]),
            'exclude' => __('settings.backup.scope_exclude', ['count' => count($this->excluded_tables ?? [])]),
            default => self::tr('backup.table_modes.all', 'كل الجداول'),
        };
    }

    // ==================== التعشية ====================

    /**
     * كلمة تعشية الأرشيف السارية، أو null إن كانت التعشية مطفأة.
     *
     * `.env` يبقى بديلاً احتياطياً: منشآت ضبطت `BACKUP_ARCHIVE_PASSWORD`
     * قبل وجود هذه الشاشة يجب أن تستمرّ نسخها معشّاة كما كانت، وأرشيفاتها
     * القديمة أن تبقى قابلة للفكّ.
     */
    public function archivePassword(): ?string
    {
        if ($this->encrypt_archives && $this->archive_password) {
            return (string) $this->archive_password;
        }

        return null;
    }

    /**
     * هل ستُعشّى النسخة القادمة فعلاً؟
     *
     * ليس `encrypt_archives` وحده: مفتاح مرفوع بلا كلمة محفوظة لا يُنتج
     * تعشية، وقولُ الشاشة «معشّاة» حينها وعدٌ كاذب بأمانٍ غير قائم.
     */
    public function encryptionActive(): bool
    {
        return $this->archivePassword() !== null || ! empty(config('backup.backup.password'));
    }

    public function includesDatabase(): bool
    {
        return in_array($this->backup_type, ['database', 'both'], true);
    }

    public function includesFiles(): bool
    {
        return in_array($this->backup_type, ['files', 'both'], true);
    }
}
