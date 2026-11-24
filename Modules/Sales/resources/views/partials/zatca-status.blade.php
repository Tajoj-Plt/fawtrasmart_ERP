{{--
    شريط الفاتورة الإلكترونية في صفحة المستند: حالته لدى الهيئة وزرّ إرساله.

    يقرأ حالته بنفسه لا من المتحكّم: يُضمَّن في صفحات الفاتورة والمرتجع
    والإشعار الدائن، ولكلٍّ متحكّمه — وتمرير الحالة من ثلاثةٍ يعني ثلاث نسخٍ
    تتباعد.

    لا يظهر والخدمة معطَّلة: منشأةٌ لا تستعمل الفاتورة الإلكترونية لا يعنيها.

    المتغيّرات:
      $zatcaType  invoice | credit_note
      $zatcaId    معرّف المستند
--}}
@php
    $zatcaSettings = \App\Models\ElectronicInvoiceSetting::current();
    $zatcaPhaseTwo = $zatcaSettings->isPhaseTwo();
    $zatcaDocument = $zatcaSettings->is_enabled && $zatcaPhaseTwo
        ? \App\Models\ZatcaDocument::latestFor($zatcaType, (int) $zatcaId, $zatcaSettings->environment)
        : null;

    $zatcaTones = ['success' => 'success', 'warning' => 'warning', 'info' => 'info', 'danger' => 'danger'];
    $zatcaTone = $zatcaDocument ? ($zatcaTones[$zatcaDocument->tone()] ?? 'secondary') : 'secondary';

    // مقبولٌ ⇒ لا زرّ. معلَّقٌ أو متعثّر ⇒ إعادة إرسال. مرفوضٌ ⇒ إرسالٌ بعد التصحيح
    $zatcaAction = match (true) {
        $zatcaDocument === null => 'send',
        $zatcaDocument->isAccepted() => null,
        $zatcaDocument->canRetry() => 'resend',
        default => 'send_corrected',
    };
@endphp

@if ($zatcaSettings->is_enabled && ! $zatcaPhaseTwo)
    {{-- المرحلة الأولى: لا إرسال ولا حالة — يُقال ذلك كي لا يُبحَث عن زرّ --}}
    <div class="alert alert-light d-flex align-items-center" style="border:1px solid rgba(0,0,0,.08)" role="status">
        <span>
            <i class="feather icon-shield"></i>
            <strong>{{ __('zatca.invoice.card_title') }}</strong>
            <span style="font-size:.86rem;margin-inline-start:.4rem">{{ __('zatca.phase.strip') }}</span>
        </span>
    </div>
@elseif ($zatcaSettings->is_enabled)
    <div class="alert alert-{{ $zatcaTone === 'secondary' ? 'light' : $zatcaTone }} d-flex align-items-center flex-wrap"
        style="gap:.75rem;border:1px solid rgba(0,0,0,.08)" role="status">
        <div style="flex:1;min-width:240px">
            <strong>
                <i class="feather icon-shield"></i>
                {{ __('zatca.invoice.card_title') }}
                <span style="font-weight:400;opacity:.75">
                    · {{ __('sales_settings.electronic.env.'.$zatcaSettings->environment) }}
                </span>
            </strong>

            <span class="badge badge-{{ $zatcaTone }} bg-{{ $zatcaTone }}" style="margin-inline-start:.4rem">
                {{ $zatcaDocument ? __('zatca.status.'.$zatcaDocument->status) : __('zatca.status.not_sent') }}
            </span>

            @if ($zatcaDocument)
                <span style="font-size:.82rem;opacity:.8;margin-inline-start:.4rem">
                    {{ __('zatca.profile.'.$zatcaDocument->profile) }}
                    · {{ __('zatca.documents.icv') }} {{ $zatcaDocument->icv }}
                    @if ($zatcaDocument->submitted_at)
                        · {{ $zatcaDocument->submitted_at->format('Y-m-d H:i') }}
                    @endif
                </span>

                @if (! empty($zatcaDocument->errors) || filled($zatcaDocument->last_error))
                    <ul class="mb-0 mt-50" style="font-size:.82rem;padding-inline-start:1.2rem">
                        @forelse ((array) $zatcaDocument->errors as $zatcaMessage)
                            <li>{{ $zatcaMessage }}</li>
                        @empty
                            <li>{{ $zatcaDocument->last_error }}</li>
                        @endforelse
                    </ul>
                @endif

                @if (! empty($zatcaDocument->warnings))
                    <ul class="mb-0 mt-50" style="font-size:.8rem;padding-inline-start:1.2rem;opacity:.85">
                        @foreach ($zatcaDocument->warnings as $zatcaMessage)
                            <li>{{ $zatcaMessage }}</li>
                        @endforeach
                    </ul>
                @endif
            @elseif (! $zatcaSettings->canSubmit())
                <span style="font-size:.82rem;margin-inline-start:.4rem">{{ __('zatca.invoice.not_ready') }}</span>
            @endif
        </div>

        <div style="white-space:nowrap">
            {{-- نسخة العميل: PDF في جوفه ملفّ الهيئة. وملفّ XML وحده مكانه
                 «سجلّ الإرسال» — شأنُ المحاسب والمراجع لا مُصدِر الفاتورة --}}
            @if ($zatcaDocument && $zatcaDocument->isAccepted())
                @permission('sales.invoices.export_pdf')
                    <a href="{{ route('electronic_invoice.documents.pdf', $zatcaDocument->id) }}" target="_blank"
                        class="btn btn-sm btn-outline-primary" title="{{ __('zatca.pdf.hint') }}">
                        <i class="feather icon-file-text"></i> {{ __('zatca.pdf.download') }}
                    </a>
                @endpermission
            @endif

            @if ($zatcaAction !== null)
                @if ($zatcaSettings->canSubmit())
                    @permission('sales.invoices.send')
                        <form action="{{ route('electronic_invoice.send', ['type' => $zatcaType, 'id' => $zatcaId]) }}"
                            method="POST" class="d-inline"
                            onsubmit="if (! confirm(@js(__('zatca.invoice.send_confirm')))) { return false; } this.querySelector('button').disabled = true;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="feather icon-send"></i> {{ __('zatca.invoice.'.$zatcaAction) }}
                            </button>
                        </form>
                    @endpermission
                @else
                    @permission('sales.settings.view')
                        <a href="{{ route('settings.electronic_invoice') }}" class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-settings"></i> {{ __('zatca.invoice.open_settings') }}
                        </a>
                    @endpermission
                @endif
            @endif
        </div>
    </div>
@endif
