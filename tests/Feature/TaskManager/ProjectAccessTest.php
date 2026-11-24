<?php

namespace Tests\Feature\TaskManager;

use App\Models\Task;

/**
 * عزل المشاريع: الصلاحية تقول «يفعل»، والعضوية تقول «على أيّ مشروع».
 *
 * كان من يملك `tasks.items.*` يقرأ مهامّ كل المشاريع ويعدّلها.
 */
class ProjectAccessTest extends TaskManagerTestCase
{
    public function test_outsider_with_task_permissions_cannot_read_another_projects_task(): void
    {
        $owner = $this->member();
        $task = $this->task($this->project($owner));

        $this->actingAs($this->member())->getJson("/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($owner)->getJson("/tasks/{$task->id}")->assertOk()->assertJsonPath('task.id', $task->id);
        $this->actingAs($this->admin())->getJson("/tasks/{$task->id}")->assertOk();
    }

    public function test_task_lists_only_include_visible_projects(): void
    {
        $owner = $this->member();
        $mine = $this->task($this->project($owner), ['type' => 'task']);
        $theirs = $this->task($this->project($this->member()), ['type' => 'task']);

        $ids = collect($this->actingAs($owner)->getJson('/tasks/plain-tasks')->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_viewer_reads_but_cannot_write(): void
    {
        $project = $this->project($this->member());
        $task = $this->task($project);
        $viewer = $this->member();
        $this->join($project, $viewer, 'viewer');

        $this->actingAs($viewer)->getJson("/tasks/{$task->id}")->assertOk();
        $this->actingAs($viewer)->patchJson("/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/tasks/{$task->id}")->assertForbidden();

        $this->assertSame('not_started', $task->fresh()->status);
    }

    public function test_pending_invitee_is_not_a_member_yet(): void
    {
        $project = $this->project($this->member());
        $task = $this->task($project);
        $invited = $this->member();
        $this->join($project, $invited, 'member', 'pending');

        $this->actingAs($invited)->getJson("/tasks/{$task->id}")->assertForbidden();
    }

    public function test_reorder_refuses_tasks_of_another_project(): void
    {
        $task = $this->task($this->project($this->member()));

        $this->actingAs($this->member())
            ->patchJson('/tasks/reorder', ['status' => 'completed', 'task_ids' => [$task->id]])
            ->assertForbidden();

        $this->assertSame('not_started', $task->fresh()->status);
    }

    public function test_reorder_into_another_column_needs_the_status_permission(): void
    {
        $permissions = array_values(array_diff(self::MEMBER_PERMISSIONS, ['tasks.items.update_status']));
        $user = $this->userWith($permissions);
        $task = $this->task($this->project($user));

        $this->actingAs($user)
            ->patchJson('/tasks/reorder', ['status' => 'completed', 'task_ids' => [$task->id]])
            ->assertForbidden();

        // داخل العمود نفسه ترتيبٌ لا تغييرُ حالة
        $this->actingAs($user)
            ->patchJson('/tasks/reorder', ['status' => 'not_started', 'task_ids' => [$task->id]])
            ->assertOk();
    }

    public function test_project_pages_are_closed_to_outsiders(): void
    {
        $project = $this->project($this->member());
        $outsider = $this->member();

        $this->actingAs($outsider)->get("/projects/{$project->id}")->assertForbidden();
        $this->actingAs($outsider)->deleteJson("/projects/{$project->id}")->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_legacy_unguarded_settings_routes_are_gone(): void
    {
        $project = $this->project($this->member());

        // مستخدمٌ بلا أي صلاحية كان يصل إلى هذه المسارات
        $this->actingAs($this->userWith([]))
            ->get("/projects/{$project->id}/task-settings")
            ->assertNotFound();
    }

    public function test_csv_export_contains_visible_tasks_only(): void
    {
        $owner = $this->member();
        $this->task($this->project($owner), ['title' => 'ظاهرة-للمالك']);
        $this->task($this->project($this->member()), ['title' => 'مخفيّة-عن-المالك']);

        $csv = $this->actingAs($owner)->post('/task-reports/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('ظاهرة-للمالك', $csv);
        $this->assertStringNotContainsString('مخفيّة-عن-المالك', $csv);
    }

    public function test_error_responses_do_not_leak_file_and_line(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);

        $response = $this->actingAs($owner)->postJson('/tasks', [
            'type' => 'component',
            'title' => str_repeat('ط', 300),
            'priority' => 'medium',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(422)->assertJsonMissingPath('file')->assertJsonMissingPath('line');
        $this->assertSame(0, Task::count());
    }
}
