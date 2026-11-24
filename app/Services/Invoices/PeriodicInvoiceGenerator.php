<?php

namespace App\Services\Invoices;

use App\Mail\PeriodicInvoiceIssuedMail;
use App\Models\Invoice;
use App\Models\notifications;
use App\Models\PeriodicInvoice;
use App\Models\PeriodicInvoiceInstance;
use App\Models\User;
use App\Services\Sales\SalesDocumentMailer;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Http\Controllers\InvoicesController;

/**
 * مولّد فواتير الاشتراكات الدورية.
 *
 * ══════════ الجدول ══════════
 *
 * حفظُ الاشتراك يكتب جدولَه في `periodic_invoice_instances`
 * (`PeriodicInvoice::syncSchedule()`): نسخةٌ لكلّ استحقاق، بيوم استحقاقها
 * ويوم إصدارها (الاستحقاق − «يُصدَر قبل الاستحقاق بـ»). والمهمّة المجدولة
 * `sales:generate-periodic-invoices` — من «المهام المجدولة»، يقودها
 * `cron:run-due` — تُكمل كلَّ نسخةٍ حان يوم إصدارها ولم تصدر.
 *
 * ══════════ الإصدار من مسار الفاتورة نفسِه ══════════
 *
 * كان المولّد ينسخ الاشتراك إلى `invoices` بـ`Invoice::create()` ويقول صراحةً
 * إنّه لا يُرحّل قيداً ولا يمسّ المخزون: فاتورةٌ بلا قيدٍ يومية، بلا فرع،
 * بلا خصمٍ من المستودع ولا فحصٍ للكمية. صار يبني طلباً بحقول الاشتراك
 * ويمرّره إلى `InvoicesController::store()` — المسار الذي تمرّ به الفاتورة
 * المكتوبة في الشاشة: القيد، والمخزون، ونقصُ الكمية، والحدُّ الائتمانيّ،
 * والإقفال، والترقيم، والإيداع وسند قبضه. فلا مسارَ ترحيلٍ ثانياً يتباعد
 * عن الأوّل بأوّل تعديل.
 *
 * والسياق يصل في `attributes` لا في المدخلات (`REQUEST_CONTEXT`): منه يأخذ
 * المسار فرعَ الاشتراك، ويتخطّى قياسَ موقع الموظّف، ويربط النسخة بالفاتورة
 * داخل معاملته — فلا تصدر نسخةٌ مرّتين ولا تبقى فاتورةٌ بلا نسخة.
 *
 * ══════════ الإخفاق ══════════
 *
 * ما يردّه المسار (نقصُ مخزون، حدٌّ ائتماني، فترةٌ مقفلة…) يُكتب على النسخة
 * (`last_error`, حالة «أخفقت») ويُرسَل به إشعارٌ لمنشئ الاشتراك ومسؤول
 * مبيعاته — مرّةً لكلّ سببٍ جديد لا كلَّ ليلة. والنسخة تُعاد في التشغيل
 * التالي، فمتى وصل المخزون صدرت.
 */
class PeriodicInvoiceGenerator
{
    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'SalesPeriodicInvoices';

    /** مفتاح السياق في `Request::$attributes` — يقرؤه `InvoicesController::periodicIssue()` */
    public const REQUEST_CONTEXT = 'periodic_issue';

    /** نوع الإشعار في «إعدادات الإشعارات» */
    public const NOTIFICATION_TYPE = 'sales_periodic_failed';

    /**
     * يُصدر ما حان من كلّ الاشتراكات المفعَّلة الآلية — للمهمّة المجدولة.
     *
     * يُصدر **كلَّ** ما فات موعده: مهمّةٌ توقّفت أسبوعاً تُكمل أسبوعها.
     *
     * @return array{scanned:int, created:int, skipped:int, failed:int, invoices:array<int,string>, notes:array<int,string>}
     */
    public function run(?Carbon $on = null, bool $dryRun = false): array
    {
        $today = ($on ?: Carbon::today())->startOfDay();
        $result = $this->emptyResult();

        $subscriptions = PeriodicInvoice::with(['items', 'client'])
            ->where('is_active', true)
            ->where('auto_generate', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            $result['scanned']++;
            $this->generateFor($subscription, $today, $dryRun, $result, null);
        }

        return $result;
    }

    /**
     * يُصدر من اشتراكٍ واحد — لزرّ «إصدار الآن» ولحفظ الاشتراك.
     *
     * `$limit` عددُ النسخ في النقرة: الزرّ يُصدر **النسخة المستحقّة التالية
     * وحدها**. كان يُصدر كلَّ ما فات منذ تاريخ أوّل فاتورة دفعةً واحدة، فاشتراكٌ
     * بدأ قبل أربعة أشهر يُخرج أربع فواتير بنقرة.
     *
     * @return array{scanned:int, created:int, skipped:int, failed:int, invoices:array<int,string>, notes:array<int,string>}
     */
    public function runFor(PeriodicInvoice $subscription, ?Carbon $on = null, bool $dryRun = false, ?int $limit = 1): array
    {
        $today = ($on ?: Carbon::today())->startOfDay();
        $result = $this->emptyResult();
        $result['scanned'] = 1;

        $subscription->loadMissing(['items', 'client']);
        $this->generateFor($subscription, $today, $dryRun, $result, $limit);

        return $result;
    }

    /** عدد النسخ التي فات يوم إصدارها ولم تصدر — للتنبيه في الشاشة */
    public function overdueCount(PeriodicInvoice $subscription, ?Carbon $on = null): int
    {
        return $this->dueInstances($subscription, ($on ?: Carbon::today())->startOfDay())->count();
    }

    protected function emptyResult(): array
    {
        return ['scanned' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'invoices' => [], 'notes' => []];
    }

    /** النسخ التي حان إصدارها: مجدولةٌ أو أخفقت، بلا فاتورة، يومُ إصدارها اليوم أو قبله */
    protected function dueInstances(PeriodicInvoice $subscription, Carbon $today)
    {
        return PeriodicInvoiceInstance::where('periodic_invoice_id', $subscription->id)
            ->whereNull('invoice_id')
            ->whereIn('status', [PeriodicInvoiceInstance::STATUS_SCHEDULED, PeriodicInvoiceInstance::STATUS_FAILED])
            ->whereDate('issue_date', '<=', $today->toDateString())
            ->orderBy('instance_number')
            ->get();
    }

    protected function generateFor(PeriodicInvoice $subscription, Carbon $today, bool $dryRun, array &$result, ?int $limit): void
    {
        try {
            if ($subscription->items->isEmpty()) {
                $result['skipped']++;
                $result['notes'][] = "#{$subscription->invoice_number}: ".__('sales_periodic.generator.no_items');

                return;
            }

            // اشتراكٌ من قبل الترقية بلا جدول — يُكتب جدولُه أوّلاً
            if (! $dryRun && ! $subscription->instances()->exists()) {
                $subscription->syncSchedule();
            }

            $due = $this->dueInstances($subscription, $today);

            if ($limit !== null) {
                $due = $due->take($limit);
            }

            foreach ($due as $instance) {
                if ($dryRun) {
                    $result['created']++;
                    $result['invoices'][] = "#{$subscription->invoice_number} → ".__('sales_periodic.generator.instance', [
                        'number' => $instance->instance_number,
                        'date' => $instance->due_date->format('Y-m-d'),
                    ]);

                    continue;
                }

                $this->issueInstance($subscription, $instance, $result);
            }
        } catch (\Throwable $e) {
            $result['failed']++;
            $result['notes'][] = "#{$subscription->invoice_number}: ".$e->getMessage();

            OperationLog::failure($e, self::LOG_MODULE, 'generate', $subscription->id);
        }
    }

    /** يُصدر نسخةً واحدة ويكتب نتيجتها في `$result` */
    protected function issueInstance(PeriodicInvoice $subscription, PeriodicInvoiceInstance $instance, array &$result): void
    {
        $issueDate = Carbon::parse($instance->issue_date ?: $subscription->issueDateFor($instance->due_date));

        /* الإقفال بتاريخ الإصدار — تاريخ الفاتورة وقيدها. يُفحص هنا أيضاً
           ليُكتب السبب على النسخة لا ليُكتشف من رسالة المسار. */
        if (! AccountingPeriodGuard::allows($issueDate)) {
            $this->markFailed($subscription, $instance, __('sales_periodic.generator.period_locked', [
                'date' => $issueDate->toDateString(),
            ]), $result);

            return;
        }

        $error = null;

        try {
            $error = $this->throughInvoicePath($subscription, $instance, $issueDate);
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->implode(' — ');
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            OperationLog::failure($e, self::LOG_MODULE, 'generate', $subscription->id);
        }

        $instance->refresh();

        if (! $instance->invoice_id) {
            $this->markFailed($subscription, $instance, $error ?: __('sales_periodic.generator.unknown_error'), $result);

            return;
        }

        $invoice = Invoice::find($instance->invoice_id);

        OperationLog::record(
            'generated',
            self::LOG_MODULE,
            $subscription,
            __('sales_periodic.generator.log', [
                'invoice' => $invoice->code ?? $instance->invoice_id,
                'number' => $instance->instance_number,
                'total' => $subscription->repeat_count ?: '—',
                'subscription' => $subscription->invoice_number,
            ]),
        );

        // بعد المعاملة: البريد لا يُبطل فاتورةً حُفظت
        if ($invoice) {
            $this->sendCopy($subscription, $invoice, (int) $instance->instance_number);
        }

        $result['created']++;
        $result['invoices'][] = $invoice->code ?? (string) $instance->invoice_id;
    }

    /**
     * يمرّر النسخة إلى `InvoicesController::store()` ويُرجع سبب الردّ — أو `null`.
     *
     * المسار مبنيٌّ على طلبٍ ومستخدمٍ وجلسة: يُبنى طلبٌ بحقول الاشتراك،
     * ويُعمل باسم المستخدم الحاضر (زرّ «إصدار الآن») أو منشئ الاشتراك
     * (المهمّة المجدولة)، ورسائلُ الردّ تُلتقط من جلسةٍ معزولة فلا تظهر
     * لمستخدمٍ في صفحته التالية. ويُعاد كلُّ شيءٍ كما كان بعدها.
     */
    protected function throughInvoicePath(PeriodicInvoice $subscription, PeriodicInvoiceInstance $instance, Carbon $issueDate): ?string
    {
        $actor = auth()->user() ?: User::find($subscription->created_by);

        if (! $actor) {
            return __('sales_periodic.generator.no_actor');
        }

        $session = new Store('periodic-invoice', new ArraySessionHandler(10));
        $session->start();

        $request = Request::create('/sales/invoices/store', 'POST', $this->payload($subscription, $instance, $issueDate));
        $request->setLaravelSession($session);
        $request->attributes->set(self::REQUEST_CONTEXT, [
            'instance_id' => $instance->id,
            'periodic_invoice_id' => $subscription->id,
            'branch_id' => $subscription->branch_id,
            'disable_partial_payment' => (bool) $subscription->disable_partial_payment,
        ]);

        $previousRequest = app()->bound('request') ? app('request') : null;
        $previousUser = auth()->user();
        $redirector = app('redirect');
        $previousSession = (fn () => $this->session ?? null)->call($redirector);

        // المسار يفتح معاملته ويُغلقها؛ ما يبقى مفتوحاً فوق مستوى المُنادي يُرجَع
        $transactionLevel = DB::transactionLevel();

        try {
            app()->instance('request', $request);
            auth()->setUser($actor);
            $redirector->setSession($session);

            // المستودع وحدودُه والخصمُ من «إعدادات الفواتير» تُقرأ لكلّ فاتورة
            app(InvoicesController::class)->store($request);
        } finally {
            if ($previousSession) {
                $redirector->setSession($previousSession);
            }

            if ($previousRequest) {
                app()->instance('request', $previousRequest);
            }

            if ($previousUser) {
                auth()->setUser($previousUser);
            } else {
                auth()->forgetUser();
            }

            while (DB::transactionLevel() > $transactionLevel) {
                DB::rollBack();
            }
        }

        return $session->get('error');
    }

    /**
     * حقول الطلب — ما ترسله شاشة «إنشاء فاتورة مبيعات» لو كُتبت الفاتورة يدوياً.
     *
     * @return array<string, mixed>
     */
    protected function payload(PeriodicInvoice $subscription, PeriodicInvoiceInstance $instance, Carbon $issueDate): array
    {
        $dueDate = Carbon::parse($instance->due_date);

        $items = [];

        foreach ($subscription->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'conversion_factor' => $item->conversion_factor ?: 1,
                'unit_name' => $item->unit_name,
                'item' => $item->item,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                // خصمُ البند أُزيل من الشاشتين — المبلغ المحفوظ يمرّ كما هو
                'discount' => (float) ($item->discount ?? 0),
                'discount_type' => 'amount',
                'tax_1' => (float) ($item->tax_1 ?? 0),
                'tax_1_id' => $item->tax_1_id,
                'tax_2' => (float) ($item->tax_2 ?? 0),
                'tax_2_id' => $item->tax_2_id,
                'store_house_id' => $item->store_house_id ?: $subscription->store_house_id,
            ];
        }

        /* الخصم العامّ **مبلغاً**: `total_discount` محسوبٌ عند الحفظ بالقاعدة
           نفسها (النسبة من صافي الأصناف)، فيصل المسارَ كما حُسب — واشتراكاتُ
           ما قبل الترقية كانت تحفظ في `discount_amount` مبلغاً بجوار نوع «نسبة». */
        $lineDiscounts = (float) $subscription->items->sum('discount');
        $generalDiscount = max(0, round((float) $subscription->total_discount - $lineDiscounts, 2));

        $grandTotal = round((float) $subscription->grand_total, 2);

        $payload = [
            'client_id' => $subscription->client_id,
            'employee_id' => $subscription->employee_id,
            'store_house_id' => $subscription->store_house_id,
            'invoice_date' => $issueDate->toDateString(),
            'issue_date' => $issueDate->toDateString(),

            /* الاستحقاق = يوم الإصدار + المهلة. منه يقرأ `PaymentTerms` حالةَ
               الفاتورة (ضمن المهلة / متأخّرة)، وتنبيهُ `invoices:payment-reminders`،
               وتقاريرُ الأعمار. */
            'terms' => max(0, $issueDate->diffInDays($dueDate)),

            'reference_number' => $subscription->invoice_number,
            'notes' => trim(($subscription->notes ?? '')."\n".__('sales_periodic.generator.invoice_note', [
                'subscription' => $subscription->invoice_number,
                'number' => $instance->instance_number,
                'due' => $dueDate->toDateString(),
            ])),
            'journal_description' => __('sales_periodic.generator.journal', [
                'subscription' => $subscription->invoice_number,
                'number' => $instance->instance_number,
            ]),
            'items' => $items,
            'discount_amount' => $generalDiscount,
            'discount_type' => 'amount',
            'shipping_cost' => (float) ($subscription->shipping_cost ?? 0),
            'tax_type' => 1,
            'payment_method' => $subscription->payment_method,
        ];

        /* ضريبة الشحن: المختارة محفوظةٌ منذ الترقية. وقبلها: شحنٌ بلا ضريبة
           محفوظة يعني «بلا ضريبة» صراحةً، وشحنٌ بضريبة يُترك للمسار يشتقّها
           من ضرائب البنود كما كانت تُحسب. */
        if ($subscription->shipping_tax_id) {
            $payload['shipping_tax_id'] = $subscription->shipping_tax_id;
        } elseif ((float) $subscription->shipping_tax <= 0) {
            $payload['shipping_tax_id'] = '';
        }

        // الإيداع — كما في شاشة الفاتورة: «مدفوعة بالكامل» أو مبلغٌ مقبوض
        if ($subscription->is_paid) {
            $payload['is_paid'] = 1;
            $payload['advance_payment'] = $grandTotal;
        } elseif ((float) $subscription->advance_payment > 0) {
            $payload['advance_payment'] = min((float) $subscription->advance_payment, $grandTotal);
        }

        return $payload;
    }

    protected function markFailed(PeriodicInvoice $subscription, PeriodicInvoiceInstance $instance, string $reason, array &$result): void
    {
        $previous = $instance->last_error;

        $instance->forceFill([
            'status' => PeriodicInvoiceInstance::STATUS_FAILED,
            'last_error' => mb_substr($reason, 0, 2000),
            'attempted_at' => now(),
        ])->save();

        $result['failed']++;
        $result['notes'][] = "#{$subscription->invoice_number} (".$instance->instance_number.'): '.$reason;

        Log::warning('الفاتورة الدورية: لم تصدر النسخة', [
            'periodic_invoice_id' => $subscription->id,
            'instance' => $instance->instance_number,
            'reason' => $reason,
        ]);

        // سببٌ جديد يُبلَّغ مرّة — لا إشعارٌ كلَّ ليلةٍ بالسبب نفسه
        if ($previous !== $instance->last_error) {
            $this->notifyFailure($subscription, $instance, $reason);
        }
    }

    /** إشعار «لم تصدر الفاتورة الدورية» — لمنشئ الاشتراك ولمسؤول مبيعاته */
    protected function notifyFailure(PeriodicInvoice $subscription, PeriodicInvoiceInstance $instance, string $reason): void
    {
        $receivers = collect([
            $subscription->created_by,
            $subscription->employee_id ? User::where('employee_id', $subscription->employee_id)->value('id') : null,
        ])->filter()->unique();

        foreach ($receivers->isEmpty() ? [null] : $receivers as $receiverId) {
            try {
                notifications::create([
                    'user_id' => null,
                    'receiver_id' => $receiverId,
                    'client_id' => $subscription->client_id,
                    'type' => self::NOTIFICATION_TYPE,
                    'title' => __('sales_periodic.generator.failed_title', ['subscription' => $subscription->invoice_number]),
                    'description' => __('sales_periodic.generator.failed_body', [
                        'number' => $instance->instance_number,
                        'date' => optional($instance->issue_date)->format('Y-m-d'),
                        'client' => $subscription->client->trade_name ?? '—',
                        // رسائل المسار تنتهي بنقطة، ونصُّ الإشعار يضع نقطته
                        'reason' => rtrim($reason, '. '),
                    ]),
                    'read' => 0,
                ]);
            } catch (\Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'notify', $subscription->id);
            }
        }
    }

    /**
     * نسخة الفاتورة إلى بريد العميل — إن طلبها الاشتراك.
     *
     * بالمُرسِل نفسِه الذي يرسل به زرّ «إرسال بالبريد» في الفاتورة
     * (`SalesDocumentMailer`): المستند مرفقاً PDF بالقالب المصمَّم، ونصُّ
     * الرسالة من «قوالب البريد»، وسطرٌ في سجلّ البريد. وإن ردّ المُرسِل
     * (قالبُ بريدٍ غير مزروع مثلاً) تُرسَل رسالةُ الرابط القديمة احتياطاً.
     *
     * **خارج المعاملة عمداً**: انقطاع البريد لا يُلغي فاتورةً صدرت.
     */
    protected function sendCopy(PeriodicInvoice $subscription, Invoice $invoice, int $number): void
    {
        if (! $subscription->send_copy) {
            return;
        }

        $email = $subscription->client->email ?? null;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('الفاتورة الدورية: لا بريد صالح للعميل، لم تُرسَل نسخة', [
                'periodic_invoice_id' => $subscription->id,
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        $url = route('invoices.print', ['id' => $invoice->id, 'embed' => true]);

        try {
            $sent = app(SalesDocumentMailer::class)->send(
                'sales_invoice',
                $invoice->id,
                $email,
                [
                    'client' => $subscription->client->trade_name ?? '',
                    'number' => (string) $invoice->code,
                    'total' => CurrencySettings::format($invoice->grand_total),
                    'link' => $url,
                ],
                null,
                null,
                (int) $invoice->client_id,
            );

            if (! ($sent['ok'] ?? false)) {
                Mail::to($email)->send(new PeriodicInvoiceIssuedMail($subscription, $invoice, $url, $number));
            }

            OperationLog::record(
                'emailed',
                self::LOG_MODULE,
                $subscription,
                __('sales_periodic.mail.sent').' — '.$invoice->code.' → '.$email,
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'mail', $subscription->id);

            Log::warning('الفاتورة الدورية: تعذّر إرسال النسخة', [
                'periodic_invoice_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
