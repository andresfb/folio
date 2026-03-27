<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Enums\AccessType;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class AccessItem extends Data
{
    public function __construct(
        public readonly string $userId,
        public readonly AccessType $type,
        public readonly string $ipAddress,
        public readonly string $agent,
        public readonly CarbonInterface $loginAt,
    ) {}
}
