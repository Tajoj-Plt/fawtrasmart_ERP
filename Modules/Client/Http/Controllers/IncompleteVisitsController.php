<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeClientVisit;
use App\Models\notifications;
use App\Models\User;
use App\Models\VisitJustification;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * تبريرات الزيارات — الجزء الذاتي-الخدمة (الموظف يقدّم ويعدّل تبريره هو).
 * منقولةٌ حرفياً من `App\Http\Controllers\IncompleteVisitsController` إلى
 * وحدة العملاء — هذا هو المتحكّم الحقيقي المستعمَل فعلاً من الواجهة
 * (أسماء الروابط `employee.visit-justifications.*`/`incomplete.visits.*`،
 * مرتبطةٌ من الشريط الجانبي في مكانين)؛ لا تخلطه بطرق «الموظف» الميتة التي
 * كانت على `App\Http\Controllers\Admin\VisitJustificationController` ولم
 * تُنقَل لعدم استعمالها إطلاقاً.
 *
 * @see \Modules\Client\Http\Controllers\VisitJustificationController الجزء الإداري
 */
class IncompleteVisitsController extends Controller
{
    private const LOG_MODULE = 'VisitJustification';

    /**
     * Show the justification form for incomplete visits.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showJustificationForm()
    {
        $user = Auth::user();

        // التحقق من ما إذا كان الموظف معفى من تقديم التبريرات
        if (hasPermission('staff_visit_justification_exempt')) {
            return redirect()->route('dashboard_sales.index')
                ->with('info', '✅ أنت معفى من تقديم تبريرات الزيارات غير المكتملة.');
        }

        // الزيارات التي تحتاج تبريراً نصّياً فعلاً — راجع
        // EmployeeClientVisit::needingJustificationQuery(): تستثني تلقائياً
        // أي تاريخٍ تغطّيه فترة تبريرٍ معتمدة (VisitJustification)، بصرف
        // النظر عن صلاحيات الموظف الحالية.
        $incompleteVisits = EmployeeClientVisit::needingJustificationQuery($user->id)
            ->with('client')
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        \Log::info('Incomplete visits retrieved', [
            'user_id' => $user->id,
            'count' => $incompleteVisits->count(),
            'visits' => $incompleteVisits->map(fn ($v) => [
                'id' => $v->id,
                'status' => $v->status,
                'justification' => $v->justification,

            ])->toArray(),
        ]);

        return view('client::visit_justifications.incomplete.justification', compact('incompleteVisits'));
    }

    /**
     * Process the justification form submission.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function submitJustification(Request $request)
    {
        $user = Auth::user();

        // تسجيل كامل البيانات المرسلة
        \Log::info('=== START: submitJustification ===', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'all_request_data' => $request->all(),
            'has_justifications' => $request->has('justifications'),
            'justifications_is_array' => is_array($request->justifications ?? null),
        ]);

        // التحقق من أن البيانات موجودة
        if (! $request->has('justifications')) {
            \Log::error('No justifications key in request', ['request_keys' => array_keys($request->all())]);

            return redirect()->back()
                ->with('error', '❌ خطأ: لم يتم إرسال حقل التبريرات. يرجى المحاولة مرة أخرى.')
                ->withInput();
        }

        if (! is_array($request->justifications)) {
            \Log::error('Justifications is not an array', ['type' => gettype($request->justifications)]);

            return redirect()->back()
                ->with('error', '❌ خطأ: صيغة البيانات غير صحيحة.')
                ->withInput();
        }

        if (empty($request->justifications)) {
            \Log::warning('Empty justifications array received');

            return redirect()->back()
                ->with('error', '❌ خطأ: لم يتم إرسال أي تبريرات.')
                ->withInput();
        }

        // التحقق من الـ validation
        $request->validate([
            'justifications' => 'required|array',
            'justifications.*' => 'required|string|min:5|max:1000',
        ], [
            'justifications.required' => 'يجب تقديم تبرير لكل زيارة غير مكتملة',
            'justifications.*.required' => 'التبرير مطلوب لكل زيارة غير مكتملة',
            'justifications.*.min' => 'التبرير يجب أن يكون 5 أحرف على الأقل',
            'justifications.*.max' => 'التبرير يجب ألا يتجاوز 1000 حرف',
        ]);

        $justificationSubmitted = false;
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        try {
            \Log::info('Processing justifications', [
                'user_id' => $user->id,
                'justifications_count' => count($request->justifications),
            ]);

            // Process each justification
            foreach ($request->justifications as $visitId => $justification) {
                \Log::info('Processing visit', [
                    'visit_id' => $visitId,
                    'justification_length' => strlen($justification ?? ''),
                    'justification_preview' => substr($justification ?? '', 0, 50),
                ]);

                // قم بـ trim لإزالة المسافات الزائدة
                $justification = trim($justification ?? '');

                if (empty($justification)) {
                    $failedCount++;
                    $errors[] = "الزيارة #$visitId: التبرير فارغ";
                    \Log::warning('Empty justification after trim', ['visit_id' => $visitId]);

                    continue;
                }

                // البحث عن الزيارة بدون شروط معقدة
                $visit = EmployeeClientVisit::find($visitId);

                if (! $visit) {
                    $failedCount++;
                    $errors[] = "الزيارة #$visitId: لم يتم العثور عليها في قاعدة البيانات";
                    \Log::error('Visit not found by ID', [
                        'visit_id' => $visitId,
                        'employee_id' => $user->id,
                        'all_visit_ids' => EmployeeClientVisit::where('employee_id', $user->id)->pluck('id')->toArray(),
                    ]);

                    continue;
                }

                // التحقق من أن الزيارة تابعة للموظف الحالي
                if ($visit->employee_id != $user->id) {
                    $failedCount++;
                    $errors[] = "الزيارة #$visitId: لا تابعة لك";
                    \Log::error('Visit does not belong to employee', [
                        'visit_id' => $visitId,
                        'visit_employee_id' => $visit->employee_id,
                        'current_employee_id' => $user->id,
                    ]);

                    continue;
                }

                // تحديث التبرير
                $visit->justification = $justification;
                $visit->justification_date = now();
                // Pending approval (ensure it's 0, not NULL)
                $saved = $visit->save();

                if ($saved) {
                    $justificationSubmitted = true;
                    $successCount++;

                    OperationLog::record(
                        'visit_justification_submitted',
                        self::LOG_MODULE,
                        $visit,
                        'تقديم تبرير لزيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» غير المكتملة',
                        ['old' => ['justification' => null], 'new' => ['justification' => $justification]]
                    );
                } else {
                    $failedCount++;
                    $errors[] = "الزيارة #$visitId: فشل الحفظ في قاعدة البيانات";
                    \Log::error('Failed to save visit', ['visit_id' => $visitId]);
                }
            }

            // Send notification to managers if justification was submitted
            if ($justificationSubmitted) {
                try {
                    $managers = User::where(function ($query) {
                        $query->where('role', 'admin')
                            ->orWhere('role', 'manager');
                    })->get();

                    \Log::info('Found managers for notification', ['count' => $managers->count()]);

                    foreach ($managers as $manager) {
                        notifications::create([
                            'user_id' => $manager->id,
                            'type' => 'visit',
                            'title' => 'تبرير زيارة جديد',
                            'message' => 'قام الموظف '.$user->name.' بتقديم '.$successCount.' تبرير(ات) لزيارات غير مكتملة.',
                            'read' => false,
                            'data' => json_encode([
                                'employee_id' => $user->id,
                                'employee_name' => $user->name,
                                'count' => $successCount,
                            ]),
                        ]);
                    }
                } catch (\Exception $notifException) {
                    \Log::warning('Error sending notifications: '.$notifException->getMessage());
                }
            }

            if ($successCount > 0) {
                $message = '✅ تم تقديم '.$successCount.' تبرير(ات) بنجاح. سيتم مراجعتها من قبل الإدارة.';
                if ($failedCount > 0) {
                    $message .= ' ⚠️ فشل: '.$failedCount;
                }

                return redirect()->route('incomplete.visits.justification')
                    ->with('success', $message);
            } else {
                $errorMessage = '❌ فشل تقديم التبريرات:';
                if (! empty($errors)) {
                    $errorMessage .= ' '.implode(' | ', $errors);
                } else {
                    $errorMessage .= ' لم يتم تحديث أي زيارات';
                }

                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->withInput();
            }
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'submit_justification', $user->id);

            return redirect()->back()
                ->with('error', '❌ حدث خطأ: '.$e->getMessage())
                ->withInput();
        } finally {
            \Log::info('=== END: submitJustification ===', [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);
        }
    }

    /**
     * Show all justifications submitted by the employee.
     *
     * @return \Illuminate\View\View
     */
    public function showMyJustifications()
    {
        $user = Auth::user();

        // التحقق من ما إذا كان الموظف معفى من تقديم التبريرات
        if (hasPermission('staff_visit_justification_exempt')) {
            return redirect()->route('dashboard_sales.index')
                ->with('info', '✅ أنت معفى من تقديم تبريرات الزيارات غير المكتملة.');
        }

        // Get all justifications submitted by the employee
        $justifications = EmployeeClientVisit::with(['client', 'approvedBy'])
            ->where('employee_id', $user->id)
            ->whereNotNull('justification')
            ->where('justification', '!=', '')
            ->orderBy('justification_date', 'desc')
            ->paginate(10);

        // فترات الإعفاء/التبرير التي منحتها الإدارة لهذا الموظف — راجع
        // App\Models\VisitJustification. تُعرَض بمعزل عن صلاحياته الحالية:
        // هذه سجلّاتٌ تاريخية، لا نتيجة فحص صلاحيةٍ حيّة.
        $periods = VisitJustification::with('client')
            ->forEmployee($user->id)
            ->orderBy('date_from', 'desc')
            ->get();

        return view('client::visit_justifications.incomplete.my_justifications', compact('justifications', 'periods'));
    }

    /**
     * Show details of a specific justification.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function showJustificationDetails($id)
    {
        $user = Auth::user();

        // Get the justification, ensuring it belongs to the employee
        $justification = EmployeeClientVisit::with(['client', 'employee', 'approvedBy'])
            ->where('id', $id)
            ->where('employee_id', $user->id)
            ->firstOrFail();

        return view('client::visit_justifications.incomplete.justification_details', compact('justification'));
    }

    /**
     * Show the form for editing a justification.
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function editJustification($id)
    {
        $user = Auth::user();

        // التحقق من ما إذا كان الموظف معفى من تقديم التبريرات
        if (hasPermission('staff_visit_justification_exempt')) {
            return redirect()->route('dashboard_sales.index')
                ->with('info', '✅ أنت معفى من تقديم تبريرات الزيارات غير المكتملة.');
        }

        // Get the justification, ensuring it belongs to the employee and is still pending
        $justification = EmployeeClientVisit::with('client')
            ->where('id', $id)
            ->where('employee_id', $user->id)
            ->firstOrFail();

        return view('client::visit_justifications.incomplete.edit_justification', compact('justification'));
    }

    /**
     * Update a justification.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateJustification(Request $request, $id)
    {
        $user = Auth::user();

        // التحقق من ما إذا كان الموظف معفى من تقديم التبريرات
        if (hasPermission('staff_visit_justification_exempt')) {
            return redirect()->route('dashboard_sales.index')
                ->with('info', '✅ أنت معفى من تقديم تبريرات الزيارات غير المكتملة.');
        }

        $request->validate([
            'justification' => 'required|string|max:1000',
        ], [
            'justification.required' => 'التبرير مطلوب',
            'justification.max' => 'التبرير يجب ألا يتجاوز 1000 حرف',
        ]);

        try {
            // Get the justification, ensuring it belongs to the employee and is still pending
            $visit = EmployeeClientVisit::where('id', $id)
                ->where('employee_id', $user->id)
                ->firstOrFail();

            $before = $visit->justification;

            $visit->update([
                'justification' => $request->justification,
                'justification_date' => now(), // Update the date to reflect the edit
            ]);

            OperationLog::record(
                'visit_justification_updated',
                self::LOG_MODULE,
                $visit,
                'تعديل تبرير زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'»',
                ['old' => ['justification' => $before], 'new' => ['justification' => $request->justification]]
            );

            return redirect()->route('employee.visit-justifications.show', $id)
                ->with('success', 'تم تحديث التبرير بنجاح.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_justification', (int) $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحديث التبرير. يرجى المحاولة مرة أخرى.');
        }
    }
}
