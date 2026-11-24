{{--
    محفظة عميل واحد: الرصيد وكشفُ حركته.

    **الإجراءات لم تعد هنا.** كانت ثلاثة نماذج متجاورة (إيداع/سحب/سداد)
    كلٌّ في بطاقةٍ ضيّقة تحت الرصيد، فتخلط الشاشةُ «ما الرصيد» بـ«ماذا
    أفعل به»، ولا موضع فيها لشرح القيد الذي سيُكتب. صارت شاشةً مستقلّة
    (`wallet/create`) بنموذجٍ واحد يتبدّل بنوع الحركة — على قياس بقيّة
    مستندات النظام: قائمة، وعرض، وإضافة، وتعديل.

    وقيود الحركات (انظر `ClientWalletService`):
      إيداع        من ح/ الخزينة       إلى ح/ محفظة العميل
      سحب          من ح/ محفظة العميل  إلى ح/ الخزينة
      سداد فاتورة   من ح/ محفظة العميل  إلى ح/ ذمم العميل
      ردّ سداد      من ح/ ذمم العميل    إلى ح/ محفظة العميل

    كشف الحركة عشرة أعمدة، فجدولُه `cl-dense` — حشوة الإعدادات تضاعف
    ارتفاع الصفّ عند هذا العدد.
--}}
@extends('client::master')

@section('title')
    محفظة {{ $client->display_name }}
@stop

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $mayPost = canAny(['clients.wallet.deposit', 'clients.wallet.withdraw', 'clients.wallet.pay_invoice']);
    @endphp

    <div class="cl-scope">
        @section('page-actions')
            @if ($mayPost)
                <a href="{{ route('clients.wallet.create', $client->id) }}"
                    class="btn btn-primary {{ $walletsParentMapped ? '' : 'disabled' }}">
                    <i class="feather icon-plus"></i>
                    <span>حركة جديدة</span>
                </a>
            @endif
            <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-user"></i>
                <span>ملف العميل</span>
            </a>
            <a href="{{ route('clients.wallet.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                <span>رجوع</span>
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => 'محفظة '.$client->display_name,
            'icon' => 'credit-card',
            'sub' => $client->code ? 'كود العميل: '.$client->code : null,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'محافظ العملاء' => route('clients.wallet.index'),
                $client->display_name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                حساب <strong>«أرصدة محافظ العملاء»</strong> غير مربوط — اربطه من «الحسابات المحاسبية» قبل أي حركة.
            </div>
        @endunless

        {{-- ══════════ الرصيد وملخّصه ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon {{ $balance > 0 ? 'cl-stat__icon--success' : '' }}">
                    <i class="feather icon-credit-card"></i>
                </span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value {{ $balance > 0 ? 'text-success' : '' }}">
                        {{ CurrencySettings::format($balance) }}
                    </p>
                    <p class="cl-stat__label">الرصيد الحالي — مالٌ للعميل عندنا يُخصَم منه عند السداد</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--info"><i class="feather icon-repeat"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $transactions->total() }}</p>
                    <p class="cl-stat__label">عدد الحركات — لكلٍّ منها قيدٌ متوازن</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-file-text"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $invoices->count() }}</p>
                    <p class="cl-stat__label">فواتير قابلة للسداد من المحفظة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ مرتجعاتٌ وإشعاراتٌ دائنة — للاطّلاع لا للسداد ══════════
             لا صلة لهما بحركات المحفظة: كلاهما يُنقص ما على العميل لا
             يُسدَّد من ماله عندنا، فلا يدخلان نموذج «حركة جديدة». لكنّ
             المستخدم يحتاج رؤيتهما من محفظة العميل بلا تنقّلٍ لشاشةٍ
             أخرى. --}}
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card cl-table-card">
                    <div class="cl-table-head">
                        <h4 class="cl-table-head__title">
                            <i class="feather icon-corner-up-left"></i>
                            مرتجعات العميل
                            <span class="cl-count">{{ $returns->count() }}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>الكود</th>
                                        <th>التاريخ</th>
                                        <th class="text-center">الإجمالي</th>
                                        <th class="text-center">عرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($returns as $return)
                                        <tr>
                                            <td>{{ $return->code }}</td>
                                            <td>{{ LanguageSettings::formatDate($return->created_at, true) }}</td>
                                            <td class="text-center" data-num>{{ CurrencySettings::number($return->grand_total) }}</td>
                                            <td class="text-center">
                                                <div class="cl-actions">
                                                    <a href="{{ route('ReturnIInvoices.show', $return->id) }}"
                                                        class="btn btn-outline-secondary" title="عرض المرتجع">
                                                        <i class="feather icon-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">لا مرتجعات لهذا العميل</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="card cl-table-card">
                    <div class="cl-table-head">
                        <h4 class="cl-table-head__title">
                            <i class="feather icon-file-minus"></i>
                            الإشعارات الدائنة السارية
                            <span class="cl-count">{{ $creditNotes->count() }}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>الرقم</th>
                                        <th>التاريخ</th>
                                        <th class="text-center">الإجمالي</th>
                                        <th class="text-center">عرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($creditNotes as $note)
                                        <tr>
                                            <td>{{ $note->credit_number ?: '#'.$note->id }}</td>
                                            <td>{{ LanguageSettings::formatDate($note->credit_date, true) }}</td>
                                            <td class="text-center" data-num>{{ CurrencySettings::number($note->grand_total) }}</td>
                                            <td class="text-center">
                                                <div class="cl-actions">
                                                    <a href="{{ route('CreditNotes.show', $note->id) }}"
                                                        class="btn btn-outline-secondary" title="عرض الإشعار">
                                                        <i class="feather icon-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">لا إشعارات دائنة سارية لهذا العميل</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ كشف الحركة ══════════ --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-list"></i>
                    كشف حركة المحفظة
                    <span class="cl-count">{{ $transactions->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover cl-dense mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>الحركة</th>
                                <th>المرجع</th>
                                <th class="text-center">مدين (خصم)</th>
                                <th class="text-center">دائن (إيداع)</th>
                                <th class="text-center">الرصيد بعدها</th>
                                <th>الملاحظة</th>
                                <th>المستخدم</th>
                                <th class="text-center">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                <tr>
                                    <td data-num>{{ $transaction->id }}</td>
                                    <td>{{ LanguageSettings::formatDate($transaction->created_at, true) }}</td>
                                    <td>
                                        <span class="cl-state {{ $transaction->isCredit() ? 'cl-state--on' : 'cl-state--danger' }}">
                                            {{ $transaction->typeLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($transaction->invoice)
                                            {{ $transaction->invoice->code }}
                                        @elseif ($transaction->treasury)
                                            {{ $transaction->treasury->name }}
                                        @else
                                            —
                                        @endif

                                        {{-- القيد المرافق: كلّ حركةٍ هنا لها قيدٌ متوازن، وبلا
                                             رابطٍ إليه يبقى الرقم في العمود بلا سندٍ يُراجَع. --}}
                                        @if ($transaction->journal_entry_id)
                                            <a href="{{ route('journal.show', $transaction->journal_entry_id) }}"
                                                class="text-muted" title="القيد المحاسبي المرتبط">
                                                <i class="feather icon-book-open"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        @if ($transaction->isCredit())
                                            <span class="text-muted">—</span>
                                        @else
                                            <span class="text-danger">{{ CurrencySettings::number($transaction->amount) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        @if ($transaction->isCredit())
                                            <span class="text-success">{{ CurrencySettings::number($transaction->amount) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        <strong>{{ CurrencySettings::number($transaction->balance_after) }}</strong>
                                    </td>
                                    <td>{{ $transaction->note ?: '—' }}</td>
                                    <td>{{ $transaction->creator->name ?? '—' }}</td>
                                    <td class="text-center">
                                        @if ($mayPost)
                                            <div class="cl-actions">
                                                <a href="{{ route('clients.wallet.edit', [$client->id, $transaction->id]) }}"
                                                    class="btn btn-outline-primary" title="تعديل الحركة">
                                                    <i class="feather icon-edit-2"></i>
                                                </a>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="cl-empty">
                                            <span class="cl-empty__icon"><i class="feather icon-inbox"></i></span>
                                            <p class="cl-empty__title">لا حركة على هذه المحفظة بعد</p>
                                            @if ($mayPost)
                                                <p class="cl-empty__hint">أوّل إيداعٍ يفتح المحفظة ويكتب قيدها.</p>
                                                <a href="{{ route('clients.wallet.create', $client->id) }}"
                                                    class="btn btn-primary {{ $walletsParentMapped ? '' : 'disabled' }}">
                                                    <i class="feather icon-plus"></i> أضف أوّل حركة
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($transactions->hasPages())
                <div class="cl-table-foot">
                    <p class="cl-table-foot__count">
                        عرض {{ $transactions->firstItem() }} إلى {{ $transactions->lastItem() }} من {{ $transactions->total() }} حركة
                    </p>
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
