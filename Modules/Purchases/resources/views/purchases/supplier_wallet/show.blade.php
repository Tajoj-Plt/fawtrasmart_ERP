{{--
    محفظة مورد واحد: الرصيد وكشفُ حركته.

    الإجراءات في شاشتَيهما (`create` و`edit`) لا هنا — كانت ثلاثة نماذج
    متجاورة تخلط «ما الرصيد» بـ«ماذا أفعل به»، ولا موضع فيها لشرح القيد
    الذي سيُكتب.

    القيود (انظر `SupplierWalletService`):
      دفعة مقدَّمة      من ح/ محفظة المورد   إلى ح/ الخزينة
      استرداد نقدي     من ح/ الخزينة        إلى ح/ محفظة المورد
      سداد فاتورة شراء  من ح/ المورد (دائنة) إلى ح/ محفظة المورد

    والسطر الأخير يُنقص الطرفين معاً — ما علينا للمورد وما لنا لديه —
    وهو معنى «السداد من المحفظة».

    على قياس «محفظة العميل» بطلب المستخدم: إحصاءاتٌ في الأعلى، ثمّ
    المرتجعات والإشعارات المدينة للاطّلاع، ثمّ كشفُ الحركة. وكشفُ الحركة
    عشرة أعمدة فجدولُه `sl-dense` — حشوةُ الإعدادات تضاعف ارتفاع الصفّ عند
    هذا العدد.
--}}
@extends('purchases::master')

@php
    $displayName = $supplier->trade_name
        ?: (trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: '#'.$supplier->id);
@endphp

@section('title')
    محفظة {{ $displayName }}
@stop

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $mayPost = canAny([
            'purchases.supplier_wallet.deposit',
            'purchases.supplier_wallet.withdraw',
            'purchases.supplier_wallet.pay_invoice',
        ]);
    @endphp

    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">
        @section('page-actions')
            @if ($mayPost)
                <a href="{{ route('purchases.supplier-wallet.create', $supplier->id) }}"
                    class="btn btn-primary {{ $walletsParentMapped ? '' : 'disabled' }}">
                    <i class="fas fa-plus"></i>
                    <span>حركة جديدة</span>
                </a>
            @endif
            <a href="{{ route('SupplierManagement.show', $supplier->id) }}" class="btn btn-outline-secondary">
                <i class="fas fa-truck"></i>
                <span>ملف المورد</span>
            </a>
            <a href="{{ route('purchases.supplier-wallet.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                <span>رجوع</span>
            </a>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'محفظة '.$displayName,
            'icon' => 'wallet',
            'sub' => 'مالٌ لنا لدى المورد — تُخصَم منه فواتير الشراء',
            'crumbs' => [
                'المشتريات' => route('purchases.dashboard'),
                'محافظ الموردين' => route('purchases.supplier-wallet.index'),
                $displayName => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                حساب <strong>«دفعات مقدَّمة للموردين»</strong> غير مربوط — اربطه من «الحسابات المحاسبية» قبل أي حركة.
            </div>
        @endunless

        {{-- ══════════ الرصيد وملخّصه ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon {{ $balance > 0 ? 'sl-stat__icon--success' : '' }}">
                    <i class="fas fa-wallet"></i>
                </span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value {{ $balance > 0 ? 'text-success' : '' }}">
                        {{ CurrencySettings::format($balance) }}
                    </p>
                    <p class="sl-stat__label">الرصيد الحالي — مالٌ لنا لديه يُخصَم منه عند السداد</p>
                </div>
            </div>
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="fas fa-exchange-alt"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $transactions->total() }}</p>
                    <p class="sl-stat__label">عدد الحركات — لكلٍّ منها قيدٌ متوازن</p>
                </div>
            </div>
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-file-invoice"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $invoices->count() }}</p>
                    <p class="sl-stat__label">فواتير قابلة للسداد من المحفظة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ مرتجعاتٌ وإشعاراتٌ مدينة — للاطّلاع لا للسداد ══════════
             لا صلة لهما بحركات المحفظة: كلاهما يُنقص ما علينا للمورد لا
             يُسدَّد من مالنا لديه، فلا يدخلان نموذج «حركة جديدة». لكنّ
             المستخدم يحتاج رؤيتهما من محفظة المورد بلا تنقّلٍ لشاشةٍ
             أخرى — نظيرُ ما في محفظة العميل تماماً. --}}
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h4 class="sl-table-head__title">
                            <i class="fas fa-undo"></i>
                            مرتجعات المشتريات
                            <span class="sl-count">{{ $returns->count() }}</span>
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
                                            <td>{{ LanguageSettings::formatDate($return->date ?: $return->created_at, true) }}</td>
                                            <td class="text-center" data-num>{{ CurrencySettings::number($return->grand_total) }}</td>
                                            <td class="text-center">
                                                <div class="sl-actions">
                                                    <a href="{{ route('ReturnsInvoice.show', $return->id) }}"
                                                        class="btn btn-outline-secondary" title="عرض المرتجع">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">لا مرتجعات لهذا المورد</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h4 class="sl-table-head__title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            الإشعارات المدينة
                            <span class="sl-count">{{ $debitNotes->count() }}</span>
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
                                    @forelse ($debitNotes as $note)
                                        <tr>
                                            <td>{{ $note->code }}</td>
                                            <td>{{ LanguageSettings::formatDate($note->date ?: $note->created_at, true) }}</td>
                                            <td class="text-center" data-num>{{ CurrencySettings::number($note->grand_total) }}</td>
                                            <td class="text-center">
                                                <div class="sl-actions">
                                                    <a href="{{ route('CityNotices.show', $note->id) }}"
                                                        class="btn btn-outline-secondary" title="عرض الإشعار">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">لا إشعارات مدينة لهذا المورد</td>
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
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h4 class="sl-table-head__title">
                    <i class="fas fa-list"></i>
                    كشف حركة المحفظة
                    <span class="sl-count">{{ $transactions->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover sl-dense mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>الحركة</th>
                                <th>المرجع</th>
                                <th class="text-center">مدين (زيادة)</th>
                                <th class="text-center">دائن (خصم)</th>
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
                                        <span class="sl-state {{ $transaction->isIncrease() ? 'sl-state--on' : 'sl-state--danger' }}">
                                            {{ $transaction->typeLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($transaction->purchaseInvoice)
                                            {{ $transaction->purchaseInvoice->code }}
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
                                                <i class="fas fa-book-open"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        @if ($transaction->isIncrease())
                                            <span class="text-success">{{ CurrencySettings::number($transaction->amount) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        @if ($transaction->isIncrease())
                                            <span class="text-muted">—</span>
                                        @else
                                            <span class="text-danger">{{ CurrencySettings::number($transaction->amount) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center" data-num>
                                        <strong>{{ CurrencySettings::number($transaction->balance_after) }}</strong>
                                    </td>
                                    <td>{{ $transaction->note ?: '—' }}</td>
                                    <td>{{ $transaction->creator->name ?? '—' }}</td>
                                    <td class="text-center">
                                        @if ($mayPost)
                                            <div class="sl-actions">
                                                <a href="{{ route('purchases.supplier-wallet.edit', [$supplier->id, $transaction->id]) }}"
                                                    class="btn btn-outline-primary" title="تعديل الحركة">
                                                    <i class="fas fa-pen"></i>
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
                                        <div class="sl-empty">
                                            <span class="sl-empty__icon"><i class="fas fa-inbox"></i></span>
                                            <p class="sl-empty__title">لا حركة على هذه المحفظة بعد</p>
                                            @if ($mayPost)
                                                <p class="sl-empty__hint">أوّل دفعةٍ مقدَّمة تفتح المحفظة وتكتب قيدها.</p>
                                                <a href="{{ route('purchases.supplier-wallet.create', $supplier->id) }}"
                                                    class="btn btn-primary {{ $walletsParentMapped ? '' : 'disabled' }}">
                                                    <i class="fas fa-plus"></i> أضف أوّل حركة
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
                <div class="sl-table-foot">
                    <p class="sl-table-foot__count">
                        عرض {{ $transactions->firstItem() }} إلى {{ $transactions->lastItem() }} من {{ $transactions->total() }} حركة
                    </p>
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
