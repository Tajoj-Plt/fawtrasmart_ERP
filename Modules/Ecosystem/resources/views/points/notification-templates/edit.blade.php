@extends('ecosystem::master')

@section('title', 'تعديل قالب الإشعار')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل قالب الإشعار</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.notification-templates.index') }}">قوالب الإشعارات</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.notification-templates.update', $notification_template->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.notification-templates.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <div>
                                <a href="{{ route('ecosystem.points.notification-templates.show', $notification_template->id) }}" class="btn btn-outline-info">
                                    <i class="feather icon-eye"></i> عرض
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i> حفظ التعديلات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- نوع الحدث -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-zap"></i> نوع الحدث</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="event_type">نوع الحدث <span class="text-danger">*</span></label>
                            <select name="event_type" id="event_type" class="form-control @error('event_type') is-invalid @enderror" required>
                                <option value="">-- اختر نوع الحدث --</option>
                                @foreach($eventTypes as $key => $event)
                                    <option value="{{ $key }}"
                                            {{ old('event_type', $notification_template->event_type) == $key ? 'selected' : '' }}
                                            data-variables="{{ implode(',', $event['variables']) }}"
                                            data-description="{{ $event['description'] }}">
                                        {{ $event['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('event_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">اختر الحدث الذي سيُرسل عنده الإشعار تلقائياً</small>
                        </div>
                        <div id="eventDescription" class="alert alert-light-info" style="display: none;">
                            <i class="feather icon-info"></i> <span id="eventDescText"></span>
                        </div>
                        <div id="variablesHelp" class="alert alert-light-warning" style="display: none;">
                            <strong><i class="feather icon-code"></i> المتغيرات المتاحة:</strong>
                            <div id="variablesList" class="mt-1"></div>
                            <small class="text-muted">انقر على المتغير لنسخه ولصقه في العنوان أو المحتوى</small>
                        </div>
                    </div>
                </div>

                <!-- المحتوى بالعربية -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-edit-3"></i> المحتوى بالعربية</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="title_ar">العنوان بالعربية <span class="text-danger">*</span></label>
                            <input type="text" name="title_ar" id="title_ar"
                                   class="form-control @error('title_ar') is-invalid @enderror"
                                   value="{{ old('title_ar', $notification_template->title_ar) }}" required>
                            @error('title_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">العنوان الرئيسي للإشعار</small>
                        </div>
                        <div class="form-group">
                            <label for="body_ar">المحتوى بالعربية <span class="text-danger">*</span></label>
                            <textarea name="body_ar" id="body_ar" rows="4"
                                      class="form-control @error('body_ar') is-invalid @enderror"
                                      required>{{ old('body_ar', $notification_template->body_ar) }}</textarea>
                            @error('body_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">نص الإشعار الكامل - استخدم المتغيرات بين {} للبيانات الديناميكية</small>
                        </div>
                    </div>
                </div>

                <!-- المحتوى بالإنجليزية -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-globe"></i> المحتوى بالإنجليزية (اختياري)</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="title_en">العنوان بالإنجليزية</label>
                            <input type="text" name="title_en" id="title_en"
                                   class="form-control @error('title_en') is-invalid @enderror"
                                   value="{{ old('title_en', $notification_template->title_en) }}">
                            @error('title_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="body_en">المحتوى بالإنجليزية</label>
                            <textarea name="body_en" id="body_en" rows="4"
                                      class="form-control @error('body_en') is-invalid @enderror">{{ old('body_en', $notification_template->body_en) }}</textarea>
                            @error('body_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- القنوات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-send"></i> قنوات الإرسال</h4>
                    </div>
                    <div class="card-body">
                        @php
                            $oldChannels = old('channels', $notification_template->channels ?? []);
                        @endphp
                        @foreach($channels as $channel)
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" name="channels[]" value="{{ $channel->value }}"
                                       id="channel_{{ $channel->value }}"
                                       class="custom-control-input"
                                       {{ in_array($channel->value, $oldChannels) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="channel_{{ $channel->value }}">
                                    {{ $channel->label() }}
                                </label>
                            </div>
                        @endforeach
                        @error('channels')
                            <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                        @enderror
                        <small class="text-muted d-block mt-2">حدد قنوات الإرسال المطلوبة</small>
                    </div>
                </div>

                <!-- الإعدادات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> الإعدادات</h4>
                    </div>
                    <div class="card-body">
                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">تفعيل القالب</span>
                                <label class="ios-switch">
                                    <input type="checkbox" name="is_active" value="1"
                                           {{ old('is_active', $notification_template->is_active) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عند التفعيل سيتم إرسال الإشعارات تلقائياً</small>
                        </div>
                    </div>
                </div>

                <!-- معلومات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-clock"></i> معلومات</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td class="text-muted">تاريخ الإنشاء</td>
                                <td>{{ $notification_template->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">آخر تعديل</td>
                                <td>{{ $notification_template->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">عدد الإرسال</td>
                                <td><span class="badge badge-light-primary">{{ number_format($notification_template->logs()->count()) }}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#event_type').on('change', function() {
        var selected = $(this).find(':selected');
        var description = selected.data('description');
        var variables = selected.data('variables');

        if (description) {
            $('#eventDescText').text(description);
            $('#eventDescription').show();
        } else {
            $('#eventDescription').hide();
        }

        if (variables) {
            var varsArray = variables.split(',');
            var html = '';
            varsArray.forEach(function(v) {
                html += '<code class="mr-2 mb-1 d-inline-block" style="cursor:pointer; padding: 3px 8px; background: #fff3cd; border-radius: 4px;" onclick="copyVariable(this)">{' + v.trim() + '}</code> ';
            });
            $('#variablesList').html(html);
            $('#variablesHelp').show();
        } else {
            $('#variablesHelp').hide();
        }
    });

    // Trigger on page load
    $('#event_type').trigger('change');
});

function copyVariable(el) {
    var text = $(el).text();
    navigator.clipboard.writeText(text).then(function() {
        toastr.info('تم نسخ: ' + text);
    });
}
</script>
@endsection
