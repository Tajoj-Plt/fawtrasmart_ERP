@extends('sitting::master')

@section('title', 'تقرير الإقفال السنوي — '.$report['year']->name)

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $year = $report['year'];
        $closed = $report['closed'];
        $net = $report['net'];
        $retained = $report['retained'];
        $money = fn ($v) => number_format((float) $v, 2);
        $showJournal = Route::has('journal.show');
        $stateLabel = [
            'active' => ['قائم', 'on'],
            'reversed' => ['معكوس (بإعادة فتح)', 'warn'],
            'reversal' => ['قيد عكسي', 'info'],
        ];
    @endphp

    <style>
        .cr-table td, .cr-table th { white-space: nowrap; vertical-align: middle; }
        .cr-table .num { text-align: left; direction: ltr; font-variant-numeric: tabular-nums; }
        .cr-total td { font-weight: 700; background: #f7f9fc; }
        .cr-section { margin-bottom: 1.25rem; }
        .cr-section h5 { font-weight: 700; margin-bottom: .75rem; }
        .cr-check-ok { color: #2f855a; }
        .cr-check-bad { color: #c53030; }
        @media print {
            .no-print, .main-menu, .header-navbar, .footer, .customizer, .buy-now { display: none !important; }
            .app-content, .content-wrapper { margin: 0 !important; padding: 0 !important; }
            .st-card { box-shadow: none !important; border: 1px solid #ccc; break-inside: avoid; }
        }
    </style>

    {{-- ══════════ الترويسة ══════════ --}}
    <div class="st-card cr-section" id="crHeader">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h4 class="mb-1">
                    <i class="feather icon-file-text"></i> تقرير الإقفال السنوي — {{ $year->name }}
                    <span class="st-badge {{ $closed ? 'warn' : 'on' }}">{{ $closed ? 'مقفلة' : 'مفتوحة' }}</span>
                </h4>
                <div class="text-muted">
                    <span class="st-mono">{{ $year->start_date->format('Y-m-d') }}</span> &larr;
                    <span class="st-mono">{{ $year->end_date->format('Y-m-d') }}</span>
                    @if ($closed && $report['closed_at'])
                        · أُقفلت في <span class="st-mono">{{ $report['closed_at'] }}</span>
                        @if ($report['closed_by']) بواسطة {{ $report['closed_by'] }} @endif
                    @endif
                </div>
                <div class="text-muted mt-1">
                    حساب الترحيل:
                    <strong>{{ $retained ? trim(($retained->code ? $retained->code.' — ' : '').$retained->name) : 'غير مضبوط' }}</strong>
                </div>
            </div>
            <div class="st-actions no-print">
                <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="feather icon-arrow-right"></i> الفترات المحاسبية
                </a>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                    <i class="feather icon-printer"></i> طباعة
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="crExport">
                    <i class="feather icon-download"></i> Excel
                </button>
            </div>
        </div>

        @unless ($closed)
            <div class="alert alert-info d-block mt-2 mb-0" role="note">
                <i class="feather icon-info"></i>
                السنة مفتوحة — هذه <strong>معاينة</strong> لما سيُرحَّل عند إقفالها بأرصدتها الحالية، لا قيودٌ مُنشأة.
                @if (! empty($readiness['blockers']))
                    <strong class="d-block mt-1">لا تُقفل بعد:</strong>
                    <ul class="mb-0">
                        @foreach ($readiness['blockers'] as $blocker)
                            <li>{{ $blocker['message'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endunless
    </div>

    {{-- ══════════ الملخّص ══════════ --}}
    <div class="st-stats cr-section">
        <div class="st-stat">
            <div class="text-muted">إجمالي الإيرادات</div>
            <h4 class="num">{{ $money($report['revenue']) }}</h4>
        </div>
        <div class="st-stat">
            <div class="text-muted">إجمالي المصروفات</div>
            <h4 class="num">{{ $money($report['expenses']) }}</h4>
        </div>
        <div class="st-stat">
            <div class="text-muted">{{ $net >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</div>
            <h4 class="num {{ $net >= 0 ? 'cr-check-ok' : 'cr-check-bad' }}">{{ $money(abs($net)) }}</h4>
        </div>
        <div class="st-stat">
            <div class="text-muted">{{ $closed ? 'قيود الإقفال القائمة' : 'قيود الإقفال المتوقّعة' }}</div>
            <h4>{{ $closed ? $report['entries']->count() : $report['branches']->count() }}</h4>
        </div>
    </div>

    {{-- ══════════ المطابقة ══════════ --}}
    @if ($closed)
        <div class="st-card cr-section">
            <h5><i class="feather icon-check-square"></i> مطابقة الإقفال</h5>
            <table class="table table-sm st-table cr-table mb-0">
                <tbody>
                    @foreach ($report['checks'] as $check)
                        <tr>
                            <td style="width:2rem" class="{{ $check['ok'] ? 'cr-check-ok' : 'cr-check-bad' }}">
                                <i class="feather {{ $check['ok'] ? 'icon-check-circle' : 'icon-x-circle' }}"></i>
                            </td>
                            <td>{{ $check['label'] }}</td>
                            <td class="text-muted">{{ $check['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ══════════ الفروع ══════════ --}}
    <div class="st-card cr-section">
        <h5><i class="feather icon-git-branch"></i> الإقفال حسب الفرع</h5>
        <div class="table-responsive">
            <table class="table table-sm st-table cr-table cr-export" data-title="الفروع">
                <thead>
                    <tr>
                        <th>الفرع</th>
                        <th class="num">الإيرادات</th>
                        <th class="num">المصروفات</th>
                        <th class="num">الصافي</th>
                        <th>قيد الإقفال</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['branches'] as $row)
                        <tr>
                            <td>{{ $row['branch'] }}</td>
                            <td class="num">{{ $money($row['revenue']) }}</td>
                            <td class="num">{{ $money($row['expenses']) }}</td>
                            <td class="num {{ $row['net'] >= 0 ? 'cr-check-ok' : 'cr-check-bad' }}">{{ $money($row['net']) }}</td>
                            <td>
                                @if ($row['entry_id'] && $showJournal)
                                    <a href="{{ route('journal.show', $row['entry_id']) }}">{{ $row['entry_number'] }}</a>
                                @else
                                    {{ $row['entry_number'] ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">لا أرصدة إيرادات أو مصروفات في هذه السنة.</td></tr>
                    @endforelse
                </tbody>
                @if ($report['branches']->isNotEmpty())
                    <tfoot>
                        <tr class="cr-total">
                            <td>الإجمالي</td>
                            <td class="num">{{ $money($report['revenue']) }}</td>
                            <td class="num">{{ $money($report['expenses']) }}</td>
                            <td class="num">{{ $money($net) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ══════════ الحسابات ══════════ --}}
    <div class="st-card cr-section">
        <h5><i class="feather icon-list"></i> الحسابات المُقفلة</h5>
        <p class="text-muted mb-2">
            كلُّ حساب إيرادٍ أو مصروفٍ يُقفل بعكس رصيده في السنة فيصير صفراً، والفرقُ يُرحَّل إلى حساب الترحيل.
        </p>
        <div class="table-responsive">
            <table class="table table-sm st-table cr-table cr-export" data-title="الحسابات">
                <thead>
                    <tr>
                        <th rowspan="2">رقم الحساب</th>
                        <th rowspan="2">اسم الحساب</th>
                        <th rowspan="2">النوع</th>
                        <th colspan="2" class="text-center">الرصيد قبل الإقفال</th>
                        <th colspan="2" class="text-center">قيد الإقفال</th>
                    </tr>
                    <tr>
                        <th class="num">مدين</th>
                        <th class="num">دائن</th>
                        <th class="num">مدين</th>
                        <th class="num">دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['accounts'] as $row)
                        <tr>
                            <td class="st-mono">{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['kind'] === 'revenue' ? 'إيراد' : 'مصروف' }}</td>
                            <td class="num">{{ $row['balance_debit'] ? $money($row['balance_debit']) : '' }}</td>
                            <td class="num">{{ $row['balance_credit'] ? $money($row['balance_credit']) : '' }}</td>
                            <td class="num">{{ $row['closing_debit'] ? $money($row['closing_debit']) : '' }}</td>
                            <td class="num">{{ $row['closing_credit'] ? $money($row['closing_credit']) : '' }}</td>
                        </tr>
                    @endforeach
                    @if ($retained && $report['accounts']->isNotEmpty())
                        <tr>
                            <td class="st-mono">{{ $retained->code }}</td>
                            <td><strong>{{ $retained->name }}</strong></td>
                            <td>{{ $net >= 0 ? 'ترحيل الربح' : 'ترحيل الخسارة' }}</td>
                            <td></td>
                            <td></td>
                            <td class="num">{{ $net < 0 ? $money(-$net) : '' }}</td>
                            <td class="num">{{ $net > 0 ? $money($net) : '' }}</td>
                        </tr>
                    @endif
                </tbody>
                @if ($report['accounts']->isNotEmpty())
                    @php
                        $closingDebit = $report['accounts']->sum('closing_debit') + ($net < 0 ? -$net : 0);
                        $closingCredit = $report['accounts']->sum('closing_credit') + ($net > 0 ? $net : 0);
                    @endphp
                    <tfoot>
                        <tr class="cr-total">
                            <td colspan="3">الإجمالي</td>
                            <td class="num">{{ $money($report['accounts']->sum('balance_debit')) }}</td>
                            <td class="num">{{ $money($report['accounts']->sum('balance_credit')) }}</td>
                            <td class="num">{{ $money($closingDebit) }}</td>
                            <td class="num">{{ $money($closingCredit) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ══════════ السجلّ ══════════ --}}
    <div class="st-card cr-section">
        <h5><i class="feather icon-clock"></i> سجلّ الإقفال وإعادة الفتح</h5>
        <p class="text-muted mb-2">
            إعادةُ فتح السنة لا تحذف قيود الإقفال: تُنشئ لكلّ قيدٍ قيداً عكسياً بتاريخ آخر السنة ويُعلَّم الأصلُ «معكوساً»،
            ثم يُنشئ الإقفالُ التالي قيوداً جديدة بأرقامٍ جديدة.
        </p>
        <div class="table-responsive">
            <table class="table table-sm st-table cr-table cr-export" data-title="السجل">
                <thead>
                    <tr>
                        <th>رقم القيد</th>
                        <th>الحالة</th>
                        <th>الفرع</th>
                        <th>تاريخ القيد</th>
                        <th>أُنشئ في</th>
                        <th>بواسطة</th>
                        <th class="num">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['history'] as $row)
                        <tr>
                            <td class="st-mono">
                                @if ($showJournal)
                                    <a href="{{ route('journal.show', $row['id']) }}">{{ $row['number'] }}</a>
                                @else
                                    {{ $row['number'] }}
                                @endif
                            </td>
                            <td>
                                <span class="st-badge {{ $stateLabel[$row['state']][1] }}">{{ $stateLabel[$row['state']][0] }}</span>
                                @if ($row['reverses'])
                                    <small class="text-muted">يعكس {{ $row['reverses'] }}</small>
                                @endif
                            </td>
                            <td>{{ $row['branch'] }}</td>
                            <td class="st-mono">{{ $row['date'] }}</td>
                            <td class="st-mono">{{ $row['created_at'] }}</td>
                            <td>{{ $row['by'] }}</td>
                            <td class="num">{{ $money($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">لم تُقفل هذه السنة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        /*
            تصديرُ جداول التقرير إلى Excel — ملفُّ HTML بامتداد xls يفتحه Excel
            بجداوله كما هي، بلا مكتبةٍ خارجية.
        */
        document.getElementById('crExport')?.addEventListener('click', function () {
            var header = document.querySelector('#crHeader h4');
            var parts = ['<h3>' + (header ? header.innerText.trim() : '') + '</h3>'];
            document.querySelectorAll('table.cr-export').forEach(function (table) {
                parts.push('<h4>' + table.dataset.title + '</h4>' + table.outerHTML);
            });
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
                + '<head><meta charset="UTF-8"></head><body dir="rtl">' + parts.join('<br>') + '</body></html>';
            var blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'تقرير-الإقفال-{{ $year->name }}.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    </script>
@endpush
