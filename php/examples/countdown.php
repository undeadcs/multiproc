<?php
// simple countdown
require_once( __DIR__.'/../autoload.php' );

use multiproc\Process;
use multiproc\process\Forking;
use multiproc\exceptions\PcntlLastError;
use multiproc\process\Managed;
use multiproc\ProcessStatus;

class Countdown extends Forking {
	protected $count = 10;
	
	public function __construct( int $count, string $workingDir = '', string $command = '', int $pid = 0, ?Process $parentProc = null ) {
		parent::__construct( $workingDir, $command, $pid, $parentProc );
		
		$this->count = $count;
	}
	
	protected function CommonCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid] common code for child {$this->pid}\n";
	}
	
	protected function ParentCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid] parent code for child {$this->pid}\n";
	}
	
	protected function ChildCode( ) : void {
		$pid = posix_getpid( );
		echo "[INFO pid=$pid] child code for child {$this->pid}\n";
		
		$this->exitStatus = $this->count;
		
		do {
			echo '['.str_pad( $this->pid, 10, ' ', STR_PAD_LEFT ).'] '.$this->count."\n";
			sleep( 1 );
		} while( --$this->count );
	}
}

$process = new class ( getcwd( ), cli_get_process_title( ), posix_getpid( ) ) extends Managed {
	protected function ChildWaitFailed( Managed $child, PcntlLastError $e ) : void {
		echo '[ERROR] pid='.$this->pid.'] filed to wait child with pid='.$child->pid."\n\t".$e->getMessage( )."\n";
	}
	
	protected function ChildStarted( Managed $child ) : void {
		echo '[INFO pid='.$this->pid.'] started child with pid='.$child->pid."\n";
	}
	
	protected function ChildExited( Managed $child ) : void {
		echo '[INFO pid='.$this->pid.'] child pid='.$child->pid." exited with code {$child->lastProcessStatus->exitCode}\n";
	}
	
	protected function UnregisteredChildExited( int $pid, ProcessStatus $status ) : void {
		echo "[ERROR] exited unknown child PID=$pid status={$status->status}\n";
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
};
$process->Start( );
echo 'master process pid='.$process->GetPid( )."\n";

echo "starting children\n";
$process->StartAll( new Countdown( 6 ), new Countdown( 9 ), new Countdown( 12 ) );

echo "waiting children\n";
$process->WaitAll( );
