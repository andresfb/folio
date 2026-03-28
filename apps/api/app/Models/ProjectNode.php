<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NodeType;
use Carbon\CarbonInterface;
use Database\Factories\ProjectNodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelArchivable\Archivable;
use Override;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property-read string $id
 * @property-read string $workspace_id
 * @property-read string $project_id
 * @property-read string $parent_id
 * @property-read string $user_id
 * @property NodeType $node_type
 * @property string $slug
 * @property string $title
 * @property float $sort_index
 * @property int $depth
 * @property CarbonInterface|null $archived_at
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Project $project
 * @property-read User $user
 * @property-read ProjectNode|null $parent
 */
final class ProjectNode extends Model
{
    use Archivable;

    /** @use HasFactory<ProjectNodeFactory> */
    use HasFactory;

    use HasSlug;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProjectNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(50)
            ->extraScope(fn (Builder $builder) => $builder->where('project_id', $this->project_id))
            ->useSuffixOnFirstOccurrence();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'node_type' => NodeType::class,
        ];
    }
}
