<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret',
        'abilities',
        'last_used_at',
        'last_used_ip',
        'usage_count',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** الرمز السري لا يخرج مع النموذج أبداً — يُعرض مرة واحدة عند الإنشاء */
    protected $hidden = ['secret'];

    /**
     * الصلاحيات المتاحة للمفتاح.
     *
     * قراءة فقط عمداً: فتح الكتابة عبر واجهة يُعطى مفتاحها لطرف خارجي
     * قرار يخصّ صاحب النظام، لا افتراض يُتخذ نيابةً عنه.
     */
    public const ABILITIES = [
        'clients.read' => 'قراءة بيانات العملاء',
        'invoices.read' => 'قراءة الفواتير',
        'products.read' => 'قراءة الأصناف',
        'reports.read' => 'قراءة الملخّصات',
    ];

    /**
     * الصلاحيات بأسمائها المترجَمة.
     *
     * `ABILITIES` ثابتٌ لا يقبل نداء `__()`، فبقيت قيمه نصوصاً عربية ثابتة
     * تُستعمل مفاتيحَ احتياطية إن نقصت الترجمة. هذه الدالة هي التي تُعرَض
     * للمستخدم، والثابت يبقى مرجع المفاتيح المقبولة في التحقّق.
     */
    public static function abilities(): array
    {
        $labels = [];

        foreach (self::ABILITIES as $ability => $fallback) {
            $key = 'settings.api.ability_labels.'.str_replace('.', '_', $ability);
            $label = __($key);
            $labels[$ability] = $label === $key ? $fallback : $label;
        }

        return $labels;
    }

    /**
     * العلاقة مع المستخدم
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * إنشاء مفتاح جديد.
     *
     * الرمز السري يُخزَّن مُجزَّأً ولا يمكن استرجاعه — يُعرض للمستخدم مرة
     * واحدة عند الإنشاء، كما هو المتّبع في كل واجهة يُعتدّ بها.
     *
     * @param  array<int, string>  $abilities
     */
    public static function generate($userId, $name, array $abilities = [], ?string $expiresAt = null)
    {
        $rawSecret = Str::random(48);

        $apiKey = self::create([
            'user_id' => $userId,
            'name' => $name,
            'key' => self::generateUniqueKey(),
            'secret' => hash('sha256', $rawSecret),
            'abilities' => array_values(array_intersect($abilities, array_keys(self::ABILITIES))),
            'expires_at' => $expiresAt,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // يُلحق بالكائن مؤقتاً ليعرضه المتحكّم مرة واحدة، ولا يُخزَّن
        $apiKey->raw_secret = $rawSecret;

        return $apiKey;
    }

    /** هل يملك المفتاح هذه الصلاحية؟ */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?: [];

        // مفتاح بلا صلاحيات محدّدة لا يفتح شيئاً — الافتراض الآمن
        return in_array($ability, $abilities, true) || in_array('*', $abilities, true);
    }

    /** أسماء الصلاحيات بالعربية للعرض */
    public function abilityLabels(): array
    {
        $labels = self::abilities();

        return array_map(
            fn ($ability) => $labels[$ability] ?? $ability,
            $this->abilities ?: []
        );
    }

    /**
     * توليد مفتاح فريد
     */
    private static function generateUniqueKey()
    {
        do {
            $key = 'fawt_'.Str::random(40);
        } while (self::where('key', $key)->exists());

        return $key;
    }

    /**
     * تسجيل استخدام المفتاح.
     *
     * يُكتب بلا لمس `updated_at` وبعبارة واحدة: يقع مع كل طلب واجهة،
     * فأي تكلفة إضافية هنا تتضاعف بعدد الطلبات.
     */
    public function markAsUsed(?string $ip = null): void
    {
        static::withoutTimestamps(function () use ($ip) {
            $this->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $ip,
                'usage_count' => ($this->usage_count ?? 0) + 1,
            ])->saveQuietly();
        });
    }

    /**
     * التحقق من صلاحية المفتاح
     */
    public function isValid()
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * الحصول على مفاتيح المستخدم النشطة
     */
    public static function getUserKeys($userId)
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
