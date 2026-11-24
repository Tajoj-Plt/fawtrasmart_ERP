{{--
    «إضافة وحدات دفعة واحدة» لعمارة: من رقم … إلى رقم …، بالطابق والنوع والاستخدام
    والمساحة والسعر نفسها. الوسائط: $bulkBuildings (مبانٍ متعددة الوحدات مع floors)،
    $bulkBuilding (اختياري: مبنى محدَّد سلفاً). يفتحها أي عنصر `.js-bulk-units`.
    تحتاج الصفحة إلى list-scripts و ajax-form-scripts.
--}}
@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $bulkBuilding = $bulkBuilding ?? null;
    $bulkMeta = $bulkBuildings->mapWithKeys(fn ($b) => [$b->id => [
        'usage' => $b->usage_type,
        'hasFloors' => $b->hasFloors(),
        'floors' => $b->floors->map(fn ($f) => ['id' => $f->id, 'label' => $f->label])->values(),
    ]]);
@endphp

<div class="modal fade" id="bulkUnitsModal" tabindex="-1" aria-labelledby="bulkUnitsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content re-ajax-form" data-url="{{ route('realestate.units.bulk') }}" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUnitsTitle"><i class="feather icon-layers"></i> إضافة وحدات دفعة واحدة</h5>
                <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <div class="re-ajax-errors alert alert-danger d-none"></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="bulk_building_id" class="stk-req">المبنى</label>
                            @if ($bulkBuilding)
                                <input type="hidden" id="bulk_building_id" name="building_id" value="{{ $bulkBuilding->id }}">
                                <input type="text" class="form-control" value="{{ $bulkBuilding->name }} — {{ $bulkBuilding->code }}" readonly>
                            @else
                                <select id="bulk_building_id" name="building_id" class="form-control">
                                    <option value="">اختر المبنى…</option>
                                    @foreach ($bulkBuildings as $b) <option value="{{ $b->id }}">{{ $b->name }} — {{ $b->code }}</option> @endforeach
                                </select>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="bulk_floor_id">الطابق</label>
                            <select id="bulk_floor_id" name="floor_id" class="form-control"><option value="">— بلا طابق —</option></select>
                            <small class="text-muted d-block" id="bulkFloorHint"></small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_from_no" class="stk-req">من رقم</label>
                            <input type="number" min="0" id="bulk_from_no" name="from_no" class="form-control" value="1">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_to_no" class="stk-req">إلى رقم</label>
                            <input type="number" min="0" id="bulk_to_no" name="to_no" class="form-control" value="10">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="bulk_prefix">بادئة الرقم (اختياري)</label>
                            <input type="text" id="bulk_prefix" name="prefix" class="form-control" maxlength="20" placeholder="مثال: A-">
                            <small class="text-muted d-block" id="bulkPreview"></small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_usage_type" class="stk-req">الاستخدام</label>
                            <select id="bulk_usage_type" name="usage_type" class="form-control">
                                @foreach (V::UNIT_USAGE_TYPES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_unit_type" class="stk-req">نوع الوحدة</label>
                            <select id="bulk_unit_type" name="unit_type" class="form-control"></select>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_area_sqm">المساحة (م²)</label>
                            <input type="number" step="0.01" min="0" id="bulk_area_sqm" name="area_sqm" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_base_rent">السعر الإرشادي ({{ \App\Support\CurrencySettings::symbol() }})</label>
                            <input type="number" step="0.01" min="0" id="bulk_base_rent" name="base_rent" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="form-group">
                            <label for="bulk_rent_period">دورية السعر</label>
                            <select id="bulk_rent_period" name="rent_period" class="form-control">
                                @foreach (V::RENT_PERIODS as $value => $meta) <option value="{{ $value }}" @selected($value === 'yearly')>{{ $meta['label'] }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row bulk-residential">
                    @foreach ([['bedrooms', 'غرف النوم'], ['bathrooms', 'دورات المياه'], ['living_rooms', 'الصالات'], ['kitchens', 'المطابخ']] as [$field, $label])
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="bulk_{{ $field }}">{{ $label }}</label>
                                <input type="number" min="0" id="bulk_{{ $field }}" name="{{ $field }}" class="form-control" value="0">
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="row bulk-commercial">
                    <div class="col-md-3 col-6"><div class="form-group"><label for="bulk_frontage_m">الواجهة (م)</label><input type="number" step="0.01" min="0" id="bulk_frontage_m" name="frontage_m" class="form-control"></div></div>
                    <div class="col-md-3 col-6"><div class="form-group"><label for="bulk_ceiling_height_m">ارتفاع السقف (م)</label><input type="number" step="0.01" min="0" id="bulk_ceiling_height_m" name="ceiling_height_m" class="form-control"></div></div>
                    <div class="col-md-3 col-6"><div class="form-group"><label for="bulk_openings_count">عدد الفتحات</label><input type="number" min="0" id="bulk_openings_count" name="openings_count" class="form-control"></div></div>
                    <div class="col-md-3 col-6"><div class="form-group"><label for="bulk_permitted_activity">النشاط المسموح</label><input type="text" id="bulk_permitted_activity" name="permitted_activity" class="form-control" maxlength="255"></div></div>
                </div>
                <p class="text-muted small mb-0">تُنشأ الوحدات كلها شاغرة بالمواصفات نفسها، وتُعدَّل كل وحدة بعدها من صفحتها. إن كان أحد الأرقام مستخدماً لم تُنشأ أي وحدة.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> إنشاء الوحدات</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            const buildings = @json($bulkMeta);
            const unitTypes = @json(collect(V::UNIT_TYPES)->map(fn ($meta) => ['label' => $meta['label'], 'usage' => $meta['usage']]));
            const $building = $('#bulk_building_id'), $floor = $('#bulk_floor_id'), $usage = $('#bulk_usage_type'), $type = $('#bulk_unit_type');

            function fillTypes() {
                const usage = $usage.val(), selected = $type.val();
                $type.empty();
                Object.keys(unitTypes).forEach(function (value) {
                    const meta = unitTypes[value];
                    if (value !== 'other' && (meta.usage === null || meta.usage === usage)) $type.append(new Option(meta.label, value, false, value === selected));
                });
                if (!$type.val()) $type.prop('selectedIndex', 0);
                $('.bulk-residential').toggle(usage === 'residential' && $type.val() !== 'land');
                $('.bulk-commercial').toggle(usage === 'commercial');
            }

            function syncBuilding() {
                const building = buildings[$building.val()] || null;
                const fixed = building && building.usage !== 'mixed';

                $floor.find('option:not(:first)').remove();
                (building ? building.floors : []).forEach(f => $floor.append(new Option(f.label, f.id)));
                $('#bulkFloorHint').text(building && building.hasFloors && !building.floors.length ? 'لا طوابق مسجّلة لهذا المبنى — أضفها من صفحة المبنى.' : '');

                if (fixed) $usage.val(building.usage);
                $usage.css({ 'pointer-events': fixed ? 'none' : '', opacity: fixed ? .65 : '' });
                fillTypes();
            }

            function preview() {
                const from = parseInt($('#bulk_from_no').val(), 10), to = parseInt($('#bulk_to_no').val(), 10), prefix = $('#bulk_prefix').val();
                $('#bulkPreview').text(isNaN(from) || isNaN(to) || to < from ? '' : (to - from + 1) + ' وحدة: ' + prefix + from + ' … ' + prefix + to);
            }

            $building.on('change', syncBuilding);
            $usage.on('change', fillTypes);
            $type.on('change', fillTypes);
            $('#bulk_from_no, #bulk_to_no, #bulk_prefix').on('input', preview);
            $(document).on('click', '.js-bulk-units', function (e) { e.preventDefault(); reModal($('#bulkUnitsModal'), true); });

            syncBuilding();
            preview();
        });
    </script>
@endpush
