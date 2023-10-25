<?php
namespace multiproc\process;

use multiproc\exceptions\PcntlLastError;
use multiproc\Process;

/**
 * Daemon
 */
abstract class Daemon extends Managed {
	/**
	 * PID filename
	 */
	protected string $pidFilename;
	
	/**
	 * Standard descriptors
	 * stdin, stdout, stderr
	 */
	protected array $stdDescriptors = [ ];
	
	/**
	 * Constructor
	 *
	 * @param string $pidFilename PID filename
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct( string $pidFilename, string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null ) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->pidFilename = $pidFilename;
	}
	
	/**
	 * Destructor
	 */
	public function __destruct( ) {
		$this->CloseStdDescriptors( );
	}
	
	/**
	 * Starting process
	 *
	 * @throws \multiproc\exceptions\PcntlLastError
	 */
	public function Start( ) : bool {
		$pid = @pcntl_fork( );
		if ( $pid == -1 ) {
			throw new PcntlLastError( 'pcntl_fork' );
		} else if ( $pid ) { // first parent must exit
			exit( 0 );
		}
		if ( @posix_setsid( ) == -1 ) {
			throw new PcntlLastError( 'posix_setsid' );
		}
		
		$pid = @pcntl_fork( );
		if ( $pid == -1 ) {
			throw new PcntlLastError( 'pcntl_fork' );
		} else if ( $pid ) { // second parent must exit
			exit( 0 );
		}
		
		$this->pid = posix_getpid( );
		
		if ( !file_put_contents( $this->pidFilename, $this->pid."\n" ) ) {
			return false;
		}
		
		umask( 0 );
		chdir( '/' );
		$this->ResetStdDescriptors( );
		$this->Main( );
		exit( $this->exitCode );
		
		return true;
	}
	
	/**
	 * Resetting standard descriptors to /dev/null
	 */
	protected function ResetStdDescriptors( ) : void {
		fclose( STDIN );
		fclose( STDOUT );
		fclose( STDERR );
		
		// это надо, чтобы PHP правильно работал с echo
		$this->stdDescriptors[ 'stdin'	] = fopen( '/dev/null', 'r' );
		$this->stdDescriptors[ 'stdout'	] = fopen( '/dev/null', 'w' );
		$this->stdDescriptors[ 'stderr'	] = fopen( '/dev/null', 'w' );
	}
	
	/**
	 * Close standard descriptors
	 */
	protected function CloseStdDescriptors( ) : void {
		foreach( $this->stdDescriptors as $fd ) {
			fclose( $fd );
		}
	}
	
	/**
	 * Main function
	 */
	abstract protected function Main( ) : void;
}
