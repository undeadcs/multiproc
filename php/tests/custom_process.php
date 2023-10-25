<?php
// utility script for creating process with changed: command (title) and working directory
$title = $_SERVER[ 'argv' ][ 1 ];
$workingDir = $_SERVER[ 'argv' ][ 2 ];
$sleep = $_SERVER[ 'argv' ][ 3 ] ?? 3;

cli_set_process_title( $title );
chdir( $workingDir );

echo getcwd( )."\n";
echo cli_get_process_title( )."\n";

sleep( $sleep );
exit( 0 );
