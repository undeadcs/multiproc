#!/usr/bin/php
<?php
// script just get stdin and write it to file
$text = stream_get_contents( STDIN );
file_put_contents( 'stdin.txt', $text );
exit( 0 );