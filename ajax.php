<?php
//
/**
 * 
 * Ajax Обработчики.
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}


/**
 * 
 * Так как код для ajax обработчиков слишком разросся, я решил вынести его в отдельные файлы, располоенные в папке /async/
 * Ниже мы подключаем эти файлы в цикле.
 * 
 */

$files = scandir(AFTPARSER__PLUGIN_DIR . "async/");

foreach($files as $key=>$file){
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	if($ext == 'php'){	// Если файл имеет расширение .php мы его подрубаем.
		include_once(AFTPARSER__PLUGIN_DIR . "async/" . $file);
	}
}
// end of file //