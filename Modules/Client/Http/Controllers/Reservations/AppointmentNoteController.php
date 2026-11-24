<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentNoteRequest;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Client;
use App\Models\Statuses;
use App\Support\OperationLog;
use Illuminate\Support\Facades\Log;
use Modules\Client\Http\Controllers\Concerns\EnforcesClientModuleSettings;
use Throwable;

/**
 * ملاحظات مواعيد العملاء ومرفقاتها.
 *
 * **ما يأتي من الإعدادات** — «إعدادات وحدات العملاء»:
 *
 *   • *القسم*        ← `notes_enabled`.
 *   • *حجم المرفقات* ← «حدود حجم المرفقات»، في `AppointmentNoteRequest`.
 *
 * بطاقة «ملاحظات العملاء» (مهلة التعديل، الحذف، عدد المرفقات، الموقع)
 * أُزيلت من الإعدادات في 2026-09-13.
 *
 * **ما كان مكسوراً**: النموذج يرفع `attachments[]` (متعدّد)، و`store()`
 * تمرّر المصفوفةَ كلَّها إلى `uploadImage()` التي تنادي
 * `getClientOriginalExtension()` على مصفوفة — فأيُّ ملاحظةٍ بمرفق كانت
 * تُسقط الشاشة بخطأ 500. وقاعدةُ التحقّق على المرفقات كانت مُعلَّقة أصلاً،
 * فلا نوعَ ولا حجم.
 *
 * **السجلّات**: `OperationLog` في الإنشاء والتعديل والحذف — لم يكن على
 * أيٍّ منها سطرُ سجلٍّ واحد.
 */
class AppointmentNoteController extends Controller
{
    use EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientAppointmentNotes';

    /** مجلّد المرفقات — كان مكتوباً في نداء الرفع وحده */
    private const UPLOAD_FOLDER = 'assets/uploads/note';

    public function __construct()
    {
        // صلاحيات متابعة العملاء
        $this->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own', ['only' => ['downloadAttachment']]);
        $this->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own', ['only' => ['create', 'store']]);
        $this->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own', ['only' => ['destroy']]);
    }

    /*
        أُزيلت `index()` و`show()` و`edit()` و`update()`: لا مسارَ يصل إليها منذ
        حُذفت مساراتُها (انظر ترويسة `routes/reservations.php`) — `index()`
        كانت تعرض قالباً يطلب `$client` بلا عميل، و`show()` قالباً غير موجود،
        و`edit()`/`update()` نموذجاً يُحوِّل إلى مسارٍ غير موجود. تعديلُ
        ملاحظة العميل من `client::notes.edit_client_note`.
    */

    /**
     * عرض نموذج إنشاء ملاحظة جديدة
     */
    public function create($id)
    {
        if ($block = $this->sectionBlock('notes')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $clients = Client::with('latestStatus')->orderBy('created_at', 'desc')->get();
        $appointments = Appointment::all();
        $statuses = Statuses::all();

        // حدّ حجم المرفقات يصل النموذج فيقوله للمستخدم قبل الرفع لا بعده
        $maxUploadSizeKb = ClientSettingsHelper::maxUploadSizeKb();

        return view('client::reservations.appointments.note.add_note', compact(
            'clients', 'appointments', 'statuses', 'id', 'maxUploadSizeKb'
        ));
    }

    /**
     * حفظ ملاحظة جديدة
     */
    public function store(AppointmentNoteRequest $request)
    {
        if ($block = $this->sectionBlock('notes')) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $data = $request->except('_token');

        /* الكاتبُ يُختم من الجلسة لا من النموذج: النموذجُ لا يرسل `user_id`
           أصلاً، والطلبُ يقبله اختيارياً — فكانت الملاحظاتُ تُحفظ بلا كاتبٍ
           (أو بكاتبٍ يختاره المرسِل)، ولا يمكن بعدها حصرُ تعديلها بصاحبها. */
        $data['user_id'] = auth()->id();

        try {
            if ($request->hasFile('attachments')) {
                $data['attachments'] = $this->uploadAttachments($request->file('attachments'));
            }

            $note = AppointmentNote::create($data);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()->back()->withInput()->with('error', __('reservations.messages.note_error'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $note,
            'إضافة ملاحظة متابعة للعميل #'.($data['client_id'] ?? '—'),
            ['new' => [
                'client_id' => $data['client_id'] ?? null,
                'visit_type' => $data['visit_type'] ?? null,
                'attachments' => count($data['attachments'] ?? []),
            ]]
        );

        try {
            app(\App\Services\PointsService::class)->processAction('note_created', [
                'employee_id' => auth()->id(),
                'client_id' => $data['client_id'] ?? null,
                'description' => 'تسجيل ملاحظة موعد',
                'reference_type' => get_class($note),
                'reference_id' => $note->id,
            ]);

            // Evaluate itinerary completion
            app(\App\Services\PointsService::class)->evaluateItineraryCompletion(auth()->id(), now()->toDateString());
        } catch (\Exception $e) {
            Log::warning('فشل إضافة نقاط أو تقييم خط السير في ملاحظة الموعد: '.$e->getMessage());
        }

        return redirect()->route('clients.show_contant', ['id' => $data['client_id']])
            ->with('success', 'تم إضافة الملاحظة بنجاح');
    }

    /**
     * رفعُ مرفقات الملاحظة — واحداً أو أكثر.
     *
     * `attachments` عمودٌ من نوع `array` في النموذج والنموذجُ يرفع
     * `attachments[]`، فالمصفوفة هي الحالة الطبيعية لا الاستثناء. والدالّة
     * السابقة كانت تنادي `getClientOriginalExtension()` على المصفوفة نفسها.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>|\Illuminate\Http\UploadedFile  $files
     * @return array<int, string>
     */
    protected function uploadAttachments($files): array
    {
        $stored = [];

        foreach (is_array($files) ? $files : [$files] as $file) {
            $name = time().rand(1, 99).'.'.$file->getClientOriginalExtension();
            $file->move(self::UPLOAD_FOLDER, $name);
            $stored[] = $name;
        }

        return $stored;
    }

    /**
     * حراسةُ الملاحظة نفسها بعد جلبها — لا يكفي أن يملك المستخدم إحدى
     * صلاحيات `_all|_assigned|_own` الثلاث (ذاك عمل الميدلوير).
     *
     * كانت `destroy` تعمل على أيّ معرّفٍ في الرابط: من يملك `_own` يحذف
     * ملاحظاتِ زملائه بتغيير الرقم. الآن: `_all` بلا قيد، وإلّا فكاتبُ
     * الملاحظة وحدَه.
     */
    private function authorizeNote(AppointmentNote $note): void
    {
        if (hasPermission('client_follow_up_edit_delete_notes_attachments_appointments_all')) {
            return;
        }

        if ($note->user_id && (int) $note->user_id === (int) auth()->id()) {
            return;
        }

        abort(403, __('reservations.errors.note_out_of_scope'));
    }

    /**
     * حذف ملاحظة محددة
     */
    public function destroy($id)
    {
        $note = AppointmentNote::find($id);

        if (! $note) {
            return redirect()->back()->with('toast_message', 'الملاحظة غير موجودة!')->with('toast_type', 'error');
        }

        $this->authorizeNote($note);

        try {
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $note,
                'حذف ملاحظة متابعة للعميل #'.$note->client_id,
                ['old' => ['notes' => $note->notes, 'client_id' => $note->client_id]]
            );

            $note->delete();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return redirect()->back()->with('toast_message', 'تعذّر حذف الملاحظة')->with('toast_type', 'error');
        }

        return redirect()->back()->with('toast_message', 'ملاحظة تم حذفها بنجاح!')->with('toast_type', 'success');
    }

    /**
     * تحميل مرفق
     */
    public function downloadAttachment($noteId, $index)
    {
        $note = AppointmentNote::findOrFail($noteId);
        $attachments = $note->attachments ?? [];

        if (! isset($attachments[$index])) {
            return back()->with('error', 'الملف غير موجود');
        }

        $path = self::UPLOAD_FOLDER.'/'.$attachments[$index];

        // كان السطر `return Storage::disk('public')->downloadAttachment;`
        // — خاصّيةٌ غير موجودة على القرص، أي خطأ في كل تنزيل
        if (! file_exists(public_path($path)) && ! file_exists(base_path($path))) {
            return back()->with('error', 'الملف غير موجود');
        }

        OperationLog::record('downloaded_attachment', self::LOG_MODULE, $note, 'تنزيل مرفق ملاحظة #'.$note->id);

        return response()->download(
            file_exists(public_path($path)) ? public_path($path) : base_path($path)
        );
    }
}
