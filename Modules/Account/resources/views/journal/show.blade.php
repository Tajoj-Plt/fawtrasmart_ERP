@extends('account::master')

@section('title')
    قيد #{{ $entry->id }}
@stop

@section('content')
<style>
    .je-tabs { display: flex; gap: 0; border-bottom: 2px solid #e8f4fc; margin-bottom: 0; background: #fafcfe; padding: 0 16px; }
    .je-tab { padding: 14px 24px; cursor: pointer; border: none; background: none; color: #7a8b9a; font-size: 13px; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s ease; }
    .je-tab:hover { color: #4a90d9; background: rgba(74, 144, 217, 0.05); }
    .je-tab.active { color: #2c7be5; border-bottom-color: #2c7be5; font-weight: 600; background: rgba(44, 123, 229, 0.08); }
    .je-tab i { margin-left: 8px; }
    .je-panel { display: none; animation: fadeIn 0.3s ease; padding: 24px; }
    .je-panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .je-table { font-size: 13px; }
    .je-table th { background: #f8fafc !important; color: #4a6785; font-weight: 600; }
    .je-table th, .je-table td { padding: 12px 16px !important; border-color: #eef2f7 !important; }
    .je-table th i { margin-left: 8px; opacity: 0.7; }
    .je-table tbody tr:hover { background: #f8fbff; }
    .je-label { color: #8896a4; font-size: 12px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
    .je-label i { font-size: 11px; opacity: 0.8; }
    .je-value { font-size: 15px; color: #2d3748; font-weight: 500; margin-top: 4px; }
    .je-info-card { background: #fff; border: 1px solid #e8eff7; border-radius: 12px; padding: 16px 20px; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .je-info-card:hover { border-color: #d0e3f7; box-shadow: 0 2px 8px rgba(44, 123, 229, 0.08); }
    .je-main-card { background: #fff; border: 1px solid #e8eff7; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .badge-type { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
    .badge-sales { background: #e8f5e9; color: #2e7d32; }
    .badge-purchase { background: #ffebee; color: #c62828; }
    .badge-payment { background: #e3f2fd; color: #1565c0; }
    .badge-client { background: #e8eaf6; color: #3949ab; }
    .badge-supplier { background: #f3e5f5; color: #7b1fa2; }
    .badge-employee { background: #fff3e0; color: #ef6c00; }
    .badge-default { background: #f5f5f5; color: #616161; }
    .summary-card { background: #fff; border-radius: 16px; padding: 20px; text-align: center; border: 1px solid #e8eff7; transition: all 0.2s; }
    .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .summary-icon { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; }
</style>

<div class="content-body">
    {{-- $canEdit يُحسب في JournalEntryController::show() — صلاحية account.journal.edit ضمن نطاق الرؤية، لا صلاحيات النظام القديم --}}

    <!-- Header -->
    <div class="card mb-4" style="border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-radius: 16px; overflow: hidden;">
        <div class="card-body py-4 px-4" style="background: #fff;">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 16px;">
                    <div style="width: 52px; height: 52px; background: linear-gradient(135deg, #2c7be5, #5a9cf0); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(44, 123, 229, 0.3);">
                        <i class="fas fa-file-invoice text-white" style="font-size: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold" style="color: #1a202c; font-size: 18px;">قيد يومية #{{ $entry->id }}</h5>
                        <div style="color: #718096; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <span style="display: flex; align-items: center; gap: 6px;"><i class="far fa-calendar-alt"></i> {{ $entry->date->format('Y-m-d') }}</span>
                            <span style="color: #cbd5e0;">|</span>
                            <span>{{ $entry->description ?? 'بدون وصف' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="gap: 12px;">
                    @if($entry->isBalanced())
                        <span style="background: #e8f5e9; color: #2e7d32; padding: 10px 18px; border-radius: 25px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i> متوازن
                        </span>
                    @else
                        <span style="background: #ffebee; color: #c62828; padding: 10px 18px; border-radius: 25px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-circle"></i> غير متوازن
                        </span>
                    @endif
                    @if($canEdit)
                        <a href="{{ route('journal.edit', $entry->id) }}" class="btn" style="background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 6px;" title="تعديل">
                            <i class="fas fa-pen" style="font-size: 13px;"></i>
                        </a>
                    @endif
                    <button class="btn print-btn" style="background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 6px;" title="طباعة">
                        <i class="fas fa-print" style="font-size: 13px;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="card" style="border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-radius: 16px; overflow: hidden;">
        <div class="card-body p-0">
            <div class="je-tabs">
                <button class="je-tab active" data-tab="preview">
                    <i class="fas fa-eye"></i> معاينة
                </button>
                <button class="je-tab" data-tab="details">
                    <i class="fas fa-info-circle"></i> التفاصيل
                </button>
                <button class="je-tab" data-tab="related">
                    <i class="fas fa-link"></i> المرتبطات
                    @if(isset($relatedItems) && count($relatedItems) > 0)
                        <span style="background: #2c7be5; color: #fff; font-size: 11px; margin-right: 8px; padding: 4px 10px; border-radius: 12px;">{{ count($relatedItems) }}</span>
                    @endif
                </button>
                <button class="je-tab" data-tab="logs">
                    <i class="fas fa-history"></i> السجل
                </button>
            </div>

            <div style="padding: 0;">

            <!-- معاينة القيد -->
            <div class="je-panel active" id="panel-preview">
                <div style="background: linear-gradient(135deg, #e8f0f8, #f0f5fa); padding: 20px; border-radius: 12px; max-height: 75vh; overflow: auto;">
                    <div style="background: #fff; max-width: 550px; margin: 0 auto; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px;">
                        <div style="transform: scale(0.85); transform-origin: top center;">
                            {{--
                                المتنُ لا الغلاف: `journal.pdf` مستندٌ
                                كامل، وضمُّه هنا كان يحقن `<body>` ثانياً
                                وأنماطاً عاريةً تسري على الصفحة كلِّها.
                            --}}
                            @include('account::journal.partials.document', ['entry' => $entry])
                        </div>
                    </div>
                </div>
            </div>

            <!-- التفاصيل -->
            <div class="je-panel" id="panel-details">
                <div class="row g-4 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-hashtag"></i> رقم القيد
                            </div>
                            <div class="je-value">{{ $entry->id }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="far fa-calendar"></i> التاريخ
                            </div>
                            <div class="je-value">{{ $entry->date->format('Y-m-d') }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-tag"></i> نوع المرجع
                            </div>
                            <div class="je-value">{{ $entry->reference_type_name }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-link"></i> رقم المرجع
                            </div>
                            <div class="je-value">
                                {{ $entry->reference_number ?? '-' }}
                                <x-taxable-badge :on="$entry->is_taxable" />
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-coins"></i> العملة
                            </div>
                            <div class="je-value">{{ $entry->currency }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-flag"></i> الحالة
                            </div>
                            <div class="je-value">{{ $entry->status_text }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-user-plus"></i> أنشئ بواسطة
                            </div>
                            <div class="je-value">{{ $entry->createdByEmployee->name ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="je-info-card">
                            <div class="je-label">
                                <i class="fas fa-user-check"></i> اعتمد بواسطة
                            </div>
                            <div class="je-value">{{ $entry->approvedByEmployee->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                @if($entry->description)
                <div class="mb-4 p-4" style="background: #fffbf0; border: 1px solid #f5e6c4; border-radius: 12px; font-size: 14px; color: #6b5a2c;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <i class="fas fa-quote-right" style="color: #d4a012; font-size: 16px; margin-top: 2px;"></i>
                        <span>{{ $entry->description }}</span>
                    </div>
                </div>
                @endif

                <div class="je-main-card" style="padding: 0; overflow: hidden;">
                    <table class="table table-bordered je-table mb-0">
                        <thead>
                            <tr>
                                <th><i class="fas fa-barcode"></i> الكود</th>
                                <th><i class="fas fa-wallet"></i> الحساب</th>
                                <th><i class="fas fa-align-right"></i> البيان</th>
                                <th><i class="fas fa-building"></i> مركز التكلفة</th>
                                <th class="text-end" style="color: #2e7d32;"><i class="fas fa-arrow-down"></i> مدين</th>
                                <th class="text-end" style="color: #c62828;"><i class="fas fa-arrow-up"></i> دائن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entry->details as $d)
                            <tr>
                                <td>
                                    <code style="background: #f0f7ff; color: #2c7be5; padding: 4px 8px; border-radius: 6px; font-size: 12px;">{{ $d->account->code ?? '' }}</code>
                                </td>
                                <td style="font-weight: 500;">{{ $d->account->name ?? '' }}</td>
                                <td style="color: #718096;">{{ $d->description ?? '' }}</td>
                                <td style="color: #5a6978;">{{ $d->costCenter->name ?? '-' }}</td>
                                <td class="text-end" style="color: #2e7d32; font-weight: 600;">{{ $d->debit > 0 ? number_format($d->debit, 2) : '' }}</td>
                                <td class="text-end" style="color: #c62828; font-weight: 600;">{{ $d->credit > 0 ? number_format($d->credit, 2) : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot style="background: #f8fafc;">
                            <tr>
                                <td colspan="4" class="text-end fw-bold" style="color: #4a5568; font-size: 14px;">الإجمالي</td>
                                <td class="text-end fw-bold" style="color: #2e7d32; font-size: 15px;">{{ number_format($entry->getTotalDebit(), 2) }}</td>
                                <td class="text-end fw-bold" style="color: #c62828; font-size: 15px;">{{ number_format($entry->getTotalCredit(), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- المرتبطات -->
            <div class="je-panel" id="panel-related">
                @if(isset($relatedItems) && count($relatedItems) > 0)
                    @php
                        $itemsByType = collect($relatedItems)->groupBy('type');
                        $badgeClasses = [
                            'sales_invoice' => 'badge-sales',
                            'purchase_invoice' => 'badge-purchase',
                            'payment' => 'badge-payment',
                            'client' => 'badge-client',
                            'supplier' => 'badge-supplier',
                            'employee' => 'badge-employee',
                            'receipt' => 'badge-sales',
                            'expense' => 'badge-purchase',
                            'received_cheque' => 'badge-payment',
                            'payable_cheque' => 'badge-payment',
                            'paid_invoice' => 'badge-sales',
                            'paid_purchase_invoice' => 'badge-purchase',
                            'linked_invoice' => 'badge-sales',
                            'cost_center' => 'badge-default',
                            'branch' => 'badge-default',
                        ];
                    @endphp
                    
                    <!-- ملخص العناصر -->
                    <div class="row g-4 mb-4">
                        @foreach($itemsByType as $type => $items)
                            @php
                                $firstItem = $items->first();
                                $icons = [
                                    'sales_invoice' => 'fa-file-invoice-dollar',
                                    'purchase_invoice' => 'fa-file-invoice',
                                    'payment' => 'fa-money-bill-wave',
                                    'client' => 'fa-user',
                                    'supplier' => 'fa-truck',
                                    'employee' => 'fa-user-tie',
                                    'receipt' => 'fa-hand-holding-usd',
                                    'expense' => 'fa-money-bill-wave',
                                    'received_cheque' => 'fa-money-check',
                                    'payable_cheque' => 'fa-money-check-alt',
                                    'paid_invoice' => 'fa-file-invoice-dollar',
                                    'paid_purchase_invoice' => 'fa-file-invoice',
                                    'linked_invoice' => 'fa-link',
                                    'cost_center' => 'fa-sitemap',
                                    'branch' => 'fa-building',
                                ];
                                $bgColors = [
                                    'sales_invoice' => '#e8f5e9',
                                    'purchase_invoice' => '#ffebee',
                                    'payment' => '#e3f2fd',
                                    'client' => '#e8eaf6',
                                    'supplier' => '#f3e5f5',
                                    'employee' => '#fff3e0',
                                    'receipt' => '#e8f5e9',
                                    'expense' => '#ffebee',
                                    'received_cheque' => '#e0f7fa',
                                    'payable_cheque' => '#fff8e1',
                                    'paid_invoice' => '#e8f5e9',
                                    'paid_purchase_invoice' => '#ffebee',
                                    'linked_invoice' => '#e3f2fd',
                                    'cost_center' => '#f5f5f5',
                                    'branch' => '#e3f2fd',
                                ];
                                $iconBgColors = [
                                    'sales_invoice' => '#c8e6c9',
                                    'purchase_invoice' => '#ffcdd2',
                                    'payment' => '#bbdefb',
                                    'client' => '#c5cae9',
                                    'supplier' => '#e1bee7',
                                    'employee' => '#ffe0b2',
                                    'receipt' => '#c8e6c9',
                                    'expense' => '#ffcdd2',
                                    'received_cheque' => '#b2ebf2',
                                    'payable_cheque' => '#ffecb3',
                                    'paid_invoice' => '#c8e6c9',
                                    'paid_purchase_invoice' => '#ffcdd2',
                                    'linked_invoice' => '#bbdefb',
                                    'cost_center' => '#e0e0e0',
                                    'branch' => '#bbdefb',
                                ];
                                $textColors = [
                                    'sales_invoice' => '#2e7d32',
                                    'purchase_invoice' => '#c62828',
                                    'payment' => '#1565c0',
                                    'client' => '#3949ab',
                                    'supplier' => '#7b1fa2',
                                    'employee' => '#ef6c00',
                                    'receipt' => '#2e7d32',
                                    'expense' => '#c62828',
                                    'received_cheque' => '#00838f',
                                    'payable_cheque' => '#f57c00',
                                    'paid_invoice' => '#2e7d32',
                                    'paid_purchase_invoice' => '#c62828',
                                    'linked_invoice' => '#1565c0',
                                    'cost_center' => '#616161',
                                    'branch' => '#1565c0',
                                ];
                            @endphp
                            <div class="col-6 col-md-3">
                                <div class="summary-card" style="background: {{ $bgColors[$type] ?? '#f5f5f5' }};">
                                    <div class="summary-icon" style="background: {{ $iconBgColors[$type] ?? '#e0e0e0' }};">
                                        <i class="fas {{ $icons[$type] ?? 'fa-file' }}" style="color: {{ $textColors[$type] ?? '#616161' }}; font-size: 18px;"></i>
                                    </div>
                                    <h3 class="mb-1 fw-bold" style="color: {{ $textColors[$type] ?? '#616161' }}; font-size: 28px;">{{ count($items) }}</h3>
                                    <div style="color: {{ $textColors[$type] ?? '#616161' }}; font-size: 13px; opacity: 0.9;">{{ $firstItem['type_name'] ?? $type }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                <div class="je-main-card" style="padding: 0; overflow: hidden;">
                    <table class="table table-hover je-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>النوع</th>
                                <th>التفاصيل</th>
                                <th>التاريخ</th>
                                <th class="text-end">المبلغ</th>
                                <th style="width: 70px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($relatedItems as $i => $item)
                            @php 
                                $badgeClass = $badgeClasses[$item['type']] ?? 'badge-default';
                                $globalIcons = [
                                    'sales_invoice' => 'fa-file-invoice-dollar',
                                    'purchase_invoice' => 'fa-file-invoice',
                                    'payment' => 'fa-money-bill-wave',
                                    'client' => 'fa-user',
                                    'supplier' => 'fa-truck',
                                    'employee' => 'fa-user-tie',
                                    'receipt' => 'fa-hand-holding-usd',
                                    'expense' => 'fa-money-bill-wave',
                                    'received_cheque' => 'fa-money-check',
                                    'payable_cheque' => 'fa-money-check-alt',
                                    'paid_invoice' => 'fa-file-invoice-dollar',
                                    'paid_purchase_invoice' => 'fa-file-invoice',
                                    'linked_invoice' => 'fa-link',
                                    'cost_center' => 'fa-sitemap',
                                    'branch' => 'fa-building',
                                ];
                            @endphp
                            <tr>
                                <td style="color: #a0aec0;">{{ $i + 1 }}</td>
                                <td>
                                    <span class="badge-type {{ $badgeClass }}">
                                        <i class="fas {{ $globalIcons[$item['type']] ?? 'fa-file' }}"></i>
                                        {{ $item['type_name'] ?? $item['type'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold" style="color: #2d3748;">
                                    @if(in_array($item['type'], ['sales_invoice', 'purchase_invoice', 'linked_invoice', 'paid_invoice', 'paid_purchase_invoice']))
                                        فاتورة #{{ $item['number'] ?? $item['id'] }}
                                        @if(isset($item['paid_amount']) && $item['paid_amount'] > 0)
                                            <small style="color: #2e7d32; font-weight: normal;">(مدفوع: {{ number_format($item['paid_amount'], 2) }})</small>
                                        @endif
                                    @elseif(in_array($item['type'], ['payment', 'receipt', 'expense']))
                                        سند #{{ $item['code'] ?? $item['number'] ?? $item['id'] }}
                                    @elseif(in_array($item['type'], ['received_cheque', 'payable_cheque']))
                                        شيك #{{ $item['cheque_number'] ?? $item['id'] }}
                                        @if(isset($item['status']))
                                            <span class="badge bg-secondary ms-1" style="font-size: 10px;">{{ $item['status'] }}</span>
                                        @endif
                                    @else
                                        {{ $item['name'] ?? $item['code'] ?? $item['number'] ?? $item['id'] ?? '-' }}
                                    @endif
                                    </div>
                                    @if(isset($item['description']) && $item['description'])
                                        <small style="color: #a0aec0; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                            <i class="fas fa-comment"></i> {{ \Str::limit($item['description'], 50) }}
                                        </small>
                                    @endif
                                    @if(isset($item['client']))
                                        <small style="color: #a0aec0; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                            <i class="fas fa-user"></i> {{ $item['client'] }}
                                        </small>
                                    @elseif(isset($item['supplier']))
                                        <small style="color: #a0aec0; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                            <i class="fas fa-truck"></i> {{ $item['supplier'] }}
                                        </small>
                                    @elseif(isset($item['payee_name']))
                                        <small style="color: #a0aec0; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                            <i class="fas fa-user-check"></i> {{ $item['payee_name'] }}
                                        </small>
                                    @elseif(isset($item['drawer_bank']))
                                        <small style="color: #a0aec0; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                            <i class="fas fa-university"></i> {{ $item['drawer_bank'] }}
                                        </small>
                                    @endif
                                </td>
                                <td style="color: #718096;">
                                    <span style="display: flex; align-items: center; gap: 6px;">
                                        <i class="far fa-calendar"></i> {{ $item['date'] ?? $item['due_date'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold" style="color: #2c7be5; font-size: 14px;">
                                    @if(isset($item['amount']) && $item['amount'] > 0)
                                        {{ number_format($item['amount'], 2) }}
                                    @elseif(isset($item['total_amount']) && $item['total_amount'] > 0)
                                        {{ number_format($item['total_amount'], 2) }}
                                    @elseif(isset($item['allocation_amount']))
                                        {{ number_format($item['allocation_amount'], 2) }}
                                    @else
                                        <span style="color: #cbd5e0;">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(isset($item['url']) && $item['url'])
                                        <a href="{{ $item['url'] }}" class="btn btn-sm" style="background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 10px;" target="_blank" title="عرض">
                                            <i class="fas fa-external-link-alt" style="font-size: 11px;"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <div style="width: 90px; height: 90px; background: #f7fafc; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <i class="fas fa-link fa-2x" style="color: #cbd5e0;"></i>
                    </div>
                    <h5 style="color: #4a5568; margin-bottom: 8px;">لا توجد عناصر مرتبطة</h5>
                    <p style="color: #a0aec0; font-size: 14px; margin-bottom: 0;">هذا القيد غير مرتبط بأي فواتير أو سندات</p>
                </div>
                @endif
            </div>

            <!-- السجل -->
            <div class="je-panel" id="panel-logs">
                <div class="text-center py-5">
                    <div style="width: 90px; height: 90px; background: #f7fafc; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <i class="fas fa-history fa-2x" style="color: #cbd5e0;"></i>
                    </div>
                    <h5 style="color: #4a5568; margin-bottom: 8px;">سجل النشاطات</h5>
                    <p style="color: #a0aec0; font-size: 14px; margin-bottom: 0;">لا توجد سجلات مسجلة حتى الآن</p>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tabs
    document.querySelectorAll('.je-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.je-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.je-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + this.dataset.tab).classList.add('active');
        });
    });
    // Print
    document.querySelectorAll('.print-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { window.print(); });
    });
});
</script>
@endsection
