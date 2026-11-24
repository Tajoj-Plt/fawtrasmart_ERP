{{--
    قائمة الوحدات تتبع قائمة العقار.

    كانت كل شاشة تعرض **كل وحدات النظام** بعد اختيار العقار. الآن اختيار العقار يقصر القائمة على وحداته،
    ومسحه يعيد الكل، واختيار وحدة يحدّد عقارها (إلا إن مُنع بـ `syncBuilding: false`).

    الاستعمال بعد تحميل الصفحة:
        reUnitFilter('#building_id', '#unit_id');
    ولكل `<option>` من الوحدات الخاصية `data-building="{{ $unit->building_id }}"`.
--}}
<script>
    (function ($) {
        window.reUnitFilter = function (buildingSelector, unitSelector, options) {
            const settings = Object.assign({ syncBuilding: true }, options || {});
            const $building = $(buildingSelector), $unit = $(unitSelector);

            if (!$building.length || !$unit.length || $unit.data('reUnitFilter')) return;
            $unit.data('reUnitFilter', true);

            const all = $unit.find('option').map(function () {
                return { value: this.value, text: $(this).text(), building: $(this).attr('data-building') || '' };
            }).get();
            const emptyText = (all.find(o => o.value === '') || {}).text || 'اختر الوحدة…';

            function rebuild() {
                const building = String($building.val() || '');
                const current = String($unit.val() || '');
                const list = all.filter(o => o.value !== '' && (!building || o.building === building));
                const keep = list.some(o => o.value === current);

                $unit.empty().append(new Option(building && !list.length ? 'لا وحدات في هذا العقار' : emptyText, ''));
                list.forEach(function (o) {
                    const option = new Option(o.text, o.value, false, o.value === current);
                    option.setAttribute('data-building', o.building);
                    $unit.append(option);
                });

                $unit.val(keep ? current : '');
                // وحدةٌ سقطت من القائمة: تُبلَّغ بقية المعالجات (تحميل بيانات الوحدة…)؛ وإلا يكفي تحديث الواجهة
                keep ? $unit.trigger('change.select2') : $unit.trigger('change');
            }

            $building.on('change', rebuild);

            if (settings.syncBuilding) {
                $unit.on('change', function () {
                    const building = $(this).find('option:selected').attr('data-building');

                    if (building && String($building.val() || '') !== building) {
                        $building.val(building).trigger('change.select2');
                    }
                });
            }

            if ($building.val()) rebuild();
        };
    })(jQuery);
</script>
