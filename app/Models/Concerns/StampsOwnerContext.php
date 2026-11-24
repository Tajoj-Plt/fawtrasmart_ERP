<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * الفرعُ والموظّفُ والمُنشئ — تُختَم في المستند لحظةَ إنشائه.
 *
 * ═════════════════════ لماذا في المودل لا في المتحكّم؟ ═════════════════════
 *
 * هي حجّةُ [BelongsToAccountingPeriod] و[HasDocumentCurrency] نفسُها:
 * المستندُ يُنشأ من شاشةٍ ومن أمرِ كرونٍ ومن خدمةٍ ومن بذرة. وما وُضع في
 * متحكّمٍ واحد يفلت من البقية بلا أن يُلاحَظ.
 *
 * وفي نقاط البيع كان الأثرُ ملموساً: `branch_id` يُملأ في `PosSessionService`
 * وحده، فحركةُ درجٍ تُنشأ من خدمةٍ أخرى تخرج بلا فرع، وسجلُّ تدقيقٍ يُكتب
 * من ثلاثة مواضع يخرج من واحدٍ منها بلا فرع. ثم يُبنى تقريرٌ يحصر بالفرع
 * فتغيب عنه تلك الصفوف — **بلا خطأٍ يظهر**، فقط أرقامٌ أقلّ ممّا ينبغي.
 *
 * ═════════════════════ ما تملؤه ═════════════════════
 *
 *   `branch_id`     ← فرعُ سجلّ الموظف، وإلّا فرعُ الحساب، وإلّا الفرعُ النشط.
 *   `employee_id`   ← سجلُّ الموظف المرتبط بالمستخدم.
 *   `user_id`       ← حسابُ المستخدم.
 *   `created_by`    ← المُنشئ.
 *
 * ولا يُملأ إلّا **العمودُ الموجود في الجدول والفارغُ في المستند**. فما
 * ضبطه المتحكّمُ صراحةً يبقى كما ضبطه — السِّمةُ تسدّ الفراغ ولا تفرض.
 *
 * ═════════════════════ ترتيبُ الفرع ═════════════════════
 *
 * سجلُّ الموظف أوّلاً ثم حسابُ المستخدم: الموظفُ يُنقَل بين الفروع في سجله
 * الوظيفي بينما يبقى `users.branch_id` على ما سُجّل يوم الإنشاء. ثم
 * `current_branch_id()` لمن ليس موظفاً.
 *
 * ═════════════════════ والتجميدُ بعد الحفظ ═════════════════════
 *
 * الفرعُ لا يُعاد حسابُه عند التعديل: المستندُ يخصّ الفرعَ الذي أُنشئ فيه،
 * ونقلُ منشئه إلى فرعٍ آخر لا ينقل مستنداته معه — وإلّا تغيّر تاريخُ
 * الفروع بأثرٍ رجعي كلّما نُقل موظف.
 *
 * ═════════════════════ الاستعمال ═════════════════════
 *
 *     class PosCashMovement extends Model
 *     {
 *         use StampsOwnerContext;
 *     }
 *
 * ولتعطيل عمودٍ بعينه — كمستندٍ فرعُه يُشتقّ من غير منشئه:
 *
 *     protected array $ownerContextExcept = ['branch_id'];
 *
 * أو لتسمية عمود الفرع بغير اسمه (كـ`invoices.pos_branch_id`):
 *
 *     protected array $ownerContextMap = ['branch_id' => 'pos_branch_id'];
 */
trait StampsOwnerContext
{
    /**
     * أعمدةُ الجدول المعروفة — تُقرأ مرّةً لكل صنفٍ في الطلب الواحد.
     *
     * `Schema::hasColumn()` استعلامٌ على القاموس، ومناداتُه لكل حقلٍ في كل
     * صفٍّ يُنشأ تجعل حفظَ مئة صفٍّ أربعمئة استعلامٍ إضافي.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $ownerContextColumns = [];

    public static function bootStampsOwnerContext(): void
    {
        static::creating(function (Model $model) {
            $model->stampOwnerContext();
        });
    }

    /**
     * ملءُ ما هو فارغٌ من أعمدة السياق.
     *
     * لا يرمي أبداً: غيابُ جلسةِ مصادقة — في بذرةٍ أو أمرِ كرون — حالٌ
     * مشروعة، والمستندُ يُحفظ حينئذٍ بلا سياقٍ بدل أن يفشل الحفظ.
     */
    public function stampOwnerContext(): void
    {
        try {
            if (! $this->shouldStampOwnerContext()) {
                return;
            }

            $user = auth()->user();

            if (! $user) {
                return;
            }

            $employee = $this->resolveOwnerEmployee($user);

            $values = [
                'branch_id' => fn () => $this->resolveOwnerBranchId($user, $employee),
                'employee_id' => fn () => $employee?->id ?: $user->employee_id,
                'user_id' => fn () => $user->id,
                'created_by' => fn () => $user->id,
            ];

            foreach ($values as $logical => $resolver) {
                $column = $this->ownerContextColumnFor($logical);

                if ($column === null) {
                    continue;
                }

                // ما ضبطه المتحكّمُ صراحةً لا يُمَسّ
                if (filled($this->getAttribute($column))) {
                    continue;
                }

                $value = $resolver();

                if (filled($value)) {
                    $this->setAttribute($column, $value);
                }
            }
        } catch (Throwable $e) {
            // ختمُ السياق لا يجوز أن يُسقط عمليةً صحيحة
            \Illuminate\Support\Facades\Log::warning('تعذّر ختمُ سياق المستند', [
                'model' => static::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * أيُختَم هذا السجلُّ أصلاً؟
     *
     * يُعاد تعريفُها في المودل الذي لا يخصّ كلَّ صفوفِه الختمُ — كجدولٍ
     * تتشارك فيه أنواعٌ من المستندات. مثالُه `Invoice`: عمودا
     * `pos_branch_id`/`pos_device_id` يخصّان فواتيرَ نقطة البيع وحدها،
     * فختمُهما على فاتورةِ مشترياتٍ يملأ عموداً لا معنى له فيها.
     */
    protected function shouldStampOwnerContext(): bool
    {
        return true;
    }

    /**
     * اسمُ العمود الفعلي لحقلٍ منطقي — أو `null` إن كان مستثنًى أو غيرَ موجود.
     */
    protected function ownerContextColumnFor(string $logical): ?string
    {
        if (in_array($logical, $this->ownerContextExcluded(), true)) {
            return null;
        }

        $column = $this->ownerContextMapping()[$logical] ?? $logical;

        return in_array($column, $this->ownerContextTableColumns(), true) ? $column : null;
    }

    /** @return array<int, string> */
    protected function ownerContextExcluded(): array
    {
        return property_exists($this, 'ownerContextExcept') ? $this->ownerContextExcept : [];
    }

    /** @return array<string, string> */
    protected function ownerContextMapping(): array
    {
        return property_exists($this, 'ownerContextMap') ? $this->ownerContextMap : [];
    }

    /** @return array<int, string> */
    protected function ownerContextTableColumns(): array
    {
        $key = static::class;

        if (! isset(static::$ownerContextColumns[$key])) {
            try {
                static::$ownerContextColumns[$key] = Schema::getColumnListing($this->getTable());
            } catch (Throwable $e) {
                static::$ownerContextColumns[$key] = [];
            }
        }

        return static::$ownerContextColumns[$key];
    }

    /**
     * سجلُّ الموظف المرتبط بالمستخدم — بلا استعلامٍ إن كان محمَّلاً.
     */
    protected function resolveOwnerEmployee(User $user): ?Employee
    {
        if (! $user->employee_id) {
            return null;
        }

        return $user->relationLoaded('employee')
            ? $user->getRelation('employee')
            : $user->employee;
    }

    /**
     * الفرعُ الحاكم: فرعُ المستند ← سجلُّ الموظف ← حسابُ المستخدم ← الفرعُ النشط.
     *
     * ═════════ ولماذا فرعُ المستند أوّلاً؟ ═════════
     *
     * لأنّ فرعَ المُنشئ ليس دائماً فرعَ الحدث. البيعُ يقع في **فرع الجهاز**
     * الذي وقف عليه العميل، لا في فرع الموظف الذي سجّل دخوله. ومشرفٌ يملك
     * `data_scope_all` قد يفتح جلسةً على جهازٍ في فرعٍ غير فرعه — فتُنسَب
     * مبيعاتُ ذلك اليوم إلى فرعه هو، ويظهر في تقرير الفروع بيعٌ لم يقع فيه.
     *
     * فالمودل يُسأل أوّلاً: `ownerBranchFromDocument()` تُعاد تعريفُها
     * لتشتقّ الفرع من الجهاز أو الجلسة أو الفاتورة الأمّ.
     */
    protected function resolveOwnerBranchId(User $user, ?Employee $employee): ?int
    {
        $branchId = $this->ownerBranchFromDocument()
            ?: ($employee?->branch_id ?: $user->branch_id);

        if (! $branchId && function_exists('current_branch_id')) {
            $branchId = current_branch_id();
        }

        return $branchId ? (int) $branchId : null;
    }

    /**
     * فرعٌ يُشتقّ من المستند نفسِه — يُعاد تعريفُها عند الحاجة.
     *
     * تُنادى قبل حفظ السجل، فلا تعتمد على علاقاتٍ مُحمَّلة: تقرأ بالمعرّف
     * مباشرةً، وتُرجع `null` لتترك القرار لسلسلة الاحتياط.
     */
    protected function ownerBranchFromDocument(): ?int
    {
        return null;
    }

    /**
     * فرعُ جلسةِ نقطة بيعٍ بمعرّفها — مساعدةٌ لمن يشتقّ فرعَه منها.
     *
     * استعلامٌ واحدٌ بعمودٍ واحد لا تحميلُ الموديل كاملاً، ويُبتلع خطؤه:
     * جدولٌ غير مُرحَّل بعد يجب ألّا يمنع الحفظ.
     */
    protected function branchOfSession($sessionId): ?int
    {
        if (! $sessionId) {
            return null;
        }

        try {
            $id = \Illuminate\Support\Facades\DB::table('pos_sessions')
                ->where('id', $sessionId)
                ->value('branch_id');

            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** فرعُ جهازِ كاشيرٍ بمعرّفه. */
    protected function branchOfDevice($deviceId): ?int
    {
        if (! $deviceId) {
            return null;
        }

        try {
            $id = \Illuminate\Support\Facades\DB::table('cashier_devices')
                ->where('id', $deviceId)
                ->value('branch_id');

            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /* ═══════════════════════ العلاقات ═══════════════════════ */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
