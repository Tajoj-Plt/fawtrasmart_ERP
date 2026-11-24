<!-- تبويب نظرة عامة -->
<div class="tab-pane active" id="overview" role="tabpanel">
    @php
        $ov_currency = $currencySymbol ?? ($account_setting->currency ?? 'SAR');
        $ov_totalSales = $invoices->sum(fn ($i) => $i->grand_total ?? $i->total ?? 0);
        $ov_invoiceCount = $invoices->count();
        $ov_due = $due ?? 0;
        $ov_creditLimit = $creditLimit->value ?? null;

        $ov_lastVisit = $visits->first();
        $ov_lastInvoice = $invoices->first();
        $ov_lastPayment = $payments->first();
        $ov_currentStatus = $statuses->firstWhere('id', $client->status_id);

        $ov_totalPaid = $payments->where('type', 'client payments')->sum('amount');

        $ov_upcomingAppointments = $client->appointments
            ->where('status', 1)
            ->sortBy('created_at')
            ->take(5);

        $ov_appointmentStatusMap = [
            1 => ['class' => 'bg-warning', 'text' => 'مجدول'],
            2 => ['class' => 'bg-success', 'text' => 'تم'],
            3 => ['class' => 'bg-danger', 'text' => 'صرف النظر'],
            4 => ['class' => 'bg-info', 'text' => 'معاد جدولته'],
        ];

        $ov_visitStatusMap = [
            'active'   => ['label' => 'مكتملة', 'dot' => 'is-success'],
            'unactive' => ['label' => 'غير مكتملة', 'dot' => 'is-warning'],
        ];

        $ov_paymentStatusMap = [
            1 => ['class' => 'bg-success', 'text' => 'مدفوعة بالكامل'],
            2 => ['class' => 'bg-info', 'text' => 'مدفوعة جزئياً'],
            3 => ['class' => 'bg-danger', 'text' => 'غير مدفوعة'],
            4 => ['class' => 'bg-secondary', 'text' => 'مستلمة'],
        ];
    @endphp

    <!-- بطاقات الإحصائيات -->
    <div class="cx-stat-grid mb-4">
        <div class="cx-stat-card">
            <div class="cx-stat-top">
                <span class="cx-stat-label">إجمالي المبيعات</span>
                <span class="cx-stat-icon is-primary"><i class="fa fa-chart-line"></i></span>
            </div>
            <div class="cx-stat-value">{{ number_format($ov_totalSales, 2) }} {!! $ov_currency !!}</div>
        </div>

        <div class="cx-stat-card">
            <div class="cx-stat-top">
                <span class="cx-stat-label">عدد الفواتير</span>
                <span class="cx-stat-icon is-info"><i class="fa fa-file-invoice"></i></span>
            </div>
            <div class="cx-stat-value">{{ number_format($ov_invoiceCount) }}</div>
        </div>

        <div class="cx-stat-card">
            <div class="cx-stat-top">
                <span class="cx-stat-label">الرصيد المستحق</span>
                <span class="cx-stat-icon {{ $ov_due > 0 ? 'is-danger' : 'is-success' }}"><i class="fa fa-coins"></i></span>
            </div>
            <div class="cx-stat-value">{{ number_format($ov_due, 2) }} {!! $ov_currency !!}</div>
            @if ($ov_due > 0)
                <span class="cx-stat-trend is-down"><i class="fa fa-arrow-down"></i> مستحق على العميل</span>
            @else
                <span class="cx-stat-trend is-up"><i class="fa fa-check"></i> مُسوّى</span>
            @endif
        </div>

        <div class="cx-stat-card">
            <div class="cx-stat-top">
                <span class="cx-stat-label">الحد الائتماني</span>
                <span class="cx-stat-icon is-warning"><i class="fa fa-dollar-sign"></i></span>
            </div>
            <div class="cx-stat-value">
                {{ $ov_creditLimit !== null ? number_format($ov_creditLimit, 2) . ' ' . ($account_setting->currency ?? 'SAR') : 'غير محدد' }}
            </div>
        </div>
    </div>

    <!-- الصف الثاني: آخر زيارة / آخر فاتورة / آخر دفعة / ملخص الحالة -->
    <div class="row g-3 mb-1">
        <div class="col-lg-3 col-md-6">
            <div class="cx-panel">
                <div class="cx-panel-head"><h6><i class="fa fa-walking"></i> آخر زيارة</h6></div>
                <div class="cx-panel-body">
                    @if ($ov_lastVisit)
                        <div class="cx-kv-list">
                            <div class="cx-kv-row"><span class="cx-kv-key">التاريخ</span><span class="cx-kv-val">{{ \Carbon\Carbon::parse($ov_lastVisit->visit_date)->format('Y-m-d') }}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">الموظف</span><span class="cx-kv-val">{{ $ov_lastVisit->employee->name ?? 'غير محدد' }}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">الحالة</span><span class="cx-kv-val">{{ ($visitStates ?? [])[$ov_lastVisit->id]['label'] ?? ($ov_visitStatusMap[$ov_lastVisit->status ?? '']['label'] ?? ($ov_lastVisit->status ?? '—')) }}</span></div>
                        </div>
                    @else
                        <div class="cx-mini-empty"><i class="fa fa-walking"></i>لا توجد زيارات بعد</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="cx-panel">
                <div class="cx-panel-head"><h6><i class="fa fa-file-invoice"></i> آخر عملية بيع</h6></div>
                <div class="cx-panel-body">
                    @if ($ov_lastInvoice)
                        <div class="cx-kv-list">
                            <div class="cx-kv-row"><span class="cx-kv-key">رقم الفاتورة</span><span class="cx-kv-val">#{{ $ov_lastInvoice->code }}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">الإجمالي</span><span class="cx-kv-val">{{ number_format($ov_lastInvoice->grand_total ?? $ov_lastInvoice->total, 2) }} {!! $ov_currency !!}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">التاريخ</span><span class="cx-kv-val">{{ $ov_lastInvoice->created_at?->format('Y-m-d') }}</span></div>
                        </div>
                    @else
                        <div class="cx-mini-empty"><i class="fa fa-file-invoice"></i>لا توجد فواتير بعد</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="cx-panel">
                <div class="cx-panel-head"><h6><i class="fa fa-credit-card"></i> آخر دفعة</h6></div>
                <div class="cx-panel-body">
                    @if ($ov_lastPayment)
                        <div class="cx-kv-list">
                            <div class="cx-kv-row"><span class="cx-kv-key">المبلغ</span><span class="cx-kv-val">{{ number_format($ov_lastPayment->amount, 2) }} {!! $ov_currency !!}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">الفاتورة</span><span class="cx-kv-val">{{ $ov_lastPayment->invoice->code ?? '--' }}</span></div>
                            <div class="cx-kv-row"><span class="cx-kv-key">التاريخ</span><span class="cx-kv-val">{{ $ov_lastPayment->payment_date ?? $ov_lastPayment->created_at?->format('Y-m-d') }}</span></div>
                        </div>
                    @else
                        <div class="cx-mini-empty"><i class="fa fa-credit-card"></i>لا توجد مدفوعات بعد</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="cx-panel">
                <div class="cx-panel-head"><h6><i class="fa fa-id-badge"></i> حالة العميل</h6></div>
                <div class="cx-panel-body">
                    <div class="cx-kv-list">
                        <div class="cx-kv-row"><span class="cx-kv-key">الحالة</span><span class="cx-kv-val">{{ $ov_currentStatus->name ?? 'غير محدد' }}</span></div>
                        <div class="cx-kv-row"><span class="cx-kv-key">تاريخ الإنشاء</span><span class="cx-kv-val">{{ $client->created_at?->format('Y-m-d') }}</span></div>
                        <div class="cx-kv-row"><span class="cx-kv-key">آخر تحديث</span><span class="cx-kv-val">{{ $client->updated_at?->format('Y-m-d') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الصف الثالث -->
    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="cx-panel">
                <div class="cx-panel-head">
                    <h6><i class="fa fa-sticky-note"></i> آخر الملاحظات</h6>
                    <a href="#notes" class="cx-panel-link cx-jump-tab" data-target="#notes">عرض الكل</a>
                </div>
                <div class="cx-panel-body">
                    @forelse ($ClientRelations->take(3) as $note)
                        <div class="cx-note-mini">
                            <div class="cx-note-mini-head">
                                <span class="cx-note-mini-employee"><i class="fa fa-user-circle text-muted"></i> {{ $note->employee->name ?? 'غير محدد' }}</span>
                                <span class="cx-note-mini-date">{{ $note->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="cx-note-mini-text">{{ $note->description ?: '—' }}</p>
                        </div>
                    @empty
                        <div class="cx-mini-empty"><i class="fa fa-sticky-note"></i>لا توجد ملاحظات بعد</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="cx-panel">
                <div class="cx-panel-head">
                    <h6><i class="fa fa-walking"></i> سجل الزيارات الأخيرة</h6>
                    <a href="#visits" class="cx-panel-link cx-jump-tab" data-target="#visits">عرض الكل</a>
                </div>
                <div class="cx-panel-body">
                    @forelse ($visits->take(5) as $visit)
                        <div class="cx-kv-row">
                            <span class="cx-kv-key">
                                <i class="fa fa-circle {{ ($visitStates ?? [])[$visit->id]['text'] ?? (($ov_visitStatusMap[$visit->status ?? '']['dot'] ?? 'is-secondary') === 'is-success' ? 'text-success' : (($ov_visitStatusMap[$visit->status ?? '']['dot'] ?? '') === 'is-warning' ? 'text-warning' : 'text-secondary')) }}" style="font-size:.5rem;"></i>
                                {{ \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') }}
                            </span>
                            <span class="cx-kv-val">
                                {{-- المدّة الفعلية إن سُجّلت (مساحة عمل الزيارة تكتبها عند الخروج) — انظر تبويب الزيارات --}}
                                @if ($visit->duration_label)
                                    <span class="text-muted" style="font-size:.75rem;"><i class="fa fa-stopwatch"></i> {{ $visit->duration_label }}</span>
                                @endif
                                {{ ($visitStates ?? [])[$visit->id]['label'] ?? ($ov_visitStatusMap[$visit->status ?? '']['label'] ?? ($visit->status ?? '—')) }}
                            </span>
                        </div>
                    @empty
                        <div class="cx-mini-empty"><i class="fa fa-walking"></i>لا توجد زيارات بعد</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="cx-panel">
                <div class="cx-panel-head">
                    <h6><i class="fa fa-calendar-check"></i> المواعيد القادمة</h6>
                    <a href="#appointments" class="cx-panel-link cx-jump-tab" data-target="#appointments">عرض الكل</a>
                </div>
                <div class="cx-panel-body">
                    @forelse ($ov_upcomingAppointments as $appointment)
                        <div class="cx-kv-row">
                            <span class="cx-kv-key">{{ $appointment->title }}</span>
                            <span class="cx-kv-val">
                                <span class="badge {{ $ov_appointmentStatusMap[$appointment->status]['class'] ?? 'bg-secondary' }}">
                                    {{ $ov_appointmentStatusMap[$appointment->status]['text'] ?? 'غير محدد' }}
                                </span>
                            </span>
                        </div>
                    @empty
                        <div class="cx-mini-empty"><i class="fa fa-calendar-check"></i>لا توجد مواعيد قادمة</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- الصف الرابع: ملخص مالي + آخر الفواتير -->
    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="cx-panel">
                <div class="cx-panel-head"><h6><i class="fa fa-wallet"></i> ملخص مالي</h6></div>
                <div class="cx-panel-body">
                    <div class="cx-kv-list">
                        <div class="cx-kv-row"><span class="cx-kv-key">إجمالي الفواتير</span><span class="cx-kv-val">{{ number_format($ov_totalSales, 2) }} {!! $ov_currency !!}</span></div>
                        <div class="cx-kv-row"><span class="cx-kv-key">إجمالي المدفوعات</span><span class="cx-kv-val">{{ number_format($ov_totalPaid, 2) }} {!! $ov_currency !!}</span></div>
                        <div class="cx-kv-row"><span class="cx-kv-key">الرصيد المستحق</span><span class="cx-kv-val {{ $ov_due > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($ov_due, 2) }} {!! $ov_currency !!}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="cx-panel">
                <div class="cx-panel-head">
                    <h6><i class="fa fa-file-invoice"></i> آخر الفواتير</h6>
                    <a href="#invoices" class="cx-panel-link cx-jump-tab" data-target="#invoices">عرض الكل</a>
                </div>
                <div class="cx-panel-body p-0">
                    @if ($invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="cx-mini-table">
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>التاريخ</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoices->take(5) as $invoice)
                                        <tr>
                                            <td>#{{ $invoice->code }}</td>
                                            <td>{{ $invoice->created_at?->format('Y-m-d') }}</td>
                                            <td>{{ number_format($invoice->grand_total ?? $invoice->total, 2) }} {!! $ov_currency !!}</td>
                                            <td>
                                                <span class="badge {{ $ov_paymentStatusMap[$invoice->payment_status]['class'] ?? 'bg-dark' }}">
                                                    {{ $ov_paymentStatusMap[$invoice->payment_status]['text'] ?? 'غير معروفة' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="cx-mini-empty py-4"><i class="fa fa-file-invoice"></i>لا توجد فواتير بعد</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
