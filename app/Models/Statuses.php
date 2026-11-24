<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * حالةُ مستند.
 *
 * `$fillable` يعِد بعمودٍ لا وجود له: `client_id` مكتوبٌ فيه وجدول
 * `statuses` لا يحوي إلّا `id, name, color, state, section, timestamps`.
 * فأيّ نداءٍ يمرّره يسقط بـ`Unknown column`.
 *
 * وعمود `section` كان يفصل قسمين يتقاسمان الجدول: `null` حالةٌ عامّة
 * يشير إليها `clients.status_id`، و`supply-order` قائمةُ أوامر التوريد.
 * وقد أُزيل ذلك القسم من النظام، فلم يبقَ إلّا العامّ — ويبقى العمود
 * لقسمٍ ثالثٍ إن جاء.
 */
class Statuses extends Model
{
    use HasFactory;

    protected $table = 'statuses';

    protected $fillable = [
        'section',
        'name',
        'color',
        'state',
        'sort_order',
        'is_default',
        'is_deletable',
        'kpi_role',
    ];

    protected $casts = [
        // `state`: 1 مفتوحة (في قائمة العمل)، 0 مغلقة (انتهى المستند)
        'state' => 'boolean',
        'is_default' => 'boolean',
        'is_deletable' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * حالات قسمٍ بعينه.
     *
     * `$withShared` يضمّ الحالات العامّة (`section = null`) التي سبقت
     * الفصل: أوامر توريدٍ قديمة قد تكون مربوطةً بإحداها، فإخفاؤها من
     * القائمة يجعل حالتها الحالية غيرَ قابلةٍ للاختيار.
     */
    public function scopeSection(Builder $query, string $section, bool $withShared = false): Builder
    {
        return $query->where(function (Builder $scope) use ($section, $withShared) {
            $scope->where('section', $section);

            if ($withShared) {
                $scope->orWhereNull('section');
            }
        });
    }

    /** الترتيب المعروض: الرقم المضبوط، ثم الاسم عند التساوي. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * حالات العميل القابلة للاستعمال فعلاً — نشط/موقوف فقط.
     *
     * حالة العميل صارت ثنائية بقرار (راجع docs/CRM_Customer_Status_Gap_Analysis_Plan.md
     * القسم 3)، وأيّ صفّ آخر بـ`section = null` بلا `kpi_role` صار أثراً
     * تاريخياً لا خياراً حياً — يبقى بالجدول لحساباتٍ قديمة (`isInUse()`،
     * سجلّات `client_status_requests`) لكن لا يظهر بأي قائمة سحب/تصفية
     * جديدة. `Statuses::whereNull('section')` وحدها (بلا هذا النطاق) ما
     * زالت صحيحةً لشاشة «حالات العملاء» الإدارية نفسها، حيث يحتاج المدير
     * رؤية الحالات القديمة لإدارتها.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->whereNull('section')->whereNotNull('kpi_role');
    }

    /**
     * حالاتُ العميل لقوائم **الفلترة** — لا الاختيار.
     *
     * `assignable()` صحيحٌ لاختيار حالةٍ جديدة وخاطئٌ للفلترة: عملاءُ ما زالوا
     * يحملون حالاتٍ قديمة (عميل محتمل ١٦٤، ضريبي ٧١، مديونية عليك ١٨…) فلا
     * يمكن الوصول إليهم من أيّ فلتر. فالقائمةُ هنا: المعتمدةُ كلُّها، ثمّ كلُّ
     * حالةٍ قديمةٍ ما زال عليها عميل — ولكلٍّ عددُ عملائها و`is_legacy`.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function clientFilterOptions()
    {
        $counts = \Illuminate\Support\Facades\DB::table('clients')
            ->whereNotNull('status_id')
            ->groupBy('status_id')
            ->selectRaw('status_id, COUNT(*) as c')
            ->pluck('c', 'status_id');

        return static::query()
            ->whereNull('section')
            ->where(fn ($q) => $q->whereNotNull('kpi_role')->orWhereIn('id', $counts->keys()->all() ?: [0]))
            ->orderByRaw('kpi_role IS NULL')
            ->orderBy('name')
            ->get()
            ->each(function (self $status) use ($counts) {
                $status->clients_count = (int) ($counts[$status->id] ?? 0);
                $status->is_legacy = $status->kpi_role === null;
            });
    }

    /** العملاء المصنَّفون بهذه الحالة — `clients.status_id`. */
    public function clients()
    {
        return $this->hasMany(Client::class, 'status_id', 'id');
    }

    /** العملاء الموسومون بهذه المرحلة — `clients.registration_stage_id`، قسم `registration_stage`. */
    public function registrationStageClients()
    {
        return $this->hasMany(Client::class, 'registration_stage_id', 'id');
    }

    /**
     * حالات العملاء (`section = null`) المحتسَبة نشطة/موقوفة في مؤشّرات
     * لوحة التحكّم — بدل `status_id == 1`/`== 2` المثبَّتين في الكود.
     *
     * لا حالة مضبوطة بعد بـ`kpi_role`؟ يُرجَع المعرّف القديم المثبَّت
     * سابقاً (1 أو 2) كي لا يتغيّر شيءٌ في العدّ قبل أن يضبط أحدٌ الإعداد
     * صراحةً من شاشة «الحالات».
     */
    public static function activeClientStatusIds(): array
    {
        $ids = static::whereNull('section')->where('kpi_role', 'active')->pluck('id')->all();

        return $ids ?: [1];
    }

    public static function stoppedClientStatusIds(): array
    {
        $ids = static::whereNull('section')->where('kpi_role', 'stopped')->pluck('id')->all();

        return $ids ?: [2];
    }

    /**
     * أمستعمَلةٌ في مكانٍ ما؟ الحذف يمرّ من هنا لا من زرٍّ في الشاشة.
     *
     * الشاشة القديمة كان زرّ الحذف فيها يزيل الصفّ من DOM وحده ثم يُرسِل
     * النموذج، و`storeStatus()` لا تحذف شيئاً — فالحالة تعود بعد التحديث.
     *
     * لا `supplyOrders()` هنا: قسم أوامر التوريد أُزيل من النظام بالكامل
     * (انظر تعليق الصفّ أعلاه)، والدالّة نفسها لم تعد موجودة على هذا
     * النموذج — استدعاؤها كان يرمي `BadMethodCallException` دائماً، يلتقطه
     * `catch` أدناه، فيُعامَل كل صفٍّ كأنّه مستعمَل ويُمنَع حذفُه صامتاً
     * مهما كان فارغاً فعلاً.
     */
    public function isInUse(): bool
    {
        try {
            return $this->clients()->exists() || $this->registrationStageClients()->exists();
        } catch (\Throwable $e) {
            // عمودٌ ناقص في أحد الجدولين: يُعامَل كأنّه مستعمَل — المنع
            // أسلم من حذفٍ يكسر مرجعاً
            return true;
        }
    }
}
