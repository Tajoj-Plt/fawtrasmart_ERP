{{--
    طلبات الزيارة الميدانية الواردة من بوابة العملاء.

    الطلبُ رغبةُ عميلٍ لا صفٌّ في خطّ سير: لا يظهر في يوم مندوبٍ ولا يحجز
    وقتَه. و«اعتماد» يختمه ويحوّلك إلى «خط سيري اليومي» على اليوم المطلوب
    لتضيف العميلَ هناك بمساره الكامل — نظيرُ «تسجيل سند القبض» في إشعارات
    السداد. لا منطقَ جدولةٍ في هذه الشاشة.

    @see \Modules\Client\Http\Controllers\ClientVisitRequestController
--}}
@extends('client::master')

@section('title')
    طلبات الزيارة من بوابة العملاء
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'طلبات الزيارة الميدانية',
            'icon' => 'map-pin',
            'sub' => 'ما طلبه العملاء من زياراتٍ في بوابتهم — بانتظار قرارك',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'طلبات الزيارة' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.visit-requests.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كل صفٍّ هنا طلبُ عميلٍ من بوابته أن يزوره مندوب. لا يدخل خطَّ سير أحدٍ ولا يحجز وقتاً حتى تعتمده وتضيف العميل بنفسك.',
            'blocks' => [
                'ماذا تفعل؟' => [
                    '«اعتماد» يختم الطلب ويفتح «خط سيري اليومي» على اليوم المطلوب — أضف العميل هناك من «إضافة عميل للمسار».',
                    '«رفض» يُبلغ العميل بالسبب في بوابته.',
                    'اليومُ المفضَّل اقتراحُ العميل لا موعدٌ ملزم — رتّب الزيارة بما يناسب خطّ المندوب.',
                ],
                'لماذا لا تُنشأ الزيارة آلياً؟' => [
                    'لأن حمولة يوم المندوب ومساره الجغرافي ونطاقه لا يعرفها العميل.',
                    'والإضافة تتمّ بمسارها المعتاد وحده — نطاق «تابع لـ»، وحدّ المُنتقي، وتسجيل من أضاف.',
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
                        <a href="{{ route('clients.visit_requests.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-map-pin"></i>
                    الطلبات
                    <span class="cl-count">{{ $requests->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>السبب</th>
                                <th>اليوم المفضَّل</th>
                                <th>الفترة</th>
                                <th>أُرسل في</th>
                                <th>الحالة</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $item)
                                <tr>
                                    <td data-num>#{{ $item->id }}</td>
                                    <td>
                                        @permission('clients_view_all_clients')
                                            <a href="{{ route('clients.show', $item->client_id) }}">
                                                {{ $item->client->trade_name ?? '#'.$item->client_id }}
                                            </a>
                                        @else
                                            {{ $item->client->trade_name ?? '#'.$item->client_id }}
                                        @endpermission
                                        @if ($item->client?->phone)
                                            <br><small class="text-muted" dir="ltr">{{ $item->client->phone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $item->reason }}
                                        @if ($item->note)
                                            <br><small class="text-muted">{{ $item->note }}</small>
                                        @endif
                                    </td>
                                    <td data-num>{{ $item->preferred_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $item->timeWindowLabel() }}</td>
                                    <td data-num>{{ $item->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->statusColor() }}">{{ $item->statusLabel() }}</span>
                                        @if ($item->review_note)
                                            <br><small class="text-muted">{{ $item->review_note }}</small>
                                        @endif
                                        @if ($item->reviewer)
                                            <br><small class="text-muted">{{ $item->reviewer->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($item->isPending())
                                            @permission('clients_daily_itinerary_add_for_others|clients_daily_itinerary_add_client')
                                                <form method="POST"
                                                      action="{{ route('clients.visit_requests.approve', $item) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="feather icon-check"></i> اعتماد وإضافة للمسار
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#rejectVisitReq{{ $item->id }}">
                                                    <i class="feather icon-x"></i> رفض
                                                </button>
                                            @endpermission
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        لا طلبات في هذه الحالة.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $requests->links() }}
            </div>
        </div>

        {{-- النوافذ خارج الجدول عمداً: `<div>` بين صفوف `<tbody>` مكانٌ غيرُ
             صالح، فيرفعه المتصفّح خارج الجدول عند التحليل ويكسر بقيّة الصفوف. --}}
        @foreach ($requests as $item)
        @if ($item->isPending())
            {{-- الرفضُ بسببٍ إلزامي: العميلُ يقرأ السببَ في بوابته،
                 و«مرفوض» بلا سببٍ يُنتج اتّصالاً هاتفياً لا أكثر. --}}
            <div class="modal fade" id="rejectVisitReq{{ $item->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('clients.visit_requests.reject', $item) }}"
                          class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">رفض طلب الزيارة #{{ $item->id }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label>سبب الرفض <span class="text-danger">*</span></label>
                            <textarea name="review_note" class="form-control" rows="3" required
                                      maxlength="500" placeholder="لا يغطّي خطُّ سيرنا منطقتك هذا الأسبوع…"></textarea>
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
