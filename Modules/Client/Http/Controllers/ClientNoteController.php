<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Models\Location;
use App\Models\notifications;
use App\Services\PointsService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ملاحظات المتابعة على العميل («إضافة ملاحظة» الرئيسية وملاحظة الزيارة
 * السريعة) وملاحظات موظفي العميل («جهات الاتصال») — إضافةً وعرضاً وتعديلاً
 * وحذفاً، بمرفقاتها.
 *
 * منقولةٌ من `ClientController` (كانت 6600+ سطراً بمسؤوليات متعددة) بلا أي
 * تغيير في المنطق أو الصلاحيات أو أسماء الـ routes — راجع
 * `Modules/Client/routes/web.php` حيث بقيت أسماء الـ routes كما هي
 * (`clients.addnotes`، `clients.notes.edit`، …) وتغيّر الكنترولر المستهدَف
 * فقط، فلا شيء في Blade أو JavaScript يحتاج تعديلاً (كلّها تنادي بالاسم).
 *
 * @see \App\Constants\permissions\ClientsPermissions كتالوج الصلاحيات
 */
class ClientNoteController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط — نفس قيمة ClientController الأصلية،
     *  حتى لا يتغيّر تصنيف السجلّات القديمة والجديدة في شاشة سجلّ النشاط. */
    public const LOG_MODULE = 'Clients';

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary', [
            'only' => ['getLastNote'],
        ]);

        $this->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own', [
            'only' => ['getEmployeeNotes'],
        ]);

        $this->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note', [
            'only' => ['addnotes', 'quickVisitNote', 'storeEmployeeNote'],
        ]);

        $this->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own', [
            'only' => ['editNote', 'updateNote', 'destroyNote', 'deleteNoteAttachment'],
        ]);
    }

    /** نفس صيغة الحساب المستعملة في بقيّة كنترولرات الزيارات — النتيجة بالكيلومتر. */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat1) || is_null($lon1) || is_null($lat2) || is_null($lon2)) {
            return 0;
        }

        if (abs($lat1) > 90 || abs($lon1) > 180 || abs($lat2) > 90 || abs($lon2) > 180) {
            return 0;
        }

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));
        $distance = 6371000 * $c; // بالمتر

        return $distance / 1000; // تحويل إلى كيلومتر
    }

    // الحصول على آخر ملاحظة للعميل للتحقق من تغيير الموظف
    public function getLastNote($id)
    {
        $lastNote = ClientRelation::where('client_id', $id)
            ->with(['clientEmployee.employee'])
            ->latest()
            ->first();

        if (! $lastNote) {
            return response()->json(['lastNote' => null]);
        }

        $employeeName = 'غير معروف';
        if ($lastNote->clientEmployee && $lastNote->clientEmployee->employee) {
            $employee = $lastNote->clientEmployee->employee;
            $employeeName = $employee->nickname ?? ($employee->first_name.' '.$employee->middle_name);
        }

        return response()->json([
            'lastNote' => [
                'id' => $lastNote->id,
                'client_employee_id' => $lastNote->client_employee_id,
                'employee_name' => $employeeName,
            ],
        ]);
    }

    public function addnotes(Request $request)
    {
        $user = auth()->user();

        // التحقق من صلاحية إضافة ملاحظة من أي موقع — بالموظف، أو باستبعاد
        // العميل نفسه من فحص الموقع («استبعاد الموقع» في ملفّه، يحرسها
        // `clients_manage_exemptions`) — كانت الملاحظةُ وحدها تُحبَس بفحص
        // الموقع رغم استبعاد العميل، فيعلق من بدأ الزيارة من بعيد عند
        // خطوتها الثالثة رغم أنه معفيٌّ من العميل نفسه لا من صلاحيته هو
        $requestedClient = $request->filled('client_id') ? Client::find($request->input('client_id')) : null;
        $clientExemptFromLocation = $requestedClient
            && \App\Models\ClientModuleSetting::current()->exempt_location_enabled
            && $requestedClient->isExemptFromVisitLocation();

        // ومن يُدير الزيارة من أي موقع يكتب ملاحظتها من أي موقع — وإلا بدأها
        // من بعيد ثم علق عند خطوة الملاحظة
        $hasLocationBypassPermission = $clientExemptFromLocation
            || hasPermission(['client_follow_up_add_notes_from_any_location', 'clients_visit_any_location']);

        // التحقق من صحة البيانات المدخلة - جعل الموقع اختياري إذا كان لدى المستخدم صلاحية التجاوز
        $locationValidation = $hasLocationBypassPermission ? 'nullable|numeric' : 'required|numeric';

        /* «عدد العهدة» و«المرفقات» كانا إلزاميَّين مثبَّتَين هنا بلا مفتاح.
           صارا يتبعان إعدادات وحدات العملاء ← الزيارات: مطفأً لا يُعرض
           الحقل في الشاشة ولا يُطلب هنا — فلا يقع الحال الأسوأ: حقلٌ مخفيٌّ
           يرفض الخادمُ الحفظ بسببه. */
        $requireDeposit = ClientSettingsHelper::visitWorkspaceSectionVisible('note_deposit_count');
        $requirePhoto = ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo');
        // كم صورةً على الأقل؟ كان `min:1` مثبَّتاً، ومن أراد صورتين لا سبيل له
        $minPhotos = ClientSettingsHelper::noteMinPhotos();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'client_employee_id' => 'required|exists:client_employee_notes,id',
            'process' => 'nullable|string|max:255',
            'description' => 'required|string',
            'deposit_count' => ($requireDeposit ? 'required' : 'nullable').'|integer|min:0',

            'competitor_documents' => 'required|integer|min:0', // إلزامي
            'attachments' => ($requirePhoto ? 'required|array|min:'.$minPhotos : 'nullable|array'),
            'attachments.*' => 'file|mimes:jpg,jpeg,png|max:'.ClientSettingsHelper::maxUploadSizeKb(), // صور فقط
            'current_latitude' => $locationValidation,
            'current_longitude' => $locationValidation,
            'shift_start' => 'required',
            'shift_end' => 'required',
        ], [
            'deposit_count.required' => 'حقل عدد العهدة إلزامي',
            'client_employee_id.required' => 'حقل موظف العميل إلزامي',
            'client_employee_id.exists' => 'موظف العميل المحدد غير صالح',

            'competitor_documents.required' => 'حقل عدد استندات المنافسين إلزامي',
            'attachments.required' => 'يجب إرفاق '.$minPhotos.' صورة على الأقل',
            'attachments.min' => 'يجب إرفاق '.$minPhotos.' صورة على الأقل',
            'attachments.*.mimes' => 'يجب أن تكون المرفقات صور فقط (jpg, jpeg, png)',
        ]);

        DB::beginTransaction();

        try {
            // التحقق من الموقع للموظفين فقط (إذا لم يكن لديه صلاحية التجاوز)
            if (auth()->user()->isEmployee() && ! $hasLocationBypassPermission) {
                $employeeLocation = Location::where('employee_id', auth()->id())
                    ->whereNull('client_id')
                    ->latest()
                    ->first();

                // إنشاء أو تحديث موقع الموظف
                if (! $employeeLocation) {
                    $employeeLocation = Location::create([
                        'employee_id' => auth()->id(),
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                } else {
                    // تحديث الموقع الحالي
                    $employeeLocation->update([
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }

                // التحقق من موقع العميل
                $clientLocation = Location::where('client_id', $request->client_id)
                    ->clientPoint()
                    ->latest()
                    ->first();

                if (! $clientLocation) {
                    throw new \Exception('لم يتم تسجيل موقع العميل! الرجاء التواصل مع الإدارة.');
                }

                // حساب المسافة
                $distance = $this->calculateDistance(
                    $employeeLocation->latitude,
                    $employeeLocation->longitude,
                    $clientLocation->latitude,
                    $clientLocation->longitude
                );

                // التحقق من المسافة
                $maxDistance = ClientSettingsHelper::visitProximityRadiusKmFor(auth()->user());
                if ($distance > $maxDistance) {
                    throw new \Exception('يجب أن تكون ضمن نطاق '.$maxDistance.' كيلومتر من العميل! المسافة الحالية: '.round($distance, 2).' كم');
                }
            }

            /* لا إكمالَ لزيارة اليوم هنا: الملاحظة خطوةٌ في الزيارة لا
               نهايتُها. كانت تُعلِّم صفَّ خطّ السير مكتملاً فور حفظها، فيختفي
               العميل من خريطة المندوب وزيارته ما زالت مفتوحة. الإكمال من زرّ
               «إنهاء الزيارة» وحده — `VisitWorkspaceController::complete()`. */

            $client = Client::findOrFail($request->client_id);

            // دالة تنسيق الوقت بالعربي مع صباحي/مسائي
            $formatTimeArabic = function ($timeStr) {
                if (! $timeStr) {
                    return 'غير محدد';
                }
                $hour = (int) substr($timeStr, 0, 2);
                $minute = substr($timeStr, 3, 2);
                $period = $hour >= 12 ? 'مسائي' : 'صباحي';
                $h = $hour % 12;
                $h = $h ?: 12;

                return $h.':'.$minute.' '.($hour >= 12 ? 'مساءً' : 'صباحاً');
            };
            $shiftPeriod = ((int) substr($request->shift_start, 0, 2)) >= 12 ? 'مسائي' : 'صباحي';
            $shiftLabel = ClientEmployeeNote::isFullTimeRange($request->shift_start, $request->shift_end)
                ? ClientEmployeeNote::FULL_TIME_LABEL
                : $formatTimeArabic($request->shift_start).' إلى '.$formatTimeArabic($request->shift_end).' ('.$shiftPeriod.')';

            // إنشاء سجل الملاحظة
            $clientRelation = ClientRelation::create([
                'employee_id' => auth()->id(),
                'client_id' => $request->client_id,
                'status' => $request->status ?? 'pending',
                'process' => $request->process,
                'description' => $request->description.' | دوام المسؤول: '.$shiftLabel.' | عدد العهدة: '.$request->deposit_count,
                'client_employee_id' => $request->client_employee_id,
                'deposit_count' => $request->deposit_count,
                'site_type' => $request->site_type ?? 'markets',
                'competitor_documents' => $request->competitor_documents,
                'shift_start' => $request->shift_start,
                'shift_end' => $request->shift_end,
                'additional_data' => json_encode([
                    'deposit_count' => $request->deposit_count,
                    'competitor_documents' => $request->competitor_documents,
                    'shift_start' => $request->shift_start,
                    'shift_end' => $request->shift_end,
                    'latitude' => $request->current_latitude,
                    'longitude' => $request->current_longitude,
                ]),
            ]);

            // إنشاء إشعار واحد فقط
            $notificationData = [
                'user_id' => auth()->id(),
                'receiver_id' => auth()->id(),
                'type' => 'client_note',
                'title' => 'تم إضافة ملاحظة للعميل '.$client->trade_name,
                'description' => $request->description.' | دوام المسؤول: '.$shiftLabel.' | عدد العهدة: '.$request->deposit_count,
                'read' => false,
            ];

            notifications::create($notificationData);

            $client->last_note_at = now();
            $client->save();

            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $destinationPath = public_path('assets/uploads/notes');

                        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg', 'image/png'])) {
                            // حفظ الصور بصيغة JPEG دائماً للضغط الأفضل
                            $filename = time().'_'.uniqid().'.jpg';

                            // حساب جودة الضغط حسب حجم الملف
                            $originalSize = $file->getSize();
                            $quality = 75; // جودة افتراضية

                            if ($originalSize > 10 * 1024 * 1024) {
                                $quality = 40; // ضغط أكثر للملفات الكبيرة جداً
                            } elseif ($originalSize > 5 * 1024 * 1024) {
                                $quality = 50;
                            } elseif ($originalSize > 2 * 1024 * 1024) {
                                $quality = 60;
                            }

                            $this->compressAndSaveImage($file->path(), $destinationPath.'/'.$filename, $quality);
                        } else {
                            // للملفات الأخرى (PDF وغيرها)
                            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

                            if ($file->getMimeType() === 'application/pdf' && $file->getSize() > 5 * 1024 * 1024) {
                                $file->move($destinationPath, $filename);
                            } else {
                                $file->move($destinationPath, $filename);
                            }
                        }

                        $attachments[] = $filename;
                    }
                }
                $clientRelation->attachments = json_encode($attachments);
                $clientRelation->save();
            }

            if (auth()->user()->isEmployee()) {
                $location = Location::where('employee_id', auth()->id())
                    ->whereNull('client_id')
                    ->latest()
                    ->first();

                if ($location) {
                    $location->update([
                        'client_relation_id' => $clientRelation->id,
                        'client_id' => $request->client_id,
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }
            }

            OperationLog::record(
                'note_added',
                self::LOG_MODULE,
                $client,
                'إضافة ملاحظة للعميل: '.$request->description.' — دوام المسؤول: '.$shiftLabel.' — عدد العهدة: '.$request->deposit_count
            );

            try {
                $employeeForPoints = auth()->user() ? (auth()->user()->employee_id ?? auth()->id()) : null;
                // منح النقاط للموظف لإضافة ملاحظة
                app(PointsService::class)->processAction('note_created', [
                    'employee_id' => $employeeForPoints,
                    'client_id' => $request->client_id,
                    'description' => 'تسجيل ملاحظة متابعة من الموظف',
                    'reference_type' => get_class($clientRelation),
                    'reference_id' => $clientRelation->id,
                ]);
            } catch (\Exception $e) {
                \Log::warning('فشل إضافة نقاط إضافة ملاحظة: '.$e->getMessage());
            }

            // تقييم اكتمال خط السير اليومي
            try {
                app(PointsService::class)->evaluateItineraryCompletion(
                    auth()->id(),
                    now()->format('Y-m-d')
                );
            } catch (\Exception $e) {
                \Log::warning('فشل تقييم اكتمال خط السير بعد إضافة ملاحظة: '.$e->getMessage());
            }

            DB::commit();

            $successMessage = 'تم إضافة الملاحظة بنجاح'.($client->wasChanged('status_id') ? ' وتغيير حالة العميل إلى متابعة!' : '!');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'note' => [
                        'id' => $clientRelation->id,
                        'client_id' => $clientRelation->client_id,
                        'description' => $clientRelation->description,
                        'process' => $clientRelation->process,
                        'created_at' => $clientRelation->created_at->format('Y-m-d H:i'),
                    ],
                ]);
            }

            return redirect()
                ->route('clients.show', $request->client_id)
                ->with('success', $successMessage);
        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'addnotes');

            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $e->validator->errors()], 422);
            }

            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'addnotes');

            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل إضافة الملاحظة: '.$e->getMessage()], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'فشل إضافة الملاحظة: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * إضافة ملاحظة زيارة سريعة (عدد العهدة + صورة من الكاميرا + GPS عند موقع العميل)
     * — تُستخدم من Modal في صفحة العميل لإكمال زيارة الموظف وفتح بقية الإجراءات.
     */
    public function quickVisitNote(Request $request)
    {
        $user = auth()->user();
        $requestedClient = $request->filled('client_id') ? Client::find($request->input('client_id')) : null;
        $clientExemptFromLocation = $requestedClient
            && \App\Models\ClientModuleSetting::current()->exempt_location_enabled
            && $requestedClient->isExemptFromVisitLocation();
        $hasLocationBypassPermission = $clientExemptFromLocation
            || ($user && hasPermission(['client_follow_up_add_notes_from_any_location', 'clients_visit_any_location']));
        $locationRule = $hasLocationBypassPermission ? 'nullable|numeric' : 'required|numeric';

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'deposit_count' => 'required|integer|min:0',
            'current_latitude' => $locationRule,
            'current_longitude' => $locationRule,
            'attachments' => 'required|array|min:1',
            'attachments.*' => 'required|file|mimes:jpg,jpeg,png|max:'.ClientSettingsHelper::maxUploadSizeKb(),
        ], [
            'deposit_count.required' => 'يجب إدخال عدد العهدة',
            'deposit_count.integer' => 'عدد العهدة يجب أن يكون رقماً صحيحاً',
            'deposit_count.min' => 'عدد العهدة لا يمكن أن يكون أقل من صفر',
            'current_latitude.required' => 'يجب تحديد موقعك الحالي (فعّل GPS).',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي (فعّل GPS).',
            'attachments.required' => 'يجب إرفاق صورة من الكاميرا',
            'attachments.min' => 'يجب إرفاق صورة من الكاميرا',
            'attachments.*.mimes' => 'الصور يجب أن تكون صورة (jpg/jpeg/png)',
            'attachments.*.max' => 'حجم الصورة لا يتجاوز 10 ميجابايت',
        ]);

        DB::beginTransaction();
        try {
            // تحقق GPS — الموظف فقط (وليس لديه صلاحية تجاوز الموقع)
            if ($user && $user->isEmployee() && ! $hasLocationBypassPermission) {
                $employeeLocation = Location::where('employee_id', $user->id)
                    ->whereNull('client_id')
                    ->latest()
                    ->first();

                if (! $employeeLocation) {
                    Location::create([
                        'employee_id' => $user->id,
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                } else {
                    $employeeLocation->update([
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }

                $clientLocation = Location::where('client_id', $request->client_id)->clientPoint()->latest()->first();
                if (! $clientLocation) {
                    DB::rollBack();

                    $message = 'لم يتم تسجيل موقع العميل! الرجاء التواصل مع الإدارة.';

                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }

                    return redirect()->back()->with('error', $message)->withInput();
                }

                $distance = $this->calculateDistance(
                    $request->current_latitude,
                    $request->current_longitude,
                    $clientLocation->latitude,
                    $clientLocation->longitude,
                );

                $maxDistance = ClientSettingsHelper::visitProximityRadiusKmFor($user);
                if ($distance > $maxDistance) {
                    DB::rollBack();

                    $message = 'يجب أن تكون ضمن نطاق '.round($maxDistance * 1000).' متر من العميل! المسافة الحالية: '.round($distance * 1000).' متر';

                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }

                    return redirect()->back()->with('error', $message)->withInput();
                }
            }

            $clientRelation = ClientRelation::create([
                'employee_id' => $user->id,
                'client_id' => $request->client_id,
                'description' => 'ملاحظة زيارة — عدد العهدة: '.(int) $request->deposit_count,
                'deposit_count' => (int) $request->deposit_count,
                'date' => now()->toDateString(),
                'time' => now()->format('H:i:s'),
                'status' => 'completed',
                'process' => 'visit_note',
                'additional_data' => [
                    'deposit_count' => (int) $request->deposit_count,
                    'latitude' => $request->current_latitude,
                    'longitude' => $request->current_longitude,
                ],
            ]);

            $destinationPath = public_path('assets/uploads/notes');
            if (! file_exists($destinationPath)) {
                @mkdir($destinationPath, 0755, true);
            }

            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                if (! $file->isValid()) {
                    continue;
                }

                $mime = $file->getMimeType();
                if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
                    // ضغط الصور وحفظها بصيغة JPEG (جودة متغيرة حسب الحجم)
                    $filename = time().'_'.uniqid().'.jpg';
                    $size = $file->getSize();
                    $quality = 75;
                    if ($size > 10 * 1024 * 1024) {
                        $quality = 40;
                    } elseif ($size > 5 * 1024 * 1024) {
                        $quality = 50;
                    } elseif ($size > 2 * 1024 * 1024) {
                        $quality = 60;
                    }

                    $this->compressAndSaveImage($file->path(), $destinationPath.'/'.$filename, $quality);
                } else {
                    $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                    $file->move($destinationPath, $filename);
                }

                $attachments[] = $filename;
            }
            if (! empty($attachments)) {
                $clientRelation->attachments = json_encode($attachments);
                $clientRelation->save();
            }

            $client = Client::find($request->client_id);
            if ($client) {
                $client->last_note_at = now();
                $client->save();
            }

            // ربط موقع الموظف بسجل الملاحظة
            if ($user && $user->isEmployee() && $request->filled(['current_latitude', 'current_longitude'])) {
                $loc = Location::where('employee_id', $user->id)
                    ->whereNull('client_id')
                    ->latest()
                    ->first();
                if ($loc) {
                    $loc->update([
                        'client_relation_id' => $clientRelation->id,
                        'client_id' => $request->client_id,
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }
            }

            // لا إكمالَ للزيارة هنا — الصورة والعهدة خطوةٌ فيها، والإنهاء من
            // زرّ «إنهاء الزيارة» وحده (راجع addnotes() أعلاه)

            OperationLog::record(
                'quick_visit_note_added',
                self::LOG_MODULE,
                (int) $request->client_id,
                'إضافة ملاحظة زيارة سريعة من صفحة العميل'
            );

            try {
                app(PointsService::class)->processAction('note_created', [
                    'employee_id' => auth()->id(),
                    'client_id' => $request->client_id,
                    'description' => 'ملاحظة زيارة (سريعة)',
                    'reference_type' => get_class($clientRelation),
                    'reference_id' => $clientRelation->id,
                ]);
                app(PointsService::class)->evaluateItineraryCompletion(
                    auth()->id(), now()->toDateString()
                );
            } catch (\Exception $e) {
                \Log::warning('فشل إضافة نقاط ملاحظة الزيارة السريعة: '.$e->getMessage());
            }

            DB::commit();

            $successMessage = 'تم إضافة ملاحظة الزيارة بنجاح. الإجراءات الأخرى مفعَّلة الآن.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'note' => [
                        'id' => $clientRelation->id,
                        'client_id' => $clientRelation->client_id,
                        'description' => $clientRelation->description,
                        'created_at' => $clientRelation->created_at->format('Y-m-d H:i'),
                    ],
                ]);
            }

            return redirect()->route('clients.show', $request->client_id)
                ->with('success', $successMessage);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'quickVisitNote');

            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل حفظ ملاحظة الزيارة: '.$e->getMessage()], 500);
            }

            return redirect()->back()
                ->with('error', 'فشل حفظ ملاحظة الزيارة: '.$e->getMessage())
                ->withInput();
        }
    }

    public function storeEmployeeNote(Request $request, $id)
    {
        try {
            /* «دوام المسؤول» يُسجَّل هنا، مع المسؤول نفسه، لا في خطوةٍ
               تالية ولا في كل ملاحظة. حقلان — «من» و«إلى» — لا فترةٌ
               جاهزة: مسؤولٌ يداوم من ٩ صباحاً إلى ٣ عصراً لم يكن له في
               القائمة القديمة خيارٌ يصفه.

               إلزاميّان: مسؤولٌ بلا دوامٍ يوقف خطوات الزيارة عند الخطوة
               الثانية (`VisitWorkspaceController::stepState()`)، فالسماح
               بإضافته ناقصاً يصنع طريقاً مسدوداً لا يفهمه المندوب. */
            \App\Rules\SaudiPhone::normalizeRequest($request, ['employee_phone']);

            $request->validate([
                'employee_name' => 'required|string|max:255',
                'employee_position' => 'nullable|string|max:100',
                'employee_phone' => ['required', 'string', 'max:20', new \App\Rules\SaudiPhone],
                // قائمةٌ واحدة «من – إلى» قيمتها «HH:MM|HH:MM»
                'shift' => ['required', 'string', function ($attribute, $value, $fail) {
                    if (ClientEmployeeNote::parseShiftRange($value) === null) {
                        $fail('فترة الدوام المختارة غير صالحة.');
                    }
                }],
                'is_primary' => 'nullable|boolean',
                'invoice_id' => 'nullable|exists:invoices,id',
                'note' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:'.ClientSettingsHelper::maxUploadSizeKb(),
            ], [
                'employee_name.required' => 'اسم الموظف إلزامي',
                'employee_phone.required' => 'رقم جوال الموظف إلزامي',
                'shift.required' => 'دوام المسؤول إلزامي — اختر فترة من القائمة',
            ]);

            [$shiftStart, $shiftEnd] = ClientEmployeeNote::parseShiftRange($request->shift);

            $user = auth()->user();
            $data = [
                'client_id' => $id,
                'employee_id' => $user->id,
                'employee_name' => $request->employee_name,
                'employee_position' => $request->employee_position ?? 'موظف',
                'employee_phone' => $request->employee_phone,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'is_primary' => $request->is_primary ? true : false,
                'invoice_id' => $request->invoice_id,

            ];

            // معالجة الصورة وضغطها إن وجدت
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time().'_'.uniqid().'.jpg';
                $destinationPath = public_path('uploads/client_notes');

                // إنشاء المجلد إذا لم يكن موجوداً
                if (! file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // ضغط الصورة وحفظها بجودة 70%
                $this->compressAndSaveImage($image->getRealPath(), $destinationPath.'/'.$imageName, 70);
                $data['image'] = 'uploads/client_notes/'.$imageName;
            }

            $note = ClientEmployeeNote::create($data);

            OperationLog::record(
                'employee_note_added',
                self::LOG_MODULE,
                (int) $id,
                'إضافة ملاحظة موظف العميل «'.$request->employee_name.'» للعميل #'.$id
            );

            // Award points for note creation
            try {
                // Determine the correct employee/user for points
                $pointsUser = $user;
                app(PointsService::class)->processAction('note_created', [
                    'employee_id' => $pointsUser->id,
                    'client_id' => $id,
                    'description' => 'تسجيل ملاحظة موظف عميل',
                    'reference_type' => get_class($note),
                    'reference_id' => $note->id,
                ]);
            } catch (\Exception $e) {
                \Log::warning('فشل إضافة نقاط ملاحظة موظف العميل: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة موظف العميل بنجاح',
                'note' => [
                    'id' => $note->id,
                    'note' => $note->note,
                    'image' => $note->image,
                    'employee_name' => $note->employee_name,
                    'employee_position' => $note->employee_position,
                    'employee_phone' => $note->employee_phone,
                    'shift_start' => $note->shift_start,
                    'shift_end' => $note->shift_end,
                    'shift_label' => $note->shift_label,
                    'is_primary' => $note->is_primary,
                    'invoice_id' => $note->invoice_id,
                    'created_at' => $note->created_at->format('Y-m-d H:i'),
                ],
            ]);

        } catch (ValidationException $e) {
            /* `ValidationException` ترث `Exception`، فكان `catch` العام أدناه
               يبتلعها ويردّ 500 برسالة «حدث خطأ: The given data was invalid» —
               والمودال يعرض `message` كما هي، فلا يعرف المستخدم أيّ حقلٍ
               نقص. تُلتقط قبله الآن وتُردّ 422 بأخطاء الحقول نفسها. */
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'storeEmployeeNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب ملاحظات العميل من الموظفين
     */
    public function getEmployeeNotes($id)
    {
        try {
            $rows = ClientEmployeeNote::where('client_id', $id)
                ->with('employee') // جلب بيانات موظف النظام الذي أنشأ الملاحظة
                ->latest()
                ->get();

            // رقمُ الفاتورة المرتبطة — استعلامٌ واحد لا واحدٌ لكل ملاحظة
            $invoiceCodes = \App\Models\Invoice::whereIn('id', $rows->pluck('invoice_id')->filter())->pluck('code', 'id');

            $notes = $rows->map(function ($note) use ($invoiceCodes) {
                    return [
                        'id' => $note->id,
                        'note' => $note->note,
                        'image' => $note->image,
                        'employee_name' => $note->employee_name ?? 'غير محدد', // موظف العميل
                        'employee_position' => $note->employee_position ?? 'موظف', // وظيفة موظف العميل
                        'created_by' => $note->employee->name ?? 'غير معروف', // موظف النظام الذي أنشأ الملاحظة
                        'created_at' => $note->created_at->format('Y-m-d H:i'),
                        // حقول بطاقة «ملاحظات الموظفين» (تصميم ملاحظات الزيارات)
                        'created_date' => $note->created_at->format('Y-m-d'),
                        'created_time' => $note->created_at->format('h:i').' '.($note->created_at->format('A') === 'AM' ? 'ص' : 'م'),
                        'image_url' => $note->image ? asset($note->image) : null,
                        'employee_phone' => $note->employee_phone,
                        'is_primary' => (bool) $note->is_primary,
                        'invoice_code' => $note->invoice_id ? ($invoiceCodes[$note->invoice_id] ?? $note->invoice_id) : null,
                        'invoice_url' => $note->invoice_id ? route('invoices.show', $note->invoice_id) : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'notes' => $notes,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'getEmployeeNotes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ضغط الصورة وتحجيمها وحفظها - استخدام GD للضغط الفعلي
     */
    private function compressAndSaveImage($sourceFile, $destinationPath, $quality = 70)
    {
        try {
            $imageInfo = getimagesize($sourceFile);
            if (! $imageInfo) {
                return false;
            }

            $mime = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];

            // إنشاء الصورة حسب النوع
            switch ($mime) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($sourceFile);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($sourceFile);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($sourceFile);
                    break;
                default:
                    $image = imagecreatefromjpeg($sourceFile);
            }

            // تحجيم الصورة إذا كانت أكبر من 1920x1920
            $maxDimension = 1920;
            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                $ratio = min($maxDimension / $originalWidth, $maxDimension / $originalHeight);
                $newWidth = intval($originalWidth * $ratio);
                $newHeight = intval($originalHeight * $ratio);

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled(
                    $resizedImage,
                    $image,
                    0,
                    0,
                    0,
                    0,
                    $newWidth,
                    $newHeight,
                    $originalWidth,
                    $originalHeight
                );
                imagedestroy($image);
                $image = $resizedImage;
            }

            // تعيين الامتداد الصحيح
            if (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'png') {
                imagepng($image, $destinationPath, intval($quality / 10)); // جودة PNG 0-9
            } elseif (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'gif') {
                imagegif($image, $destinationPath);
            } else {
                // حفظ بصيغة JPEG مع جودة منخفضة للضغط الأفضل
                imagejpeg($image, $destinationPath, intval($quality));
            }

            imagedestroy($image);

            return true;
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'compressAndSaveImage');

            return false;
        }
    }

    /**
     * عرض صفحة تعديل الملاحظة
     */
    public function editNote(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::with(['client', 'employee', 'clientEmployee'])->findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
            ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذه الملاحظة'], 403);
            }

            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل هذه الملاحظة');
        }

        $client = $note->client;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'note' => [
                    'id' => $note->id,
                    'client_id' => $note->client_id,
                    'client_employee_id' => $note->client_employee_id,
                    'description' => $note->description,
                    'deposit_count' => $note->deposit_count,
                    'site_type' => $note->site_type,
                    'competitor_documents' => $note->competitor_documents,
                    'shift_start' => $note->shift_start,
                    'shift_end' => $note->shift_end,
                    'attachments_array' => json_decode($note->attachments, true) ?? [],
                ],
            ]);
        }

        return view('client::notes.edit_client_note', compact('note', 'client'));
    }

    /**
     * تحديث الملاحظة
     */
    public function updateNote(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
            ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذه الملاحظة'], 403);
            }

            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل هذه الملاحظة');
        }

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'client_employee_id' => 'required|exists:client_employee_notes,id',
            'description' => 'required|string',
            'deposit_count' => 'required|integer|min:0',
            'site_type' => 'required|in:independent_booth,grocery,supplies,markets,station',
            'competitor_documents' => 'required|integer|min:0',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png|max:'.ClientSettingsHelper::maxUploadSizeKb(),
            'shift_start' => 'required',
            'shift_end' => 'required',
        ], [
            'client_employee_id.required' => 'حقل موظف العميل إلزامي',
            'client_employee_id.exists' => 'موظف العميل المحدد غير صالح',
            'deposit_count.required' => 'حقل عدد العهدة إلزامي',
            'site_type.required' => 'حقل نوع الموقع إلزامي',
            'site_type.in' => 'نوع الموقع غير صالح',
            'competitor_documents.required' => 'حقل عدد استندات المنافسين إلزامي',
            'attachments.*.mimes' => 'يجب أن تكون المرفقات صور فقط (jpg, jpeg, png)',
        ]);

        DB::beginTransaction();

        try {
            // دالة تنسيق الوقت بالعربي مع صباحي/مسائي
            $formatTimeArabic = function ($timeStr) {
                if (! $timeStr) {
                    return 'غير محدد';
                }
                $hour = (int) substr($timeStr, 0, 2);
                $minute = substr($timeStr, 3, 2);
                $h = $hour % 12;
                $h = $h ?: 12;

                return $h.':'.$minute.' '.($hour >= 12 ? 'مساءً' : 'صباحاً');
            };
            $shiftPeriod = ((int) substr($request->shift_start, 0, 2)) >= 12 ? 'مسائي' : 'صباحي';
            $shiftLabel = ClientEmployeeNote::isFullTimeRange($request->shift_start, $request->shift_end)
                ? ClientEmployeeNote::FULL_TIME_LABEL
                : $formatTimeArabic($request->shift_start).' إلى '.$formatTimeArabic($request->shift_end).' ('.$shiftPeriod.')';

            // تحديث البيانات الأساسية
            $note->update([
                'description' => $request->description.' | دوام المسؤول: '.$shiftLabel.' | عدد العهدة: '.$request->deposit_count,
                'client_employee_id' => $request->client_employee_id,
                'deposit_count' => $request->deposit_count,
                'site_type' => $request->site_type,
                'shift_start' => $request->shift_start,
                'shift_end' => $request->shift_end,
                'additional_data' => json_encode([
                    'deposit_count' => $request->deposit_count,
                    'site_type' => $request->site_type,
                    'competitor_documents' => $request->competitor_documents,
                    'shift_start' => $request->shift_start,
                    'shift_end' => $request->shift_end,
                ]),
            ]);

            // معالجة المرفقات الجديدة إذا وجدت
            if ($request->hasFile('attachments')) {
                $existingAttachments = json_decode($note->attachments, true) ?? [];
                $attachments = $existingAttachments;

                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $destinationPath = public_path('assets/uploads/notes');
                        $filename = time().'_'.uniqid().'.jpg';

                        // ضغط وحفظ الصورة
                        $originalSize = $file->getSize();
                        $quality = 75;
                        if ($originalSize > 10 * 1024 * 1024) {
                            $quality = 40;
                        } elseif ($originalSize > 5 * 1024 * 1024) {
                            $quality = 50;
                        } elseif ($originalSize > 2 * 1024 * 1024) {
                            $quality = 60;
                        }

                        $this->compressAndSaveImage($file->path(), $destinationPath.'/'.$filename, $quality);
                        $attachments[] = $filename;
                    }
                }

                $note->attachments = json_encode($attachments);
                $note->save();
            }

            // تسجيل السجل
            OperationLog::record(
                'note_updated',
                self::LOG_MODULE,
                (int) $note->client_id,
                'تعديل ملاحظة للعميل «'.$note->client->trade_name.'» — دوام المسؤول: '.$shiftLabel.' — عدد العهدة: '.$request->deposit_count
            );

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تعديل الملاحظة بنجاح!',
                    'note' => [
                        'id' => $note->id,
                        'description' => $note->description,
                        'attachments_array' => json_decode($note->attachments, true) ?? [],
                    ],
                ]);
            }

            return redirect()
                ->route('clients.show', $note->client_id)
                ->with('success', 'تم تعديل الملاحظة بنجاح!');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'updateNote');

            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل تعديل الملاحظة: '.$e->getMessage()], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'فشل تعديل الملاحظة: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * حذف الملاحظة
     */
    public function destroyNote(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canDelete = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
            ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canDelete) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية حذف هذه الملاحظة'], 403);
            }

            return redirect()->back()->with('error', 'ليس لديك صلاحية حذف هذه الملاحظة');
        }

        $clientId = $note->client_id;
        $clientName = $note->client->trade_name ?? 'غير معروف';

        DB::beginTransaction();

        try {
            // حذف المرفقات من الخادم
            $attachments = json_decode($note->attachments, true);
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    $filePath = public_path('assets/uploads/notes/'.$attachment);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            // حذف الملاحظة
            $note->delete();

            // تسجيل السجل
            OperationLog::record(
                'note_deleted',
                self::LOG_MODULE,
                (int) $clientId,
                'حذف ملاحظة للعميل «'.$clientName.'»'
            );

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'تم حذف الملاحظة بنجاح!']);
            }

            return redirect()
                ->route('clients.show', $clientId)
                ->with('toast_message', 'تم حذف الملاحظة بنجاح!')
                ->with('toast_type', 'success');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Client', 'destroyNote');

            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل حذف الملاحظة: '.$e->getMessage()], 500);
            }

            return redirect()
                ->back()
                ->with('toast_message', 'فشل حذف الملاحظة: '.$e->getMessage())
                ->with('toast_type', 'error');
        }
    }

    /**
     * صفحة طباعة ملاحظة واحدة — نفس تسلسل التحقق المستعمل في editNote/destroyNote.
     */
    public function printNote($noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::with(['client', 'employee', 'clientEmployee'])->findOrFail($noteId);

        $canView = $user->can('client_follow_up_view_notes_attachments_appointments_all') ||
            ($user->can('client_follow_up_view_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canView) {
            abort(403, 'ليس لديك صلاحية عرض هذه الملاحظة.');
        }

        return view('client::notes.print', ['note' => $note, 'client' => $note->client]);
    }

    /**
     * حذف مرفق من الملاحظة
     */
    public function deleteNoteAttachment(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
            ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذه الملاحظة'], 403);
        }

        $attachmentIndex = $request->input('attachment_index');
        $attachments = json_decode($note->attachments, true) ?? [];

        if (! isset($attachments[$attachmentIndex])) {
            return response()->json(['success' => false, 'message' => 'المرفق غير موجود'], 404);
        }

        try {
            // حذف الملف من الخادم
            $filePath = public_path('assets/uploads/notes/'.$attachments[$attachmentIndex]);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // إزالة من المصفوفة
            array_splice($attachments, $attachmentIndex, 1);
            $note->attachments = json_encode(array_values($attachments));
            $note->save();

            OperationLog::record(
                'note_attachment_deleted',
                self::LOG_MODULE,
                $note,
                'حذف مرفق من ملاحظة العميل #'.$note->client_id
            );

            return response()->json(['success' => true, 'message' => 'تم حذف المرفق بنجاح']);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'delete_note_attachment', $note);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء حذف المرفق'], 500);
        }
    }
}
