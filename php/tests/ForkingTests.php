<?php
namespace multiproc\tests;

use PHPUnit\Framework\TestCase;
use multiproc\process\Managed;

/**
 * Testing forking process class
 */
class ForkingTests extends TestCase {
	protected function tearDown( ) : void {
		$status = 0;
		
		while( pcntl_waitpid( -1, $status, WNOHANG ) > 0 ) { // potentially infinite loop
			sleep( 1 );
		}
	}
	
	/**
	 * Test text printing at branches
	 */
	public function testPrints( ) : void {
		$commonText = "common text\n";
		$parentText = "parent text\n";
		$childText = "child text\n";
		$exitCode = 42;
	
		$current = new Managed( '', '', posix_getpid( ) );
		$child = new ForkingTestProcess( $commonText, $parentText, $childText, $exitCode, false, '', '', 0, $current );
		
		// common and parent text printed in current context
		$this->expectOutputString( $commonText.$parentText );
		$this->assertTrue( $child->Start( ) );
		$this->assertFalse( $child->IsCurrent( ) );
		$this->assertTrue( $child->Exists( ) );
		$this->assertEquals( $exitCode, $child->Wait( )->exitCode );
	}
	
	/**
	 * Test signals
	 */
	public function testSignals( ) : void {
		$current = new Managed( '', '', posix_getpid( ) );
		
		$child = new ForkingTestProcess( '', '', '', 0, true, '', '', 0, $current );
		$this->assertTrue( $child->Start( ) );
		$this->assertTrue( $child->Exists( ) );
		$this->assertTrue( $child->Stop( ) ); // sync stopping
		
		$signals = [ SIGTERM, SIGHUP, SIGUSR1 ];
		foreach( $signals as $signo ) {
			$this->assertTrue( $child->Start( ) ); // process stopped before, we can start it again
			$this->assertTrue( $child->Exists( ) );
			$this->assertTrue( $child->SendSignal( $signo ) ); // async stopping
			$info = $child->Wait( );
			$this->assertFalse( $info->normalExit );
			$this->assertTrue( $info->signalExit );
			$this->assertFalse( $info->stopExit );
			$this->assertEquals( $signo, $info->signo );
		}
	}
}
