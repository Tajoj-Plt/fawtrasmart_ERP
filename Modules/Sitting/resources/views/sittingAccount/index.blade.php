@extends('sitting::master')

@section('title', __('settings.account.title'))

@section('content')
    @include('sitting::components._account_ui')

    @php
        use App\Services\Settings\SettingsNavigation;
        use App\Services\Settings\SubscriptionOverview;

        $overview = app(SubscriptionOverview::class);
        $sub = $overview->current();
        $usageRows = $overview->usage($sub);
        $authUser = auth()->user();
        $setting = $account_setting ?? null;

        // القيمة المعروضة: ما كتبه المستخدم قبل الرفض، ثم المحفوظ، ثم فارغ
        $val = fn ($field, $fallback = null) => old($field, $fallback);
    @endphp

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.account.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.account.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <button type="submit" form="accountForm" class="ax-btn ax-btn-primary">
                <i class="feather icon-save"></i> {{ __('settings.common.save_changes') }}
            </button>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="ax-note dang">
                <i class="feather icon-alert-triangle" style="margin-top:.15rem"></i>
                <div>
                    <strong>{{ __('settings.common.fix_fields') }}</strong>
                    <ul style="margin:.3rem 0 0;padding-right:1rem;font-size:.87rem">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="ax-shell">

            {{-- ══════════ قائمة الأقسام ══════════ --}}
            <nav class="ax-nav" id="axNav">
                @foreach ([
                    ['id' => 'establishment', 'icon' => 'briefcase', 'label' => __('settings.account.tabs.establishment')],
                    ['id' => 'contact', 'icon' => 'map-pin', 'label' => __('settings.account.tabs.contact')],
                    ['id' => 'brand', 'icon' => 'image', 'label' => __('settings.account.tabs.brand')],
                    ['id' => 'prefs', 'icon' => 'sliders', 'label' => __('settings.account.tabs.prefs')],
                    ['id' => 'notify', 'icon' => 'bell', 'label' => __('settings.account.tabs.notify')],
                ] as $tab)
                    <button type="button" class="ax-nav-item" data-tab="{{ $tab['id'] }}">
                        <i class="feather icon-{{ $tab['icon'] }}"></i>
                        <span>{{ $tab['label'] }}</span>
                        <i class="feather icon-check tick"></i>
                    </button>
                @endforeach

                <div class="ax-nav-sep"></div>

                <button type="button" class="ax-nav-item" data-tab="security">
                    <i class="feather icon-shield"></i>
                    <span>{{ __('settings.account.tabs.security') }}</span>
                    <i class="feather icon-check tick"></i>
                </button>
                <button type="button" class="ax-nav-item" data-tab="subscription">
                    <i class="feather icon-award"></i>
                    <span>{{ __('settings.account.tabs.subscription') }}</span>
                    <i class="feather icon-check tick"></i>
                </button>
            </nav>

            {{-- ══════════ المحتوى ══════════ --}}
            <div>
                <form id="accountForm" action="{{ route('SittingAccount.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    {{-- ─── بيانات المنشأة ─── --}}
                    <section class="ax-panel" data-panel="establishment">
                        <header>
                            <span class="ic"><i class="feather icon-briefcase"></i></span>
                            <div>
                                <h3>{{ __('settings.account.tabs.establishment') }}</h3>
                            </div>
                            <p>{{ __('settings.account.establishment_hint') }}</p>
                        </header>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-8 col-12">
                                    <div class="ax-field">
                                        <label for="trade_name">{{ __('settings.account.trade_name') }} <span class="req">*</span></label>
                                        <div class="ax-control">
                                            <i class="feather icon-briefcase"></i>
                                            <input type="text" id="trade_name" name="trade_name" required
                                                class="ax-input @error('trade_name') is-invalid @enderror"
                                                value="{{ $val('trade_name', $client->trade_name ?? '') }}"
                                                placeholder="{{ __('settings.account.trade_name_placeholder') }}">
                                        </div>
                                        @error('trade_name')<div class="ax-err">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="ax-field">
                                        <label for="code">{{ __('settings.account.establishment_code') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-hash"></i>
                                            <input type="text" id="code" name="code" class="ax-input"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('code', $client->code ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="first_name">{{ __('settings.account.owner_first_name') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-user"></i>
                                            <input type="text" id="first_name" name="first_name" class="ax-input"
                                                value="{{ $val('first_name', $client->first_name ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="last_name">{{ __('settings.account.owner_last_name') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-user"></i>
                                            <input type="text" id="last_name" name="last_name" class="ax-input"
                                                value="{{ $val('last_name', $client->last_name ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="commercial_registration">{{ __('settings.account.commercial_registration') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-file-text"></i>
                                            <input type="text" id="commercial_registration" name="commercial_registration"
                                                class="ax-input" style="direction:ltr;text-align:left"
                                                value="{{ $val('commercial_registration', $client->commercial_registration ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="tax_number">{{ __('settings.account.tax_number') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-percent"></i>
                                            <input type="text" id="tax_number" name="tax_number" class="ax-input"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('tax_number', $client->tax_number ?? '') }}">
                                        </div>
                                        <div class="hint">{{ __('settings.account.tax_number_hint') }}</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="notes">{{ __('settings.account.internal_notes') }}</label>
                                        <textarea id="notes" name="notes" class="ax-input"
                                            placeholder="{{ __('settings.account.internal_notes_placeholder') }}">{{ $val('notes', $client->notes ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ─── التواصل والعنوان ─── --}}
                    <section class="ax-panel ax-hide" data-panel="contact">
                        <header>
                            <span class="ic"><i class="feather icon-map-pin"></i></span>
                            <div><h3>{{ __('settings.account.tabs.contact') }}</h3></div>
                            <p>{{ __('settings.account.contact_hint') }}</p>
                        </header>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-4 col-12">
                                    <div class="ax-field">
                                        <label for="phone">{{ __('settings.common.phone') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-phone"></i>
                                            <input type="text" id="phone" name="phone" class="ax-input"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('phone', $client->phone ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="ax-field">
                                        <label for="mobile">{{ __('settings.common.mobile') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-smartphone"></i>
                                            <input type="text" id="mobile" name="mobile" class="ax-input"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('mobile', $client->mobile ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="ax-field">
                                        <label for="contact_email">{{ __('settings.common.email') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-mail"></i>
                                            <input type="email" id="contact_email" name="email"
                                                class="ax-input @error('email') is-invalid @enderror"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('email', $client->email ?? '') }}">
                                        </div>
                                        @error('email')<div class="ax-err">{{ $message }}</div>@enderror
                                        <div class="hint">{{ __('settings.account.contact_email_hint') }}</div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="street1">{{ __('settings.common.address') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-map-pin"></i>
                                            <input type="text" id="street1" name="street1" class="ax-input"
                                                value="{{ $val('street1', $client->street1 ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="street2">{{ __('settings.account.address_detailed') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-navigation"></i>
                                            <input type="text" id="street2" name="street2" class="ax-input"
                                                value="{{ $val('street2', $client->street2 ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 col-6">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="city">{{ __('settings.common.city') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-map"></i>
                                            <input type="text" id="city" name="city" class="ax-input"
                                                value="{{ $val('city', $client->city ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="region">{{ __('settings.common.region') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-compass"></i>
                                            <input type="text" id="region" name="region" class="ax-input"
                                                value="{{ $val('region', $client->region ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="postal_code">{{ __('settings.common.postal_code') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-hash"></i>
                                            <input type="text" id="postal_code" name="postal_code" class="ax-input"
                                                style="direction:ltr;text-align:left"
                                                value="{{ $val('postal_code', $client->postal_code ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="country">{{ __('settings.common.country') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-globe"></i>
                                            <input type="text" id="country" name="country" class="ax-input"
                                                value="{{ $val('country', $client->country ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ─── الشعار والختم ─── --}}
                    <section class="ax-panel ax-hide" data-panel="brand">
                        <header>
                            <span class="ic"><i class="feather icon-image"></i></span>
                            <div><h3>{{ __('settings.account.brand_title') }}</h3></div>
                            <p>{{ __('settings.account.brand_hint') }}</p>
                        </header>
                        <div class="body">
                            <div class="row">
                                @foreach ([
                                    ['field' => 'attachments', 'label' => __('settings.account.logo'), 'hint' => __('settings.account.max_2mb')],
                                    ['field' => 'stamp', 'label' => __('settings.account.stamp'), 'hint' => __('settings.account.max_1mb')],
                                    ['field' => 'signature', 'label' => __('settings.account.signature'), 'hint' => __('settings.account.max_1mb')],
                                ] as $img)
                                    @php $stored = $setting->{$img['field']} ?? null; @endphp
                                    <div class="col-md-4 col-12">
                                        <div class="ax-field">
                                            <label>{{ $img['label'] }}</label>
                                            <label class="ax-upload">
                                                <input type="file" name="{{ $img['field'] }}" accept="image/*"
                                                    data-preview="prev-{{ $img['field'] }}">
                                                @if ($stored)
                                                    <img id="prev-{{ $img['field'] }}"
                                                        src="{{ Storage::disk('public')->url($stored) }}" alt="{{ $img['label'] }}">
                                                @else
                                                    <img id="prev-{{ $img['field'] }}" style="display:none" alt="">
                                                @endif
                                                <div class="ph">
                                                    <i class="feather icon-upload-cloud"></i>
                                                    {{ __('settings.account.upload_click') }}
                                                </div>
                                            </label>
                                            @error($img['field'])<div class="ax-err">{{ $message }}</div>@enderror
                                            <div class="hint">{{ $img['hint'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    {{-- ─── التفضيلات ─── --}}
                    <section class="ax-panel ax-hide" data-panel="prefs">
                        <header>
                            <span class="ic"><i class="feather icon-sliders"></i></span>
                            <div><h3>{{ __('settings.account.tabs.prefs') }}</h3></div>
                            <p>{{ __('settings.account.prefs_hint') }}</p>
                        </header>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="currency">
                                            {{ __('settings.account.default_currency') }}
                                            <a href="{{ route('SittingAccount.currency') }}"
                                                style="float:left;font-size:.78rem;font-weight:600">{{ __('settings.account.manage_currencies') }}</a>
                                        </label>
                                        <div class="ax-control">
                                            <i class="feather icon-dollar-sign"></i>
                                            <select name="currency" id="currency" class="ax-input">
                                                @foreach (\App\Helpers\CurrencyHelper::getAllCurrencies() as $code => $name)
                                                    <option value="{{ $code }}"
                                                        {{ $val('currency', $client->currency ?? 'SAR') === $code ? 'selected' : '' }}>
                                                        {{ $code }} — {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label>{{ __('settings.account.timezone') }}</label>
                                        {{-- بلا `:value` كان الحقلُ يختار «الرياض» في كل فتحٍ للشاشة،
                                             فيكتب كلُّ حفظٍ فوق المنطقة المحفوظة --}}
                                        <x-form.timezone-select :value="$account_setting->timezone ?? 'Asia/Riyadh'" />
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="time_formula">{{ __('settings.account.date_format') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-calendar"></i>
                                            <select name="time_formula" id="time_formula" class="ax-input">
                                                @php
                                                    $currentFormat = $val('time_formula', $setting->time_formula ?? 'd/m/Y');
                                                    $formatGroups = [
                                                        __('settings.account.date_only') => ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y', 'd M Y', 'd F Y'],
                                                        __('settings.account.date_and_time') => ['d/m/Y H:i', 'Y-m-d H:i', 'H:i:s d/m/Y', 'd/m/Y h:i A'],
                                                    ];
                                                @endphp
                                                @foreach ($formatGroups as $groupLabel => $formats)
                                                    <optgroup label="{{ $groupLabel }}">
                                                        @foreach ($formats as $format)
                                                            <option value="{{ $format }}"
                                                                {{ $currentFormat === $format ? 'selected' : '' }}>
                                                                {{ now()->format($format) }} — {{ $format }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="hint">{{ __('settings.account.date_format_hint') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="negative_currency_formats">{{ __('settings.account.negative_format') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-minus-circle"></i>
                                            <select name="negative_currency_formats" id="negative_currency_formats" class="ax-input">
                                                @php $neg = $val('negative_currency_formats', $setting->negative_currency_formats ?? 'standard'); @endphp
                                                <option value="standard" {{ $neg === 'standard' ? 'selected' : '' }}>-19.5</option>
                                                <option value="parentheses" {{ $neg === 'parentheses' ? 'selected' : '' }}>(19.5)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="business_type">{{ __('settings.account.business_type') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-shopping-bag"></i>
                                            <select name="business_type" id="business_type" class="ax-input">
                                                @php $bt = $val('business_type', $setting->business_type ?? 'both'); @endphp
                                                <option value="products" {{ $bt === 'products' ? 'selected' : '' }}>{{ __('settings.account.business_products') }}</option>
                                                <option value="services" {{ $bt === 'services' ? 'selected' : '' }}>{{ __('settings.account.business_services') }}</option>
                                                <option value="both" {{ $bt === 'both' ? 'selected' : '' }}>{{ __('settings.account.business_both') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="printing_method">{{ __('settings.account.printing_method') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-printer"></i>
                                            <select name="printing_method" id="printing_method" class="ax-input">
                                                @php $pm = $val('printing_method', $setting->printing_method ?? 'browser'); @endphp
                                                <option value="browser" {{ $pm === 'browser' ? 'selected' : '' }}>{{ __('settings.account.print_browser') }}</option>
                                                <option value="pdf" {{ $pm === 'pdf' ? 'selected' : '' }}>{{ __('settings.account.print_pdf') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="ax-field">
                                        <label for="language">{{ __('settings.account.language') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-globe"></i>
                                            <select name="language" id="language" class="ax-input">
                                                <option value="ar" selected>{{ __('settings.account.language_ar') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="ax-field" style="margin-bottom:0">
                                        <label for="global_client_exchange_limit">{{ __('settings.account.exchange_limit') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-repeat"></i>
                                            <input type="number" min="0" id="global_client_exchange_limit"
                                                name="global_client_exchange_limit" class="ax-input"
                                                value="{{ $val('global_client_exchange_limit', $setting->global_client_exchange_limit ?? '') }}"
                                                placeholder="{{ __('settings.common.no_limit') }}">
                                        </div>
                                        <div class="hint">{{ __('settings.account.exchange_limit_hint') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ─── الإشعارات ─── --}}
                    <section class="ax-panel ax-hide" data-panel="notify">
                        <header>
                            <span class="ic"><i class="feather icon-bell"></i></span>
                            <div><h3>{{ __('settings.account.notify_title') }}</h3></div>
                            <p>{{ __('settings.account.notify_hint') }}</p>
                        </header>
                        <div class="body">
                            {{-- هذه المفاتيح الثلاثة كان المتحكّم يحفظها والنموذج
                                 لا يعرضها، فكان كل حفظ يُطفئها صامتاً لأن
                                 `boolean()` على حقل غائب تُرجع false --}}
                            @foreach ([
                                ['field' => 'notification_email', 'icon' => 'mail', 'label' => __('settings.account.notify_email'), 'hint' => __('settings.account.notify_email_hint')],
                                ['field' => 'notification_sms', 'icon' => 'message-square', 'label' => __('settings.account.notify_sms'), 'hint' => __('settings.account.notify_sms_hint')],
                                ['field' => 'notification_browser', 'icon' => 'monitor', 'label' => __('settings.account.notify_browser'), 'hint' => __('settings.account.notify_browser_hint')],
                            ] as $ch)
                                <label class="ax-switch">
                                    <input type="hidden" name="{{ $ch['field'] }}" value="0">
                                    <input type="checkbox" name="{{ $ch['field'] }}" value="1"
                                        {{ $val($ch['field'], $setting->{$ch['field']} ?? false) ? 'checked' : '' }}>
                                    <span class="txt">
                                        <b><i class="feather icon-{{ $ch['icon'] }}"></i> {{ $ch['label'] }}</b>
                                        <small>{{ $ch['hint'] }}</small>
                                    </span>
                                    <span class="track"></span>
                                </label>
                            @endforeach

                            <div class="ax-note brand" style="margin:1.2rem 0 0">
                                <i class="feather icon-info" style="margin-top:.15rem"></i>
                                <div>
                                    <p style="margin:0">{!! __('settings.account.notify_where', ['link' => '<a href="'.route('NotificationSettings.index').'">'.__('settings.account.notification_settings').'</a>']) !!}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="ax-panel" id="axSaveBar">
                        <div class="foot">
                            <button type="submit" class="ax-btn ax-btn-primary">
                                <i class="feather icon-save"></i> {{ __('settings.common.save_changes') }}
                            </button>
                            <a href="{{ route('SittingAccount.dashboard') }}" class="ax-btn ax-btn-ghost">{{ __('settings.common.cancel') }}</a>
                            <span class="note">{{ __('settings.account.save_all_note') }}</span>
                        </div>
                    </div>
                </form>

                {{-- ─── الدخول والأمان (خارج النموذج) ─── --}}
                <section class="ax-panel ax-hide" data-panel="security">
                    <header>
                        <span class="ic"><i class="feather icon-shield"></i></span>
                        <div><h3>{{ __('settings.account.tabs.security') }}</h3></div>
                        <p>{{ __('settings.account.security_hint') }}</p>
                    </header>
                    <div class="body">
                        <dl style="margin:0 0 1.2rem">
                            <div class="ax-def">
                                <dt>{{ __('settings.account.user') }}</dt>
                                <dd>{{ $authUser->name }}</dd>
                            </div>
                            <div class="ax-def">
                                <dt>{{ __('settings.account.login_email') }}</dt>
                                <dd><span class="ax-mono">{{ $authUser->email }}</span></dd>
                            </div>
                            <div class="ax-def">
                                <dt>{{ __('settings.account.role') }}</dt>
                                <dd>
                                    <span class="ax-badge brand">
                                        {{ $authUser->getRoleNames()->implode(__('settings.common.list_separator')) ?: $authUser->role }}
                                    </span>
                                </dd>
                            </div>
                            <div class="ax-def">
                                <dt>{{ __('settings.account.last_login') }}</dt>
                                <dd>
                                    {{ $authUser->last_login_at ? \Carbon\Carbon::parse($authUser->last_login_at)->format('Y-m-d H:i') : '—' }}
                                    @if ($authUser->last_login_ip)
                                        <span class="ax-mono">{{ $authUser->last_login_ip }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="row">
                            <div class="col-md-6 col-12">
                                <form action="{{ route('SittingAccount.Change_email') }}" method="POST">
                                    @csrf
                                    <div class="ax-field">
                                        <label for="new_email">{{ __('settings.account.change_email') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-mail"></i>
                                            <input type="email" id="new_email" name="email" required class="ax-input"
                                                style="direction:ltr;text-align:left" placeholder="{{ $authUser->email }}">
                                        </div>
                                    </div>
                                    <button type="submit" class="ax-btn ax-btn-ghost">
                                        <i class="feather icon-check"></i> {{ __('settings.account.update_email') }}
                                    </button>
                                </form>
                            </div>

                            <div class="col-md-6 col-12">
                                <form action="{{ route('SittingAccount.change_password') }}" method="POST">
                                    @csrf
                                    <div class="ax-field">
                                        <label for="current_password">{{ __('settings.account.current_password') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-lock"></i>
                                            <input type="password" id="current_password" name="current_password" required
                                                class="ax-input" style="direction:ltr;text-align:left">
                                        </div>
                                    </div>
                                    <div class="ax-field">
                                        <label for="new_password">{{ __('settings.account.new_password') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-key"></i>
                                            <input type="password" id="new_password" name="password" required
                                                class="ax-input" style="direction:ltr;text-align:left">
                                        </div>
                                    </div>
                                    <div class="ax-field">
                                        <label for="password_confirmation">{{ __('settings.account.confirm_password') }}</label>
                                        <div class="ax-control">
                                            <i class="feather icon-key"></i>
                                            <input type="password" id="password_confirmation" name="password_confirmation"
                                                required class="ax-input" style="direction:ltr;text-align:left">
                                        </div>
                                    </div>
                                    <button type="submit" class="ax-btn ax-btn-ghost">
                                        <i class="feather icon-shield"></i> {{ __('settings.account.change_password') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="ax-note warn" style="margin:1.2rem 0 0">
                            <i class="feather icon-alert-triangle" style="margin-top:.15rem"></i>
                            <div>
                                <p style="margin:0">{{ __('settings.account.security_note') }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ─── الاشتراك والحجم ─── --}}
                <section class="ax-panel ax-hide" data-panel="subscription">
                    <header>
                        <span class="ic"><i class="feather icon-award"></i></span>
                        <div><h3>{{ __('settings.account.usage_title') }}</h3></div>
                        <p>{{ __('settings.account.usage_hint') }}</p>
                    </header>
                    <div class="body">
                        @if ($sub)
                            @php $days = $sub->daysRemaining(); @endphp
                            <div class="ax-note {{ $sub->urgency() === 'ok' ? 'brand' : ($sub->urgency() === 'warn' ? 'warn' : 'dang') }}">
                                <i class="feather icon-award" style="margin-top:.15rem"></i>
                                <div style="flex:1">
                                    <strong>
                                        {{ $sub->plan_name }} —
                                        @if ($sub->isPerpetual())
                                            {{ __('settings.account.perpetual') }}
                                        @elseif ($days < 0)
                                            {{ __('settings.account.expired_since', ['days' => abs($days)]) }}
                                        @else
                                            {{ __('settings.account.days_left', ['days' => $days]) }}
                                        @endif
                                    </strong>
                                    <p>
                                        {{ $sub->statusLabel() }} · {{ $sub->cycleLabel() }}
                                        @if ($sub->expires_at) · {{ __('settings.account.until_date', ['date' => $sub->expires_at->format('Y-m-d')]) }} @endif
                                        · <a href="{{ route('Subscription.index') }}">{{ __('settings.account.subscription_details') }}</a>
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="ax-note warn">
                                <i class="feather icon-alert-triangle" style="margin-top:.15rem"></i>
                                <div>
                                    <strong>{{ __('settings.account.no_subscription') }}</strong>
                                    <p>{!! __('settings.account.no_subscription_hint', ['link' => '<a href="'.route('Subscription.index').'">'.__('settings.account.register_now').'</a>']) !!}</p>
                                </div>
                            </div>
                        @endif

                        <dl style="margin:0">
                            @foreach ($usageRows as $row)
                                <div class="ax-def">
                                    <dt><i class="feather icon-{{ $row['icon'] }}"></i> {{ $row['label'] }}</dt>
                                    <dd>
                                        {{ number_format($row['value']) }}
                                        @if ($row['limit'])
                                            <span style="color:var(--ax-ink-3);font-weight:500">{{ __('settings.usage.of', ['limit' => number_format($row['limit'])]) }}</span>
                                            <span class="ax-badge {{ $row['percent'] >= 90 ? 'dang' : ($row['percent'] >= 75 ? 'warn' : 'ok') }}">
                                                {{ $row['percent'] }}%
                                            </span>
                                        @else
                                            <span class="ax-badge off">{{ __('settings.common.no_limit') }}</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                    <div class="foot">
                        <a href="{{ route('Subscription.index') }}" class="ax-btn ax-btn-primary">
                            <i class="feather icon-award"></i> {{ __('settings.account.subscription_page') }}
                        </a>
                        <span class="note">{{ __('settings.account.subscription_page_hint') }}</span>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var nav = document.getElementById('axNav');
            var buttons = Array.prototype.slice.call(nav.querySelectorAll('.ax-nav-item'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
            var saveBar = document.getElementById('axSaveBar');
            // القسمان خارج النموذج، فلا معنى لشريط الحفظ معهما
            var formless = ['security', 'subscription'];

            function show(id, push) {
                buttons.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === id); });
                panels.forEach(function (p) { p.classList.toggle('ax-hide', p.dataset.panel !== id); });
                saveBar.classList.toggle('ax-hide', formless.indexOf(id) !== -1);

                if (push) {
                    history.replaceState(null, '', '#' + id);
                }
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () { show(button.dataset.tab, true); });
            });

            // القسم الذي فيه حقل مرفوض يُفتح تلقائياً — وإلا بقيت رسالة
            // الخطأ تشير إلى حقل لا يراه المستخدم
            var invalid = document.querySelector('.ax-input.is-invalid');
            var start = (location.hash || '').replace('#', '');

            if (invalid) {
                start = invalid.closest('[data-panel]').dataset.panel;
            }

            show(panels.some(function (p) { return p.dataset.panel === start; }) ? start : 'establishment', false);

            // معاينة الصورة قبل الرفع
            document.addEventListener('change', function (event) {
                var input = event.target;

                if (!input.dataset || !input.dataset.preview || !input.files || !input.files[0]) return;

                var img = document.getElementById(input.dataset.preview);
                img.src = URL.createObjectURL(input.files[0]);
                img.style.display = '';
            });
        })();
    </script>
@endsection
