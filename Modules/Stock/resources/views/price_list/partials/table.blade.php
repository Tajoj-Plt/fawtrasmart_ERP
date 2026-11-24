{{--
    جدول قوائم الأسعار — يُحمَّل ويُستبدل بـAjax.

    كانت نافذة حذفٍ كاملة تُطبع داخل كل `<tr>` (وهو ترميز غير صالح: لا يقبل
    الصفّ إلا خلايا). صارت واحدةً في الصفحة تُعاد تعبئتها.

    ملاحظة على الحقل: `status = 0` تعني «نشط» في هذا الجدول، وأي قيمة أخرى
    «معطّل» — عكس ما يوحي به الاسم، فتُقرأ هنا صراحةً.
--}}

@if (isset($price_lists) && count($price_lists) > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th class="text-center" style="width:16%">عدد السلع</th>
                    <th class="text-center" style="width:16%">الحالة</th>
                    <th class="text-center" style="width:12%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($price_lists as $price_list)
                    @php $isActive = $price_list->status == 0; @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-tag"></i></span>
                                <div class="stk-media__body">
                                    @can('stock.price_lists.show')
                                        <a href="{{ route('price_list.show', $price_list->id) }}" class="stk-media__title">
                                            {{ $price_list->name }}
                                        </a>
                                    @else
                                        <span class="stk-media__title">{{ $price_list->name }}</span>
                                    @endcan
                                </div>
                            </div>
                        </td>

                        <td class="text-center">
                            <span class="stk-num">{{ count($price_list->price_list_products) }}</span>
                            <span class="stk-media__sub d-block">سلعة</span>
                        </td>

                        <td class="text-center">
                            <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">
                                {{ $isActive ? 'نشط' : 'معطّل' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="خيارات {{ $price_list->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @can('stock.price_lists.show')
                                        <a class="dropdown-item" href="{{ route('price_list.show', $price_list->id) }}">
                                            <i class="feather icon-eye"></i> عرض
                                        </a>
                                    @endcan

                                    @can('stock.price_lists.edit')
                                        <a class="dropdown-item" href="{{ route('price_list.edit', $price_list->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endcan

                                    @can('stock.price_lists.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-price-list" href="#"
                                           data-id="{{ $price_list->id }}" data-name="{{ $price_list->name }}">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (method_exists($price_lists, 'hasPages') && $price_lists->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">
                عرض <span class="stk-num">{{ $price_lists->firstItem() }}</span>–<span class="stk-num">{{ $price_lists->lastItem() }}</span>
                من <span class="stk-num">{{ $price_lists->total() }}</span>
            </p>
            <nav aria-label="صفحات القوائم">
                {{ $price_lists->appends(request()->except('page'))->links() }}
            </nav>
        </div>
    @endif
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-tag"></i></div>
        <h5 class="stk-empty__title">لا قوائم أسعار</h5>
        <p class="stk-empty__hint">أضف قائمة أسعار لتسعير المنتجات لفئات عملاء مختلفة</p>
    </div>
@endif
