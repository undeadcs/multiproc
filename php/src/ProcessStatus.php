<?php
namespace multiproc;

/**
 * Process status information
 * utiliy class for information about process status after pcntl_waitpid/pcntl_wait call
 */
class ProcessStatus {
	/**
	 * Status value from wait functions
	 */
	public int $status;
	
	/**
	 * It was normal process exit
	 */
	public bool $normalExit = false;
	
	/**
	 * Exit code after normal exit
	 */
	public ?int $exitCode = null;
	
	/**
	 * It was signal exit
	 */
	public bool $signalExit = false;
	
	/**
	 * Signal number after signal exit
	 */
	public ?int $signo = null;

	/**
	 * Signal stopped
	 * from man waitpid:
	 * true if the child process was stopped by delivery of a signal;
	 * this is possible only if the call was done using WUNTRACED or when the child is being traced (see ptrace(2))
	 */
	public bool $stopExit = false;
	
	/**
	 * Signal that stopped
	 */
	public ?int $sigstop = null;
	
	/**
	 * Constructor
	 * 
	 * @param int $status Status value from wait functions
	 */
	public function __construct( int $status ) {
		$this->status = $status;
		
		if ( pcntl_wifexited( $status ) ) {
			$this->normalExit = true;
			$this->exitCode = pcntl_wexitstatus( $status );
		}
		if ( pcntl_wifsignaled( $status ) ) {
			$this->signalExit = true;
			$this->signo = pcntl_wtermsig( $status );
		}
		if ( pcntl_wifstopped( $status ) ) {
			$this->stopExit = true;
			$this->sigstop = pcntl_wstopsig( $status );
		}
	}
}
