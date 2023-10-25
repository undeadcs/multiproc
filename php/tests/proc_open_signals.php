<?php
// проверка как работают сигналы при использовании proc_open
// по итогу оказалось, что SIGCHLD принимается при остановке дочернего процесса, следовательно proc_get_status можно выкинуть

pcntl_signal( SIGCHLD, 'SigchldHandler' );

$pipes = [ ];
$spec = [ ];
$proc = proc_open( 'exec /bin/sh -c "sleep 1 && exit 4"', $spec, $pipes );
if ( !$proc ) {
	echo "failed to start\n";
	exit( 1 );
}

$status = proc_get_status( $proc );
$pid = $status[ 'pid' ];
echo "started child with pid '$pid'\n";

$try = 5;

while( $try > 0 ) {
	echo $try."\n";
	pcntl_signal_dispatch( );
	sleep( 1 );
	--$try;
}

/*$status = 0;
echo "pcntl_wait\n";
pcntl_wait( $status );
InfoWaitStatus( $status );*/

echo "proc_close\n";
proc_close( $proc );

function SigchldHandler( int $signo ) : void {
	echo "SIGCHLD\n";
	
	$status = 0;
		
	while( ( $pid = pcntl_waitpid( -1, $status, WNOHANG ) ) > 0 ) { // pcntl_waitpid returns 0 when no children exited or -1 on error
		echo "exited child with pid '$pid'\n";
		
		InfoWaitStatus( $status );
		
		$status = 0;
	}
}

function InfoWaitStatus( int $status ) : void {
	echo '  status='.$status."\n";
	echo '  status bin='.decbin( $status )."\n";
	
	if ( pcntl_wifexited( $status ) ) {
		echo '  exited with code='.pcntl_wexitstatus( $status )."\n";
	}
	if ( pcntl_wifsignaled( $status ) ) {
		echo '  signaled with sig='.pcntl_wtermsig( $status )."\n";
	}
	if ( pcntl_wifstopped( $status ) ) {
		echo '  stopped with sig='.pcntl_wstopsig( $status )."\n";
	}
}
