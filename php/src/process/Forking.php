<?php
namespace multiproc\process;

use multiproc\exceptions\PcntlLastError;

/**
 * Forking process
 * pid will be defined at start
 */
abstract class Forking extends Managed {
	/**
	 * Starting process
	 *
	 * @throws \multiproc\exceptions\PcntlLastError
	 */
	public function Start( ) : bool {
		if ( !$this->parentProc ) { // required for signals rebinding
			return false;
		}
		if ( $this->IsCurrent( ) || $this->Exists( ) ) { // already started
			return true;
		}
		
		$pid = @pcntl_fork( );
		if ( $pid == -1 ) { // example: "fork failed: Resource temporarily unavailable"
			throw new PcntlLastError( 'pcntl_fork' );
		}
		
		$this->pid = $pid ? $pid : posix_getpid( ); // fork returns 0 for child
		$this->CommonCode( ); // utility
		
		if ( $pid ) {
			$this->ParentCode( ); // utility
			
			return true;
		}

		// child context
		// we need to catch any errors or exceptions to protect from loops
		try {
			$this->ChildCode( );
		}
		catch( \Error | \Exception $e ) {
			$this->FatalExit( $e );
		}
		
		exit( $this->exitCode ); // protect from loop that may exists at up level of call stack
		
		return true;
	}
	
	/**
	 * Error or Exception triggered at current child context
	 */
	protected function FatalExit( $e ) {
		echo $e->getMessage( )."\n".$e->getTraceAsString( );
	}
	
	/**
	 * Code that running at parent and child context
	 */
	abstract protected function CommonCode( ) : void;
	
	/**
	 * Code that run at parent context
	 */
	abstract protected function ParentCode( ) : void;
	
	/**
	 * Code that run at child context
	 */
	abstract protected function ChildCode( ) : void;
}
