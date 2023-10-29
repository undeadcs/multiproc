<?php
namespace multiproc\process;

use multiproc\Process;
use multiproc\exceptions\PcntlLastError;
use multiproc\ProcessStatus;
use multiproc\exceptions\InvalidContext;

/**
 * Managed process
 */
class Managed extends Process {
	/**
	 * Working directory
	 */
	protected string $workingDir = '';
	
	/**
	 * Command
	 * 
	 * /proc/pid/cmdline - its equal to title
	 * @see https://man7.org/linux/man-pages/man5/proc.5.html
	 */
	protected string $command = '';
	
	/**
	 * Exit code
	 */
	protected int $exitCode = self::EXIT_SUCCESS;
	
	/**
	 * Last wait info
	 */
	protected ?ProcessStatus $lastProcessStatus = null;
	
	/**
	 * Children process objects indexed by spl_object_id
	 */
	protected array $children = [ ];
	
	/**
	 * Children process objects indexed by id
	 */
	protected array $childByPid = [ ];
	
	/**
	 * Signals handlers
	 */
	protected array $signalsHandlers = [ ];
	
	/**
	 * Constructor
	 * 
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct( string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null ) {
		parent::__construct( $pid, $parentProc );
		
		$this->workingDir	= ( ( $workingDir == '' ) ? getcwd( ) : $workingDir );
		$this->command		= ( ( $command == '' ) ? cli_get_process_title( ) : $command );
	}
	
	/**
	 * At current process context
	 */
	public function IsCurrent( ) : bool {
		return $this->pid == posix_getpid( );
	}
	
	/**
	 * Get exit status
	 */
	public function GetExitCode( ) : int {
		return $this->exitCode;
	}
	
	/**
	 * Get last wait info
	 */
	public function GetLastProcessStatus( ) : ?ProcessStatus {
		return $this->lastProcessStatus;
	}
	
	/**
	 * Get working directory
	 * 
	 * @throws \Exception
	 */
	public function GetWorkingDir( ) : string {
		if ( !$this->IsCurrent( ) ) { // sync value - current object is other process
			$this->workingDir = parent::GetWorkingDir( );
		}
		
		return $this->workingDir;
	}
	
	/**
	 * Get command
	 * 
	 * @throws \Exception
	 */
	public function GetCommand( ) : string {
		if ( !$this->IsCurrent( ) ) {
			$this->command = parent::GetCommand( );
		}
		
		return $this->command;
	}
	
	/**
	 * Starting process
	 * 
	 * @throws \Exception
	 */
	public function Start( ) : bool {
		return $this->IsCurrent( );
	}
	
	/**
	 * Stopping process
	 * @todo check child in parent context
	 * 
	 * @return bool success of operation
	 * @throws \Exception
	 */
	public function Stop( ) : bool {
		if ( $this->IsCurrent( ) ) { // just exit in current context
			exit( $this->exitCode );
			
			return true;
		}
		// at parent context current object is child process
		if ( !$this->Exists( ) ) { // zombie process actually exists
			return false;
		}
		
		$status = 0;
		$exitedPid = pcntl_waitpid( $this->pid, $status, WNOHANG ); // 0 - nobody exited, -1 - error
		
		if ( $exitedPid == -1 ) {
			throw new PcntlLastError( 'pcntl_waitpid' );
		}
		if ( $exitedPid == $this->pid ) { // this process already exited, fetch info
			$this->lastProcessStatus = new ProcessStatus( $status );
			
			if ( $this->lastProcessStatus->normalExit ) {
				$this->exitCode = $this->lastProcessStatus->exitCode;
			}
			
			return true;
		}
		// process still running, try to stop
		if ( !$this->SendSignal( SIGTERM ) ) {
			return false;
		}
		
		$this->Wait( ); // blocking wait
		
		return true;
	}
	
	/**
	 * Wait for process to terminate
	 * returns immediately if object is current context process
	 * @todo check child in parent context
	 *
	 * @return ProcessStatus Information about status of process
	 * @throws \multiproc\exceptions\PcntlLastError
	 * @throws \multiproc\exceptions\InvalidContext
	 */
	public function Wait( ) : ?ProcessStatus {
		if ( $this->IsCurrent( ) ) {
			return $this->lastProcessStatus;
		}
		
		// at parent context current object is child process
		$status = 0;
		
		if ( pcntl_waitpid( $this->pid, $status ) == -1 ) {
			throw new PcntlLastError( 'pcntl_waitpid' );
		}
		
		$this->lastProcessStatus = new ProcessStatus( $status );
		
		if ( $this->lastProcessStatus->normalExit ) {
			$this->exitCode = $this->lastProcessStatus->exitCode;
		}
		
		return $this->lastProcessStatus;
	}
	
	/**
	 * Bind signal handler
	 * 
	 * please take care about functions called inside signal handler code
	 * never try to call fork, start child etc. (fork is not async-signal-safe actually)
	 * @see https://man7.org/linux/man-pages/man7/signal-safety.7.html
	 * @see https://sourceware.org/bugzilla/show_bug.cgi?id=4737
	 * 
	 * @param int $signo Signal number
	 * @param callable $callback Handler code
	 * @return bool success of operation
	 */
	protected function BindSignal( int $signo, callable $callback ) : bool {
		if ( !$this->IsCurrent( ) || !pcntl_signal( $signo, $callback ) ) { // can bind only in current context
			return false;
		}
		
		$this->signalsHandlers[ $signo ] = $callback;
			
		return true;
	}
	
	/**
	 * Bind signal handler to this object method
	 *
	 * @param int $signo Signal number
	 * @param string $method Method name
	 * @return bool success of operation
	 */
	protected function BindSignalMethod( int $signo, string $method ) : bool {
		return $this->BindSignal( $signo, [ $this, $method ] );
	}
	
	/**
	 * Unbind signal handler and reset it to default
	 *
	 * @param int $signo Signal number
	 * @return bool success of operation
	 */
	protected function UnbindSignal( int $signo ) : bool {
		if ( !$this->IsCurrent( ) ) { // can unbind only in current context
			return false;
		}
		if ( isset( $this->signalsHandlers[ $signo ] ) ) { // unbinding must work anyway
			unset( $this->signalsHandlers[ $signo ] );
		}
		
		return pcntl_signal( $signo, SIG_DFL );
	}
	
	/**
	 * Wait all children processes for exit
	 * 
	 * @param int $usleep Amount of microseconds to usleep at wait loop cycle
	 * @param int $totalWaitSeconds Amount of seconds to totally wait children
	 * @return bool all children exited normally
	 */
	protected function WaitChildren( int $usleep = 150000, int $totalWaitSeconds = 300 ) : bool {
		$startAt = time( );
		
		do {
			if ( !$this->CheckChildrenStatus( ) ) {
				usleep( $usleep ); // nobody exited, idle
			}
			
			if ( ( time( ) - $startAt ) > $totalWaitSeconds ) {
				return false; // failed to wait all children, handle it by hand
			}
		} while( $this->children );
		
		return true;
	}
	
	/**
	 * Child inherit some things from parent
	 * 
	 * @param Managed $child Child process
	 */
	protected function ChildInheritance( Managed $child ) : void {
		$child->parentProc = $this; // parent-child linking
		$child->signalsHandlers = $this->signalsHandlers; // signals handlers always inherited by child process
	}
	
	/**
	 * Start child process
	 * common code for starting child process
	 *
	 * @param Managed $child Child process object
	 * @return bool success of operation
	 */
	protected function StartChild( Managed $child ) : bool {
		if ( !$this->IsCurrent( ) ) { // only at current context
			return false;
		}
		if ( !isset( $this->signalsHandlers[ SIGCHLD ] ) && !$this->BindSignalMethod( SIGCHLD, 'SigchldHandler' ) ) { // force processing of signal
			return false;
		}
		
		$this->ChildInheritance( $child );
		
		if ( $child->Start( ) ) {
			$this->RegisterChild( $child );
			$this->ChildStarted( $child );
			
			return true;
		}
		
		$this->ChildStartFailed( $child );
		
		return false;
	}
	
	/**
	 * Register child object
	 * if you register process that actually not child of current context process - its your problem
	 * 
	 * @param Managed $child Child process object
	 */
	protected function RegisterChild( Managed $child ) : void {
		$this->children[ spl_object_id( $child ) ] = $child;
		
		if ( $child->pid > 0 ) {
			$this->childByPid[ $child->pid ] = $child;
		}
	}
	
	/**
	 * Common code for processing SIGCHLD signal
	 *
	 * @param int $signo Signal number
	 */
	protected function SigchldHandler( int $signo ) : void {
		$this->CheckChildrenStatus( );
	}
	
	/**
	 * Find child process by PID
	 * 
	 * @param int $pid Process ID
	 * @return null|Managed child object if found
	 */
	protected function FindChild( int $pid ) : ?Managed {
		if ( isset( $this->childByPid[ $pid ] ) ) {
			return $this->childByPid[ $pid ];
		}
		
		foreach( $this->children as $child ) {
			if ( $child->pid == $pid ) {
				return $child;
			}
		}
		
		return null;
	}
	
	/**
	 * Check all children processes status
	 * 
	 * @return int number of exited children
	 */
	protected function CheckChildrenStatus( ) : int {
		$exitedCount = $status = 0;
		
		while( ( $pid = pcntl_waitpid( -1, $status, WNOHANG ) ) > 0 ) { // pcntl_waitpid returns 0 when no children exited or -1 on error
			if ( $child = $this->FindChild( $pid ) ) {
				$lastStatus = new ProcessStatus( $status );
				$child->lastProcessStatus = $lastStatus;
				
				$this->ChildExited( $child );
				$this->UnsetChild( $child );
				unset( $child ); // force dereference
			} else {
				$this->UnregisteredChildExited( $pid, new ProcessStatus( $status ) );
			}
			
			$status = 0;
			++$exitedCount;
		}
		
		return $exitedCount;
	}
	
	/**
	 * Unset child
	 * 
	 * @param Managed $child Child process object
	 */
	protected function UnsetChild( Managed $child ) : void {
		if ( isset( $this->childByPid[ $child->pid ] ) ) {
			unset( $this->childByPid[ $child->pid ] );
		}
		
		$id = spl_object_id( $child );
		
		if ( isset( $this->children[ $id ] ) ) {
			unset( $this->children[ $id ] );
		}
		
		unset( $child );
	}
	
	/**
	 * Send signal to all children
	 * 
	 * @param int $signo Signal number
	 * @return int number of successfully sent signals
	 */
	protected function SendSignalToChildren( int $signo ) : int {
		$cnt = 0;
		
		foreach( $this->children as $child ) {
			if ( $child->SendSignal( $signo ) ) {
				++$cnt;
			}
		}
		
		return $cnt;
	}
	
	/**
	 * Wait call failed
	 * 
	 * @param Managed $child Child process object
	 * @param PcntlLastError $e Exception from wait
	 */
	protected function ChildWaitFailed( Managed $child, PcntlLastError $e ) : void { }
	
	/**
	 * Child process just started
	 *
	 * @param Managed $child Child process object
	 */
	protected function ChildStarted( Managed $child ) : void { }
	
	/**
	 * Child process failed to start
	 * 
	 * @param Managed $child Child process object
	 */
	protected function ChildStartFailed( Managed $child ) : void { }
	
	/**
	 * Child process just exited
	 *
	 * @param Managed $child Child process object
	 */
	protected function ChildExited( Managed $child ) : void { }
	
	/**
	 * Child process just exited
	 * it was not found in children or by pid
	 *
	 * @param int $pid Process ID
	 * @param ProcessStatus $status Exit status
	 */
	protected function UnregisteredChildExited( int $pid, ProcessStatus $status ) : void { }
}
