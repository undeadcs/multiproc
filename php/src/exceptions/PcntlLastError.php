<?php
namespace multiproc\exceptions;

/**
 * Exception for PCNTL Functions
 */
class PcntlLastError extends \Exception {
	/**
	 * Constructor
	 */
	public function __construct( string $fnName ) {
		$errno = pcntl_get_last_error( );
		
		parent::__construct( $fnName.': '.pcntl_strerror( $errno ), $errno );
	}
}
