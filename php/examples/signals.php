<?php
// shows how signal handlers works
// after fork they all inherited (as code) by child process
require_once( __DIR__.'/../autoload.php' );

use multiproc\process\Managed;
use multiproc\process\Forking;

class Child extends Forking {
	public function __construct( string $title ) {
		$this->title = $title;
	}
	
	protected function CommonCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid][{$this->title}] common code for child {$this->pid}\n";
	}
	
	protected function ParentCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid][{$this->title}] parent code for child {$this->pid}\n";
	}
	
	protected function ChildCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid][{$this->title}] child code for child {$this->pid}\n";
		
		$time = time( );
		
		while( ( time( ) - $time ) < 8 ) {
			pcntl_signal_dispatch( );
			usleep( 200000 );
		}
	}
}

class ChildSignaled extends Child {
	protected function ChildCode( ) : void {
		if ( !$this->BindSignal( SIGUSR1, [ $this, 'CustomSigHandler' ] ) ) {
			echo "[ERROR][{$this->title}] failed to bind signals\n";
			
			return;
		}
		
		parent::ChildCode( );
	}
	
	protected function CustomSigHandler( int $signo ) : void {
		echo 'CustomSigHandler [signo='.$signo.']['.$this->title."]\n";
	}
}

$process = new class ( getcwd( ), cli_get_process_title( ), posix_getpid( ) ) extends Managed {
	protected function ChildStarted( Managed $child ) : void {
		echo '[INFO pid='.$this->pid.'][parent] started child with pid='.$child->pid."\n";
	}
	
	protected function ChildExited( Managed $child ) : void {
		echo '[INFO pid='.$this->pid.'][parent] exited child with pid='.$child->pid."\n";
	}
	
	public function Start( ) : bool {
		return $this->BindSignal( SIGUSR1, [ $this, 'SigHandler' ] );
	}
	
	public function SigHandler( int $signo ) : void {
		$pid = posix_getpid( );
		echo "[INFO][parent] this is default signal($signo) handler, this->pid={$this->pid}, posix_getpid=$pid\n";
	}
	
	public function StartAll( Managed ...$children ) : void {
		foreach( $children as $child ) {
			if ( !$this->StartChild( $child ) ) {
				echo "[ERROR] failed to start child\n";
			}
		}
	}
	
	public function WaitAll( ) : void {
		if ( $this->WaitChildren( ) ) {
			echo "[INFO] successfully wait children\n";
		} else {
			echo "[ERROR] failed to wait children\n";
		}
	}
	
	public function SendAll( ) : void {
		$this->SendSignalToChildren( SIGUSR1 );
	}
};

$process->Start( );
echo '[INFO][parent] process pid='.$process->GetPid( )."\n";

echo "starting children\n";
$process->StartAll(
	new Child( 'child1' ), // signal handler inherited
	new ChildSignaled( 'child2' ), // signal handler rebound
	new Child( 'child3' ) // signal handler inherited
);

echo "[INFO][parent] sending SIGUSR1 to children\n";
$process->SendAll( );

echo "[INFO][parent] sending SIGUSR1 to current context\n";
$process->SendSignal( SIGUSR1 );
echo "[INFO][parent] dispatching signals\n";
pcntl_signal_dispatch( );

echo "[INFO][parent] waiting children\n";
$process->WaitAll( );
