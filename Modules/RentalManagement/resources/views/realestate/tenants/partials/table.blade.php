@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;

    // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
    $money = fn ($amount, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
        ? \Modules\RentalManagement\Support\RealEstateMoney::format($amount, $currency)
        : number_format((float) $amount, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
    $canOpenFile ??= hasPermission('realestate.tenants.show') || hasPermission('realestate.relationships.view');
@endphp
@if ($tenants->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>المستأجر</th><th>الهوية والجوال</th><th>العقد الحالي</th><th>الوحدة</th><th class="text-center">العقود</th>@if ($canSeeMoney)<th class="text-end">الرصيد المستحق</th>@endif<th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($tenants as $tenant)
                    @php
                        $mine = $contracts->get($tenant->id, collect());
                        $current = $mine->first(fn ($c) => in_array($c->status, V::CONTRACT_OCCUPYING, true));
                        $outstanding = $canSeeMoney ? $mine->whereIn('status', \Modules\RentalManagement\Models\Contract::COLLECTIBLE_STATUSES)->sum(fn ($c) => $c->outstanding()) : 0;
                        $state = match (true) {
                            (bool) $current => ['label' => 'له عقد قائم', 'tone' => 'success'],
                            $mine->isEmpty() => ['label' => 'مسجَّل بلا عقد', 'tone' => 'info'],
                            $mine->contains('status', 'draft') => ['label' => 'عقد قيد الإعداد', 'tone' => 'warning'],
                            default => ['label' => 'مستأجر سابق', 'tone' => 'secondary'],
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-primary"><i class="feather icon-user-check"></i></span>
                                <div class="stk-media__body">
                                    @if ($canOpenFile)
                                        <a href="{{ route('realestate.relationships.client', $tenant->id) }}" class="stk-media__title">{{ $tenant->display_name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $tenant->display_name }}</span>
                                    @endif
                                    <span class="stk-media__sub stk-num">{{ $tenant->code ?? '—' }}
                                        @if (($profiles[$tenant->id] ?? null)?->isCompany()) <span class="badge badge-light-primary">شركة</span> @endif
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php($profile = $profiles[$tenant->id] ?? null)
                            <span class="d-block stk-num" dir="ltr">{{ $tenant->mobile ?: ($tenant->phone ?: '—') }}</span>
                            @if ($profile?->isCompany())
                                <small class="text-muted">سجلّ تجاري: <span class="stk-num" dir="ltr">{{ $tenant->commercial_registration ?: '—' }}</span></small>
                            @elseif ($profile?->id_number)
                                <small class="text-muted">{{ $profile->id_type_label }}: <span class="stk-num" dir="ltr">{{ $profile->id_number }}</span></small>
                                @if ($profile->idExpired()) <span class="badge badge-light-danger">منتهية</span> @endif
                            @else
                                <small class="text-warning">لم تُسجَّل الهوية</small>
                            @endif
                        </td>
                        <td>
                            @if ($current)
                                @if (hasPermission('realestate.contracts.show'))
                                    <a href="{{ route('realestate.contracts.show', $current->id) }}" class="stk-num">{{ $current->code }}</a>
                                @else
                                    <span class="stk-num">{{ $current->code }}</span>
                                @endif
                                <small class="text-muted d-block stk-num">{{ $current->start_date?->format('Y-m-d') }} ← {{ $current->end_date?->format('Y-m-d') }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($current?->unit)
                                {{ $current->unit->full_label }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($mine->isNotEmpty() && hasPermission('realestate.contracts.view'))
                                <a href="{{ route('realestate.contracts.index', ['tenant_client_id' => $tenant->id]) }}" class="stk-count" title="عرض عقود المستأجر">{{ $mine->count() }}</a>
                            @else
                                <span class="stk-count">{{ $mine->count() }}</span>
                            @endif
                        </td>
                        @if ($canSeeMoney)
                            <td class="text-end stk-num {{ $outstanding > 0 ? 'text-danger' : '' }}">{{ $money($outstanding, $mine->first()?->currency) }}</td>
                        @endif
                        <td class="text-center"><span class="badge badge-light-{{ $state['tone'] }}">{{ $state['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $tenant->display_name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if ($canOpenFile)
                                        <a class="dropdown-item" href="{{ route('realestate.relationships.client', $tenant->id) }}"><i class="feather icon-user"></i> ملف المستأجر</a>
                                    @endif
                                    @permission('realestate.tenants.edit')
                                        <a class="dropdown-item" href="{{ route('realestate.tenants.edit', $tenant->id) }}"><i class="feather icon-edit-2"></i> البيانات الشخصية والهوية</a>
                                    @endpermission
                                    @permission('realestate.contracts.create')
                                        <a class="dropdown-item" href="{{ route('realestate.contracts.create', ['tenant_client_id' => $tenant->id]) }}"><i class="feather icon-file-plus"></i> عقد جديد لهذا المستأجر</a>
                                    @endpermission
                                    @permission('realestate.contracts.view')
                                        <a class="dropdown-item" href="{{ route('realestate.contracts.index', ['tenant_client_id' => $tenant->id]) }}"><i class="feather icon-file-text"></i> عقوده</a>
                                    @endpermission
                                    @if ($current && hasPermission('realestate.payments.create'))
                                        <a class="dropdown-item" href="{{ route('realestate.payments.create', ['contract_id' => $current->id]) }}"><i class="feather icon-credit-card"></i> تسجيل دفعة</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $tenants, 'unit' => 'مستأجر'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'user-check', 'title' => 'لا مستأجرين', 'hint' => 'سجّل عميلاً من الملفّ المركزي مستأجراً، أو عدّل معايير البحث'])
@endif
