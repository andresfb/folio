<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read string $id
 * @property string $user_id
 * @property AccessType $type
 * @property string $ip_address
 * @property string $agent
 * @property CarbonInterface $login_at
 */
final class UserAccess extends Model
{
    use HasUuids;
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'access_type' => AccessType::class,
            'login_at' => 'timestamp',
        ];
    }
}
