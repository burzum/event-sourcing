<?php

/**
 * PSA Event Sourcing Library
 * Copyright PSA Ltd. All rights reserved.
 */

declare(strict_types=1);

namespace Psa\EventSourcing\SnapshotStore;

use DateTimeImmutable;
use Psa\EventSourcing\Aggregate\AggregateTypeProviderInterface;
use Psa\EventSourcing\Aggregate\EventSourcedAggregateInterface;

/**
 * Snapshot Factory
 */
interface SnapshotFactoryInterface
{
	/**
	 * Creates a snapshop from an aggregat implementing EventSourcedAggregateInterface
	 *
	 * @throws \Exception
	 * @param \Psa\EventSourcing\Aggregate\EventSourcedAggregateInterface $aggregate Aggregate
	 * @return \Psa\EventSourcing\SnapshotStore\SnapshotInterface
	 */
	public static function fromEventSourcedAggregate(EventSourcedAggregateInterface $aggregate): SnapshotInterface;
}
