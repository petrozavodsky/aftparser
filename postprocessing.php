<?php
//
/**
 * 
 * Файл, обрабатывающий сайты, схраненные для отложенного парсинга.
 * Так-как стандартный Cron, встроенный во вротпресс - глучная паскуда, то я эмулирую его сам. Ненавижу!!! писать велосипеды еще со времен си++.
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

if(!function_exists("str_get_html")) # подключаем simple_html_dom
	include_once( AFTPARSER__PLUGIN_DIR . 'class/simple_html_dom.php');

class FPostProcessing{
	
	/**
	 * Конструктор
	 */
	function __construct(){

		add_action( 'wp_enqueue_scripts', array($this, 'add_postproc_scripts'));
		add_action( 'admin_enqueue_scripts', array($this, 'add_postproc_scripts'));
		add_action( 'wp_head', array($this, 'add_ajaxurl_cdata_to_front'), 1);
		add_action( 'init', array($this, 'app_init'));

		add_action( 'wp_ajax_aft_docron', array($this, 'aft_docron'),1);
		add_action( 'wp_ajax_nopriv_aft_docron', array($this, 'aft_docron'));
		
	}

	function app_init(){

		$cron_secret = get_option("ap_cron_secret");
		if($cron_secret == false){
			// Если секретное слово для запуска CRON не задано - задаем его
			$cron_secret = h_rand_str(7);
			update_option("ap_cron_secret", $cron_secret);
		}
		
		// парсинг встроенным кроном
		if(isset($_GET['aftcron']) && $_GET['aftcron'] == $cron_secret){
			header('Content-Type: text/html; charset=utf-8');
			error_reporting(E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED));
			ini_set('display_errors', 1);
			$this->process_parsers();
			die("<br>Обработка завершена");
		}
	}

	# На все страницы сайта добавляем js переменную ajaxurl
	
	function add_ajaxurl_cdata_to_front(){
		echo '<script type="text/javascript"> //<![CDATA[
				ajaxurl = "'.admin_url('admin-ajax.php').'";
			//]]></script>';
	}

	# Добавляем скрипт, вызывающий Ajax обработчик крона
	function add_postproc_scripts(){

		// оглавление
		wp_enqueue_script('toc-js', AFTPARSER__PLUGIN_URL.'js/toc.min.js', array( 'jquery' ), '201504087', true );
		wp_enqueue_style( 'toc-css',  AFTPARSER__PLUGIN_URL."css/toc.css" );

		// парсинг
		wp_enqueue_script('aftp-postprocessing', AFTPARSER__PLUGIN_URL . 'js/postprocessing.js', array( 'jquery' ), time(), true);
	}

	function aft_docron(){
		$time_interval = get_option("aft_time_interval");
		if(!$time_interval) $time_interval = HOUR_IN_SECONDS*12;
		$l_time = get_option("aft_cron_ltime"); // последнее время срабатывания
		if(!$l_time){
			$l_time = 0;
		}
		header('Content-Type: text/plain; charset=utf-8');
		//$pps = get_option( "postprocessing_started" );
		//if( $pps && $pps == "on" ) die("Крон уже выполняется");

		if(intval(time()) > intval($l_time) + intval($time_interval) ){
			
			echo "Начнаем обработку CRON\r\n";
			try{
				$this->process_parsers();
			}catch(Exception $e){}
			update_option("aft_cron_ltime", time());
		}else{
			echo "<br>Время еще не пришло\r\n";
		}
		//update_option( "postprocessing_started" , "off" );
		//delete_option( "postprocessing_started" );
		die();
	}
	
	/**
	 * 
	 * Обработка парсера
	 * 
	 */
	function process_parsers() {
		update_option( "postprocessing_started" , "on" );

		global $wpdb;
		$table_name = $wpdb->prefix.'aft_parser';
		set_time_limit(0);
		// выборка всех активных парсеров
		$data_arr = $wpdb->get_results("SELECT * FROM {$table_name} WHERE `state` = 'on'", ARRAY_A);	// ARRAY_A - ассоциативный массив
		
		$lid = get_option( "aft_lparsed_id" );
		

		if($lid >= count($data_arr)) $lid = 0;
		$parser = $data_arr[$lid];

		$lid+=1;
		update_option( "aft_lparsed_id", $lid );
		if(!$parser) echo "<b>Ошибка!</b> в базе нет такого парсера или отложенный постинг не включен ни в одном из парсеров! Включите отложенный постинг на главной странице Aftparser'а!";
		if(intval($parser['mode']) == 0 || intval($parser['mode']) == 2){
			echo "<br>Запуск парсера ссылок ".$parser["title"];
			
			$this->process_links_parser($parser);
			
			echo "<br>готово";
		}
		if(intval($parser['mode']) == 1){
			echo "<br>Запуск парсера rss".$parser["title"];
			
			$this->process_rss_parser($parser);
			
			echo "<br>готово";
		}
		
	}
	
	/**
	 * 
	 * Обработка rss ленты
	 * 
	 */
	function process_rss_parser($parser){
		extract($parser);	// извлекаем все в переменные $title, $mode и так далее
		$rss_url 	= $links_list;	//"ссылка"
		$is_renew	= false;
		$pr 		= new Parser();
		$rss_xml 	= $pr->load($rss_url);
		$blog_enc 	= str_replace(" ","", mb_strtoupper(get_bloginfo('charset')));
		$src_enc 	= str_replace(" ","", mb_strtoupper($pr->auto_detect_encoding($rss_xml)));
		if($blog_enc != $src_enc){
			$content = mb_convert_encoding($content, $blog_enc, $src_enc);
			$title 	 = mb_convert_encoding($title, $blog_enc, $src_enc);
		}
		$src_enc = $blog_enc;
		// У валидной rss ленты в тегах pubDate хранится время последнего обновления этой самой ленты.
		// Если таких тегов нет - парсер работать не будет
		preg_match("#<pubDate>(.*?)</pubDate>#i",$rss_xml,$pdate);	
		if($pdate == false || $pdate == $last_parsed) return;
		
		preg_match_all("#<item(?:[\s\S]*?|)>([\s\S]+?)<\/item>#i", $rss_xml, $matches);	// в rss потоке каждый материал находится между тегами <item></item>
		
		if(!$matches) return;
		
		$publ = new AFPublisher($publ_mode, $cat_id);
		$publ->rss = true;
		foreach(array_reverse($matches[1]) as $iteration => $item){
			$item = preg_replace('#<\!\[CDATA\[([\s\S]*?)\]\]>#i', '\\1', $item); // Убираем CDAT'у
			if(!$item) continue;
			$title = "Untitled";
			$content = "";
			$link = "";
			$category = "";
			$attachments = "";	// Если к ленте прикреплены файлы
			
			$content = $pr->match_rss($item,$title,$link,$category);

			$content = htmlspecialchars_decode($content);
			$content = html_entity_decode($content);
			preg_match_all("#<enclosure(?:[\s\S]+?|)url(?:[\s]|)=(?:[\s]|)['\"](.+?)['\"]#i", $item, $m_att);	// Работа с вложениями
			if($m_att){
				foreach($m_att[1] as $key => $a_url){
					$file = $pr->upload_file($a_url);	// Скачиваем вложение и получаем путь до него
					if($file != false){
						$ext = pathinfo($file, PATHINFO_EXTENSION);
						if(in_array($ext, array("jpg","png","gif","jpeg","JPG","JPEG","GIF","PNG"))){
							$attachments .= "<img src='{$file}' title='{$title}'>\n";
						}else{
					 		$attachments .= "<a href='".$file."' target='_blank'>Файл №".($key+1)."</a><br />\n";
						}
					}
				}
				$item = preg_replace( '#<enclosure([\s\S]+?|)</enclosure>#i', '', $item ); // Убираем старый текст с вложением
			}

			if($attachments != ""){
				$attachments = "<br /><b>Дополнительно:</b><br />\n".$attachments;
			}
			
			$res = "";	// Переменная результата выполнения макроса
			//Запускаем код макроса	
			eval(html_entity_decode(stripslashes_deep(trim($macro))));
					
			$state = $publ->publicate($res);	//Фишка в том, что публишер сам проверяет, существует ли в базе текущая запись. Если нет - то она будет добавлена.
		
		}
		unset($publ);
		unset($pr);
		
		$this->set_last_parsed($pdate[1],$id);
	}	
	
	
	/**
	 * 
	 * Обработка парсера ссылок
	 * 
	 */
	function process_links_parser($parser){
		if (!$parser) {
			echo "<br>Парсер не указан<br>";
			return;
		}
		extract($parser);	// извлекаем все в переменные $title, $mode и так далее
		$title 		= htmlspecialchars_decode($title);
		$num_links	= intval($num_links);
		$publ_mode 	= htmlspecialchars_decode($publ_mode);
		$cat_id		= htmlspecialchars_decode($cat_id);
		$macro		= htmlspecialchars_decode($macro);
		$links_list	= htmlspecialchars_decode($links_list);
		$state		= htmlspecialchars_decode($state);
		$cdata 		= isset($custom_data) ? unserialize($custom_data) : array();
		$ub_mode 	= isset($cdata['use-borders']) ? $cdata['use-borders'] : "no";
		$add_media 	= isset($cdata['add_media']) && $cdata['add_media'] == 1 ? true : false;
		$extract 	= isset($cdata['extract']) && $cdata['extract'] == 1 ? true : false;
		$noiframe 	= isset($cdata['noiframe']) && $cdata['noiframe'] == 1 ? true : false;
		$noimages 	= isset($cdata['noimages']) && $cdata['noimages'] == 1 ? true : false;
		$add_toc 	= !isset($cdata['add_toc']) || $cdata['add_toc'] == 1 ? true : false;
		$auto_thumb = isset($cdata['auto_thumb']) && $cdata['auto_thumb'] == 1 ? true : false;
		$add_backlink = isset($cdata['add_backlink']) && is_numeric($cdata['add_backlink'])  ? $cdata['add_backlink'] : 0;
		$query_args = isset($cdata['query_args']) ? stripslashes($cdata['query_args']) : "";
		$min_content_len = isset($cdata['min_content_len']) ? intval($cdata['min_content_len']) : 0;
		$max_content_len = isset($cdata['max_content_len']) ? intval($cdata['max_content_len']) : 0;

		$minfsize = isset($cdata['minfsize']) ? intval($cdata['minfsize']) : "10000";
		$minfwidth = isset($cdata['minfwidth']) ? intval($cdata['minfwidth']) : "300";
		$maxipp = isset($cdata['maxipp']) ? intval($cdata['maxipp']) : "1000";
		$clear_images 	= isset($a['clear_images']) && $a['clear_images'] == 1 ? true : false;

		$borders = unserialize ($borders);	// десериализация массива границ
		$ll = explode("\n",str_replace("\r", "", $links_list));

		$index = 0; // порядковый номер ссылки last_parsed, которая была спаршена последней

		if(isset($last_parsed) && $last_parsed != ""){
			foreach ($ll as $key => $link) {
				if(trim($link) == trim($last_parsed)){
					$index = $key;
					break;
				}
			}
		}
		$lp = $ll[0];
		if(isset($ll[($index + $num_links)])){
			$lp = $ll[($index + $num_links)];
		}else{
			$lp = $ll[0];
		}
		$this->set_last_parsed($lp,$id);

		$url = "";

		// инициализация
		$pr = new Parser();
		$pr->add_media = $add_media;
		$pr->add_backlink = $add_backlink;
		$pr->minfsize = $minfsize;
		$pr->minfwidth = $minfwidth;
		$pr->maxipp = $maxipp;
		$pr->extract = $extract;
		$pr->clear_images = $clear_images;
		
		if( stripos($ub_mode,"yes") !== false ){
			$pr->prepare_borders_parser($borders,$macro);
		} else {
			$pr->prepare_selector_parser($cdata);
		}

		if(isset($cdata['link_updates_grab']) && trim($cdata['link_updates_grab']) != ""){
			$pr->collect_links($id, $ll, $num_links);
		}
		
		// парсинг+публикация
		$publ = new AFPublisher($publ_mode, $cat_id);
		for($i = 0; $i < intval($num_links); $i++){
			echo "<br>index ".($index);
			if(isset($ll[($index + $i)])){	// если индекс существует, находим элемент массива и парсим страницу.
				$url = trim($ll[($index + $i)]);

				if($url == "") continue;
				$res = "";

				$tmp = get_id_by_meta($url); // дубли - пропускаем
				if($tmp != false || $tmp != 0) continue;
				
				if( stripos($ub_mode,"yes") !== false ){
					$res = $pr->parse($url);
				}else if( stripos($ub_mode,"easy") !== false ) {
					$res = $pr->parse_easy( trim($url) );
				}
				if($res != ""){
					echo "<br>Публикуем\r\n";
					$publ->publicate($res,$url,$auto_thumb);
				}
			}
		}
	}
	
	/**
	 * 
	 * Обновление поля `last_parsed` в таблице
	 * 
	 */
	function set_last_parsed($url, $id){

		global $wpdb;
		$table_name = $wpdb->prefix.'aft_parser';
		
		$wpdb->query($wpdb->prepare("UPDATE {$table_name} SET `last_parsed` = '%s' WHERE `id`=%d",
									array($url, $id)
									));
	}
}

new FPostProcessing();
// end of file //