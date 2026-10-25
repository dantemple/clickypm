<?php

namespace Tests\Feature\Settings;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), ['avatar' => $file]);
    }

    public function test_a_photo_can_be_uploaded()
    {
        $this->upload(UploadedFile::fake()->image('me.png', 200, 200))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Photo updated.']);

        $path = $this->user->refresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        // Stored under a hashed name, never the one the browser sent.
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringNotContainsString('me.png', $path);
        $this->assertMatchesRegularExpression('/^avatars\/[A-Za-z0-9]{40}\.png$/', $path);
    }

    public function test_the_avatar_url_is_shared_with_the_frontend()
    {
        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', null));

        $this->upload(UploadedFile::fake()->image('me.jpg'));

        $path = $this->user->refresh()->avatar_path;
        $url = route('avatars.show', [$this->user, basename($path)]);

        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.avatar', $url)
                ->missing('auth.user.avatar_path'));

        $this->assertArrayNotHasKey('avatar_path', $this->user->toArray());
    }

    public function test_photos_on_a_local_disk_are_served_to_signed_in_users_only()
    {
        $this->upload(UploadedFile::fake()->image('me.png'));

        $path = $this->user->refresh()->avatar_path;
        $url = route('avatars.show', [$this->user, basename($path)]);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // The wrong file name for this user gets nothing.
        $this->actingAs($viewer)
            ->get(route('avatars.show', [$this->user, 'other.png']))
            ->assertNotFound();
    }

    public function test_a_file_that_is_not_an_image_is_rejected()
    {
        $this->upload(UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        // Renaming a script to .png doesn't get it through: the detected type
        // counts. (The fake file helper guesses the type from the name, so
        // this one is a real upload of a real temp file.)
        $script = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($script, '<?php echo 1;');
        $this->upload(new UploadedFile($script, 'fake.png', 'image/png', null, true))
            ->assertSessionHasErrors('avatar');

        // SVG can carry scripts, so it isn't accepted as a photo.
        $this->upload(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->user->refresh()->avatar_path);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_an_oversized_photo_is_rejected()
    {
        $this->upload(UploadedFile::fake()->image('huge.png')->size(User::AVATAR_MAX_KILOBYTES + 1))
            ->assertSessionHasErrors(['avatar' => 'The photo must be 2 MB or smaller.']);

        $this->assertNull($this->user->refresh()->avatar_path);
    }

    public function test_replacing_a_photo_deletes_the_previous_file()
    {
        $this->upload(UploadedFile::fake()->image('first.png'));
        $first = $this->user->refresh()->avatar_path;

        $this->upload(UploadedFile::fake()->image('second.webp'))->assertSessionHasNoErrors();
        $second = $this->user->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_a_photo_can_be_removed()
    {
        $this->upload(UploadedFile::fake()->image('me.png'));
        $path = $this->user->refresh()->avatar_path;

        $this->actingAs($this->user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Photo removed.']);

        $this->assertNull($this->user->refresh()->avatar_path);
        $this->assertNull($this->user->avatar);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_the_account_deletes_the_photo()
    {
        $this->upload(UploadedFile::fake()->image('me.png'));
        $path = $this->user->refresh()->avatar_path;

        $this->actingAs($this->user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertNull($this->user->fresh());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_workspace_members_see_each_others_photos()
    {
        $this->upload(UploadedFile::fake()->image('me.png'));

        $team = Team::factory()->create();
        $team->members()->attach($this->user, ['role' => TeamRole::Member->value]);
        $viewer = User::factory()->create();
        $team->members()->attach($viewer, ['role' => TeamRole::Admin->value]);
        $viewer->switchTeam($team);

        $this->actingAs($viewer)
            ->get(route('teams.edit', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members', fn ($members) => collect($members)->contains(fn ($member) => $member['id'] === $this->user->id
                    && $member['avatar'] === $this->user->fresh()->avatar)));
    }

    public function test_photos_on_s3_are_handed_out_through_short_lived_signed_links()
    {
        config(['filesystems.attachments' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration) => 'https://files.example.test/'.$path.'?expires='.$expiration->getTimestamp(),
        );

        $this->upload(UploadedFile::fake()->image('me.png'))->assertSessionHasNoErrors();

        $path = $this->user->refresh()->avatar_path;
        Storage::disk('s3')->assertExists($path);
        Storage::disk('local')->assertMissing($path);

        $url = $this->user->avatar;

        $this->assertStringStartsWith('https://files.example.test/'.$path.'?expires=', $url);
        $this->assertLessThanOrEqual(now()->addHour()->getTimestamp(), (int) str($url)->after('expires=')->toString());

        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', $url));

        $this->actingAs($this->user)->delete(route('profile.avatar.destroy'));
        Storage::disk('s3')->assertMissing($path);
    }
}
