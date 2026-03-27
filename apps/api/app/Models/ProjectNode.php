<?php

namespace App\Models;

use App\Enums\NodeType;
use Carbon\CarbonInterface;
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
class ProjectNode extends Model
{
    use Archivable;
    use HasSlug;
    use HasUuids;
    use HasFactory;
    use SoftDeletes;

    protected $keyType = 'string';

    protected $guarded = [];

    public $incrementing = false;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(50)
            ->extraScope(fn ($builder) => $builder->where('project_id', $this->project_id))
            ->useSuffixOnFirstOccurrence();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'node_type' => NodeType::class,
        ];
    }
}
