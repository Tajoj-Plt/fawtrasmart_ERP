{{--
    إشعارات السداد الواردة من بوابة العملاء.

    الإشعارُ إعلانُ العميل أنه سدّد — لا سندَ ولا قيد. و«تسجيل سند القبض»
    يفتح شاشةَ السند المعتادة معبّأةً بمبلغ الإشعار ومرجعه؛ فإن حُفظ السند
    خُتم الإشعارُ «معتمداً» وارتبط به. لا منطقَ تحصيلٍ في هذه الشاشة.

    @see \Modules\Client\Http\Controllers\ClientPaymentNoticeController
--}}
@extends('client::master')

@section('title')
    إشعارات السداد من بوابة العملاء
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'إشعارات السداد من بوابة العملاء',
            'icon' => 'inbox',
            'sub' => 'ما أعلنه العملاء من سدادٍ بانتظار التحقّق وتسجيل سند القبض',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'إشعارات السداد' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.payment-notices.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كل صفٍّ هنا إعلانُ عميلٍ من بوابته أنه سدّد فاتورةً. لا يتغيّر رصيدُه ولا الخزينة حتى تتحقّق من الإيصال وتسجّل سند القبض.',
            'blocks' => [
                'ماذا تفعل؟' => [
                    'طابق المبلغ والمرجع مع الإيصال المرفق وحسابك البنكي.',
                    '«تسجيل سند القبض» يفتح شاشة السند معبّأةً — وبحفظه يُعتمد الإشعار ويُبلَّغ العميل.',
                    '«رفض» يُبلغ العميل بالسبب ولا يمسّ الفاتورة.',
                ],
                'لماذا لا يُسجَّل السند آلياً؟' => [
                    'لأن المال لا يدخل النظام بقول العميل: التحقّق من وصول المبلغ قرارُ موظّف.',
                    'والسند يُسجَّل بمساره المعتاد وحدَه — خزينةً وقيداً وترقيماً وفترةً محاسبية.',
                ],
            ],
        ])

        <div class="card cl-filters">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>الحالة</label>
                        <select name="status" class="form-select">
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>بانتظار المراجعة</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>معتمدة</option>
                            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>مرفوضة</option>
                            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>مسحوبة من العميل</option>
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>-- الكل --</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> عرض</button>
                        <a href="{{ route('clients.payment_notices.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-inbox"></i>
                    الإشعارات
                    <span class="cl-count">{{ $notices->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>الفاتورة</th>
                                <th>المبلغ</th>
                                <th>الطريقة</th>
                                <th>تاريخ السداد</th>
                                <th>المرجع</th>
                                <th>الإيصال</th>
                                <th>الحالة</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notices as $notice)
                                <tr>
                                    <td data-num>#{{ $notice->id }}</td>
                                    <td>{{ $notice->client->trade_name ?? '#'.$notice->client_id }}</td>
                                    <td data-num>
                                        @if ($notice->invoice)
                                            <a href="{{ route('invoices.show', $notice->invoice_id) }}">
                                                {{ $notice->invoice->code ?? $notice->invoice_id }}
                                            </a>
                                            <br><small class="text-muted">
                                                المتبقّي: {{ number_format((float) $notice->invoice->due_value, 2) }}
                                            </small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-num>{{ number_format((float) $notice->amount, 2) }}</td>
                                    <td>{{ $notice->methodLabel() }}</td>
                                    <td data-num>{{ $notice->paid_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $notice->reference ?: '—' }}</td>
                                    <td>
                                        @if ($notice->attachment)
                                            <a href="{{ asset($notice->attachment) }}" target="_blank" rel="noopener">
                                                <i class="feather icon-paperclip"></i> عرض
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $notice->statusColor() }}">{{ $notice->statusLabel() }}</span>
                                        @if ($notice->review_note)
                                            <br><small class="text-muted">{{ $notice->review_note }}</small>
                                        @endif
                                        @if ($notice->reviewer)
                                            <br><small class="text-muted">{{ $notice->reviewer->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($notice->isPending())
                                            @permission('sales.payments.create')
                                                <form method="POST"
                                                      action="{{ route('clients.payment_notices.approve', $notice) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="feather icon-check"></i> تسجيل سند القبض
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#rejectNotice{{ $notice->id }}">
                                                    <i class="feather icon-x"></i> رفض
                                                </button>
                                            @endpermission
                                        @elseif ($notice->payment_id)
                                            <a href="{{ route('paymentsClient.show', $notice->payment_id) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="feather icon-file-text"></i> السند
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        لا إشعارات في هذه الحالة.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $notices->links() }}
            </div>
        </div>

        {{-- النوافذ خارج الجدول عمداً: `<div>` بين صفوف `<tbody>` مكانٌ غيرُ
             صالح، فيرفعه المتصفّح خارج الجدول عند التحليل ويكسر بقيّة الصفوف. --}}
        @foreach ($notices as $notice)
        @if ($notice->isPending())
            {{-- الرفضُ بسببٍ إلزامي: العميلُ يقرأ السببَ في بوابته،
                 و«مرفوض» بلا سببٍ يُنتج اتّصالاً هاتفياً لا أكثر. --}}
            <div class="modal fade" id="rejectNotice{{ $notice->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('clients.payment_notices.reject', $notice) }}"
                          class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">رفض إشعار السداد #{{ $notice->id }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label>سبب الرفض <span class="text-danger">*</span></label>
                            <textarea name="review_note" class="form-control" rows="3" required
                                      maxlength="500" placeholder="لم نجد المبلغ في الحساب…"></textarea>
                            <small class="text-muted">يظهر للعميل في بوابته.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
        @endforeach
    </div>
@stop
