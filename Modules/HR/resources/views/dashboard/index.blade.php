{{--
    لوحةُ تحكّم الموظفين — بتصميم لوحة العملاء ومنهجها.

    **كلُّ رقمٍ يفتح موظفيه.** البطاقاتُ وأجزاءُ المخطَّطات وصفوفُ الجداول
    تحمل `data-segment`؛ والنقرُ يطلب `hr.dashboard.segment` بفلاتر اللوحة
    نفسِها فيعرض القائمةَ في لوحةٍ جانبية، وكلُّ موظفٍ فيها رابطٌ إلى ملفّه.
    والقائمةُ تُبنى في `Modules\HR\Support\HrDashboardService` من الخريطة
    التي عُدَّ منها الرقم — فطولُها يساوي الرقمَ المعروض.

    ولكلّ بطاقةٍ علامةُ «؟» تقول **من أين يُقرأ الرقم بالضبط** — فلا يُترك
    المستخدمُ يحدس معنى «غائب» أو «لم يُسجَّل».
--}}
@extends('hr::master')

@section('title', 'لوحة تحكم الموظفين')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.css">
@include('hr::dashboard.partials.dashboard-theme')
@endpush

@section('content')
<div class="content-body">
    @include('components.hint-tips')

    <section id="hr-dashboard">
        @php
            $force = $data['force'];
            $move = $data['move'];
            $todayStats = $data['today'];
            $att = $data['attendance'];
            $leaves = $data['leaves'];
            $pay = $data['payroll'];
            $adv = $data['advances'];
            $ded = $data['deductions'];
            $contracts = $data['contracts'];
            $dist = $data['distribution'];
            $ready = $data['readiness'];

            $money = fn ($value) => \App\Support\CurrencySettings::format($value);

            // تمييزُ العدد بالعربية — أربعةُ صيغٍ لا صيغتان، و`trans_choice` لا تُحسنُها
            $employees = function (int $n) {
                if ($n === 1) { return 'موظفٌ واحد'; }
                if ($n === 2) { return 'موظفان'; }
                if ($n >= 3 && $n <= 10) { return number_format($n).' موظفين'; }

                return number_format($n).' موظفاً';
            };
            $num = fn ($value) => number_format((float) $value);
        @endphp

        {{-- ==================== الفلترة ==================== --}}
        <div class="cd-toolbar">
            <h4 class="cd-title"><i class="feather icon-users"></i> لوحة تحكم الموظفين</h4>

            <div class="cd-crumbs">
                <span>الفترة المعروضة:</span>
                <strong>{{ $data['period']['from'] }} → {{ $data['period']['to'] }}</strong>
                <span class="cd-crumb-sep">·</span>
                <span>تسري على التعيينات وأيام الحضور والصرفات والطلبات — لا على عدّة الموظفين.</span>
            </div>

            <div class="cd-tabs">
                @foreach ([
                    '1' => 'اليوم',
                    '7' => 'آخر ٧ أيام',
                    '30' => 'هذا الشهر',
                    'prev_month' => 'الشهر الماضي',
                    '90' => 'آخر ٩٠ يوماً',
                    '365' => 'هذه السنة',
                    'custom' => 'مدّة مخصّصة',
                ] as $key => $label)
                    <button type="button" class="cd-tab {{ (string) $period === (string) $key ? 'active' : '' }}" data-period="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>

            <form method="GET" action="{{ route('hr.dashboard.index') }}" id="cdFilterForm">
                <input type="hidden" name="period" id="cdPeriod" value="{{ $period }}">

                <div class="cd-filters">
                    <div class="cd-field" id="cdCustomDates" style="{{ $period === 'custom' ? '' : 'display:none' }}">
                        <label for="cdStart">من</label>
                        <input type="date" class="form-control" name="start_date" id="cdStart" value="{{ request('start_date', $from->toDateString()) }}">
                    </div>
                    <div class="cd-field" id="cdCustomDatesTo" style="{{ $period === 'custom' ? '' : 'display:none' }}">
                        <label for="cdEnd">إلى</label>
                        <input type="date" class="form-control" name="end_date" id="cdEnd" value="{{ request('end_date', $to->toDateString()) }}">
                    </div>

                    <div class="cd-field">
                        <label for="cdDepartment">القسم</label>
                        <select name="department_id" id="cdDepartment" class="form-control">
                            <option value="">كل الأقسام</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cd-field">
                        <label for="cdJobTitle">المسمّى الوظيفي</label>
                        <select name="job_title_id" id="cdJobTitle" class="form-control">
                            <option value="">كل المسمّيات</option>
                            @foreach ($jobTitles as $jobTitle)
                                <option value="{{ $jobTitle->id }}" @selected((string) ($filters['job_title_id'] ?? '') === (string) $jobTitle->id)>{{ $jobTitle->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cd-field">
                        <label for="cdBranch">الفرع</label>
                        <select name="branch_id" id="cdBranch" class="form-control">
                            <option value="">كل الفروع</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cd-field">
                        <label for="cdStatus">الحالة الوظيفية</label>
                        <select name="employee_status" id="cdStatus" class="form-control">
                            <option value="">كل الحالات</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($filters['employee_status'] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="cd-apply"><i class="feather icon-filter"></i> تطبيق</button>
                    <a href="{{ route('hr.dashboard.index') }}" class="cd-reset"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                </div>
            </form>
        </div>

        {{-- ==================== المؤشرات ==================== --}}
        @php
            $kpiRow1 = [
                ['label' => 'إجمالي الموظفين', 'value' => $num($force['total']), 'icon' => 'users', 'color' => 'var(--cd-primary)', 'segment' => 'force.total',
                    'foot' => 'كلُّ موظفٍ غيرِ مؤرشَف في نطاقك — بأيّ حالة'],
                ['label' => 'على رأس العمل', 'value' => $num($force['active']), 'icon' => 'user-check', 'color' => 'var(--cd-success)', 'segment' => 'force.active',
                    'foot' => 'نشط وتحت التجربة — ومن هو موقوفٌ أو غيرُ نشطٍ أو انتهت خدمتُه خارجَهم'],
                ['label' => 'تعيينات الفترة', 'value' => $num($move['hires']), 'icon' => 'user-plus', 'color' => 'var(--cd-info)', 'segment' => 'move.hires',
                    'foot' => 'تاريخُ التحاقهم داخل الفترة'],
                ['label' => 'مغادرو الفترة', 'value' => $num($move['leavers']), 'icon' => 'user-minus', 'color' => 'var(--cd-danger)', 'segment' => 'move.leavers',
                    'foot' => 'صافي الحركة '.($move['net'] >= 0 ? '+' : '').$move['net'].' موظف'],
                ['label' => 'معدّل الدوران', 'value' => $move['turnover_rate'].'%', 'icon' => 'repeat', 'color' => 'var(--cd-warning)', 'segment' => 'move.leavers',
                    'foot' => 'المغادرون ÷ متوسّط العدّة في الفترة'],
                ['label' => 'ملفّاتٌ ناقصة', 'value' => $num($ready['employees_with_gaps']), 'icon' => 'alert-triangle', 'color' => 'var(--cd-danger)', 'segment' => 'ready.any',
                    'foot' => 'من هو على رأس العمل وينقص ملفَّه حقلٌ على الأقلّ — بمجموع '.$num($ready['total']).' فراغ'],
            ];

            $kpiRow2 = [
                ['label' => 'رواتبُ صُرفت', 'value' => $pay['net_label'], 'icon' => 'dollar-sign', 'color' => 'var(--cd-success)', 'segment' => 'pay.disbursed',
                    'foot' => $num($pay['disbursed']).' صرفة في الفترة — متوسّط '.$money($pay['average_net'])],
                ['label' => 'صرفاتٌ معلَّقة', 'value' => $money($pay['pending_total']), 'icon' => 'clock', 'color' => 'var(--cd-warning)', 'segment' => 'pay.pending',
                    'foot' => $num($pay['pending']).' صرفة لم تُصرف بعد'],
                ['label' => 'خصمياتُ الفترة', 'value' => $ded['period_label'], 'icon' => 'minus-circle', 'color' => 'var(--cd-danger)', 'segment' => 'ded.period',
                    'foot' => $num($ded['period']).' خصمية — دون الملغاة والمرفوضة'],
                ['label' => 'سلفٌ قائمة', 'value' => $adv['outstanding_label'], 'icon' => 'credit-card', 'color' => 'var(--cd-info)', 'segment' => 'adv.repaying',
                    'foot' => $num($adv['repaying']).' سلفة في دورة السداد — المتبقّي'],
                ['label' => 'سلفٌ مقتطعة', 'value' => $money($pay['advances_total']), 'icon' => 'corner-up-left', 'color' => 'var(--cd-muted)', 'segment' => 'pay.disbursed',
                    'foot' => 'اقتُطعت من رواتب الفترة المصروفة'],
                ['label' => 'بدلاتٌ وإضافات', 'value' => $money($pay['additions_total']), 'icon' => 'plus-circle', 'color' => 'var(--cd-primary)', 'segment' => 'pay.disbursed',
                    'foot' => 'أُضيفت إلى رواتب الفترة المصروفة'],
            ];
        @endphp

        @foreach ([$kpiRow1, $kpiRow2] as $kpiRow)
            <div class="row">
                @foreach ($kpiRow as $kpi)
                    <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-1">
                        <div class="cd-kpi" data-segment="{{ $kpi['segment'] }}" role="button" tabindex="0">
                            <div class="cd-kpi-top">
                                <div class="cd-kpi-icon" style="background: color-mix(in srgb, {{ $kpi['color'] }} 14%, transparent);">
                                    <i class="feather icon-{{ $kpi['icon'] }}" style="color: {{ $kpi['color'] }};"></i>
                                </div>
                                <span class="cd-kpi-open"><i class="feather icon-list"></i> الموظفون</span>
                            </div>
                            <p class="cd-kpi-label">{{ $kpi['label'] }}</p>
                            <p class="cd-kpi-value">{{ $kpi['value'] }}</p>
                            <div class="cd-kpi-foot">{{ $kpi['foot'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- ==================== حضور اليوم · الحالة الوظيفية · الجاهزية ==================== --}}
        <div class="row">
            <div class="col-xl-4 col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>حضور اليوم</h4>
                            <small>{{ now()->translatedFormat('l j F Y') }}</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            كلُّ موظفٍ قائمٍ يقع في خانةٍ واحدة من <code>attendance_days</code> ليومِ اليوم:
                            «حاضر» و«متأخر» و«غائب» و«نصف يوم» من <code>status</code>، و«في إجازة» و«عطلة» من علامتَي الصفّ.
                            و<strong>«لم يُسجَّل»</strong> موظفٌ لا صفَّ له إطلاقاً — لم يسجّل ولم تُعالَج حالتُه بعد،
                            وهو ما كانت اللوحةُ القديمة تُخفيه فيبدو اليومُ مكتملاً.
                            والعدّةُ هنا <strong>من هو على رأس العمل</strong> وحدَه (نشطٌ أو تحت التجربة)؛ فالموقوفُ وغيرُ النشط ومن انتهت خدمتُه لا يُنتظر منهم حضور، وإدخالُهم كان يضخّم «لم يُسجَّل».
                        </div>

                        <div id="hdTodayChart"></div>

                        <ul class="cd-seg-list">
                            @foreach ($todayStats['rows'] as $row)
                                <li data-segment="today.{{ $row['key'] }}" role="button" tabindex="0">
                                    <span class="cd-seg-dot" style="background: {{ $row['color'] }}"></span>
                                    <span class="cd-seg-label">{{ $row['label'] }}</span>
                                    <span class="cd-seg-val">{{ $num($row['count']) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="cd-subhead">تحتاج انتباهاً <small>لا تدخل المخطَّط</small></div>
                        <ul class="cd-seg-list">
                            <li data-segment="today.not_recorded" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: #b8bcc8"></span>
                                <span class="cd-seg-label">لم يُسجَّل لهم يومٌ بعد<small>موظفٌ قائمٌ بلا صفِّ حضورٍ اليوم</small></span>
                                <span class="cd-seg-val">{{ $num($todayStats['not_recorded']) }}</span>
                            </li>
                            <li data-segment="today.open" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: var(--cd-info)"></span>
                                <span class="cd-seg-label">دوامٌ مفتوح<small>سجّل دخولَه ولم ينصرف</small></span>
                                <span class="cd-seg-val">{{ $num($todayStats['open']) }}</span>
                            </li>
                        </ul>

                        @if ($todayStats['attendance_rate'] !== null)
                            <div class="cd-range-note">نسبةُ الحضور اليوم <strong>{{ $todayStats['attendance_rate'] }}%</strong> — من حضر (ولو متأخراً) ÷ من كان يومُه يومَ عمل.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>القوى العاملة بالحالة</h4>
                            <small>لقطةٌ الآن — لا تتأثّر بالفترة</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            من <code>employees.status</code> مباشرةً: نشط، تحت التجربة، موقوف، مستقيل، منتهي الخدمة، غير نشط.
                            والمجموع = إجمالي الموظفين غير المؤرشَفين في نطاقك، فلا موظفَ خارج الخانات.
                        </div>

                        <div id="hdStatusChart"></div>

                        <ul class="cd-seg-list">
                            @foreach ($force['rows'] as $row)
                                <li data-segment="force.{{ $row['key'] }}" role="button" tabindex="0">
                                    <span class="cd-seg-dot" style="background: {{ $row['color'] }}"></span>
                                    <span class="cd-seg-label">{{ $row['label'] }}</span>
                                    <span class="cd-seg-val">{{ $num($row['count']) }}</span>
                                    <span class="cd-seg-pct">{{ $force['total'] > 0 ? round($row['count'] / $force['total'] * 100, 1) : 0 }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-12 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>جاهزية الملفّات</h4>
                            <small>فراغٌ يُعطّل عملاً لاحقاً</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            على من هم على رأس العمل وحدَهم. كلُّ سطرٍ حقلٌ فارغٌ في الملفّ
                            وأثرُه مكتوبٌ تحته — فليس تذكيراً بالكمال بل بما سيتعثّر: اعتمادُ إجازةٍ بلا مديرٍ مباشر،
                            وقيدُ راتبٍ بلا حساب ذمّة، وحضورٌ يُقاس على بدايةٍ افتراضية بلا وردية.
                        </div>

                        <ul class="cd-seg-list">
                            @foreach ($ready['rows'] as $row)
                                <li data-segment="ready.{{ $row['key'] }}" role="button" tabindex="0">
                                    <span class="cd-seg-dot" style="background: {{ $row['count'] > 0 ? 'var(--cd-warning)' : 'var(--cd-success)' }}"></span>
                                    <span class="cd-seg-label">{{ $row['label'] }}<small>{{ \Illuminate\Support\Str::limit($row['hint'], 95) }}</small></span>
                                    <span class="cd-seg-val">{{ $num($row['count']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== الحضور في الفترة · الإجازات والسلف ==================== --}}
        <div class="row">
            <div class="col-xl-8 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>الحضور خلال الفترة</h4>
                            <small>{{ $data['period']['from'] }} → {{ $data['period']['to'] }}</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            تُجمَع أيامُ <code>attendance_days</code> الواقعة في الفترة، <strong>دون أيام الإجازة والعطل</strong>.
                            «أيام التأخير» ما كان <code>late_minutes &gt; 0</code>، و«الغياب» <code>status = absent</code>،
                            و«الإضافي» مجموعُ <code>overtime_minutes</code>. والمدّةُ تُقرأ بالساعات والدقائق لا بعددٍ خام.
                        </div>

                        <div class="row">
                            <div class="col-6 col-md-3 cd-mini-stat" data-segment="att.late_employees" role="button" tabindex="0">
                                <div class="val">{{ $num($att['late_employees']) }}</div>
                                <div class="lbl">موظفون تأخّروا</div>
                            </div>
                            <div class="col-6 col-md-3 cd-mini-stat" data-segment="att.absent_employees" role="button" tabindex="0">
                                <div class="val">{{ $num($att['absent_employees']) }}</div>
                                <div class="lbl">موظفون غابوا</div>
                            </div>
                            <div class="col-6 col-md-3 cd-mini-stat" data-segment="att.overtime_employees" role="button" tabindex="0">
                                <div class="val">{{ $num($att['overtime_employees']) }}</div>
                                <div class="lbl">لهم وقتٌ إضافي</div>
                            </div>
                            <div class="col-6 col-md-3 cd-mini-stat">
                                <div class="val">{{ $num($att['present_days']) }}</div>
                                <div class="lbl">أيام حضور</div>
                            </div>
                        </div>

                        <div class="cd-subhead">
                            مجاميعُ الفترة
                            <small>التأخير {{ $att['late_label'] }} · الانصراف المبكر {{ $att['early_label'] }} · الإضافي {{ $att['overtime_label'] }} · الغياب {{ $num($att['absent_days']) }} يوم</small>
                        </div>

                        @if ($att['top_late'])
                            <div class="table-responsive">
                                <table class="cd-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>الموظف</th>
                                            <th>القسم</th>
                                            <th>أيام التأخير</th>
                                            <th>مجموع التأخير</th>
                                            <th>أيام الغياب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($att['top_late'] as $i => $row)
                                            <tr>
                                                <td class="cd-num">{{ $i + 1 }}</td>
                                                <td><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['name'] }}</a></td>
                                                <td>{{ $row['department'] }}</td>
                                                <td class="cd-num">{{ $num($row['late_days']) }}</td>
                                                <td class="cd-num">{{ $row['late_label'] }}</td>
                                                <td class="cd-num">{{ $num($row['absent_days']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="cd-range-note">أكثرُ عشرةٍ تأخيراً في الفترة، بمجموع الدقائق.</div>
                        @else
                            <div class="cd-empty"><i class="feather icon-check-circle"></i>لا تأخيرَ مسجَّلٌ في هذه الفترة.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>ما ينتظر قراراً</h4>
                            <small>طلباتٌ معلَّقةٌ بأيّ تاريخ</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            المعلَّقُ لا تُخفيه الفترة: طلبٌ قُدِّم الشهرَ الماضي وما زال بانتظار الاعتماد يجب أن يُرى اليوم.
                            فهذه البطاقةُ وحدَها لا تتأثّر بالفترة المحددة أعلاه.
                        </div>

                        <div class="cd-notif-list">
                            @foreach ([
                                ['segment' => 'leave.pending', 'label' => 'إجازاتٌ بانتظار الاعتماد', 'count' => $leaves['pending'], 'icon' => 'calendar', 'color' => 'var(--cd-warning)'],
                                ['segment' => 'adv.pending', 'label' => 'سلفٌ بانتظار الاعتماد', 'count' => $adv['pending'], 'icon' => 'credit-card', 'color' => 'var(--cd-warning)'],
                                ['segment' => 'adv.awaiting_disbursement', 'label' => 'سلفٌ معتمدةٌ لم تُصرف', 'count' => $adv['awaiting_disbursement'], 'icon' => 'arrow-down-circle', 'color' => 'var(--cd-info)'],
                                ['segment' => 'ded.pending', 'label' => 'خصمياتٌ بانتظار الاعتماد', 'count' => $ded['pending'], 'icon' => 'minus-circle', 'color' => 'var(--cd-danger)'],
                                ['segment' => 'pay.pending', 'label' => 'صرفاتُ رواتبٍ معلَّقة', 'count' => $pay['pending'], 'icon' => 'clock', 'color' => 'var(--cd-primary)'],
                                ['segment' => 'contract.expiring', 'label' => 'عقودٌ تنتهي خلال ٣٠ يوماً', 'count' => $contracts['expiring'], 'icon' => 'file-text', 'color' => 'var(--cd-danger)'],
                            ] as $notif)
                                <div class="cd-notif-item" data-segment="{{ $notif['segment'] }}" role="button" tabindex="0">
                                    <div class="cd-notif-icon" style="background: color-mix(in srgb, {{ $notif['color'] }} 14%, transparent);">
                                        <i class="feather icon-{{ $notif['icon'] }}" style="color: {{ $notif['color'] }};"></i>
                                    </div>
                                    <div class="cd-notif-label">{{ $notif['label'] }}</div>
                                    <div class="cd-notif-count" style="color: {{ $notif['count'] > 0 ? $notif['color'] : 'var(--cd-muted)' }};">{{ $num($notif['count']) }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="cd-subhead">الإجازات في الفترة <small>معتمدة</small></div>
                        <ul class="cd-seg-list">
                            <li data-segment="leave.today" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: var(--cd-primary)"></span>
                                <span class="cd-seg-label">في إجازةٍ اليوم</span>
                                <span class="cd-seg-val">{{ $num($leaves['today']) }}</span>
                            </li>
                            <li data-segment="leave.approved_period" role="button" tabindex="0">
                                <span class="cd-seg-dot" style="background: var(--cd-info)"></span>
                                <span class="cd-seg-label">إجازاتٌ تقع في الفترة<small>{{ $leaves['days_in_period'] }} يوماً معتمداً</small></span>
                                <span class="cd-seg-val">{{ $num($leaves['approved_period']) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== التوزيع ==================== --}}
        <div class="row">
            @foreach ([
                ['axis' => 'department', 'id' => 'hdDepartmentChart', 'icon' => 'grid'],
                ['axis' => 'job_title', 'id' => 'hdJobTitleChart', 'icon' => 'tag'],
                ['axis' => 'branch', 'id' => 'hdBranchChart', 'icon' => 'map-pin'],
            ] as $card)
                @php $axis = $dist[$card['axis']]; @endphp
                <div class="col-xl-4 col-lg-6 col-12">
                    <div class="cd-card">
                        <div class="cd-card-head">
                            <div>
                                <h4>التوزيع حسب {{ $axis['label'] }}</h4>
                                <small>من هم على رأس العمل</small>
                            </div>
                            <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                        </div>
                        <div class="cd-card-body">
                            <div class="cd-info-panel" hidden>
                                من حقل «{{ $axis['label'] }}» في ملفّ الموظف، ومن انتهت خدمتُه مستثنى.
                                ومن لا قيمةَ له يقع في خانة «بلا …» — وهي نفسُها سطرٌ في «جاهزية الملفّات».
                            </div>

                            <div id="{{ $card['id'] }}"></div>

                            <ul class="cd-seg-list">
                                @foreach (array_slice($axis['rows'], 0, 8) as $row)
                                    <li data-segment="dist.{{ $row['key'] }}" role="button" tabindex="0">
                                        <span class="cd-seg-dot" style="background: var(--cd-primary)"></span>
                                        <span class="cd-seg-label">{{ $row['label'] }}</span>
                                        <span class="cd-seg-val">{{ $num($row['count']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ==================== العقود ==================== --}}
        <div class="row">
            <div class="col-12">
                <div class="cd-card">
                    <div class="cd-card-head">
                        <div>
                            <h4>العقود</h4>
                            <small>أحدثُ عقدٍ لكلّ موظف — {{ $employees($contracts['covered']) }} لهم عقد من أصل {{ $num($force['total']) }}</small>
                        </div>
                        <button type="button" class="cd-info-toggle" data-cd-info-toggle aria-label="كيف يُحسب؟"><i class="feather icon-help-circle"></i></button>
                    </div>
                    <div class="cd-card-body">
                        <div class="cd-info-panel" hidden>
                            كلُّ موظفٍ يُصنّف بـ<strong>أحدثِ عقدٍ له</strong> لا بكلّ عقوده؛ فمن جُدّد عقدُه
                            لا يظهر في «منتهية» بسبب عقده القديم.
                            والخاناتُ تشمل <strong>كلّ من له عقد</strong> بأيّ حالةٍ وظيفية — فعقدٌ منتهٍ
                            لموظفٍ موقوفٍ أولى ما يُنظر فيه، وعمودُ «الحالة» في القائمة يقول
                            أيُّهم على رأس العمل.
                            والنهايةُ تُقرأ من <code>end_date</code>، وإن خلا فمن المدّة
                            (<code>type_contract = 1</code> مع <code>duration</code>) — وعقدٌ بلا نهايةٍ ولا مدّةٍ
                            <strong>غيرُ محدّد المدّة</strong>، لا عقدٌ ناقص. وما لم يُعتمد لا يُعَدُّ سارياً.
                            أمّا «بلا عقد» فعلى من هو على رأس العمل وحدَه (لا معنى لمطالبة من انتهت خدمتُه بعقد)، ولا صفَّ له في <code>contracts</code> إطلاقاً.
                        </div>

                        <div class="row">
                            @foreach ([
                                ['key' => 'expiring', 'label' => 'تنتهي خلال ٣٠ يوماً', 'color' => 'var(--cd-danger)'],
                                ['key' => 'expired', 'label' => 'منتهيةٌ لم تُجدّد', 'color' => 'var(--cd-warning)'],
                                ['key' => 'running', 'label' => 'ساريةٌ بمدّةٍ باقية', 'color' => 'var(--cd-success)'],
                                ['key' => 'open', 'label' => 'غيرُ محدّدة المدّة', 'color' => 'var(--cd-info)'],
                                ['key' => 'unapproved', 'label' => 'بانتظار الاعتماد', 'color' => 'var(--cd-primary)'],
                                ['key' => 'none', 'label' => 'موظفون بلا عقد', 'color' => 'var(--cd-muted)'],
                            ] as $box)
                                <div class="col-6 col-md-2 cd-mini-stat" data-segment="contract.{{ $box['key'] }}" role="button" tabindex="0">
                                    <div class="val" style="color: {{ $contracts[$box['key']] > 0 ? $box['color'] : 'var(--cd-muted)' }}">{{ $num($contracts[$box['key']]) }}</div>
                                    <div class="lbl">{{ $box['label'] }}</div>
                                </div>
                            @endforeach
                        </div>

                        @if ($contracts['expiring_rows'])
                            <div class="cd-subhead">أقربُ ما ينتهي</div>
                            <div class="table-responsive">
                                <table class="cd-table">
                                    <thead>
                                        <tr><th>الموظف</th><th>رقم العقد</th><th>القسم</th><th>البداية</th><th>النهاية</th><th>المتبقّي</th><th>القيمة</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($contracts['expiring_rows'] as $row)
                                            <tr>
                                                <td><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['name'] }}</a></td>
                                                <td>{{ $row['contract_code'] }}</td>
                                                <td>{{ $row['department'] }}</td>
                                                <td class="cd-num">{{ $row['start_date'] }}</td>
                                                <td class="cd-num">{{ $row['end_label'] }}</td>
                                                <td class="cd-num">{{ $row['days_left'] }} يوم</td>
                                                <td class="cd-num">{{ $money($row['amount']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== اللوحة الجانبية: موظفو الشريحة ==================== --}}
        <div class="cd-drawer-backdrop" id="cdDrawerBackdrop" data-cd-drawer-close hidden></div>
        <aside class="cd-drawer" id="cdDrawer" role="dialog" aria-modal="true" aria-labelledby="cdDrawerTitle" hidden>
            <header class="cd-drawer-head">
                <div>
                    <h5 id="cdDrawerTitle"></h5>
                    <p id="cdDrawerHint"></p>
                </div>
                <button type="button" class="cd-drawer-close" data-cd-drawer-close aria-label="إغلاق">&times;</button>
            </header>
            <div class="cd-drawer-tools">
                <input type="search" id="cdDrawerSearch" placeholder="بحث بالاسم أو الرقم أو القسم…" autocomplete="off">
                <span class="cd-drawer-count" id="cdDrawerCount"></span>
                <button type="button" class="cd-reset" id="cdDrawerCsv"><i class="feather icon-download"></i> تصدير CSV</button>
            </div>
            <div class="cd-drawer-body" id="cdDrawerBody"></div>
        </aside>

    </section>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('hr-dashboard');
    if (!root) { return; }

    // ==================== «كيف يُحسب؟» ====================
    root.querySelectorAll('[data-cd-info-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = btn.closest('.cd-card').querySelector('.cd-info-panel');
            if (panel) { panel.hidden = !panel.hidden; }
        });
    });

    // ==================== أزرار الفترة ====================
    root.querySelectorAll('.cd-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var period = btn.getAttribute('data-period');
            document.getElementById('cdPeriod').value = period;
            var isCustom = period === 'custom';
            document.getElementById('cdCustomDates').style.display = isCustom ? '' : 'none';
            document.getElementById('cdCustomDatesTo').style.display = isCustom ? '' : 'none';
            if (!isCustom) { document.getElementById('cdFilterForm').submit(); }
        });
    });

    // ==================== قائمة موظفي الشريحة ====================
    var segmentUrl = @json(route('hr.dashboard.segment'));
    /*
     * فلاترُ الشاشة كما تُرسَل مع كلّ طلب شريحة.
     *
     * **الفارغُ يُحذَف هنا لا يُمرّر**: وسيطُ `ConvertEmptyStringsToNull`
     * يحوّل `department_id=` إلى `null`، ثمّ `new URLSearchParams({a: null})`
     * يكتبها نصّاً `a=null` — فيصل الخادمَ فلترٌ قيمتُه «null»،
     * فيستعلم `where('department_id', 'null')` وتعود كلُّ شريحةٍ صفراً.
     * وهذا هو سببُ أنّ كلّ قائمةٍ كانت تُفتح فارغةً على الخادم الحيّ.
     */
    var dashboardFilters = (function (raw) {
        var clean = {};
        Object.keys(raw || {}).forEach(function (key) {
            var value = raw[key];
            if (value === null || value === undefined || value === '' || Array.isArray(value) || typeof value === 'object') { return; }
            clean[key] = String(value);
        });
        return clean;
    })(@json(request()->except(['segment'])));
    var drawer = document.getElementById('cdDrawer');
    var backdrop = document.getElementById('cdDrawerBackdrop');
    var drawerBody = document.getElementById('cdDrawerBody');
    var drawerSearch = document.getElementById('cdDrawerSearch');
    var drawerCount = document.getElementById('cdDrawerCount');
    var PAGE = 200;
    var state = { data: null, filtered: [], shown: PAGE, request: 0, trigger: null };

    var esc = function (value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var money = function (v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    var cell = function (col, row) {
        var v = row[col.key];
        switch (col.type) {
            case 'client': return '<a href="' + esc(row.url) + '" target="_blank" rel="noopener">' + esc(v) + '</a>';
            case 'status': {
                var tone = row[col.key + '_color'] || row.status_color || '#999999';
                return '<span class="cd-badge" style="background: color-mix(in srgb, ' + esc(tone) + ' 16%, transparent); color: ' + esc(tone) + ';">' + esc(v) + '</span>';
            }
            case 'money': return '<span class="cd-num">' + money(v) + '</span>';
            case 'number': return '<span class="cd-num">' + Number(v || 0).toLocaleString('en-US') + '</span>';
            /* المتبقّي السالب «مضى»، لا «−277 يوم» */
            case 'days': {
                if (v === null || v === undefined || v === '') { return '—'; }
                var n = Number(v);
                if (!isFinite(n)) { return esc(v); }
                if (n < 0) { return 'انتهى منذ <span class="cd-num">' + Math.abs(n).toLocaleString('en-US') + '</span> يوماً'; }
                if (n === 0) { return 'ينتهي اليوم'; }
                return '<span class="cd-num">' + n.toLocaleString('en-US') + '</span> يوم';
            }
            case 'date': return v ? '<span class="cd-num">' + esc(v) + '</span>' : '—';
            case 'longtext': return v ? '<div class="cd-long">' + esc(v) + '</div>' : '—';
            default: return v || v === 0 ? esc(v) : '—';
        }
    };

    /* قائمةٌ فارغة: الأسبابُ ثلاثةٌ — النطاق، أو الفلتر، أو أنّ الشريحة بلا سجلّ */
    var whyEmpty = function (ctx) {
        if (!ctx) { return ''; }
        var lines = [];
        if (ctx.visible === 0) {
            lines.push(ctx.scope === 0
                ? 'نطاقُ رؤيتك لا يشمل أيَّ موظف — راجع نطاق البيانات في دورك.'
                : 'لا موظفَ يطابق فلاتر الشاشة الحالية.');
        } else {
            lines.push('اللوحةُ ترى ' + Number(ctx.visible).toLocaleString('en-US') + ' موظفاً، فالفراغُ هنا يعني أنّ هذه الشريحة بلا سجلّ.');
        }
        if (ctx.filters && ctx.filters.length) { lines.push('فلاترُ مطبَّقة: ' + ctx.filters.join('، ') + '.'); }
        if (ctx.period) { lines.push('الفترة: ' + ctx.period + '.'); }
        return '<div class="cd-empty-why">' + lines.map(esc).join('<br>') + '</div>';
    };

    var renderRows = function () {
        var data = state.data;
        if (!data) { return; }
        drawerCount.textContent = state.filtered.length === data.rows.length
            ? data.rows.length.toLocaleString('en-US') + ' موظف'
            : state.filtered.length.toLocaleString('en-US') + ' من ' + data.rows.length.toLocaleString('en-US') + ' موظف';

        if (!state.filtered.length) {
            drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-users"></i>'
                + (data.rows.length ? 'لا نتائج للبحث.' : 'لا يوجد موظفون في هذه الشريحة.')
                + (data.rows.length ? '' : whyEmpty(data.context))
                + '</div>';
            return;
        }

        var head = '<tr><th>#</th>' + data.columns.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('') + '</tr>';
        var body = state.filtered.slice(0, state.shown).map(function (row, i) {
            return '<tr><td class="cd-num">' + (i + 1) + '</td>' + data.columns.map(function (c) { return '<td>' + cell(c, row) + '</td>'; }).join('') + '</tr>';
        }).join('');
        var more = state.filtered.length > state.shown
            ? '<button type="button" class="cd-reset cd-drawer-more" id="cdDrawerMore">عرض المزيد (' + (state.filtered.length - state.shown).toLocaleString('en-US') + ' متبقٍّ)</button>'
            : '';

        drawerBody.innerHTML = '<div class="table-responsive"><table class="cd-table"><thead>' + head + '</thead><tbody>' + body + '</tbody></table></div>' + more;
    };

    var applySearch = function () {
        var q = drawerSearch.value.trim().toLowerCase();
        state.shown = PAGE;
        state.filtered = !q ? state.data.rows.slice() : state.data.rows.filter(function (r) {
            return [r.name, r.code, r.department, r.job_title, r.branch, r.status].some(function (x) {
                return String(x || '').toLowerCase().indexOf(q) !== -1;
            });
        });
        renderRows();
    };

    var openDrawer = function () {
        drawer.hidden = false;
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    var closeDrawer = function () {
        drawer.hidden = true;
        backdrop.hidden = true;
        document.body.style.overflow = '';
        state.request++;
        if (state.trigger && document.contains(state.trigger)) { state.trigger.focus(); }
    };

    var openSegment = function (segment, trigger) {
        state.trigger = trigger || null;
        state.data = null;
        var requestId = ++state.request;

        document.getElementById('cdDrawerTitle').textContent = 'جارٍ التحميل…';
        document.getElementById('cdDrawerHint').textContent = '';
        drawerCount.textContent = '';
        drawerSearch.value = '';
        drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-loader"></i>جارٍ جلب الموظفين…</div>';
        openDrawer();

        var params = new URLSearchParams(dashboardFilters);
        params.set('segment', segment);

        fetch(segmentUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
            .then(function (data) {
                if (requestId !== state.request) { return; }
                state.data = data;
                document.getElementById('cdDrawerTitle').textContent = data.title;
                document.getElementById('cdDrawerHint').textContent = data.hint || '';
                applySearch();
            })
            .catch(function () {
                if (requestId !== state.request) { return; }
                document.getElementById('cdDrawerTitle').textContent = 'تعذّر التحميل';
                drawerBody.innerHTML = '<div class="cd-empty"><i class="feather icon-alert-triangle"></i>تعذّر جلب القائمة — أعد المحاولة.</div>';
            });
    };

    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-cd-drawer-close]')) { closeDrawer(); return; }
        if (e.target.closest('#cdDrawerMore')) { state.shown += PAGE; renderRows(); return; }

        var link = e.target.closest('a[href]');
        var el = e.target.closest('[data-segment]');
        if (!el || (link && el.contains(link) && link !== el)) { return; }
        e.preventDefault();
        openSegment(el.getAttribute('data-segment'), el);
    });

    root.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-segment]')) {
            e.preventDefault();
            openSegment(e.target.getAttribute('data-segment'), e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !drawer.hidden) { closeDrawer(); }
    });

    drawerSearch.addEventListener('input', function () { if (state.data) { applySearch(); } });

    document.getElementById('cdDrawerCsv').addEventListener('click', function () {
        if (!state.data || !state.filtered.length) { return; }
        var cols = state.data.columns;
        var quote = function (v) { return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; };
        var lines = [cols.map(function (c) { return quote(c.label); }).join(',')];
        state.filtered.forEach(function (row) {
            lines.push(cols.map(function (c) { return quote(row[c.key]); }).join(','));
        });
        var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (state.data.title || 'employees').replace(/[\\/:*?"<>|]/g, '-') + '.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
    });

    // ==================== المخطَّطات ====================
    if (typeof ApexCharts === 'undefined') { return; }

    var probe = document.createElement('span');
    probe.style.display = 'none';
    root.appendChild(probe);
    var SENTINEL = 'rgb(1, 2, 3)';
    var color = function (name, fallback) {
        probe.style.color = SENTINEL;
        probe.style.color = 'var(' + name + ')';
        var resolved = getComputedStyle(probe).color;
        return (!resolved || resolved === SENTINEL) ? fallback : resolved;
    };

    var palette = {
        primary: color('--cd-primary', '#7367f0'),
        success: color('--cd-success', '#28c76f'),
        danger: color('--cd-danger', '#ea5455'),
        warning: color('--cd-warning', '#ff9f43'),
        info: color('--cd-info', '#00cfe8'),
        muted: color('--cd-muted', '#7b8194'),
        font: getComputedStyle(root).fontFamily || 'Cairo, sans-serif'
    };

    var base = {
        chart: { fontFamily: palette.font, toolbar: { show: false } },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '12px', labels: { colors: palette.muted } }
    };

    var cycle = [palette.primary, palette.info, palette.success, palette.warning, palette.danger, palette.muted, '#20c997', '#6f42c1', '#fd7e14', '#0dcaf0'];

    /* دونات قابلة للنقر: كل جزءٍ يفتح `prefix.key` — نفسُ سلوك لوحة العملاء */
    var donut = function (selector, rows, opts) {
        var el = document.querySelector(selector);
        if (!el) { return; }

        var series = rows.map(function (r) { return Number(r.count) || 0; });
        if (!series.some(function (v) { return v > 0; })) {
            el.innerHTML = '<div class="cd-empty"><i class="feather icon-pie-chart"></i>' + (opts.emptyText || 'لا توجد بيانات ضمن هذا النطاق.') + '</div>';
            return;
        }

        var open = function (index) {
            var row = rows[index];
            if (row && row.key !== undefined) { openSegment(opts.prefix + '.' + row.key); }
        };

        new ApexCharts(el, Object.assign({}, base, {
            chart: Object.assign({}, base.chart, {
                type: 'donut', height: 250,
                events: {
                    dataPointSelection: function (event, ctx, config) { open(config.dataPointIndex); },
                    legendClick: function (ctx, index) { open(index); }
                }
            }),
            series: series,
            labels: rows.map(function (r) { return r.label; }),
            colors: rows.map(function (r, i) { return r.color || cycle[i % cycle.length]; }),
            legend: Object.assign({}, base.legend, { show: opts.legend !== false, onItemClick: { toggleDataSeries: false } }),
            tooltip: { y: { formatter: function (v) { return Number(v).toLocaleString('en-US'); } } },
            states: { active: { filter: { type: 'none' } } },
            plotOptions: {
                pie: { expandOnClick: false, donut: { labels: { show: true, total: {
                    show: true, label: opts.totalLabel || 'موظف', fontSize: '13px', color: palette.muted,
                    formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0).toLocaleString('en-US'); }
                } } } }
            }
        })).render();
    };

    donut('#hdTodayChart', @json(array_values($todayStats['rows'])), {
        prefix: 'today', legend: false,
        emptyText: 'لم تُعالَج حالةُ أيِّ موظفٍ اليوم بعد — انظر «لم يُسجَّل لهم يومٌ بعد» أدناه.'
    });
    donut('#hdStatusChart', @json(array_values($force['rows'])), { prefix: 'force', legend: false });
    donut('#hdDepartmentChart', @json(array_values($dist['department']['rows'])), { prefix: 'dist', legend: false });
    donut('#hdJobTitleChart', @json(array_values($dist['job_title']['rows'])), { prefix: 'dist', legend: false });
    donut('#hdBranchChart', @json(array_values($dist['branch']['rows'])), { prefix: 'dist', legend: false });
});
</script>
@endsection
