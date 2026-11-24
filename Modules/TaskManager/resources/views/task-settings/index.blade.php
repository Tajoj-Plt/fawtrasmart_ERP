@extends('taskmanager::master')

@section('title', 'إعدادات المهام')

@section('styles')
<style>
    .settings-card {
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .settings-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 10px 10px 0 0;
    }
    .settings-body {
        padding: 20px;
    }
    .form-section {
        margin-bottom: 30px;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #2196F3;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
</style>
@endsection

@section('content')
<div class="container">
    @include('taskmanager::partials.page_hero', [
        'heroTitle' => 'إعدادات',
        'heroHighlight' => 'المهام',
        'heroSubtitle' => 'خصّص إعدادات الفريق والسبرنت والإشعارات لمشروعك',
    ])

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('taskmanager.settings.store', $projectId) }}" id="taskSettingsForm">
        @csrf

        <!-- Team Configuration -->
        <div class="card settings-card">
            <div class="settings-header">
                <h3>إعدادات الفريق</h3>
            </div>
            <div class="settings-body">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="max_team_size" class="form-label">الحد الأقصى لحجم الفريق</label>
                        <input type="number" class="form-control @error('team_config.max_team_size') is-invalid @enderror"
                               id="max_team_size" name="team_config[max_team_size]"
                               value="{{ old('team_config.max_team_size', isset($settings) && isset($settings->team_config) ? ($settings->team_config['max_team_size'] ?? 10) : 10) }}">
                        @error('team_config.max_team_size')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">موافقة الفريق مطلوبة</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="team_config[require_team_approval]"
                                   @checked(old('team_config.require_team_approval', isset($settings) && isset($settings->team_config['require_team_approval']) ? $settings->team_config['require_team_approval'] : false))>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sprint Configuration -->
        <div class="card settings-card">
            <div class="settings-header">
                <h3>إعدادات السبرنت</h3>
            </div>
            <div class="settings-body">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="sprint_duration" class="form-label">مدة السبرنت</label>
                        <select class="form-select @error('sprint_config.sprint_duration') is-invalid @enderror"
                                id="sprint_duration" name="sprint_config[sprint_duration]">
                            @foreach([7 => '7 أيام', 14 => '14 يوم', 30 => '30 يوم'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('sprint_config.sprint_duration', isset($settings) && isset($settings->sprint_config) ? ($settings->sprint_config['sprint_duration'] ?? '') : '') == $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('sprint_config.sprint_duration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="sprint_start_day" class="form-label">يوم بداية السبرنت</label>
                        <select class="form-select @error('sprint_config.sprint_start_day') is-invalid @enderror"
                                id="sprint_start_day" name="sprint_config[sprint_start_day]">
                            @foreach(['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'] as $day)
                                <option value="{{ $day }}" @selected(old('sprint_config.sprint_start_day', isset($settings) && isset($settings->sprint_config) ? ($settings->sprint_config['sprint_start_day'] ?? '') : '') == $day)>
                                    {{ $day }}
                                </option>
                            @endforeach
                        </select>
                        @error('sprint_config.sprint_start_day')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="card settings-card">
            <div class="settings-header">
                <h3>إعدادات الإشعارات</h3>
            </div>
            <div class="settings-body">
                <div class="form-section">
                    @foreach([
                        'task_assigned' => 'تعيين المهام',
                        'task_completed' => 'إكمال المهام',
                        'task_overdue' => 'المهام المتأخرة'
                    ] as $key => $label)
                        <div class="mb-3">
                            <label class="form-label d-block">{{ $label }}</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notification_config[notification_types][{{ $key }}]"
                                       @checked(old("notification_config.notification_types.$key",
                                       isset($settings) && isset($settings->notification_config['notification_types']) ?
                                       ($settings->notification_config['notification_types'][$key] ?? false) : false))>
                                <span class="slider"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-5">
            <button type="submit" class="btn btn-primary btn-lg">حفظ الإعدادات</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('taskSettingsForm');

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        let isValid = true;
        const maxTeamSize = document.getElementById('max_team_size');

        if (maxTeamSize.value < 1) {
            isValid = false;
            maxTeamSize.classList.add('is-invalid');
            alert('الحد الأدنى لحجم الفريق هو 1');
        } else {
            maxTeamSize.classList.remove('is-invalid');
        }

        if (isValid) {
            this.submit();
        }
    });

    document.querySelectorAll('.toggle-switch input').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            // يمكن إضافة وظائف إضافية هنا مثل الحفظ التلقائي
        });
    });
});
</script>
@endsection
