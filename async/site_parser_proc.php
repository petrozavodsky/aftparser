<?php
//
/**
 * 
 * Ajax обработчики для парсера сайтов
 * 
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

class SiteParserProc extends pAjax{
	private $echo = true;
	/**
	 * Конструктор
	 */
	function __construct() {

		// хуки парсера ссылок
		add_action( 'wp_ajax_test_parse',  	array($this, 'test_parse') );
		add_action( 'wp_ajax_parse_single',  	array($this, 'parse_single') );
		
		// хук сохранения парсера для отложенной обработки
		add_action( 'wp_ajax_save_parser',    array($this, 'save_parser') );
		parent::__construct();
	}
	
	/**
	 * Обработчики парсера ссылок
	 */
	 
	function parse_single(){
		$this->validate_ajax();
		set_time_limit(0);
		parse_str($_POST['fields'], $a); // Извлекаем переменные формы
		$url		= trim($_POST['url']);
		$cat_id 	= $a['cat_id'];
		
		$publ_mode 	= isset($a['publication_mode']) && !empty($a['publication_mode']) ? htmlspecialchars($a['publication_mode']) : htmlspecialchars($a['status']);
		$macro 		= $a['macro'];
		$links_list	= htmlspecialchars_decode($a['links_list']);
		$ub_mode 	= $a['use-borders'];
		$add_media 	= isset($a['add_media']) && $a['add_media'] == 1 ? true : false;
		$extract 	= isset($a['extract']) && $a['extract'] == 1 ? true : false;
		$noiframe 	= isset($a['noiframe']) && $a['noiframe'] == 1 ? true : false;
		$noimages 	= isset($a['noimages']) && $a['noimages'] == 1 ? true : false;
		$auto_thumb = isset($a['auto_thumb']) && $a['auto_thumb'] == 1 ? true : false;
		$add_toc 	= isset($a['add_toc']) && $a['add_toc'] == 1 ? true : false;
		$add_backlink 	= isset($a['add_backlink']) && is_numeric($a['add_backlink']) ? intval($a['add_backlink']) : 0;
		$clear_images 	= isset($a['clear_images']) && $a['clear_images'] == 1 ? true : false;
		
		$minfsize = isset($a['minfsize']) ? intval($a['minfsize']) : "10000";
		$minfwidth = isset($a['minfwidth']) ? intval($a['minfwidth']) : "300";
		$maxipp = isset($a['maxipp']) ? intval($a['maxipp']) : "1000";
		$query_args = isset($a['query_args']) ? stripslashes($a['query_args']) : "";
		$min_content_len = isset($a['min_content_len']) ? intval($a['min_content_len']) : 0;
		$max_content_len = isset($a['max_content_len']) ? intval($a['max_content_len']) : 0;

		if ( ! function_exists( 'post_exists' ) ) {
		    require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}
		if ( ! function_exists( 'get_current_user_id' ) ) {
		    require_once( ABSPATH . '/wp-includes/user.php'); 
		}
		
		// для парсера запросов
		$state = post_exists($url);
		if($state != false && $state != 0){
			
			$post_title = wp_unslash( sanitize_post_field( 'post_title', $url, 0, 'db' ) );
			$state = post_exists($url); // двойная проверка на всякий случай
			
		}
		
		$pr = new Parser();
		$pr->add_media = $add_media;
		$pr->minfsize = $minfsize;
		$pr->minfwidth = $minfwidth;
		$pr->maxipp = $maxipp;
		$pr->clear_images = $clear_images;
		$pr->extract = $extract;
		$pr->add_backlink = $add_backlink;
		$res = "";
		if( $state == false || $state == 0 ){
			$borders = $_POST['borders'];
			if( stripos($ub_mode,"yes") !== false ){
				$pr->prepare_borders_parser($borders,$macro);
				$res = $pr->parse($url);
			} else if( $ub_mode == "easy" ){
				$res = $pr->parse_easy( trim($url) );
			} else {
				// парсинг с селекторами
				$pr->prepare_selector_parser($a);
				$res = $pr->parse_selectors($url);

			}
			
			$publ = new AFPublisher($publ_mode, $cat_id);
			$state = $publ->publicate($res,$url,$auto_thumb);
		}

		if($state == true || $state != 0){
			echo json_encode(array("success","Метариал успешно добавлен (или уже существует) <a target='_blank' href='".admin_url( "/post.php?post={$state}&action=edit")."'>[изменить]</a>"));
		}else{
			echo json_encode(array("error","Ошибка добавления материала"));	
		}
		exit;
	}
	
	# Ajax обработчик пробного парсинга
	function test_parse(){
		//error_reporting(E_ALL & ~(E_NOTICE | E_USER_NOTICE));
		$this->validate_ajax();
		parse_str($_POST['fields'], $a); // Извлекаем переменные формы

		$count = 1;
		if(isset($a['tcount'])){
			$count = intval($a['tcount']);
			if($count > 0) $count -= 1;
			else $count = 1;
		}
		
		$links_list	= htmlspecialchars_decode($a['links_list']);
		$cat_id 	= $a['cat_id'];
		$publ_mode 	= $a['status'];
		$macro 		= $a['macro'];
		$ub_mode 	= $a['use-borders'];
		$add_media 	= isset($a['add_media']) && $a['add_media'] == 1 ? true : false;
		$extract 	= isset($a['extract']) && $a['extract'] == 1 ? true : false;
		$noiframe 	= isset($a['noiframe']) && $a['noiframe'] == 1 ? true : false;
		$noimages 	= isset($a['noimages']) && $a['noimages'] == 1 ? true : false;
		$add_toc 	= isset($a['add_toc']) && $a['add_toc'] == 1 ? true : false;
		$add_backlink 	= isset($a['add_backlink']) && is_numeric($a['add_backlink']) ? intval($a['add_backlink']) : 0;
		$minfsize = isset($a['minfsize']) ? intval($a['minfsize']) : "10000";
		$minfwidth = isset($a['minfwidth']) ? intval($a['minfwidth']) : "300";
		$maxipp = isset($a['maxipp']) ? intval($a['maxipp']) : "1000";
		$query_args = isset($a['query_args']) ? stripslashes($a['query_args']) : "";
		$min_content_len = isset($a['min_content_len']) ? intval($a['min_content_len']) : 0;
		$max_content_len = isset($a['max_content_len']) ? intval($a['max_content_len']) : 0;

		$clear_images 	= isset($a['clear_images']) && $a['clear_images'] == 1 ? true : false;
		
		if(isset($_POST['borders'])) foreach($_POST['borders'] as $key => $border){

			$_POST['borders'][$key]['top_border'] = stripslashes($border['top_border']);
			$_POST['borders'][$key]['bottom_border'] = stripslashes($border['bottom_border']);

		}

		$borders = isset($_POST['borders']) ? $_POST['borders'] : array();
		$ll = array_filter( explode("\n", $links_list), 'strlen' );
		shuffle($ll);

		// сохраняем
		$this->echo = false;
		$this->save_parser();

		$output = "";
		$pr = new Parser();
		$pr->add_media = $add_media;
		$pr->extract = $extract;
		$pr->add_backlink = $add_backlink;
		$pr->minfsize = $minfsize;
		$pr->maxipp = $maxipp;
		$pr->minfwidth = $minfwidth;
		$pr->clear_images = $clear_images;
		if( stripos($ub_mode,"yes") !== false ){ 
			// режим парсинга с границами
			if(!$this->validate_data($links_list,$borders, $cat_id, $macro, $publ_mode))
				exit(json_encode(array("error","Какой-то из параметров пуст или имеет не верное значение. 
												Заполните все поля формы, прежде чем тыкать кнопочки.")));

			$pr->prepare_borders_parser($borders,$macro);
			foreach($ll as $key=>$url){
				if($key > $count) continue;
				$text = $pr->parse(trim($url));
				$output .= "\n<br>\n[------------- проход_".$key."--------------]\n<br>\n".$text."\n<br>\n[------------- /проход_".$key."--------------]\n";
			}
		} else if( $ub_mode == "easy" ){
			foreach($ll as $key=>$url){
				if($key > $count) continue;
				$text = $pr->parse_easy(trim($url));
				$output .= "\n<br>\n[------------- проход_".$key."--------------]\n<br>\n".$text."\n<br>\n[------------- /проход_".$key."--------------]\n";
			}
		} else { 
			// парсинг с селекторами
			$pr->prepare_selector_parser($a);
			foreach($ll as $key=>$url){
				if($key > $count) continue;
				$text = $pr->parse_selectors(trim($url));
				$output .= "\n<br>\n[------------- проход_".$key."--------------]\n<br>\n".$text."\n<br>\n[------------- /проход_".$key."--------------]\n";
			}
		}

		$lout = "";

		if(isset($a['link_updates_grab']) && trim($a['link_updates_grab']) != ""){
			// собираем ссылки

			$udata = array(
					"url"=> ($a['link_updates_grab']),
					"regex" => ($a['link_regex']),
					"remsel" => ($a['link_remsel'])
				);

			if (false || get_magic_quotes_gpc()){
				$udata["regex"] = stripslashes($udata["regex"]);
			}


			if($a["link_updates_grab"] == "" || strpos($a["link_updates_grab"],"[none]") !== false) $udata = array();

			$num_links = count($ll);

			$links = $pr->collect_links($udata, $ll, $num_links);

			if(!empty($links)){
				$lout .= "На странице парсинга обновлений найдены ссылки:\n\n";
				$lout = implode("\n", $links);
			}
		}

		// выводим
		exit(($pr->errors != "") ? json_encode(array("error", $pr->errors)) : json_encode(array("success", $lout.$output)));
	}
	
	# Ajax обработчик, сохраняющий сайт для отложенного парсинга
	function save_parser(){
		$this->validate_ajax();
		parse_str($_POST['fields'], $a); // Извлекаем переменные формы
		//error_reporting(E_ALL);
		//ini_set('display_errors', 1);
		$links_list	= htmlspecialchars($a['links_list']);
		unset($a['links_list']);
		$cat_id 	= htmlspecialchars($a['cat_id']);
		$publ_mode 	= htmlspecialchars($a['status']);
		$macro 		= htmlspecialchars($a['macro']);
		if (get_magic_quotes_gpc())  
			$macro = stripslashes($macro);
		else
			$macro = $macro;

		$num_links 	= htmlspecialchars($a['nl']);
		$title 		= htmlspecialchars($a['title']);
		$state 		= isset($_POST['auto_run']) && $_POST['auto_run'] == "run" ? "on" : "off";
		
		$a['add_media'] = isset($a['add_media']) && $a['add_media'] == 1 ? "1" : "2";
		$a['extract'] = isset($a['extract']) && $a['extract'] == 1 ? "1" : "2";
		$a['noiframe'] = isset($a['noiframe']) && $a['noiframe'] == 1 ? "1" : "2";
		$a['noimages'] = isset($a['noimages']) && $a['noimages'] == 1 ? "1" : "2";
		$a['add_toc'] = isset($a['add_toc']) && $a['add_toc'] == 1 ? "1" : "2";
		$a['clear_images'] = isset($a['clear_images']) && $a['clear_images'] == 1 ? "1" : "2";
		$a['auto_thumb'] = isset($a['auto_thumb']) && $a['auto_thumb'] == 1 ? "1" : "2";
		$a['add_backlink'] = isset($a['add_backlink']) && is_numeric($a['add_backlink']) ? $a['add_backlink'] : "2";
		$a['add_hid_backlink'] = isset($a['add_hid_backlink']) && $a['add_hid_backlink'] == 1 ? "1" : "2";
		$a['minfsize'] = isset($a['minfsize']) ? intval($a['minfsize']) : "10000";
		$a['minfwidth'] = isset($a['minfwidth']) ? intval($a['minfwidth']) : "300";
		$a['maxipp'] = isset($a['maxipp']) ? intval($a['maxipp']) : "1000";
		$a['query_args'] = isset($a['query_args']) ? stripslashes($a['query_args']) : "";
		$a['min_content_len'] = isset($a['min_content_len']) ? intval($a['min_content_len']) : "0";
		$a['max_content_len'] = isset($a['max_content_len']) ? intval($a['max_content_len']) : "0";

		$ub_mode 	= $a['use-borders'];
		$mode = 0;
		
		if(isset($_POST['borders'])) foreach($_POST['borders'] as $key => $border){

			$_POST['borders'][$key]['top_border'] = stripslashes($border['top_border']);
			$_POST['borders'][$key]['bottom_border'] = stripslashes($border['bottom_border']);

		}

		$borders = (isset($_POST['borders']) && !empty($_POST['borders'])) ? serialize($_POST['borders']) : "";

		if($this->echo != false && stripos($ub_mode,"yes") !== false && !$this->validate_data($links_list, array("0"), $cat_id, $macro, $publ_mode)) 
			exit(json_encode(array("error","Какой-то из параметров пуст или имеет не верное значение. 
				Заполните все поля формы, прежде чем тыкать кнопочки.")));
		global $wpdb;

		$id = NULL;

		$table_name = $wpdb->prefix . 'aft_parser';

		$query = $wpdb->prepare("SELECT `id` FROM {$table_name}
									WHERE `title` = '%s'", 
										array(
											$title,
										)
									);
		$data = $wpdb->get_results($query, ARRAY_A);
		$cnt = isset($data[0]["id"]) ? intval($data[0]["id"]) : 0;

		if($cnt && $cnt != 0){
			$a['pid'] = $cnt;
		}

		// xmlproxy
		if(isset($a["yemail"]) && $a["yemail"] != ""){
			update_option("aft_yemail", $a["yemail"] );
		}

		if(isset($a["ykey"]) && $a["ykey"] != ""){
			update_option("aft_ykey", $a["ykey"] );
		}

		if(isset($a["yregion"]) && $a["yregion"] != ""){
			update_option("aft_yregion", $a["yregion"] );
		}

		if(isset($a["exclude_urls"]) && $a["exclude_urls"] != ""){
			update_option("aft_exclude_urls", $a["exclude_urls"] );
		}

		// смена режима
		if(isset($a["use_y_xml"]) && $a["use_y_xml"] == "yandex"){
			update_option("use_y_xml", "yandex" );
		}else{
			update_option("use_y_xml", "xmlproxy" );
		}
		
		if(isset($a['pid'])){
			
			$id = $a['pid'];
			$wpdb->show_errors = TRUE;
			$wpdb->suppress_errors = FALSE;
			$args = array( 
					'id'			=> intval($id), 
					'title'			=> $title,
					'links_list'	=> $links_list,
					'num_links' 	=> $num_links, 
					'borders'		=> $borders, 
					'macro'	    	=> $macro, 
					'publ_mode'		=> $publ_mode,
					'cat_id'		=> $cat_id,
					//'state'			=> $state, 
					'mode'			=> $mode,
					'custom_data'	=> serialize($a),
				);
			//if($state == "on") $args['last_parsed']	= "";
			if(isset($a["reset"]) && intval($a["reset"]) == 1) $args['last_parsed']	= ""; // по тыку на сохранение - обнуляем last_parsed
			$res = $wpdb->update( 
				$table_name, 
				$args,
				array("id" => $id)
			);
			
			if($this->echo != false){
				echo json_encode(array("success",'Изменения сохранены!', $id));
			}
		}else{
			$res = $wpdb->insert( 
				$table_name, 
				array( 
					'id'			=> NULL, 
					'title'			=> $title,
					'links_list'	=> $links_list,
					'num_links' 	=> $num_links, 
					'borders'		=> $borders, 
					'macro'	    	=> $macro, 
					'publ_mode'		=> $publ_mode,
					'cat_id'		=> $cat_id,
					'state'			=> $state, 
					'last_parsed'	=> "",
					'mode'			=> $mode,
					'custom_data'	=> serialize($a),
				)
			);
			$id = $wpdb->insert_id;
			if($this->echo != false){
				if($res != false){
					echo json_encode(array("success",'Изменения сохранены!', $wpdb->insert_id));
				}else{
					echo json_encode(array("error",'Ошибка сохранения!'));
				}
			}
		}
		if($id && $id > 0){
			if(isset($a['link_updates_grab'])){
				$update_d = array(
						"url"=> ($a['link_updates_grab']),
						"regex" => ($a['link_regex']),
						"remsel" => ($a['link_remsel'])
					);

				if (false || get_magic_quotes_gpc()){
					$update_d["regex"] = stripslashes($update_d["regex"]);
				}
				
				if($a["link_updates_grab"] == "" || strpos($a["link_updates_grab"],"[none]") !== false) $update_d = array();
				update_option( "aft_data_update".$id, $update_d );
			}else{
				update_option( "aft_data_update".$id, array() );
			}

			if(isset($a["aft_proxy"]) && trim($a["aft_proxy"]) != ""){

				update_option( "aft_proxy_list", trim($a["aft_proxy"]) );

			}else{

				update_option( "aft_proxy_list","" );
			
			}
		}

		if($this->echo)
			exit;
	}
	
}

new SiteParserProc();
// end of file //