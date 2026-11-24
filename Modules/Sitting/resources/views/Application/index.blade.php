@extends('sitting::master')

@section('title', __('settings.applications.title'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-grid"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.applications.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.applications.breadcrumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <button type="button" class="btn btn-outline-secondary" id="stToggleAll" data-target="on">
                <i class="feather icon-check-square"></i> {{ __('settings.applications.enable_all') }}
            </button>
            <button type="submit" form="applicationsForm" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ __('settings.common.save') }}
            </button>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $activeCount }}</div>
                    <div class="lbl">{{ __('settings.applications.modules_on') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic secondary" style="background:#f0f1f3;color:#6b7280"><i class="feather icon-slash"></i></div>
                <div>
                    <div class="val">{{ $offCount }}</div>
                    <div class="lbl">{{ __('settings.applications.modules_off') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-lock"></i></div>
                <div>
                    <div class="val">{{ $lockedCount }}</div>
                    <div class="lbl">{{ __('settings.applications.modules_locked') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-grid"></i></div>
                <div>
                    <div class="val">{{ $totalCount }}</div>
                    <div class="lbl">{{ __('settings.applications.modules_total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-layers"></i></div>
                <div>
                    <div class="val">{{ count($groups) }}</div>
                    <div class="lbl">{{ __('settings.applications.groups_count') }}</div>
                </div>
            </div>
        </div>

        {{-- التعطيل صار يمنع الوصول فعلاً بعد إضافة وسيط `module` —
             وقبله كان يُخفي بند القائمة ويترك المسار مفتوحاً --}}
        <div class="st-note warn">
            <i class="feather icon-alert-triangle mt-1"></i>
            <div>
                <strong>{{ __('settings.applications.notice_title') }}</strong>
                <p>
                    {{ __('settings.applications.notice_body') }}
                </p>
            </div>
        </div>

        {{-- لا تُعرض هذه إلا حين يوجد ما هو خارج الباقة فعلاً: ملاحظةٌ
             دائمة عن قيدٍ لا يقع تصير خلفيةً لا يقرؤها أحد --}}
        @if ($lockedCount > 0)
            <div class="st-note dang">
                <i class="feather icon-lock mt-1"></i>
                <div>
                    <strong>{{ __('settings.applications.plan_notice_title', ['count' => $lockedCount]) }}</strong>
                    <p>
                        {{ __('settings.applications.plan_notice_body') }}
                        <a href="{{ route('Fawtra.index') }}" class="font-weight-bold">
                            {{ __('settings.applications.plan_notice_link') }}
                        </a>
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('Application.update') }}" id="applicationsForm">
            @csrf

            <div class="row">
                @foreach ($groups as $groupName => $group)
                    <div class="col-lg-6 col-12">
                        <div class="st-card">
                            <div class="head">
                                <i class="feather icon-{{ $group['icon'] }}" style="color:#2b6cb0"></i>
                                <h4>{{ $groupName }}</h4>
                                @php
                                    // المقام هو المشمول بالباقة لا كل الوحدات: عدّاد
                                    // «٢ / ٥» في قسمٍ ثلاثُ وحداته خارج الباقة يقرأ
                                    // كأن المسؤول عطّل ثلاثاً بيده
                                    $inPlan = collect(array_keys($group['modules']))
                                        ->filter(fn ($k) => $permits[$k] ?? true);
                                    $onInGroup = $inPlan
                                        ->filter(fn ($k) => ($statuses[$k] ?? 'active') === 'active')
                                        ->count();
                                @endphp
                                <span class="count st-group-count">{{ $onInGroup }} / {{ $inPlan->count() }}</span>
                                <span class="spacer"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary st-group-toggle">{{ __('settings.applications.invert') }}</button>
                            </div>
                            <div class="body">
                                @foreach ($group['modules'] as $key => $module)
                                    @php $locked = ! ($permits[$key] ?? true); @endphp

                                    <div class="st-toggle-row {{ $locked ? 'locked' : '' }}">
                                        <div class="txt">
                                            <b>{{ $module['label'] }}</b>
                                            @isset($module['hint'])
                                                <small>{{ $module['hint'] }}</small>
                                            @endisset
                                        </div>

                                        <label class="st-switch" title="{{ $module['label'] }}">
                                            {{-- خارج الباقة: لا حقل يُرسَل أصلاً.
                                                 حقلٌ مخفيّ بـ`inactive` هنا كان سيكتب
                                                 تعطيلاً لم يطلبه أحد، فتضيع اختيارات
                                                 المسؤول يوم تُرقّى الباقة. --}}
                                            @unless ($locked)
                                                {{-- الحقل المخفيّ يضمن وصول `inactive` حين لا يُؤشَّر المربّع --}}
                                                <input type="hidden" name="{{ $key }}" value="inactive">
                                                <input type="checkbox" name="{{ $key }}" value="active"
                                                    {{ old($key, $statuses[$key] ?? 'active') === 'active' ? 'checked' : '' }}>
                                            @else
                                                <input type="checkbox" disabled>
                                            @endunless
                                            <span class="track"></span>
                                        </label>

                                        @if ($locked)
                                            <a class="st-lock" href="{{ route('Fawtra.index') }}"
                                               title="{{ __('settings.applications.plan_locked_title', ['module' => $module['label']]) }}">
                                                <i class="feather icon-lock"></i>
                                                <span>{{ __('settings.applications.plan_locked') }}</span>
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="st-card">
                <div class="body text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> {{ __('settings.applications.save') }}
                    </button>
                    <div style="font-size:.82rem;color:#8a93a0;margin-top:.5rem" id="stSummary"></div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('applicationsForm');

            function boxes(scope) {
                return Array.prototype.slice.call(
                    (scope || form).querySelectorAll('input[type=checkbox][value=active]')
                );
            }

            function refresh() {
                var all = boxes();
                var on = all.filter(function (b) { return b.checked; }).length;

                document.getElementById('stSummary').textContent =
                    @json(__('settings.applications.summary')).replace(':on', on).replace(':total', all.length);

                // عدّاد كل قسم يتبع اختيارات المستخدم قبل الحفظ لا بعده
                form.querySelectorAll('.st-card').forEach(function (card) {
                    var counter = card.querySelector('.st-group-count');
                    if (!counter) return;
                    var inCard = boxes(card);
                    counter.textContent = inCard.filter(function (b) { return b.checked; }).length
                        + ' / ' + inCard.length;
                });

                var master = document.getElementById('stToggleAll');
                var allOn = on === all.length;
                master.dataset.target = allOn ? 'off' : 'on';
                master.innerHTML = allOn
                    ? @json('<i class="feather icon-square"></i> '.__('settings.applications.disable_all'))
                    : @json('<i class="feather icon-check-square"></i> '.__('settings.applications.enable_all'));
            }

            form.addEventListener('change', refresh);

            document.getElementById('stToggleAll').addEventListener('click', function () {
                var on = this.dataset.target === 'on';
                boxes().forEach(function (b) { b.checked = on; });
                refresh();
            });

            form.querySelectorAll('.st-group-toggle').forEach(function (button) {
                button.addEventListener('click', function () {
                    boxes(button.closest('.st-card')).forEach(function (b) { b.checked = !b.checked; });
                    refresh();
                });
            });

            refresh();
        })();
    </script>
@endsection
