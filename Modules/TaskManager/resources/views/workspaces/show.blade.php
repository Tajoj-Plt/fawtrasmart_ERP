@extends('taskmanager::master')

@section('title')
    تفاصيل مساحة العمل
@stop

@section('css')
<style>
/* تصميم بسيط لبطاقة المساحة */
.workspace-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid #e3f2fd;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.workspace-header {
    color: #2196F3;
    font-weight: 600;
    margin: 0;
}

.workspace-details-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.workspace-details-table tr td:first-child {
    color: #2196F3;
    font-weight: 600;
    width: 140px;
    padding: 8px 0;
}

.workspace-details-table tr td:last-child {
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
</style>
@endsection

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل مساحة العمل</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسيه</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('workspaces.index') }}">مساحات العمل</a></li>
                        <li class="breadcrumb-item active">تفاصيل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12 d-md-block d-none">
        <div class="form-group breadcrumb-right">
            <div class="btn-group">
                @if(Auth::id() === $workspace->admin_id)
                <a href="{{ route('workspaces.edit', $workspace->id) }}" class="btn btn-primary btn-sm btn-action">
                    <i class="feather icon-edit"></i> تعديل
                </a>
                @if(($workspace->projects_count ?? 0) == 0)
                <button type="button" class="btn btn-primary btn-sm btn-action" onclick="deleteWorkspace({{ $workspace->id }})">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
                @endif
                @endif
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-12">
            <!-- معلومات مساحة العمل -->
            <div class="workspace-card">
                <h4 class="workspace-header">معلومات مساحة العمل</h4>
                <hr>
                <table class="workspace-details-table">
                    <tr>
                        <td>اسم المساحة:</td>
                        <td>{{ $workspace->title }}</td>
                    </tr>
                    <tr>
                        <td>الوصف:</td>
                        <td>{{ $workspace->description ?? 'لا يوجد وصف' }}</td>
                    </tr>
                    <tr>
                        <td>المالك:</td>
                        <td>{{ $workspace->admin->name ?? 'غير محدد' }}</td>
                    </tr>
                    <tr>
                        <td>النوع:</td>
                        <td>
                            @if($workspace->is_primary)
                                <span class="badge-primary-custom">مساحة رئيسية</span>
                            @else
                                <span class="badge-primary-custom">مساحة فرعية</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>عدد المشاريع:</td>
                        <td>{{ $workspace->projects_count ?? 0 }} مشروع</td>
                    </tr>
                    <tr>
                        <td>تاريخ الإنشاء:</td>
                        <td>{{ $workspace->created_at->format('Y/m/d H:i') }}</td>
                    </tr>
                    <tr>
                        <td>آخر تحديث:</td>
                        <td>{{ $workspace->updated_at->diffForHumans() }}</td>
                    </tr>
                </table>
            </div>

            <!-- المشاريع في المساحة -->
            <div class="workspace-card">
                <h4 class="workspace-header">المشاريع في هذه المساحة</h4>
                <hr>
                @if($workspace->projects->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>اسم المشروع</th>
                                    <th>الحالة</th>
                                    <th>الأولوية</th>
                                    <th>تاريخ البداية</th>
                                    <th>خيارات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($workspace->projects as $project)
                                <tr>
                                    <td>{{ $project->title }}</td>
                                    <td>
                                        @switch($project->status)
                                            @case('new')
                                                <span class="badge bg-warning">جديد</span>
                                                @break
                                            @case('in_progress')
                                                <span class="badge bg-primary">قيد التنفيذ</span>
                                                @break
                                            @case('on_hold')
                                                <span class="badge bg-secondary">معلق</span>
                                                @break
                                            @case('completed')
                                                <span class="badge bg-success">مكتمل</span>
                                                @break
                                            @default
                                                <span class="badge bg-dark">{{ $project->status }}</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @switch($project->priority)
                                            @case('low')
                                                <span class="badge bg-info">منخفضة</span>
                                                @break
                                            @case('medium')
                                                <span class="badge bg-primary">متوسطة</span>
                                                @break
                                            @case('high')
                                                <span class="badge bg-warning">مرتفعة</span>
                                                @break
                                            @case('urgent')
                                                <span class="badge bg-danger">عاجلة</span>
                                                @break
                                            @default
                                                <span class="badge bg-dark">{{ $project->priority }}</span>
                                        @endswitch
                                    </td>
                                    <td>{{ $project->start_date->format('Y/m/d') }}</td>
                                    <td>
                                        <a href="{{ route('projects.show', $project->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info"></i>
                        <p class="mb-0">لا توجد مشاريع في هذه المساحة بعد</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deleteWorkspace(workspaceId) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف مساحة العمل؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#2196F3',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route('workspaces.destroy', '__ID__') }}'.replace('__ID__', encodeURIComponent(workspaceId)),
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
                            window.location.href = '{{ route("workspaces.index") }}';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: 'حدث خطأ في حذف مساحة العمل'
                    });
                }
            });
        }
    });
}
</script>
@endsection