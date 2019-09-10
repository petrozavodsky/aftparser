<?php
//
/**
 * 
 * Главная страница модуля
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

include_once(AFTPARSER__PLUGIN_DIR.'class/parsersListTable.php');

class IndexPage{
	public $items_table;

	function __construct() {
		$this->items_table = new ParsersListTable();
	}
}

global $wpdb;
$table_name = $wpdb->prefix.'aft_parser';

if(isset($_GET['action'])){
	$id = intval($_GET['parser_id']);
	if($_GET['action'] == "on")
		$wpdb->update( $table_name, array('state'=>'on'), array('id'=>$id));	// $wpdb->update( $table, $data, $where, $format = null, $where_format = null );
	if($_GET['action'] == "off")
		$wpdb->update( $table_name, array('state'=>'off'), array('id'=>$id));
	if($_GET['action'] == "delete")
		$wpdb->delete( $table_name, array('id'=>$id)); 	// $wpdb->delete( $table, $where, $where_format = null ); 
}

if(isset($_POST['action']) && $_POST['action'] == "on_all"){
		$query = $wpdb->prepare("SELECT `id`, `title`, `publ_mode`, `cat_id`, `last_parsed`, `mode`, `state`, `num_links`
								FROM {$table_name}");

		$data = $wpdb->get_results($query, ARRAY_A);
		
		foreach ($data as $key => $item) {
			$wpdb->update( $table_name, array('state'=>'on'), array('id'=>$item["id"]));
		}
	}

	if(isset($_POST['action']) && $_POST['action'] == "off_all"){
		$query = $wpdb->prepare("SELECT `id`, `title`, `publ_mode`, `cat_id`, `last_parsed`, `mode`, `state`, `num_links`
								FROM {$table_name}");

		$data = $wpdb->get_results($query, ARRAY_A);

		foreach ($data as $key => $item) {
			$wpdb->update( $table_name, array('state'=>'off'), array('id'=>$item["id"]));
		}
	}

//echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>'; // _get_cron_array() - не документированна функция wp, показывающая все задачи для встроенного cron'а

$main = new IndexPage();
$main->items_table->prepare_items();
?>

<div class="wrap">
	<h2>Создать новый:
		<a href="?page=aft_parser_plinks_parser" class="add-new-h2">Парсер ссылок</a>
		<a href="?page=aft_parser_prss_parser" class="add-new-h2">Парсер обновлений rss</a>
	</h2>
	
	<div class="aft_info">
		<p>В списке, приведенном ниже, отображаются парсеры, доступные для обработки в данный момент.</p>
		<p><strong>Значения ключевых колонок:</strong></p>
		<ol>
			<li>Публикация - Режим, в котором сохраняются материалы, полученные от парсера.</li>
			<li>Состояние: <i>On</i> - парсер в данный момент работает. <i>Off</i> - парсер обработал все ссылки и ожидает удаления\изменения.</li>
			<li>Шаг - количество материалов, которое парсится за один проход.</li>
		</ol>

		<form method="POST">
			<legend>Блочные операции</legend>
			<button type="submit" value="on_all" name="action" class="button-primary">Включить все</button>
			<button  value="off_all" name="action" class="button-secondary">Выключить все</button>
		</form>
	</div>
	<p>
	<img src='<?php echo AFTPARSER__PLUGIN_URL . "/img/aft_icon.png";?>' id="main_i"></img>
	</p>

	<form method="post">
		<input type="hidden" name="page" value="clt_page" /> <!-- Этот параметр нужен для $_REQUEST['page'] --> 
		<?php
			$main->items_table->search_box('Поиск по названию', 'search_by_title');
			$main->items_table->display(); 
		?>
	 </form>
 </div>
 
 <style type="text/css">
	.wp-list-table .column-id { width: 5%; }
	.wp-list-table .column-title { width: 35%; }
	.wp-list-table .column-publ_mode { width: 12%; }
	.wp-list-table .column-state { width: 10%; }
	.wp-list-table .column-last_parsed { width: 10%; }
	.wp-list-table .column-mode { width: 12%; }
	.wp-list-table .column-cat_id { width: 10%; }
</style>