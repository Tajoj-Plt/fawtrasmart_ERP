{{--
    بيانات اعتماد بوابة الدفع — مشتركة بين الإنشاء والتحرير.

    الأسرار لا تُطبع في `value`: صفحةٌ تحمل المفتاح السرّي في مصدرها
    تسرّبه لكل من يفتح «عرض المصدر» أو يلتقط الصفحة من ذاكرة المتصفّح.
    الحقل يبقى فارغاً ويعني تركُه «لا تغيير».
--}}
@php($isEdit = isset($payment))

<div class="st-card" id="gatewayCard">
    <div class="head">
        <i class="feather icon-key" style="color:#2b6cb0"></i>
        <h4>{{ __('settings.payment.gateway') }}</h4>
    </div>
    <div class="body">
        <div class="dt-note" style="background:#f4f7fb;border:1px solid #dbe4ef;border-radius:8px;padding:.6rem .8rem;margin-bottom:1rem;font-size:.84rem;color:#4a5568">
            <i class="feather icon-info"></i> {{ __('settings.payment.gateway_hint') }}
        </div>

        <div class="form-group">
            <label>{{ __('settings.payment.gateway_mode') }}</label>
            <select name="gateway_mode" class="form-control">
                @foreach (['test', 'live'] as $mode)
                    <option value="{{ $mode }}"
                        {{ old('gateway_mode', $isEdit ? $payment->gateway_mode : 'test') === $mode ? 'selected' : '' }}>
                        {{ __('settings.payment.gateway_mode_' . $mode) }}
                    </option>
                @endforeach
            </select>
            <small class="form-text text-muted">{{ __('settings.payment.gateway_mode_hint') }}</small>
        </div>

        <div class="form-group">
            <label>{{ __('settings.payment.public_key') }}</label>
            <input type="text" name="public_key" class="form-control st-mono @error('public_key') is-invalid @enderror"
                value="{{ old('public_key', $isEdit ? $payment->public_key : '') }}"
                placeholder="{{ __('settings.payment.public_key_placeholder') }}" autocomplete="off">
            @error('public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="form-text text-muted">{{ __('settings.payment.public_key_hint') }}</small>
        </div>

        <div class="form-group">
            <label>{{ __('settings.payment.secret_key') }}</label>
            <input type="password" name="secret_key" class="form-control st-mono @error('secret_key') is-invalid @enderror"
                value="" placeholder="{{ $isEdit && $payment->secret_key
                    ? $payment->maskedSecret($payment->secret_key)
                    : __('settings.payment.secret_key_placeholder') }}" autocomplete="new-password">
            @error('secret_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="form-text text-muted">
                {{ $isEdit ? __('settings.payment.secret_keep_hint') : __('settings.payment.secret_key_hint') }}
            </small>
        </div>

        <hr>

        <div class="form-group">
            <label>{{ __('settings.payment.webhook_url') }}</label>
            <input type="url" name="webhook_url" class="form-control st-mono @error('webhook_url') is-invalid @enderror"
                value="{{ old('webhook_url', $isEdit ? $payment->webhook_url : '') }}"
                placeholder="https://example.com/webhooks/payment" autocomplete="off">
            @error('webhook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="form-text text-muted">{{ __('settings.payment.webhook_url_hint') }}</small>
        </div>

        <div class="form-group">
            <label>{{ __('settings.payment.webhook_secret') }}</label>
            <input type="password" name="webhook_secret" class="form-control st-mono @error('webhook_secret') is-invalid @enderror"
                value="" placeholder="{{ $isEdit && $payment->webhook_secret
                    ? $payment->maskedSecret($payment->webhook_secret)
                    : __('settings.payment.webhook_secret_placeholder') }}" autocomplete="new-password">
            @error('webhook_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="form-text text-muted">
                {{ $isEdit ? __('settings.payment.secret_keep_hint') : __('settings.payment.webhook_secret_hint') }}
            </small>
        </div>
    </div>
</div>
