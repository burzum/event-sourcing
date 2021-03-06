<?php

declare(strict_types=1);

namespace Psa\EventSourcing\Test\TestCase\SnapshotStore\Serializer;

use PHPUnit\Framework\TestCase;
use Psa\EventSourcing\SnapshotStore\Serializer\SerializeSerializer;

/**
 * SerializeSerializerTest
 */
class SerializeSerializerTest extends TestCase
{
	/**
	 * testSerializer
	 *
	 * @return void
	 */
	public function testSerializer(): void
	{
		$data = [
			'test' => 'value'
		];
		$serializer = new SerializeSerializer();
		$result = $serializer->serialize($data);
		$this->assertIsString('string', $result);

		$result = $serializer->unserialize($result);
		$this->assertEquals($data, $result);
	}
}
