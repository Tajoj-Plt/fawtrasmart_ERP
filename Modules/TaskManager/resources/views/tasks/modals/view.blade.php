<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" data-current-user-id="{{ Auth::id() }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content task-view-modal" data-task-id="">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span id="viewTaskTypeBadge" class="task-type-badge"></span>
                    <span id="viewTaskPriorityBadge" class="badge"></span>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h4 id="viewTaskTitle" class="mb-3"></h4>

                <div class="view-meta-grid">
                    <div class="view-meta-item">
                        <i class="feather icon-flag"></i>
                        <div><span class="label d-block">الحالة</span><span id="viewTaskStatus" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-calendar"></i>
                        <div><span class="label d-block">تاريخ الاستحقاق</span><span id="viewTaskDueDate" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-play"></i>
                        <div><span class="label d-block">تاريخ البدء</span><span id="viewTaskStartDate" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-clock"></i>
                        <div><span class="label d-block">الساعات المقدرة</span><span id="viewTaskEstimatedHours" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-folder"></i>
                        <div><span class="label d-block">المشروع</span><span id="viewTaskProject" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-repeat"></i>
                        <div><span class="label d-block">السبرنت</span><span id="viewTaskSprint" class="value"></span></div>
                    </div>
                    <div class="view-meta-item">
                        <i class="feather icon-corner-up-left"></i>
                        <div><span class="label d-block">المهمة الأب</span><span id="viewTaskParent" class="value"></span></div>
                    </div>
                </div>

                <div class="mt-3">
                    <h6 class="text-muted">الوصف</h6>
                    <p id="viewTaskDescription" class="mb-0"></p>
                </div>

                <div class="mt-3">
                    <h6 class="text-muted">المعينون</h6>
                    <div id="viewTaskAssignedUsers" class="task-assignees"></div>
                </div>

                <hr>

                <div class="task-comments-section">
                    <h6 class="d-flex align-items-center gap-1 mb-2">
                        <i class="feather icon-message-circle"></i> التعليقات
                        <span id="viewTaskCommentsCount" class="badge badge-pill badge-light-secondary">0</span>
                    </h6>
                    <div id="viewTaskCommentsList" class="comments-list"></div>
                    <div class="add-comment-box mt-2">
                        <textarea id="newCommentContent" class="form-control" rows="2" placeholder="أضف تعليقاً..."></textarea>
                        <div class="text-end mt-1">
                            <button type="button" class="btn btn-primary btn-sm" id="submitCommentBtn">
                                <i class="feather icon-send"></i> إرسال
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="viewTaskEditBtn">
                    <i class="feather icon-edit"></i> تعديل
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<style>
.task-view-modal .view-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px;
}
.task-view-modal .view-meta-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.task-view-modal .view-meta-item i {
    color: #7367f0;
    margin-top: 3px;
}
.task-view-modal .view-meta-item .label {
    font-size: 0.75rem;
    color: #6c757d;
}
.task-view-modal .view-meta-item .value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #172b4d;
}
.task-view-modal .task-assignees .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-inline-end: 6px;
    object-fit: cover;
}
.task-view-modal .comments-list {
    max-height: 280px;
    overflow-y: auto;
}
.task-view-modal .comment-item {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}
.task-view-modal .comment-item .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    object-fit: cover;
}
.task-view-modal .comment-body {
    flex: 1;
}
.task-view-modal .comment-meta {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 8px;
}
.task-view-modal .comment-content {
    font-size: 0.9rem;
    margin: 2px 0 0;
    white-space: pre-wrap;
}
.task-view-modal .comment-delete {
    color: #ea5455;
    cursor: pointer;
    font-size: 0.75rem;
}
.task-view-modal .no-comments-hint {
    color: #adb5bd;
    font-size: 0.85rem;
    text-align: center;
    padding: 16px 0;
}
</style>
