// تهريب نصٍّ يكتبه المستخدم (عنوان مهمّة، اسم سبرنت…) قبل حقنه في HTML
window.tmEscape = window.tmEscape || function (value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
};

// Task Form Submission
$('#createTaskForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: getLocalePrefix() + '/tasks',
        method: 'POST',
        data: $(this).serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            toastr.success(response.message);
            $('#createTaskModal').modal('hide');
            
            // Add the new task to the table without refreshing the entire table
            addTaskToTable(response.task);
            
            // Reset the form
            $('#createTaskForm')[0].reset();
            $('.task-fields').hide();
        },
        error: function(xhr) {
            if (xhr.status === 422) {
                const errors = xhr.responseJSON.errors;
                Object.keys(errors).forEach(field => {
                    toastr.error(errors[field][0]);
                });
            } else {
                toastr.error('حدث خطأ أثناء إنشاء المهمة');
            }
        }
    });
});

// Edit Task Form Submission
$('#editTaskForm').on('submit', function(e) {
    e.preventDefault();
    const taskId = $('#editTaskId').val();
    
    $.ajax({
        url: getLocalePrefix() + `/tasks/${taskId}`,
        method: 'PUT',
        data: $(this).serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            toastr.success(response.message);
            $('#editTaskModal').modal('hide');
            
            // Update the specific task row in the table
            updateTaskRow(response.task);
        },
        error: function(xhr) {
            if (xhr.status === 422) {
                const errors = xhr.responseJSON.errors;
                Object.keys(errors).forEach(field => {
                    toastr.error(errors[field][0]);
                });
            } else {
                toastr.error('حدث خطأ أثناء تحديث المهمة');
            }
        }
    });
});

// Delete Task
function deleteTask(taskId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: "لن تتمكن من التراجع عن هذا الإجراء!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم، احذفها!',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: getLocalePrefix() + `/tasks/${taskId}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Remove the task row from the table
                    $(`tr[data-task-id="${taskId}"]`).remove();
                    
                    // Show success message
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message || 'حدث خطأ أثناء حذف المهمة');
                }
            });
        }
    });
}

// Edit Task
function editTask(taskId) {
    $.ajax({
        url: getLocalePrefix() + `/tasks/${taskId}`,
        method: 'GET',
        success: function(response) {
            const task = response.task;
            
            // Set the task ID
            $('#editTaskId').val(task.id);
            
            // Set the task type (disabled in edit)
            $('#editTaskType').val(task.type);
            
            // Set common fields
            $('#editTitle').val(task.title);
            $('#editDescription').val(task.description);
            $('#editPriority').val(task.priority);
            $('#editStatus').val(task.status);
            $('#editSprintId').val(task.sprint_id);
            $('#editStartDate').val(task.start_date);
            $('#editDueDate').val(task.due_date);
            $('#editEstimatedHours').val(task.estimated_hours);
            
            // Set assigned users
            if (task.assigned_users) {
                const assignedUserIds = task.assigned_users.map(user => user.id);
                $('#editAssignedUsers').val(assignedUserIds);
            }
            
            // Set type-specific fields based on task type and update parent task selection
            switch(task.type) {
                case 'component':
                    $('#editProjectId').val(task.project_id);
                    $('.edit-task-fields').hide();
                    $('.edit-component-fields').show();
                    break;
                case 'task':
                    $('#editProjectId').val(task.project_id);
                    $('.edit-task-fields').hide();
                    $('.edit-plain-task-fields').show();
                    break;
                case 'epic':
                    $('#editEpicParentId').val(task.parent_task_id);
                    $('#editParentTaskId').val(task.parent_task_id);
                    $('.edit-task-fields').hide();
                    $('.edit-epic-fields').show();
                    break;
                case 'user_story':
                    $('#editStoryParentId').val(task.parent_task_id);
                    $('#editParentTaskId').val(task.parent_task_id);
                    $('.edit-task-fields').hide();
                    $('.edit-user-story-fields').show();
                    break;
                case 'sub_task':
                    $('#editSubTaskParentId').val(task.parent_task_id);
                    $('#editParentTaskId').val(task.parent_task_id);
                    $('.edit-task-fields').hide();
                    $('.edit-sub-task-fields').show();
                    break;
                case 'bug':
                    $('#editBugParentId').val(task.parent_task_id);
                    $('#editParentTaskId').val(task.parent_task_id);
                    $('.edit-task-fields').hide();
                    $('.edit-bug-fields').show();
                    break;
            }
            
            // Add event listeners for parent task selection in edit modal
            // Remove any existing event listeners to prevent duplication
            $('#editEpicParentId, #editStoryParentId, #editSubTaskParentId, #editBugParentId').off('change');
            
            // Add event listeners to update the hidden parent task ID field
            $('#editEpicParentId').on('change', function() {
                $('#editParentTaskId').val($(this).val());
            });
            
            $('#editStoryParentId').on('change', function() {
                $('#editParentTaskId').val($(this).val());
            });
            
            $('#editSubTaskParentId').on('change', function() {
                $('#editParentTaskId').val($(this).val());
            });
            
            $('#editBugParentId').on('change', function() {
                $('#editParentTaskId').val($(this).val());
            });
            
            $('#editTaskModal').modal('show');
        },
        error: function() {
            toastr.error('حدث خطأ أثناء تحميل بيانات المهمة');
        }
    });
}

// Show Task Details
function showTaskDetails(taskId) {
    $.ajax({
        url: getLocalePrefix() + `/tasks/${taskId}`,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            const task = response.task;
            const priorityInfo = getPriorityInfo(task.priority);
            const statusInfo = getStatusInfo(task.status);

            $('#viewTaskModal .task-view-modal').attr('data-task-id', task.id);
            $('#viewTaskTitle').text(task.title);
            $('#viewTaskDescription').text(task.description || 'لا يوجد وصف');

            $('#viewTaskTypeBadge').text(getTypeLabel(task.type));
            $('#viewTaskPriorityBadge')
                .attr('class', 'badge badge-' + priorityInfo.class)
                .text(priorityInfo.text);

            $('#viewTaskStatus')
                .html('<span class="badge badge-' + statusInfo.class + '">' + statusInfo.text + '</span>');

            const isOverdue = task.status !== 'completed' && task.due_date && new Date(task.due_date) < new Date();
            $('#viewTaskDueDate').text(task.due_date || '-').toggleClass('text-danger fw-bold', !!isOverdue);
            $('#viewTaskStartDate').text(task.start_date || '-');
            $('#viewTaskSprint').text(task.sprint ? task.sprint.name : '-');
            $('#viewTaskProject').text(task.project ? task.project.title : '-');
            $('#viewTaskParent').text(task.parent ? task.parent.title : '-');
            $('#viewTaskEstimatedHours').text(task.estimated_hours ? task.estimated_hours + ' ساعة' : '-');

            const assigneesEl = $('#viewTaskAssignedUsers').empty();
            if (task.assigned_users && task.assigned_users.length > 0) {
                task.assigned_users.forEach(function(user) {
                    const avatarUrl = user.avatar_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=7367f0&color=fff');
                    assigneesEl.append('<img src="' + tmEscape(avatarUrl) + '" class="user-avatar" title="' + tmEscape(user.name) + '" alt="' + tmEscape(user.name) + '">');
                });
            } else {
                assigneesEl.text('لا يوجد معينون');
            }

            loadTaskComments(task.id);

            $('#viewTaskEditBtn').off('click').on('click', function() {
                $('#viewTaskModal').modal('hide');
                editTask(task.id);
            });

            $('#viewTaskModal').modal('show');
        },
        error: function() {
            toastr.error('حدث خطأ أثناء تحميل تفاصيل المهمة');
        }
    });
}

// ===== تعليقات المهمة =====

function loadTaskComments(taskId) {
    const listEl = $('#viewTaskCommentsList').html('<div class="no-comments-hint">جاري التحميل...</div>');

    $.ajax({
        url: getLocalePrefix() + `/tasks/${taskId}/comments`,
        method: 'GET',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (!response.success) {
                listEl.html('<div class="no-comments-hint">تعذّر تحميل التعليقات</div>');
                return;
            }

            $('#viewTaskCommentsCount').text(response.total || 0);
            renderComments(response.data);
        },
        error: function() {
            listEl.html('<div class="no-comments-hint">تعذّر تحميل التعليقات</div>');
        }
    });
}

function renderComments(comments) {
    const listEl = $('#viewTaskCommentsList').empty();

    if (!comments || comments.length === 0) {
        listEl.html('<div class="no-comments-hint">لا توجد تعليقات بعد — كن أول من يعلّق</div>');
        return;
    }

    comments.forEach(function(comment) {
        listEl.append(buildCommentHtml(comment));
        if (comment.replies && comment.replies.length) {
            comment.replies.forEach(function(reply) {
                listEl.append(buildCommentHtml(reply, true));
            });
        }
    });
}

function buildCommentHtml(comment, isReply) {
    const user = comment.user || { name: 'مستخدم' };
    const avatarUrl = user.avatar_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=7367f0&color=fff');
    const currentUserId = $('#viewTaskModal').data('current-user-id');
    const canDelete = currentUserId && Number(currentUserId) === Number(comment.user_id);

    const html = $('<div class="comment-item" style="' + (isReply ? 'margin-right:32px;' : '') + '"></div>');
    html.append('<img src="' + tmEscape(avatarUrl) + '" class="user-avatar" alt="' + tmEscape(user.name) + '">');

    const body = $('<div class="comment-body"></div>');
    const meta = $('<div class="comment-meta"></div>')
        .append('<strong>' + $('<span>').text(user.name).html() + '</strong>')
        .append('<span>' + (comment.created_at_human || '') + '</span>');

    if (canDelete) {
        meta.append('<span class="comment-delete" data-comment-id="' + comment.id + '"><i class="feather icon-trash-2"></i></span>');
    }

    body.append(meta);
    body.append($('<p class="comment-content"></p>').text(comment.content));
    html.append(body);

    return html;
}

function submitTaskComment() {
    const taskId = $('#viewTaskModal .task-view-modal').data('task-id');
    const content = $('#newCommentContent').val().trim();

    if (!content) {
        return;
    }

    const btn = $('#submitCommentBtn');
    btn.prop('disabled', true);

    $.ajax({
        url: getLocalePrefix() + '/comments',
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            content: content,
            commentable_type: 'App\\Models\\Task',
            commentable_id: taskId
        },
        success: function(response) {
            if (response.success) {
                $('#newCommentContent').val('');
                loadTaskComments(taskId);
            } else {
                toastr.error(response.message || 'تعذّر إضافة التعليق');
            }
        },
        error: function(xhr) {
            const message = (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ أثناء إضافة التعليق';
            toastr.error(message);
        },
        complete: function() {
            btn.prop('disabled', false);
        }
    });
}

function deleteTaskComment(commentId) {
    const taskId = $('#viewTaskModal .task-view-modal').data('task-id');

    $.ajax({
        url: getLocalePrefix() + '/comments/' + commentId,
        method: 'DELETE',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                loadTaskComments(taskId);
            } else {
                toastr.error(response.message || 'تعذّر حذف التعليق');
            }
        },
        error: function() {
            toastr.error('حدث خطأ أثناء حذف التعليق');
        }
    });
}

$(document).on('click', '#submitCommentBtn', function() {
    submitTaskComment();
});

$(document).on('click', '.comment-delete', function() {
    const commentId = $(this).data('comment-id');
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'حذف التعليق؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذفه',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#ea5455'
        }).then(function(result) {
            if (result.isConfirmed) {
                deleteTaskComment(commentId);
            }
        });
    } else if (confirm('حذف التعليق؟')) {
        deleteTaskComment(commentId);
    }
});

// Refresh Tables
function refreshTables() {
    // Instead of trying to reload DataTables (which aren't initialized),
    // we'll refresh the content using the existing AJAX filtering approach
    const activeTab = $('.nav-tabs .nav-link.active').attr('href');
    
    switch(activeTab) {
        case '#components':
            applyFilters('component');
            break;
        case '#epics':
            applyFilters('epic');
            break;
        case '#stories':
            applyFilters('user_story');
            break;
        case '#subtasks':
            applyFilters('sub_task');
            break;
        case '#bugs':
            applyFilters('bug');
            break;
        default:
            // Refresh all tabs
            applyFilters('component');
            applyFilters('epic');
            applyFilters('user_story');
            applyFilters('sub_task');
            applyFilters('bug');
    }
}

// Clear form when modal is closed
$('#createTaskModal, #editTaskModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    $('.edit-task-fields').hide();
});

// Task type change handler for edit modal
$('#editTaskType').on('change', function() {
    const selectedType = $(this).val();
    $('.edit-task-fields').hide();
    
    if (selectedType) {
        $(`.${selectedType.replace('_', '-')}-fields`).show();
    }
});

// Function to get locale prefix for URLs
function getLocalePrefix() {
    // Get the current locale from the HTML dir attribute
    var dir = $('html').attr('dir');
    // Return the locale prefix based on the direction
    if (dir === 'rtl') {
        return '/ar'; // Assuming Arabic for RTL
    } else {
        return '/en'; // Assuming English for LTR
    }
}

// Function to add a new task to the table
function addTaskToTable(task) {
    // Determine which table to add the task to based on its type
    let tableContainerId;
    let tableBodySelector;
    
    switch(task.type) {
        case 'component':
            tableContainerId = 'components-table-container';
            tableBodySelector = '#components-table-container tbody';
            break;
        case 'epic':
            tableContainerId = 'epics-table-container';
            tableBodySelector = '#epics-table-container tbody';
            break;
        case 'user_story':
            tableContainerId = 'stories-table-container';
            tableBodySelector = '#stories-table-container tbody';
            break;
        case 'sub_task':
            tableContainerId = 'subtasks-table-container';
            tableBodySelector = '#subtasks-table-container tbody';
            break;
        case 'bug':
            tableContainerId = 'bugs-table-container';
            tableBodySelector = '#bugs-table-container tbody';
            break;
        default:
            return; // Unknown task type
    }
    
    // Check if the table body exists
    if ($(tableBodySelector).length === 0) {
        // If the table body doesn't exist, we need to refresh the table
        refreshTables();
        return;
    }
    
    // Create the new row HTML
    const newRowHtml = generateTaskRowHtml(task);
    
    // Add the new row to the top of the table
    $(tableBodySelector).prepend(newRowHtml);
    
    // Apply any necessary JavaScript to the new row (e.g., tooltips, dropdowns)
    initializeNewRowComponents(task.id);
}

// Function to generate HTML for a task row
function generateTaskRowHtml(task) {
    // Get status information
    const statusInfo = getStatusInfo(task.status);
    
    // Get priority information
    const priorityInfo = getPriorityInfo(task.priority);
    
    // Get task icon
    const taskIcon = getTaskIcon(task.type);
    
    // Generate the row HTML based on task type
    let rowHtml = `<tr data-task-id="${task.id}">`;
    
    // Title column
    rowHtml += `
        <td>
            <div class="d-flex align-items-center">
                ${taskIcon}
                ${task.title}
            </div>
        </td>
    `;
    
    // Parent/Project column (varies by task type)
    if (task.type === 'component') {
        rowHtml += `<td>${task.project ? task.project.title : 'غير محدد'}</td>`;
    } else {
        rowHtml += `<td>${task.parent ? task.parent.title : '-'}</td>`;
    }
    
    // Status column
    rowHtml += `<td><span class="badge badge-${statusInfo.class}">${statusInfo.text}</span></td>`;
    
    // Priority column
    rowHtml += `<td><span class="badge badge-${priorityInfo.class}">${priorityInfo.text}</span></td>`;
    
    // Additional columns based on task type
    if (task.type === 'user_story' || task.type === 'epic') {
        rowHtml += `<td>${task.estimated_hours || '-'}</td>`;
    }
    
    // Actions column
    rowHtml += `
        <td>
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn bg-gradient-info fa fa-ellipsis-v btn-sm"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item view-task" href="javascript:void(0);"
                           data-task-id="${task.id}">
                            <i class="fa fa-eye me-2 text-primary"></i> عرض التفاصيل
                        </a>
                        <a class="dropdown-item edit-task" href="javascript:void(0);"
                           data-task-id="${task.id}">
                            <i class="fa fa-edit me-2 text-success"></i> تعديل
                        </a>
                        <a class="dropdown-item delete-task" href="javascript:void(0);"
                           data-task-id="${task.id}">
                            <i class="fa fa-trash me-2 text-danger"></i> حذف
                        </a>
    `;
    
    // Add type-specific actions
    if (task.type === 'component') {
        rowHtml += `
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask(${task.id}, 'epic')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة إبيك
                        </a>
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask(${task.id}, 'bug')">
                            <i class="fa fa-bug me-2 text-warning"></i> إضافة خطأ
                        </a>
        `;
    } else if (task.type === 'epic') {
        rowHtml += `
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask(${task.id}, 'user_story')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة قصة مستخدم
                        </a>
                        <a class="dropdown-item assign-to-sprint" href="javascript:void(0);"
                           data-task-id="${task.id}">
                            <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                        </a>
                        <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);"
                           data-task-id="${task.id}" data-task-title="${task.title}">
                            <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                        </a>
        `;
    } else if (task.type === 'user_story' || task.type === 'sub_task' || task.type === 'bug') {
        rowHtml += `
                        <a class="dropdown-item assign-to-sprint" href="javascript:void(0);"
                           data-task-id="${task.id}">
                            <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                        </a>
                        <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);"
                           data-task-id="${task.id}" data-task-title="${task.title}">
                            <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                        </a>
        `;
        if (task.type === 'user_story') {
            rowHtml += `
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask(${task.id}, 'sub_task')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة مهمة فرعية
                        </a>
            `;
        }
    }
    
    rowHtml += `
                    </div>
                </div>
            </div>
        </td>
    `;
    
    rowHtml += '</tr>';
    
    return rowHtml;
}

// Function to update a specific task row in the table
function updateTaskRow(task) {
    try {
        // Find the row in the table
        const $row = $(`tr[data-task-id="${task.id}"]`);
        
        if ($row.length > 0) {
            // Update the row content based on task type
            // Update title
            $row.find('td:first-child').html(`
                <div class="d-flex align-items-center">
                    ${getTaskIcon(task.type)}
                    ${tmEscape(task.title)}
                </div>
            `);
            
            // Update status badge
            const statusInfo = getStatusInfo(task.status);
            $row.find('td:eq(2)').html(`<span class="badge badge-${statusInfo.class}">${statusInfo.text}</span>`);
            
            // Update priority badge
            const priorityInfo = getPriorityInfo(task.priority);
            $row.find('td:eq(3)').html(`<span class="badge badge-${priorityInfo.class}">${priorityInfo.text}</span>`);
            
            // Update additional fields based on task type
            if (task.type === 'component') {
                // Update project name
                const projectName = task.project ? task.project.name : '-';
                $row.find('td:eq(1)').text(projectName);
            } else if (task.type === 'user_story') {
                // Update parent task name
                const parentName = task.parent ? task.parent.title : '-';
                $row.find('td:eq(1)').text(parentName);
                
                // Update estimated hours
                $row.find('td:eq(4)').text(task.estimated_hours || '-');
            } else if (task.type === 'epic') {
                // Update parent task name
                const parentName = task.parent ? task.parent.title : '-';
                $row.find('td:eq(1)').text(parentName);
                
                // Update estimated hours
                $row.find('td:eq(4)').text(task.estimated_hours || '-');
            } else if (task.type === 'sub_task' || task.type === 'bug') {
                // Update parent task name
                const parentName = task.parent ? task.parent.title : '-';
                $row.find('td:eq(1)').text(parentName);
            }
        } else {
            console.log('Task row not found for task ID:', task.id);
            // If row not found, refresh the tables
            refreshTables();
        }
    } catch (error) {
        console.error('Error updating task row:', error);
    }
}

// Helper function to get task icon based on type
function getTaskIcon(type) {
    switch(type) {
        case 'component':
            return '<i class="fas fa-puzzle-piece text-primary mr-2"></i>';
        case 'epic':
            return '<i class="fas fa-bookmark text-success mr-2"></i>';
        case 'user_story':
            return '<i class="fas fa-book text-info mr-2"></i>';
        case 'sub_task':
            return '<i class="fas fa-tasks text-warning mr-2"></i>';
        case 'bug':
            return '<i class="fas fa-bug text-danger mr-2"></i>';
        case 'task':
            return '<i class="fas fa-check-square text-primary mr-2"></i>';
        default:
            return '';
    }
}

// Helper function to get status information
function getStatusInfo(status) {
    switch(status) {
        case 'completed':
            return { class: 'success', text: 'مكتملة' };
        case 'in_progress':
            return { class: 'warning', text: 'قيد المعالجة' };
        case 'not_started':
            return { class: 'secondary', text: 'لم تبدء' };
        case 'overdue':
            return { class: 'danger', text: 'متأخرة' };
        default:
            return { class: 'info', text: 'غير محدد' };
    }
}

// Helper function to get priority information
function getPriorityInfo(priority) {
    switch(priority) {
        case 'urgent':
            return { class: 'danger', text: 'عاجلة' };
        case 'high':
            return { class: 'danger', text: 'عالية' };
        case 'medium':
            return { class: 'warning', text: 'متوسطة' };
        case 'low':
            return { class: 'info', text: 'منخفضة' };
        default:
            return { class: 'info', text: 'غير محددة' };
    }
}

// Helper: اسم عرض نوع المهمة (يشمل النوعين المستخدمين في المشاريع اليدوية)
function getTypeLabel(type) {
    switch (type) {
        case 'component': return 'مكون';
        case 'epic': return 'إبيك';
        case 'user_story': return 'قصة مستخدم';
        case 'sub_task': return 'مهمة فرعية';
        case 'bug': return 'خطأ برمجي';
        case 'task': return 'مهمة';
        default: return type || '-';
    }
}

// Function to initialize components for a new row
function initializeNewRowComponents(taskId) {
    // Initialize any necessary JavaScript components for the new row
    // For example, tooltips, dropdowns, etc.
    $(`tr[data-task-id="${taskId}"] [data-bs-toggle="dropdown"]`).dropdown();
    
    // Reattach event handlers for the new row
    $(`tr[data-task-id="${taskId}"] .edit-task`).on('click', function() {
        const taskId = $(this).data('task-id');
        editTask(taskId);
    });
    
    $(`tr[data-task-id="${taskId}"] .delete-task`).on('click', function() {
        const taskId = $(this).data('task-id');
        deleteTask(taskId);
    });
    
    $(`tr[data-task-id="${taskId}"] .view-task`).on('click', function() {
        const taskId = $(this).data('task-id');
        showTaskDetails(taskId);
    });
    
    $(`tr[data-task-id="${taskId}"] .assign-to-sprint`).on('click', function() {
        const taskId = $(this).data('task-id');
        // Handle assign to sprint
        $('#assignTaskId').val(taskId);
        $('#assignTaskToSprintModal').modal('show');
    });
    
    $(`tr[data-task-id="${taskId}"] .assign-sprint-with-inheritance`).on('click', function() {
        const taskId = $(this).data('task-id');
        const taskTitle = $(this).data('task-title');
        // Handle assign sprint with inheritance
        assignSprintWithInheritance(taskId, taskTitle);
    });
}

// Function to handle assign sprint with inheritance
function assignSprintWithInheritance(taskId, taskTitle) {
    // Show confirmation dialog
    Swal.fire({
        title: 'تعيين السبرنت مع الإرث',
        html: `
            <p>هل تريد تعيين السبرنت لـ "${taskTitle}" وجميع المهام الفرعية الخاصة بها؟</p>
            <div class="form-group">
                <label for="inheritanceSprintId">اختر السبرنت</label>
                <select class="form-control" id="inheritanceSprintId" required>
                    <option value="">اختر السبرنت</option>
                    @foreach($sprints as $sprint)
                        <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                    @endforeach
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'تعيين',
        cancelButtonText: 'إلغاء',
        preConfirm: () => {
            const sprintId = $('#inheritanceSprintId').val();
            if (!sprintId) {
                Swal.showValidationMessage('الرجاء اختيار سبرنت');
                return false;
            }
            return { sprintId: sprintId };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading indicator
            Swal.fire({
                title: 'جاري التعيين...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make AJAX request to assign sprint with inheritance
            $.ajax({
                url: getLocalePrefix() + '/tasks/' + taskId + '/assign-sprint-with-inheritance',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sprint_id: result.value.sprintId
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم التعيين!',
                            text: 'تم تعيين السبرنت للمهمة وجميع المهام الفرعية الخاصة بها.',
                            confirmButtonText: 'موافق'
                        });
                        
                        // Update UI without refreshing the page
                        // You can add specific UI updates here if needed
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: response.message || 'حدث خطأ أثناء تعيين السبرنت.',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: 'حدث خطأ أثناء تعيين السبرنت.',
                        confirmButtonText: 'موافق'
                    });
                }
            });
        }
    });
}
