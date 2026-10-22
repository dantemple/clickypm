<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $client;

    private Team $team;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->client = User::factory()->create();
        $this->team->members()->attach($this->client, ['role' => TeamRole::Client->value]);
        $this->project = Project::factory()->for($this->team)->create();
        $this->task = Task::factory()->for($this->project)->create();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function upload(User $user, array $files): TestResponse
    {
        return $this->actingAs($user)
            ->post(route('projects.tasks.attachments.store', [$this->team, $this->project, $this->task]), ['files' => $files]);
    }

    private function route(string $name, Attachment $attachment): string
    {
        return route($name, [$this->team, $this->project, $this->task, $attachment]);
    }

    public function test_files_can_be_attached_downloaded_and_previewed()
    {
        $this->upload($this->owner, [
            UploadedFile::fake()->image('mockup.png'),
            UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        [$image, $pdf] = $this->task->attachments;

        Storage::disk('local')->assertExists($image->path);
        $this->assertSame('mockup.png', $image->name);
        $this->assertSame($this->owner->id, $image->user_id);
        $this->assertSame(['created', 'attachment_added', 'attachment_added'], $this->task->activities()->pluck('type')->all());

        $this->actingAs($this->owner)
            ->get($this->route('projects.tasks.attachments.download', $pdf))
            ->assertOk()
            ->assertDownload('brief.pdf');

        $this->actingAs($this->owner)
            ->get($this->route('projects.tasks.attachments.preview', $image))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->owner)
            ->get($this->route('projects.tasks.attachments.preview', $pdf))
            ->assertNotFound();
    }

    public function test_svgs_and_other_active_content_are_never_shown_inline()
    {
        $this->upload($this->owner, [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]);

        $svg = $this->task->attachments()->firstOrFail();

        $this->assertFalse($svg->isPreviewable());
        $this->actingAs($this->owner)
            ->get($this->route('projects.tasks.attachments.preview', $svg))
            ->assertNotFound();
    }

    public function test_the_project_page_lists_the_open_tasks_attachments()
    {
        $this->upload($this->owner, [UploadedFile::fake()->image('mockup.png')]);

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $this->task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.attachments_count', 1)
                ->has('attachments', 1)
                ->where('attachments.0.name', 'mockup.png')
                ->whereNot('attachments.0.preview_url', null)
                ->where('maxUploadMegabytes', 10),
            );
    }

    public function test_files_over_the_size_limit_are_rejected()
    {
        $this->upload($this->owner, [UploadedFile::fake()->create('huge.zip', Attachment::MAX_KILOBYTES + 1)])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, $this->task->attachments()->count());
    }

    public function test_clients_can_attach_files_and_remove_only_their_own()
    {
        $this->upload($this->client, [UploadedFile::fake()->image('screenshot.png')])->assertRedirect();
        $this->upload($this->owner, [UploadedFile::fake()->image('team.png')]);

        [$clientFile, $teamFile] = $this->task->attachments;

        $this->actingAs($this->client)
            ->delete($this->route('projects.tasks.attachments.destroy', $teamFile))
            ->assertForbidden();

        $this->actingAs($this->client)
            ->delete($this->route('projects.tasks.attachments.destroy', $clientFile))
            ->assertRedirect();

        $this->assertModelMissing($clientFile);
        Storage::disk('local')->assertMissing($clientFile->path);

        $this->actingAs($this->owner)
            ->delete($this->route('projects.tasks.attachments.destroy', $teamFile))
            ->assertRedirect();
    }

    public function test_clients_cannot_reach_files_in_hidden_projects()
    {
        $this->upload($this->owner, [UploadedFile::fake()->create('pricing.xlsx', 10)]);
        $this->project->update(['hidden_from_clients' => true]);

        $this->actingAs($this->client)
            ->get($this->route('projects.tasks.attachments.download', $this->task->attachments()->firstOrFail()))
            ->assertForbidden();
    }

    public function test_attachments_are_scoped_to_their_task()
    {
        $other = Task::factory()->for($this->project)->create();
        $file = Attachment::store($other, UploadedFile::fake()->create('other.txt', 1), $this->owner);

        $this->actingAs($this->owner)
            ->get($this->route('projects.tasks.attachments.download', $file))
            ->assertNotFound();
    }

    public function test_client_requests_can_include_files()
    {
        $this->actingAs($this->client)
            ->post(route('projects.tasks.store', [$this->team, $this->project]), [
                'title' => 'Broken button',
                'attachments' => [UploadedFile::fake()->image('bug.png')],
            ])
            ->assertRedirect();

        $request = $this->project->tasks()->where('title', 'Broken button')->firstOrFail();

        $this->assertTrue($request->needs_triage);
        $this->assertSame(['bug.png'], $request->attachments()->pluck('name')->all());
    }

    public function test_deleting_a_task_or_project_removes_its_files()
    {
        $this->upload($this->owner, [UploadedFile::fake()->image('a.png')]);
        $path = $this->task->attachments()->value('path');

        $this->actingAs($this->owner)->delete(route('projects.tasks.destroy', [$this->team, $this->project, $this->task]));
        Storage::disk('local')->assertMissing($path);

        $other = Task::factory()->for($this->project)->create();
        $otherPath = Attachment::store($other, UploadedFile::fake()->image('b.png'), $this->owner)->path;

        $this->actingAs($this->owner)->delete(route('projects.destroy', [$this->team, $this->project]));
        Storage::disk('local')->assertMissing($otherPath);
    }

    public function test_files_on_s3_are_handed_out_through_short_lived_signed_links()
    {
        config(['filesystems.attachments' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options) => 'https://files.example.test/'.$path.'?'.http_build_query([
                'expires' => $expiration->getTimestamp(),
                ...$options,
            ]),
        );

        $this->upload($this->owner, [
            UploadedFile::fake()->image('mockup.png'),
            UploadedFile::fake()->create('Q4 brief.pdf', 20, 'application/pdf'),
        ]);

        [$image, $pdf] = $this->task->attachments;

        $this->assertSame('s3', $pdf->disk);
        Storage::disk('s3')->assertExists($pdf->path);
        Storage::disk('local')->assertMissing($pdf->path);

        $download = $this->actingAs($this->owner)->get($this->route('projects.tasks.attachments.download', $pdf));
        $download->assertRedirect();
        $location = urldecode($download->headers->get('Location'));

        $this->assertStringStartsWith('https://files.example.test/'.$pdf->path, $location);
        $this->assertStringContainsString('attachment; filename="Q4 brief.pdf"', $location);
        $this->assertStringContainsString('ResponseContentType=application/octet-stream', $location);
        $this->assertLessThanOrEqual(now()->addMinutes(5)->getTimestamp(), (int) str($location)->after('expires=')->before('&')->toString());

        $preview = $this->actingAs($this->owner)->get($this->route('projects.tasks.attachments.preview', $image));
        $this->assertStringContainsString('inline; filename=mockup.png', urldecode($preview->headers->get('Location')));
        $this->assertStringContainsString('ResponseContentType=image/png', urldecode($preview->headers->get('Location')));

        $this->actingAs($this->client)->get($this->route('projects.tasks.attachments.download', $pdf))->assertRedirect();
        $this->project->update(['hidden_from_clients' => true]);
        $this->actingAs($this->client)->get($this->route('projects.tasks.attachments.download', $pdf))->assertForbidden();

        $this->actingAs($this->owner)->delete(route('projects.destroy', [$this->team, $this->project]));
        Storage::disk('s3')->assertMissing($pdf->path);
    }

    public function test_an_upload_that_fails_to_store_leaves_no_attachment_behind()
    {
        $this->expectException(\RuntimeException::class);

        $file = \Mockery::mock(UploadedFile::fake()->create('notes.txt', 1))->makePartial();
        $file->shouldReceive('store')->andReturn(false);

        try {
            Attachment::store($this->task, $file, $this->owner);
        } finally {
            $this->assertSame(0, $this->task->attachments()->count());
        }
    }
}
