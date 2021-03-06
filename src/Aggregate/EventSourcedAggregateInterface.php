<?php

/**
 * PSA Event Sourcing Library
 * Copyright PSA Ltd. All rights reserved.
 */

declare(strict_types=1);

namespace Psa\EventSourcing\Aggregate;

/**
 * Event Sourced Aggregate Interface
 */
interface EventSourcedAggregateInterface extends AggregateInterface
{
	/**
	 * Returns the aggregates version
	 *
	 * @return int
	 */
	public function aggregateVersion(): int;

	/**
	 * @return array
	 */
	public function popRecordedEvents(): array;
}
