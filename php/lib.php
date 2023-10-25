<?php
namespace lib;

/**
 * Добавляет слэш в конец строки, если надо
 *
 * @param string $value строка для изменения
 *
 * @return string
 */
function AppendTrailingSlash( $value ) {
	return preg_match( '/\/$/u', $value ) ? $value : $value.'/' ;
}

/**
 * Регистрирует функцию автозагрузки для дерева каталога
 *
 * @param string $rootNs корень пространства имен
 * @param string $baseDir корень в файловой системе
 * @param bool $checkFileExists проверять ли наличие файла
 *
 * @return void
 */
function AutoloadTree( $rootNs, $baseDir, $checkFileExists = false ) {
	spl_autoload_register( function( $class ) use( $rootNs, $baseDir, $checkFileExists ) {
		if ( preg_match( '/^'.preg_quote( $rootNs, '/' ).'/', $class ) ) {
			$class = preg_replace( '/^'.preg_quote( $rootNs, '/' ).'/', '', $class );
			$parts = preg_split( '/\\\/', $class, -1, PREG_SPLIT_NO_EMPTY );
			$filename = AppendTrailingSlash( $baseDir ).join( '/', $parts ).'.php';
			
			if ( $checkFileExists && !file_exists( $filename ) ) {
				return;
			}
			
			require_once( $filename );
		}
	} );
}
