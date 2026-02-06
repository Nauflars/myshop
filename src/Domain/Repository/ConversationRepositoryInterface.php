<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Conversation;
use App\Domain\Entity\User;

interface ConversationRepositoryInterface
{
    public function save(Conversation $conversation): void;
    
    public function findById(string $id): ?Conversation;
    
    /**
     * @return Conversation[]
     */
    public function findByUser(User $user): array;
    
    /**
     * Find the most recent active conversation for a user
     */
    public function findActiveForUser(User $user): ?Conversation;
    
    public function delete(Conversation $conversation): void;
}
