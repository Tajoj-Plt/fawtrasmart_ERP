<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\Payment\PaymentMethodFileWriter;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sitting\Http\Requests\PaymentMethodRequest;
use Throwable;

class PaymentMethodsController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط. */
    private const LOG_MODULE = 'PaymentMethods';

    private PaymentMethodFileWriter $files;

    public function __construct(PaymentMethodFileWriter $files)
    {
        $this->middleware('permission:settings_edit_general_settings');
        $this->files = $files;
    }

    public function index()
    {
        $all = PaymentMethod::orderBy('sort_order')->orderBy('id')->get();

        return view('sitting::paymentMethod.index', [
            'payments' => $all->where('type', 'normal')->values(),
            'payments_electronics' => $all->where('type', 'electronic')->values(),
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'online' => $all->where('is_online', true)->count(),
                'electronic' => $all->where('type', 'electronic')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('sitting::paymentMethod.create', [
            'suggestedCode' => peek_document_number('payment-method'),
        ]);
    }

    public function store(PaymentMethodRequest $request)
    {
        $data = $request->validated();

        // الرمز حقلٌ حرّ (CASH، VISA…) يكتبه المسؤول — تركُه فارغاً يمنحه
        // ترقيماً تسلسلياً بدل أن يبقى بلا رمزٍ يُعرَف به في الربط والتقارير
        if (! filled($data['code'] ?? null)) {
            $data['code'] = next_document_number('payment-method');
        }

        try {
            // كان هنا سطر ملتوٍ يجمع `filter_var(...) ?? (... === 'active')`،
            // وشقّه الأول يُرجع null دائماً حين تصل القيمة `'active'` فلا يعمل
            // إلا الثاني. الطلب يتكفّل بالتحويل الآن
            $payment = PaymentMethod::create($data + [
                'is_active' => $data['status'] === 'active',
                'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
            ]);

            $this->writeConfigFile($payment);

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $payment,
                __('settings.payment.log_created', ['name' => $payment->name])
            );

            return redirect()->route('PaymentMethods.index')
                ->with('success', __('settings.payment.created', ['name' => $payment->name]));
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('settings.payment.errors.save_failed'));
        }
    }

    /**
     * نموذج تحرير طريقة قائمة.
     */
    public function edit($id)
    {
        $payment = PaymentMethod::findOrFail($id);

        return view('sitting::paymentMethod.edit', [
            'payment' => $payment,
            'fileExists' => $this->files->exists($payment->config_file_path),
            'filePath' => $payment->config_file_path,
        ]);
    }

    /**
     * تحديث طريقة قائمة وإعادة توليد ملفّها.
     */
    public function update(PaymentMethodRequest $request, $id)
    {
        $payment = PaymentMethod::findOrFail($id);
        $data = $request->validated();

        // حقول الأسرار تُعرض فارغةً دائماً (لا تُطبع قيمتها في HTML)، فترْكها
        // فارغةً يعني «لا تغيير» لا «امحُها» — وإلا مسح كلَّ حفظٍ مفاتيحَ
        // البوابة لمجرّد تعديل الاسم
        foreach (['secret_key', 'webhook_secret'] as $secret) {
            if (! filled($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        try {
            $before = $payment->only(['name', 'code', 'status']);

            $payment->update($data + ['is_active' => $data['status'] === 'active']);

            $this->writeConfigFile($payment->refresh());

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $payment,
                __('settings.payment.log_updated', ['name' => $payment->name]),
                ['old' => $before, 'new' => $payment->only(['name', 'code', 'status'])]
            );

            return redirect()->route('PaymentMethods.index')
                ->with('success', __('settings.payment.updated', ['name' => $payment->name]));
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $payment);

            return back()->withInput()->with('error', __('settings.payment.errors.save_failed'));
        }
    }

    /**
     * إعادة توليد ملفّ الإعدادات يدوياً — للملفّ الذي حُذف من القرص.
     */
    public function regenerateFile($id)
    {
        $payment = PaymentMethod::findOrFail($id);

        return $this->writeConfigFile($payment)
            ? back()->with('success', __('settings.payment.file_generated', ['name' => $payment->name]))
            : back()->with('error', __('settings.payment.file_failed', ['name' => $payment->name]));
    }

    /**
     * يكتب ملفّ الطريقة ويسجّل مساره على الصفّ.
     *
     * `saveQuietly` لا `save`: النموذج محفوظ للتوّ، وحفظٌ عادي هنا يرفع
     * أحداث التعديل مرّة ثانية على تغييرٍ داخليٍّ محض.
     */
    private function writeConfigFile(PaymentMethod $payment): bool
    {
        $path = $this->files->write($payment);

        if ($path === null) {
            return false;
        }

        $payment->config_file_path = $path;
        $payment->config_file_generated_at = now();
        $payment->saveQuietly();

        return true;
    }

    /**
     * تحديث حالات عدّة طرق دفع من الجدول.
     */
    public function updatePaymentStatus(Request $request)
    {
        $data = $request->validate([
            'payments' => ['required', 'array'],
            'payments.*.status' => ['nullable', 'in:active,inactive'],
        ], [
            'payments.required' => __('settings.payment.errors.payments_required'),
            'payments.*.status.in' => __('settings.payment.errors.payments_status_in'),
        ]);

        $changed = 0;

        DB::transaction(function () use ($data, &$changed) {
            foreach ($data['payments'] as $id => $row) {
                $payment = PaymentMethod::find($id);

                if (! $payment) {
                    continue;
                }

                $status = $row['status'] ?? 'inactive';

                if ($payment->status !== $status) {
                    $payment->update(['status' => $status, 'is_active' => $status === 'active']);
                    $changed++;
                }
            }
        });

        return back()->with('success', $changed
            ? __('settings.payment.statuses_updated', ['count' => $changed])
            : __('settings.payment.statuses_unchanged'));
    }

    /**
     * حذف طريقة دفع.
     *
     * لم يكن للشاشة إجراء حذف إطلاقاً: طريقة تُضاف بالخطأ تبقى إلى الأبد.
     * والحذف ناعم (`SoftDeletes` مفعَّل في النموذج) فلا تفقد الفواتير
     * القديمة مرجعها.
     */
    public function destroy($id)
    {
        $payment = PaymentMethod::find($id);

        if (! $payment) {
            return response()->json(['success' => false, 'message' => __('settings.payment.not_found')], 404);
        }

        if ($payment->is_default) {
            return response()->json([
                'success' => false,
                'message' => __('settings.payment.cannot_delete_default'),
            ], 422);
        }

        $name = $payment->name;
        $paymentId = $payment->id;

        try {
            // الملفّ يحمل مفاتيح حيّة — يُحذف مع الطريقة ولو كان الحذف ناعماً،
            // فطريقةٌ معطّلة لا يجوز أن تترك مفاتيحها مقروءة على القرص
            $this->files->delete($payment);

            $payment->delete();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $paymentId,
                __('settings.payment.log_deleted', ['name' => $name])
            );

            return response()->json([
                'success' => true,
                'message' => __('settings.payment.deleted', ['name' => $name]),
            ]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $paymentId);

            return response()->json([
                'success' => false,
                'message' => __('settings.payment.errors.save_failed'),
            ], 500);
        }
    }
}
