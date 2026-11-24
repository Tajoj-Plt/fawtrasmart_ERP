{{--
    زرُّ «إرسال بالبريد» ونافذتُه — لمستندات المبيعات كلِّها.

    كان الزرُّ في شاشة عرض السعر والمرتجع والإشعار يحمل هذا الاسم وتحته
    `https://wa.me/…`، فيفتح واتساب. والدفعة بلا زرٍّ أصلاً. صار الإرسالُ
    بريداً حقيقياً يحمل المستند مصيَّراً من قالبه المصمَّم، ونصَّ رسالةٍ
    من «قوالب البريد» — انظر `App\Services\Sales\SalesDocumentMailer`.

    المطلوب:
      $mailRoute      — مسار الإرسال (POST)
      $mailNumber     — رقم المستند، لعنوان النافذة
      $mailEmail      — بريد العميل المحفوظ (قد يكون فارغاً)
      $mailTemplates  — قوالب طباعة النوع (Collection)، ليختار منها المرسِل

    والاختياري:
      $mailDefaultTemplate — القالب الافتراضي للنوع
      $mailClientName      — اسم العميل، يُعرض في النافذة
      $mailAsDropdownItem  — بندُ قائمةٍ بدل زرّ مستقلّ
      $mailId              — لاحقةٌ تميّز النافذة حين تتكرّر في صفحة
      $mailDocumentType    — نوعُ القالب المرفق حين يختلف عن نوع الشاشة
                             (فاتورةٌ ضريبية تُرسَل بقالب `tax_invoice`
                             كما تُطبع به)
--}}

@php
    $mailId = $mailId ?? 'doc';
    $mailModalId = 'sendEmailModal-'.$mailId;
    $mailTemplates = $mailTemplates ?? collect();
    $mailDefaultTemplate = $mailDefaultTemplate ?? null;
    $mailClientName = $mailClientName ?? '';
    $mailHasEmail = ! empty($mailEmail) && filter_var($mailEmail, FILTER_VALIDATE_EMAIL);
@endphp

@if (! empty($mailAsDropdownItem))
    <a class="dropdown-item" href="#" data-toggle="modal" data-bs-toggle="modal"
       data-target="#{{ $mailModalId }}" data-bs-target="#{{ $mailModalId }}">
        <i class="feather icon-mail"></i> {{ __('sales_documents.email.action') }}
    </a>
@else
    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-bs-toggle="modal"
            data-target="#{{ $mailModalId }}" data-bs-target="#{{ $mailModalId }}">
        <i class="feather icon-mail"></i> {{ __('sales_documents.email.action') }}
    </button>
@endif

{{-- `data-sl-portal`: النافذة تُكتب حيث وُضع زرُّها — وزرُّ الفاتورة بندٌ
     في قائمةٍ منسدلة، فتُكتب النافذة داخل `dropdown-menu`. والقائمة
     تُغلق بمجرّد الضغط فتُخفي النافذة معها، بينما يضع بوتستراب ظلَّه على
     الصفحة: شاشةٌ معتمةٌ بلا نافذة. السكربت أسفلَه ينقلها إلى `body` --}}
<div class="modal fade" id="{{ $mailModalId }}" data-sl-portal tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="{{ $mailRoute }}" class="modal-content">
            @csrf

            @isset($mailDocumentType)
                <input type="hidden" name="document_type" value="{{ $mailDocumentType }}">
            @endisset

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-mail"></i>
                    {{ __('sales_documents.email.title', ['number' => $mailNumber]) }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                {{-- بريدٌ محفوظٌ يُملأ مسبقاً، وغيابُه لا يقفل النافذة:
                     يُرسَل لبريدٍ يُكتب هنا مرّةً دون تعديل بطاقة العميل --}}
                <div class="form-group">
                    <label for="{{ $mailModalId }}-email">{{ __('sales_documents.email.recipient') }}</label>
                    <input type="email" class="form-control" dir="ltr" required
                           id="{{ $mailModalId }}-email" name="email"
                           value="{{ $mailHasEmail ? $mailEmail : '' }}"
                           placeholder="name@example.com">
                    @unless ($mailHasEmail)
                        <small class="form-text text-muted">
                            <i class="feather icon-alert-circle"></i>
                            {{ __('sales_documents.email.no_client_email', ['client' => $mailClientName]) }}
                        </small>
                    @endunless
                </div>

                {{-- القالبُ هو ما يُصيَّر ويُرفَق PDF: نفسُه الذي يُطبع من
                     الشاشة، فلا يختلف المرسَلُ عن المطبوع --}}
                <div class="form-group">
                    <label for="{{ $mailModalId }}-template">{{ __('sales_documents.email.template') }}</label>
                    @if ($mailTemplates->isNotEmpty())
                        <select class="form-control" id="{{ $mailModalId }}-template" name="template">
                            @foreach ($mailTemplates as $mailTemplate)
                                <option value="{{ $mailTemplate->id }}"
                                    @selected($mailDefaultTemplate && $mailDefaultTemplate->id === $mailTemplate->id)>
                                    {{ $mailTemplate->name }}
                                    @if ($mailDefaultTemplate && $mailDefaultTemplate->id === $mailTemplate->id)
                                        — {{ __('sales_documents.email.template_default') }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" class="form-control" disabled
                               value="{{ __('sales_documents.email.template_builtin') }}">
                        <small class="form-text text-muted">
                            {{ __('sales_documents.email.template_none') }}
                        </small>
                    @endif
                </div>

                <div class="form-group mb-0">
                    <label for="{{ $mailModalId }}-note">{{ __('sales_documents.email.note') }}</label>
                    <textarea class="form-control" rows="3" id="{{ $mailModalId }}-note" name="note"
                              maxlength="1000" placeholder="{{ __('sales_documents.email.note_hint') }}"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">
                    {{ __('sales_documents.ui.cancel') }}
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-send"></i> {{ __('sales_documents.email.submit') }}
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            /* نقلُ نوافذ الإرسال إلى `body`.
               النافذة تُكتب حيث وُضع زرُّها، وقد يكون داخل قائمةٍ منسدلة
               (شاشة الفاتورة) أو داخل حاويةٍ لها سياقُ تكديسٍ خاص. القائمة
               تُغلق عند الضغط فتُخفي النافذة معها، ويضع بوتستراب ظلَّه على
               الصفحة — فيرى المستخدم شاشةً معتمةً بلا نافذة. ونقلُها إلى
               `body` يفصل ظهورَها عن مصير ما احتواها.
               ويبقى الزرّ عاملاً: `data-bs-target` مُحدِّدٌ عامّ لا نسبيّ. */
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.modal[data-sl-portal]').forEach(function (modal) {
                    if (modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                });
            });
        </script>
    @endpush
@endonce
