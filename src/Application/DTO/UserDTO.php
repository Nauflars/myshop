<?php

namespace App\Application\DTO;

final readonly class UserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
        public string $createdAt
    ) {
    }
}
