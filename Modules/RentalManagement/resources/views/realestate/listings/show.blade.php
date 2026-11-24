@extends('rentalmanagement::realestate.master')

@section('title', $listing->title)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
        $unit = $listing->unit;
        $place = $unit?->building ?? $listing->building;
        $features = is_array($listing->features) ? $listing->features : [];
        $statusUrl = route('realestate.listings.status', $listing->id);
        $isRequest = $listing->listing_kind === 'request';
        $kindWord = $isRequest ? 'الطلب' : 'العرض';
        $canContract = ! $isRequest && $listing->status === 'active' && in_array($listing->listing_type, ['rent', 'sale'], true);
        $external = ! $unit && ! $place;
        $viewingErrors = $errors->hasAny(['kind', 'client_id', 'visitor_name', 'visitor_phone', 'agent_id', 'scheduled_at']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.listings.publish')
                @foreach ([['active', 'upload-cloud', 'تفعيل', 'success', false], ['paused', 'pause-circle', 'إيقاف', 'warning', true], ['rented', 'key', 'تم الإيجار', 'success', true], ['purchased', 'shopping-bag', 'تم الشراء', 'success', true], ['closed', 'x-circle', 'إغلاق', 'secondary', true]] as [$st, $ic, $lbl, $tn, $outline])
                    @continue($listing->status === $st || ($st === 'paused' && $listing->status !== 'active') || ! isset($listing->allowedStatuses()[$st]))
                    <button type="button" class="btn btn-{{ $outline ? 'outline-' : '' }}{{ $tn }} re-action" data-url="{{ $statusUrl }}" data-method="POST" data-reload="1"
                            data-payload="{{ json_encode(['status' => $st]) }}" data-tone="{{ $tn === 'success' ? 'info' : 'warning' }}" data-confirm="{{ $lbl }} — {{ $kindWord }}؟"><i class="feather icon-{{ $ic }}"></i> {{ $lbl }}</button>
                @endforeach
            @endpermission
            @permission('realestate.contracts.create')
                @if ($canContract)
                    <a href="{{ route('realestate.contracts.create', ['listing_id' => $listing->id]) }}" class="btn btn-outline-primary"><i class="feather icon-file-plus"></i> إنشاء عقد من هذا العرض</a>
                @endif
            @endpermission
            @permission('realestate.listings.edit')
                <a href="{{ route('realestate.listings.edit', $listing->id) }}" class="btn btn-primary"><i class="feather icon-edit"></i> تعديل</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $listing->title,
            'icon' => 'tag',
            'sub' => $listing->code.' · '.$listing->kind_meta['label'].' · '.($unit ? $unit->full_label : ($place->name ?? 'خارجي')),
            'crumbs' => ['الرئيسية' => url('/'), 'العروض العقارية' => route('realestate.listings.index'), $listing->code => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $listing->status_meta['tone'] }}">{{ $listing->status_meta['label'] }}</span></span><span class="stk-metric__label">الحالة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $listing->type_meta['tone'] }}">{{ $listing->type_meta['label'] }}</span></span><span class="stk-metric__label">{{ $kindWord }} · {{ $listing->kind_meta['label'] }}</span></div>
            @if ($isRequest && ($listing->budget_min || $listing->budget_max))
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value re-num">{{ number_format((float) $listing->budget_min) }} – {{ $money($listing->budget_max) }}</span><span class="stk-metric__label">الميزانية</span></div>
            @else
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value re-num">{{ $money($listing->asking_price) }}@if ($listing->listing_type === 'rent' && $listing->rent_period) <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $listing->rent_period) }}</small>@endif</span>
                <span class="stk-metric__label">{{ $isRequest ? 'السعر المستهدف' : 'السعر المطلوب' }}</span></div>
            @endif
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-eye"></i></span><span class="stk-metric__value stk-num">{{ (int) $listing->views_count }}</span><span class="stk-metric__label">المشاهدات</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-message-circle"></i></span><span class="stk-metric__value stk-num">{{ (int) $listing->inquiries_count }}</span><span class="stk-metric__label">الاستفسارات</span></div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-image"></i> الصور والوصف</h4></div>
                    <div class="card-body">
                        @include('rentalmanagement::realestate.components.file-list', ['paths' => $listing->filePaths('images'), 'images' => true])

                        @if ($listing->description)
                            <p class="mt-1 mb-0" style="white-space:pre-line">{{ $listing->description }}</p>
                        @endif

                        @if ($features)
                            <h6 class="mt-2 mb-75">المميزات</h6>
                            <div class="stk-tags">
                                @foreach ($features as $feature) <span class="badge badge-light-primary">{{ $feature }}</span> @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar"></i> الاستفسارات والمعاينات <span class="stk-count">{{ $viewings->count() }}</span></h4>
                        @permission('realestate.listings.viewings')
                            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-bs-toggle="modal" data-target="#viewingModal" data-bs-target="#viewingModal">
                                <i class="feather icon-plus"></i> تسجيل استفسار / معاينة
                            </button>
                        @endpermission
                    </div>
                    @if ($viewings->isEmpty())
                        @include('rentalmanagement::realestate.components.empty', ['icon' => 'calendar', 'title' => 'لا استفسارات ولا معاينات', 'hint' => 'سجّل أول استفسار أو موعد معاينة على هذا العرض'])
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>العميل / الزائر</th><th class="text-center">النوع</th><th>الموعد</th><th>المسوّق</th><th class="text-center">الحالة</th>
                                    @permission('realestate.listings.viewings') <th>تحديث الحالة</th> @endpermission</tr></thead>
                                <tbody>
                                    @foreach ($viewings as $viewing)
                                        <tr>
                                            <td>
                                                {{ $viewing->person }}
                                                @if ($viewing->client?->phone || $viewing->visitor_phone)
                                                    <small class="text-muted d-block stk-num" dir="ltr">{{ $viewing->client?->phone ?: $viewing->visitor_phone }}</small>
                                                @endif
                                                @if ($viewing->notes) <small class="text-muted d-block">{{ $viewing->notes }}</small> @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-light-{{ $viewing->kind === 'viewing' ? 'primary' : 'info' }}">{{ $viewing->kind === 'viewing' ? V::label(V::ACTIVITY_TYPES, 'viewing') : 'استفسار' }}</span>
                                            </td>
                                            <td class="stk-num">{{ $viewing->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                            <td>{{ $viewing->agent->name ?? '—' }}</td>
                                            <td class="text-center"><span class="badge badge-light-{{ $viewing->status_meta['tone'] }}">{{ $viewing->status_meta['label'] }}</span></td>
                                            @permission('realestate.listings.viewings')
                                                <td>
                                                    {{-- `updateViewing` يردّ `back()` لا JSON — نموذجٌ عاديّ --}}
                                                    <form method="POST" action="{{ route('realestate.listings.viewings.update', [$listing->id, $viewing->id]) }}" class="d-flex m-0" style="gap:.35rem;min-width:190px">
                                                        @csrf @method('PUT')
                                                        <select name="status" class="form-control form-control-sm" aria-label="حالة المعاينة">
                                                            @foreach (V::VIEWING_STATUSES as $value => $meta) <option value="{{ $value }}" @selected($viewing->status === $value)>{{ $meta['label'] }}</option> @endforeach
                                                        </select>
                                                        <input type="hidden" name="notes" value="{{ $viewing->notes }}">
                                                        <button class="btn btn-sm btn-outline-primary" aria-label="حفظ الحالة"><i class="feather icon-check"></i></button>
                                                    </form>
                                                </td>
                                            @endpermission
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> العقود الناتجة <span class="stk-count">{{ $listing->contracts->count() }}</span></h4></div>
                    @if ($listing->contracts->isEmpty())
                        <div class="card-body"><p class="text-muted mb-0">لم ينتج عن {{ $isRequest ? 'هذا الطلب' : 'هذا العرض' }} عقدٌ بعد.</p></div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>العقد</th><th>المستأجر / المشتري</th><th>الفترة</th><th class="text-end">القيمة السنوية</th><th class="text-center">الحالة</th></tr></thead>
                                <tbody>
                                    @foreach ($listing->contracts as $contract)
                                        <tr>
                                            <td>
                                                @if (hasPermission('realestate.contracts.show'))
                                                    <a href="{{ route('realestate.contracts.show', $contract->id) }}">{{ $contract->code }}</a>
                                                @else
                                                    {{ $contract->code }}
                                                @endif
                                            </td>
                                            <td>{{ $contract->tenant->display_name ?? '—' }}</td>
                                            <td class="stk-num">{{ $contract->start_date?->format('Y-m-d') ?? '—' }} ← {{ $contract->end_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="text-end re-num">{{ $money($contract->annual_value, $contract->currency) }}</td>
                                            <td class="text-center"><span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> بيانات {{ $kindWord }}</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>العقار</dt><dd>
                                @if ($place && hasPermission('realestate.buildings.show'))
                                    <a href="{{ route('realestate.buildings.show', $place->id) }}">{{ $place->name }}</a>
                                @else
                                    {{ $place->name ?? '—' }}
                                @endif
                            </dd></div>
                            @if ($listing->contact_name || $listing->contact_phone)
                                <div class="stk-def__row"><dt>{{ $isRequest ? 'مقدّم الطلب' : 'صاحب العرض' }}</dt><dd>{{ $listing->contact_name ?: '—' }}@if ($listing->contact_phone) <small class="text-muted d-block stk-num" dir="ltr">{{ $listing->contact_phone }}</small>@endif</dd></div>
                            @endif
                            @if ($listing->client)
                                <div class="stk-def__row"><dt>العميل</dt><dd>{{ $listing->client->display_name ?? $listing->client->trade_name ?? '#'.$listing->client_id }}</dd></div>
                            @endif
                            <div class="stk-def__row"><dt>المسوّق المسؤول</dt><dd>{{ $listing->agent->name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>تاريخ النشر</dt><dd class="stk-num">{{ $listing->publish_date?->format('Y-m-d') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>انتهاء الصلاحية</dt><dd class="stk-num">{{ $listing->expiry_date?->format('Y-m-d') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>العمولة</dt><dd class="re-num">
                                @if ($listing->commission_type && $listing->commission_value !== null)
                                    @if ($listing->commission_type === 'percent')
                                        {{ rtrim(rtrim(number_format((float) $listing->commission_value, 2), '0'), '.') }}%
                                    @else
                                        {{ $money($listing->commission_value) }} <small class="text-muted">{{ V::label(V::COMMISSION_TYPES, $listing->commission_type) }}</small>
                                    @endif
                                @else
                                    —
                                @endif
                            </dd></div>
                        </dl>
                    </div>
                </div>

                @if ($listing->property_type || $listing->city || $listing->district || $listing->address || $listing->area_sqm || $listing->bedrooms !== null || $listing->bathrooms !== null)
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> {{ $isRequest ? 'مواصفات المطلوب' : 'تفاصيل العقار الخارجية' }}</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>نوع العقار</dt><dd>{{ $listing->property_type ? V::label(V::UNIT_TYPES, $listing->property_type) : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>المدينة</dt><dd>{{ $listing->city ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الحي</dt><dd>{{ $listing->district ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>العنوان</dt><dd>{{ $listing->address ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>المساحة</dt><dd class="stk-num">{{ $listing->area_sqm ? number_format((float) $listing->area_sqm, 2).' م²' : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>غرف النوم</dt><dd class="stk-num">{{ $listing->bedrooms ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>دورات المياه</dt><dd class="stk-num">{{ $listing->bathrooms ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
                @endif

                @if (! $external || $unit)
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-grid"></i> مواصفات الوحدة</h4></div>
                    <div class="card-body">
                        @if ($unit)
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>الوحدة</dt><dd>
                                    @if (hasPermission('realestate.units.show'))
                                        <a href="{{ route('realestate.units.show', $unit->id) }}">{{ $unit->type_label }} {{ $unit->unit_no }}</a>
                                    @else
                                        {{ $unit->type_label }} {{ $unit->unit_no }}
                                    @endif
                                </dd></div>
                                <div class="stk-def__row"><dt>الإشغال</dt><dd><span class="badge badge-light-{{ $unit->occupancy_meta['tone'] }}">{{ $unit->occupancy_meta['label'] }}</span></dd></div>
                                <div class="stk-def__row"><dt>المساحة</dt><dd class="stk-num">{{ $unit->area_sqm ? number_format((float) $unit->area_sqm, 2).' م²' : '—' }}</dd></div>
                                <div class="stk-def__row"><dt>غرف النوم</dt><dd class="stk-num">{{ (int) $unit->bedrooms }}</dd></div>
                                <div class="stk-def__row"><dt>دورات المياه</dt><dd class="stk-num">{{ (int) $unit->bathrooms }}</dd></div>
                                <div class="stk-def__row"><dt>المواقف</dt><dd class="stk-num">{{ (int) $unit->parking_count }}</dd></div>
                                <div class="stk-def__row"><dt>التأثيث</dt><dd>{{ V::label(V::FURNISHED, $unit->furnished_status) }}</dd></div>
                                <div class="stk-def__row"><dt>الحالة الفنية</dt><dd>{{ V::label(V::UNIT_CONDITIONS, $unit->unit_condition) }}</dd></div>
                                <div class="stk-def__row"><dt>الإيجار الإرشادي</dt><dd class="re-num">{{ $money($unit->base_rent) }} <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</small></dd></div>
                                <div class="stk-def__row"><dt>قابلة للتسويق</dt><dd><span class="badge badge-light-{{ $unit->is_marketable ? 'success' : 'danger' }}">{{ $unit->is_marketable ? 'نعم' : 'لا' }}</span></dd></div>
                            </dl>

                            @if ($unit->amenities->isNotEmpty())
                                <h6 class="mt-1 mb-75">المرافق</h6>
                                <div class="stk-tags">
                                    @foreach ($unit->amenities as $amenity)
                                        <span class="badge badge-light-secondary">@if ($amenity->icon)<i class="feather icon-{{ $amenity->icon }}"></i> @endif{{ $amenity->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <p class="text-muted mb-0">{{ $kindWord }} على العقار كاملاً — لا وحدة محدّدة.</p>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    @permission('realestate.listings.viewings')
        <div class="modal fade" id="viewingModal" tabindex="-1" aria-labelledby="viewingModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                {{-- `storeViewing` يردّ `back()` — نموذجٌ عاديّ، وأخطاء التحقّق تُعيد فتح النافذة --}}
                <form class="modal-content" id="viewingForm" method="POST" action="{{ route('realestate.listings.viewings.store', $listing->id) }}" novalidate data-re-validate>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewingModalTitle"><i class="feather icon-calendar"></i> تسجيل استفسار / معاينة</h5>
                        <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="kind" class="stk-req">النوع</label>
                                    <select id="kind" name="kind" class="form-control" data-required="1">
                                        <option value="inquiry" @selected(old('kind', 'inquiry') === 'inquiry')>استفسار</option>
                                        <option value="viewing" @selected(old('kind') === 'viewing')>{{ V::label(V::ACTIVITY_TYPES, 'viewing') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" id="scheduledField">
                                <div class="form-group">
                                    <label for="scheduled_at" id="scheduledLabel">الموعد</label>
                                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}" min="{{ now()->format('Y-m-d\TH:i') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="agent_id">المسوّق</label>
                                    <select id="agent_id" name="agent_id" class="form-control select2">
                                        <option value="">اختر مستخدماً…</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) old('agent_id', $listing->assigned_agent_id) === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="client_id">العميل (من ملفّ العملاء)</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => old('client_id'), 'placeholder' => 'اختر عميلاً مسجّلاً…'])
                                </div>
                            </div>
                            <div class="col-12"><p class="text-muted mb-75">أو زائر غير مسجَّل:</p></div>
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label for="visitor_name">اسم الزائر</label>
                                    <input type="text" id="visitor_name" name="visitor_name" class="form-control" value="{{ old('visitor_name') }}" maxlength="191">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="visitor_phone">هاتف الزائر</label>
                                    <input type="text" id="visitor_phone" name="visitor_phone" class="form-control" dir="ltr" value="{{ old('visitor_phone') }}" maxlength="40">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label for="viewing_notes">ملاحظات</label>
                                    <textarea id="viewing_notes" name="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    @endpermission
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            const $modal = $('#viewingModal');
            if (!$modal.length) return;

            function showModal() {
                reModal($modal, true);
            }

            // موعد المعاينة إلزامي؛ الاستفسار بلا موعد
            function toggleKind() {
                const viewing = $('#kind').val() === 'viewing';
                $('#scheduledLabel').toggleClass('stk-req', viewing);
                $('#scheduled_at').attr('data-required', viewing ? '1' : null);
            }

            // عميل أو اسم زائر — أحدهما يكفي؛ يسبق الفحص العام
            $('#viewingForm').on('submit', function (e) {
                if ($('#client_id').val() || $('#visitor_name').val().trim()) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire({ icon: 'warning', title: 'حقول مطلوبة', text: 'اختر عميلاً أو اكتب اسم الزائر.' });
            });

            $('#kind').on('change', toggleKind);
            toggleKind();

            if (@json($viewingErrors)) showModal();
        });
    </script>
@endsection
