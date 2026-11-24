{{--
    نموذجُ الوردية — مشتركٌ بين «إضافة» و«تعديل».

    يستقبل: $shift (null عند الإضافة).
    قسمان يتبادلان حسب «النوع»: `#basic` (أيامُ عمل + قواعدُ واحدة لكل الأيام)
    و`#advanced` (أوقاتٌ لكل يوم، كتلتُه `#{day}_fields` ويبدّلها
    `toggleDayFields()` بـ display:flex — فتبقى `div.row`). السكربت في الصفحة
    يعطّل مدخلات القسم المخفي فلا تُرسَل.
--}}
@php
    $sh = $shift ?? null;
    $days = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت'
    ];
    $workingDays = $sh
        ? $sh->days->where('working_day', 1)->pluck('day')->toArray()
        : ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
    // استرجاع بيانات القواعد من اليوم الأول كمرجع
    $defaultDay = $sh ? $sh->days->first() : null;
    $savedType = $sh ? (($sh->type == 1) ? 'basic' : 'advanced') : null;
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم تُحفظ الوردية — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="shift_name" class="form-label stk-req">الاسم</label>
                <input type="text" id="shift_name" name="name" class="form-control" value="{{ old('name', $sh->name ?? '') }}" required>
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-6 col-xl-4 mb-3">
                <label for="shift_type" class="form-label stk-req">النوع</label>
                @if ($sh)
                    <select class="form-control" id="shift_type" name="type" data-saved-value="{{ old('type', $savedType) }}" required>
                        <option value="basic" {{ old('type', $savedType) == 'basic' ? 'selected' : '' }}>أساسي</option>
                        <option value="advanced" {{ old('type', $savedType) == 'advanced' ? 'selected' : '' }}>متقدم</option>
                    </select>
                @else
                    <select class="form-control" id="shift_type" name="type" required>
                        <option value="basic" {{ old('type') == 'basic' ? 'selected' : '' }}>اساسي</option>
                        <option value="advanced" {{ old('type') == 'advanced' ? 'selected' : '' }}>متقدم</option>
                    </select>
                @endif
                <small class="stk-help">«أساسي»: أوقاتٌ واحدة لكل أيام العمل. «متقدم»: أوقاتٌ مستقلّة لكل يوم.</small>
            </div>
        </div>
    </div>
</div>

<!-- القسم الأساسي -->
<div id="basic">
    <div class="card">
        <div class="card-header"><h4 class="card-title"><i class="feather icon-clock"></i> قواعد الحضور</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">بداية الوردية</label>
                    <input type="time" name="start_time" class="form-control"
                           value="{{ old('start_time', $defaultDay->start_time ?? '09:00') }}" required>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">نهاية الوردية</label>
                    <input type="time" name="end_time" class="form-control"
                           value="{{ old('end_time', $defaultDay->end_time ?? '17:00') }}" required>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">فترة سماح التأخير</label>
                    <input type="number" name="grace_period" class="form-control"
                           value="{{ old('grace_period', $defaultDay->grace_period ?? ($sh ? 15 : '15')) }}"
                           placeholder="دقائق" min="0" required>
                    <small class="stk-help">بالدقائق.</small>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">حساب التأخير</label>
                    <select class="form-control" name="delay_calculation" required>
                        @if ($sh)
                            <option value="1" {{ old('delay_calculation', $defaultDay->delay_calculation ?? '') == 1 ? 'selected' : '' }}>
                                بعد موعد بداية الوردية + مهلة التأخير
                            </option>
                            <option value="2" {{ old('delay_calculation', $defaultDay->delay_calculation ?? '') == 2 ? 'selected' : '' }}>
                                من موعد بداية الوردية
                            </option>
                        @else
                            <option value="1" {{ old('delay_calculation') == '1' ? 'selected' : '' }}>بعد موعد بداية الوردية + مهلة التأخير</option>
                            <option value="2" {{ old('delay_calculation') == '2' ? 'selected' : '' }}>من موعد بداية الوردية</option>
                        @endif
                    </select>
                </div>

                {{--
                    نافذةُ تسجيل الدخول اختيارية — وكانت مطلوبةً بقيمتَين
                    مكتوبتَين هنا (07:00 و11:00). فمن أنشأ ورديّةً بلا أن
                    ينظر إليهما كتب سياسةً: «لا حضورَ بعد الحادية عشرة»،
                    وهي تعلو على إعدادات الدوام ولا سبيلَ إلى إلغائها.
                    الفراغُ الآن = لا نافذة، فيحكم «إقفال تسجيل الحضور بعد»
                    في إعدادات الدوام (وهو «لا يُقفل» افتراضاً).
                --}}
                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label">بداية تسجيل الدخول</label>
                    <input type="time" name="login_start_time" class="form-control"
                           value="{{ old('login_start_time', $defaultDay->login_start_time ?? null) }}">
                    <span class="stk-help">فارغ = يُفتح حسب «يُفتح التسجيل قبل الوردية» في إعدادات الدوام.</span>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label">نهاية تسجيل الدخول</label>
                    <input type="time" name="login_end_time" class="form-control"
                           value="{{ old('login_end_time', $defaultDay->login_end_time ?? null) }}">
                    <span class="stk-help">بعدها لا يُقبل تسجيل حضور لهذه الوردية. فارغ = لا إقفال على مستوى الوردية.</span>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">بداية تسجيل الخروج</label>
                    <input type="time" name="logout_start_time" class="form-control"
                           value="{{ old('logout_start_time', $defaultDay->logout_start_time ?? '15:00') }}" required>
                </div>

                <div class="col-md-6 col-xl-3 mb-3">
                    <label class="form-label stk-req">نهاية تسجيل الخروج</label>
                    <input type="time" name="logout_end_time" class="form-control"
                           value="{{ old('logout_end_time', $defaultDay->logout_end_time ?? '19:00') }}" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title"><i class="feather icon-calendar"></i> أيام العمل</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>يوم</th>
                        <th style="width: 50%">يوم عمل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $dayKey => $dayName)
                        <tr>
                            <td>{{ $dayName }}</td>
                            <td>
                                <label class="stk-switch mb-0">
                                    <input type="checkbox"
                                           id="{{ $dayKey }}"
                                           name="days[{{ $dayKey }}][working_day]"
                                           value="1"
                                           {{ in_array($dayKey, $workingDays) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title"></span>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- القسم المتقدم -->
<div id="advanced" style="display: none">
    <div class="card">
        <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> أيام العمل وأوقاتها</h4></div>
        <div class="card-body">
            @foreach ($days as $dayKey => $dayName)
                @php
                    // استرجاع بيانات اليوم الحالي من المجموعة
                    $currentDay = $sh ? $sh->days->where('day', $dayKey)->first() : null;
                    $isWorkingDay = in_array($dayKey, $workingDays);
                @endphp

                <div class="d-flex align-items-center justify-content-between border-bottom pb-50 mb-1 {{ $loop->first ? '' : 'pt-1' }}">
                    <strong>{{ $dayName }}</strong>
                    <label class="stk-switch mb-0">
                        <input type="checkbox"
                               id="{{ $dayKey }}_enabled"
                               name="days[{{ $dayKey }}][working_day]"
                               value="1"
                               onchange="toggleDayFields('{{ $dayKey }}')"
                               {{ $isWorkingDay ? 'checked' : '' }}>
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title"></span>
                        </span>
                    </label>
                </div>

                <div id="{{ $dayKey }}_fields" class="row">
                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">بداية الوردية</label>
                        <input type="time" name="days[{{ $dayKey }}][start_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.start_time', $currentDay->start_time ?? '09:00') }}">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">نهاية الوردية</label>
                        <input type="time" name="days[{{ $dayKey }}][end_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.end_time', $currentDay->end_time ?? '17:00') }}">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">فترة سماح التأخير</label>
                        <input type="number" name="days[{{ $dayKey }}][grace_period]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.grace_period', $currentDay->grace_period ?? ($sh ? 15 : '15')) }}"
                               placeholder="دقائق" min="0">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">حساب التأخير</label>
                        <select class="form-control" name="days[{{ $dayKey }}][delay_calculation]">
                            @if ($sh)
                                <option value="1" {{ old('days.'.$dayKey.'.delay_calculation', $currentDay->delay_calculation ?? '') == 1 ? 'selected' : '' }}>
                                    بعد موعد بداية الوردية + مهلة التأخير
                                </option>
                                <option value="2" {{ old('days.'.$dayKey.'.delay_calculation', $currentDay->delay_calculation ?? '') == 2 ? 'selected' : '' }}>
                                    من موعد بداية الوردية
                                </option>
                            @else
                                <option value="1" {{ old('days.'.$dayKey.'.delay_calculation') == '1' ? 'selected' : '' }}>بعد موعد بداية الوردية + مهلة التأخير</option>
                                <option value="2" {{ old('days.'.$dayKey.'.delay_calculation') == '2' ? 'selected' : '' }}>من موعد بداية الوردية</option>
                            @endif
                        </select>
                    </div>

                    {{-- اختيارية كالنمط الأساسي: الفراغ = لا نافذة على مستوى الوردية --}}
                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label">بداية تسجيل الدخول</label>
                        <input type="time" name="days[{{ $dayKey }}][login_start_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.login_start_time', $currentDay->login_start_time ?? null) }}">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label">نهاية تسجيل الدخول</label>
                        <input type="time" name="days[{{ $dayKey }}][login_end_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.login_end_time', $currentDay->login_end_time ?? null) }}">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">بداية تسجيل الخروج</label>
                        <input type="time" name="days[{{ $dayKey }}][logout_start_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.logout_start_time', $currentDay->logout_start_time ?? '15:00') }}">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-2">
                        <label class="form-label stk-req">نهاية تسجيل الخروج</label>
                        <input type="time" name="days[{{ $dayKey }}][logout_end_time]" class="form-control"
                               value="{{ old('days.'.$dayKey.'.logout_end_time', $currentDay->logout_end_time ?? '19:00') }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
