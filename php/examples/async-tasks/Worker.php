<?php

use multiproc\process\Forking;
use multiproc\Process;

class Worker extends Forking {
	protected int $number = 0;
	
	/**
	 * Constructor
	 * 
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct( int $number, string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null ) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->number = $number;
	}
	
	/**
	 * Code that running at parent and child context
	 */
	protected function CommonCode( ) : void { }
	
	/**
	 * Code that run at parent context
	 */
	protected function ParentCode( ) : void { }
	
	/**
	 * Code that run at child context
	 */
	protected function ChildCode( ) : void {
		printf( "worker %2d: just simple async worker loading or processing something\n", $this->number );
		sleep( 2 );
	}
}
