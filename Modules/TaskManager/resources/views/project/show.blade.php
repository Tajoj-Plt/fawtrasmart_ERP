@extends('taskmanager::master')

@section('title')
    تفاصيل المشروع
@stop

@section('css')
<style>
/* تصميم بسيط لبطاقة المشروع */
.project-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid #e3f2fd;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.project-header {
    color: #2196F3;
    font-weight: 600;
    margin: 0;
}

.project-details-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.project-details-table tr td:first-child {
    color: #2196F3;
    font-weight: 600;
    width: 140px;
    padding: 8px 0;
}

.project-details-table tr td:last-child {
    color: #666;
    padding: 8px 0;
}

.badge-primary-custom {
    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.btn-action {
    margin: 5px;
}

.team-members-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.team-member-badge {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 5px 15px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.team-member-badge.manager {
    background: #e3f2fd;
    border-color: #2196F3;
    font-weight: bold;
}

/* Invitation Modal Styles */
.modal-header {
    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
    color: white;
    border-radius: 8px 8px 0 0;
}

.modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.form-control, .form-select {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
}

/* Select2 dropdown styles */
.select2-container {
    width: 100% !important;
}

.select2-selection {
    border: 1px solid #ddd !important;
    border-radius: 6px !important;
    padding: 8px !important;
    height: auto !important;
}

.select2-selection__rendered {
    line-height: 1.5 !important;
}

.select2-selection__arrow {
    height: 36px !important;
}

/* Loading spinner */
.spinner-border {
    width: 1rem;
    height: 1rem;
}
</style>
@endsection

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل المشروع</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسيه</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">المشاريع</a></li>
                        <li class="breadcrumb-item active">تفاصيل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12 d-md-block d-none">
        <div class="form-group breadcrumb-right">
            <div class="btn-group">
                @if(Auth::id() === $project->created_by)
                <button type="button" class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#inviteModal">
                    <i class="feather icon-user-plus"></i> دعوة
                </button>
                <a href="{{ route('projects.edit', $project->id) }}" class="btn btn-primary btn-sm btn-action">
                    <i class="feather icon-edit"></i> تعديل
                </a>
                <button type="button" class="btn btn-primary btn-sm btn-action" onclick="deleteProject({{ $project->id }})">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-12">
            <!-- التنقل بين صفحات المشروع -->
            <ul class="nav nav-pills mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('projects.board', $project->id) }}">
                        <i class="feather icon-trello"></i> لوحة المهام
                    </a>
                </li>
                @if($project->management_type !== 'manual')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('projects.backlog', $project->id) }}">
                            <i class="feather icon-list"></i> قائمة الانتظار
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('projects.sprints', $project->id) }}">
                            <i class="feather icon-repeat"></i> السبرنتات
                        </a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('projects.task-dashboard', $project->id) }}">
                        <i class="feather icon-bar-chart-2"></i> لوحة المعلومات
                    </a>
                </li>
            </ul>

            <!-- معلومات المشروع -->
            <div class="project-card">
                <h4 class="project-header">معلومات المشروع</h4>
                <hr>
                <table class="project-details-table">
                    <tr>
                        <td>اسم المشروع:</td>
                        <td>{{ $project->title }}</td>
                    </tr>
                    <tr>
                        <td>الوصف:</td>
                        <td>{{ $project->description ?? 'لا يوجد وصف' }}</td>
                    </tr>
                    <tr>
                        <td>مساحة العمل:</td>
                        <td>{{ $project->workspace->title ?? 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>المنشئ:</td>
                        <td>{{ $project->creator->name ?? 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>طريقة الإدارة:</td>
                        <td>
                            @if($project->management_type === 'manual')
                                <span class="badge bg-secondary">مهام بسيطة</span>
                            @else
                                <span class="badge bg-primary">سكرم كامل</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>الحالة:</td>
                        <td>
                            @php
                                $statusColors = [
                                    'planning' => 'warning',
                                    'active' => 'primary',
                                    'on_hold' => 'secondary',
                                    'completed' => 'success',
                                    'cancelled' => 'dark',
                                    'archived' => 'light',
                                ];
                                $statusNames = [
                                    'planning' => 'تخطيط',
                                    'active' => 'نشط',
                                    'on_hold' => 'معلق',
                                    'completed' => 'مكتمل',
                                    'cancelled' => 'ملغي',
                                    'archived' => 'مؤرشف',
                                ];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$project->status] ?? 'dark' }}">
                                {{ $statusNames[$project->status] ?? $project->status }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>الأولوية:</td>
                        <td>
                            @php
                                $priorityColors = [
                                    'low' => 'info',
                                    'medium' => 'primary',
                                    'high' => 'warning',
                                    'urgent' => 'danger'
                                ];
                                $priorityNames = [
                                    'low' => 'منخفضة',
                                    'medium' => 'متوسطة',
                                    'high' => 'مرتفعة',
                                    'urgent' => 'عاجلة'
                                ];
                            @endphp
                            <span class="badge bg-{{ $priorityColors[$project->priority] ?? 'dark' }}">
                                {{ $priorityNames[$project->priority] ?? $project->priority }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>التقدم:</td>
                        <td>
                            <div class="progress" style="height: 10px; width: 200px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ $project->progress_percentage }}%"
                                     aria-valuenow="{{ $project->progress_percentage }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <span class="ml-2">{{ $project->progress_percentage }}%</span>
                        </td>
                    </tr>
                    <tr>
                        <td>الميزانية:</td>
                        <td>{{ number_format($project->budget ?? 0, 2) }} ريال</td>
                    </tr>
                    <tr>
                        <td>التكلفة الفعلية:</td>
                        <td>{{ number_format($project->cost ?? 0, 2) }} ريال</td>
                    </tr>
                    <tr>
                        <td>تاريخ البداية:</td>
                        <td>{{ $project->start_date ? $project->start_date->format('Y/m/d') : 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>تاريخ النهاية المتوقع:</td>
                        <td>{{ $project->end_date ? $project->end_date->format('Y/m/d') : 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>تاريخ النهاية الفعلي:</td>
                        <td>{{ $project->actual_end_date ? $project->actual_end_date->format('Y/m/d') : 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>تاريخ الإنشاء:</td>
                        <td>{{ $project->created_at->format('Y/m/d H:i') }}</td>
                    </tr>
                    <tr>
                        <td>آخر تحديث:</td>
                        <td>{{ $project->updated_at->diffForHumans() }}</td>
                    </tr>
                </table>
            </div>

            <!-- أعضاء الفريق -->
            <div class="project-card">
                <h4 class="project-header">أعضاء الفريق</h4>
                <hr>
                @if($project->users->count() > 0)
                    <div class="team-members-container">
                        {{-- من رفض الدعوة أو انتهت دعوتُه ليس عضواً ولا مدعوّاً --}}
                        @foreach($project->users->filter(fn ($member) => in_array($member->pivot->status ?? 'active', ['active', 'pending'], true)) as $user)
                            <div class="team-member-badge {{ $user->pivot->role === 'manager' ? 'manager' : '' }}">
                                <i class="feather icon-user"></i>
                                {{ $user->name }}
                                @if($user->pivot->role === 'manager')
                                    <span class="badge bg-primary">مدير</span>
                                @elseif($user->pivot->role === 'viewer')
                                    <span class="badge bg-secondary">مراقب</span>
                                @endif
                                @if(($user->pivot->status ?? 'active') === 'pending')
                                    <span class="badge bg-warning">دعوة معلّقة</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info"></i>
                        <p class="mb-0">لا يوجد أعضاء في الفريق بعد</p>
                    </div>
                @endif
            </div>

            <!-- المهام في المشروع -->
            <div class="project-card">
                <h4 class="project-header">المهام في هذا المشروع</h4>
                <hr>
                <div class="alert alert-info text-center">
                    <i class="feather icon-info"></i>
                    <p class="mb-0">سيتم عرض المهام هنا عند إضافتها</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invitation Modal -->
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inviteModalLabel">دعوة مستخدم إلى المشروع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="inviteForm">
                    @csrf
                    <div class="mb-3">
                        <label for="user_id" class="form-label">اختر المستخدم</label>
                        <select class="form-control select2" id="user_id" name="user_id" required>
                            <option value="">اختر مستخدم من النظام</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" data-email="{{ $user->email }}">
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">اختر مستخدم من قائمة المستخدمين المسجلين في النظام</div>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">الدور</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="member">عضو</option>
                            <option value="manager">مدير</option>
                            <option value="viewer">مشاهد</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">رسالة (اختيارية)</label>
                        <textarea class="form-control" id="message" name="message" rows="3" placeholder="أضف رسالة مخصصة للدعوة..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">إرسال الدعوة</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Initialize Select2
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "اختر مستخدم...",
        allowClear: true,
        width: '100%'
    });
});

// Handle invitation form submission
document.getElementById('inviteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form
    const userId = document.getElementById('user_id').value;
    if (!userId) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: 'يرجى اختيار مستخدم من القائمة',
            timer: 3000,
            showConfirmButton: false
        });
        return;
    }

    const formData = new FormData(this);
    const projectId = {{ $project->id }};
    
    // Show loading indicator
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الإرسال...';
    submitButton.disabled = true;

    fetch('{{ route('projects.invite', $project->id) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Raw response:', response);
        
        // Check content type
        const contentType = response.headers.get('content-type');
        console.log('Content-Type:', contentType);
        
        // Handle different response status codes
        if (response.status === 200) {
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.log('Non-JSON response text:', text);
                    throw new Error('الخادم أرجع رد غير متوقع: ' + text);
                });
            }
        } else if (response.status === 403) {
            return response.json().then(data => {
                throw new Error(data.message || 'غير مخول للقيام بهذا الإجراء');
            });
        } else if (response.status === 422) {
            return response.json().then(data => {
                const errorMessage = data.message || 'بيانات غير صحيحة';
                const errorDetails = data.errors ? Object.values(data.errors).flat().join(', ') : '';
                throw new Error(errorMessage + (errorDetails ? ': ' + errorDetails : ''));
            });
        } else if (response.status >= 500) {
            return response.json().then(data => {
                throw new Error(data.message || 'حدث خطأ في الخادم');
            }).catch(() => {
                throw new Error('حدث خطأ في الخادم. يرجى المحاولة لاحقاً');
            });
        } else {
            throw new Error('حدث خطأ غير متوقع. رمز الحالة: ' + response.status);
        }
    })
    .then(data => {
        console.log('Parsed response data:', data);
        
        if (data && data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم!',
                text: data.message || 'تم إرسال الدعوة بنجاح',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('inviteModal'));
            if (modal) {
                modal.hide();
            }
            
            // Reset the form
            document.getElementById('inviteForm').reset();
            
            // Reset Select2
            $('.select2').val(null).trigger('change');
        } else {
            throw new Error(data.message || 'حدث خطأ أثناء إرسال الدعوة');
        }
    })
    .catch(error => {
        console.error('Error caught:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: error.message || 'حدث خطأ أثناء إرسال الدعوة',
            timer: 4000,
            showConfirmButton: true
        });
    })
    .finally(() => {
        // Always restore button state
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
});

function deleteProject(projectId) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف المشروع؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#2196F3',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route('projects.destroy', $project->id) }}',
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحذف!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '{{ route("projects.index") }}';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: 'حدث خطأ في حذف المشروع: ' + (xhr.responseJSON?.message || error)
                    });
                }
            });
        }
    });
}
</script>
@endsection