<?php
declare(strict_types = 1);

namespace Psa\EventSourcing\EventSourcing\Aggregate;

use Psa\EventSourcing\EventSourcing\Aggregate\Event\EventCollection;
use Psa\EventSourcing\EventSourcing\Aggregate\Event\EventCollectionInterface;
use Psa\EventSourcing\EventSourcing\Aggregate\Exception\AggregateTypeMismatchException;
use Psa\EventSourcing\EventSourcing\Aggregate\Exception\EventTypeException;
use Psa\EventSourcing\EventSourcing\EventStoreIntegration\AggregateRootDecorator;
use Psa\EventSourcing\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Psa\EventSourcing\EventSourcing\SnapshotStore\SnapshotStoreInterface;
use Assert\Assert;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use RuntimeException;

/**
 * Aggregate Repository
 *
 * When extending this class make sure you are setting the aggregate type
 * property with your aggregate type the repository should use.
 */
abstract class AbstractAggregateRepository implements AggregateRepositoryInterface
{
	/**
	 * @var \Prooph\EventStore\EventStoreConnection
	 */
	protected $eventStore;

	/**
	 * Snapshot Store
	 *
	 * @var \Psa\EventSourcing\EventSourcing\SnapshotStore\SnapshotStoreInterface|null
	 */
	protected $snapshotStore;

	/**
	 * Aggregate Type
	 *
	 * @var string
	 */
	protected $aggregateType;

	/**
	 * Constructor
	 *
	 * @param \Prooph\EventStore\EventStoreConnection $eventStore Event Store Connection
	 */
	public function __construct(
		EventStoreConnection $eventStore,
		SnapshotStoreInterface $snapshotStore
	) {
		$this->eventStore = $eventStore;
		$this->snapshotStore = $snapshotStore;
		$this->aggregateDecorator = AggregateRootDecorator::newInstance();
	}

	/**
	 * Deletes an aggregate
	 *
	 * @param string $aggregateId Aggregate UUID
	 */
	public function delete(string $aggregateId, $hardDelete = false)
	{
		Assert::that($aggregateId)->uuid($aggregateId);
		$this->eventStore->deleteStream($aggregateId, ExpectedVersion::ANY, $hardDelete);
	}

	/**
	 * Attempts to load an aggregate from the snapshot store
	 *
	 * @param string $aggregateId Aggregate Id
	 * @return null|\Psa\EventSourcing\EventSourcing\Aggregate\EventSourcedAggregateInterface
	 */
	protected function loadFromSnapshotStore(string $aggregateId)
	{
		if (!$this->snapshotStore) {
			return null;
		}

		$snapshot = $this->snapshotStore->get($aggregateId);

		if ($snapshot === null) {
			return null;
		}

		if ($snapshot->aggregateType() !== $this->aggregateType) {
			throw AggregateTypeMismatchException::mismatch(
				$snapshot->aggregateType(),
				$this->aggregateType
			);
		}

		$lastVersion = $snapshot->lastVersion();
		$aggregateRoot = $snapshot->aggregateRoot();

		$events = $this->getEventsFromPosition($snapshot->aggregateId(), $snapshot->lastVersion() + 1);

		$this->aggregateDecorator->replayStreamEvents($aggregateRoot, $events);

		return $aggregateRoot;
	}

	/**
	 * Creates a snapshot of the aggregate
	 *
	 * @return void
	 */
	public function takeSnapshot(AggregateRoot $aggregate): void
	{
		$this->snapshotStore->store($aggregate);
	}

	/**
	 * Gets an aggregate
	 *
	 * @param string $aggregateId Aggregate UUID
	 * @return \Psa\EventSourcing\EventSourcing\Aggregate\EventSourcedAggregateInterface
	 */
	public function get(string $aggregateId): EventSourcedAggregateInterface
	{
		Assert::that($aggregateId)->uuid($aggregateId);

		if ($this->snapshotStore) {
			$result = $this->loadFromSnapshotStore($aggregateId);
			if ($result !== null) {
				return $result;
			}
		}

		$eventCollection = $this->getEventsFromPosition($aggregateId, 0);
		$aggregateType = $this->aggregateType;

		return $aggregateType::reconstituteFromHistory($eventCollection);
	}

	/**
	 * Get events from position
	 */
	protected function getEventsFromPosition(string $aggregateId, int $position = 0)
	{
		$eventsSlice = $this->eventStore->readStreamEventsForward($aggregateId, $position, 50);
		$eventCollection = new EventCollection();

		if ($eventsSlice->isEndOfStream()) {
			$this->convertEvents($eventsSlice, $eventCollection);

			return $eventCollection;
		}

		while (!$eventsSlice->isEndOfStream()) {
			$this->convertEvents($eventsSlice, $eventCollection);
			$eventsSlice = $this->eventStore->readStreamEventsForward($aggregateId, $eventsSlice->lastEventNumber() + 1, 5);
		}

		return $eventCollection;
	}

	/**
	 * @todo Refactor this? I have the feeling this can be done a lot better
	 * @param \Prooph\EventStore\StreamEventsSlice $eventsSlice Event Slice
	 * @param \Psa\EventSourcing\EventSourcing\Aggregate\Event\EventCollectionInterface Event Collection
	 * @return void
	 */
	protected function convertEvents(
		StreamEventsSlice $eventsSlice,
		EventCollectionInterface $eventCollection
	) {
		foreach ($eventsSlice->events() as $event) {
			/**
			 * @var $event \Prooph\EventStore\Internal\ResolvedEvent
			 */
			$eventClass = $event->event()->eventType();
			$metaData = json_decode($event->event()->metadata(), true);
			$payload = $event->event()->data();

			if (!class_exists($eventClass)) {
				throw EventTypeException::mappingFailed(
					$eventClass,
					$event->event()->eventNumber(),
					$event->event()->eventId()->toString()
				);
			}

			if ($event->event()->isJson()) {
				$payload = json_decode($payload, true);
			}

			/**
			 * @var $event \Psa\EventSourcing\EventSourcing\Aggregate\AggregateChangedEvent
			 */
			$event = $eventClass::occur(
				$metaData['_aggregate_id'],
				$payload
			);
			$event = $event->withMetadata($metaData);
			$event = $event->withVersion($metaData['_aggregate_version']);

			$eventCollection->add($event);
		}
	}

	/**
	 * @param \Psa\EventSourcing\EventSourcing\Aggregate\EventSourcedAggregateInterface $aggregate Aggregate
	 * @return void
	 */
	public function save(EventSourcedAggregateInterface $aggregate): void
	{
		$aggregateId = $aggregate->aggregateId();
		$aggregateType = get_class($aggregate);
		$events = $aggregate->popRecordedEvents();

		$storeEvents = [];
		foreach ($events as $event) {
			echo $event->version() . PHP_EOL;

			$storeEvents[] = new EventData(
				EventId::generate(),
				get_class($event),
				true,
				json_encode($event->payload()),
				json_encode([
					'_aggregate_id' => $aggregateId,
					'_aggregate_type' => $aggregateType,
					'_aggregate_version' => $event->version()
				])
			);
		}

		$this->eventStore->appendToStream(
			$aggregateId,
			ExpectedVersion::ANY,
			$storeEvents
		);
	}
}
