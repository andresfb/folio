<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelArchivable\Archivable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property-read string $id
 * @property-read string $workspace_id
 * @property string $user_id
 * @property string $slug
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property CarbonInterface|null $archived_at
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Workspace $workspace
 * @property-read User $user
 */
final class Project extends Model
{
    use Archivable;
    use HasFactory;
    use HasSlug;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $guarded = [];

    protected $keyType = 'string';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(50)
            ->extraScope(fn ($builder) => $builder->where('workspace_id', $this->workspace_id))
            ->useSuffixOnFirstOccurrence();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
