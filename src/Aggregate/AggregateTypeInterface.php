<?php

/**
 * PSA Event Sourcing Library
 * Copyright PSA Ltd. All rights reserved.
 */

declare(strict_types=1);

namespace Psa\EventSourcing\Aggregate;

use Psa\EventSourcing\Aggregate\Exception\AggregateTypeException;

/**
 * Aggregate Type Interface
 */
interface AggregateTypeInterface
{
	/**
	 * @return null|string
	 */
	public function mappedClass(): ?string;

	/**
	 * @return string
	 */
	public function toString(): string;

	/**
	 * @return string
	 */
	public function __toString(): string;

	/**
	 * @param \Psa\EventSourcing\Aggregate\AggregateTypeInterface $aggregateType An aggregate
	 * @throws \Psa\EventSourcing\Aggregate\Exception\AggregateTypeException
	 */
	public function assert(AggregateTypeInterface $aggregateType): void;

	/**
	 * Checks if two instances of this class are equal
	 *
	 * @param \Psa\EventSourcing\Aggregate\AggregateTypeInterface $other
	 * @return bool
	 */
	public function equals(AggregateTypeInterface $other): bool;
}
