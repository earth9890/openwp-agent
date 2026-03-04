<?php

namespace OpenWP\Tests\Unit;

use OpenWP\Inc\Security\Sql_Guard;
use PHPUnit\Framework\TestCase;

class SqlGuardTest extends TestCase {
	public function test_blocks_outfile_queries() {
		$guard  = new Sql_Guard();
		$result = $guard->inspect( "SELECT * FROM wp_users INTO OUTFILE '/tmp/x.sql'" );
		$this->assertInstanceOf( '\\WP_Error', $result );
	}

	public function test_classifies_write_query() {
		$guard  = new Sql_Guard();
		$result = $guard->inspect( 'UPDATE wp_posts SET post_title = "x" WHERE ID = 1' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['is_write'] );
		$this->assertSame( 'update', $result['statement_type'] );
	}
}
