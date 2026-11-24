<?php

namespace Tests\Feature\TaskManager;

use App\Mail\ProjectInviteMail;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * الدعوات والعضوية وحذف المشروع.
 */
class ProjectMembershipTest extends TaskManagerTestCase
{
    private function row(int $projectId, int $userId): ?object
    {
        return DB::table('project_user')->where('project_id', $projectId)->where('user_id', $userId)->first();
    }

    public function test_inviting_an_existing_member_is_refused_not_a_server_error(): void
    {
        Mail::fake();
        $owner = $this->member();
        $project = $this->project($owner);
        $member = $this->member();
        // عضوٌ من نموذج الإنشاء: صفٌّ بلا بريد — وهو ما كان الفحص بالبريد لا يراه
        $this->join($project, $member);

        $this->actingAs($owner)
            ->postJson("/projects/{$project->id}/invite", ['user_id' => $member->id, 'role' => 'member'])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_declined_invitation_can_be_sent_again(): void
    {
        Mail::fake();
        $owner = $this->member();
        $project = $this->project($owner);
        $invitee = $this->member();
        $this->join($project, $invitee, 'member', 'declined', ['email' => $invitee->email, 'invite_token' => 'old-token']);

        $this->actingAs($owner)
            ->postJson("/projects/{$project->id}/invite", ['user_id' => $invitee->id, 'role' => 'viewer'])
            ->assertOk()
            ->assertJsonPath('data.email_sent', true);

        $row = $this->row($project->id, $invitee->id);
        $this->assertSame('pending', $row->status);
        $this->assertSame('viewer', $row->role);
        $this->assertNotSame('old-token', $row->invite_token);
        $this->assertSame(1, DB::table('project_user')->where('project_id', $project->id)->where('user_id', $invitee->id)->count());

        Mail::assertSent(ProjectInviteMail::class);
    }

    public function test_project_manager_who_is_not_the_creator_can_invite_and_a_plain_member_cannot(): void
    {
        Mail::fake();
        $project = $this->project($this->member());
        $manager = $this->member();
        $plain = $this->member();
        $this->join($project, $manager, 'manager');
        $this->join($project, $plain);

        $this->actingAs($manager)
            ->postJson("/projects/{$project->id}/invite", ['user_id' => $this->member()->id, 'role' => 'member'])
            ->assertOk();

        $this->actingAs($plain)
            ->postJson("/projects/{$project->id}/invite", ['user_id' => $this->member()->id, 'role' => 'member'])
            ->assertForbidden();
    }

    public function test_failed_invitation_email_is_reported_to_the_sender(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $owner = $this->member();
        $project = $this->project($owner);
        $invitee = $this->member();

        $this->actingAs($owner)
            ->postJson("/projects/{$project->id}/invite", ['user_id' => $invitee->id, 'role' => 'member'])
            ->assertOk()
            ->assertJsonPath('data.email_sent', false);

        $this->assertSame('pending', $this->row($project->id, $invitee->id)->status);
    }

    public function test_invitation_mail_carries_no_fake_password(): void
    {
        $html = (new ProjectInviteMail([
            'inviter_name' => 'أحمد',
            'project_title' => 'مشروع',
            'workspace_title' => 'مساحة',
            'role' => 'عضو',
            'invite_message' => null,
            'accept_url' => 'https://example.test/invitations/abc/accept',
        ]))->render();

        $this->assertStringNotContainsString('كلمة المرور المؤقتة', $html);
    }

    public function test_updating_a_project_keeps_invited_members_and_pending_invitations(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $joined = $this->member();     // انضمّ بدعوة: بريدٌ وتاريخ انضمام
        $pending = $this->member();    // دعوةٌ لم تُقبل بعد
        $viewer = $this->member();
        $removed = $this->member();
        $added = $this->member();

        $this->join($project, $joined, 'member', 'active', ['email' => $joined->email, 'joined_at' => '2026-02-01 10:00:00']);
        $this->join($project, $pending, 'member', 'pending', ['email' => $pending->email, 'invite_token' => 'tok-1', 'expires_at' => now()->addDay()]);
        $this->join($project, $viewer, 'viewer');
        $this->join($project, $removed);

        $this->actingAs($owner)->putJson("/projects/{$project->id}", [
            'title' => 'اسم جديد',
            'workspace_id' => $project->workspace_id,
            'status' => 'active',
            'priority' => 'high',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            // المراقب مُختار أيضاً: كان إدراجه «عضواً» يصطدم بالقيد الفريد
            'team_members' => [$joined->id, $viewer->id, $added->id],
        ])->assertOk();

        $this->assertSame('اسم جديد', $project->fresh()->title);

        $this->assertSame($joined->email, $this->row($project->id, $joined->id)->email);
        $this->assertSame('2026-02-01 10:00:00', $this->row($project->id, $joined->id)->joined_at);
        $this->assertSame('pending', $this->row($project->id, $pending->id)->status);
        $this->assertSame('viewer', $this->row($project->id, $viewer->id)->role);
        $this->assertNull($this->row($project->id, $removed->id));
        $this->assertSame('active', $this->row($project->id, $added->id)->status);
        $this->assertSame('manager', $this->row($project->id, $owner->id)->role);
    }

    public function test_updating_without_the_members_field_leaves_the_team_alone(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $member = $this->member();
        $this->join($project, $member);

        $payload = [
            'title' => 'اسم', 'workspace_id' => $project->workspace_id, 'status' => 'active',
            'priority' => 'low', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ];

        $this->actingAs($owner)->putJson("/projects/{$project->id}", $payload)->assertOk();
        $this->assertNotNull($this->row($project->id, $member->id));

        // القائمة معروضةٌ وأُفرغت: يُزال الجميع
        $this->actingAs($owner)->putJson("/projects/{$project->id}", $payload + ['team_members_present' => 1])->assertOk();
        $this->assertNull($this->row($project->id, $member->id));
    }

    public function test_deleting_a_project_removes_everything_under_it(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $other = $this->project($owner);
        $sprint = $this->sprint($project);
        $task = $this->task($project, ['sprint_id' => $sprint->id]);
        $kept = $this->task($other);
        $task->assignedUsers()->attach($owner->id);
        Comment::create(['user_id' => $owner->id, 'commentable_type' => Task::class, 'commentable_id' => $task->id, 'content' => 'تعليق']);
        $owner->forceFill(['active_project_id' => $project->id])->save();

        $this->actingAs($owner)->deleteJson("/projects/{$project->id}")->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('task_user', ['task_id' => $task->id]);
        $this->assertSame(0, Comment::where('commentable_id', $task->id)->count());
        $this->assertNull($owner->fresh()->active_project_id);

        // والمشروع المجاور لا يُمسّ
        $this->assertDatabaseHas('tasks', ['id' => $kept->id]);
    }
}
