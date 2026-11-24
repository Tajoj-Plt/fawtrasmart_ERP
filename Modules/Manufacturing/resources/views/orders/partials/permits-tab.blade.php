{{--
    تبويب أذون أمر التصنيع — يخدم «المواد المستلمة» و«مرتجعات المواد».

    كان التبويبان ملفّين متطابقين يختلفان في `permission_type` وحده، وفي
    قراءة الحالة: أحدهما يقارنها بـ`1`/`0` والآخر بـ`'approved'`/`'pending'`
    — والعمود نصّ، فجدول «المستلمة» كان يعرض «مرفوض» لكل إذنٍ معتمد.

    الوسائط: $id، $type (نوع الإذن)، $title، $icon، $emptyText، $action
--}}

@php
    /*
        الأذون تصل جاهزةً من `ManufacturingOrdersController@show`.

        كان القالب يستعلم قاعدة البيانات بنفسه — استعلامٌ في طبقة العرض
        بمعرّف مصدرٍ سحري مكتوب باليد (`15`)، والقالب يُدرَج مرّتين لكل
        عرض. والمعرّف `15` نفسه خطأ: `PermissionSourcesSeeder` يعرّفه
        «تحويل وارد لنقطة البيع»، وأذون التصنيع مصادرها 3 و4 و16 و17.
        الاستعلام في المتحكّم يشملها كلها ويُبقي 15 للأذون التاريخية.

        احتياطٌ للتوافق: إن أُدرج القالب من موضعٍ لا يمرّر $permitsBySource.
    */
    /*
        التجميع صار بمصدر الإذن لا بنوعه.

        النوع `1` (إضافة) يشمل ثلاثة مصادر — المنتج التام والهالك ومرتجع
        المواد — فكان تبويب «مرتجعات المواد» يعرضها كلّها تحت عنوانه.
        المصدر يفصلها، فصار لكل نوع تبويبه باسمه.
    */
    $sources = (array) ($source ?? []);

    $permits = isset($permitsBySource)
        ? collect($sources)->flatMap(fn ($s) => ($permitsBySource[$s] ?? collect()))->values()
        : App\Models\WarehousePermits::where('reference_type', \App\Enums\ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->whereIn('permission_source_id', $sources)
            ->with(['items.product', 'storeHouse'])
            ->orderByDesc('created_at')
            ->get();

    /* العمود غير منضبط على مستوى النظام: يُكتب `1` عدداً في وحدة
       المخزون، ويُستعلم عنه أحياناً `'apporod'` بخطأ إملائي. */
    $states = [
        1 => ['معتمد', 'on'],
        '1' => ['معتمد', 'on'],
        'pending' => ['قيد الانتظار', 'off'],
        'approved' => ['معتمد', 'on'],
        'apporod' => ['معتمد', 'on'],
        'rejected' => ['مرفوض', 'off'],
        'rejec' => ['مرفوض', 'off'],
        'reversed' => ['معكوس', 'off'],
        'returned' => ['مرتجع', 'off'],
    ];
@endphp

<div class="tab-pane" id="{{ $id }}" role="tabpanel">
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-{{ $icon }}"></i> {{ $title }}
                <span class="stk-count">{{ $permits->count() }}</span>
            </h6>

            @if (! empty($action))
                {!! $action !!}
            @endif
        </div>

        @if ($permits->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:22%">رقم الإذن</th>
                            <th style="width:16%">التاريخ</th>
                            <th>المستودع</th>
                            <th class="text-center" style="width:14%">الحالة</th>
                            <th class="text-center" style="width:14%">المجموع</th>
                            <th class="text-center" style="width:9%">عرض</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permits as $permit)
                            @php [$label, $tone] = $states[$permit->status] ?? [$permit->status, 'off']; @endphp

                            <tr>
                                <td>
                                    <div class="stk-media__body">
                                        <a href="{{ route('store_permits_management.show', $permit->id) }}"
                                           class="stk-media__title">{{ $permit->number }}</a>
                                        @if ($permit->details)
                                            <span class="stk-media__sub">{{ $permit->details }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="stk-num">
                                    {{ $permit->permission_date
                                        ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d')
                                        : '—' }}
                                </td>
                                <td>{{ optional($permit->storeHouse)->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $tone }}">{{ $label }}</span>
                                </td>
                                <td class="text-center stk-num">@money($permit->grand_total)</td>
                                <td class="text-center">
                                    <a href="{{ route('store_permits_management.show', $permit->id) }}"
                                       class="btn btn-sm btn-outline-secondary" aria-label="عرض الإذن">
                                        <i class="feather icon-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-{{ $icon }}"></i></div>
                    <h5 class="stk-empty__title">{{ $title }}: لا شيء بعد</h5>
                    <p class="stk-empty__hint">{{ $emptyText }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
