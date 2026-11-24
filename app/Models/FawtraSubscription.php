<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * لقطة الاشتراك المحفوظة من لوحة فوترة — صفٌّ واحد لا أكثر.
 *
 * النظام مشترِكٌ واحد لا مزوّد اشتراكات، فلا معنى لأكثر من صفّ. و`current()`
 * هي المدخل الوحيد إليه.
 */
class FawtraSubscription extends Model
{
    protected $table = 'fawtra_subscription';

    protected $fillable = [
        'is_active', 'is_expired', 'is_trial', 'status', 'end_date', 'days_remaining',
        'auto_renew', 'plan_name', 'activation_serial',
        'snapshot', 'sync_token', 'usage_report_state',
        'last_synced_at', 'last_usage_report_at', 'last_attempt_at', 'last_error_code', 'last_error',
        'consecutive_failures', 'retry_after',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_expired' => 'boolean',
        'is_trial' => 'boolean',
        'auto_renew' => 'boolean',
        'end_date' => 'date',
        'days_remaining' => 'integer',
        'consecutive_failures' => 'integer',
        'snapshot' => 'array',
        'usage_report_state' => 'array',
        'last_synced_at' => 'datetime',
        'last_usage_report_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'retry_after' => 'datetime',
    ];

    /** الصفّ الوحيد، أو null إن لم تُجرَ مزامنة بعد. */
    public static function current(): ?self
    {
        try {
            return static::query()->first();
        } catch (\Throwable) {
            // الجدول غير مُهجَّر بعد — الشاشة تعرض دعوةً للربط لا خطأً
            return null;
        }
    }

    /* ==================== قراءات مشتقّة من اللقطة ==================== */

    /** بيانات الحساب عند فوترة: الاسم والبريد والدومين. */
    public function account(): array
    {
        return $this->snapshot['account'] ?? [];
    }

    /** الباقة: الاسم والنوع والصلاحيات. */
    public function plan(): array
    {
        return $this->snapshot['plan']['plan'] ?? $this->snapshot['plan'] ?? [];
    }

    /**
     * صلاحيات الباقة — ما تسمح به الخطة من شاشات وميزات.
     *
     * @return array<string, bool>
     */
    public function planPermissions(): array
    {
        return $this->plan()['permissions'] ?? [];
    }

    /**
     * الأسماء العربية لصلاحيات الباقة — من اللقطة لا من قائمةٍ عندنا.
     *
     * ترسلها فوترة في `plan.permission_labels`. وما لم تصل (لوحةٌ أقدم من
     * هذه الإضافة) يُستدلّ عليه من أسماء عناصر الاستهلاك التي تشترك معها في
     * المفاتيح، وما بقي بلا اسم يُعرض بمفتاحه — ولا قائمةَ أسماء تُكتب هنا
     * فتتباعد عن الكتالوج مع أول تعديل عندهم.
     *
     * @return array<string, string>
     */
    public function permissionLabels(): array
    {
        $fromDashboard = $this->plan()['permission_labels'] ?? [];

        if (is_array($fromDashboard) && $fromDashboard !== []) {
            return $fromDashboard;
        }

        $derived = [];

        foreach ($this->usage() as $item) {
            if (isset($item['key'], $item['label'])) {
                $derived[$item['key']] = $item['label'];
            }
        }

        return $derived;
    }

    /**
     * حدود الاستهلاك الاسمية — ما تمنحه الباقة زائدَ ما اشتُري من إضافات.
     */
    public function limits(): array
    {
        // `/plan` يُعيد الحدود قائمةً مستقلة، أمّا `/sync` فيُدرجها في عناصر
        // الاستهلاك نفسها (كلٌّ منها يحمل `included` و`addon_quantity`
        // و`allowance`). فتُقرأ المستقلّة إن وُجدت، وإلا اشتُقّت من الاستهلاك —
        // وبلا هذا الاشتقاق تخرج شاشة الاشتراك بلا حدود ولا إضافات
        $explicit = $this->snapshot['plan']['limits'] ?? null;

        if (is_array($explicit) && $explicit !== []) {
            return $explicit;
        }

        return array_values(array_map(
            fn ($item) => array_intersect_key($item, array_flip([
                'key', 'label', 'type', 'period', 'included', 'addon_quantity', 'allowance', 'unlimited',
            ])),
            $this->usage()
        ));
    }

    /** الاستهلاك الفعلي لكل ميزة. */
    public function usage(): array
    {
        return $this->snapshot['usage']['usage'] ?? $this->snapshot['usage'] ?? [];
    }

    /** الميزات المستنفدة. */
    public function exhausted(): array
    {
        return $this->snapshot['usage']['exhausted'] ?? [];
    }

    /** الفوترة: المبالغ وآخر دفعة والدفعات المعلّقة. */
    public function billing(): array
    {
        return $this->snapshot['billing'] ?? [];
    }

    /**
     * الإضافات المشتراة — تُستخرج من الحدود.
     *
     * لا تُرسلها الواجهة قائمةً مستقلة، لكن كل حدٍّ يحمل `addon_quantity`؛
     * فما كان أكبر من صفر فهو إضافة اشتراها العميل فوق باقته.
     */
    public function addons(): array
    {
        return array_values(array_filter(
            $this->limits(),
            fn ($limit) => (int) ($limit['addon_quantity'] ?? 0) > 0
        ));
    }

    /**
     * فواتير الاشتراك كما تصدرها لوحة فوترة.
     *
     * لا تُنشأ عندنا ولا تُخزَّن: النظام المحاسبي مشترٍ لا بائع في هذه
     * العلاقة، والفاتورة مستندٌ صادرٌ عن فوترة — فتُعرض من اللقطة ويُفتح
     * أصلها عندهم للطباعة والتنزيل.
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoices(): array
    {
        return $this->billing()['invoices'] ?? [];
    }

    /**
     * روابط ما لا يقع إلا في اللوحة: التجديد، الترقية، شراء الإضافات.
     *
     * تصل جاهزةً من فوترة، فلا يُركّب هذا النظام مساراً يكسره أول تعديل
     * عندهم. وغيابُ رابطٍ يعني إخفاء زرّه لا تخمين عنوانه.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        $links = $this->snapshot['links'] ?? [];

        return is_array($links) ? array_filter($links) : [];
    }

    /** رابطٌ بعينه، أو رابط اللوحة العام، أو null. */
    public function link(string $key): ?string
    {
        return $this->links()[$key] ?? ($this->links()['subscription'] ?? null);
    }

    /* ==================== حالة المزامنة ==================== */

    /** هل اللقطة قديمة؟ الحدّ من الإعداد (٢٤ ساعة افتراضاً كما توصي الوثيقة). */
    public function isStale(): bool
    {
        if (! $this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->diffInSeconds(now()) > (int) config('fawtra.sync.stale_after', 86400);
    }

    /** هل حان وقت المزامنة التالية؟ يحترم التراجع الأسّي بعد الإخفاق. */
    public function isDueForSync(): bool
    {
        if ($this->retry_after && $this->retry_after->isFuture()) {
            return false;
        }

        if (! $this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->diffInSeconds(now()) >= (int) config('fawtra.sync.interval', 900);
    }

    /** وصف عربي لحالة الاشتراك. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->is_expired => 'منتهٍ',
            $this->is_trial && $this->is_active => 'تجريبي',
            $this->is_active => 'نشط',
            $this->status === 'suspended' => 'موقوف',
            default => $this->status ?: 'غير معروف',
        };
    }
}
