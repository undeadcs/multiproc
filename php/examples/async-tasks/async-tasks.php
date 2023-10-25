<?php
// async tasks example
require_once( __DIR__.'/../../autoload.php' );
require_once( __DIR__.'/Worker.php' );
require_once( __DIR__.'/Service.php' );

$service = new Service( 3, 4, pid: posix_getpid( ) );
$service->Start( );

echo "done\n";
exit( 0 );