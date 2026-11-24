// Function to refresh specific table content
function refreshTable(type) {
    $.ajax({
        url: getLocalePrefix() + '/task-manager/tasks/table/' + type,
        method: 'GET',
        success: function(response) {
            $('.' + type + '-table-container').html(response);
        },
        error: function(xhr) {
            console.error('Error refreshing table:', xhr);
        }
    });
}

// Function to handle sprint assignment with inheritance
function assignSprintWithInheritance(taskId, sprintId) {
    $.ajax({
        url: getLocalePrefix() + '/tasks/' + taskId + '/assign-sprint-with-inheritance',
        method: 'POST',
        data: {
            task_id: taskId,
            sprint_id: sprintId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'تم!',
                    text: 'تم تعيين السبرنت بنجاح للمهمة وجميع المهام التابعة لها',
                    icon: 'success'
                });
                // Refresh all tables to show updated sprint assignments
                refreshTable('components');
                refreshTable('epics');
                refreshTable('stories');
                refreshTable('subtasks');
                refreshTable('bugs');
            } else {
                Swal.fire({
                    title: 'خطأ!',
                    text: response.message || 'حدث خطأ أثناء تعيين السبرنت',
                    icon: 'error'
                });
            }
        },
        error: function(xhr) {
            Swal.fire({
                title: 'خطأ!',
                text: 'حدث خطأ أثناء تعيين السبرنت',
                icon: 'error'
            });
        }
    });
}

// Event handler for sprint assignment with inheritance
$(document).on('click', '.assign-sprint-with-inheritance', function() {
    const taskId = $(this).data('task-id');
    const taskTitle = $(this).data('task-title');
    
    // Show sprint selection modal
    $('#assignTaskToSprintModal').modal('show');
    $('#assignTaskToSprintModal').data('task-id', taskId);
    $('#assignTaskToSprintModal').data('with-inheritance', true);
    
    // Show task title for inheritance assignment
    $('#sprintAssignmentTaskTitle').text(taskTitle);
    $('#sprintAssignmentTaskTitleContainer').show();
});

// Handle sprint assignment form submission
$('#assignSprintForm').on('submit', function(e) {
    e.preventDefault();
    const taskId = $('#assignTaskToSprintModal').data('task-id');
    const sprintId = $('#assignSprintId').val();
    const withInheritance = $('#assignTaskToSprintModal').data('with-inheritance');

    if (withInheritance) {
        assignSprintWithInheritance(taskId, sprintId);
    } else {
        // Regular sprint assignment
        $.ajax({
            url: getLocalePrefix() + '/sprints/' + sprintId + '/tasks',
            method: 'POST',
            data: {
                task_id: taskId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'تم!',
                        text: 'تم تعيين السبرنت بنجاح',
                        icon: 'success'
                    });
                    refreshTable('components');
                    refreshTable('epics');
                    refreshTable('stories');
                    refreshTable('subtasks');
                    refreshTable('bugs');
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: response.message || 'حدث خطأ أثناء تعيين السبرنت',
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ أثناء تعيين السبرنت',
                    icon: 'error'
                });
            }
        });
    }
    $('#assignTaskToSprintModal').modal('hide');
    
    // Reset modal for next use
    $('#assignTaskToSprintModal').removeData('task-id');
    $('#assignTaskToSprintModal').removeData('with-inheritance');
    $('#sprintAssignmentTaskTitleContainer').hide();
});

// Handle regular assign-to-sprint button clicks
$(document).on('click', '.assign-to-sprint', function() {
    const taskId = $(this).data('task-id');
    $('#assignTaskId').val(taskId);
    $('#assignTaskToSprintModal').modal('show');
    $('#assignTaskToSprintModal').data('task-id', taskId);
    $('#assignTaskToSprintModal').data('with-inheritance', false);
    $('#sprintAssignmentTaskTitleContainer').hide();
});

// Handle sprint form submission and keep modal visible after validation errors
$(document).ready(function() {
    // Show sprint modal if there are validation errors
    if ($('#createSprintModal .is-invalid').length > 0) {
        $('#createSprintModal').modal('show');
    }

    // Handle sprint creation form submission
    $('#createSprintForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: getLocalePrefix() + '/sprints',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'تم!',
                        text: 'تم إنشاء السبرنت بنجاح',
                        icon: 'success'
                    });
                    
                    // Add the new sprint to the sprint dropdown
                    $('#assignSprintId').append(new Option(response.sprint.name, response.sprint.id));
                    
                    // Clear form and close modal
                    $('#createSprintForm')[0].reset();
                    $('#createSprintModal').modal('hide');
                    
                    // Refresh the tables to show the new sprint
                    refreshAllTables();
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: response.message,
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = 'حدث خطأ أثناء إنشاء السبرنت';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                
                Swal.fire({
                    title: 'خطأ!',
                    text: errorMessage,
                    icon: 'error'
                });
            }
        });
    });
});

// Helper function to refresh all tables
function refreshAllTables() {
    refreshTable('components');
    refreshTable('epics');
    refreshTable('stories');
    refreshTable('subtasks');
    refreshTable('bugs');
}