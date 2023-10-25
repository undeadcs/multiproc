<?php
namespace multiproc\process;

use multiproc\Process;
use multiproc\ProcessStatus;

/**
 * External process
 * wrapper for child process
 * proc_get_status - under the hood calls waitpid function
 */
class External extends Managed {
	/**
	 * Resource from proc_open
	 */
	protected $handle = null;
	
	/**
	 * Environment variables for child process
	 */
	protected array $env = [ ];
	
	/**
	 * Seconds to wait child process termination
	 */
	protected int $terminateTimeout = 5;
	
	/**
	 * Descriptor specification
	 */
	protected array $spec;
	
	/**
	 * Pipes created by proc_open
	 */
	protected array $pipes = [ ];
	
	/**
	 * Constructor
	 * 
	 * @param array $env Environment variables for child process
	 * @param string $workingDir Working directory
	 * @param string $command Command
	 * @param int $pid Process ID
	 * @param Process $parentProc Parent process
	 */
	public function __construct(
		array $spec = [ ], array $env = [ ], int $terminateTimeout = 5,
		string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null
	) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->spec = $spec;
		$this->enc = $env;
		$this->terminateTimeout = $terminateTimeout;
	}
	
	/**
	 * Destructor
	 */
	public function __destruct( ) {
		if ( $this->handle ) {
			$this->CloseHandle( );
		}
		
		parent::__destruct( );
	}
	
	/**
	 * Get command
	 * 
	 * @throws \Exception
	 */
	public function GetCommand( ) : string {
		return $this->command;
	}
	
	/**
	 * Starting process
	 * 
	 * @throws \multiproc\exceptions\PcntlLastError
	 */
	public function Start( ) : bool {
		if ( $this->command == '' ) {
			return false;
		}
		
		$handle = $this->CreateHandle( );
		if ( $handle === false ) {
			return false;
		}
		
		$this->handle = $handle;
		$this->pid = proc_get_status( $this->handle )[ 'pid' ];
		
		return true;
	}
	
	/**
	 * Stopping process
	 * 
	 * @return bool success of operation
	 * @throws \multiproc\exceptions\PcntlLastError
	 */
	public function Stop( ) : bool {
		if ( !$this->handle ) {
			return false;
		}
		
		$success = parent::Stop( );
		
		$this->ClosePipes( );
		
		if ( $this->handle ) {
			$this->CloseHandle( );
		}
		
		return $success;
	}
	
	/**
	 * Wait for process to terminate
	 * returns immediately if object is current context process
	 *
	 * @return ProcessStatus Information about status of process
	 * @throws \Exception
	 */
	public function Wait( ) : ProcessStatus {
		$info = parent::Wait( );
		
		$this->ClosePipes( );
		$this->CloseHandle( );
		
		return $info;
	}
	
	/**
	 * Create resource
	 */
	protected function CreateHandle( ) {
		return proc_open(
			'exec '.$this->command, // exec to remove useless shell
			$this->spec,
			$this->pipes,
			( $this->workingDir == '' ) ? null : $this->workingDir,
			$this->env ? $this->env : null
		);
	}
	
	/**
	 * Close resource
	 */
	protected function CloseHandle( ) : void {
		proc_close( $this->handle );
		$this->handle = null;
	}
	
	/**
	 * Close all pipes
	 */
	public function ClosePipes( ) : int {
		$cnt = 0;
		
		foreach( $this->pipes as $index => $pipe ) {
			if ( fclose( $pipe ) ) {
				++$cnt;
			}
			
			unset( $pipe, $this->pipes[ $index ] );
		}
		
		return $cnt;
	}
	
	/**
	 * Close specified pipe
	 * 
	 * @param int $index Index of pipe
	 */
	public function ClosePipe( int $index ) : bool {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return false;
		}
		
		$closed = fclose( $this->pipes[ $index ] );
		unset( $this->pipes[ $index ] );
		
		return $closed;
	}
	
	/**
	 * Generic set blocking descriptor of child process in current context
	 * 
	 * @param int $index Index of descriptor
	 * @return bool success of operation
	 */
	public function SetBlockingPipe( int $index, bool $blocking = true ) : bool {
		return isset( $this->pipes[ $index ] ) && stream_set_blocking( $this->pipes[ $index ], $blocking );
	}
	
	/**
	 * Set pipe read buffer size
	 * 
	 * @param int $index Index of descriptor
	 * @param int $size Buffer size in bytes
	 * @return bool success of operation
	 */
	public function SetPipeReadBufferSize( int $index, int $size ) : bool {
		return isset( $this->pipes[ $index ] ) && ( stream_set_read_buffer( $this->pipes[ $index ], $size ) == 0 );
	}
	
	/**
	 * Set pipe write buffer size
	 * 
	 * @param int $index Index of pipe
	 * @param int $size Buffer size in bytes
	 * @return bool success of operation
	 */
	public function SetPipeWriteBufferSize( int $index, int $size ) : bool {
		return isset( $this->pipes[ $index ] ) && ( stream_set_write_buffer( $this->pipes[ $index ], $size ) == 0 );
	}
	
	/**
	 * Set write buffer size of stdin of child process in current context
	 * 
	 * @param int $size Number of bytes
	 * @return bool success of operation
	 */
	public function SetWriteBufferSize( int $size ) : bool {
		if ( !isset( $this->pipes[ Process::STDIN_ID ] ) ) {
			return false;
		}
		
		return stream_set_write_buffer( $this->pipes[ Process::STDOUT_ID ], $size ) == 0;
	}
	
	/**
	 * Checks that pipe descriptor is ready for write
	 * 
	 * @return bool ready for write
	 */
	public function ReadyWritePipe( int $index ) : bool {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return false;
		}
		
		$w = [ $this->pipes[ $index ] ];
		$r = $e = null;
		// @todo fix: PHP Warning:  stream_select(): Unable to select [4]: Interrupted system call (max_fd=
		return stream_select( $r, $w, $e, 0, 0 ) == 1;
	}
	
	/**
	 * Checks that pipe descriptor is ready for read
	 * 
	 * @return bool ready for read
	 */
	public function ReadyReadPipe( int $index ) : bool {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return false;
		}
		
		$r = [ $this->pipes[ $index ] ];
		$w = $e = null;
		// @todo fix: PHP Warning:  stream_select(): Unable to select [4]: Interrupted system call (max_fd=
		return stream_select( $r, $w, $e, 0, 0 ) == 1;
	}
	
	/**
	 * Read all text from pipe
	 * 
	 * @param int $index Index of pipe
	 * @return string|null content from pipe or null on error
	 */
	public function ReadPipeContents( int $index ) : ?string {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return null;
		}
		
		$ret = stream_get_contents( $this->pipes[ $index ] );
		
		return ( $ret === false ) ? null : $ret;
	}
	
	/**
	 * Read one byte from pipe
	 * 
	 * @param int $index Index of pipe
	 * @return string|null byte from pipe or null on error
	 */
	public function ReadPipeByte( int $index ) : ?string {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return null;
		}
		if ( !$this->ReadyReadPipe( $index ) ) { // protection from non-blocking mode
			return '';
		}
		
		$c = fgetc( $this->pipes[ $index ] );
		
		return ( $c === false ) ? null : $c;
	}
	
	/**
	 * Write to pipe
	 * 
	 * @param int $index Index of pipe
	 * @param string $bytes Bytes to write
	 * @return bool success of operation
	 */
	public function WritePipe( int $index, string $bytes ) : bool {
		if ( !isset( $this->pipes[ $index ] ) ) {
			return false;
		}
		
		return fwrite( $this->pipes[ $index ], $bytes ) > 0;
	}
}
