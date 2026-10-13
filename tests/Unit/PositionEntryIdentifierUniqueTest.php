<?php

namespace Tests\Unit;

use App\Repositories\Api\PositionEntryRepository;
use App\Services\Api\PositionEntryService;
use Scaffold\Framework\Models\Api\PositionEntry;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class PositionEntryIdentifierUniqueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeModelMock(): PositionEntry
    {
        $model = Mockery::mock(PositionEntry::class);
        $model->shouldReceive('getKeyName')->andReturn('id');
        $model->shouldReceive('getTable')->andReturn('position_entry');

        return $model;
    }

    public function test_repository_excludes_current_entry_when_checking_identifier_uniqueness(): void
    {
        $expected = (object) ['id' => 9, 'identifier' => 'duplicate'];

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->once()
            ->with('identifier', '=', 'duplicate')
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->once()
            ->with('id', '<>', 7)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn($expected);

        $repo = new class($query, $this->makeModelMock()) extends PositionEntryRepository {
            public function __construct(private object $query, PositionEntry $model)
            {
                parent::__construct($model);
            }

            protected function getQuery($isWrite = false)
            {
                return $this->query;
            }
        };

        self::assertSame($expected, $repo->identifierUnique('duplicate', 7));
    }

    public function test_repository_checks_plain_identifier_uniqueness_without_exclude_id(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->once()
            ->with('identifier', '=', 'plain')
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        $repo = new class($query, $this->makeModelMock()) extends PositionEntryRepository {
            public function __construct(private object $query, PositionEntry $model)
            {
                parent::__construct($model);
            }

            protected function getQuery($isWrite = false)
            {
                return $this->query;
            }
        };

        self::assertNull($repo->identifierUnique('plain'));
    }

    public function test_service_passes_exclude_id_through_to_repository(): void
    {
        $repo = Mockery::mock(PositionEntryRepository::class);
        $repo->shouldReceive('identifierUnique')
            ->once()
            ->with('duplicate', 7)
            ->andReturn('hit');

        $service = new PositionEntryService(
            $repo,
            Mockery::mock(\App\Repositories\Blockmatrix\AdminUserRepository::class),
            Mockery::mock(\App\Repositories\Blockmatrix\UserOperationLogRepository::class)
        );

        self::assertSame('hit', $service->identifierUnique('duplicate', 7));
    }
}
