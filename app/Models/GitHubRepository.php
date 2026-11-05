<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A repository the installed GitHub App can see. Projects pick the ones
 * they follow; pull requests and pushes from other repositories in the
 * installation still link tasks, but never move them.
 *
 * @property int $id
 * @property int $github_installation_id
 * @property int $repository_id
 * @property string $full_name
 * @property bool $private
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read GitHubInstallation $installation
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['github_installation_id', 'repository_id', 'full_name', 'private'])]
class GitHubRepository extends Model
{
    protected $table = 'github_repositories';

    /**
     * The installation the repository was shared through.
     *
     * @return BelongsTo<GitHubInstallation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(GitHubInstallation::class, 'github_installation_id');
    }

    /**
     * The projects that follow this repository.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'github_repository_project', 'github_repository_id', 'project_id')->withTimestamps();
    }

    public function url(): string
    {
        return "https://github.com/{$this->full_name}";
    }

    /**
     * The repository as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toRepositoryData(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'private' => $this->private,
            'url' => $this->url(),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private' => 'boolean',
        ];
    }
}
