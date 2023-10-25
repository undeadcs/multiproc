<?php
namespace multiproc\process\external;

/**
 * Utility functions for working with standard pipes
 * for class External
 */
trait StdPipes {
	/**
	 * Close stdin descriptor of child process in current context
	 */
	public function CloseStdin( ) : bool {
		return $this->ClosePipe( self::STDIN_ID );
	}
	
	/**
	 * Close stdout descriptor of child process in current context
	 */
	public function CloseStdout( ) : bool {
		return $this->ClosePipe( self::STDOUT_ID );
	}
	
	/**
	 * Close stderr descriptor of child process in current context
	 */
	public function CloseStderr( ) : bool {
		return $this->ClosePipe( self::STDERR_ID );
	}
	
	/**
	 * Set non-blocking stdin of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetNonblockStdin( ) : bool {
		return $this->SetBlockingPipe( self::STDIN_ID, false );
	}
	
	/**
	 * Set non-blocking stdout of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetNonblockStdout( ) : bool {
		return $this->SetBlockingPipe( self::STDOUT_ID, false );
	}
	
	/**
	 * Set non-blocking stderr of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetNonblockStderr( ) : bool {
		return $this->SetBlockingPipe( self::STDERR_ID, false );
	}
	
	/**
	 * Set blocking stdin of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetBlockStdin( ) : bool {
		return $this->SetBlockingPipe( self::STDIN_ID, true );
	}
	
	/**
	 * Set blocking stdout of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetBlockStdout( ) : bool {
		return $this->SetBlockingPipe( self::STDOUT_ID, true );
	}
	
	/**
	 * Set blocking stderr of child process in current context
	 * 
	 * @return bool success of operation
	 */
	public function SetBlockStderr( ) : bool {
		return $this->SetBlockingPipe( self::STDERR_ID, true );
	}
	
	/**
	 * Checks that stdin descriptor is ready for write
	 * useful for non-blocking mode
	 * 
	 * @return bool ready for write
	 */
	public function ReadyWriteStdin( ) : bool {
		return $this->ReadyWritePipe( self::STDOUT_ID );
	}
	
	/**
	 * Checks that stdout is ready for read
	 * useful for non-blocking mode
	 * 
	 * @return bool ready for read
	 */
	public function ReadyReadStdout( ) : bool {
		return $this->ReadyReadPipe( self::STDOUT_ID );
	}
	
	/**
	 * Checks that stderr is ready for read
	 * useful for non-blocking mode
	 * 
	 * @return bool ready for read
	 */
	public function ReadyReadStderr( ) : bool {
		return $this->ReadyReadPipe( self::STDERR_ID );
	}
	
	/**
	 * Read all text from stdout of child process
	 * 
	 * @return string|null line from stdout or null on error
	 */
	public function ReadOutput( ) : ?string {
		return $this->ReadPipeContents( self::STDOUT_ID );
	}
	
	/**
	 * Read all text from stderr of child process
	 * 
	 * @return string|null line from stderr or null on error
	 */
	public function ReadErrors( ) : ?string {
		return $this->ReadPipeContents( self::STDERR_ID );
	}
	
	/**
	 * Set read buffer size of stdout of child process in current context
	 * 
	 * @param int $size Number of bytes
	 * @return bool success of operation
	 */
	public function SetStdoutReadBufferSize( int $size ) : bool {
		return $this->SetPipeReadBufferSize( self::STDOUT_ID, $size );
	}
	
	/**
	 * Set read buffer size of stderr of child process in current context
	 * 
	 * @param int $size Number of bytes
	 * @return bool success of operation
	 */
	public function SetStderrReadBufferSize( int $size ) : bool {
		return $this->SetPipeReadBufferSize( self::STDERR_ID, $size );
	}
}
