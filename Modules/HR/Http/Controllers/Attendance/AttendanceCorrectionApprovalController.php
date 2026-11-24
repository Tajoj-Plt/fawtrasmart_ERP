<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceDays;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Log as ModelsLog;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * الوجهُ المعتمِد لتصحيح الحضور — `approve()` هو الموضع الوحيد في هذا
 * التدفّق الذي يكتب فعلياً على `attendance_days`. على نمط
 * `AdvanceApprovalController` لطلبات السلف، بصلاحيةٍ مستقلة عن صلاحية
 * الطلب (`attendance_corrections.approve`).
 *
 * القفلُ الذي غاب عن نظير السلف: `lockForUpdate()` قبل فحص
 * `canBeDecided()` — فاعتمادان متزامنان لنفس الطلب لا يُطبَّقان معاً؛
 * الثاني يُحجَب على القفل حتى يُقفَل الأول، ثم يقرأ حالةً مبتوتة
 * فيرفضها فحصُه هو.
 */
class AttendanceCorrectionApprovalController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:attendance_corrections.approve', [
            'only' => ['index', 'show', 'approve', 'reject'],
        ]);
    }

    /**
     * الطابورُ معلَّقٌ بالافتراض، وما بُتَّ فيه يبقى مبلوغاً.
     *
     * كانت الشاشةُ `pending()` قسراً: يعتمد المسؤولُ طلباً فيختفي من
     * أمامه إلى الأبد، ولا سبيلَ إلى «ماذا قرّرتُ في طلب فلانٍ الشهر
     * الماضي؟» إلا من شاشةِ الطالب. وأثرُ القرار هو ما تُحفَظ الشاشةُ
     * لأجله.
     */
    public function index(Request $request)
    {
        $base = AttendanceCorrectionRequest::query();
        $this->scopeToEmployees($base);

        $counts = [
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
            'rejected' => (clone $base)->rejected()->count(),
        ];

        $status = $request->input('status', AttendanceCorrectionRequest::STATUS_PENDING);

        if (! array_key_exists($status, AttendanceCorrectionRequest::statusLabels())) {
            $status = AttendanceCorrectionRequest::STATUS_PENDING;
        }

        $query = AttendanceCorrectionRequest::with(['employee', 'requestedBy', 'approvedBy'])
            ->where('status', $status)
            // المعلَّقُ أقدمُه أولاً (طابور)، والمبتوتُ أحدثُه أولاً (سجلّ)
            ->orderBy('id', $status === AttendanceCorrectionRequest::STATUS_PENDING ? 'asc' : 'desc');

        $this->scopeToEmployees($query);

        $requests = $query->paginate(20)->withQueryString();

        return view('hr::attendance.correction_approvals.index', compact('requests', 'counts', 'status'));
    }

    public function show($id)
    {
        $correction = AttendanceCorrectionRequest::with(['employee', 'requestedBy', 'approvedBy'])->findOrFail($id);

        $this->authorizeEmployeeScope($correction->employee_id);

        /*
            أثرُ التدقيق يُعرَض حيث يُتَّخذ القرار.

            المتحكّمُ يكتب في `logs` عند كلِّ تقديمٍ واعتمادٍ ورفضٍ وإلغاء
            منذ البداية، ولم تكن شاشةٌ واحدةٌ تقرؤه — فالأثرُ محفوظٌ
            ولا يراه أحد. و`approved_by`/`approved_at` على الصفّ تحمل
            القرارَ الأخير وحدَه، لا سردَه.
        */
        $auditTrail = ModelsLog::with('user')
            ->where('type', 'attendance_correction_request_log')
            ->where('type_id', $correction->id)
            ->orderBy('id')
            ->get();

        return view('hr::attendance.correction_approvals.show', compact('correction', 'auditTrail'));
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        // خارج القفل والمعاملة عمداً: فحصُ النطاق ثابتٌ بثبات الطلب، ولا
        // يحتاج قفلَ صفٍّ. وقوعُه هنا يمنع 403 من أن يُبتلَع في catch أدناه.
        $correction = AttendanceCorrectionRequest::findOrFail($id);
        $this->authorizeEmployeeScope($correction->employee_id);

        try {
            DB::beginTransaction();

            $correction = AttendanceCorrectionRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if (! $correction->canBeDecided()) {
                DB::rollBack();

                return back()->with('error', 'هذا الطلب سبق البتّ فيه — لا يمكن اعتماده مرتين.');
            }

            // لا firstOrNew(['attendance_date' => ...]) هنا: مطابقةٌ نصّيةٌ
            // حرفية على عمودٍ مصبوبٍ 'date' قد يحمل دقّةً كاملة وقت الكتابة
            // (خصوصاً بعد اعتماد سابق)، فلا يجد الصفّ الموجود ويحاول
            // إدراج صفٍّ جديد يصطدم بالقيد الفريد. whereDate() يقارن جزء
            // التاريخ فقط، بصرف النظر عن الدقّة الفعلية المخزَّنة.
            $day = AttendanceDays::where('employee_id', $correction->employee_id)
                ->whereDate('attendance_date', $correction->attendance_date->toDateString())
                ->first()
                ?? new AttendanceDays([
                    'employee_id' => $correction->employee_id,
                    'attendance_date' => $correction->attendance_date->toDateString(),
                ]);

            $day->status = $correction->requested_status;

            // لا حقلَ في نموذج الطلب يزوّد هذه الدقائق بقيمةٍ جديدة، فتبقى
            // من حسابٍ سابق (تأخيرٌ حُسِب آليًّا مثلاً) عالقةً على يومٍ
            // اعتُمِد الآن "حاضر" — فيظهر تقرير الحضور دقائق تأخيرٍ على
            // يومٍ لا تأخير فيه، أو تُحتسَب خصميتها لو أُعيدت معالجة اليوم.
            $day->late_minutes = 0;
            $day->early_leave_minutes = 0;
            $day->overtime_minutes = 0;

            /*
                والخصميةُ التي ولّدتها الوقائعُ القديمة تُلغى معها.

                كان اليومُ يُكتب «حاضراً» وتُصفَّر دقائقُه، وتبقى خصميةُ
                الغياب مربوطةً به بحالة «معتمَدة». فالموظفُ الذي أُثبت
                حضورُه يُحسَم من راتبه غيابُ يومٍ حضَره — وهو عينُ ما رُفع
                الطلبُ لإصلاحه. ولا شاشةَ تُنبّه: صفحةُ «أيام الحضور» تقول
                «حاضر» و«خُصم ٣٠٠» في صفٍّ واحد.

                وتُلغى لا تُحذَف: الخصميةُ أثرٌ ماليٌّ له تاريخُه، فتُقيَّد
                ملغاةً بسببها ويبقى سردُها في `deduction_histories`.

                والمسوّاةُ — كلياً أو جزئياً — لا تُمسّ: مبلغُها خرج فعلاً
                في راتبٍ صُرف، وإلغاؤها هنا يُخفي حسماً وقع ولا يردّه.
                تلك تُعالَج بتسويةٍ عكسيةٍ في الرواتب، لا بقلب حالةٍ هنا.
            */
            $stale = $this->staleDeductionsFor($day, $correction);

            foreach ($stale['cancel'] as $deduction) {
                $deduction->update([
                    'status' => Deduction::STATUS_CANCELLED,
                    'cancelled_by_user' => auth()->id(),
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'اعتُمد طلب تصحيح حضور #'.$correction->id,
                ]);

                DeductionHistory::create([
                    'deduction_id' => $deduction->id,
                    'action' => 'cancelled',
                    'user_id' => auth()->id(),
                    'amount' => $deduction->amount,
                    'description' => 'أُلغيت باعتماد طلب تصحيح حضور #'.$correction->id,
                    'notes' => 'يوم '.$correction->attendance_date->toDateString()
                        .' صُحِّح إلى «'.$correction->requestedStatusLabel().'»',
                ]);
            }

            if ($stale['cancel']->isNotEmpty()) {
                $day->deduction_id = null;
            }

            // نفسُ فرعِ الحالة في AttendanceDaysController::store/update —
            // الحقولُ التي لا تخصّ الحالة المختارة تُصفَّر لا تُترَك عالقة.
            if ($correction->requested_status === 'present') {
                $day->start_shift = $correction->requested_start_shift;
                $day->end_shift = $correction->requested_end_shift;
                $day->check_in_time = $correction->requested_check_in_time;
                $day->check_out_time = $correction->requested_check_out_time;
                $day->absence_type = null;
                $day->absence_balance = null;
            } elseif ($correction->requested_status === 'absent') {
                $day->absence_type = $correction->requested_absence_type;
                $day->absence_balance = $correction->requested_absence_balance;
                $day->start_shift = null;
                $day->end_shift = null;
                $day->check_in_time = null;
                $day->check_out_time = null;
            } else { // late — أوقات الحضور تُحفظ وإلا يقلب الكرون اليوم غيبياً
                $day->start_shift = $correction->requested_start_shift;
                $day->end_shift = $correction->requested_end_shift;
                $day->check_in_time = $correction->requested_check_in_time;
                $day->check_out_time = $correction->requested_check_out_time;
                $day->absence_type = null;
                $day->absence_balance = null;
            }

            if ($correction->requested_notes) {
                $day->notes = $correction->requested_notes;
            }

            // يثبت الاعتماد أمام إعادة الحساب الليلية حتى يأتي ختمٌ جديد
            $day->manual_override = true;
            $day->processed_at = now();

            $day->save();

            $correction->update([
                'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $request->admin_notes,
            ]);

            ModelsLog::create([
                'type' => 'attendance_correction_request_log',
                'type_id' => $correction->id,
                'type_log' => 'log',
                'description' => 'تم اعتماد طلب تصحيح الحضور وتطبيقه على السجل',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            $redirect = redirect()
                ->route('hr.attendance-correction-approvals.show', $correction->id)
                ->with('success', $this->approvalNotice($stale));

            // خصميّةٌ مسوّاةٌ بقيت: تُقال صراحةً، فردُّها قرارٌ في الرواتب لا هنا
            if ($stale['settled']->isNotEmpty()) {
                $redirect->with('warning', $this->settledNotice($stale['settled']));
            }

            return $redirect;
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.AttendanceCorrectionApproval', 'approve');

            return back()->with('error', 'حدث خطأ أثناء اعتماد الطلب. لم يُطبَّق أي تغيير.');
        }
    }

    /**
     * خصميّاتُ هذا اليومِ التي لم تعد مستحقّةً بعد التصحيح.
     *
     * **لا تكفي `attendance_days.deduction_id`**: المعالجُ
     * ({@see \App\Services\AttendanceProcessorService::syncDayDeductions()}) يُنشئ
     * لليوم الواحد خصميّةً من كلّ نوعٍ مستحَقّ — غياب/نصف يوم/تأخير **و
     * انصرافٍ مبكر** — ولا يربط باليوم إلّا «الأساسية» منها. فكان اعتمادُ
     * التصحيح يُلغي المربوطةَ وحدَها، ويبقى خصمُ الانصراف المبكر معتمَداً
     * يُحسم من راتب موظفٍ أُثبت أنّ يومَه كاملٌ وصُفِّرت دقائقُه. ثبت ذلك
     * بتجربةٍ: يومٌ بخصمَي تأخيرٍ وانصرافٍ مبكر، فأُلغي الأوّلُ وبقي الثاني.
     *
     * والمأخوذُ ما وَسَمه المعالجُ بعلامته أو ما رُبط باليوم — فخصميّةٌ
     * كتبها المديرُ بيده على ذلك اليوم (عقوبةٌ إدارية مثلاً) **لا تُلغى**:
     * قرارُها ليس من وقائع الحضور، ورفعُها قرارٌ مستقلّ.
     *
     * والمسوّاةُ — كلّياً أو جزئياً — تُفرَد ولا تُمَسّ: مبلغُها خرج في راتبٍ
     * صُرف، وقلبُ حالتها هنا يُخفي حسماً وقع ولا يردّه. تُعاد بتسويةٍ عكسيةٍ
     * في الرواتب، ولذلك تُذكَر للمستخدم بدل أن تُبتلَع بصمت.
     *
     * @return array{cancel: \Illuminate\Support\Collection, settled: \Illuminate\Support\Collection}
     */
    private function staleDeductionsFor(AttendanceDays $day, AttendanceCorrectionRequest $correction): array
    {
        $fromAttendance = [
            Deduction::TYPE_ABSENCE,
            Deduction::TYPE_HALF_DAY,
            Deduction::TYPE_LATE,
            Deduction::TYPE_EARLY_LEAVE,
        ];

        $candidates = Deduction::query()
            ->where('employee_id', $correction->employee_id)
            ->whereDate('date', $correction->attendance_date->toDateString())
            ->whereIn('deduction_type', $fromAttendance)
            ->where(function ($query) use ($day) {
                $query->where('notes', \App\Services\AttendanceProcessorService::AUTO_DEDUCTION_NOTE);

                if ($day->deduction_id) {
                    $query->orWhere('id', $day->deduction_id);
                }
            })
            ->whereIn('status', [Deduction::STATUS_PENDING, Deduction::STATUS_APPROVED, Deduction::STATUS_SETTLED, Deduction::STATUS_PARTIALLY_SETTLED])
            ->lockForUpdate()
            ->get();

        $settled = $candidates->filter(
            fn (Deduction $d) => (float) $d->settled_amount > 0
                || in_array($d->status, [Deduction::STATUS_SETTLED, Deduction::STATUS_PARTIALLY_SETTLED], true)
        )->values();

        return [
            'cancel' => $candidates->whereNotIn('id', $settled->pluck('id')->all())->values(),
            'settled' => $settled,
        ];
    }

    /** @param  array{cancel: \Illuminate\Support\Collection, settled: \Illuminate\Support\Collection}  $stale */
    private function approvalNotice(array $stale): string
    {
        $count = $stale['cancel']->count();

        if ($count === 0) {
            return 'تم اعتماد طلب التصحيح وتطبيقه — ولا خصميّةَ على هذا اليوم.';
        }

        $amount = number_format((float) $stale['cancel']->sum('amount'), 2);

        $label = match (true) {
            $count === 1 => 'خصميّةٌ واحدة',
            $count === 2 => 'خصميّتان',
            $count <= 10 => $count.' خصميّات',
            default => $count.' خصميّة',
        };

        return 'تم اعتماد طلب التصحيح وتطبيقه، وأُلغيت '.$label.' بمجموع '.$amount.' فلم تعد تُحسم من الراتب.';
    }

    /** @param  \Illuminate\Support\Collection  $settled */
    private function settledNotice($settled): string
    {
        if ($settled->isEmpty()) {
            return '';
        }

        return 'تنبيه: '.$settled->count().' خصميّةً على هذا اليوم اقتُطعت في راتبٍ صُرف بالفعل (بمجموع '
            .number_format((float) $settled->sum('amount'), 2).')، فلم تُلغَ هنا — رُدَّها بتسويةٍ عكسيةٍ في الرواتب.';
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $correction = AttendanceCorrectionRequest::findOrFail($id);
        $this->authorizeEmployeeScope($correction->employee_id);

        try {
            DB::beginTransaction();

            $correction = AttendanceCorrectionRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if (! $correction->canBeDecided()) {
                DB::rollBack();

                return back()->with('error', 'هذا الطلب سبق البتّ فيه.');
            }

            $correction->update([
                'status' => AttendanceCorrectionRequest::STATUS_REJECTED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            ModelsLog::create([
                'type' => 'attendance_correction_request_log',
                'type_id' => $correction->id,
                'type_log' => 'log',
                'description' => 'تم رفض طلب تصحيح الحضور: '.$request->rejection_reason,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('hr.attendance-correction-approvals.show', $correction->id)
                ->with('success', 'تم رفض طلب التصحيح.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.AttendanceCorrectionApproval', 'reject');

            return back()->with('error', 'حدث خطأ أثناء رفض الطلب.');
        }
    }
}
