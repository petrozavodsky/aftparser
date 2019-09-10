<?php
//
/**
 * 
 * Класс инкапсулирующий WP_List_Table. Тут мы формируем таблицу отложенных парсеров.
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

if(!class_exists( 'WP_List_Table' )) { // еще один баг вротпресса... ну сколько можно, блджад! уже 3 бага насчитал...
    include_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class_alias('WP_List_Table', 'AftLT');	// некрасиво. Я не допускаю слеш в именах классов.

class ParsersListTable extends AftLT{
	
	/**
	 * 
	 * Конструктор
	 * 
	 */
    function __construct(){
        parent::__construct(
			array(
				'singular'  => 'Парсер',	//Название одной записи
				'plural'    => 'Парсеры',	//Название нескольких записей
				'ajax'      => false,		//Поддержка Ajax
			)
		);
    }
	
	/**
	 * 
	 * Если данных нет
	 * 
	 */
	function no_items(){
		echo 'Данные отсутствуют.';
	}
	
	/**
	 * 
	 * Тут определеся какие данные будут каким колонкам соответствовать. У нас массив [{id:'0', title:'Название',....}], соответственно выборка будет $item['title'], $item['id'].
	 * В данном случае $column_name изменяется и принимает значения 'id','title' и другие, при итерациях цикла. Поэтому и выборка идет в виде: return $item[$column_name];
	 * 
	 */
	function column_default($item, $column_name) {
		switch($column_name){ 
			case 'id':
			case 'title':
			case 'publ_mode':
			case 'cat_id':
			case 'last_parsed':
			case 'state':
			case 'num_links':
				return $item[$column_name];
				break;
			case 'mode':
				if($item['mode'] == 0){
					return "Парсинг ссылок";
				}
				if($item['mode'] == 1){
					return "Парсинг rss";
				}
				break;
			default:
				return print_r( $item, true ) ;	// По умолчанию распечатываем весь массив
		}
	}
	
	/**
	 * 
	 * Тут можно указать какие колонки мы хотим сделать сортируемыми
	 * 
	 */
	function get_sortable_columns() {
		return array(
				'id'  		=> array('id',false),
				'title' 	=> array('title',false),
				'publ_mode'	=> array('publ_mode',false),
				'state'   	=> array('state',false),
			);
	}
	
	/**
	 * 
	 * Тут указываются надписи над колонками
	 * 
	 */
	function get_columns(){
		$columns = array(
			//'cb'        	=> '<input type="checkbox" />',
			'id' 			=> 'ID',
			'title'    		=> 'Название',
			'publ_mode'	 	=> 'Публикация',
			'state' 		=> 'Состоние',
			'cat_id'		=> 'ID категории',
			'last_parsed' 	=> 'Триггер',
			'mode' 			=> 'Режим применения',
			'num_links' 	=> 'Шаг',
			);
		return $columns;
    }
	
	/**
	 * 
	 * Значения сортировки по умолчанию.
	 * 
	 */
	function usort_reorder($a, $b) {
		// Set defaults
        $orderby = 'id';
        $order = 'asc';
        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }
        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }
        $result = strcmp( $a[$orderby], $b[$orderby] );
        if($order === 'asc')
        {
            return $result;
        }
        return -$result;
	}
	/*function usort_reorder($a, $b) {
		$order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
		
		if($_GET['orderby'] == "state"){
			$a = $a[$orderby] == "off" ? 1 : 0;
			$b = $b[$orderby] == "off" ? 1 : 0;
			$result = strnatcmp($a, $b);
			return ($order === 'desc') ? -$result : $result;
		}

		// По умолчанию сортировка идет по айди
		$orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';
		
		// Переопределяем порядок сортировки
		$result = strnatcmp($a[$orderby], $b[$orderby]);
		return ($order === 'desc') ? -$result : $result;
	}*/
	
	/**
	 * 
	 * Названия дополнительных ссылок в каждой колонке.(Edit, Delete)
	 * Формат функции: column_{$keyname}, где keyname - имя колонки, к которой будут добавляться доп. ссылки.
	 */
	function column_title($item){
		$edit_page_url = "aft_parser_plinks_parser";
		if($item['mode'] == 1){
			$edit_page_url = "aft_parser_prss_parser";
		}
		if($item['mode'] == 2){
			$edit_page_url = "aft_parser_req_parser";
		}
		$gets_on = $_GET;
		$gets_on["action"] = "on";
		$gets_on["parser_id"] = $item['id'];


		$gets_off = $_GET;
		$gets_off["action"] = "off";
		$gets_off["parser_id"] = $item['id'];

		$gets_delete = $_GET;
		$gets_delete["action"] = "delete";
		$gets_delete["parser_id"] = $item['id'];

		$actions = array(
			'edit'	=> sprintf('<a href="?page=%s&action=%s&parser_id=%s">Изменить</a>', $edit_page_url, 'edit', $item['id']),
			'on'	=> '<a href="?'.http_build_query($gets_on).'">Включить</a>',
			'off'	=> '<a href="?'.http_build_query($gets_off).'">Выключить</a>',
			'delete'=> '<a href="?'.http_build_query($gets_delete).'">Удалить</a>',
		);
		return sprintf('%1$s %2$s', $item['title'], $this->row_actions($actions));
	}
	
	/**
	 * 
	 * Действия, которые можно применить для наскольких выбранных элементов
	 * 
	 */
	/*
	function get_bulk_actions() {
		$actions = array(
			'delete'  => 'Удалить',
		);
		return $actions;
	}*/

	function column_cb($item) {
        //return sprintf('<input type="checkbox" name="parsers[]" value="%s" />', $item['ID']);    
    }
	
	/**
	 * Тут мы формируем массив данных, отображаемый при поиске и при перехода по страницам
	 * Данные поиска, берутся из $_POST['s'], $page берется из $this->get_pagenum(); $num_items - количество элементов на одной странице
	 */
	function get_data_array($page, $num_items){
		//error_reporting(~0);
		//ini_set('display_errors', 1);
		$page = $page - 1;	//Весь прикол в том, что ебаный вротперсс начинает отсчет страниц не с 0, как это в php, а с 1. Пизда тупая...
		global $wpdb;
		$table_name = $wpdb->prefix.'aft_parser';
		$orderby = (isset($_GET['orderby'])) ? $_GET['orderby'] : 'id';

		$order = isset($_GET['order']) && in_array($_GET['order'], array("asc","desc","ASC","DESC")) ? strtolower($_GET['order']) : 'desc';
		$order = strtoupper($order);
		
		//Обычный запрос для первой страницы
		$query = $wpdb->prepare("SELECT `id`, `title`, `publ_mode`, `cat_id`, `last_parsed`, `mode`, `state`, `num_links`
								FROM {$table_name} ORDER BY `".$orderby."` ".$order." LIMIT %d,%d",
									array(
										$page*$num_items,
										$num_items
									)
								);
		
		$search = "";
		if(isset($_POST['s']) && $_POST['s']){	//Если массив пуст его значение == false
			$query = $wpdb->prepare("SELECT `id`, `title`, `publ_mode`, `cat_id`, `last_parsed`, `mode`, `state`, `num_links`
									FROM {$table_name}
									WHERE `title` LIKE %s ORDER BY `".$orderby."` ".$order, 
										array(
											'%'.$wpdb->esc_like($_POST['s']).'%'
										)
									);
		}
		
		return $wpdb->get_results($query, ARRAY_A);	// ARRAY_A - ассоциативный массив, то же самое что и mysql_fetch_assoc
	}
	
	/**
	 * 
	 * Возвращает кол-во записей в таблице. $def - количество, которое получается из count($found_data)
	 * 
	 */
	function get_total_items($def){
		if(isset($_POST['s'])){
			return $def;
		}
		global $wpdb;
		$table_name = $wpdb->prefix.'aft_parser';
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
		if($count == null) return $def;
		else return $count;
	}
	
	/**
	 *
	 * Основаня функция. Тут идет получение данных для табицы($this->get_data_array), плучение колонок($this->get_columns) и прочей хрени.
	 *
	 */
	function prepare_items() {
		$hidden   = array();						// Скрытые колонки, которых нет, но можно добавить
		
		$columns  = $this->get_columns();			// Получаем колонки
		$sortable = $this->get_sortable_columns();	// Определяем, какие из них можно сортировать, а какие- нельзя
		
		$this->_column_headers = array($columns, $hidden, $sortable);	// Задаем заголовки

		$per_page = 15;				// Число элементов на одной странице
		$cp = $this->get_pagenum();	//$cp - не удержался, троллинг власти даже в коде весьма уместен
		
		$found_data = $this->get_data_array($cp, $per_page);		// Получение данных для конкретного представления
		$total_items = $this->get_total_items(count($found_data));	// Получение общего количества элементов в таблице
		//usort($found_data, array( &$this, 'usort_reorder' ));		// Сортировка
		
		if(!isset($_POST['s'])){
			$this->set_pagination_args( 
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			) 
		);
		}
		
		$this->items = $found_data;	// Задаем содержимое таблицы
	}

}
// end of file //