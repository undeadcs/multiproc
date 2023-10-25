<?php

use multiproc\process\Managed;
use multiproc\Process;

class Service extends Managed {
	/**
	 * Amount of cycles
	 */
	protected int $cycles = 3;
	
	/**
	 * Maximum number of parallel workers
	 */
	protected int $workersCount = 0;
	
	/**
	 * Terminate process
	 */
	protected bool $terminate = false;
	
	/**
	 * Constructor
	 * 
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct( int $cycles, int $workersCount, string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null ) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->cycles = $cycles;
		$this->workersCount = $workersCount;
	}
	
	/**
	 * Starting process
	 * 
	 * @throws \Exception
	 */
	public function Start( ) : bool {
		$this->BindSignalMethod( SIGTERM, 'SigtermHandler' );
		
		$cycles = $this->cycles;
		
		while( $cycles > 0 ) {
			$workersCount = count( $this->children );
			
			if ( !$workersCount ) { // all workers finished job
				echo 'cycle #'.( $this->cycles - $cycles + 1 )."\n";
				--$cycles;
				
				for( $i = 0; $i < $this->workersCount; ++$i ) {
					if ( !$this->StartChild( new Worker( $i + 1 ) ) ) {
						echo "failed to start child #$i\n";
					}
				}
			} else { // workers still running, idle
				usleep( 150000 );
			}
			
			pcntl_signal_dispatch( );
			
			if ( $this->terminate ) {
				echo "<TERMINATING>\n";
				break;
			}
		}
		
		$this->WaitChildren( );
		
		return true;
	}
	
	protected function SigtermHandler( int $signo ) : void {
		$this->terminate = true;
	}
}
