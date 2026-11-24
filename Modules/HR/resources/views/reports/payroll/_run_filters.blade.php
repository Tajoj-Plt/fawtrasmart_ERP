{{--
    نموذجُ فلتر تقارير الرواتب — بتصميم تقارير العملاء.

    الحقولُ المشتركة من `hr::reports.partials.rpt-employee-fields` (المدى + القسم
    والمسمى والموظف وحالته + «تابع لـ»)، وحالةُ الموظف تفتح على «الكل»: رواتبُ
    من انتهت خدمته جزءٌ من التكلفة. ثم حقولُ التقرير نفسه.

    @param string $action       رابطُ التقرير (وهو رابطُ «إعادة تعيين» أيضاً)
    @param string|null $reportView   العرضُ الحاليّ إن كان للتقرير عرضان
    @param array  $views        [key => [label, icon]] — زرّا «نوع العرض»
    @param array  $selects      [[name, label, options => [value => label]]] — قوائمُ التقرير
    @param array  $texts        [[name, label, placeholder]] — حقولُ نصّ
--}}
@php
    $views = $views ?? [];
    $selects = $selects ?? [];
    $texts = $texts ?? [];
@endphp
<div class="rpt-card rpt-filters no-print">
    <div class="rpt-card__body">
        <form method="GET" action="{{ $action }}" id="filterForm">
            @if (! empty($reportView) && count($views) > 1)
                <input type="hidden" name="view" value="{{ $reportView }}">
            @endif
            <div class="ag-filter-grid">
                @include('hr::reports.partials.rpt-employee-fields', ['defaultEmployeeStatus' => ''])

                @foreach ($selects as $select)
                    <div class="rpt-field">
                        <label for="f_{{ $select['name'] }}">{{ $select['label'] }}</label>
                        <select name="{{ $select['name'] }}" id="f_{{ $select['name'] }}" class="form-control">
                            @foreach ($select['options'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($select['value'] ?? request($select['name'], '')) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                @foreach ($texts as $text)
                    <div class="rpt-field">
                        <label for="f_{{ $text['name'] }}">{{ $text['label'] }}</label>
                        <input type="text" name="{{ $text['name'] }}" id="f_{{ $text['name'] }}" class="form-control" value="{{ request($text['name']) }}" placeholder="{{ $text['placeholder'] ?? '' }}">
                    </div>
                @endforeach

                @if (count($views) > 1)
                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            @foreach ($views as $key => [$label, $icon])
                                <button type="submit" name="view" value="{{ $key }}" class="btn {{ $reportView === $key ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-{{ $icon }}"></i> {{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="ag-filter-foot">
                <div class="ag-quick__actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                    <a href="{{ $action }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                </div>
            </div>
        </form>
    </div>
</div>
