{{--
    فتحُ جلسة — العهدةُ تبدأ هنا.

    ═══════════════ ما كان ═══════════════

    • **ترميزٌ خاصّ**: `content-header row` مكتوبةً بيدها، وثلاثُ بطاقات
      `stat-card` بألوان `bg-rgba-*` لا تتبع سمة النظام، تعرض اسمَ الموظف
      والتاريخَ والوقت — معلوماتٌ يعرفها المستخدم أصلاً وتشغل ثلثَ الشاشة.

    • **أخطاءُ التحقّق ورسائلُ الجلسة مكتوبةٌ في الشاشة** وقد صارت في
      `master` — فتظهر مرّتين.

    • **تنبيهٌ ثابت** «لا يمكنك إنشاء جلسة إذا كانت لديك جلسة نشطة» يُعرض
      دائماً — والمتحكّم يحوّل صاحبَ الجلسة النشطة إلى جلسته قبل أن تُعرض
      الشاشةُ أصلاً. فهو تحذيرٌ من حالةٍ لا يمكن أن يكون فيها من يقرؤه.

    • **`$isManager` غيرُ مُمرَّر** من المتحكّم — `isset()` تُخفي ذلك، فاسمُ
      صاحب الوردية لا يظهر أبداً.

    • **تهيئةُ select2 مكرَّرة**: `master` يهيّئها لكل `.select2`، وهذه
      تهيّئها ثانيةً.

    ═══════════════ والتصميم ═══════════════

    نظامُ `stk-` نفسُه المستعمل في بقيّة الوحدة: ترويسةٌ مكوّنة، وأرقامٌ
    `stk-metric` تقول ما **يلزم لفتح الجلسة** لا ما يعرفه المستخدم، وذيلُ
    نموذجٍ موحَّد.
--}}
@extends('pos::master')

@section('title', 'بدء جلسة جديدة')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $limit = app(SubscriptionGate::class)->limit(PosPlanFeatures::LIMIT_SESSIONS);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    $ready = $devices->isNotEmpty() && $shifts->isNotEmpty();
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> العودة للجلسات
        </a>
        @if ($ready)
            <button type="submit" form="sessionForm" class="btn btn-primary">
                <i class="feather icon-play"></i> بدء الجلسة
            </button>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'بدء جلسة جديدة',
        'icon' => 'play-circle',
        'sub' => 'الجلسةُ عهدةٌ: تُفتح برصيدٍ افتتاحي، ويُنسب إليها كلُّ بيعٍ وحركةِ درج',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الجلسات' => route('pos.sessions.index'),
            'جلسة جديدة' => null,
        ],
    ])

    @if (! $ready)
        {{--
            ═══════════ الطريقُ لا الاعتذار ═══════════

            كان هنا تنبيهٌ أحمر يقول «يرجى التواصل مع المدير لربط جهاز
            بحسابك» — نصٌّ لا زر. ومن يقرؤه لا يعرف أين تُربط الأجهزة.
            وأسوأُ منه أنّ **المديرَ نفسه** كان يرى الرسالةَ ذاتها.
        --}}
        <div class="alert alert-warning">
            <h5 class="mb-1"><i class="feather icon-alert-circle"></i> لا يمكن فتحُ جلسةٍ بعد</h5>
            <p class="mb-0">
                الجلسةُ تُنسب إلى جهازِ كاشيرٍ ووردية، وينقص
                @if ($devices->isEmpty() && $shifts->isEmpty())
                    كلاهما.
                @elseif ($devices->isEmpty())
                    الجهاز.
                @else
                    الوردية.
                @endif
            </p>

            <x-create-first :count="$devices->count()" route="pos.settings.devices.create"
                permission="pos.devices.manage"
                :plan="\App\Constants\planFeatures\PosPlanFeatures::DEVICES"
                label="جهاز كاشير"
                hint="لا جهازَ مرتبطٌ بك." />

            <x-create-first :count="$shifts->count()" route="pos.settings.shift.create"
                permission="pos.shifts.manage"
                :plan="\App\Constants\planFeatures\PosPlanFeatures::DEVICES"
                label="وردية"
                hint="لا وردية مرتبطةٌ بك." />
        </div>
    @else
        {{--
            الأرقامُ تقول ما **يلزم لفتح الجلسة**، لا اسمَ الموظف والتاريخ
            والوقت — وهي ثلاثةٌ يعرفها من يقرأها ولا تُغيّر قراره.
        --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-monitor"></i></span>
                <span class="stk-metric__label">أجهزةٌ متاحة لك</span>
                <span class="stk-metric__value">{{ $devices->count() }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">ورديّاتٌ متاحة لك</span>
                <span class="stk-metric__value">{{ $shifts->count() }}</span>
            </div>

            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">عملة الجلسة</span>
                {{-- `defaultCurrency()` تُرجع الرمزَ نصّاً لا كائناً --}}
                <span class="stk-metric__value">{{ $currency ?: $symbol }}</span>
            </div>

            @if ($limit['known'] && ! $limit['unlimited'])
                <div class="stk-metric {{ $limit['exhausted'] ? 'stk-metric--danger' : '' }}">
                    <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                    <span class="stk-metric__label">حدّ الباقة</span>
                    <span class="stk-metric__value">
                        {{ (int) $limit['used'] }} / {{ (int) $limit['allowance'] }}
                    </span>
                </div>
            @endif
        </div>

        <form action="{{ route('pos.session.store') }}" method="POST" id="sessionForm">
            @csrf

            <div class="card">
                <div class="card-body">
                    <div class="stk-filters__head">
                        <h6 class="stk-filters__title">
                            <i class="feather icon-play-circle"></i> بيانات الجلسة
                            @if ($limitBadge)
                                <span class="stk-plan-badge {{ $limitBadge['full'] ? 'stk-plan-badge--full' : 'stk-plan-badge--warn' }}"
                                      title="{{ $limitBadge['title'] }}">{{ $limitBadge['text'] }}</span>
                            @endif
                        </h6>
                    </div>

                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="device_id" class="stk-req">الجهاز</label>
                                <select id="device_id" name="device_id"
                                        class="form-control select2 @error('device_id') is-invalid @enderror" required>
                                    <option value="">اختر الجهاز…</option>
                                    @foreach ($devices as $device)
                                        <option value="{{ $device->id }}"
                                            @selected(old('device_id') == $device->id)>
                                            {{ $device->device_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <p class="stk-help mb-0">منه يُخصَم المبيعُ — ومستودعُه هو مستودعُ الجلسة.</p>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="shift_id" class="stk-req">الوردية</label>
                                <select id="shift_id" name="shift_id"
                                        class="form-control select2 @error('shift_id') is-invalid @enderror" required>
                                    <option value="">اختر الوردية…</option>
                                    @foreach ($shifts as $shift)
                                        @php $blocked = $shift->window_block ?? null; @endphp

                                        {{--
                                            الخيارُ الذي سيُرفض يظهر معطَّلاً وبسببه.

                                            كان يُعرض كأيّ خيار، فيختاره المستخدمُ
                                            ويملأ الرصيدَ الافتتاحي ثم يُردّ برسالة.
                                        --}}
                                        <option value="{{ $shift->id }}"
                                            @selected(old('shift_id') == $shift->id)
                                            @disabled($blocked)
                                            @if ($blocked) title="{{ $blocked }}" @endif>
                                            {{ $shift->name }}
                                            @if ($shift->start_time && $shift->end_time)
                                                ({{ substr($shift->start_time, 0, 5) }} — {{ substr($shift->end_time, 0, 5) }})
                                            @endif
                                            @if ($blocked) — خارج وقتها الآن @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('shift_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                @php $anyBlocked = collect($shifts)->contains(fn ($s) => filled($s->window_block ?? null)); @endphp

                                <p class="stk-help mb-0">
                                    @if ($anyBlocked)
                                        الوردياتُ المعطَّلة في القائمة خارجَ وقتها الآن — مرِّر المؤشّر لمعرفة السبب.
                                    @else
                                        خارجَ وقتها يُمنع الفتح — والمعطَّلةُ لا تظهر هنا أصلاً.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="opening_balance" class="stk-req">
                                    الرصيد الافتتاحي ({{ $symbol }})
                                </label>
                                <input type="number" id="opening_balance" name="opening_balance"
                                       class="form-control @error('opening_balance') is-invalid @enderror"
                                       placeholder="0.00" step="0.01" min="0"
                                       value="{{ old('opening_balance', '0') }}" required>
                                @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <p class="stk-help mb-0">
                                    ما في الدرج الآن — وعليه يُقاس الفرقُ عند الإقفال.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{--
                        ما يقع عند الفتح يُقال قبله لا بعده: العهدةُ تُفتح
                        باسمك، وكلُّ بيعٍ وحركةِ درجٍ يُنسبان إليها حتى تُقفل.
                    --}}
                    <div class="alert alert-info mb-0">
                        <i class="feather icon-info"></i>
                        بفتح الجلسة تُسجَّل العهدةُ باسمك: يُنسب إليها كلُّ بيعٍ وكلُّ حركةِ درج،
                        ويُقارَن رصيدُها الفعليّ بالمتوقَّع عند الإقفال فيظهر الفائضُ أو العجز.
                    </div>
                </div>
            </div>

            <div class="stk-form-foot">
                <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-play"></i> بدء الجلسة
                </button>
            </div>
        </form>
    @endif
</div>

@endsection
