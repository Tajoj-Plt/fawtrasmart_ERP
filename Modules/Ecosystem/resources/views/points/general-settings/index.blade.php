@extends('ecosystem::master')

@section('title', 'الإعدادات العامة للنظام')

<style>
/* ========== General Settings - Modern Design ========== */
.gs-system-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.gs-system-badge.active { background: #e8f5e9; color: #2e7d32; }
.gs-system-badge.inactive { background: #ffebee; color: #c62828; }
.gs-status-pulse { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.gs-status-pulse.active { background: #2e7d32; box-shadow: 0 0 0 3px rgba(46,125,50,0.2); animation: gsPulse 2s infinite; }
.gs-status-pulse:not(.active) { background: #c62828; }
@keyframes gsPulse { 0%,100% { box-shadow: 0 0 0 3px rgba(46,125,50,0.2); } 50% { box-shadow: 0 0 0 8px rgba(46,125,50,0.05); } }

.gs-master-panel { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 0; margin-bottom: 2rem; overflow: hidden; transition: all 0.4s ease; }
.gs-master-panel.disabled { background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%); }
.gs-master-inner { display: flex; align-items: center; justify-content: space-between; padding: 1.75rem 2rem; }
.gs-master-info { display: flex; align-items: center; gap: 1rem; }
.gs-master-icon { width: 56px; height: 56px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; }
.gs-master-title { color: #fff; font-size: 1.25rem; font-weight: 700; margin: 0; }
.gs-master-desc { color: rgba(255,255,255,0.8); font-size: 0.9rem; margin: 4px 0 0; }

.gs-toggle { position: relative; display: inline-block; width: 60px; height: 32px; cursor: pointer; }
.gs-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.gs-toggle-track { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.3); border-radius: 32px; transition: 0.3s; border: 2px solid rgba(255,255,255,0.5); }
.gs-toggle-thumb { position: absolute; width: 24px; height: 24px; border-radius: 50%; background: #fff; top: 2px; right: 2px; transition: 0.3s; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
.gs-toggle input:checked + .gs-toggle-track { background: rgba(255,255,255,0.5); border-color: rgba(255,255,255,0.8); }
.gs-toggle input:checked + .gs-toggle-track .gs-toggle-thumb { transform: translateX(-28px); }

.gs-user-types { display: flex; gap: 1rem; padding: 0 2rem 1.5rem; }
.gs-user-type-card { flex: 1; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; transition: all 0.3s; cursor: pointer; }
.gs-user-type-card.active { background: rgba(255,255,255,0.25); border-color: rgba(255,255,255,0.5); }
.gs-user-type-card label { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; margin: 0; }
.gs-hidden-check { position: absolute; opacity: 0; width: 0; height: 0; }
.gs-ut-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
.gs-ut-label { color: #fff; font-weight: 600; font-size: 0.9rem; }
.gs-ut-status { color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-right: auto; }

.gs-section { background: #fff; border: 1px solid #e9ecef; border-radius: 14px; margin-bottom: 1.5rem; overflow: hidden; transition: box-shadow 0.3s; }
.gs-section:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
.gs-section-danger { border-color: #ffcdd2; }
.gs-section-header { display: flex; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f5f5f5; }
.gs-section-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.gs-section-title { font-size: 1.05rem; font-weight: 600; color: #333; margin: 0; }
.gs-section-desc { font-size: 0.82rem; color: #999; margin: 2px 0 0; }
.gs-section-body { padding: 1.25rem 1.5rem; }

.gs-subsystems-grid { display: grid; grid-template-columns: 1fr; gap: 0; }
.gs-subsystem-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #f5f5f5; transition: background 0.2s; }
.gs-subsystem-item:last-child { border-bottom: none; }
.gs-subsystem-item:hover { background: #fafafa; margin: 0 -1.5rem; padding: 14px 1.5rem; }
.gs-subsystem-item.active .gs-subsystem-name { color: #333; }
.gs-subsystem-item:not(.active) .gs-subsystem-icon { opacity: 0.5; filter: grayscale(0.6); }
.gs-subsystem-header { display: flex; align-items: center; gap: 12px; }
.gs-subsystem-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; transition: all 0.3s; }
.gs-subsystem-info { display: flex; flex-direction: column; }
.gs-subsystem-name { font-weight: 600; font-size: 0.95rem; color: #555; }
.gs-subsystem-desc { font-size: 0.78rem; color: #aaa; margin-top: 1px; }

.gs-mini-toggle { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; flex-shrink: 0; margin: 0; }
.gs-mini-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.gs-mini-track { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #e0e0e0; border-radius: 24px; transition: 0.3s; }
.gs-mini-thumb { position: absolute; width: 20px; height: 20px; border-radius: 50%; background: #fff; top: 2px; right: 2px; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.gs-mini-toggle input:checked + .gs-mini-track { background: #667eea; }
.gs-mini-toggle input:checked + .gs-mini-track .gs-mini-thumb { transform: translateX(-20px); }

.gs-field-group { margin-bottom: 1.5rem; }
.gs-field-group:last-child { margin-bottom: 0; }
.gs-field-group-label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.gs-field-group-label i { font-size: 0.85rem; }
.gs-label { font-weight: 500; font-size: 0.9rem; color: #555; margin-bottom: 6px; }
.gs-hint { font-size: 0.78rem; color: #aaa; margin-top: 4px; display: block; }
.gs-input { border-radius: 8px !important; border-color: #e0e0e0 !important; font-size: 0.95rem; }
.gs-input:focus { border-color: #667eea !important; box-shadow: 0 0 0 3px rgba(102,126,234,0.1) !important; }
.gs-textarea { resize: vertical; min-height: 100px; }
.gs-input-prefix, .gs-input-suffix { background: #f8f9fa; border-color: #e0e0e0; font-size: 0.82rem; color: #888; }
.gs-symbol-preview { position: relative; }
.gs-symbol-display { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 1.5rem; pointer-events: none; }

.gs-switches-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.gs-switch-card { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #fafafa; border-radius: 10px; border: 1px solid #f0f0f0; transition: all 0.2s; }
.gs-switch-card:hover { border-color: #e0e0e0; background: #f5f5f5; }
.gs-switch-info { display: flex; align-items: center; gap: 10px; }
.gs-switch-icon { font-size: 1.1rem; }
.gs-switch-label { font-weight: 500; font-size: 0.88rem; color: #444; display: block; }
.gs-switch-desc { font-size: 0.75rem; color: #aaa; display: block; }

.gs-sticky-sidebar { position: sticky; top: 90px; }
.gs-save-panel, .gs-nav-panel, .gs-display-panel, .gs-status-panel { background: #fff; border: 1px solid #e9ecef; border-radius: 14px; margin-bottom: 1rem; overflow: hidden; }
.gs-save-header, .gs-nav-title, .gs-display-header, .gs-status-header { padding: 14px 18px; font-weight: 600; font-size: 0.95rem; border-bottom: 1px solid #f5f5f5; display: flex; align-items: center; gap: 8px; color: #444; }
.gs-save-header { background: linear-gradient(135deg, #28a745, #48c774); color: #fff; }
.gs-save-body { padding: 16px; }
.gs-save-btn { padding: 12px; font-size: 1rem; font-weight: 600; border-radius: 10px; }
.gs-reset-btn { margin-top: 8px; border-radius: 10px; }

.gs-nav-links { padding: 8px; }
.gs-nav-link { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 8px; color: #666; font-size: 0.88rem; text-decoration: none; transition: all 0.2s; }
.gs-nav-link:hover { background: #f8f8ff; color: #667eea; text-decoration: none; }
.gs-nav-link.active { background: #f0f0ff; color: #667eea; font-weight: 600; }
.gs-nav-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.gs-display-body { padding: 12px 16px; }
.gs-display-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.88rem; color: #555; }
.gs-display-item:last-child { border-bottom: none; }
.gs-display-divider { height: 1px; background: #eee; margin: 8px 0; }

.gs-status-body { padding: 16px; }
.gs-meter-label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem; color: #666; }
.gs-meter-bar { height: 6px; background: #eee; border-radius: 6px; overflow: hidden; }
.gs-meter-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 6px; transition: width 0.5s; }
.gs-status-info { margin-top: 16px; }
.gs-status-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.85rem; border-bottom: 1px solid #f8f8f8; }
.gs-status-row:last-child { border-bottom: none; }

.text-purple { color: #8e24aa !important; }

@media (max-width: 991px) {
    .gs-master-inner { flex-direction: column; text-align: center; gap: 1rem; }
    .gs-master-info { flex-direction: column; }
    .gs-user-types { flex-direction: column; }
    .gs-switches-grid { grid-template-columns: 1fr; }
}
@media (max-width: 767px) {
    .gs-master-panel { border-radius: 12px; }
    .gs-master-inner { padding: 1.25rem; }
    .gs-user-types { padding: 0 1.25rem 1.25rem; }
}
</style>

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">الإعدادات العامة</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">الإعدادات العامة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <span class="gs-system-badge {{ $settings->system_enabled ? 'active' : 'inactive' }}" id="systemStatusBadge">
                <span class="gs-status-pulse {{ $settings->system_enabled ? 'active' : '' }}"></span>
                {{ $settings->system_enabled ? 'النظام مفعّل' : 'النظام معطّل' }}
            </span>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form method="POST" action="{{ route('ecosystem.points.general-settings.update') }}" id="generalSettingsForm">
            @csrf

            {{-- ===================== البانر الرئيسي - تحكم النظام ===================== --}}
            <div class="gs-master-panel {{ $settings->system_enabled ? '' : 'disabled' }}" id="masterPanel">
                <div class="gs-master-inner">
                    <div class="gs-master-info">
                        <div class="gs-master-icon">
                            <i class="feather icon-zap"></i>
                        </div>
                        <div>
                            <h3 class="gs-master-title">نظام النقاط والمكافآت</h3>
                            <p class="gs-master-desc">المفتاح الرئيسي — إيقافه سيعطل جميع الأنظمة الفرعية والعمليات</p>
                        </div>
                    </div>
                    <div class="gs-master-switch">
                        <label class="gs-toggle">
                            <input type="checkbox" name="system_enabled" value="1"
                                   {{ $settings->system_enabled ? 'checked' : '' }} id="masterSwitch">
                            <span class="gs-toggle-track">
                                <span class="gs-toggle-thumb"></span>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- أزرار نوع المستخدم --}}
                <div class="gs-user-types">
                    <div class="gs-user-type-card {{ $settings->enable_employee_points ? 'active' : '' }}">
                        <label>
                            <input type="checkbox" name="enable_employee_points" value="1"
                                   {{ $settings->enable_employee_points ? 'checked' : '' }} class="gs-hidden-check">
                            <div class="gs-ut-icon"><i class="feather icon-briefcase"></i></div>
                            <span class="gs-ut-label">نقاط الموظفين</span>
                            <span class="gs-ut-status">{{ $settings->enable_employee_points ? 'مفعّل' : 'معطّل' }}</span>
                        </label>
                    </div>
                    <div class="gs-user-type-card {{ $settings->enable_client_points ? 'active' : '' }}">
                        <label>
                            <input type="checkbox" name="enable_client_points" value="1"
                                   {{ $settings->enable_client_points ? 'checked' : '' }} class="gs-hidden-check">
                            <div class="gs-ut-icon"><i class="feather icon-users"></i></div>
                            <span class="gs-ut-label">نقاط العملاء</span>
                            <span class="gs-ut-status">{{ $settings->enable_client_points ? 'مفعّل' : 'معطّل' }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- ===================== العمود الرئيسي ===================== --}}
                <div class="col-lg-8">

                    {{-- الأنظمة الفرعية --}}
                    <div class="gs-section" id="subsystems">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #fff3e0; color: #f57c00;">
                                <i class="feather icon-layers"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">الأنظمة الفرعية</h4>
                                <p class="gs-section-desc">تحكم في تفعيل أو تعطيل كل نظام فرعي بشكل مستقل</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            @php
                                $subsystems = [
                                    ['field' => 'enable_tiers_system', 'label' => 'المستويات', 'icon' => 'trending-up', 'gradient' => 'linear-gradient(135deg, #ff9800, #ffb74d)', 'desc' => 'مستويات وترقيات تلقائية للمستخدمين'],
                                    ['field' => 'enable_rewards_system', 'label' => 'المكافآت', 'icon' => 'gift', 'gradient' => 'linear-gradient(135deg, #7c4dff, #b388ff)', 'desc' => 'استبدال النقاط بمكافآت وهدايا متنوعة'],
                                    ['field' => 'enable_achievements_system', 'label' => 'الإنجازات', 'icon' => 'award', 'gradient' => 'linear-gradient(135deg, #00c853, #69f0ae)', 'desc' => 'أوسمة وشارات تحفيزية'],
                                    ['field' => 'enable_challenges_system', 'label' => 'التحديات', 'icon' => 'target', 'gradient' => 'linear-gradient(135deg, #ff1744, #ff8a80)', 'desc' => 'تحديات ومنافسات جماعية وفردية'],
                                    ['field' => 'enable_referrals_system', 'label' => 'الإحالات', 'icon' => 'share-2', 'gradient' => 'linear-gradient(135deg, #00b8d4, #80deea)', 'desc' => 'برنامج إحالات للعملاء والموظفين'],
                                    ['field' => 'enable_expiry_system', 'label' => 'انتهاء الصلاحية', 'icon' => 'clock', 'gradient' => 'linear-gradient(135deg, #ff6d00, #ffab40)', 'desc' => 'إدارة انتهاء صلاحية النقاط تلقائياً'],
                                    ['field' => 'enable_notifications_system', 'label' => 'الإشعارات', 'icon' => 'bell', 'gradient' => 'linear-gradient(135deg, #0091ea, #82b1ff)', 'desc' => 'إشعارات وتنبيهات مخصصة للمستخدمين'],
                                ];
                            @endphp

                            <div class="gs-subsystems-grid">
                                @foreach($subsystems as $sub)
                                    <div class="gs-subsystem-item {{ $settings->{$sub['field']} ? 'active' : '' }}">
                                        <div class="gs-subsystem-header">
                                            <div class="gs-subsystem-icon" style="background: {{ $sub['gradient'] }};">
                                                <i class="feather icon-{{ $sub['icon'] }}"></i>
                                            </div>
                                            <div class="gs-subsystem-info">
                                                <span class="gs-subsystem-name">{{ $sub['label'] }}</span>
                                                <span class="gs-subsystem-desc">{{ $sub['desc'] }}</span>
                                            </div>
                                        </div>
                                        <label class="gs-mini-toggle">
                                            <input type="checkbox" name="{{ $sub['field'] }}" value="1"
                                                   {{ $settings->{$sub['field']} ? 'checked' : '' }}
                                                   class="subsystem-toggle">
                                            <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- إعدادات النقاط العامة --}}
                    <div class="gs-section" id="points-config">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #e8eaf6; color: #5c6bc0;">
                                <i class="feather icon-star"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">إعدادات النقاط</h4>
                                <p class="gs-section-desc">إعدادات عامة للعملة والحدود</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            <div class="gs-field-group">
                                <div class="gs-field-group-label">
                                    <i class="feather icon-type"></i> تسمية العملة
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">اسم العملة (مفرد) <span class="text-danger">*</span></label>
                                            <input type="text" name="currency_name" class="form-control gs-input"
                                                   value="{{ old('currency_name', $settings->currency_name) }}" placeholder="نقطة">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">اسم الجمع <span class="text-danger">*</span></label>
                                            <input type="text" name="currency_name_plural" class="form-control gs-input"
                                                   value="{{ old('currency_name_plural', $settings->currency_name_plural) }}" placeholder="نقاط">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">الرمز <span class="text-danger">*</span></label>
                                            <div class="gs-symbol-preview">
                                                <input type="text" name="currency_symbol" class="form-control gs-input"
                                                       value="{{ old('currency_symbol', $settings->currency_symbol) }}" placeholder="⭐"
                                                       id="symbolInput" maxlength="5">
                                                <span class="gs-symbol-display" id="symbolPreview">{{ $settings->currency_symbol ?? '⭐' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="gs-field-group">
                                <div class="gs-field-group-label">
                                    <i class="feather icon-sliders"></i> الحدود والضبط
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">الحد الأدنى للتحويل</label>
                                            <div class="input-group">
                                                <input type="number" name="min_transfer_points" class="form-control gs-input" min="0"
                                                       value="{{ old('min_transfer_points', $settings->min_transfer_points) }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text gs-input-suffix">{{ $settings->currency_symbol ?? '⭐' }}</span>
                                                </div>
                                            </div>
                                            <small class="gs-hint">أقل عدد نقاط يمكن تحويله</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">الحد اليومي للكسب</label>
                                            <div class="input-group">
                                                <input type="number" name="max_daily_earn" class="form-control gs-input" min="0"
                                                       value="{{ old('max_daily_earn', $settings->max_daily_earn) }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text gs-input-suffix">{{ $settings->currency_symbol ?? '⭐' }}</span>
                                                </div>
                                            </div>
                                            <small class="gs-hint">0 = بلا حد يومي</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="gs-label">الخانات العشرية</label>
                                            <select name="points_decimal_places" class="form-control gs-input">
                                                @for($i = 0; $i <= 4; $i++)
                                                    <option value="{{ $i }}" {{ $settings->points_decimal_places == $i ? 'selected' : '' }}>{{ $i }} خانات</option>
                                                @endfor
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- إعدادات المكافآت --}}
                    <div class="gs-section" id="rewards-config">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #f3e5f5; color: #8e24aa;">
                                <i class="feather icon-gift"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">إعدادات المكافآت</h4>
                                <p class="gs-section-desc">ضبط عمليات استبدال النقاط بالمكافآت</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            <div class="gs-switches-grid">
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-check-square gs-switch-icon text-purple"></i>
                                        <div>
                                            <span class="gs-switch-label">موافقة المدير</span>
                                            <span class="gs-switch-desc">طلب موافقة قبل الاستبدال</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="require_redemption_approval" value="1"
                                               {{ $settings->require_redemption_approval ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-scissors gs-switch-icon text-info"></i>
                                        <div>
                                            <span class="gs-switch-label">الاستبدال الجزئي</span>
                                            <span class="gs-switch-desc">استبدال جزء من قيمة المكافأة</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="allow_partial_redemption" value="1"
                                               {{ $settings->allow_partial_redemption ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="gs-label">الحد اليومي للاستبدال</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text gs-input-prefix"><i class="feather icon-repeat"></i></span>
                                            </div>
                                            <input type="number" name="max_daily_redemptions" class="form-control gs-input" min="0"
                                                   value="{{ old('max_daily_redemptions', $settings->max_daily_redemptions) }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text gs-input-suffix">طلب/يوم</span>
                                            </div>
                                        </div>
                                        <small class="gs-hint">0 = بلا حدود</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="gs-label">فترة الانتظار بين الطلبات</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text gs-input-prefix"><i class="feather icon-clock"></i></span>
                                            </div>
                                            <input type="number" name="redemption_cooldown_hours" class="form-control gs-input" min="0"
                                                   value="{{ old('redemption_cooldown_hours', $settings->redemption_cooldown_hours) }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text gs-input-suffix">ساعة</span>
                                            </div>
                                        </div>
                                        <small class="gs-hint">0 = بلا انتظار</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- إعدادات التحديات --}}
                    <div class="gs-section" id="challenges-config">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #fce4ec; color: #e53935;">
                                <i class="feather icon-target"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">إعدادات التحديات</h4>
                                <p class="gs-section-desc">ضبط المنافسات والتحديات</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            <div class="gs-switches-grid">
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-user-plus gs-switch-icon text-success"></i>
                                        <div>
                                            <span class="gs-switch-label">التسجيل التلقائي</span>
                                            <span class="gs-switch-desc">تسجيل المستخدمين تلقائياً في التحديات</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="auto_enroll_challenges" value="1"
                                               {{ $settings->auto_enroll_challenges ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-users gs-switch-icon text-primary"></i>
                                        <div>
                                            <span class="gs-switch-label">السماح بالفرق</span>
                                            <span class="gs-switch-desc">تمكين التحديات الجماعية</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="allow_challenge_teams" value="1"
                                               {{ $settings->allow_challenge_teams ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="gs-label">الحد الأقصى للتحديات النشطة</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text gs-input-prefix"><i class="feather icon-hash"></i></span>
                                            </div>
                                            <input type="number" name="max_active_challenges" class="form-control gs-input" min="1"
                                                   value="{{ old('max_active_challenges', $settings->max_active_challenges) }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text gs-input-suffix">تحدي</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- إعدادات الأمان --}}
                    <div class="gs-section gs-section-danger" id="security-config">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #ffebee; color: #c62828;">
                                <i class="feather icon-shield"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">الأمان والمراقبة</h4>
                                <p class="gs-section-desc">حماية النظام من الاحتيال والنشاط المشبوه</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            <div class="gs-switches-grid">
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-database gs-switch-icon text-info"></i>
                                        <div>
                                            <span class="gs-switch-label">تسجيل جميع المعاملات</span>
                                            <span class="gs-switch-desc">حفظ سجل كامل لكل عملية</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="log_all_transactions" value="1"
                                               {{ $settings->log_all_transactions ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-switch-card">
                                    <div class="gs-switch-info">
                                        <i class="feather icon-alert-triangle gs-switch-icon text-danger"></i>
                                        <div>
                                            <span class="gs-switch-label">كشف الاحتيال</span>
                                            <span class="gs-switch-desc">تنبيه عند أنماط مشبوهة</span>
                                        </div>
                                    </div>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="enable_fraud_detection" value="1"
                                               {{ $settings->enable_fraud_detection ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="gs-label">الحد الأقصى لكل معاملة</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text gs-input-prefix"><i class="feather icon-trending-up"></i></span>
                                            </div>
                                            <input type="number" name="max_points_per_transaction" class="form-control gs-input" min="1"
                                                   value="{{ old('max_points_per_transaction', $settings->max_points_per_transaction) }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text gs-input-suffix">{{ $settings->currency_symbol ?? '⭐' }}</span>
                                            </div>
                                        </div>
                                        <small class="gs-hint">أقصى نقاط يمكن منحها في معاملة واحدة</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="gs-label">حد التنبيه المشبوه</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text gs-input-prefix"><i class="feather icon-alert-circle"></i></span>
                                            </div>
                                            <input type="number" name="suspicious_threshold" class="form-control gs-input" min="1"
                                                   value="{{ old('suspicious_threshold', $settings->suspicious_threshold) }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text gs-input-suffix">معاملة/ساعة</span>
                                            </div>
                                        </div>
                                        <small class="gs-hint">عدد المعاملات لتفعيل تنبيه الاحتيال</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- الشروط والأحكام --}}
                    <div class="gs-section" id="terms-config">
                        <div class="gs-section-header">
                            <div class="gs-section-icon" style="background: #efebe9; color: #795548;">
                                <i class="feather icon-file-text"></i>
                            </div>
                            <div>
                                <h4 class="gs-section-title">الشروط والأحكام</h4>
                                <p class="gs-section-desc">النص المعروض للمستخدمين حول نظام النقاط</p>
                            </div>
                        </div>
                        <div class="gs-section-body">
                            <textarea name="terms_conditions" class="form-control gs-input gs-textarea" rows="5"
                                      placeholder="اكتب الشروط والأحكام هنا... (اختياري)">{{ old('terms_conditions', $settings->terms_conditions) }}</textarea>
                        </div>
                    </div>

                </div>

                {{-- ===================== الشريط الجانبي ===================== --}}
                <div class="col-lg-4">
                    <div class="gs-sticky-sidebar">
                        {{-- زر الحفظ --}}
                        <div class="gs-save-panel">
                            <div class="gs-save-header">
                                <i class="feather icon-save"></i>
                                <span>حفظ الإعدادات</span>
                            </div>
                            <div class="gs-save-body">
                                <button type="submit" class="btn btn-success btn-block gs-save-btn">
                                    <i class="feather icon-check"></i> حفظ جميع الإعدادات
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-block gs-reset-btn" onclick="location.reload()">
                                    <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        {{-- التنقل السريع --}}
                        <div class="gs-nav-panel">
                            <div class="gs-nav-title">
                                <i class="feather icon-compass"></i> تنقل سريع
                            </div>
                            <div class="gs-nav-links">
                                <a href="#subsystems" class="gs-nav-link" data-section="subsystems">
                                    <span class="gs-nav-dot" style="background: #f57c00;"></span> الأنظمة الفرعية
                                </a>
                                <a href="#points-config" class="gs-nav-link" data-section="points-config">
                                    <span class="gs-nav-dot" style="background: #5c6bc0;"></span> إعدادات النقاط
                                </a>
                                <a href="#rewards-config" class="gs-nav-link" data-section="rewards-config">
                                    <span class="gs-nav-dot" style="background: #8e24aa;"></span> المكافآت
                                </a>
                                <a href="#challenges-config" class="gs-nav-link" data-section="challenges-config">
                                    <span class="gs-nav-dot" style="background: #e53935;"></span> التحديات
                                </a>
                                <a href="#display-config" class="gs-nav-link" data-section="display-config">
                                    <span class="gs-nav-dot" style="background: #2e7d32;"></span> العرض
                                </a>
                                <a href="#security-config" class="gs-nav-link" data-section="security-config">
                                    <span class="gs-nav-dot" style="background: #c62828;"></span> الأمان
                                </a>
                                <a href="#terms-config" class="gs-nav-link" data-section="terms-config">
                                    <span class="gs-nav-dot" style="background: #795548;"></span> الشروط
                                </a>
                            </div>
                        </div>

                        {{-- إعدادات العرض --}}
                        <div class="gs-display-panel" id="display-config">
                            <div class="gs-display-header">
                                <i class="feather icon-eye"></i> إعدادات العرض
                            </div>
                            <div class="gs-display-body">
                                <div class="gs-display-item">
                                    <span>عرض النقاط في الملف</span>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="show_points_in_profile" value="1"
                                               {{ $settings->show_points_in_profile ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-display-item">
                                    <span>لوحة المتصدرين</span>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="show_leaderboard" value="1"
                                               {{ $settings->show_leaderboard ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-display-item">
                                    <span>شارة المستوى</span>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="show_tier_badge" value="1"
                                               {{ $settings->show_tier_badge ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-display-item">
                                    <span>إنجازات عامة</span>
                                    <label class="gs-mini-toggle">
                                        <input type="checkbox" name="show_achievements_public" value="1"
                                               {{ $settings->show_achievements_public ? 'checked' : '' }}>
                                        <span class="gs-mini-track"><span class="gs-mini-thumb"></span></span>
                                    </label>
                                </div>
                                <div class="gs-display-divider"></div>
                                <div class="form-group mb-0">
                                    <label class="gs-label" style="font-size: 0.85rem;">حجم لوحة المتصدرين</label>
                                    <input type="number" name="leaderboard_size" class="form-control gs-input" min="3" max="100"
                                           value="{{ old('leaderboard_size', $settings->leaderboard_size) }}" style="font-size: 0.9rem;">
                                </div>
                            </div>
                        </div>

                        {{-- حالة النظام --}}
                        <div class="gs-status-panel">
                            <div class="gs-status-header">
                                <i class="feather icon-activity"></i> حالة النظام
                            </div>
                            <div class="gs-status-body">
                                @php
                                    $activeCount = collect($subsystems)->filter(fn($s) => $settings->{$s['field']})->count();
                                    $totalCount = count($subsystems);
                                    $percentage = $totalCount > 0 ? round(($activeCount / $totalCount) * 100) : 0;
                                @endphp
                                <div class="gs-status-meter">
                                    <div class="gs-meter-label">
                                        <span>الأنظمة المفعّلة</span>
                                        <strong>{{ $activeCount }}/{{ $totalCount }}</strong>
                                    </div>
                                    <div class="gs-meter-bar">
                                        <div class="gs-meter-fill" style="width: {{ $percentage }}%;"></div>
                                    </div>
                                </div>
                                <div class="gs-status-info">
                                    <div class="gs-status-row">
                                        <span class="text-muted">آخر تحديث</span>
                                        <span>{{ $settings->updated_at ? $settings->updated_at->diffForHumans() : '—' }}</span>
                                    </div>
                                    @if($settings->last_maintenance_at)
                                    <div class="gs-status-row">
                                        <span class="text-muted">آخر صيانة</span>
                                        <span>{{ \Carbon\Carbon::parse($settings->last_maintenance_at)->diffForHumans() }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')

<script>
$(function() {
    $('#masterSwitch').on('change', function() {
        var enabled = $(this).is(':checked');
        $('#masterPanel').toggleClass('disabled', !enabled);
        var badge = $('#systemStatusBadge');
        badge.toggleClass('active', enabled).toggleClass('inactive', !enabled);
        badge.find('.gs-status-pulse').toggleClass('active', enabled);
        badge.contents().filter(function() { return this.nodeType === 3; }).remove();
        badge.append(enabled ? ' النظام مفعّل' : ' النظام معطّل');
    });

    $('.gs-user-type-card label').on('click', function() {
        var card = $(this).closest('.gs-user-type-card');
        setTimeout(function() {
            var checked = card.find('input').is(':checked');
            card.toggleClass('active', checked);
            card.find('.gs-ut-status').text(checked ? 'مفعّل' : 'معطّل');
        }, 10);
    });

    $('.subsystem-toggle').on('change', function() {
        $(this).closest('.gs-subsystem-item').toggleClass('active', $(this).is(':checked'));
    });

    $('#symbolInput').on('input', function() {
        $('#symbolPreview').text($(this).val() || '⭐');
    });

    $('.gs-nav-link').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.gs-nav-link').removeClass('active');
        $(this).addClass('active');
        $('html, body').animate({ scrollTop: $(target).offset().top - 100 }, 400);
    });

    $(window).on('scroll', function() {
        var scrollPos = $(window).scrollTop() + 120;
        $('.gs-section, .gs-display-panel').each(function() {
            var id = $(this).attr('id');
            if (id && $(this).offset().top <= scrollPos) {
                $('.gs-nav-link').removeClass('active');
                $('.gs-nav-link[data-section="' + id + '"]').addClass('active');
            }
        });
    });

    $('#generalSettingsForm').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'حفظ الإعدادات',
            text: 'هل أنت متأكد من حفظ جميع التغييرات؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'نعم، احفظ',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endsection
