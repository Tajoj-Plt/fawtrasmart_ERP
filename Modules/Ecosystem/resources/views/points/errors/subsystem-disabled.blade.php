@extends('ecosystem::master')

@section('content')
<div class="content-body">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center py-5">
                    <!-- أيقونة التعطيل -->
                    <div class="mb-3">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #f9ca24, #f0932b); display: inline-flex; align-items: center; justify-content: center;">
                            <i class="feather icon-slash" style="font-size: 48px; color: #fff;"></i>
                        </div>
                    </div>

                    <h2 class="text-warning mb-3">{{ $subsystemLabel ?? 'النظام الفرعي' }} معطّل</h2>
                    <p class="text-muted mb-4" style="font-size: 1.1rem;">
                        <strong>{{ $subsystemLabel ?? 'هذا النظام الفرعي' }}</strong> غير مفعّل حالياً.
                        <br>
                        يمكنك تفعيله من صفحة الإعدادات العامة لنظام النقاط.
                    </p>

                    @php
                        $subsystemIcons = [
                            'tiers' => 'trending-up',
                            'rewards' => 'gift',
                            'achievements' => 'award',
                            'challenges' => 'target',
                            'referrals' => 'users',
                            'expiry' => 'clock',
                            'notifications' => 'bell',
                        ];
                        $icon = $subsystemIcons[$subsystem ?? ''] ?? 'alert-triangle';
                    @endphp

                    <div class="alert alert-light border mb-4" style="display: inline-block;">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-{{ $icon }} text-warning mr-2" style="font-size: 20px;"></i>
                            <span>للتفعيل: الإعدادات العامة ← الأنظمة الفرعية ← فعّل "{{ $subsystemLabel ?? 'النظام' }}"</span>
                        </div>
                    </div>

                    {{-- عرض الأنظمة الفرعية المفعّلة --}}
                    @if(isset($settings) && $settings->getActiveSubsystems())
                        @php $active = $settings->getActiveSubsystems(); @endphp
                        @if(count($active) > 0)
                            <div class="mb-4">
                                <small class="text-muted d-block mb-2">الأنظمة الفرعية المفعّلة حالياً:</small>
                                @php $labels = $settings->getSubsystemLabels(); @endphp
                                @foreach($active as $activeSub)
                                    <span class="badge badge-success px-2 py-1 mx-1" style="font-size: 0.85rem;">
                                        <i class="feather icon-check mr-1"></i>{{ $labels[$activeSub] ?? $activeSub }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <div class="mt-4">
                        <a href="{{ route('ecosystem.points.general-settings.index') }}" class="btn btn-warning btn-lg px-5">
                            <i class="feather icon-settings mr-1"></i>
                            الإعدادات العامة
                        </a>
                        <a href="javascript:history.back();" class="btn btn-outline-secondary btn-lg px-4 ml-2">
                            <i class="feather icon-arrow-right mr-1"></i>
                            رجوع
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('ecosystem.dashboard') }}" class="text-muted">
                            <i class="feather icon-arrow-right mr-1"></i>
                            العودة للوحة التحكم
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
