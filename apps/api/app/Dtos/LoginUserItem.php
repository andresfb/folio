<?php

declare(strict_types=1);

namespace App\Dtos;

use Spatie\LaravelData\Data;

final class LoginUserItem extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public string $client,
        public string $ipAddress,
        public string $agent,
    ) {}

    public function withClientInfo(string $ipAddress, string $agent): self
    {
        return new self(
            $this->email,
            $this->password,
            $this->client,
            $ipAddress,
            $agent
        );
    }
}
