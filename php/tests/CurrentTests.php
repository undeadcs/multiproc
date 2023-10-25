<?php
namespace multiproc\tests;

use PHPUnit\Framework\TestCase;
use multiproc\process\Managed;

/**
 * Testing current context class
 */
class CurrentTests extends TestCase {
	public function testStart( ) : void {
		$current = new Managed( '', '', posix_getpid( ) );
		
		$this->assertTrue( $current->Start( ) );
		$this->assertEquals( getcwd( ), $current->GetWorkingDir( ) );
		$this->assertEquals( posix_getpid( ), $current->GetPid( ) );
		$this->assertEquals( cli_get_process_title( ), $current->GetCommand( ) );
	}
}
