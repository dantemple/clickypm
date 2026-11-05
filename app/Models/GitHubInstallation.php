<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The GitHub App installed on a GitHub user or organisation, connected to
 * one workspace. GitHub tells us about it (and its repositories) by webhook.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int $installation_id
 * @property string|null $account_login
 * @property string $account_type
 * @property string|null $account_avatar_url
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team|null $team
 * @property-read Collection<int, GitHubRepository> $repositories
 */
#[Fillable(['team_id', 'installation_id', 'account_login', 'account_type', 'account_avatar_url', 'suspended_at'])]
class GitHubInstallation extends Model
{
    protected $table = 'github_installations';

    /**
     * The workspace the installation is connected to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The repositories the app was given access to, by name.
     *
     * @return HasMany<GitHubRepository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(GitHubRepository::class, 'github_installation_id')->orderBy('full_name');
    }

    /**
     * Whether pull requests and pushes should be acted on.
     */
    public function isActive(): bool
    {
        return $this->team_id !== null && $this->suspended_at === null;
    }

    /**
     * Where on GitHub the installation's repositories are chosen and the
     * app can be uninstalled.
     */
    public function manageUrl(): string
    {
        return $this->account_type === 'Organization'
            ? "https://github.com/organizations/{$this->account_login}/settings/installations/{$this->installation_id}"
            : "https://github.com/settings/installations/{$this->installation_id}";
    }

    /**
     * The installation as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toInstallationData(): array
    {
        return [
            'id' => $this->id,
            'account_login' => $this->account_login,
            'account_type' => $this->account_type,
            'account_avatar_url' => $this->account_avatar_url,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'manage_url' => $this->manageUrl(),
            'repositories' => $this->repositories->map->toRepositoryData()->values()->all(),
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
            'suspended_at' => 'datetime',
        ];
    }
}
