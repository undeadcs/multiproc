<?php
// show information about system process
require_once( __DIR__.'/../autoload.php' );

use multiproc\Process;

$process = new Process( $_SERVER[ 'argv' ][ 1 ] ?? posix_getpid( ) );

echo 'PID: '.$process->GetPid( )."\n";
echo 'Command: '.$process->GetCommand( )."\n";
echo 'Working dir: '.$process->GetWorkingDir( )."\n";
