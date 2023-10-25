<?php
namespace multiproc\process\external;

use multiproc\Process;

/**
 * Descriptor specification of external process
 * utility class
 */
class DescriptorSpec {
	/**
	 * Specification for proc_open
	 */
	protected array $values = [ ];
	
	/**
	 * Get specification
	 */
	public function GetSpecification( ) : array {
		return $this->values;
	}
	
	/**
	 * Generic specification
	 * 
	 * @param int $index Number of descriptor
	 * @param mixed $value Specification
	 */
	protected function SetSpec( int $index, $value ) : self {
		$this->values[ $index ] = $value;
		
		return $this;
	}
	
	/**
	 * Set pipe specification
	 * 
	 * @param int $index Index of descriptor
	 * @param bool $write Write or read
	 */
	public function SetPipe( int $index, bool $write = true ) : self {
		return $this->SetSpec( $index, [ 'pipe', $write ? 'w' : 'r' ] );
	}
	
	/**
	 * Set file specification
	 * 
	 * @param int $index Index of descriptor
	 * @param string $filename File name
	 * @param string $mode Mode for file
	 */
	public function SetFile( int $index, string $filename, string $mode ) : self {
		return $this->SetSpec( $index, [ 'file', $filename, $mode ] );
	}
	
	/**
	 * Resource specification
	 * already opened file, socket etc.
	 * 
	 * @param int $index Index of descriptor
	 * @param mixed $resource Resource
	 */
	public function SetResource( int $index, $resource ) : self {
		return $this->SetSpec( $index, $resource );
	}
	
	/**
	 * PTY specification
	 * pty is working like bidirectional pipe
	 * it is possible to read from and write to
	 * 
	 * @param int $index Index of descriptor
	 */
	public function SetPty( int $index ) : self {
		return $this->SetSpec( $index, [ 'pty' ] );
	}
	
	/**
	 * Commonly used specification
	 */
	public static function CreateStandard( ) : self {
		$ret = new static;
		$ret->values = [
			Process::STDIN_ID	=> [ 'pipe', 'r' ],
			Process::STDOUT_ID	=> [ 'pipe', 'w' ],
			Process::STDERR_ID	=> [ 'pipe', 'w' ]
		];
		
		return $ret;
	}
}
