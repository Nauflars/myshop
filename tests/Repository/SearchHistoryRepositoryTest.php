<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Entity\SearchHistory;
use App\Repository\SearchHistoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchHistoryRepository
 */
class SearchHistoryRepositoryTest extends TestCase
{
    public function testSaveCallsPersistAndFlush(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $searchHistory = new SearchHistory($user, 'test query');
        
        $manager->expects($this->once())
            ->method('persist')
            ->with($searchHistory);
        
        $manager->expects($this->once())
            ->method('flush');
        
        $repository = new SearchHistoryRepository($registry);
        $repository->save($searchHistory);
    }
}
