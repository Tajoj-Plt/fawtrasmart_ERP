{{--
    بطاقة معلومات أمر التصنيع.

    كان هذا الملف نسخةً ثانية من الكتلة نفسها المكتوبة داخل `info-tab`،
    ولا يستدعيه أحد — نسختان تتباعدان مع الوقت (إحداهما تعرض العميل
    والأخرى تركت خانته فارغة). صار المصدر واحداً و`info-tab` يستدعيه.
--}}

<div class="row">
    <div class="col-lg-4 col-12">
        <div class="card">
            <div class="card-body text-center">
                <img src="{{ $order->product && $order->product->images
                            ? asset('assets/uploads/product/' . $order->product->images)
                            : asset('assets/uploads/no_image.jpg') }}"
                     alt="{{ optional($order->product)->name }}" class="stk-photo">

                <h5 class="mt-2 mb-1">{{ optional($order->product)->name ?? 'منتج محذوف' }}</h5>

                @if ($order->product)
                    <a href="{{ route('products.show', $order->product->id) }}" class="stk-media__sub stk-num">
                        #{{ $order->product->serial_number }} <i class="feather icon-external-link"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-info"></i> بيانات الأمر</h4>
                @include('manufacturing::orders.partials.status', ['status' => $order->status])
            </div>
            <div class="card-body">
                <dl class="stk-def">
                    <div class="stk-def__row">
                        <dt>الاسم</dt>
                        <dd>{{ $order->name }}</dd>
                    </div>

                    <div class="stk-def__row">
                        <dt>الكمية المطلوبة</dt>
                        <dd class="stk-num">
                            {{ $order->quantity }}
                            @if ($order->actual_quantity && $order->actual_quantity != $order->quantity)
                                <span class="stk-media__sub">الفعلية: {{ $order->actual_quantity }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="stk-def__row">
                        <dt>الفترة</dt>
                        <dd class="stk-num">
                            {{ $order->from_date }} — {{ $order->to_date }}
                            @if ($order->finished_at)
                                <span class="stk-media__sub text-success">
                                    أُنهي في {{ \Illuminate\Support\Carbon::parse($order->finished_at)->format('Y-m-d') }}
                                </span>
                            @endif
                        </dd>
                    </div>

                    @php
                        /* الروابط الأربعة بالبنية نفسها — كانت مكتوبة أربع مرّات
                           بيدها، وواحد منها يشير خطأً إلى `Bom.show` للموظّف. */
                        $links = [
                            ['مسار الإنتاج', $order->productionPath, 'manufacturing.paths.show', 'name', 'code'],
                            ['قائمة المواد', $order->productionMaterial, 'Bom.show', 'name', 'code'],
                            ['العميل', $order->client, 'clients.show', 'trade_name', 'code'],
                        ];
                    @endphp

                    @foreach ($links as [$label, $relation, $route, $nameKey, $codeKey])
                        <div class="stk-def__row">
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if ($relation)
                                    {{ $relation->{$nameKey} }}
                                    @if (Route::has($route))
                                        <a href="{{ route($route, $relation->id) }}" class="stk-media__sub stk-num">
                                            #{{ $relation->{$codeKey} }} <i class="feather icon-external-link"></i>
                                        </a>
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    @endforeach

                    <div class="stk-def__row">
                        <dt>الحساب</dt>
                        <dd>{{ optional($order->account)->name ?? '—' }}</dd>
                    </div>

                    <div class="stk-def__row">
                        <dt>الموظف</dt>
                        <dd>{{ optional($order->employee)->full_name ?? '—' }}</dd>
                    </div>

                    @if ($order->finish_notes)
                        <div class="stk-def__row">
                            <dt>ملاحظات الإنهاء</dt>
                            <dd>{{ $order->finish_notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
