<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CreditNotification;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Invoice;
use App\Models\ZatcaDocument;
use App\Services\Zatca\ZatcaException;
use App\Services\Zatca\ZatcaInvoiceService;
use App\Services\Zatca\ZatcaOnboardingService;
use App\Services\Zatca\ZatcaPdfBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Sales\Http\Requests\ElectronicInvoiceRequest;
use Throwable;

/**
 * إعدادات الفاتورة الإلكترونية.
 *
 * كانت هذه الشاشات في `SittingInvoiceController` ثلاثَ دوالّ لا تفعل إلا
 * `return view(...)` — بلا نموذج ولا حفظ ولا تحقّق. فُصلت هنا وأُعطيت
 * خلفيةً حقيقية.
 */
class ElectronicInvoiceController extends Controller
{
    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.settings.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.settings.view', ['only' => ['index', 'edit', 'company', 'documents', 'downloadXml']]);
        $this->middleware('permission:sales.settings.update', ['only' => ['activate', 'deactivate', 'update', 'onboard', 'retry', 'phase']]);
        // نسخة العميل من فاتورته: بصلاحية تنزيلها لا بصلاحية الإعدادات
        $this->middleware('permission:sales.invoices.export_pdf', ['only' => ['downloadPdf']]);
        // الإرسال من صفحة المستند بصلاحية إرسال الفواتير لا بصلاحية الإعدادات:
        // من يُصدر الفاتورة يرسلها، وليس له أن يمسّ الشهادة
        $this->middleware('permission:sales.invoices.send', ['only' => ['send']]);
    }

    /** اللوحة: البيئة المفعَّلة وحالة الجاهزية ومداخل الإعدادات. */
    public function index()
    {
        $settings = ElectronicInvoiceSetting::current();

        return view('sales::sitting.ElectronicInvoice.index', [
            'settings' => $settings,
            'missing' => $this->missingLabels($settings),
        ]);
    }

    /**
     * تفعيل بيئة أو تعطيل الخدمة.
     *
     * البوابة الفعلية لا تُفعَّل ببيانات ناقصة: الإرسال سيفشل عند أول
     * فاتورة، والفشل حينها يقع على المحاسب لا على من ضبط الإعداد.
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'environment' => ['required', Rule::in(ElectronicInvoiceSetting::ENVIRONMENTS)],
        ]);

        $settings = ElectronicInvoiceSetting::current();

        if ($data['environment'] === ElectronicInvoiceSetting::ENV_PRODUCTION && ! $settings->isComplete()) {
            return back()->with('error', __('sales_settings.electronic.complete_first', [
                'fields' => implode(__('settings.common.list_separator'), $this->missingLabels($settings)['all']),
            ]));
        }

        $settings->fill([
            'environment' => $data['environment'],
            'is_enabled' => true,
            'updated_by' => auth()->id(),
        ])->save();

        return redirect()->route('settings.electronic_invoice')
            ->with('success', __('sales_settings.electronic.activated', [
                'env' => __('sales_settings.electronic.env.'.$data['environment']),
            ]));
    }

    /**
     * اختيار المرحلة: «الإصدار» أم «الربط والتكامل».
     *
     * لا يُفعِّل ولا يُعطِّل: منشأةٌ تعمل بالأولى وتنتقل إلى الثانية تبقى
     * خدمتها كما كانت، والإرسال لا يبدأ حتى تُسجَّل لدى الهيئة.
     */
    public function phase(Request $request)
    {
        $data = $request->validate([
            'phase' => ['required', Rule::in(ElectronicInvoiceSetting::PHASES)],
        ]);

        ElectronicInvoiceSetting::current()
            ->fill(['phase' => $data['phase'], 'updated_by' => auth()->id()])
            ->save();

        return redirect()->route('settings.electronic_invoice')
            ->with('success', __('zatca.phase.switched', ['phase' => __('zatca.phase.'.$data['phase'].'.title')]));
    }

    public function deactivate()
    {
        ElectronicInvoiceSetting::current()
            ->fill(['is_enabled' => false, 'updated_by' => auth()->id()])
            ->save();

        return redirect()->route('settings.electronic_invoice')
            ->with('success', __('sales_settings.electronic.deactivated'));
    }

    /** الإعدادات العامّة — سلوك الإرسال. */
    public function edit()
    {
        return view('sales::sitting.ElectronicInvoice.behavior', [
            'settings' => ElectronicInvoiceSetting::current(),
        ]);
    }

    /** بيانات المنشأة المطلوبة للتسجيل، وبيانات الربط. */
    public function company()
    {
        $settings = ElectronicInvoiceSetting::current();

        return view('sales::sitting.ElectronicInvoice.company', [
            'settings' => $settings,
            'missing' => $this->missingLabels($settings),
        ]);
    }

    /** الحفظ للقسمين معاً — القسم يأتي في `section`. */
    public function update(ElectronicInvoiceRequest $request)
    {
        $settings = ElectronicInvoiceSetting::current();
        $settings->fill($request->settings());
        $settings->updated_by = auth()->id();
        $settings->save();

        // بيانات المنشأة نقصت بعد أن كانت الخدمة مفعَّلة على البوابة
        // الفعلية: تُعطَّل بدل أن تُترك تُرسِل ما سيُرفض
        if ($settings->is_enabled && $settings->isProduction() && ! $settings->isComplete()) {
            $settings->fill(['is_enabled' => false])->save();

            return redirect()->route('settings.electronic_invoice')
                ->with('error', __('sales_settings.electronic.disabled_incomplete'));
        }

        return redirect()->route('settings.electronic_invoice')
            ->with('success', __('sales_settings.electronic.saved'));
    }

    /**
     * التسجيل لدى هيئة الزكاة والضريبة والجمارك برمز OTP.
     *
     * الرمز يُستهلَك بالمحاولة الواحدة، فكلّ ما يُمكن فحصه يُفحَص قبل النداء
     * — انظر `ZatcaOnboardingService::onboard()`.
     */
    public function onboard(Request $request, ZatcaOnboardingService $onboarding)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => __('zatca.errors.otp_required'),
            'otp.digits' => __('zatca.errors.otp_format'),
        ]);

        $settings = ElectronicInvoiceSetting::current();

        // نداءات الهيئة المتتابعة (شهادة، ستّة فحوص، شهادة) تتجاوز مهلة
        // الطلب الافتراضية على الاستضافات المشتركة
        @set_time_limit(180);

        try {
            $result = $onboarding->onboard($settings, $data['otp']);
        } catch (ZatcaException $e) {
            $onboarding->recordFailure($settings, $this->describe($e));

            return back()->with('error', $this->describe($e));
        } catch (Throwable $e) {
            Log::error('ZATCA onboarding failed', ['exception' => $e]);
            $onboarding->recordFailure($settings, $e->getMessage());

            return back()->with('error', __('zatca.errors.unexpected'));
        }

        return redirect()->route('settings.electronic_invoice')
            ->with('success', __('zatca.onboarding.success', ['count' => count($result['checks'])]));
    }

    /** سجلّ ما أُرسل إلى الهيئة. */
    public function documents(Request $request)
    {
        $documents = ZatcaDocument::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('environment'), fn ($q) => $q->where('environment', $request->input('environment')))
            ->when($request->filled('q'), fn ($q) => $q->where('document_number', 'like', '%'.$request->input('q').'%'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $settings = ElectronicInvoiceSetting::current();

        return view('sales::sitting.ElectronicInvoice.documents', [
            'documents' => $documents,
            'settings' => $settings,
            'counts' => ZatcaDocument::query()
                ->where('environment', $settings->environment)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /** الملفّ الموقَّع — المعتمَد من الهيئة إن وُجد. */
    public function downloadXml(int $document)
    {
        $document = ZatcaDocument::findOrFail($document);

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $document->document_number).'_'.$document->icv.'.xml';

        return response($document->finalXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * الفاتورة الإلكترونية كما تُسلَّم للعميل: PDF/A-3 في جوفه ملفّ الهيئة.
     *
     * لِما قبلته الهيئة وحده: نسخةٌ من مستندٍ مرفوض أو معلَّق تُشبه فاتورةً
     * معتمَدة وليست كذلك.
     */
    public function downloadPdf(int $document, ZatcaPdfBuilder $builder)
    {
        $document = ZatcaDocument::findOrFail($document);

        if (! $document->isAccepted()) {
            return back()->with('error', __('zatca.errors.pdf_not_accepted'));
        }

        try {
            $pdf = $builder->build($document);
        } catch (Throwable $e) {
            Log::error('ZATCA PDF failed', ['exception' => $e, 'document' => $document->id]);

            return back()->with('error', __('zatca.errors.pdf_failed'));
        }

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $document->document_number).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    /** إعادة إرسال ملفٍّ موقَّع لم يبلغ الهيئة. */
    public function retry(int $document, ZatcaInvoiceService $service)
    {
        $document = ZatcaDocument::findOrFail($document);

        try {
            $document = $service->retry($document);
        } catch (ZatcaException $e) {
            return back()->with('error', $service->describe($e));
        } catch (Throwable $e) {
            Log::error('ZATCA retry failed', ['exception' => $e]);

            return back()->with('error', __('zatca.errors.unexpected'));
        }

        return $this->afterSend($document, $service);
    }

    /**
     * إرسال مستندٍ من صفحته.
     *
     * @param  string  $type  `invoice` (فاتورة أو مرتجع) أو `credit_note`
     */
    public function send(string $type, int $id, ZatcaInvoiceService $service)
    {
        $source = $type === ZatcaDocument::TYPE_CREDIT_NOTE
            ? CreditNotification::findOrFail($id)
            : Invoice::findOrFail($id);

        try {
            $document = $service->submit($source);
        } catch (ZatcaException $e) {
            return back()->with('error', $service->describe($e));
        } catch (Throwable $e) {
            Log::error('ZATCA send failed', ['exception' => $e]);

            return back()->with('error', __('zatca.errors.unexpected'));
        }

        return $this->afterSend($document, $service);
    }

    /** رسالة ما بعد الإرسال: مقبولٌ، مقبولٌ بملاحظات، أو غير مقبول. */
    private function afterSend(ZatcaDocument $document, ZatcaInvoiceService $service)
    {
        if (! $document->isAccepted()) {
            return back()->with('error', $service->summary($document));
        }

        if (! empty($document->warnings)) {
            return back()->with('success', __('zatca.invoice.sent_with_warnings', [
                'warnings' => implode(' • ', array_slice($document->warnings, 0, 3)),
            ]));
        }

        return back()->with('success', __('zatca.invoice.sent', [
            'status' => __('zatca.status.'.$document->status),
        ]));
    }

    private function describe(ZatcaException $e): string
    {
        return app(ZatcaInvoiceService::class)->describe($e);
    }

    /**
     * النواقص بأسمائها، مفصولةً بمصدرها.
     *
     * ما ينقص في المنشأة يُصلَح في شاشة «إعدادات الحساب» لا هنا، فالفصل
     * يسمح للشاشة أن توجّه المستخدم إلى الموضع الصحيح بدل أن تطلب منه
     * حقلاً لا وجود له في هذه الشاشة.
     *
     * @return array{establishment: list<string>, local: list<string>, all: list<string>}
     */
    private function missingLabels(ElectronicInvoiceSetting $settings): array
    {
        $missing = $settings->missingFields();

        $label = fn (string $field) => __('sales_settings.electronic.fields.'.$field);

        $establishment = array_map($label, $missing['establishment']);
        $local = array_map($label, $missing['local']);

        return [
            'establishment' => $establishment,
            'local' => $local,
            'all' => array_merge($establishment, $local),
        ];
    }
}
