<?php
namespace multiproc\tests;

use PHPUnit\Framework\TestCase;
use multiproc\process\external\DescriptorSpec;
use multiproc\process\External;
use multiproc\process\external\StdPipes;
use multiproc\Process;

/**
 * Testing external process class
 */
class ExternalTests extends TestCase {
	/**
	 * Working dir for child process
	 */
	protected string $dir = __DIR__.'/external';
	
	/**
	 * Test file descriptor for specification
	 */
	protected $testFd = null;
	
	protected function setUp( ) : void {
		if ( !is_dir( $this->dir ) ) {
			mkdir( $this->dir );
		}
		
		$this->testFd = fopen( $this->dir.'/test_file', 'wb' );
	}
	
	protected function tearDown( ) : void {
		fclose( $this->testFd );
		
		$entries = scandir( $this->dir );
		foreach( $entries as $entry ) {
			if ( ( $entry != '.' ) && ( $entry != '..' ) ) {
				unlink( $this->dir.'/'.$entry );
			}
		}
		
		rmdir( $this->dir );
	}
	
	/**
	 * Testing specification
	 */
	public function testSpecification( ) : void {
		$this->assertEquals(
			[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ],
			DescriptorSpec::CreateStandard( )->GetSpecification( )
		);
		
		// just for testing, useless in real code
		$spec = new DescriptorSpec;
		$spec->SetPipe( 0, true );
		$spec->SetPipe( 1, false );
		$spec->SetFile( 2, $this->dir.'/file.txt', 'wb' );
		$spec->SetFile( 3, $this->dir.'/file.txt', 'rb' );
		$spec->SetPty( 4 );
		$spec->SetResource( 5, $this->testFd );
		
		$this->assertEquals(
			[
				0 => [ 'pipe', 'w' ],
				1 => [ 'pipe', 'r' ],
				2 => [ 'file', $this->dir.'/file.txt', 'wb' ],
				3 => [ 'file', $this->dir.'/file.txt', 'rb' ],
				4 => [ 'pty' ],
				5 => $this->testFd
			],
			$spec->GetSpecification( )
		);
	}
	
	protected function CreateProc( array $spec, string $command ) : External {
		return new class ( $spec, [ ], 5, $this->dir, $command ) extends External {
			use StdPipes;
			
			public function IssetPipe( int $index ) : bool {
				return isset( $this->pipes[ $index ] );
			}
			
			public function IssetHandle( ) : bool {
				return isset( $this->handle );
			}
		};
	}
	
	/**
	 * Testing wait function
	 */
	public function testWait( ) : void {
		$process = $this->CreateProc( [ ], 'sleep 1' );
		$this->assertTrue( $process->Start( ) );
		$info = $process->Wait( );
		$this->assertTrue( $info->normalExit );
		$this->assertNotNull( $info->exitCode ); // here we dont care about number
		$this->assertFalse( $process->IssetHandle( ) );
	}
	
	/**
	 * Testing working dir changes
	 */
	public function testWorkingDir( ) : void {
		$process = $this->CreateProc( [ ], 'sleep 1' ); // need time to get value
		
		$this->assertTrue( $process->Start( ) );
		$this->assertEquals( $this->dir, $process->GetWorkingDir( ) );
		$process->Wait( );
	}
	
	/**
	 * Testing command (aka title)
	 */
	public function testCommand( ) : void {
		$command = 'sleep 2';
		$process = $this->CreateProc( [ ], $command );
		
		$this->assertTrue( $process->Start( ) );
		$this->assertEquals( $command, $process->GetCommand( ) );
		$process->Wait( );
	}
	
	/**
	 * Testing exit code
	 */
	public function testExitCode( ) : void {
		$exitCode = 42;
		$command = '/bin/sh -c "sleep 1 && exit '.$exitCode.'"';
		
		$process = $this->CreateProc( [ ], $command );
		
		// wait function must work too
		$this->assertTrue( $process->Start( ) );
		$info = $process->Wait( );
		$this->assertTrue( $info->normalExit );
		$this->assertEquals( $exitCode, $info->exitCode );
		$this->assertEquals( $exitCode, $process->GetExitCode( ) );
	}
	
	/**
	 * Testing stop process by sending signal
	 */
	public function testExitSignaled( ) : void {
		$process = $this->CreateProc( [ ], 'sleep 3' ); // sleep will be interrupted by signal
		
		$this->assertTrue( $process->Start( ) );
		$this->assertTrue( $process->Stop( ) ); // SIGTERM will be sent here
		$info = $process->GetLastProcessStatus( );
		$this->assertTrue( $info->signalExit );
		$this->assertEquals( SIGTERM, $info->signo );
	}
	
	/**
	 * Testing closing pipes
	 */
	public function testClosingPipes( ) : void {
		$process = $this->CreateProc( DescriptorSpec::CreateStandard( )->GetSpecification( ), 'sleep 1' );
		
		$this->assertTrue( $process->Start( ) );
		$this->assertTrue( $process->IssetPipe( Process::STDIN_ID ) );
		$this->assertTrue( $process->IssetPipe( Process::STDOUT_ID ) );
		$this->assertTrue( $process->IssetPipe( Process::STDERR_ID ) );
		$this->assertTrue( $process->CloseStdin( ) );
		$this->assertTrue( $process->CloseStdout( ) );
		$this->assertTrue( $process->CloseStderr( ) );
		$this->assertFalse( $process->IssetPipe( Process::STDIN_ID ) );
		$this->assertFalse( $process->IssetPipe( Process::STDOUT_ID ) );
		$this->assertFalse( $process->IssetPipe( Process::STDERR_ID ) );
		$process->Wait( );
	}
	
	/**
	 * Testing stdout read
	 */
	public function testStdout( ) : void {
		$text = 'output';
		$command = '/bin/sh -c "echo '.escapeshellarg( $text ).'"';
		$process = $this->CreateProc( [ Process::STDOUT_ID => [ 'pipe', 'w' ] ], $command );
		$this->assertTrue( $process->Start( ) );
		sleep( 1 );
		$this->assertEquals( $text."\n", $process->ReadOutput( ) );
		$process->Wait( );
	}
	
	/**
	 * Testing stderr read
	 */
	public function testStderr( ) : void {
		$text = 'output';
		$command = '/bin/sh -c "echo '.escapeshellarg( $text ).'" >&2';
		$process = $this->CreateProc( [ Process::STDERR_ID => [ 'pipe', 'w' ] ], $command );
		$this->assertTrue( $process->Start( ) );
		sleep( 1 );
		$this->assertEquals( $text."\n", $process->ReadErrors( ) );
		$process->Wait( );
	}
	
	/**
	 * Testing write to child stding
	 */
	public function testStdin( ) : void {
		$text = 'stdin example';
		$process = $this->CreateProc( [ Process::STDIN_ID => [ 'pipe', 'r' ] ], escapeshellarg( __DIR__.'/external_stdin.php' ) );
		$this->assertTrue( $process->Start( ) );
		$this->assertTrue( $process->WritePipe( Process::STDIN_ID, $text ) );
		$this->assertTrue( $process->CloseStdin( ) );
		$process->Wait( );
		$this->assertTrue( file_exists( $this->dir.'/stdin.txt' ) ); // file must be created by child process
		$this->assertEquals( $text, file_get_contents( $this->dir.'/stdin.txt' ) ); // child process should write text from stdin
	}
	
	/**
	 * Testing read child output from non-blocking stdout
	 */
	public function testReadNonblocking( ) : void {
		$spec = new DescriptorSpec;
		$spec->SetPipe( Process::STDOUT_ID, true );
		$process = $this->CreateProc( $spec->GetSpecification( ), escapeshellarg( __DIR__.'/external_echo_fixed.php' ) );
		$this->assertTrue( $process->Start( ) );
		
		// when using non-blocking mode - polling (select) is required
		$this->assertTrue( $process->SetNonblockStdout( ) );
		$bytes = '';
		
		while( ( $byte = $process->ReadPipeByte( Process::STDOUT_ID ) ) !== null ) {
			if ( $byte == '' ) { // nothing in pipe, idle
				usleep( 150000 ); // 150ms
			} else {
				$bytes .= $byte;
			}
		}
		
		$this->assertEquals( '01234567890123456789', $bytes );
		$process->Wait( );
	}
}
