<?php
namespace multiproc\tests;

use multiproc\process\Forking;
use multiproc\Process;

/**
 * Process for tests
 */
class ForkingTestProcess extends Forking {
	protected string $commonText;
	protected string $parentText;
	protected string $childText;
	protected int $setExitStatus;
	protected bool $running = true;
	
	/**
	 * Constructor
	 * 
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct(
		string $commonText, string $parentText, string $childText, int $setExitStatus, bool $running,
		string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null
	) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->commonText = $commonText;
		$this->parentText = $parentText;
		$this->childText = $childText;
		$this->running = $running;
		$this->setExitStatus = $setExitStatus;
	}
	
	/**
	 * Code that running at parent and child context
	 */
	protected function CommonCode( ) : void {
		if ( $this->commonText != '' ) {
			echo $this->commonText;
		}
	}
	
	/**
	 * Code that run at parent context
	 */
	protected function ParentCode( ) : void {
		if ( $this->parentText != '' ) {
			echo $this->parentText;
		}
	}
	
	/**
	 * Code that run at child context
	 */
	protected function ChildCode( ) : void {
		$this->BindSignalMethod( SIGTERM, 'SigtermHandler' );
		$this->BindSignalMethod( SIGHUP, 'SighupHandler' );
		$this->BindSignalMethod( SIGUSR1, 'Sigusr1Handler' );
		
		if ( $this->childText ) {
			echo $this->childText;
		}
		
		if ( $this->running ) {
			while( $this->running ) {
				usleep( 150000 );
				pcntl_signal_dispatch( );
			}
		} else { // just for testing without working loop
			sleep( 3 );
		}
		
		$this->exitCode = $this->setExitStatus;
	}
	
	protected function SigtermHandler( int $signo ) : void {
		$this->running = false;
	}
	
	protected function SighupHandler( int $signo ) : void {
		// usually it is config reload signal
		$this->running = false;
	}
	
	protected function Sigusr1Handler( int $signo ) : void {
		// usually it is special action signal
		$this->running = false;
	}
}
