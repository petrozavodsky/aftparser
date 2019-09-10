<?php
//
/**
 *
 * @package AftParser
 *
 *
 *	Plugin Name: AftParser
 *	Plugin URI: http://aftamat4ik.ru/
 *	Description: Парсер контента с из различных источников
 *	Version: 1.9.9
 *	Author: ваш Гарри
 *	Author URI: http://aftamat4ik.ru/
 *	License: GPLv2 or later
 *
 *	Copyright (C) 11-11-2015 Aftamat4ik
 *
 *	This program is free software; you can redistribute it and/or
 *	modify it under the terms of the GNU General Public License
 *	as published by the Free Software Foundation; either version 2
 *	of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *	Плагин парсера сайтов от Гарри.
 *	Благодарю вас за проявленный интерес к коду.
 *	Чтобы лучше разобраться в функционале посетите страницу плагина - http://aftamat4ik.ru/еще_не_создал
 *
 *	Заметка для того, кто будет читать код: 
 *		Это мой первый модуль под wp, до этого я пользовался сугубо самописными движками, а потому немного повернут на проверке всего и вся...
 *		Каждый или почти каждый метод имеет соответствующие комментарии.
 *		Все *.php файлы начинаются с // из-за бага, который присутствовал в версии php 5.4, проявляется он редко, но метко.
 *		Я старался снизить нагрузки на слабые сервера, что от части мне таки удалось, но код стал запутанным. Чтобы лучше понять механизм работы, придется читать файлы *-parser-scripts.js.
 *		Перевод на английский язык не планируется, поэтому я не использовал функции __() и _e().
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

# Константы
define( 'AFTPARSER__PLUGIN_URL', trailingslashit(plugin_dir_url( __FILE__ )) );
define( 'AFTPARSER__PLUGIN_DIR', trailingslashit(plugin_dir_path( __FILE__ )) );
define( 'AFTPARSER__PLUGIN_MAIN', __FILE__ );
/************/


include_once( ABSPATH . WPINC . '/pluggable.php');				// баг. Call to undefined function wp_get_current_user()... лечится этой строчкой
include_once( AFTPARSER__PLUGIN_DIR . 'functions.php');			// различные функции, если пользователь хочет ипользовать в парсере свою функцию, он может добавить ее в этот файл.
include_once( AFTPARSER__PLUGIN_DIR . "class/parser.php");		// класс парсера.
include_once( AFTPARSER__PLUGIN_DIR . "class/pAjax.php");		// класс асинхронной обработки.
include_once( AFTPARSER__PLUGIN_DIR . "class/publisher.php");	// класс публикатора.
include_once( AFTPARSER__PLUGIN_DIR . 'postprocessing.php' );	// класс, отвечающий за отложеный парсинг. В него встроена эмуляция cron'a. Когда пользователь обновляет любую страницу на сайте, этот cron вызывается.
include_once( AFTPARSER__PLUGIN_DIR . 'toolbar.php' );	// тулбар
			
if(!function_exists("str_get_html")) # подключаем simple_html_dom
	include_once( AFTPARSER__PLUGIN_DIR . 'class/simple_html_dom.php');

if (is_admin()) {
	include_once( AFTPARSER__PLUGIN_DIR . 'ajax.php' );				// тут подключаются ajax обработчики.
	include_once( AFTPARSER__PLUGIN_DIR . 'main.php' );				// основной файл плагина. Создание таблиц в базе, создание бокового меню и подключение прочих файлов.
}

// end of file //