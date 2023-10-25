<?php
namespace multiproc\exceptions;

/**
 * Access denied when try to work with procfs
 */
class ProcfsAccessDenied extends \Exception {
	/**
	 * Constructor
	 * 
	 * @param string $procfsPath procfs path
	 * @param string $fnName function name
	 */
	public function __construct( string $procfsPath, string $fnName ) {
		parent::__construct( $fnName.' failed at \''.$procfsPath.'\'' );
	}
}
