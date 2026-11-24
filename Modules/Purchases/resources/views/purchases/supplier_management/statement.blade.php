{{--
    كشف حساب المورّد.

    أُعيد بناء الشاشة على نظام تصميم وحدة المشتريات (`page-head` / `stats-cards`
    / `po-card` / `modern-table`)، ثمّ على `po-`، وهي اليوم على قياس شاشات
    العملاء كبقيّة شاشات الموردين — البادئة `sl-`.

    **المطبوعة تغيّر مصدرها.** كانت الشاشة تطبع نفسها بنسخة HTML مكتوبة في
    JavaScript داخل الملف: لا يعرفها محرّر القوالب، ولا تتبع «مظهر النظام»،
    ولا تقرأ بيانات المنشأة من إعداداتها. صارت قائمة «المطبوعات» تُبنى من
    «قوالب المستندات» لنوع `supplier_statement` — عشرة قوالب مزروعة، وللمستخدم
    أن يصمّم غيرها من الشاشة نفسها.

    ما يأتي من الإعدادات:
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • قوالب المطبوعة          ← «قوالب المستندات» + «طريقة الطباعة»
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.supplier.statement') }} — {{ $supplier->trade_name }}
@stop


@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        /* رصيد المحفظة يُقرأ ويُعرض **مستقلاً** عن الذمّة.

           المحفظة حسابٌ آخر للمورد نفسه (دفعات مقدَّمة لدى المورد، تحت
           المدينين)، والكشف يصف الذمّة وحدها. فجمعُهما يخرج برقمٍ لا يصف
           أيّهما — ولذلك يُقرأ الحسابُ هنا بنطاق `ledger()`، ويُعرض رصيد
           المحفظة سطراً بجانبه لا داخله. */
        $supplierWalletBalance = \App\Services\Wallets\SupplierWalletService::balance($supplier);

        $isCredit = ($account->balance_type ?? 'credit') === 'credit';
        $direction = $finalBalance >= 0
            ? ($isCredit ? 'دائن' : 'مدين')
            : ($isCredit ? 'مدين' : 'دائن');
    @endphp

    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    {{-- ورقةُ `po-` من أجل «قائمة الطباعة» وحدها: القائمة قالبٌ مشترك بين
         شاشات المشتريات كلِّها وأصنافُها `po-print-menu` و`po-status`،
         فبلا هذه الورقة تخرج بلا تنسيق. وقواعدُها كلُّها مقيَّدة بأصناف
         `po-*` فلا تزاحم نظام التصميم هنا. --}}
    @include('purchases::partials.style')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.supplier.statement').' — '.$supplier->trade_name,
            'icon' => 'file-invoice',
            'crumbs' => [
                __('purchase_cycle.supplier.section') => route('SupplierManagement.index'),
                $supplier->trade_name => route('SupplierManagement.show', $supplier->id),
                __('purchase_cycle.supplier.statement') => null,
            ],
        ])

        <div>
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            {{-- الإحصاءات بمكوّن نظام التصميم لا بـ`stats-cards`: ذاك على
                 أصناف `stat-card-modern` التي تُنمَّط في ورقة `po-`، وهي لم
                 تعد محمَّلةً على هذه الشاشة. --}}
            <div class="sl-stats">
                <div class="sl-stat">
                    <span class="sl-stat__icon"><i class="fas fa-list"></i></span>
                    <div class="sl-stat__body">
                        <p class="sl-stat__value">{{ LanguageSettings::formatNumber($operationsPaginator->total(), 0) }}</p>
                        <p class="sl-stat__label">{{ __('purchase_cycle.search.heading') }}</p>
                    </div>
                </div>
                <div class="sl-stat">
                    <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-arrow-down"></i></span>
                    <div class="sl-stat__body">
                        <p class="sl-stat__value">{{ CurrencySettings::format($totalDebit) }}</p>
                        <p class="sl-stat__label">{{ __('purchase_cycle.supplier.total_debit') }}</p>
                    </div>
                </div>
                <div class="sl-stat">
                    <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-arrow-up"></i></span>
                    <div class="sl-stat__body">
                        <p class="sl-stat__value">{{ CurrencySettings::format($totalCredit) }}</p>
                        <p class="sl-stat__label">{{ __('purchase_cycle.supplier.total_credit') }}</p>
                    </div>
                </div>
                <div class="sl-stat">
                    <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-balance-scale"></i></span>
                    <div class="sl-stat__body">
                        <p class="sl-stat__value">{{ CurrencySettings::format(abs($finalBalance)) }}</p>
                        <p class="sl-stat__label">{{ __('purchase_cycle.supplier.final_balance') }} ({{ $direction }})</p>
                    </div>
                </div>
            </div>

            {{-- المحفظة: رصيدٌ لنا لدى المورد، لا يدخل الذمّة أعلاه --}}
            @if ($supplierWalletBalance != 0)
                <div class="sl-stats">
                    <div class="sl-stat">
                        <span class="sl-stat__icon sl-stat__icon--info"><i class="fas fa-wallet"></i></span>
                        <div class="sl-stat__body">
                            <p class="sl-stat__value">{{ CurrencySettings::format($supplierWalletBalance) }}</p>
                            <p class="sl-stat__label">
                                رصيد المحفظة (دفعات مقدَّمة) — حسابٌ مستقلٌّ عن الذمّة، لا يُجمع مع الرصيد أعلاه.
                                <a href="{{ route('purchases.supplier-wallet.show', $supplier->id) }}">فتح كشف المحفظة</a>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- الفترة والمطبوعات --}}
            <div class="card sl-filters">
                <div class="sl-filters__head">
                    <h4 class="sl-filters__title">
                        <i class="fas fa-filter"></i> {{ __('purchase_cycle.search.heading') }}
                    </h4>

                    <div class="sl-form-bar__actions">
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates ?? collect(),
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'SupplierManagement.statementPrint',
                            'pdfRoute' => 'SupplierManagement.statementPdf',
                            'documentId' => $supplier->id,
                        ])
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="statementFilter" method="GET"
                        action="{{ route('SupplierManagement.statement', $supplier->id) }}">
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <div class="form-group">
                                    <label for="from_date">{{ __('purchase_cycle.supplier.from_date') }}</label>
                                    <input type="date" name="from_date" id="from_date" class="form-control"
                                        value="{{ request('from_date') }}">
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <div class="form-group">
                                    <label for="to_date">{{ __('purchase_cycle.supplier.to_date') }}</label>
                                    <input type="date" name="to_date" id="to_date" class="form-control"
                                        value="{{ request('to_date') }}">
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <div class="form-group">
                                    <label>{{ __('purchase_cycle.supplier.balance') }}</label>
                                    <div class="form-control-plaintext num">
                                        <strong>{{ CurrencySettings::format(abs($finalBalance)) }}</strong>
                                        <small class="sl-help">({{ $direction }})</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="sl-form-foot">
                    <small class="sl-help">
                        <i class="far fa-calendar me-1"></i>{{ LanguageSettings::dateFormat() }}
                    </small>
                    <div class="sl-form-bar__actions">
                        <a href="{{ route('SupplierManagement.statement', $supplier->id) }}"
                            class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>{{ __('purchase_orders.search.clear') }}
                        </a>
                        <button type="submit" form="statementFilter" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>{{ __('purchase_cycle.actions.search') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- الحركة --}}
            <div class="card sl-table-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-list"></i> {{ __('purchase_cycle.supplier.statement') }}
                    </h4>
                    <span class="sl-count">
                        {{ LanguageSettings::formatNumber($operationsPaginator->total(), 0) }}
                    </span>
                </div>

                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table table-hover sl-dense mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 60px;">#</th>
                                    <th class="text-center">{{ __('purchase_cycle.fields.date') }}</th>
                                    <th>{{ __('purchase_cycle.fields.code') }}</th>
                                    <th>{{ __('purchase_cycle.fields.notes') }}</th>
                                    <th class="text-center">{{ __('purchase_cycle.supplier.total_debit') }}</th>
                                    <th class="text-center">{{ __('purchase_cycle.supplier.total_credit') }}</th>
                                    <th class="text-center">{{ __('purchase_cycle.supplier.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($operationsPaginator as $operation)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration + $operationsPaginator->firstItem() - 1 }}</td>
                                        <td class="text-center num">{{ LanguageSettings::formatDate($operation['date']) ?: '—' }}</td>
                                        <td>
                                            @if (! empty($operation['journal_entry_id']))
                                                <a href="#" class="journal-link"
                                                    data-id="{{ $operation['journal_entry_id'] }}">
                                                    <strong>{{ $operation['reference_number'] ?: '—' }}</strong>
                                                </a>
                                            @else
                                                {{ $operation['reference_number'] ?: '—' }}
                                            @endif
                                        </td>
                                        <td>{{ $operation['description'] ?: '—' }}</td>
                                        <td class="text-center num">{{ CurrencySettings::format($operation['debit']) }}</td>
                                        <td class="text-center num">{{ CurrencySettings::format($operation['credit']) }}</td>
                                        <td class="text-center num"><strong>{{ CurrencySettings::format($operation['balance']) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-file-invoice-dollar" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="mt-2 text-muted">{{ __('purchase_cycle.supplier.no_operations') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if ($operationsPaginator->total() > 0)
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-center"><strong>{{ __('purchase_cycle.fields.grand_total') }}</strong></td>
                                        <td class="text-center num"><strong>{{ CurrencySettings::format($totalDebit) }}</strong></td>
                                        <td class="text-center num"><strong>{{ CurrencySettings::format($totalCredit) }}</strong></td>
                                        <td class="text-center num"><strong>{{ CurrencySettings::format(abs($finalBalance)) }}</strong></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <div class="card-footer p-0">
                        @include('components.custom-pagination', ['paginator' => $operationsPaginator])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $statementConfig = [
            'journalRoute' => route('SupplierManagement.journalEntryDetails', ['journalEntryId' => '__ID__']),
            'i18n' => [
                'entry' => __('purchase_cycle.fields.code'),
                'close' => __('purchase_orders.confirm.no'),
                'failed' => __('purchase_cycle.messages.validation_failed'),
                'debit' => __('purchase_cycle.supplier.total_debit'),
                'credit' => __('purchase_cycle.supplier.total_credit'),
            ],
        ];
    @endphp

    <script>
        window.supplierStatement = @json($statementConfig);

        $(document).ready(function() {
            // تفاصيل القيد — كانت الشاشة تفتحه في نافذة منفصلة، والنافذة
            // المنبثقة تُبقي القارئ في سياق الكشف
            $(document).on('click', '.journal-link', function(e) {
                e.preventDefault();

                const id = $(this).data('id');
                const url = window.supplierStatement.journalRoute.replace('__ID__', id);

                $.get(url, function(response) {
                    const details = (response && response.details) || [];

                    const rows = details.map(function(row) {
                        return '<tr>' +
                            '<td>' + (row.account_name || row.account || '') + '</td>' +
                            '<td>' + (row.debit || 0) + '</td>' +
                            '<td>' + (row.credit || 0) + '</td>' +
                            '</tr>';
                    }).join('');

                    Swal.fire({
                        title: window.supplierStatement.i18n.entry + ' #' + id,
                        html: '<table class="table modern-table"><thead><tr>' +
                            '<th></th>' +
                            '<th>' + window.supplierStatement.i18n.debit + '</th>' +
                            '<th>' + window.supplierStatement.i18n.credit + '</th>' +
                            '</tr></thead><tbody>' + rows + '</tbody></table>',
                        width: 720,
                        confirmButtonText: window.supplierStatement.i18n.close,
                    });
                }).fail(function() {
                    Swal.fire(window.supplierStatement.i18n.failed, '', 'error');
                });
            });
        });
    </script>
@endsection
