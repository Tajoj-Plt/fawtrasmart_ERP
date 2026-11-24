@extends('client::reservations.master')

@section('title', 'حجوزات العميل')

@section('content')
    @section('page-actions')
        @if (hasPermission('reservations.create'))
            <a href="{{ route('Reservations.create') }}?client_id={{ $client->id }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> حجز جديد لهذا العميل
            </a>
        @endif
        <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> كل الحجوزات
        </a>
    @endsection

    @include('client::reservations.components.page-head', [
        'title' => 'حجوزات العميل: ' . $client->display_name,
        'icon' => 'user',
        'sub' => trim(($client->code ? 'رقم العميل: ' . $client->code : '')
            . ($client->phone ? ' — ' . $client->phone : '')),
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'الحجوزات' => route('Reservations.index'),
            $client->display_name => null,
        ],
    ])
        <div class="d-flex gap-2">
            @if (hasPermission('reservations.create'))
                <a href="{{ route('Reservations.create') }}?client_id={{ $client->id }}" class="btn btn-primary btn-sm">
                    <i class="feather icon-plus"></i> حجز جديد لهذا العميل
                </a>
            @endif
            <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-arrow-right"></i> كل الحجوزات
            </a>
        </div>
    </div>

    @include('client::reservations.partials.alerts')

    <div class="rsv-card">
        <div class="rsv-card__head">
            <h6 class="rsv-card__title"><i class="feather icon-list"></i> سجلّ الحجوزات</h6>
            <span class="rsv-muted">{{ $reservations->total() }} حجزاً</span>
        </div>
        <div class="rsv-scroll">
            <table class="table rsv-table">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>الخدمة</th>
                        <th>الموظّف</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجمالي</th>
                        <th class="text-end">المتبقّي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td><a href="{{ route('Reservations.show', $reservation->id) }}">{{ $reservation->code }}</a></td>
                            <td>{{ $reservation->service_name }}</td>
                            <td>{{ $reservation->employee ? $reservation->employee->full_name : '—' }}</td>
                            <td>{{ $reservation->reservation_date?->format('Y-m-d') }}</td>
                            <td class="rsv-muted">{{ substr((string) $reservation->start_time, 0, 5) }}</td>
                            <td>
                                <span class="rsv-badge" style="background: {{ $reservation->status->color() }}">
                                    {{ $reservation->status->label() }}
                                </span>
                            </td>
                            <td class="text-end rsv-money">{{ number_format((float) $reservation->total, 2) }}</td>
                            <td class="text-end rsv-money">{{ number_format((float) $reservation->remaining_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="rsv-empty">
                                    <div class="rsv-empty__icon"><i class="feather icon-calendar"></i></div>
                                    <p class="rsv-empty__title">لا توجد حجوزات لهذا العميل</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reservations->hasPages())
            <div class="rsv-card__body">{{ $reservations->links() }}</div>
        @endif
    </div>
@endsection
