{{--
    قائمة طلبات تغيير حالة العميل عبر كل العملاء — كان بند «طلبات تغيير حالة
    معلَّقة» في مركز تنبيهات لوحة العملاء رقماً بلا وجهة؛ هذا اعتمادها
    ورفضها بنفس رَوطي `clients.status-requests.approve`/`.reject`
    المستعملين أصلاً في تبويب سجلّ نشاط العميل — لا منطق موازٍ.
--}}
@extends('client::master')

@section('title')
    طلبات تغيير حالة العملاء
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @php
            $statusBadge = [
                'pending' => ['bg' => 'badge-warning', 'label' => 'بانتظار الاعتماد'],
                'approved' => ['bg' => 'badge-success', 'label' => 'معتمد'],
                'rejected' => ['bg' => 'badge-danger', 'label' => 'مرفوض'],
            ];
        @endphp

        @include('client::components.page-head', [
            'title' => 'طلبات تغيير حالة العملاء',
            'icon' => 'user-check',
            'sub' => 'اعتماد أو رفض طلبات تغيير حالة العملاء المقدَّمة من الموظفين',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'طلبات تغيير الحالة' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.status-requests.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كل صفٍّ هنا طلبُ موظفٍ لتغيير حالة عميل (مثل إيقافه)، بانتظار اعتماد أو رفض من صاحب الصلاحية. الاعتماد ينفّذ التغيير على العميل فوراً.',
            'blocks' => [
                'الحالات' => [
                    'بانتظار الاعتماد — لم يُبتّ في الطلب بعد.',
                    'معتمد — نُفِّذ تغيير الحالة على العميل.',
                    'مرفوض — بقي العميل على حالته الحالية.',
                ],
            ],
        ])

        {{-- ══════════ إحصاءات الحالات ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-clock"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['pending'] }}</p>
                    <p class="cl-stat__label">بانتظار الاعتماد</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-check-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['approved'] }}</p>
                    <p class="cl-stat__label">معتمدة</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="feather icon-x-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['rejected'] }}</p>
                    <p class="cl-stat__label">مرفوضة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ الفلاتر ══════════ --}}
        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <h4 class="cl-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h4>
                </div>

                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>البحث عن عميل</label>
                        <input type="text" name="search" class="form-control" placeholder="اسم العميل أو كوده..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <label>الحالة</label>
                        <select name="status" class="form-select select2">
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>بانتظار الاعتماد</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>معتمدة</option>
                            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>مرفوضة</option>
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>-- الكل --</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <a href="{{ route('clients.status-requests.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ جدول الطلبات ══════════ --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-list"></i>
                    طلبات تغيير الحالة
                    <span class="cl-count">{{ $statusRequests->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>مقدَّم بواسطة</th>
                                <th>الحالة القديمة</th>
                                <th>الحالة الجديدة</th>
                                <th>ملاحظات</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($statusRequests as $request)
                                <tr>
                                    <td data-num>#{{ $request->id }}</td>
                                    <td>
                                        @if ($request->client)
                                            <a href="{{ route('clients.show', $request->client->id) }}" class="cl-media__title text-decoration-none">
                                                {{ $request->client->trade_name }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $request->requester->name ?? 'غير معروف' }}</td>
                                    <td>
                                        @if ($request->oldStatus)
                                            <span class="badge" style="background-color: {{ $request->oldStatus->color ?? '#6c757d' }}">{{ $request->oldStatus->name }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($request->newStatus)
                                            <span class="badge" style="background-color: {{ $request->newStatus->color ?? '#28a745' }}">{{ $request->newStatus->name }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $request->notes ?? '—' }}</td>
                                    <td>{{ $request->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $statusBadge[$request->status]['bg'] ?? 'badge-secondary' }}">
                                            {{ $statusBadge[$request->status]['label'] ?? $request->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($request->status === 'pending')
                                            <div class="cl-actions d-flex gap-2">
                                                <form action="{{ route('clients.status-requests.approve', $request->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('هل أنت متأكد من اعتماد هذا الطلب؟')">
                                                        <i class="fa fa-check"></i> اعتماد
                                                    </button>
                                                </form>
                                                <form action="{{ route('clients.status-requests.reject', $request->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                                        <i class="fa fa-times"></i> رفض
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="cl-empty">
                                            <span class="cl-empty__icon"><i class="feather icon-inbox"></i></span>
                                            <p class="cl-empty__title">لا توجد طلبات ضمن هذا الفلتر</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($statusRequests->hasPages())
                <div class="cl-table-foot">
                    <p class="cl-table-foot__count">
                        عرض {{ $statusRequests->currentPage() * $statusRequests->perPage() - $statusRequests->perPage() + 1 }}
                        إلى {{ min($statusRequests->currentPage() * $statusRequests->perPage(), $statusRequests->total()) }}
                        من {{ $statusRequests->total() }} سجل
                    </p>
                    {{ $statusRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
