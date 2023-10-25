<?php
namespace multiproc\tests;

use PHPUnit\Framework\TestCase;
use multiproc\Process;

/**
 * Testing system process class
 */
class SystemTests extends TestCase {
	protected $externalProcess;
	protected string $command = 'system-test';
	protected string $workingDir;
	protected $externalPid = 0;
	protected int $sleep = 4;
	
	protected function setUp( ) : void {
		$this->workingDir = realpath( __DIR__.'/../' );
		
		$descriptors = [
			0 => [ 'file', '/dev/null', 'r' ],
			1 => [ 'file', '/dev/null', 'w' ],
			2 => [ 'file', '/dev/null', 'w' ]
		];
		$pipes = [ ];
		$this->externalProcess = proc_open(
			'exec php custom_process.php '.escapeshellarg( $this->command ).' '.escapeshellarg( $this->workingDir ).' '.$this->sleep,
			$descriptors, $pipes
		);
		
		if ( !$this->externalProcess ) {
			$this->fail( 'Failed to setup external process' );
		}
		
		$this->externalPid = proc_get_status( $this->externalProcess )[ 'pid' ];
	}
	
	protected function tearDown( ) : void {
		proc_terminate( $this->externalProcess );
		
		while( proc_get_status( $this->externalProcess )[ 'running' ] ) { // potentially infinite loop
			sleep( 1 );
		}
		
		proc_close( $this->externalProcess );
	}
	
	public function testInfo( ) : void {
		sleep( 1 ); // need to wait for chdir and cli_set_process_title
		
		$system = new Process( $this->externalPid );
		
		$this->assertEquals( $this->workingDir, $system->GetWorkingDir( ) );
		$this->assertEquals( $this->command, $system->GetCommand( ) );
	}
}
