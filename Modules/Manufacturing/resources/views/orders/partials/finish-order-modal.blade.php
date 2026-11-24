{{--
    نافذة إنهاء أمر التصنيع.

    كانت نسختها الثانية في `delete-modal` بالمعرّف نفسه — حُذفت هناك.
    و`$wasteMaterials` لم يكن يُمرَّر من `show`، فيسقط القالب على متغيّر
    غير معرَّف متى فُتحت النافذة؛ يُشتقّ الآن من بنود الأمر نفسه.
--}}

@if (\App\Enums\ManufacturOrderStatus::fromLegacy($order->status)?->canComplete() ?? false)
    @php
        $waste = ($wasteMaterials ?? null)
            ?: $order->manufacturOrdersItem->whereNotNull('end_life_product_id')->values();

        $wasteTotal = $waste->sum('end_life_total');
        $stores = collect($storehouse ?? []);
    @endphp

    <div class="modal fade" id="finishOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <form action="{{ route('manufacturing.orders.complete', $order->id) }}" method="POST">
                @csrf

                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">إنهاء أمر التصنيع</h5>
                        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="feather icon-info"></i>
                            يُحدَّث المخزون ويُضاف المنتج النهائي مباشرةً بلا حاجة إلى موافقة.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="storehouse_finish" class="stk-req">مستودع الاستلام</label>
                                    <select id="storehouse_finish" name="storehouse_id" required class="form-control">
                                        <option value="">اختر المستودع…</option>
                                        @foreach ($stores as $store)
                                            <option value="{{ $store->id }}" @selected(old('storehouse_id', $order->storehouse_id) == $store->id)>{{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="actual_quantity_finish" class="stk-req">الكمية الفعلية</label>
                                    {{-- إعداد «السماح بتجاوز الكمية المطلوبة»: معطَّلاً يُحدّ
                                         الحقلُ بكمية الأمر، والخادم يرفض ما زاد عليها --}}
                                    @php
                                        $exceedAllowed = (bool) optional(\App\Models\ManufacturingGeneralSetting::first())->quantity_exceeded;
                                    @endphp
                                    <input type="number" step="any" min="0" id="actual_quantity_finish"
                                           name="actual_quantity" required class="form-control stk-num"
                                           @unless ($exceedAllowed) max="{{ (float) $order->quantity }}" @endunless
                                           value="{{ (float) $order->quantity }}">
                                    <span class="stk-help">
                                        المطلوبة: {{ (float) $order->quantity }} —
                                        {{ $exceedAllowed ? 'تجاوزها مسموح من الإعدادات' : 'لا يُسمح بتجاوزها (من إعدادات التصنيع)' }}
                                    </span>
                                </div>
                            </div>

                            {{-- مستودعُ الهالك — يظهر حين في الأمر موادُّ هالكة. كان الهالكُ يدخل
                                 مستودعَ المنتج التامّ دائماً ولا حقلَ لاختيار غيره. --}}
                            @if ($waste->count() > 0)
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="waste_storehouse_finish">مستودع المواد الهالكة</label>
                                        <select id="waste_storehouse_finish" name="waste_storehouse_id" class="form-control">
                                            <option value="">نفس مستودع الاستلام</option>
                                            @foreach ($stores as $store)
                                                <option value="{{ $store->id }}" @selected(old('waste_storehouse_id', $order->waste_warehouse_id) == $store->id)>{{ $store->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="stk-help">المستودع الذي يدخله الهالك — اتركه فارغاً ليدخل مع المنتج</span>
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label for="notes_finish">ملاحظات الإنهاء</label>
                                    <textarea id="notes_finish" name="notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        @if ($waste->count() > 0)
                            <hr>

                            <p class="stk-help mb-2">
                                <i class="feather icon-alert-triangle"></i>
                                تُضاف المواد الهالكة التالية إلى «مستودع المواد الهالكة» المختار أعلاه وتُخصم من تكلفة المنتج.
                            </p>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>المنتج</th>
                                            <th class="text-center" style="width:18%">الكمية</th>
                                            <th class="text-center" style="width:20%">السعر</th>
                                            <th class="text-center" style="width:20%">الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($waste as $item)
                                            <tr>
                                                <td>{{ optional($item->endLifeProduct)->name ?? '—' }}</td>
                                                <td class="text-center stk-num">{{ $item->end_life_quantity }}</td>
                                                <td class="text-center stk-num">@money($item->end_life_unit_price)</td>
                                                <td class="text-center stk-num">@money($item->end_life_total)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="stk-total-row">
                                            <td colspan="3">إجمالي الهوالك</td>
                                            <td class="text-center stk-num">@money($wasteTotal)</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="feather icon-check"></i> إنهاء الأمر
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
