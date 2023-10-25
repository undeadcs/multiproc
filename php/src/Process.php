<?php
namespace multiproc;

use multiproc\exceptions\ProcfsAccessDenied;

/**
 * Base process
 */
class Process {
	// exit codes
	const EXIT_SUCCESS	= 0;	// successful exit code
	const EXIT_FAILED	= 1;	// generic fail exit code
	
	// standard descriptors
	const STDIN_ID	= 0;	// standard input
	const STDOUT_ID	= 1;	// standard output
	const STDERR_ID	= 2;	// standard error
	
	/**
	 * ID in system
	 */
	protected int $pid = 0;
	
	/**
	 * Parent process
	 */
	protected ?Process $parentProc = null;
	
	/**
	 * Constructor
	 * 
	 * @param int $pid ID in system
	 * @param Process $parentProc Parent process
	 */
	public function __construct( int $pid = 0, ?Process $parentProc = null ) {
		$this->pid			= $pid;
		$this->parentProc	= $parentProc;
	}
	
	/**
	 * Destructor
	 */
	public function __destruct( ) {
		$this->parentProc = null;
	}
	
	/**
	 * Get ID
	 */
	public function GetPid( ) : int {
		return $this->pid;
	}
	
	/**
	 * Get parent process
	 */
	public function GetParentProc( ) : ?Process {
		return $this->parentProc;
	}
	
	/**
	 * Is process exists in operating system
	 */
	public function Exists( ) : bool {
		// note: posix_kill returns true for zombie child process
		return ( $this->pid > 0 ) && posix_kill( $this->pid, 0 );
	}
	
	/**
	 * Send system signal
	 * 
	 * @param int $signo Signal number
	 */
	public function SendSignal( int $signo ) : bool {
		return ( $this->pid > 0 ) && posix_kill( $this->pid, $signo );
	}
	
	/**
	 * Get working directory
	 * 
	 * @throws \Exception
	 */
	public function GetWorkingDir( ) : string {
		$path = '/proc/'.$this->pid.'/cwd'; // this is symlink usually
		$dir = @realpath( $path );
		
		if ( $dir === false ) {
			throw new ProcfsAccessDenied( $path, 'realpath' );
		}
		
		return $dir;
	}
	
	/**
	 * Get command
	 * 
	 * @throws \Exception
	 */
	public function GetCommand( ) : string {
		$path = '/proc/'.$this->pid.'/cmdline'; // string separated by null (\0) character
		
		if ( @!is_readable( $path ) ) {
			throw new ProcfsAccessDenied( $path, 'is_readable' );
		}
		
		$word = '';
		$words = [ ];
		$cmd = file_get_contents( $path );
		$len = strlen( $cmd );
		
		for( $i = 0; $i < $len; ++$i ) {
			$char = $cmd[ $i ];
			
			if ( ord( $char ) == 0 ) { // \0
				$words[ ] = $word;
				$word = '';
			} else {
				$word .= $char;
			}
		}
		
		return trim( join( ' ', $words ) );
	}
}
