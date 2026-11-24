// تهيئة التطبيق عند تحميل الصفحة
$(document).ready(function() {
    // تهيئة Select2 للمستخدمين
    $('.user-select').select2({
        placeholder: 'اختر المستخدمين...',
        allowClear: true,
        multiple: true
    });

    // عند تغيير القيم في أي حقل فلتر
    $('.filter-select').on('change', function() {
        reloadTasks();
    });

    // إضافة مستمع للنقر على زر تطبيق الفلتر
    $('#applyFilters').on('click', function() {
        reloadTasks();
    });

    // عند تغيير تعيين المستخدمين
    $('.user-select').on('change', function() {
        const taskId = $(this).data('task-id');
        const selectedUsers = $(this).val();
        
        if (taskId && selectedUsers) {
            updateAssignedUsers(taskId, selectedUsers);
        }
    });

    // تحميل المهام عند بدء التطبيق
    reloadTasks();
});

// تحديث المستخدمين المعينين مع المهام الفرعية
function updateAssignedUsers(taskId, userIds) {
    $.ajax({
        url: `/api/backlog/tasks/${taskId}/update-assigned-users`,
        method: 'POST',
        data: {
            user_ids: userIds,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                // عرض رسالة نجاح
                toastr.success(response.message);
                // إعادة تحميل المهام لتحديث العرض
                reloadTasks();
            } else {
                toastr.error(response.message || 'حدث خطأ أثناء تحديث المستخدمين المعينين');
            }
        },
        error: function(xhr) {
            toastr.error('حدث خطأ أثناء تحديث المستخدمين المعينين');
            console.error('Error:', xhr);
        }
    });
}

// إعادة تحميل المهام مع تطبيق الفلاتر
function reloadTasks() {
    // جمع قيم الفلاتر
    const filters = {
        sprint_id: $('#sprintFilter').val(),
        user_id: $('#userFilter').val(),
        priority: $('#priorityFilter').val(),
        status: $('#statusFilter').val(),
        project_id: $('#projectFilter').val(),
        component_id: $('#componentFilter').val(),
        epic_id: $('#epicFilter').val(),
        story_id: $('#storyFilter').val(),
        search: $('#searchInput').val(),
        start_date: $('#startDateFilter').val(),
        due_date: $('#dueDateFilter').val()
    };

    // إضافة رمز التحميل
    $('#tasksContainer').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');

    // طلب AJAX لجلب المهام المفلترة
    $.ajax({
        url: '/api/backlog/sprints-and-tasks',
        method: 'GET',
        data: filters,
        success: function(response) {
            if (response.success) {
                // تحديث عرض المهام
                updateTasksView(response);
            } else {
                toastr.error('حدث خطأ أثناء تحميل المهام');
            }
        },
        error: function(xhr) {
            toastr.error('حدث خطأ أثناء تحميل المهام');
            console.error('Error:', xhr);
        }
    });
}

// تحديث عرض المهام في الواجهة
function updateTasksView(data) {
    // هنا تنفيذ منطق عرض المهام في الواجهة
    // يمكنك تخصيص هذه الدالة حسب هيكل واجهتك
}