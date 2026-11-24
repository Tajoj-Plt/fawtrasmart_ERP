// Event handlers for create modal
$('#taskType').on('change', function() {
    const selectedType = $(this).val();
    $('.task-fields').hide();

    if (selectedType === 'task') {
        // "task" يتشارك اسم الكلاس المشترك .task-fields، لذا يُعامَل كحالة خاصة
        $('.plain-task-fields').show();
    } else if (selectedType) {
        $(`.${selectedType.replace('_', '-')}-fields`).show();
    }
});

// Event handlers for edit modal
$('#editTaskType').on('change', function() {
    const selectedType = $(this).val();
    $('.edit-task-fields').hide();

    if (selectedType) {
        $(`.${selectedType.replace('_', '-')}-fields`).show();
    }
});

// Add event listeners for parent task selection in create modal
$('#epicParentId').on('change', function() {
    $('#parentTaskId').val($(this).val());
});

$('#storyParentId').on('change', function() {
    $('#parentTaskId').val($(this).val());
});

$('#subTaskParentId').on('change', function() {
    $('#parentTaskId').val($(this).val());
});

$('#bugParentId').on('change', function() {
    $('#parentTaskId').val($(this).val());
});

// Add event listeners for parent task selection in edit modal
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

// Ensure modals close properly
$(document).on('click', '[data-bs-dismiss="modal"]', function() {
    var modalId = $(this).closest('.modal').attr('id');
    $('#' + modalId).modal('hide');
});

// Function to load components
function loadComponents() {
    // نحن لا نحتاج لتحميل المكونات عبر AJAX لأننا نقوم بتمريرها مباشرة من الكونترولر
    // ولكن نحتفظ بهذه الدالة للتوافق مع بقية الكود
    return;
}

// Function to load epics based on component - not needed anymore as we load them directly
function loadEpics(componentId = null) {
    return;
}

// Function to load user stories based on epic - not needed anymore as we load them directly
function loadUserStories(epicId = null) {
    return;
}

// Helper function to update dropdowns
function updateDropdown(selectId, data, defaultOption) {
    const select = $(selectId);
    select.empty();
    select.append(`<option value="">${defaultOption}</option>`);

    data.forEach(item => {
        select.append($('<option></option>').val(item.id).text(item.title));
    });
}