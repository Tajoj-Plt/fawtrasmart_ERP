{{--
    جدول الأصول — يُحمَّل مع الصفحة ومع نتائج البحث الحيّ معاً.

    كان يحمل ترميز DataTables كاملاً (`dataTables_wrapper`, `sorting_asc`,
    `aria-controls`) بلا أي مكتبة DataTables على الصفحة: أصنافٌ لا تفعل شيئاً
    وسماتُ وصولٍ تَعِد بفرزٍ لا وجود له — قارئ الشاشة يقول «مرتَّب تصاعدياً»
    والعمود غير قابل للفرز أصلاً.

    ورمز العملة كان صورة `Saudi_Riyal.svg` أو `$` بحسب رقمٍ مخزَّن (1 أو 2)،
    فلا يتبع «العملات» في الإعدادات.
--}}

@if ($assets->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة الأصول
            <span class="stk-count">
                {{ method_exists($assets, 'total') ? $assets->total() : $assets->count() }}
            </span>
        </h6>

        @include('stock::components.per-page', [
            'onchange' => "window.location.href='"
                . route('Assets.index') . '?' . http_build_query(request()->except('per_page', 'page'))
                . "&per_page=' + this.value",
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>الأصل</th>
                    <th>الكود</th>
                    <th class="text-center">قيمة الشراء</th>
                    <th class="text-center">القيمة الدفترية</th>
                    <th class="text-center">طريقة الإهلاك</th>
                    <th>الموظف المسؤول</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($assets as $asset)
                    @php
                        $bookValue = $asset->depreciation->book_value ?? $asset->purchase_value;

                        $methods = [
                            1 => 'القسط الثابت',
                            2 => 'القسط المتناقص',
                            3 => 'وحدات الإنتاج',
                            4 => 'بدون إهلاك',
                        ];

                        $method = $methods[$asset->depreciation->dep_method ?? 4] ?? 'بدون إهلاك';

                        $states = [
                            1 => ['stk-state--on', 'في الخدمة'],
                            2 => ['stk-state--neutral', 'تم البيع'],
                            3 => ['stk-state--off', 'مهلك'],
                        ];

                        [$stateClass, $stateLabel] = $states[$asset->status] ?? ['stk-state--neutral', 'غير معروف'];
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                @if ($asset->attachments)
                                    <img src="{{ asset('storage/' . $asset->attachments) }}"
                                        alt="{{ $asset->name }}" class="stk-media__thumb">
                                @else
                                    <span class="stk-media__thumb"><i class="feather icon-box"></i></span>
                                @endif

                                <span class="stk-media__body">
                                    <span class="stk-media__title">{{ $asset->name }}</span>
                                    @if ($asset->date_price)
                                        <span class="stk-media__sub">اشتُري في {{ $asset->date_price }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>

                        <td class="stk-num">{{ $asset->code }}</td>

                        <td class="text-center">@money($asset->purchase_value ?? 0, $asset)</td>

                        <td class="text-center">
                            <strong>@money($bookValue, $asset)</strong>
                        </td>

                        <td class="text-center">
                            <span class="stk-state stk-state--neutral">{{ $method }}</span>
                        </td>

                        <td>{{ $asset->employee->full_name ?? 'غير محدد' }}</td>

                        <td class="text-center">
                            <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('Assets.show', $asset->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @can('account.assets.edit')
                                        <a class="dropdown-item" href="{{ route('Assets.edit', $asset->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endcan

                                    @can('account.assets.sell')
                                        @if ($asset->status != 2)
                                            <a class="dropdown-item" href="{{ route('Assets.showSell', $asset->id) }}">
                                                <i class="feather icon-dollar-sign"></i> بيع الأصل
                                            </a>
                                        @endif
                                    @endcan

                                    @can('account.assets.delete')
                                        <div class="dropdown-divider"></div>

                                        <form action="{{ route('Assets.destroy', $asset->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger"
                                                onclick="return confirm('حذف الأصل «{{ $asset->name }}»؟ لا رجوع عن هذا.')">
                                                <i class="feather icon-trash-2"></i> حذف
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('account::asol.partials.pagination', ['assets' => $assets])
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-box"></i></span>
        <h6 class="stk-empty__title">لا أصول مطابقة</h6>
        <p class="stk-empty__hint">
            @if (request()->hasAny(['name', 'code', 'employee_id', 'status', 'from_date_2', 'to_date_2']))
                لا نتائج لهذه الفلاتر — جرّب توسيعها أو أعِد تعيينها.
            @else
                لم يُسجَّل أصلٌ ثابت بعد. الأصل المسجَّل يُهلَك تلقائياً وتظهر قيمته الدفترية هنا.
            @endif
        </p>

        @can('account.assets.create')
            <a href="{{ route('Assets.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> أصل جديد
            </a>
        @endcan
    </div>
@endif
