<?php
// example of daemon
require_once( __DIR__.'/../autoload.php' );

use multiproc\process\Daemon;

/**
 * Daemon that doing nothing
 */
class DummyDaemon extends Daemon {
	/**
	 * Flag that app is running
	 */
	protected bool $running = false;
	
	/**
	 * Main function
	 */
	protected function Main( ) : void {
		if ( !$this->BindSignal( SIGTERM, [ $this, 'Terminate' ] ) ) {
			return;
		}
		
		$this->running = true;
		
		// working loop
		while( $this->running ) {
			usleep( 200000 ); // reduce cpu loading, let other system tasks to work
			pcntl_signal_dispatch( );
		}
	}
	
	protected function Terminate( int $signo ) : void {
		$this->running = false;
	}
}

$pidFilename = '/tmp/dummy_daemon.pid';
echo "PID filename '$pidFilename'\n";

$daemon = new DummyDaemon( $pidFilename );
$daemon->Start( );
