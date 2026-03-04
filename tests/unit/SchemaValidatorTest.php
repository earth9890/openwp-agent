<?php

namespace OpenWP\Tests\Unit;

use OpenWP\Inc\Utils\Schema_Validator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase {
	public function test_validates_required_and_sanitizes_values() {
		$validator = new Schema_Validator();
		$schema    = [
			'required' => [ 'post_id', 'title' ],
			'properties' => [
				'post_id' => [ 'type' => 'integer' ],
				'title'   => [ 'type' => 'string' ],
			],
			'additionalProperties' => false,
		];

		$result = $validator->validate(
			$schema,
			[
				'post_id' => '42',
				'title'   => '<b>Hello</b>',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['post_id'] );
		$this->assertSame( 'Hello', $result['title'] );
	}

	public function test_rejects_unknown_fields_when_not_allowed() {
		$validator = new Schema_Validator();
		$schema    = [
			'properties' => [
				'name' => [ 'type' => 'string' ],
			],
			'additionalProperties' => false,
		];

		$result = $validator->validate( $schema, [ 'name' => 'ok', 'unexpected' => 'x' ] );
		$this->assertInstanceOf( '\\WP_Error', $result );
	}
}
