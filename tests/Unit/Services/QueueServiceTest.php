<?php

namespace CureRx\Tests\Unit\Services;

use CureRx\Services\QueueService;
use PHPUnit\Framework\TestCase;

class QueueServiceTest extends TestCase
{
    public function testAddToQueueReturnsIdAndToken(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchColumn')->willReturn('3'); // token = 3

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->method('lastInsertId')->willReturn('7');
        $mockPdo->method('commit')->willReturn(true);

        $service = new QueueService($mockPdo);
        $result = $service->addToQueue(1, 10, 'Test Patient');

        $this->assertSame(7, $result['id']);
        $this->assertSame(3, $result['token_number']);
    }

    public function testAddToQueueRollsBackAndRethrowsOnException(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willThrowException(new \RuntimeException('DB error'));

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $service = new QueueService($mockPdo);
        $service->addToQueue(1, 10, 'Test Patient');
    }

    public function testTokenIsComputedFromFetchColumn(): void
    {
        // fetchColumn returns the next token number (COALESCE(MAX, 0) + 1)
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchColumn')->willReturn('5'); // 5th token today

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->method('lastInsertId')->willReturn('12');
        $mockPdo->method('commit')->willReturn(true);

        $result = (new QueueService($mockPdo))->addToQueue(2, 20, 'Jane Doe');

        $this->assertSame(5, $result['token_number']);
        $this->assertSame(12, $result['id']);
    }
}
