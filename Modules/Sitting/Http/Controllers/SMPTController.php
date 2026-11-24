<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SmtpSetting;
use App\Services\MailerConfigurator;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SMPTController extends Controller
{
    public function __construct(protected MailerConfigurator $mailer)
    {
        // حماية بصلاحية تعديل الإعدادات العامة
        $this->middleware('permission:settings_edit_general_settings');
    }

    /**
     * Display SMTP settings page
     */
    public function index()
    {
        $smtpSettings = SmtpSetting::query()->where('is_active', true)->orderBy('id')->first();

        // الشاشة تعرض بأي إعداد يُرسل النظام فعلاً، لا ما هو محفوظ فقط
        return view('sitting::smpt.index', [
            'smtpSettings' => $smtpSettings,
            'activeMailer' => $this->mailer->describe(),
        ]);
    }

    /**
     * Store or update SMTP settings
     */
    public function store(Request $request)
    {
        // تحويل checkboxes إلى boolean بشكل صحيح
        $useSmtp = $request->has('use_smtp') ? 1 : 0;
        $sslRequired = $request->has('ssl_required') ? 1 : 0;

        $validator = Validator::make($request->all(), [
            'sender_name' => $useSmtp ? 'required|string|max:255' : 'nullable|string|max:255',
            'email' => $useSmtp ? 'required|email|max:255' : 'nullable|email|max:255',
            'password' => 'nullable|string|max:255',
            'smtp_username' => 'nullable|string|max:255',
            // اسم الخادم كان يقبل أي نص — يوجد في الجدول صفّ خادمه «بيس».
            // النمط يقبل اسم نطاق أو عنوان IP فقط.
            'smtp_host' => [
                $useSmtp ? 'required' : 'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$|^\d{1,3}(?:\.\d{1,3}){3}$/',
            ],
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:tls,ssl',
        ], [
            'smtp_host.required' => __('settings.smtp.errors.host_required'),
            'smtp_host.regex' => __('settings.smtp.errors.host_regex'),
            'smtp_port.integer' => __('settings.smtp.errors.port_integer'),
            'email.email' => __('settings.smtp.errors.email_invalid'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $data = [
                'user_id' => Auth::id(),
                'use_smtp' => $useSmtp,
                'sender_name' => $request->sender_name,
                'email' => $request->email,
                'password' => $request->password,
                'smtp_username' => $request->smtp_username ?? $request->email,
                'smtp_host' => $request->smtp_host,
                'smtp_port' => $request->smtp_port ?? 587,
                'smtp_encryption' => $request->smtp_encryption ?? 'tls',
                'ssl_required' => $sslRequired,
                'is_active' => true,
            ];

            /* إعدادٌ واحدٌ للنظام لا إعدادٌ لكل مستخدم.

               كان يُحفظ صفٌّ باسم كل مديرٍ يحفظ، والمُرسِلُ الفعليّ يأخذ أقدمَ
               صفّ: فمديرٌ ثانٍ يحفظ حساباً جديداً وينجح اختبارُه، والفواتيرُ
               تخرج بالحساب القديم. الآن يُحدَّث الصفُّ القائم أيّاً كان حافظُه. */
            $existing = SmtpSetting::query()->orderBy('id')->first();

            if ($existing) {
                unset($data['user_id']);
                $existing->update($data);
            } else {
                SmtpSetting::create($data);
            }

            // الإعداد مخزَّن مؤقتاً — بلا إسقاطه يبقى النظام على القيم القديمة
            $this->mailer->forget();

            return redirect()->back()->with('success', __('settings.smtp.saved'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SMPT', 'store');

            return redirect()->back()->with('error', __('settings.smtp.save_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Reset SMTP settings
     */
    public function reset()
    {
        try {
            SmtpSetting::query()->delete();
            $this->mailer->forget();

            return redirect()->back()
                ->with('success', __('settings.smtp.reset_done'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SMPT', 'reset');

            return redirect()->back()->with('error', __('settings.smtp.reset_error'));
        }
    }

    /**
     * Send test email
     */
    public function sendTestMail()
    {
        $smtpSettings = SmtpSetting::query()->where('is_active', true)->orderBy('id')->first();

        if (! $smtpSettings || ! $smtpSettings->use_smtp) {
            return redirect()->back()->with('error', __('settings.smtp.save_first'));
        }

        // بلا هذا السطر يُرسَل «الاختبار» عبر مُرسِل `.env` فيُعلن النجاح
        // دون أن يمسّ إعدادات المستخدم — وهو ما كان يحدث
        $this->mailer->apply($smtpSettings);

        try {
            Mail::raw(
                __('settings.smtp.test_body', ['host' => $smtpSettings->smtp_host]),
                function ($message) use ($smtpSettings) {
                    $message->to($smtpSettings->email)
                        ->subject(__('settings.smtp.test_subject'));
                }
            );

            return redirect()->back()->with(
                'success',
                __('settings.smtp.test_sent', ['host' => $smtpSettings->smtp_host])
            );
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SMPT', 'sendTestMail');

            return redirect()->back()->with('error', $this->readableMailError($e->getMessage()));
        }
    }

    /**
     * يترجم أخطاء الخادم الشائعة إلى سبب يفهمه المستخدم.
     */
    private function readableMailError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Authentication') || str_contains($message, '535') => __('settings.smtp.errors.auth'),
            str_contains($message, 'Connection could not be established')
                || str_contains($message, 'Connection refused') => __('settings.smtp.errors.connection'),
            str_contains($message, 'certificate') || str_contains($message, 'SSL') => __('settings.smtp.errors.certificate'),
            str_contains($message, 'timed out') || str_contains($message, 'timeout') => __('settings.smtp.errors.timeout'),
            default => __('settings.smtp.errors.generic', ['error' => $message]),
        };
    }

    /**
     * Send test email via AJAX
     */
    public function sendTestEmailAjax(Request $request)
    {
        $smtpSettings = SmtpSetting::query()->where('is_active', true)->orderBy('id')->first();

        if (! $smtpSettings || ! $smtpSettings->use_smtp) {
            return response()->json([
                'success' => false,
                'error' => __('settings.smtp.save_first'),
            ]);
        }

        $validator = Validator::make($request->all(), ['email' => 'required|email']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => __('settings.smtp.invalid_test_email'),
            ]);
        }

        $email = $request->input('email');

        // الاختبار يجب أن يمرّ بالإعدادات المحفوظة، وإلا فهو يختبر `.env`
        $this->mailer->apply($smtpSettings);

        try {
            Mail::raw(
                __('settings.smtp.test_body_short', ['host' => $smtpSettings->smtp_host]),
                function ($message) use ($email, $smtpSettings) {
                    $message->to($email)
                        ->from($smtpSettings->email, $smtpSettings->sender_name)
                        ->subject(__('settings.smtp.test_subject'));
                }
            );

            return response()->json([
                'success' => true,
                'message' => __('settings.smtp.test_sent_to', ['email' => $email, 'host' => $smtpSettings->smtp_host]),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SMPT', 'sendTestEmailAjax');

            return response()->json([
                'success' => false,
                'error' => $this->readableMailError($e->getMessage()),
            ]);
        }
    }
}
