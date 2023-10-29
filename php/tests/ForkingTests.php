<?php
namespace multiproc\tests;

use PHPUnit\Framework\TestCase;
use multiproc\process\Managed;
use multiproc\process\Forking;
use multiproc\ProcessStatus;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Testing forking process class
 */
class ForkingTests extends TestCase {
	protected string $filename = __DIR__.'/forking_output.log';
	
	protected function setUp( ) : void {
		touch( $this->filename );
		ini_set( 'error_log', $this->filename );
	}
	
	protected function tearDown( ) : void {
		$status = 0;
		
		while( pcntl_waitpid( -1, $status, WNOHANG ) > 0 ) { // potentially infinite loop
			sleep( 1 );
		}
		
		unlink( $this->filename );
	}
	
	/**
	 * Testing basic start and stop
	 */
	public function testStartStop( ) : void {
		$current = new class ( pid: posix_getpid( ) ) extends Managed {
			public function StartChild( Managed $child ) : bool {
				return parent::StartChild( $child );
			}
		};
		// child that sleep and will be stopped by signal default handler
		$child = new class extends Forking {
			protected function CommonCode( ) : void { }
			protected function ParentCode( ) : void { }
			
			protected function ChildCode( ) : void {
				sleep( 2 );
			}
		};
		
		$this->assertTrue( $current->IsCurrent( ) );
		$this->assertTrue( $current->StartChild( $child ) );
		$this->assertFalse( $child->IsCurrent( ) );
		$this->assertTrue( $child->Exists( ) );
		$this->assertTrue( $child->Stop( ) ); // sigterm will terminate
	}
	
	/**
	 * Test text printing at branches
	 */
	public function testOutputs( ) : void {
		$commonText = "common text\n";
		$parentText = "parent text\n";
		$childText = "child text\n";
	
		$current = new class ( pid: posix_getpid( ) ) extends Managed {
			public function StartChild( Managed $child ) : bool {
				return parent::StartChild( $child );
			}
		};
		// child that sleep and will be stopped by signal default handler
		$child = new class ( $this->filename, $commonText, $parentText, $childText ) extends Forking {
			protected string $filename;
			protected string $commonOutput;
			protected string $parentOutput;
			protected string $childOutput;
			
			public function __construct( string $filename, string $commonOutput, string $parentOutput, string $childOutput ) {
				$this->filename = $filename;
				$this->commonOutput = $commonOutput;
				$this->parentOutput = $parentOutput;
				$this->childOutput = $childOutput;
			}
			
			protected function CommonCode( ) : void {
				file_put_contents( $this->filename, $this->commonOutput, FILE_APPEND );
			}
			protected function ParentCode( ) : void {
				sleep( 1 ); // sleep for common code to output twice
				file_put_contents( $this->filename, $this->parentOutput, FILE_APPEND );
			}
			
			protected function ChildCode( ) : void {
				sleep( 2 ); // sleep for parent code to output
				file_put_contents( $this->filename, $this->childOutput, FILE_APPEND );
			}
		};
		
		$this->assertTrue( $current->StartChild( $child ) );
		$child->Wait( ); // wait to not interrupt sleep calls
		$this->assertEquals( $commonText.$commonText.$parentText.$childText, file_get_contents( $this->filename ) );
	}
	
	/**
	 * Testing signals binding and unbinding in child process
	 */
	public function testSignalsBinding( ) : void {
		$current = new class ( pid: posix_getpid( ) ) extends Managed {
			protected function SighupHandler( int $signo ) : void { }
			
			public function Init( ) : bool {
				return $this->BindSignalMethod( SIGHUP, 'SighupHandler' );
			}
			
			public function StartChild( Managed $child ) : bool {
				return parent::StartChild( $child );
			}
		};
		
		// child that set exit code based on success of signal binding
		$child = new class extends Forking {
			protected function CommonCode( ) : void { }
			protected function ParentCode( ) : void { }
			protected function SighupHandler( int $signo ) : void { }
			
			protected function ChildCode( ) : void {
				$this->exitCode = $this->BindSignalMethod( SIGHUP, 'SighupHandler' ) ? self::EXIT_SUCCESS : self::EXIT_FAILED;
			}
		};
		
		$this->assertTrue( $current->StartChild( $child ) );
		$info = $child->Wait( );
		$this->assertTrue( $info->normalExit );
		$this->assertEquals( Managed::EXIT_SUCCESS, $info->exitCode );
		
		// child that set exit code based on success of signal unbinding
		$child = new class extends Forking {
			protected function CommonCode( ) : void { }
			protected function ParentCode( ) : void { }
			
			protected function ChildCode( ) : void {
				$this->exitCode = $this->UnbindSignal( SIGHUP ) ? self::EXIT_SUCCESS : self::EXIT_FAILED;
			}
		};
		
		$this->assertTrue( $current->Init( ) );
		$this->assertTrue( $current->StartChild( $child ) );
		$info = $child->Wait( );
		$this->assertTrue( $info->normalExit );
		$this->assertEquals( Managed::EXIT_SUCCESS, $info->exitCode );
	}
	
	public static function signalsSendingProvider( ) : array {
		return [
			// signo normalExit exitCode signalExit sigterm stopExit sigstop
			[ SIGTERM, true, Managed::EXIT_SUCCESS, false, null, false, null, 'signo='.SIGTERM ],
			[ SIGHUP, true, Managed::EXIT_SUCCESS, false, null, false, null, 'signo='.SIGHUP ],
			[ SIGUSR1, true, Managed::EXIT_SUCCESS, false, null, false, null, 'signo='.SIGUSR1 ],
			[ SIGUSR2, true, Managed::EXIT_SUCCESS, false, null, false, null, 'signo='.SIGUSR2 ]
		];
	}
	
	/**
	 * Testing signals sending to child process
	 */
	#[ DataProvider( 'signalsSendingProvider' ) ]
	public function testSeignalsSending(
		int $signoSend, bool $normalExit, ?int $exitCode, bool $signalExit, ?int $sigterm,
		bool $stopExit, ?int $sigstop, string $output
	) : void {
		$current = new class ( pid: posix_getpid( ) ) extends Managed {
			public function StartChild( Managed $child ) : bool {
				return parent::StartChild( $child );
			}
		};
		
		// child that write signal code to file
		$child = new class ( $this->filename, $signoSend ) extends Forking {
			protected string $filename;
			protected int $signo;
			protected bool $running = true;
			
			public function __construct( string $filename, int $signo ) {
				$this->filename = $filename;
				$this->signo = $signo;
			}
			
			protected function CommonCode( ) : void { }
			protected function ParentCode( ) : void { }
			
			protected function SigHandler( int $signo ) : void {
				$this->exitCode = self::EXIT_SUCCESS;
				file_put_contents( $this->filename, 'signo='.$signo );
				$this->running = false;
			}
			
			protected function ChildCode( ) : void {
				$this->exitCode = self::EXIT_FAILED;
				$this->BindSignalMethod( $this->signo, 'SigHandler' );
				
				while( $this->running ) {
					usleep( 150000 );
					pcntl_signal_dispatch( );
				}
			}
		};
		
		$this->assertTrue( $current->StartChild( $child ) );
		sleep( 1 ); // need to sleep, sometimes signal delivered before binding
		$this->assertTrue( $child->SendSignal( $signoSend ) );
		$info = $child->Wait( );
		$this->assertEquals( $normalExit, $info->normalExit );
		$this->assertEquals( $exitCode, $info->exitCode );
		$this->assertEquals( $signalExit, $info->signalExit );
		$this->assertEquals( $sigterm, $info->signo );
		$this->assertEquals( $stopExit, $info->stopExit );
		$this->assertEquals( $sigstop, $info->sigstop );
		$this->assertEquals( $output, file_get_contents( $this->filename ) );
	}
}
