@extends('sitting::master')

@section('title')
    {{ __('settings.smtp.page_title') }}
@stop

@section('content')
<style>
    .smtp-wrapper {
        padding: 1.5rem;
        background-color: #f6f7fb;
        border-radius: 16px;
    }

    .smtp-card {
        background-color: #ffffff;
        border-radius: 18px;
        padding: 2rem;
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
        border: 1px solid rgba(226, 232, 240, 0.7);
        margin-bottom: 1.5rem;
    }

    .form-label-custom {
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-control-custom {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control-custom:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }
</style>

<div class="content-body smtp-wrapper" dir="rtl">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
        <div>
            <h2 class="font-weight-bold text-dark d-flex align-items-center gap-2 mb-1">
                <i class="fas fa-envelope text-primary"></i>
                {{ __('settings.smtp.title') }}
            </h2>
            <p class="text-muted mb-0">{{ __('settings.smtp.subtitle') }}</p>
        </div>
    </div>

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- المستخدم لا يستطيع معرفة أي إعداد يُرسل به النظام فعلاً: الشاشة كانت
         تحفظ في جدول لا يقرؤه أحد، والإرسال يبقى على ملف البيئة. هذا الشريط
         يقول الحقيقة صراحةً في كل مرة. --}}
    @if (($activeMailer['source'] ?? 'env') === 'database')
        <div class="alert d-flex align-items-start"
            style="background:#eaf8f0;border:1px solid #c7e9d5;color:#1c6b40;border-radius:10px">
            <i class="fas fa-check-circle mt-1 ml-2"></i>
            <div>
                <strong>{{ __('settings.smtp.active_title') }}</strong>
                <div style="font-size:.88rem;margin-top:2px">
                    {{ __('settings.smtp.server') }}: <code>{{ $activeMailer['host'] ?: '—' }}</code>
                    &nbsp;•&nbsp; {{ __('settings.smtp.sender') }}: <code>{{ $activeMailer['from'] ?: '—' }}</code>
                </div>
            </div>
        </div>
    @else
        <div class="alert d-flex align-items-start"
            style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
            <i class="fas fa-info-circle mt-1 ml-2"></i>
            <div>
                <strong>{{ __('settings.smtp.env_title') }}</strong>
                <div style="font-size:.88rem;margin-top:2px">
                    {!! __('settings.smtp.env_hint', ['host' => '<code>'.($activeMailer['host'] ?: '—').'</code>']) !!}
                </div>
            </div>
        </div>
    @endif


    <div class="row">
        <!-- Form Section -->
        <div class="col-lg-8">
            <div class="smtp-card">
                <form action="{{ route('SMPT.store') }}" method="POST" id="smtp-form">
                    @csrf
                    
                    <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                        <h4 class="font-weight-bold text-dark mb-0">{{ __('settings.smtp.server_details') }}</h4>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="use_smtp" id="use_smtp" value="1" {{ old('use_smtp', $smtpSettings->use_smtp ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label font-weight-bold" for="use_smtp">{{ __('settings.smtp.enable') }}</label>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">SMTP Host</label>
                            <input type="text" name="smtp_host" value="{{ old('smtp_host', $smtpSettings->smtp_host ?? '') }}" class="form-control form-control-custom" placeholder="{{ __('settings.smtp.host_placeholder') }}" dir="ltr">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">SMTP Port</label>
                            <input type="number" name="smtp_port" value="{{ old('smtp_port', $smtpSettings->smtp_port ?? '587') }}" class="form-control form-control-custom" dir="ltr">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">{{ __('settings.smtp.encryption') }}</label>
                            <select name="smtp_encryption" class="form-control form-control-custom" dir="ltr">
                                <option value="tls" {{ (old('smtp_encryption', $smtpSettings->smtp_encryption ?? '') == 'tls') ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ (old('smtp_encryption', $smtpSettings->smtp_encryption ?? '') == 'ssl') ? 'selected' : '' }}>SSL</option>
                                <option value="" {{ (old('smtp_encryption', $smtpSettings->smtp_encryption ?? '') == '') ? 'selected' : '' }}>None</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ssl_required" id="ssl_required" value="1" {{ old('ssl_required', $smtpSettings->ssl_required ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label font-weight-bold" for="ssl_required">{{ __('settings.smtp.requires_ssl') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 pb-3 border-bottom">
                        <h4 class="font-weight-bold text-dark mb-4">{{ __('settings.smtp.auth') }}</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">{{ __('settings.smtp.username') }}</label>
                                <input type="text" name="smtp_username" value="{{ old('smtp_username', $smtpSettings->smtp_username ?? '') }}" class="form-control form-control-custom" dir="ltr">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">{{ __('settings.smtp.password') }}</label>
                                <input type="password" name="password" placeholder="{{ isset($smtpSettings->password) ? __('settings.smtp.password_keep') : __('settings.smtp.password_enter') }}" class="form-control form-control-custom" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-weight-bold text-dark mb-4">{{ __('settings.smtp.send_settings') }}</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">{{ __('settings.smtp.from_email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $smtpSettings->email ?? '') }}" class="form-control form-control-custom" required dir="ltr">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">{{ __('settings.smtp.from_name') }}</label>
                                <input type="text" name="sender_name" value="{{ old('sender_name', $smtpSettings->sender_name ?? '') }}" class="form-control form-control-custom" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" form="reset-form" class="btn btn-outline-danger" onclick="return confirm('{{ __('settings.smtp.confirm_reset') }}')">
                            <i class="fas fa-redo"></i> {{ __('settings.smtp.reset') }}
                        </button>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="fas fa-save"></i> {{ __('settings.smtp.save') }}
                        </button>
                    </div>
                </form>
                
                <form action="{{ route('SMPT.reset') }}" method="POST" id="reset-form" style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>

        <!-- Testing Section -->
        <div class="col-lg-4">
            <div class="smtp-card">
                <h4 class="font-weight-bold text-dark mb-4">
                    <i class="fas fa-tools text-secondary"></i>
                    {{ __('settings.smtp.tools') }}
                </h4>
                
                <p class="text-muted small mb-4">
                    {{ __('settings.smtp.tools_hint') }}
                </p>

                <hr class="mb-4">

                <div>
                    <label class="form-label-custom">{{ __('settings.smtp.test_to') }}</label>
                    {{-- يُملأ ببريد المستخدم الحالي: كتابته يدوياً في كل فحص عبء بلا داعٍ --}}
                    <input type="email" id="testEmail" class="form-control form-control-custom mb-3"
                        value="{{ auth()->user()->email ?? '' }}" placeholder="example@email.com" dir="ltr">
                    <button type="button" onclick="sendTestEmail()" class="btn btn-outline-primary w-100">
                        <i class="fas fa-paper-plane"></i> {{ __('settings.smtp.send_test') }}
                    </button>
                </div>
                
                <div id="testResult" class="d-none alert mt-4"></div>
            </div>
            
            <div class="smtp-card bg-primary text-white">
                <h5 class="font-weight-bold mb-3"><i class="fab fa-google"></i> {{ __('settings.smtp.gmail') }}</h5>
                <p class="mb-1"><small><strong>SMTP:</strong> smtp.gmail.com</small></p>
                <p class="mb-1"><small><strong>{{ __('settings.smtp.gmail_port') }}</strong> 587 (TLS) / 465 (SSL)</small></p>
                <hr class="border-light">
                <p class="mb-2"><small>{{ __('settings.smtp.gmail_hint') }}</small></p>
                <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="btn btn-light btn-sm text-primary w-100">{{ __('settings.layout.app_password_guide') }}</a>
            </div>
        </div>
    </div>
</div>

<script>
    async function sendTestEmail() {
        const email = document.getElementById('testEmail').value;
        const resultDiv = document.getElementById('testResult');
        
        if (!email) {
            alert(@json(__('settings.smtp.enter_email')));
            return;
        }

        resultDiv.className = 'alert alert-info mt-4';
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + @json(__('settings.smtp.sending'));
        resultDiv.classList.remove('d-none');

        try {
            const response = await fetch('{{ route("SMPT.test_email") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ email: email })
            });
            const data = await response.json();
            
            if (data.success) {
                resultDiv.className = 'alert alert-success mt-4';
                resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || @json(__('settings.smtp.sent_ok')));
            } else {
                resultDiv.className = 'alert alert-danger mt-4 text-break';
                resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> ' + (data.error || @json(__('settings.smtp.send_failed')));
            }
        } catch (error) {
            resultDiv.className = 'alert alert-danger mt-4';
            resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + @json(__('settings.smtp.system_error'));
        }
    }
</script>
@endsection
