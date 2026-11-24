@extends('client::reservations.master')

{{--
    خدماتُ الحجز = المنتجاتُ من نوع «منتج خدمي».

    القائمةُ هنا جدولُ المنتجات نفسُه — لا كتالوجٌ ثانٍ. وكلُّ منتجٍ خدمي
    يُحجَز فوراً بالمدّة الافتراضية؛ و«ضبط الحجز» يضيف له ما لا يعرفه جدول
    المنتجات: المدّة والتحضير والتنظيف والموظفين المؤهَّلين.
--}}

@section('title', __('reservations.services.title'))

@section('content')
    @php $money = fn ($v) => number_format((float) $v, 2); @endphp

    @section('page-actions')
        {{-- الخدمةُ منتجٌ خدمي: الزرُّ يفتح شاشةَ «منتج خدمي» في المخزون، والحفظُ يعود إلى ملفّ حجزها --}}
        @if (Route::has('products.create_services') && hasPermission('stock.products.services'))
            <a href="{{ route('products.create_services', ['return_to' => 'reservations']) }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> {{ __('reservations.services.new_product') }}
            </a>
        @endif
        <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-list"></i> {{ __('reservations.menu.reservations') }}
        </a>
    @endsection

    @include('client::reservations.components.page-head', [
        'title' => __('reservations.services.title'),
        'icon' => 'briefcase',
        'sub' => __('reservations.services.sub'),
        'crumbs' => [
            __('reservations.menu.home') => route('dashboard'),
            __('reservations.menu.reservations') => route('Reservations.index'),
            __('reservations.services.title') => null,
        ],
    ])

    @include('client::reservations.partials.alerts')
    @include('client::reservations.partials.table-menu')

    @include('client::reservations.components.guide', [
        'key' => 'reservations.services',
        'open' => false,
        'title' => __('reservations.services.guide_title'),
        'lead' => __('reservations.services.sub'),
        'blocks' => [__('reservations.services.guide_title') => __('reservations.services.guide_points')],
    ])

    @if ($legacyCount > 0)
        <div class="alert alert-warning py-2 px-3">
            <i class="feather icon-alert-triangle"></i> {{ __('reservations.services.legacy', ['n' => $legacyCount]) }}
        </div>
    @endif

    {{-- الفلترة والنتائج على قياس شاشات النظام (`sl-filters` / `sl-table-card`) --}}
    <div class="card rsv-filters rsv-no-print">
        <div class="card-body">
            <div class="rsv-filters__head">
                <h6 class="rsv-filters__title"><i class="feather icon-filter"></i> {{ __('reservations.ui.filters') }}</h6>
            </div>
            <form method="GET" action="{{ route('Reservations.services.index') }}">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="rsv-service-q">{{ __('reservations.services.search') }}</label>
                            <input type="search" id="rsv-service-q" name="q" value="{{ request('q') }}" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="rsv-filters__foot">
                    <a href="{{ route('Reservations.services.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> {{ __('reservations.actions.reset') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> {{ __('reservations.actions.search') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card rsv-table-card">
        <div class="rsv-table-head">
            <h6 class="rsv-table-head__title">
                <i class="feather icon-briefcase"></i> {{ __('reservations.services.list') }}
                <span class="rsv-count">{{ number_format($products->total()) }}</span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table rsv-table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('reservations.services.product') }}</th>
                        <th class="text-end">{{ __('reservations.columns.price') }}</th>
                        <th class="text-center">{{ __('reservations.attributes.duration') }}</th>
                        <th class="text-center">{{ __('reservations.services.buffers') }}</th>
                        <th>{{ __('reservations.attributes.employees') }}</th>
                        <th class="text-center">{{ __('reservations.menu.reservations') }}</th>
                        <th>{{ __('reservations.columns.status') }}</th>
                        <th class="text-center rsv-no-print">{{ __('reservations.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $profile = $profiles[$product->id] ?? null; @endphp
                        <tr>
                            <td><strong>{{ $product->name }}</strong></td>
                            <td class="text-end rsv-money">{{ $money($product->sale_price) }}</td>
                            <td class="text-center">
                                @if ($profile)
                                    {{ __('reservations.ui.minutes', ['n' => $profile->duration]) }}
                                @else
                                    <span class="rsv-muted">{{ __('reservations.services.default_duration', ['n' => $defaultDuration]) }}</span>
                                @endif
                            </td>
                            <td class="text-center rsv-muted">{{ $profile ? $profile->buffer_before . ' / ' . $profile->buffer_after : '0 / 0' }}</td>
                            <td>
                                {{ $profile && $profile->employees_count
                                    ? __('reservations.services.employees_n', ['n' => $profile->employees_count])
                                    : __('reservations.services.employees_all') }}
                            </td>
                            <td class="text-center">{{ number_format($bookings[$product->id] ?? 0) }}</td>
                            <td>
                                @php $bookable = ! $profile || $profile->is_active; @endphp
                                <span class="rsv-badge" style="background: {{ $bookable ? '#27ae60' : '#6c757d' }}">
                                    {{ $bookable ? __('reservations.services.bookable') : __('reservations.services.stopped') }}
                                </span>
                            </td>
                            {{-- قائمةُ إجراءات الصفّ — زرُّ «⋮» كجداول النظام؛ النماذجُ أزرارٌ داخل القائمة --}}
                            <td class="text-center rsv-no-print">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            aria-label="{{ __('reservations.columns.actions') }} {{ $product->name }}">
                                        <i class="feather icon-more-vertical"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($profile)
                                            @if (hasPermission('reservation_services.edit'))
                                                <a class="dropdown-item" href="{{ route('Reservations.services.edit', $profile->id) }}">
                                                    <i class="feather icon-edit-2"></i> {{ __('reservations.actions.edit') }}
                                                </a>
                                                <form method="POST" action="{{ route('Reservations.services.toggle', $profile->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather icon-power"></i> {{ __('reservations.services.toggle') }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if (hasPermission('reservation_services.delete'))
                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="{{ route('Reservations.services.destroy', $profile->id) }}"
                                                      onsubmit="return confirm(@js(__('reservations.services.delete_confirm')))">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather icon-rotate-ccw"></i> {{ __('reservations.services.reset') }}
                                                    </button>
                                                </form>
                                            @endif
                                        @elseif (hasPermission('reservation_services.create'))
                                            <a class="dropdown-item" href="{{ route('Reservations.services.create', ['product_id' => $product->id]) }}">
                                                <i class="feather icon-sliders"></i> {{ __('reservations.services.set_profile') }}
                                            </a>
                                        @else
                                            <span class="dropdown-item disabled text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="rsv-empty">
                                    <div class="rsv-empty__icon"><i class="feather icon-briefcase"></i></div>
                                    <p class="rsv-empty__title">{{ __('reservations.services.empty_title') }}</p>
                                    <p class="rsv-empty__hint">{{ __('reservations.services.empty_hint') }}</p>
                                    @if (Route::has('products.create_services') && hasPermission('stock.products.services'))
                                        <a href="{{ route('products.create_services', ['return_to' => 'reservations']) }}" class="btn btn-sm btn-primary mt-2">
                                            <i class="feather icon-plus"></i> {{ __('reservations.services.new_product') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-body rsv-no-print">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
