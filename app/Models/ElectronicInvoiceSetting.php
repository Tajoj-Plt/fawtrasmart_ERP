<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * إعدادات الفاتورة الإلكترونية — صفّ واحد للمنشأة.
 *
 * بيانات المنشأة (الاسم التجاري، الرقم الضريبي، السجل التجاري، العنوان،
 * الدولة) **ليست هنا**: مصدرها «إعدادات الحساب» التي تحفظها في سجلّ
 * `Client` المرتبط بالمستخدم. تكرارها كان يعني إدخالها مرّتين ونسختين
 * تتباعدان — يُصحَّح الرقم الضريبي في مكانه فتُرسَل الفاتورة بالقديم.
 *
 * يبقى هنا ما يخصّ الفوترة الإلكترونية وحدها: التفعيل والبيئة وسلوك
 * الإرسال، واسم الفرع/المجموعة الضريبية، وتصنيف العمل، وبيانات الربط.
 *
 * Usage:
 *   ElectronicInvoiceSetting::current();
 *   ElectronicInvoiceSetting::current()->isReady();
 */
class ElectronicInvoiceSetting extends Model
{
    protected $table = 'electronic_invoice_settings';

    public const ENV_SANDBOX = 'sandbox';

    /** بيئة المحاكاة لدى الهيئة: شهادةٌ حقيقية برمز OTP، وفواتيرُ بلا أثر نظامي */
    public const ENV_SIMULATION = 'simulation';

    public const ENV_PRODUCTION = 'production';

    public const ENVIRONMENTS = [self::ENV_SANDBOX, self::ENV_SIMULATION, self::ENV_PRODUCTION];

    /** «الإصدار»: فاتورةٌ إلكترونية برمز QR، بلا اتصالٍ بالهيئة */
    public const PHASE_ONE = 'phase1';

    /** «الربط والتكامل»: توقيعٌ وإرسالٌ إلى الهيئة */
    public const PHASE_TWO = 'phase2';

    public const PHASES = [self::PHASE_ONE, self::PHASE_TWO];

    /** ما يحمله رمز المرحلة الأولى من بيانات المنشأة: اسمها ورقمها الضريبي */
    public const REQUIRED_FOR_PHASE_ONE = [
        'trade_name' => 'trade_name',
        'tax_number' => 'tax_number',
    ];

    /** تصنيفات البند بلا ضريبة كما تعرّفها الهيئة */
    public const ZERO_TAX_CATEGORIES = ['Z', 'E', 'O'];

    /**
     * حقول المنشأة المطلوبة للإرسال — تُقرأ من سجلّ المنشأة لا من هنا.
     *
     * المفتاح اسم العمود في `clients`، والقيمة مفتاح الترجمة.
     */
    public const REQUIRED_FROM_ESTABLISHMENT = [
        'trade_name' => 'trade_name',
        'tax_number' => 'tax_number',
        'country' => 'country',
        // الهيئة تشترط معرّف البائع وعنوانه مفصَّلاً
        'commercial_registration' => 'commercial_register',
        'street1' => 'street',
        'city' => 'city',
        'postal_code' => 'postal_code',
    ];

    /** حقول هذا الجدول التي لا يصحّ الإرسال بدونها. */
    public const REQUIRED_LOCAL = ['tax_group_name', 'business_category', 'building_number', 'district'];

    protected $fillable = [
        'is_enabled',
        'phase',
        'environment',
        'auto_send_after_create',
        'send_on_payment',
        'attach_qr_to_print',
        'common_name',
        'tax_group_name',
        'business_category',
        'api_base_url',
        'client_id',
        'client_secret',
        'building_number',
        'district',
        'invoice_types',
        'zero_tax_category',
        'zero_tax_reason_code',
        'zero_tax_reason',
        'updated_by',
    ];

    /**
     * المفتاح والشهادات والأسرار لا تُعاد إلى أي شاشة ولا إلى JSON.
     *
     * وليست في `$fillable` عمداً: تكتبها خدمة التسجيل وحدها، فلا يبلغها
     * طلبٌ مُصاغ يدوياً إلى شاشة الإعدادات.
     */
    protected $hidden = [
        'client_id', 'client_secret', 'private_key',
        'compliance_certificate', 'compliance_secret',
        'production_certificate', 'production_secret',
    ];

    /**
     * القيم الافتراضية على الكائن لا على القاعدة وحدها.
     *
     * `firstOrCreate([])` يُدرِج صفّاً تملؤه القاعدة بقيمها الافتراضية، لكن
     * الكائن العائد لا يحملها — فتعود `environment` فارغةً عند أوّل زيارة،
     * و`__('…env.'.null)` تطبع مفتاحاً بدل اسم البيئة.
     */
    protected $attributes = [
        'is_enabled' => false,
        'phase' => self::PHASE_ONE,
        'environment' => self::ENV_SANDBOX,
        'auto_send_after_create' => false,
        'send_on_payment' => false,
        'attach_qr_to_print' => true,
        'invoice_types' => '1100',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'auto_send_after_create' => 'boolean',
            'send_on_payment' => 'boolean',
            'attach_qr_to_print' => 'boolean',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'private_key' => 'encrypted',
            'compliance_certificate' => 'encrypted',
            'compliance_secret' => 'encrypted',
            'production_certificate' => 'encrypted',
            'production_secret' => 'encrypted',
            'certificate_expires_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /** الصفّ الوحيد، يُنشأ بالقيم الافتراضية عند أوّل طلب. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * سجلّ المنشأة — نفس السجلّ الذي تكتبه شاشة «إعدادات الحساب».
     *
     * يُحلّ مرّة واحدة لكلّ كائن: الشاشة تسأل عنه في بطاقة الحالة وفي
     * الجدول وفي حساب النواقص، وثلاثة استعلامات لصفٍّ واحد سرف.
     */
    public function establishment(): ?Client
    {
        if (! array_key_exists('establishment', $this->relations)) {
            /* صاحب «إعدادات الحساب» هو المرجع لا من يفتح الشاشة: الإرسال
               المجدول يعمل بلا مستخدم، والمندوب الذي يرسل فاتورته ليس له
               سجلّ منشأة — فكان الإرسال يخرج بلا اسمٍ ولا رقمٍ ضريبي.
               الترتيب نفسه في `DocumentTemplateDataService::getBusinessData()`. */
            $owner = (Auth::check() ? AccountSetting::where('user_id', Auth::id())->first() : null)
                ?? AccountSetting::query()->orderBy('id')->first();

            $ownerId = $owner->user_id ?? Auth::id();

            $this->relations['establishment'] = $ownerId
                ? Client::where('user_id', $ownerId)->first()
                : null;
        }

        return $this->relations['establishment'];
    }

    /** قيمة حقل من سجلّ المنشأة. */
    public function fromEstablishment(string $field): ?string
    {
        $value = $this->establishment()?->{$field};

        return filled($value) ? (string) $value : null;
    }

    /** العنوان مجمَّعاً من أجزائه كما تحفظها شاشة إعدادات الحساب. */
    public function establishmentAddress(): ?string
    {
        $parts = array_filter([
            $this->fromEstablishment('street1'),
            $this->fromEstablishment('street2'),
            $this->fromEstablishment('city'),
            $this->fromEstablishment('region'),
            $this->fromEstablishment('postal_code'),
        ]);

        return $parts ? implode('، ', $parts) : null;
    }

    /**
     * الحقول الناقصة، مفصولةً بمصدرها.
     *
     * الفصل مقصود: ما ينقص في المنشأة يُصلَح في شاشة إعدادات الحساب لا
     * هنا، فتوجيه المستخدم إلى الشاشة الصحيحة جزء من الرسالة.
     *
     * @return array{establishment: list<string>, local: list<string>}
     */
    public function missingFields(): array
    {
        $establishment = [];

        /* المطلوب بحسب المرحلة: الأولى لا تُرسِل شيئاً، فلا عنوانٌ مفصَّل
           ولا سجلٌّ تجاري ولا بيانات شهادة — رمزها اسمٌ ورقمٌ ضريبي. ومطالبة
           منشأةٍ في المرحلة الأولى بما تطلبه الثانية تُبقي خدمتها «ناقصة»
           بلا سبيلٍ إلى إكمالها. */
        $required = $this->isPhaseTwo() ? self::REQUIRED_FROM_ESTABLISHMENT : self::REQUIRED_FOR_PHASE_ONE;

        foreach ($required as $column => $key) {
            if (blank($this->fromEstablishment($column))) {
                $establishment[] = $key;
            }
        }

        if (! $this->isPhaseTwo()) {
            return ['establishment' => $establishment, 'local' => []];
        }

        if (blank($this->establishmentAddress())) {
            $establishment[] = 'address';
        }

        $local = array_values(array_filter(
            self::REQUIRED_LOCAL,
            fn (string $field) => blank($this->{$field})
        ));

        return ['establishment' => $establishment, 'local' => $local];
    }

    /** مكتملة البيانات، بصرف النظر عن التفعيل. */
    public function isComplete(): bool
    {
        $missing = $this->missingFields();

        return $missing['establishment'] === [] && $missing['local'] === [];
    }

    /** مفعَّلة ومكتملة — أي أن الإرسال ممكن فعلاً. */
    public function isReady(): bool
    {
        return $this->is_enabled && $this->isComplete();
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    public function isPhaseTwo(): bool
    {
        return $this->phase === self::PHASE_TWO;
    }

    /**
     * مسجَّلة لدى الهيئة في البيئة المفعَّلة — أي أن لها شهادة إنتاج صالحة.
     *
     * الشهادة تخصّ بيئتها: شهادة المنصّة التجريبية لا تُرسِل إلى البوابة
     * الفعلية، فالتحويل بين البيئات يستلزم تسجيلاً جديداً.
     */
    public function hasCredentials(): bool
    {
        return filled($this->private_key)
            && filled($this->production_certificate)
            && filled($this->production_secret)
            && $this->credentials_environment === $this->environment;
    }

    /** مفعَّلة ومكتملة ومسجَّلة — الإرسال إلى الهيئة ممكن الآن. */
    public function canSubmit(): bool
    {
        return $this->isPhaseTwo() && $this->isReady() && $this->hasCredentials() && ! $this->certificateExpired();
    }

    public function certificateExpired(): bool
    {
        return $this->certificate_expires_at !== null && $this->certificate_expires_at->isPast();
    }

    /** أتُصدر المنشأة هذا النوع؟ الخانة الأولى ضريبية والثانية مبسّطة. */
    public function issues(string $profile): bool
    {
        $types = str_pad((string) $this->invoice_types, 4, '0');

        return $profile === 'standard' ? $types[0] === '1' : $types[1] === '1';
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
