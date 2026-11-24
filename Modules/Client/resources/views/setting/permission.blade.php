@extends('client::master')

{{--
    شاشة «صلاحيات بوابة العميل».

    ══════════════════ ما استُبدل ══════════════════

    كانت الشاشة تُقسّم المفاتيح بـ`$ClientPermissions->where('category', …)`
    على جدولٍ **لا عمود `category` فيه** — فالأقسام الثلاثة تخرج فارغةً
    دائماً: تُفتح الشاشة بلا مفتاحٍ واحد، وأيُّ ضغطٍ على «حفظ» يُطفئ
    الثمانية جميعاً لأن `settings[]` لا يصل فيه شيء.

    ولم تكن مربوطةً بشيء أصلاً: `is_active` تُخزَّن ولا يقرؤها أحد.

    ══════════════════ التقسيم الآن ══════════════════

    من `App\Support\ClientPortalPermissions::groups()` — كودٌ لا عمود،
    ومعه لكلّ مفتاحٍ **أسماء الراوتات التي يحرسها**. فمِن الشاشة نفسها
    يُشتقّ ما يُسمح للعميل به في `ClientAccessMiddleware`، لا قائمةٌ ثانية
    تُحدَّث يدوياً فتفترق عمّا يراه المستخدم هنا.

    ══════════════════ الدور ══════════════════

    «دور العميل» يُختار من هذه الشاشة بمعرّفه — كان اسمُ الدور مثبَّتاً في
    الكود (`hasRoleName('client')`) لدورٍ غير موجودٍ في جدول الأدوار، فكان
    الحصر لا يقع على أحد أبداً.

    ══════════════════ شكل النموذج ══════════════════

    `perm[<key>]` خريطةً لا `settings[]` قائمةً: مكوّن `switch` يُرسل حقلاً
    مخفيّاً بـ`0` قبل المربّع، وهو ما يجعل الإطفاء يصل إلى الخادم.
--}}

@section('title', 'صلاحيات بوابة العميل')

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    <style>
        /* المفتاح الرئيسي: بطاقةٌ مميّزة — إطفاؤه يُبطل كل ما تحته،
           فلا يصحّ أن يكون مفتاحاً كسائر المفاتيح في شبكة واحدة */
        .cl-scope .cl-master {
            border-inline-start: 4px solid var(--app-primary, #7367f0);
        }

        .cl-scope .cl-master.is-off {
            border-inline-start-color: var(--app-danger, #ea5455);
        }

        .cl-scope .cl-switch-row {
            padding: .65rem .25rem;
        }

        /* مفتاحٌ بلا شاشةٍ في البوابة بعد — يُعرَض ولا يُدّعى أنه يعمل */
        .cl-scope .cl-planned {
            display: inline-flex; align-items: center; gap: .25rem;
            margin-inline-start: .4rem; padding: .05rem .4rem;
            border-radius: 999px; font-size: .66rem; font-weight: 700;
            background: rgba(255, 159, 67, .14); color: #ff9f43;
            vertical-align: middle;
        }

        .cl-scope .cl-missing {
            background: rgba(234, 84, 85, .14); color: #ea5455;
        }
    </style>

    <div class="content-body cl-scope">

        @php
            $portalOn = in_array(\App\Support\ClientPortalPermissions::MASTER, $activeKeys, true);
            $master = \App\Support\ClientPortalPermissions::MASTER;

            // القيمة المعروضة تحترم `old()` بعد فشل تحقّق، وإلّا فحالة الجدول
            $isOn = fn (string $key) => (bool) old('perm.'.$key, in_array($key, $activeKeys, true));
        @endphp

        @section('page-actions')
            <button type="submit" form="clientPortalPermissions" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ
            </button>

            <a href="{{ route('clients.setting') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                إلغاء
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => 'صلاحيات بوابة العميل',
            'icon' => 'user-check',
            'sub' => 'ما يدخله العميل بنفسه ويراه من بياناته عندنا.',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'إعدادات العملاء' => route('clients.setting'),
                'صلاحيات بوابة العميل' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('client::components.form-errors')

        @include('client::components.guide', [
            'key' => 'client-portal-permissions.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'بوابة العميل تسمح لعميلك أن يسجّل دخوله بحسابٍ خاصٍّ به فيرى بياناته '
                .'ومستنداته عندنا بنفسه، بلا أن يتّصل بموظّف. هذه الشاشة تقرّر شيئين: '
                .'مَن يُعدّ «عميلاً» في النظام، وماذا يرى بعد دخوله.',
            'blocks' => [
                'دور العميل' => [
                    'حساب العميل مستخدمٌ عاديّ في النظام، لكنه مربوطٌ بسجلّ عميل ودورُه هو الدور المختار أدناه.',
                    'لا اسم دورٍ مثبَّت في الكود: أنشئ دوراً باسمٍ تختاره أنت، ثم اختره هنا.',
                    'الحصر لا يقع إلّا على من اجتمع فيه الشرطان معاً: دورُه هو المختار، وحسابه مربوطٌ بعميل. '
                        .'فموظّفٌ بالدور نفسه وبلا ربطٍ بعميل لا يُحصر.',
                ],
                'المفاتيح' => [
                    'كل مفتاحٍ يفتح شاشةً بعينها في البوابة — إطفاؤه يُغلق رابطها فوراً لا بعد إعادة تسجيل الدخول.',
                    'العميل لا يرى إلّا بياناته هو: الفواتير والعروض والمواعيد كلها مُصفّاةٌ على سجلّه.',
                    'مفتاحٌ موسومٌ «لا شاشة بعد» محفوظٌ لميزةٍ لم تُبنَ — تفعيلُه اليوم لا يفتح شيئاً.',
                ],
            ],
            'facts' => [
                'إطفاء المفتاح الرئيسي يقفل البوابة على كل العملاء فوراً.',
                'إن لم يبقَ للعميل مفتاحٌ واحد، تُنهى جلسته برسالةٍ صريحة بدل حلقة تحويل.',
            ],
        ])

        <form id="clientPortalPermissions" action="{{ route('clients.store_permission') }}" method="POST">
            @csrf

            {{-- ══════════ دور العميل ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-users"></i> دور العميل
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 col-12">
                            <div class="form-group mb-0">
                                <label for="portal_role_id">الدور الذي تُعدّ حساباتُه حسابات عملاء</label>
                                <select name="portal_role_id" id="portal_role_id" class="form-control">
                                    <option value="">— لم يُختَر بعد (البوابة معطّلة) —</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            @selected((string) old('portal_role_id', $portalRoleId) === (string) $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="cl-help">
                                    أنشئ دوراً خاصّاً بالعملاء من «الأدوار» ثم اخترْه هنا. اختيارُ دورِ موظّفين
                                    يحصر كلَّ من فيه ممّن له ربطٌ بعميل.
                                </span>
                            </div>
                        </div>

                        <div class="col-lg-6 col-12">
                            @if ($portalRoleId)
                                <div class="cl-stats">
                                    <div class="cl-stat">
                                        <div class="cl-stat__icon cl-stat__icon--info">
                                            <i class="feather icon-user-check"></i>
                                        </div>
                                        <div class="cl-stat__body">
                                            <p class="cl-stat__value">{{ number_format($affectedUsers) }}</p>
                                            <p class="cl-stat__label">حساب يدخل البوابة</p>
                                        </div>
                                    </div>

                                    <div class="cl-stat">
                                        <div class="cl-stat__icon">
                                            <i class="feather icon-users"></i>
                                        </div>
                                        <div class="cl-stat__body">
                                            <p class="cl-stat__value">{{ number_format($roleUsers) }}</p>
                                            <p class="cl-stat__label">إجمالي حسابات هذا الدور</p>
                                        </div>
                                    </div>
                                </div>

                                @if ($roleUsers > $affectedUsers)
                                    <span class="cl-help" data-hint-keep>
                                        {{ number_format($roleUsers - $affectedUsers) }} حساباً في هذا الدور بلا ربطٍ
                                        بعميل — لا يُحصر ولا يدخل البوابة.
                                    </span>
                                @endif
                            @else
                                <span class="cl-help" data-hint-keep>
                                    ما دام لم يُختَر دور، لا يُحصر أحدٌ في البوابة ولا تعمل المفاتيح أدناه.
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ المفتاح الرئيسي ══════════ --}}
            <div class="card cl-master {{ $isOn($master) ? '' : 'is-off' }}">
                <div class="card-body">
                    <div class="cl-switch-row">
                        @include('client::components.switch', [
                            'name' => 'perm['.$master.']',
                            'id' => 'sw_'.$master,
                            'checked' => $isOn($master),
                            'title' => \App\Support\ClientPortalPermissions::all()[$master]['label'],
                            'hint' => \App\Support\ClientPortalPermissions::all()[$master]['hint'],
                        ])
                    </div>
                </div>
            </div>

            {{-- ══════════ المجموعات ══════════ --}}
            @foreach ($groups as $groupKey => $group)
                @php
                    // المفتاح الرئيسي عُرض وحده أعلاه — لا يُكرَّر داخل مجموعته
                    $keys = array_filter(
                        array_keys($group['permissions']),
                        fn ($k) => $k !== $master
                    );
                @endphp

                @continue($keys === [])

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="cl-help mt-0">{{ $group['hint'] }}</p>

                        <div class="row">
                            @foreach ($keys as $key)
                                @php
                                    $definition = $group['permissions'][$key];
                                    $planned = $definition['routes'] === [];
                                    $missing = ! in_array($key, $storedKeys, true);
                                @endphp

                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => 'perm['.$key.']',
                                            'id' => 'sw_'.$key,
                                            'checked' => $isOn($key),
                                            'disabled' => $missing,
                                            'title' => $definition['label'],
                                            'hint' => $definition['hint'],
                                        ])

                                        @if ($missing)
                                            <span class="cl-planned cl-missing">
                                                <i class="feather icon-alert-triangle"></i> غير مزروع في القاعدة
                                            </span>
                                        @elseif ($planned)
                                            <span class="cl-planned">
                                                <i class="feather icon-clock"></i> لا شاشة بعد
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </form>
    </div>
@endsection
