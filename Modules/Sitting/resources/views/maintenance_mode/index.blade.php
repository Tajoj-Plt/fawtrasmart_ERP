@extends('sitting::master')

@section('title', 'وضع الصيانة')

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $active = $setting->is_active;
        $me = auth()->id();
    @endphp

    <style>
        /* بطاقة الحالة — الجواب عن «أمقفلٌ النظام الآن؟» قبل أي شيء آخر */
        .mm-state {
            display: flex; gap: 1rem; align-items: center;
            padding: 1.2rem 1.3rem; border-radius: 12px; border: 1px solid;
            margin-bottom: 1.2rem;
        }
        .mm-state .dot { width: 14px; height: 14px; border-radius: 50%; flex: 0 0 auto; }
        .mm-state .txt { flex: 1; min-width: 0; }
        .mm-state .txt b { display: block; font-size: 1.05rem; font-weight: 700; }
        .mm-state .txt p { margin: .25rem 0 0; font-size: .86rem; line-height: 1.8; }
        .mm-state.on { background: #fdf4e0; border-color: #f0dfb4; color: #7d5f0e; }
        .mm-state.on .dot { background: #d99b1a; box-shadow: 0 0 0 4px rgba(217, 155, 26, .18); }
        .mm-state.off { background: #eaf7f1; border-color: #bfe4d4; color: #17603f; }
        .mm-state.off .dot { background: #1a9c66; box-shadow: 0 0 0 4px rgba(26, 156, 102, .16); }

        /* قائمة المستخدمين — شبكةٌ لا عمودٌ واحد: عشرون مستخدماً في عمودٍ
           واحد يعني تمريراً طويلاً لاختيار اثنين */
        .mm-users {
            display: grid; gap: .5rem; margin: .2rem 0 0;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            max-height: 340px; overflow: auto; padding: .2rem;
        }
        .mm-user {
            display: flex; gap: .6rem; align-items: flex-start; cursor: pointer;
            padding: .6rem .75rem; border-radius: 9px;
            background: #f7f9fb; border: 1px solid #e6eaef; margin: 0; font-weight: 400;
        }
        .mm-user:hover { border-color: #c9d4e0; }
        .mm-user input { margin-top: .25rem; flex: 0 0 auto; }
        .mm-user b { display: block; font-size: .87rem; color: #1b2430; font-weight: 600; }
        .mm-user small { display: block; font-size: .75rem; color: #8a93a0; direction: ltr; text-align: start; }
        .mm-user .self { font-size: .7rem; color: #17603f; background: #eaf7f1; border-radius: 5px; padding: 0 .35rem; }
        .mm-user.is-self { background: #f2faf6; border-color: #cfe8dc; }

        .mm-key {
            direction: ltr; text-align: left; unicode-bidi: embed;
            font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .78rem;
            background: #1e2430; color: #dfe4ec; border-radius: 8px;
            padding: .7rem .9rem; margin: .5rem 0 0; word-break: break-all;
        }
        .mm-tools { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .6rem; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-tool"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">وضع الصيانة</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">وضع الصيانة</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ الحالة ══════════ --}}
        <div class="mm-state {{ $active ? 'on' : 'off' }}">
            <span class="dot"></span>
            <div class="txt">
                @if ($active)
                    <b>وضع الصيانة مفعَّل — النظام مقفل</b>
                    <p>
                        لا يدخل النظام إلا {{ count($allowedIds) }} مستخدماً من القائمة أدناه. وكلّ من عداهم
                        يرى صفحة الصيانة، ومن كان داخلاً تُنهى جلستُه عند أوّل صفحة يفتحها.
                        @if ($setting->activated_at)
                            <br>فُعِّل {{ $setting->activated_at->diffForHumans() }}@if ($setting->activator) بواسطة {{ $setting->activator->name }}@endif.
                        @endif
                    </p>
                @else
                    <b>النظام مفتوح لكل المستخدمين</b>
                    <p>وضع الصيانة متوقّف. فعّله قبل عملٍ لا يُراد أن يكتب أحدٌ بيانات في أثنائه.</p>
                @endif
            </div>
            @permission('settings.maintenance_mode.manage')
                @if ($active)
                    <form method="POST" action="{{ route('MaintenanceMode.disable') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-success"
                            onclick="return confirm('سيعود النظام مفتوحاً لكل المستخدمين. متابعة؟')">
                            <i class="feather icon-unlock"></i> إيقاف وضع الصيانة
                        </button>
                    </form>
                @endif
            @endpermission
        </div>

        {{-- ── دليل الشاشة ── --}}
        <div class="st-note info">
            <i class="feather icon-help-circle mt-1"></i>
            <div>
                <strong>متى يُستعمَل وضع الصيانة؟</strong>
                <p>
                    قبل أيّ عملٍ لا يجوز أن يكتب أحدٌ بياناتٍ في أثنائه: استرجاعُ نسخة احتياطية،
                    أو ترحيلٌ كبير على قاعدة البيانات، أو تصحيحُ بياناتٍ يدوي. فاتورةٌ تُصدَر
                    أثناء الاسترجاع تُكتب ثم تُمحى مع ما يُكتب فوقه، ولا يعرف أحدٌ أنها فُقدت.
                    <br>
                    وهو غير إقفال الخادم: المسموح لهم يعملون في النظام كاملاً كأن شيئاً لم يكن،
                    والباقون يرون صفحة الصيانة وحدها.
                </p>
            </div>
        </div>

        <form method="POST"
              action="{{ $active ? route('MaintenanceMode.update') : route('MaintenanceMode.enable') }}">
            @csrf

            {{-- ══════════ المسموح لهم ══════════ --}}
            <div class="st-card">
                <div class="head">
                    <i class="feather icon-users" style="color:#2b6cb0"></i>
                    <h4>من يستطيع الدخول أثناء الصيانة</h4>
                    <span class="st-badge {{ $allowedIds ? 'on' : 'off' }}">{{ count($allowedIds) }} مختار</span>
                </div>
                <div class="body">
                    <p style="font-size:.85rem;color:#6b7280;line-height:1.9;margin:0 0 .8rem">
                        علّم الحسابات التي تبقى قادرةً على العمل. وحسابُك أنت يُضاف تلقائياً في كل
                        الأحوال — لا يُقفَل النظام على من أقفله.
                        @error('allowed_user_ids')
                            <br><b style="color:#a3342a">{{ $message }}</b>
                        @enderror
                    </p>

                    {{-- خانةٌ فارغة تسبق المجموعة: نموذجُ HTML لا يرسل شيئاً عن
                         مجموعةِ خاناتٍ لم يُعلَّم منها شيء، فتصل القائمة غائبةً
                         لا فارغة — ولا يفرّق التحقّق بينهما إلا بهذا السطر --}}
                    <input type="hidden" name="allowed_user_ids[]" value="">

                    <div class="mm-users">
                        @foreach ($users as $user)
                            @php $isSelf = (int) $user->id === (int) $me; @endphp
                            <label class="mm-user {{ $isSelf ? 'is-self' : '' }}" for="mm-u-{{ $user->id }}">
                                <input type="checkbox" id="mm-u-{{ $user->id }}"
                                       name="allowed_user_ids[]" value="{{ $user->id }}"
                                       @checked($isSelf || in_array((int) $user->id, $allowedIds, true))
                                       @disabled($isSelf)>
                                {{-- الحساب الحالي معلَّمٌ ومعطَّل: لا يُنزَع الاختيار عنه في
                                     الواجهة، ولا يُعتمد على ذلك — `enable()` تضيفه قسراً --}}
                                @if ($isSelf)
                                    <input type="hidden" name="allowed_user_ids[]" value="{{ $user->id }}">
                                @endif
                                <span>
                                    <b>{{ $user->name }} @if ($isSelf)<span class="self">أنت</span>@endif</b>
                                    <small>{{ $user->email }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════ الرسالة ══════════ --}}
            <div class="st-card">
                <div class="head">
                    <i class="feather icon-message-square" style="color:#2b6cb0"></i>
                    <h4>الرسالة المعروضة للمحجوبين</h4>
                </div>
                <div class="body">
                    <textarea name="message" rows="3" class="form-control"
                              placeholder="النظام في وضع الصيانة حالياً. نعمل على تحديثه، وسيعود للعمل قريباً.">{{ old('message', $setting->message) }}</textarea>
                    <p style="font-size:.8rem;color:#8a93a0;margin:.5rem 0 0">
                        اتركها فارغة لاستعمال الرسالة المبدئية. وذِكرُ الوقت المتوقّع للعودة يوفّر
                        اتصالاتٍ كثيرة.
                    </p>

                    @permission('settings.maintenance_mode.manage')
                        <div style="margin-top:1.1rem;padding-top:.9rem;border-top:1px solid #eef0f4">
                            @if ($active)
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i> حفظ التعديلات
                                </button>
                            @else
                                <button type="submit" class="btn btn-warning"
                                    onclick="return confirm('سيُقفَل النظام على كل المستخدمين عدا من علّمتهم، ومن كان داخلاً تُنهى جلسته. متابعة؟')">
                                    <i class="feather icon-lock"></i> تفعيل وضع الصيانة
                                </button>
                            @endif
                        </div>
                    @endpermission
                </div>
            </div>
        </form>

        {{-- ══════════ مفتاح الطوارئ ══════════ --}}
        @permission('settings.maintenance_mode.manage')
            @if ($bypassUrl)
                <div class="st-card">
                    <div class="head">
                        <i class="feather icon-key" style="color:#2b6cb0"></i>
                        <h4>رابط الطوارئ</h4>
                        <span class="st-badge warn">سرّي</span>
                    </div>
                    <div class="body">
                        <p style="font-size:.85rem;color:#4b5563;line-height:1.9;margin:0">
                            إن تعذّر دخول كلّ من في القائمة — حسابٌ عُطِّل، أو كلمةُ مرورٍ نُسيت، أو
                            خطأٌ في الاختيار — فهذا الرابط هو المخرج الوحيد على استضافةٍ بلا سطر أوامر.
                            فتحُه يمنح متصفّحك حقَّ المرور حتى تُطفئ الوضع من هذه الشاشة.
                            <b>احفظه خارج النظام الآن</b> — لا بعد أن تُقفَل عليك الشاشة.
                        </p>
                        <div class="mm-key">{{ $bypassUrl }}</div>
                        <div class="mm-tools">
                            <form method="POST" action="{{ route('MaintenanceMode.rotate') }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('سيُبطَل الرابط الحالي وكلُّ جلسةٍ دخلت به. متابعة؟')">
                                    <i class="feather icon-refresh-cw"></i> توليد مفتاح جديد
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endpermission
    </div>
@endsection
