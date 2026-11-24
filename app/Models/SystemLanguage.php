<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemLanguage extends Model
{
    protected $table = 'system_languages';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'locale',
        'flag',
        'translation_file',
        'translation_files',
        'direction',
        'is_active',
        'is_default',
        'sort_order',
        'date_format',
        'time_format',
        'number_format',
        'timezone',
        'calendar_type',
        'thousands_separator',
        'decimal_separator',
        'font_family',
        'font_size',
        'color',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'translation_files' => 'array',
    ];

    /** مفاتيح كاش اللغات — يُفرّغها أي كتابة على الجدول مهما كان مصدرها. */
    public const CACHE_KEYS = [
        'active_system_languages',
        'default_system_language',
        'active_system_language_codes',
        'system_languages_supported_locales',
    ];

    protected static function booted(): void
    {
        // المستودع يفرّغ الكاش، لكن الـ Seeders والأوامر تكتب على الموديل مباشرة
        $flush = function () {
            foreach (self::CACHE_KEYS as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /* ==================== Scopes ==================== */

    /** اللغات المفعّلة فقط */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** ترتيب العرض المعتمد في كل الشاشات */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /** بحث حر على الاسم والاسم الأصلي والرمز والمحلية */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
                ->orWhere('native_name', 'LIKE', "%{$term}%")
                ->orWhere('code', 'LIKE', "%{$term}%")
                ->orWhere('locale', 'LIKE', "%{$term}%");
        });
    }

    /* ==================== Helpers ==================== */

    /** اللغة الافتراضية للنظام (قد تكون null قبل تشغيل الـ Seeder) */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * كل ملفات الترجمة المرتبطة باللغة.
     *
     * العمود الجديد translation_files يحمل القائمة كاملة، والقديم
     * translation_file يحمل ملفاً واحداً — تُدمج الاثنتان حتى تبقى
     * السجلات المحفوظة قبل دعم تعدّد الملفات ظاهرة كما هي.
     *
     * @return array<int, string>
     */
    public function translationFiles(): array
    {
        $files = is_array($this->translation_files) ? $this->translation_files : [];

        if (filled($this->translation_file)) {
            array_unshift($files, $this->translation_file);
        }

        return array_values(array_unique(array_filter($files)));
    }

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /** المحلية الفعلية: عمود locale إن وُجد وإلا الرمز */
    public function getEffectiveLocaleAttribute(): string
    {
        return $this->locale ?: $this->code;
    }

    /**
     * هل قيمة العلم اسم ملف صورة (sa.svg) أم رمز Emoji؟
     * الشاشات تعرض الاثنين، والتمييز بوجود امتداد.
     *
     * يُشترط وجود الملف فعلاً في public/flags: بدون ذلك كان الجدول
     * يعرض أيقونة صورة مكسورة بدل بديل مقروء.
     */
    public function flagIsImage(): bool
    {
        if (blank($this->flag) || ! preg_match('/\.(svg|png|jpe?g|gif|webp)$/i', $this->flag)) {
            return false;
        }

        // اسم الملف فقط: القيمة تُركّب في مسار، فلا يُقبل فيها اجتياز مجلدات
        $file = basename((string) $this->flag);

        return $file === $this->flag && is_file(public_path('flags/'.$file));
    }

    /**
     * قيمة العلم رمز نصّي (Emoji) يُعرض كما هو.
     * اسم ملف صورة مفقود ليس نصاً يُعرض — يسقط إلى بديل الرمز.
     */
    public function flagIsText(): bool
    {
        return filled($this->flag) && ! preg_match('/\.(svg|png|jpe?g|gif|webp)$/i', $this->flag);
    }

    /**
     * رمز الدولة لمكتبة flag-icon-css المستعملة في الترويسة — مثل «sa» من «sa.svg».
     *
     * مستقلّ عن flagIsImage() لأن تلك تشترط وجود ملف في public/flags،
     * بينما هذه تعتمد على أيقونات المكتبة ولا تحتاج ملفاً.
     * وإن لم يُذكر علم يُشتقّ من الرمز حين يكون رمز دولة محتملاً.
     */
    public function flagIconCode(): ?string
    {
        if (filled($this->flag) && preg_match('/^([a-z]{2})\.(svg|png|jpe?g|gif|webp)$/i', basename((string) $this->flag), $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
