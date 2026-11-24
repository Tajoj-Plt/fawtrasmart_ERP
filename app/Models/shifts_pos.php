<?php

namespace App\Models;

use App\Models\Concerns\StampsOwnerContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Pos\Models\PosShiftCategory;

class shifts_pos extends Model
{
    use HasFactory, SoftDeletes;

    /*
        الفرعُ يُختَم تلقائياً، و`user_id` **لا**.

        `user_id` هنا ليس مُنشئَ الوردية بل **الموظفَ المسنَدة إليه** —
        يختاره المسؤول في النموذج. وختمُه بالمستخدم الحالي يُسنِد كلَّ
        وردية إلى من أنشأها، فيرى المسؤولُ نفسَه كاشيرَ كل الورديات.
    */
    use StampsOwnerContext;

    protected array $ownerContextExcept = ['user_id'];

    // ربط الموديل بالجدول الجديد
    protected $table = 'shifts_pos';

    protected $fillable = [
        'name',
        'category_id',
        'parent_id',
        'attachment',
        'description',
        'branch_id',
        'user_id',
        // المدى الزمني — بدونه كانت «الوردية» تسميةً في شجرة لا وردية
        'start_time',
        'end_time',
        'days_of_week',
        'grace_minutes',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'grace_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /** جلساتُ هذه الوردية — لمنع حذفِ ورديةٍ لها تاريخ. */
    public function sessions()
    {
        return $this->hasMany(PosSession::class, 'shift_id');
    }

    /**
     * نصُّ المدى الزمني للعرض — «٠٨:٠٠ — ١٦:٠٠» أو «غير محدَّد».
     */
    public function getTimeRangeAttribute(): string
    {
        if (! $this->start_time || ! $this->end_time) {
            return 'غير محدَّد';
        }

        return substr((string) $this->start_time, 0, 5).' — '.substr((string) $this->end_time, 0, 5);
    }

    /**
     * أتعمل هذه الوردية في هذا اليوم؟
     *
     * `null` أو مصفوفةٌ فارغة تعني **كلَّ الأيام** — لا «لا يوم».
     */
    public function worksOn(int $dayOfWeek): bool
    {
        $days = $this->days_of_week;

        return empty($days) || in_array($dayOfWeek, $days, true);
    }

    /**
     * تصنيفُ الوردية.
     *
     * وهو ما حلَّ محلَّ `parent_id` في الواجهة. الفرقُ أنّ التصنيف كِيانٌ
     * مستقلّ بلا وقتِ دوامٍ ولا جلسات، بينما كان «الأب» ورديةً كاملةً
     * تظهر في قائمة الورديات وفي تقاريرها وفي قائمة ما يُفتَح عليه جلسة.
     */
    public function category()
    {
        return $this->belongsTo(PosShiftCategory::class, 'category_id');
    }

    /*
        `parent_id` مُجمَّد: لا تكتب فيه شاشةٌ بعد اليوم.

        ويبقى العمودُ والعلاقتان لأنّ تركيباتٍ قائمةً قد تحمل شجرةً
        سابقة — وحارسُ الحذف أدناه لا يزال يحتاجهما.
    */

    // العلاقة مع الورديات الأب
    public function parent()
    {
        return $this->belongsTo(shifts_pos::class, 'parent_id');
    }

    // العلاقة مع الورديات الأبناء
    public function children()
    {
        return $this->hasMany(shifts_pos::class, 'parent_id');
    }

    // العلاقة مع الفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // العلاقة مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * أتقع هذه اللحظةُ داخل نافذة الوردية؟
     *
     * ═══════════════ ما كان ═══════════════
     *
     * الحقولُ الأربعة (`start_time`، `end_time`، `days_of_week`،
     * `grace_minutes`) تُضبط في الشاشة وتُحفظ في الجدول، و**لا شيء يفرضها**:
     *
     *   • الوقتُ يُقاس في `PosSessionService::warnIfOutsideShift()` — ثم
     *     يُكتب سطرٌ في اللوغ ويمضي. لا منعَ ولا حتى إخبارَ المستخدم.
     *   • و`days_of_week` **لا تُقرأ في أيّ سطرٍ من النظام**: تُكتب في
     *     `ShiftController::store()` ولا تُستعمل بعدها قطّ. فوردية «السبت
     *     والأحد» تُفتح يوم الجمعة بلا فرق.
     *
     * فالمستخدم يضبط أربعةَ حقولٍ ويظنّها ضوابط، وهي وصفٌ لا حكم.
     *
     * ═══════════════ والقاعدة ═══════════════
     *
     * اليومُ أوّلاً ثم الوقت — لأن يوماً غيرَ مسموحٍ يُبطل الوقتَ كلَّه،
     * ورسالتُه أوضح («الوردية لا تعمل اليوم» لا «خارج الوقت»).
     *
     * والمهلةُ (`grace_minutes`) تُوسّع الطرفين معاً: من يفتح قبل الموعد
     * بدقائقَ يجهّز صندوقه، ومن يفتح بعده متأخّرٌ لا مخالف.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function windowCheck(?Carbon $at = null): array
    {
        $at = $at ?: Carbon::now();

        // ورديةٌ معطَّلة لا تُفتح عليها جلسةٌ أصلاً
        if (! $this->is_active) {
            return ['ok' => false, 'reason' => 'الوردية «'.$this->name.'» معطَّلة.'];
        }

        $days = array_map('intval', (array) ($this->days_of_week ?? []));

        // بلا تحديدٍ = كلُّ الأيام
        if ($days && ! in_array((int) $at->dayOfWeek, $days, true)) {
            return [
                'ok' => false,
                'reason' => 'الوردية «'.$this->name.'» لا تعمل في هذا اليوم.',
            ];
        }

        if (! $this->start_time || ! $this->end_time) {
            return ['ok' => true, 'reason' => null];
        }

        $grace = max(0, (int) ($this->grace_minutes ?? 0));

        $start = Carbon::parse($this->start_time)->setDateFrom($at)->subMinutes($grace);
        $end = Carbon::parse($this->end_time)->setDateFrom($at)->addMinutes($grace);

        // ورديةٌ تعبر منتصف الليل: نهايتُها في اليوم التالي
        if ($end->lessThan($start)) {
            $end->addDay();

            // ولحظةٌ في الساعات الأولى تتبع ورديةَ الأمس
            if ($at->lessThan($start)) {
                $start->subDay();
                $end->subDay();
            }
        }

        if ($at->between($start, $end)) {
            return ['ok' => true, 'reason' => null];
        }

        return [
            'ok' => false,
            'reason' => sprintf(
                'الوردية «%s» تعمل من %s إلى %s%s — والوقت الآن %s.',
                $this->name,
                substr((string) $this->start_time, 0, 5),
                substr((string) $this->end_time, 0, 5),
                $grace ? ' (بمهلة '.$grace.' دقيقة)' : '',
                $at->format('H:i')
            ),
        ];
    }

    /** اختصارٌ للفحص وحده. */
    public function isWithinWindow(?Carbon $at = null): bool
    {
        return $this->windowCheck($at)['ok'];
    }
}
