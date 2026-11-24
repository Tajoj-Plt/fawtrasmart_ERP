@extends('rentalmanagement::realestate.master')

@section('title', 'الطلب '.$listing->code)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;

        // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
        $statusUrl = route('realestate.listings.status', $listing->id);
        $client = $listing->client;
        $purpose = $listing->listing_type === 'sale' ? 'شراء' : 'إيجار';
        $budget = match (true) {
            (float) $listing->budget_min > 0 && (float) $listing->budget_max > 0 => number_format((float) $listing->budget_min).' – '.$money($listing->budget_max),
            (float) $listing->budget_max > 0 => 'حتى '.$money($listing->budget_max),
            (float) $listing->budget_min > 0 => 'من '.$money($listing->budget_min),
            default => 'غير محدَّدة',
        };
        $canOpenClient = $client && (hasPermission('realestate.relationships.view') || hasPermission('realestate.tenants.show'));
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.listings.publish')
                @foreach ([['active', 'play-circle', 'نشط', 'success'], ['paused', 'phone-call', 'قيد المتابعة', 'warning'], ['fulfilled', 'check-circle', 'تمت تلبيته', 'success'], ['closed', 'x-circle', 'إغلاق الطلب', 'secondary']] as [$st, $ic, $lbl, $tn])
                    @continue($listing->status === $st || ($st === 'fulfilled' && $listing->status === $listing->fulfilledStatus()))
                    <button type="button" class="btn btn-outline-{{ $tn }} re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1"
                            data-payload="{{ json_encode(['status' => $st]) }}" data-tone="{{ $tn === 'success' ? 'primary' : 'warning' }}" data-confirm="{{ $lbl }} — الطلب {{ $listing->code }}؟"><i class="feather icon-{{ $ic }}"></i> {{ $lbl }}</button>
                @endforeach
            @endpermission
            @permission('realestate.listings.edit')
                <a href="{{ route('realestate.listings.edit', $listing->id) }}" class="btn btn-primary"><i class="feather icon-edit"></i> تعديل</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $listing->title,
            'icon' => 'search',
            'sub' => $listing->code.' · طلب عميل · '.$listing->requester_name,
            'crumbs' => ['الرئيسية' => url('/'), 'طلبات العملاء' => route('realestate.listings.requests'), $listing->code => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $listing->status_meta['tone'] }}">{{ $listing->status_meta['label'] }}</span></span><span class="stk-metric__label">حالة الطلب</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                <span class="stk-metric__value">{{ $listing->property_type ? V::label(V::UNIT_TYPES, $listing->property_type) : 'أيّ عقار' }} <span class="badge badge-light-info">{{ $purpose }}</span></span><span class="stk-metric__label">يبحث عن</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value re-num">{{ $budget }}</span><span class="stk-metric__label">الميزانية</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__value stk-num">{{ $matches->count() }}</span><span class="stk-metric__label">عروض مطابقة</span></div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-layers"></i> عروض مطابقة</h4>
                        <small class="text-muted">عروض نشطة للغرض نفسه تطابق النوع والمدينة والميزانية وعدد الغرف المحدَّدة في الطلب</small>
                    </div>
                    <div class="card-body p-0">
                        @if ($matches->isEmpty())
                            <div class="p-2">
                                @include('rentalmanagement::realestate.components.empty', ['icon' => 'layers', 'title' => 'لا عروض مطابقة الآن', 'hint' => 'وسّع الميزانية أو المدينة في الطلب، أو أضف عرضاً يناسبه'])
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العرض</th><th>الوحدة / العقار</th><th>المدينة</th><th class="text-center">الغرف</th><th class="text-end">السعر المطلوب</th><th>المسوّق</th></tr></thead>
                                    <tbody>
                                        @foreach ($matches as $offer)
                                            @php $place = $offer->unit?->building ?? $offer->building; @endphp
                                            <tr>
                                                <td>
                                                    @if (hasPermission('realestate.listings.show'))
                                                        <a href="{{ route('realestate.listings.show', $offer->id) }}" class="font-weight-bold">{{ $offer->title }}</a>
                                                    @else
                                                        <span class="font-weight-bold">{{ $offer->title }}</span>
                                                    @endif
                                                    <small class="text-muted d-block stk-num">{{ $offer->code }}</small>
                                                </td>
                                                <td>
                                                    @if ($offer->unit) {{ $offer->unit->type_label }} {{ $offer->unit->unit_no }} <small class="text-muted d-block">{{ $place->name ?? '—' }}</small>
                                                    @else {{ $place->name ?? ($offer->property_type ? V::label(V::UNIT_TYPES, $offer->property_type) : 'خارجي') }}
                                                    @endif
                                                </td>
                                                <td>{{ $offer->city ?: ($place->city ?? '—') }}</td>
                                                <td class="text-center stk-num">{{ $offer->bedrooms ?: ($offer->unit->bedrooms ?? '—') }}</td>
                                                <td class="text-end re-num">{{ $money($offer->asking_price) }}@if ($offer->listing_type === 'rent' && $offer->rent_period) <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $offer->rent_period) }}</small>@endif</td>
                                                <td>{{ $offer->agent->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($listing->description)
                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-align-right"></i> تفاصيل الطلب</h4></div>
                        <div class="card-body"><p class="mb-0" style="white-space:pre-line">{{ $listing->description }}</p></div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-user"></i> العميل الطالب</h4></div>
                    <div class="card-body">
                        <dl class="stk-def mb-0">
                            <div class="stk-def__row"><dt>الاسم</dt><dd>
                                @if ($canOpenClient)
                                    <a href="{{ route('realestate.relationships.client', $client->id) }}">{{ $listing->requester_name }}</a>
                                @else
                                    {{ $listing->requester_name }}
                                @endif
                                @unless ($client) <span class="badge badge-light-secondary">زائر غير مسجَّل</span> @endunless
                            </dd></div>
                            <div class="stk-def__row"><dt>الهاتف</dt><dd class="stk-num" dir="ltr">{{ $client->mobile ?? ($client->phone ?? ($listing->contact_phone ?: '—')) }}</dd></div>
                            <div class="stk-def__row"><dt>المتابِع</dt><dd>{{ $listing->agent->name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>تاريخ الطلب</dt><dd class="stk-num">{{ $listing->created_at?->format('Y-m-d') }}</dd></div>
                            <div class="stk-def__row"><dt>قائم حتى</dt><dd class="stk-num">{{ $listing->expiry_date?->format('Y-m-d') ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-list"></i> المواصفات المطلوبة</h4></div>
                    <div class="card-body">
                        <dl class="stk-def mb-0">
                            <div class="stk-def__row"><dt>الغرض</dt><dd>{{ $purpose }}</dd></div>
                            <div class="stk-def__row"><dt>نوع العقار المطلوب</dt><dd>{{ $listing->property_type ? V::label(V::UNIT_TYPES, $listing->property_type) : 'أيّ نوع' }}</dd></div>
                            <div class="stk-def__row"><dt>المدينة / الحي</dt><dd>{{ collect([$listing->city, $listing->district])->filter()->implode('، ') ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>غرف النوم</dt><dd class="stk-num">{{ $listing->bedrooms ? $listing->bedrooms.' فأكثر' : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>المساحة</dt><dd class="stk-num">{{ $listing->area_sqm ? number_format((float) $listing->area_sqm, 2).' م²' : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الميزانية</dt><dd class="re-num">{{ $budget }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
@endsection
