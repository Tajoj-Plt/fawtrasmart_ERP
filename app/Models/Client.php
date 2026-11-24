<?php

namespace App\Models;

use App\Models\Store\Cart;
use App\Models\Store\ClientAddress;
use App\Models\Store\ClientDevice;
use App\Models\Store\ClientPaymentCard;
use App\Models\Store\OnlineOrder;
use App\Models\Store\ProductReview;
use App\Models\Store\RecentlyViewedProduct;
use App\Models\Store\Wishlist;
use App\Support\AffiliationTree;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    /**
     * مرحلة تسجيل العميل — قائمةٌ قابلة للتعديل من الإعدادات (`statuses`
     * بقسم `registration_stage`)، لا حالة ثالثة موازية لنشط/موقوف، ولا
     * تؤثّر على حالة العميل التشغيلية. انظر
     * docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3.
     */
    public function registrationStage()
    {
        return $this->belongsTo(Statuses::class, 'registration_stage_id');
    }

    /**
     * اسم العميل المعروض.
     *
     * لا عمود `name` في `clients` — الاسم التجاري هو الأصل ويقع عليه
     * أكثرُ العملاء، ومن سُجِّل باسمٍ شخصيّ يبقى `trade_name` فارغاً عنده
     * والاسمان في `first_name` و`last_name`. وكتابة `$client->name`
     * تعود `null` بلا خطأ، فتخرج الشاشة باسمٍ فارغ لا بعطلٍ يُرى.
     */
    public function getDisplayNameAttribute(): string
    {
        if (filled($this->trade_name)) {
            return $this->trade_name;
        }

        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ('#'.$this->id);
    }

    use HasApiTokens;

    /** معرّف العميل الافتراضي في النظام — fallback للعمليات بلا عميل محدد */
    public const DEFAULT_CLIENT_ID = 1;

    /**
     * معرّف «العميل النقدي» — من يشتري نقداً ولا يُفتح له ملفّ.
     *
     * كان حسابُه المحاسبي موجوداً وحده (`SystemAccountIds::CASH_CLIENT` في
     * شجرة الحسابات) بلا عميلٍ يقابله في `clients`، فنقاطُ البيع تكتب
     * «عميل نقدي» نصّاً في الشاشة وتحفظ الفاتورة بلا `client_id`. فلا كشفَ
     * حسابٍ له، ولا تقريرَ مبيعاتٍ يعدّه، ولا نقاطَ ولاء.
     */
    public const CASH_CLIENT_ID = 2;

    protected $table = 'clients';

    protected $dates = [
        'last_note_at',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'birth_date',
    ];

    protected $casts = [
        'force_show' => 'boolean',
        'is_store_client' => 'boolean',
        'exempt_visit_location' => 'boolean',
        'exempt_exchange_transaction' => 'boolean',
        'birth_date' => 'date',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'exempt_visit_location' => false,
        'exempt_exchange_transaction' => false,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $primaryKey = 'id';

    public $timestamps = true;

    // الحقول القابلة للتعبئة
    protected $fillable = [
        'trade_name',
        'category_id',
        'force_show',
        'last_note_at',
        'first_name',
        'last_name',
        'phone',
        'mobile',
        'cat',
        'street1',
        'street2',
        'building_number',
        'category',
        'city',
        'region',
        'visit_type',
        'postal_code',
        'country',
        'tax_number',
        'commercial_registration',
        'credit_limit',
        'credit_period',
        'printing_method',
        'code',
        'currency',
        'email',
        'client_type',
        'notes',
        'attachments',
        'employee_id',
        'status_id',
        'registration_stage_id',
        'branch_id',
        'tree_node_id',
        'is_store_client',
        // حقول المصادقة والتحقق
        'password',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'deleted_reason',
        // حقول الملف الشخصي للمتجر
        'birth_date',
        'gender',
        'avatar',
        // حقول الإحالة
        'referred_by',
        'referral_code',
        'daily_exchange_limit', // الحد اليومي للتبديل الخاص بالعميل
        'item_quantity_limit', // الحد الأقصى للكمية المسموح بها لكل بند في الفاتورة
        'customer_target_id',
        'target_evaluated_at',
    ];

    // العلاقة مع المواعيد
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // في ملف Client.php
    public function latestStatus()
    {
        return $this->hasOne(ClientRelation::class, 'client_id')->latest();
    }

    public function latestRelation()
    {
        return $this->hasOne(ClientRelation::class, 'client_id')->latestOfMany();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function Neighborhoodname()
    {
        return $this->hasOne(Neighborhood::class, 'client_id');
    }

    public function Balance()
    {
        return Account::ledger()->where('client_id', $this->id)->sum('balance');
    }

    public function employeeNotes()
    {
        return $this->hasMany(ClientEmployeeNote::class, 'client_id');
    }

    /**
     * رابطُ صورة العميل المخزَّنة في `avatar` — أو `null` حين لا صورة.
     *
     * الصورةُ تُرفع من ملف العميل بأكثر من طريق، فيُخزَّن `avatar` بأشكالٍ
     * مختلفة: مسارُ قرص `public` (بوابة المتجر: `clients/avatars/x.jpg`)، أو
     * اسمُ ملفٍّ من الجوال تحت `public/uploads/avatars/`، أو مسارٌ نسبيٌّ تحت
     * `public/`، أو رابطٌ كامل. هذه القراءةُ الواحدة تحلّها جميعاً فلا تختلف
     * الشاشات في بناء الرابط.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $avatar = $this->avatar;

        if (! $avatar) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($avatar, ['http://', 'https://', '//'])) {
            return $avatar;
        }

        // مسارُ قرص public (بوابة المتجر / البروفايل)
        try {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($avatar)) {
                return \Illuminate\Support\Facades\Storage::disk('public')->url($avatar);
            }
        } catch (\Throwable $e) {
            // قرصٌ غير مهيّأ — تُجرَّب البدائل
        }

        // ملفٌّ تحت public/ مباشرةً (مسارٌ نسبيّ محفوظ)
        if (file_exists(public_path($avatar))) {
            return asset($avatar);
        }

        // اسمُ ملفٍّ من الجوال تحت uploads/avatars/
        if (file_exists(public_path('uploads/avatars/'.$avatar))) {
            return asset('uploads/avatars/'.$avatar);
        }

        // آخر محاولة: رابطُ قرص public كما هو
        try {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($avatar);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFullAddressAttribute()
    {
        $address = [];
        if ($this->street1) {
            $address[] = $this->street1;
        }
        if ($this->street2) {
            $address[] = $this->street2;
        }
        if ($this->city) {
            $address[] = $this->city;
        }
        if ($this->region) {
            $address[] = $this->region;
        }
        if ($this->postal_code) {
            $address[] = $this->postal_code;
        }
        if ($this->country) {
            $address[] = $this->country;
        }

        return implode(', ', $address);
    }

    // العلاقة مع ملاحظات المواعيد
    public function appointmentNotes()
    {
        return $this->hasMany(ClientRelation::class, 'client_id');
    }

    // العلاقات
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'client_id', 'id');
    }

    public function cheques()
    {
        return $this->hasMany(ChequesCycle::class, 'client_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    // العلاقة مع ملاحظات الموظفين

    // العلاقة مع CreditNotification
    public function creditNotifications()
    {
        return $this->hasMany(CreditNotification::class, 'client_id');
    }

    // العلاقة مع JournalEntry
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'client_id', 'id');
    }

    public function account()
    {
        // يرجع أول حساب مرتبط بالعميل (client_id)
        return $this->hasOne(Account::class, 'client_id');
    }

    /**
     * حسابُ ذمّة العميل في الأستاذ — لا محفظتُه.
     *
     * `account()` يرجع **أوّلَ** حسابٍ يحمل `client_id`، والمحفظةُ تحمله
     * أيضاً: عميلٌ أُنشئت محفظتُه قبل ذمّته يُقرأ رصيدُ محفظته (التزامٌ علينا)
     * ديناً عليه. لوحتا العملاء والمبيعات تقرآن `Account::ledger()`، فتقاريرُ
     * المديونية تقرأ هذه العلاقةَ كي لا تفترق عنهما.
     */
    public function ledgerAccount()
    {
        return $this->hasOne(Account::class, 'client_id')->ledger()->orderBy('id');
    }

    /**
     * العلاقة مع أهداف العملاء
     */
    public function customerTarget()
    {
        return $this->belongsTo(CustomerTarget::class, 'customer_target_id');
    }

    /**
     * العلاقة مع الحد الائتماني
     */
    public function creditLimit()
    {
        return $this->hasOne(CreditLimit::class, 'client_id');
    }

    // Accessors
    public function getBalanceAttribute()
    {
        $invoicesTotal = $this->invoices()->sum('grand_total') ?? 0;
        $paymentsTotal = $this->payments()->sum('amount') ?? 0;

        return $invoicesTotal - $paymentsTotal;
    }

    public function getTotalInvoicesAttribute()
    {
        return $this->invoices()->sum('grand_total') ?? 0;
    }

    public function getTotalPaymentsAttribute()
    {
        return $this->payments()->sum('amount') ?? 0;
    }

    /**
     * **ليست** حالة العميل التشغيلية (نشط/موقوف) رغم الاسم — هذه فقط
     * `deleted_at === null`. بقيت باسمها القديم لأن كودَ استدعاءٍ حيّاً
     * (`show_contant.blade.php`) كان يعتمدها بهذا المعنى تحديداً؛ ونظراً
     * لوجود عمودٍ حقيقي باسم `status` على `clients` (enum قديم، لا يُقرأ
     * فعلياً في أي مكان)، حذفُ هذا الـaccessor كان سيُسرّب قيمة ذلك العمود
     * الميت بدل الحالة الحقيقية — أسوأ من إبقائه موثَّقاً بوضوح.
     *
     * **حالة العميل الحقيقية** (نشط/موقوف) تُقرأ من `status_id` عبر
     * `status_client()` أدناه، أو `Statuses::activeClientStatusIds()` عند
     * فحص "أنشطٌ هذا العميل؟" فقط بلا حاجة للسجلّ كاملاً.
     */
    public function getStatusAttribute()
    {
        return $this->deleted_at === null;
    }

    public function notes()
    {
        return $this->hasMany(AppointmentNote::class);
    }

    // دالة لجلب حركة الحساب
    public function getTransactionsAttribute()
    {
        $transactions = collect();

        // إضافة الفواتير
        $this->invoices->each(function ($invoice) use ($transactions) {
            $transactions->push([
                'date' => $invoice->invoice_date,
                'type' => 'فاتورة',
                'number' => $invoice->invoice_number,
                'amount' => $invoice->grand_total,
                'balance' => 0, // سيتم حسابه لاحقاً
                'notes' => $invoice->notes,
            ]);
        });

        // إضافة المدفوعات
        $this->payments->each(function ($payment) use ($transactions) {
            $transactions->push([
                'date' => $payment->payment_date,
                'type' => 'دفعة',
                'number' => $payment->payment_number,
                'amount' => -$payment->amount, // سالب لأنها دفعة
                'balance' => 0, // سيتم حسابه لاحقاً
                'notes' => $payment->notes,
            ]);
        });

        // ترتيب المعاملات حسب التاريخ
        $transactions = $transactions->sortBy('date');

        // حساب الرصيد التراكمي (يبدأ من صفر لأن الرصيد الافتتاحي الآن في جدول accounts)
        $balance = 0;
        $transactions->transform(function ($transaction) use (&$balance) {
            $balance += $transaction['amount'];
            $transaction['balance'] = $balance;

            return $transaction;
        });

        return $transactions;
    }

    // Boot method to handle cascading deletes
    protected static function boot()
    {
        parent::boot();

        /* عميلٌ يُنشأ من مسارٍ لا يعرف الشجرة — تسجيلُ المتجر، نقاطُ البيع،
           `mang_client_store` — يرسل `branch_id` وحدَه. فبلا هذا يبقى موقعُه
           التنظيميّ فارغاً فلا يظهر في الفهرس ولا تلتقطه فلترةُ العقدة.
           والفرعُ موقعٌ تنظيميٌّ صالح، فيُتّخذ افتراضاً حتى يُنقل يدوياً. */
        static::saving(function ($client) {
            if (empty($client->tree_node_id) && ! empty($client->branch_id)) {
                $client->tree_node_id = (int) $client->branch_id;
            }
        });

        static::deleting(function ($client) {
            // حذف الفواتير المرتبطة
            $client->invoices()->delete();

            // حذف السندات المرتبطة
            // $client->receipts()->delete();

            // حذف المدفوعات المرتبطة
            $client->payments()->delete();

            // حذف الشيكات المرتبطة

            // حذف إشعارات الائتمان المرتبطة
            $client->creditNotifications()->delete();

            // حذف مدخلات المجلة المرتبطة
            $client->journalEntries()->delete();
        });
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function categoriesClient()
    {
        return $this->belongsTo(CategoriesClient::class, 'category_id'); // وضح المفتاح الأجنبي
    }

    public function employees()
    {
        return $this->belongsToMany(
            Employee::class, // النموذج المرتبط
            'client_employee', // اسم الجدول الوسيط
            'client_id', // المفتاح الأجنبي للنموذج الحالي
            'employee_id', // المفتاح الأجنبي للنموذج المرتبط
        )->withTimestamps(); // إضافة طوابع زمنية
    }

    public function payments()
    {
        return $this->hasManyThrough(PaymentsProcess::class, Invoice::class);
    }

    // في Client model
    public function clientEmployees()
    {
        return $this->hasMany(ClientEmployee::class);
    }

    public function locations()
    {
        // صفوفُ الملاحظات تحمل موقعَ الموظف لا العميل — {@see Location::scopeClientPoint()}
        return $this->hasOne(Location::class, 'client_id')
            ->whereNull('client_relation_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->latest();
    }

    public function neighborhood()
    {
        return $this->hasOne(Neighborhood::class, 'client_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'client_id');
    }

    public function companyAssets()
    {
        return $this->hasMany(ClientCompanyAsset::class, 'client_id');
    }

    public function account_client()
    {
        return $this->hasOne(Account::class, 'client_id'); // مو belongsTo
    }

    /**
     * حالة العميل الحقيقية (نشط/موقوف/إلخ) — العلاقة الوحيدة المعتمَدة لهذا
     * الغرض. كان هناك `status()` مكرِّرةٌ لها بالضبط (نفس `belongsTo` بنفس
     * المفتاح المستنتَج تلقائياً)، حُذفت: لا نداءٍ في الكود استعملها، وبقاؤها
     * كان يعني طريقتَين لقراءة نفس العلاقة بلا فرقٍ بينهما سوى الاسم.
     */
    public function status_client()
    {
        return $this->belongsTo(Statuses::class, 'status_id');
    }

    public function fullLocation(): Attribute
    {
        return Attribute::get(function () {
            $location = [];

            if (! empty($this->street1)) {
                $location['street1'] = $this->street1;
            }

            if (! empty($this->street2)) {
                $location['street2'] = $this->street2;
            }

            if (! empty($this->country)) {
                $location['country'] = $this->country;
            }

            if (! empty($this->city)) {
                $location['city'] = $this->city;
            }

            if (! empty($this->region)) {
                $location['region'] = $this->region;
            }

            if (! empty($this->postal_code)) {
                $location['postal_code'] = $this->postal_code;
            }

            if ($this->relationLoaded('neighborhood') && $this->neighborhood) {
                $location['neighborhood'] = $this->neighborhood->name;
            } elseif (! empty($this->neighborhood_id)) {
                $location['neighborhood'] = Neighborhood::find($this->neighborhood_id)?->name;
            }

            if ($this->relationLoaded('locations') && $this->locations) {
                $location['latitude'] = $this->locations->latitude;
                $location['longitude'] = $this->locations->longitude;
                $location['map_url'] = $this->locations->map_url;
            }

            $formattedAddress = [];
            if (! empty($location['street1'])) {
                $formattedAddress[] = $location['street1'];
            }
            if (! empty($location['street2'])) {
                $formattedAddress[] = $location['street2'];
            }
            if (! empty($location['neighborhood'])) {
                $formattedAddress[] = $location['neighborhood'];
            }
            if (! empty($location['city'])) {
                $formattedAddress[] = $location['city'];
            }
            if (! empty($location['region'])) {
                $formattedAddress[] = $location['region'];
            }
            if (! empty($location['postal_code'])) {
                $formattedAddress[] = $location['postal_code'];
            }
            if (! empty($location['country'])) {
                $formattedAddress[] = $location['country'];
            }

            $location['formatted_address'] = implode('، ', $formattedAddress);

            return $location;
        });
    }

    public function formattedAddress(): Attribute
    {
        return Attribute::get(function () {
            return $this->full_location['formatted_address'] ?? '';
        });
    }

    public function group()
    {
        return $this->belongsTo(Region_groub::class, 'group_id');
    }

    /**
     * العقدةُ التنظيمية التي يقع فيها العميل — منطقة أو فرع أو مجموعة أو
     * منفذ، بأيّ عمقٍ في شجرة `branches`. هي ما يختاره المستخدم وما تُبنى
     * عليه الفلترةُ والفهرس والتقارير التنظيمية.
     *
     * ولا تُخلط بـ `branch()`: ذاك الفرعُ المحاسبيّ المشتقّ منها
     * (`Branch::resolveAccountingBranchId()`)، تُنسب إليه الفواتيرُ والقيود
     * ولا يختاره أحد.
     */
    public function treeNode()
    {
        return $this->belongsTo(Branch::class, 'tree_node_id');
    }

    /** العقدةُ التنظيمية، مع الرجوع إلى الفرع للعملاء السابقين للعمود. */
    public function organizationalNode(): ?Branch
    {
        $id = $this->tree_node_id ?: $this->branch_id;

        return $id ? Branch::findCached($id) : null;
    }

    /**
     * وصفُ موقع العميل في الشجرة، جاهزاً للعرض.
     *
     * المرتكزُ هو `tree_node_id` — العقدةُ التي اختارها المستخدم فعلاً. وكان
     * قبلَه يُستنتج من `neighborhood.region_id` ثمّ `group_id` ثمّ `branch_id`،
     * ثمّ يُستبدل بـ`deepestDescendantOrSelf()`: أعمقُ ورقةٍ تحت تلك العقدة.
     * فيقرأ المستخدمُ في الفهرس اسمَ فرعٍ لم يخترْه أحد — تخمينٌ من الشجرة لا
     * بيانٌ عن العميل. فحُذف التخمين، والاستنتاجُ القديم بقي fallback للعملاء
     * السابقين للعمود وحدهم.
     *
     * @return array{main: ?string, node: ?Branch, type: ?string, type_label: ?string}
     */
    public function branchTreeDisplayPaths(): array
    {
        $node = $this->resolveTreeNode();

        return [
            'main' => $node?->lineagePathLabel(),
            'node' => $node,
            'type' => $node?->nodeTypeKey(),
            'type_label' => $node?->displayTypeLabel(),
        ];
    }

    /**
     * عملاءُ عقدةٍ في الشجرة — موضعٌ واحدٌ لكل شاشةٍ تفلتر بالفرع.
     *
     * `$includeDescendants` هو الفرق بين «فرع الرياض» و«فرع الرياض وما تحته»:
     * ما دام العملاءُ يقعون على مستوياتٍ مختلفة، فالمطابقةُ التامّة تُخفي عميلَ
     * المنفذ عمّن يفلتر بالفرع الذي فوقه. والافتراضُ شامل، والشاشةُ تعرض
     * مفتاحَ «شامل التوابع» لمن أراد المطابقة التامّة.
     *
     * ويُطابَق `tree_node_id` و`branch_id` معاً: الأوّلُ الموقعُ التنظيمي،
     * والثاني الفرعُ المحاسبي — ففلترةُ فرعٍ محاسبيّ تعرض من يُقيَّد عليه ولو
     * كان تنظيمياً تحت منطقةٍ لا فرع. ومعهما الحيُّ والمجموعةُ لعملاءَ سبقوا
     * العمود ولم يُرحَّلوا.
     */
    public function scopeInTreeNode($query, $nodeId, bool $includeDescendants = true)
    {
        $nodeId = (int) $nodeId;
        $node = $nodeId ? Branch::findCached($nodeId) : null;

        if (! $node) {
            return $query->whereRaw('0 = 1');
        }

        $ids = $includeDescendants
            ? $node->descendantBranchIds()->push($nodeId)->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [$nodeId];

        $hasGroupId = Schema::hasColumn($this->getTable(), 'group_id');

        /* المطابقةُ نفسُها في `App\Support\AffiliationTree` — تستدعيها
           تقاريرُ المبيعات مباشرةً على مجموعةِ معرّفاتٍ موسَّعةٍ سلفاً، فلو
           بقي التعريفُ هنا وحدَه لانقسم إلى نسختين تختلفان بمرور الوقت. */
        AffiliationTree::constrainClients($query, $ids, $hasGroupId);

        return $query;
    }

    /**
     * العقدةُ التنظيمية للعميل: العمودُ الصريح أوّلاً، ثمّ استنتاجُ ما قبله.
     */
    public function resolveTreeNode(): ?Branch
    {
        if ($this->tree_node_id) {
            return Branch::findCached($this->tree_node_id);
        }

        // عملاءُ ما قبل عمود `tree_node_id` — نفسُ ترتيب الاستنتاج السابق
        $this->loadMissing(['neighborhood', 'branch']);

        if ($this->neighborhood?->region_id) {
            if ($legacy = Branch::findCached($this->neighborhood->region_id)) {
                return $legacy;
            }
        }

        if (Schema::hasColumn($this->getTable(), 'group_id') && $this->group_id) {
            if ($legacy = Branch::findCached($this->group_id)) {
                return $legacy;
            }
        }

        return $this->branch_id ? Branch::findCached($this->branch_id) : null;
    }

    public function accounts()
    {
        return $this->hasMany(Account::class, 'client_id');
    }

    public function clientRelations()
    {
        return $this->hasMany(ClientRelation::class);
    }

    // Relationship for bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    // Relationship for product exchanges
    public function productExchanges()
    {
        return $this->hasMany(ProductExchange::class, 'client_id');
    }

    // ==================== علاقات المتجر الإلكتروني ====================

    /**
     * العلاقة مع عناوين التوصيل
     * العميل لديه عدة عناوين توصيل
     */
    public function addresses()
    {
        return $this->hasMany(ClientAddress::class);
    }

    /**
     * العنوان الافتراضي للتوصيل
     */
    public function defaultAddress()
    {
        return $this->hasOne(ClientAddress::class)->where('is_default', true);
    }

    /**
     * العلاقة مع سلة التسوق
     * العميل لديه سلة تسوق واحدة نشطة
     */
    public function cart()
    {
        return $this->hasOne(Cart::class)->active();
    }

    /**
     * العلاقة مع قائمة الأمنيات
     * العميل لديه عدة منتجات في قائمة الأمنيات
     */
    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * العلاقة مع تقييمات المنتجات
     * العميل قام بعدة تقييمات
     */
    public function productReviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * العلاقة مع الطلبات الإلكترونية
     * العميل قام بعدة طلبات إلكترونية
     */
    public function onlineOrders()
    {
        return $this->hasMany(OnlineOrder::class);
    }

    /**
     * العلاقة مع المنتجات المشاهدة مؤخراً
     */
    public function recentlyViewedProducts()
    {
        return $this->hasMany(RecentlyViewedProduct::class);
    }

    /**
     * العلاقة مع أجهزة العميل
     * لإرسال الإشعارات
     */
    public function devices()
    {
        return $this->hasMany(ClientDevice::class);
    }

    /**
     * العلاقة مع بطاقات الدفع المحفوظة
     */
    public function paymentCards()
    {
        return $this->hasMany(ClientPaymentCard::class);
    }

    /**
     * البطاقة الافتراضية للدفع
     */
    public function defaultPaymentCard()
    {
        return $this->hasOne(ClientPaymentCard::class)->where('is_default', true);
    }

    // ==================== دوال مساعدة للمتجر الإلكتروني ====================

    /**
     * عدد عناصر قائمة الأمنيات
     */
    public function getWishlistCountAttribute(): int
    {
        return $this->wishlistItems()->count();
    }

    /**
     * عدد عناصر سلة التسوق
     */
    public function getCartItemsCountAttribute(): int
    {
        return $this->cart ? $this->cart->total_items : 0;
    }

    /**
     * عدد الطلبات المكتملة
     */
    public function getCompletedOrdersCountAttribute(): int
    {
        return $this->onlineOrders()->where('status', 'completed')->count();
    }

    /**
     * إجمالي قيمة الطلبات
     */
    public function getTotalOrdersValueAttribute(): float
    {
        return $this->onlineOrders()->sum('total_amount');
    }

    /**
     * متوسط تقييم العميل للمنتجات
     */
    public function getAverageReviewRatingAttribute(): float
    {
        return $this->productReviews()->avg('rating') ?? 0;
    }

    /**
     * التحقق من وجود عنوان افتراضي
     */
    public function hasDefaultAddress(): bool
    {
        return $this->defaultAddress !== null;
    }

    /**
     * الحصول على أو إنشاء سلة تسوق
     */
    public function getOrCreateCart()
    {
        return $this->cart ?: Cart::create(['client_id' => $this->id]);
    }

    public function invoiceTransfers()
    {
        return $this->hasMany(InvoiceTransfer::class, 'client_id');
    }

    // ==================== علاقات نظام النقاط ====================

    /**
     * العلاقة مع معاملات النقاط
     */
    public function pointsTransactions()
    {
        return $this->hasMany(\App\Models\Ecosystem\PointsTransaction::class, 'client_id');
    }

    /**
     * العلاقة مع العميل الذي أحال هذا العميل
     */
    public function referrer()
    {
        return $this->belongsTo(Client::class, 'referred_by');
    }

    /**
     * العلاقة مع العملاء المحالين من هذا العميل
     */
    public function referrals()
    {
        return $this->hasMany(Client::class, 'referred_by');
    }

    /**
     * توليد كود إحالة فريد للعميل
     */
    public function generateReferralCode(): string
    {
        if (! $this->referral_code) {
            $this->referral_code = strtoupper(substr(md5($this->id.time()), 0, 8));
            $this->save();
        }

        return $this->referral_code;
    }

    /**
     * هل يُلزم الموظف بملاحظة زيارة (GPS + صورة) قبل فتح إجراءات العميل؟
     * يُستثنى عند تفعيل «استبعاد التحقق من الموقع» في بيانات العميل.
     */
    public function requiresVisitNote(): bool
    {
        return ! $this->exempt_visit_location;
    }

    public function isExemptFromVisitLocation(): bool
    {
        return (bool) $this->exempt_visit_location;
    }

    /**
     * هل يُستثنى من شرط الفاتورة/السند/الإشعار للتبديل في يوم خط السير؟
     */
    public function allowsExchangeWithoutTransaction(): bool
    {
        return (bool) $this->exempt_exchange_transaction;
    }
}
