@extends('rentalmanagement::realestate.master')

@section('title', 'خريطة العقارات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('realestate.buildings.index') }}" class="btn btn-outline-secondary"><i class="feather icon-list"></i> قائمة العقارات</a>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'خريطة العقارات',
            'icon' => 'map',
            'sub' => 'كل المباني والمجمعات التي حُدّد موقعها',
            'crumbs' => ['الرئيسية' => url('/'), 'المباني والمجمعات' => route('realestate.buildings.index'), 'الخريطة' => null],
        ])

        @if ($missing > 0)
            <div class="alert alert-warning" role="alert">
                <i class="feather icon-alert-triangle"></i> {{ number_format($missing) }} عقار بلا موقع — حدّد موقعه من «تعديل العقار» ليظهر هنا.
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center mb-1" style="gap:.5rem">
                    <select id="mapJump" class="form-control select2" style="flex:1 1 260px">
                        <option value="">انتقل إلى عقار…</option>
                        @foreach ($points as $point) <option value="{{ $point['id'] }}">{{ $point['name'] }} — {{ $point['code'] }}</option> @endforeach
                    </select>
                    <button type="button" class="btn btn-outline-primary" id="mapFitAll"><i class="feather icon-maximize"></i> إظهار الكل</button>
                    <span class="stk-count">{{ number_format($points->count()) }} عقار</span>
                </div>

                @if ($points->isEmpty())
                    <p class="text-muted text-center py-3 mb-0">لا عقارات محدَّدة الموقع بعد.</p>
                @else
                    <div id="buildingsMap" style="height:70vh;min-height:420px;border-radius:.5rem;border:1px solid rgba(0,0,0,.1)"></div>
                    <p class="text-danger d-none mt-1 mb-0" id="mapError">تعذّر تحميل الخريطة — تحقّق من اتصال الإنترنت.</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.map-provider')
    <script>
        const points = @json($points);

        if (points.length) reMap.init(function (api) {
            const map = api.map(document.getElementById('buildingsMap'), { center: points[0], zoom: 12 });
            const markers = {};

            function card(p) {
                const $card = $('<div dir="rtl" style="min-width:200px;text-align:right"></div>');
                $card.append($('<strong class="d-block"></strong>').text(p.name), $('<small class="text-muted d-block"></small>').text(p.code));
                if (p.address) $card.append($('<div class="mt-25"></div>').text(p.address));
                $card.append($('<div class="mt-25"></div>').text('الوحدات: ' + p.units + ' · الشاغرة: ' + p.vacant));

                const $links = $('<div class="mt-50"></div>');
                if (p.url) $links.append($('<a class="me-1 ms-1"></a>').attr('href', p.url).text('فتح العقار'));
                $links.append($('<a target="_blank" rel="noopener"></a>').attr('href', 'https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng).text('الاتجاهات'));

                return $card.append($links)[0];
            }

            points.forEach(function (p) {
                markers[p.id] = map.marker(p, { title: p.name });
                markers[p.id].onClick(() => markers[p.id].popup(card(p)));
            });

            const fitAll = () => points.length === 1 ? map.setView(points[0], 16) : map.fit(points);
            const focus = p => { map.setView(p, 17); markers[p.id].popup(card(p)); };

            const focused = points.find(p => p.focus);
            focused ? focus(focused) : fitAll();

            $('#mapFitAll').off('.remap').on('click.remap', function () { map.closePopup(); fitAll(); });
            $('#mapJump').off('.remap').on('change.remap', function () {
                const p = points.find(x => String(x.id) === this.value);
                if (p) focus(p);
            });
        }, function () {
            $('#buildingsMap').hide();
            $('#mapError').removeClass('d-none');
        });
    </script>
@endsection
