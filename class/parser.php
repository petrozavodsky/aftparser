<?php
//
/**
 * Парсер, он-же API макросов
 */
 
class Parser{

	public  $cookie;
	public $errors;
	public  $post;
	public $selectors_list = array();
	private $borders_list = array();	
	public $add_media = false;	
	public $extract = true;	
	private $border_pos = 0;	// позиция от начала страницы до начала поиска границы.
	private $macro;
	private $index = 0; // индекс файла
	public $current_url;		// урл сайта, который парсится в данный момент
	public $rnames = false;		// вклюает режим случайных имен картинок
	public $minfsize = 10000;
	public $minfwidth = 300;
	public $maxipp = 1000;
	public $add_backlink = 0;
	public $clear_images = false;
	public $inames = false;
	
	function __construct() {}
	
	/**
	 * Парсер. Основной функционал
	 */
	
	# Подготовка парсера по селекторам.
	public function prepare_selector_parser($data){
		$selectors = array();

		if(!isset($data["title_selector"]) || $data["title_selector"] == "") 
			die( json_encode(array("error", "Селектор заголовка не указан!")) );
		$selectors["title_selector"] = $data["title_selector"];


		if(!isset($data["content_selector"]) || $data["content_selector"] == "") 
			die( json_encode(array("error", "Селектор содержимого не указан!")) );
		$selectors["content_selector"] = $data["content_selector"];

		if(isset($data["download_selector"]) && $data["download_selector"] != ""){
			$selectors["download_selector"] = $data["download_selector"];
		}

		if(!isset($data["exclude_selectors"]) || $data["exclude_selectors"] == "") 
			$selectors["exclude_selectors"] = array();
		else{
			$data["exclude_selectors"] = trim($data["exclude_selectors"]);
			$data["exclude_selectors"] = str_replace("\r", "", $data["exclude_selectors"]);
			$selectors["exclude_selectors"] = explode("\n", $data["exclude_selectors"]);
		}

		if(!isset($data["exclude_replace"]) || $data["exclude_replace"] == "") 
			$selectors["exclude_replace"] = array();
		else{
			$data["exclude_replace"] = trim($data["exclude_replace"]);
			$data["exclude_replace"] = str_replace("\r", "", $data["exclude_replace"]);
			$selectors["exclude_replace"] = explode("\n", $data["exclude_replace"]);
		}

		if(!isset($data["lang-from"]) || $data["lang-from"] == "") 
			$selectors["lang-from"] = false;
		else{
			$selectors["lang-from"] = trim($data["lang-from"]);
		}

		if(!isset($data["lang-to"]) || $data["lang-to"] == "") 
			$selectors["lang-to"] = false;
		else{
			$selectors["lang-to"] = trim($data["lang-to"]);
		}

		if(isset($data["translate_key"]) && $data["translate_key"] != ""){
			update_option("y_translate_key", $data["translate_key"] );
		}

		$this->selectors_list = $selectors;
	}

	# Подготовка парсера по границам.
	public function prepare_borders_parser($borders, $custom_macro){

		$this->borders_list = $borders;
		// убираем дубли
		if( !empty($borders) ) foreach($borders as $key=>$b1){
			$i = 0;
			foreach($this->borders_list as $b2){
				if($b2['top_border'] == $b1['top_border'] && $b2['bottom_border'] == $b1['bottom_border']) $i++;
			}
			if($i >= 2){
				unset($this->borders_list[$key]);
			}
		}
		
		if (get_magic_quotes_gpc()){
			$this->macro = stripslashes($custom_macro);
		}else{
			$this->macro = $custom_macro;
		}
	}
	
	# Парсит переданный урл
	public function parse($url){

		if(empty($this->borders_list) ||  empty($this->macro)) die( json_encode(array("error", "Список границ пуст / макрос пуст!")) );
		$this->current_url = $url;
		$html = $this->load(trim($url));

		if(!$html) return false;
		return $this->process_macro($html);
	}

	# Парсит переданный урл через селекторы
	public function parse_selectors($url){

		if(empty($this->selectors_list)) die( json_encode(array("error", "Список селекторов пуст!")) );

		$this->current_url = $url;
		$html = $this->load(trim($url));

		if(!$html) return false;
		$res = "";

		$blog_enc = str_replace(" ","", mb_strtoupper(get_bloginfo('charset')));
		$src_enc = str_replace(" ","", mb_strtoupper($this->auto_detect_encoding($html)));

		if(!stristr($blog_enc,$src_enc)) $html = mb_convert_encoding($html, $blog_enc, $src_enc);

		$html = str_get_html($html);
		
		$title = trim(html_entity_decode(@$html->find($this->selectors_list["title_selector"],0)->plaintext));

		if($title == "") 
			die( json_encode(array("error", "Заголовок по селектору ".$this->selectors_list["title_selector"]." не найден!")) );

		// удаляем звенья, которые надо удалить
		if(!empty($this->selectors_list["exclude_selectors"])) foreach ($this->selectors_list["exclude_selectors"] as $sel) {
			$nodes = $html->find($sel);
	        if(!empty($nodes)) foreach ($nodes as $node){
	            $node->outertext = '';
	        }
	        $html = str_get_html($html->save());
		}
		
		$content = "";
		if(strpos($this->selectors_list["content_selector"], "[") === false && 
			strpos($this->selectors_list["content_selector"], "nth-child") === false){
			$nodes = @$html->find($this->selectors_list["content_selector"]);
			foreach ($nodes as $key => $cnt) {
				$content .= $cnt->innertext;
			}
		}else{
			$parts = explode(",",$this->selectors_list["content_selector"]);
			foreach ($parts as $key => $part) {
				$content = @$html->find(trim($part),0)->innertext;
			}
		}

		if(isset($this->selectors_list["download_selector"]) && $this->selectors_list["download_selector"] != ""){
			$url = @$html->find($this->selectors_list["download_selector"],0)->href;
			if($url){
				$url = trim($url);
				$url = str_replace("&#9;", "", $url);
				$url = str_replace("&#10;", "", $url);

				$url = $this->_prepareUrl($url);
				$response_headers = get_headers($url,1); 

				$filename = basename($url);   

				// если есть возможность взять имя файла из заголовков
				if(isset($response_headers["Content-Disposition"])){
					if(preg_match('/.*filename=[\'\"]([^\'\"]+)/', $response_headers["Content-Disposition"], $matches)){ 
						$filename = $matches[1]; 
					}else if(preg_match("/.*filename=([^ ]+)/", $response_headers["Content-Disposition"], $matches)){ 
						$filename = $matches[1]; 
					}
				}
				$filename = preg_replace("/[^a-zA-Z0-9_#\(\)\[\]\.+-=]/", "",$filename);
				$media_folder = wp_upload_dir();
				
				$result_url = $media_folder["url"]."/".$filename;

				if($this->download($url, $media_folder["path"]."/".$filename)){
					$content .= "<p><a href='".$result_url."' target='_blank'>Скачать файл ".$filename."</a></p>";
				}
			}
		}

		$content = html_entity_decode(trim($this->remove_script_tags($content)));
		$title = html_entity_decode(trim($this->remove_script_tags($title)));
		// удаляем звенья, которые надо удалить
		if(!empty($this->selectors_list["exclude_replace"])) foreach ($this->selectors_list["exclude_replace"] as $repl) {
			$tmp = explode("[|]",$repl );
			$to = isset($tmp[1]) ? $tmp[1] : "";
			$nc = "";
			$nt = "";
			try{
				$nc = preg_replace("~".($tmp[0])."~uis", $to, $content);
				$nt = preg_replace("~".($tmp[0])."~uis", $to, $title);
			}catch (Exception $e){}
			if($nc != "" && $nt != ""){
				$content = $nc;
				$title = $nt;
			}
		}

		$content = $this->clear_tags_from_trash($content);

		if(!empty($this->selectors_list["exclude_replace"])) foreach ($this->selectors_list["exclude_replace"] as $repl) {
			$tmp = explode("[|]",$repl );
			$to = isset($tmp[1]) ? $tmp[1] : "";
			$nc = "";
			$nt = "";
			try{
				$nc = preg_replace("~".($tmp[0])."~uis", $to, $content);
				$nt = preg_replace("~".($tmp[0])."~uis", $to, $title);
			}catch (Exception $e){}
			if($nc != "" && $nt != ""){
				$content = $nc;
				$title = $nt;
			}
		}

		$link = "";
		if($this->add_backlink == 1){
			$src = explode("?", $url); // убираем get
			$src = $src[0];
			$link = "<br><a title='оригинал' href='".$src."' rel='noindex nofollow'>источник: ".parse_url($src,PHP_URL_HOST)."</a>";
		}

		$res = $this->set_title($title)."\r\n".$content.$link;

		if(!empty($this->selectors_list["exclude_replace"])) foreach ($this->selectors_list["exclude_replace"] as $repl) {
			$tmp = explode("[|]",$repl );
			$to = isset($tmp[1]) ? $tmp[1] : "";
			$nc = "";
			try{
				$nc = preg_replace("~".($tmp[0])."~uis", $to, $res);
			}catch (Exception $e){}
			if($nc != ""){
				$res = $nc;
			}
		}

		$this->use_random_image_names();
		$res = $this->process_images($res);
		$res = $this->change_image_meta($res,$title);
		if($this->selectors_list["lang-from"] != $this->selectors_list["lang-to"]){
			$tkey = get_option( "y_translate_key" );
			if(!$tkey && $tkey != "") die( json_encode(array("error", "Вы не указали ключ переводчика!")) );
			$res = $this->get_translation($res, $this->selectors_list["lang-from"], $this->selectors_list["lang-to"], $tkey);
		}

		return $res;
	}

	# получение ссылок из ПС по запросу
	public function get_links($query, $count = 1){
		$links = array();

		# получаем список поисковых систем на текущий момент + кешируем его на 1 час
		$clist = get_transient( "clist" );
		if($clist == false){

			$clist =  $this->load("http://stats.searx.oe5tpo.com/"); // список счетчиков

			if( $clist == false || $clist == "") return "";
			else set_transient( "clist", $clist, 1 * HOUR_IN_SECONDS  );

		}

		$html = str_get_html($clist);
		$table = $html->find("table"); // парсим ссылки
		if($table != null && !empty($table)){

			$nodes = $table[0]->find('tr > td > a');
			if($nodes != null && !empty($nodes) && count($nodes) > 3){

				$nodes = array_slice($nodes, 0, 3); // вырезаем первые 3 элемента
				shuffle($nodes); // и перемешиваем случайным образом, тогда $nodes[0] будет выбран случайно

				if($nodes[0] != null){
					foreach ($nodes as $key => $node) {
						$link = $node->href;

						// получаем результаты поисковика
						$data =  $this->load($link."?q=".urlencode($query)."&category_general=1&format=json&pageno=1");

						if( $data != null && $data !="" ){

							$jsonp = json_decode($data, true);
							if(isset($jsonp["results"])) foreach($jsonp["results"] as $j => $item){
								if(count($links) > $count || in_array($item["url"], $links)) continue;
								$links[] = $item["url"]; // суем ссылки в общий массив
							}
							
						}
					}
				}
			}
		}

		return array_slice($links, 0, $count);
	}

	# Парсинг в простом режиме(простом, разумеется, для пользователя)
	public function parse_easy($url){
		$res = ""; // сюда помещаем результаты

		if(!class_exists("Readability")){
			# подключаем Readability
			require_once( AFTPARSER__PLUGIN_DIR . 'readability/autoload.php');
			require_once( AFTPARSER__PLUGIN_DIR . 'class/htmLawed.php');
		}

		$html = $this->load($url);
		$this->current_url = $url;
		
		$blog_enc = str_replace(" ","", mb_strtoupper(get_bloginfo('charset')));
		$src_enc = str_replace(" ","", mb_strtoupper($this->auto_detect_encoding($html)));

		if(!stristr($blog_enc,$src_enc)) $html = mb_convert_encoding($html, $blog_enc, $src_enc);

		$configuration = new \andreskrey\Readability\Configuration(array(
			"stripUnlikelyCandidates"=>true,
			"cleanConditionally"=>true,
			"maxTopCandidates"=>5,
			"summonCthulhu"=>true,
			'fixRelativeURLs' => true,
			"originalURL" => $url
		));

		$content = "";
		$title = "untitled";
		//$html = $this->remove_a_href($html);
		try{
			$readability = new \andreskrey\Readability\Readability($configuration);
			$readability->parse($html);
			$content = $readability->getContent();
			$title = $readability->getTitle();
		}catch(Exception $e){
			_e("Что-то пошло по пизде: ".print_r($e,true));
			die();
		}

		$link = "";
		if($this->add_backlink == 1){
			$src = explode("?", $url); // убираем get
			$src = $src[0];
			$link = "<br><a title='оригинал' href='".$src."' rel='noindex nofollow'>источник: ".parse_url($src,PHP_URL_HOST)."</a>";
		}

		$content = $this->clear_tags_from_trash($content);
		$res = $this->set_title($title)."\r\n".$content.$link;

		$this->use_random_image_names();
		$res = $this->process_images($res);
		$res = $this->change_image_meta($res,$title);
		return $res;
	}

	# удаление всякой там адсенсе-хуенсе и прочего говна
	public function remove_script_tags($html){
		return preg_replace('~<script([\r\n\t\s\S]*?)>([\r\n\t\s\S]*?)</script>~is', '', $html);
	}

	# удаление комментариев из html
	public function remove_html_comments($html){
		return preg_replace('~<\!--[\r\n\t\s\S]*?-->~is', '', $html);
	}
	
	# Определяет кодировку страниц сайта, берет инфу из тега <meta>
	public function auto_detect_encoding($html){
		if(preg_match( '~(?:charset|encoding)(?:\s+|)=(?:\s+|)(?:[\'"]|)(.+?)[\'"]~si', $html, $matches )){
			return $matches[1];
		}else 
			return get_bloginfo('charset');
	}
	
	# Выполняет код макроса для переданного html
	private function process_macro($html){
		
		$blog_enc = str_replace(" ","", mb_strtoupper(get_bloginfo('charset')));
		$src_enc = str_replace(" ","", mb_strtoupper($this->auto_detect_encoding($html)));
		$res = "";
		ob_start();
		//Запускаем код макроса	

		@eval($this->macro);
		$this->errors = ob_get_contents();
		ob_end_clean();
		/*if (error_get_last()){
		    $this->errors = '<h4>Ошибка:</h4>';
		    $this->errors .= "<p>".print_r(error_get_last(),true)."</p>";
		}*/
		return $res;
	}

	# парсит ссылки для отложенного постинга
	function collect_links($udata, &$ll, &$num_links){

		$lout = array();
		if(is_numeric($udata) && intval($udata) > 0) // если передан ID
			$udata = get_option("aft_data_update".$udata);

		if(isset($udata["url"]) && !empty($ll)){
			$this->current_url = $ll[0];
			$html = $this->load($udata["url"]);
			
			if($html){
				$html = str_get_html($html);
				$exclude_selectors = trim($udata["remsel"]);
				$exclude_selectors = explode(",", $exclude_selectors);

				// удаляем звенья, которые надо удалить
				if(!empty($exclude_selectors)) foreach ($exclude_selectors as $sel) {
					$nodes = $html->find($sel);
			        if(!empty($nodes)) foreach ($nodes as $node){
			            $node->outertext = '';
			        }
			        $html = str_get_html($html->save());
				}
				$html = $html->save();

				//if (get_magic_quotes_gpc()){
				//	$udata["regex"] = stripslashes($udata["regex"]);
				//}

				//$html = preg_replace("/<div id=[\"']recent-comments-2[\s\S]+?<\/ul>[\n\t\r ]*<\/div>/is", "", $html);
				preg_match_all("/".$udata["regex"]."/is", $html, $matches);

				foreach ($matches[1] as $key => $url) {
					$matches[1][$key] = preg_replace('/#.*/', '', $url);
				}

				$matches[1] = array_unique($matches[1]);
				
				$ulc = 0;
				foreach ($matches[1] as $key => $mth) {
					if(trim($mth) == "") continue;
					
					$url = $this->_prepareUrl($mth);

					if(filter_var($url, FILTER_VALIDATE_URL) !== FALSE){
						$ulc ++;
						$ll[] = $url;
						$lout[] = $url;
					}
				}

				$num_links += $ulc;
			}
		}

		return $lout;
	}

	# Если надо использовать случайные имена файлов
	public function use_random_image_names(){
		$this->rnames = true;
	}

	# ебучие data-src
	public function normalize_image_src($res){
		$res = preg_replace("~src\s*=\s*([\"']data\:[\s\S]+?[\"'])~is", "", $res);
		$res = preg_replace("~data-src\s*=\s*([\"'][\s\S]+?[\"'])~is", "src=\$1", $res);
		return $res;
	}

	# убирает картинки
	public function remove_images($fr_html){
		$fr_html = preg_replace("~(?:<a[^>]+?href\s*=\s*[\"']([^><]+?)[\"'][^>]*?>|)<img(?:[^><]*?|)src\s*=\s*[\"']([^><]+?)[\"'](?:[^><]*?)>(?:<\/img>|)(?:<\/a>|)~uis", "", $fr_html);
		return $fr_html;
	}

	# Выполняет поиск и загрузку всех картинок в переданном html
	public function process_images($fr_html,$attr="src",$Y_BOTTOM="none",$min_w=5,$min_h=5){
		//error_reporting(~0);
		//ini_set('display_errors', 1);

		$fr_html = $this->normalize_image_src($fr_html);

		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$fr_html = preg_replace("~srcset(?:[^\S]*?)=(?:[^\S]*?|)[\"'][\r\n\t\s\S]*?[\"']~uis", "", $fr_html);

		preg_match_all("~<img(.*?)>~uis", $fr_html, $matches);
		// убираем теги img в которых нет src
		foreach ($matches[1] as $key=>$data) {
			if(mb_strpos($data, "src") === false){
				$fr_html = str_replace($matches[0][$key], "", $fr_html);
			}
		}

		preg_match_all("~(?:<a[^>]+?href\s*=\s*[\"']([^><]+?)[\"'][^>]*?>[\r\n\s]*|)<img(?:[^><]*?|)".$attr."\s*=\s*[\"']([^><]+?)[\"'](?:[^><]*?)>(?:<\/img>|)(?:[\r\n\s]*<\/a>|)~uis", $fr_html, $matches);
		$ri = 0;
		foreach ($matches[2] as $key=>$url) {
			$this->index ++;
			$ourl = $url;
			$url = html_entity_decode( trim($url) );
			$url = str_replace("&#9;", "", $url);
			$url = str_replace("&#10;", "", $url);
			if($url == "" || $ri >= $this->maxipp){
				$fr_html = str_replace($matches[0][$key] , '' , $fr_html );
				continue;
			}
			//$url = str_replace("https", "http", $url);

			$furl = $this->_prepareUrl($url,true);

			if(!file_exists($furl)){
				if(strpos($url, "?") !== false){
					$url = explode("?", $url);
					$url = $url[0];
				}

				$pos = strrpos($url, '/') + 1;
				$url = substr($url, 0, $pos) . urlencode(substr($url, $pos));

				$furl = $this->_prepareUrl($url,true);
			}

			$url_link = trim($matches[1][$key]);
			
			$furl_form_link = $this->_prepareUrl($url_link);

			if(!file_exists($furl_form_link)){
				if(strpos($furl_form_link, "?") !== false){
					$furl_form_link = explode("?", $furl_form_link);
					$furl_form_link = $furl_form_link[0];
				}

				$pos = strrpos($furl_form_link, '/') + 1;
				$furl_form_link = substr($furl_form_link, 0, $pos) . urlencode(substr($furl_form_link, $pos));

				$furl_form_link = $this->_prepareUrl($furl_form_link,true);
			}

			$path_parts = pathinfo($furl);
			$link_path_parts = @pathinfo($furl_form_link);

			// убираем из имени файла все GET параметры
			$path_parts['extension'] = (isset($path_parts['extension'])) ? preg_replace("~\?.*~is", "", $path_parts['extension']) : "";
			$path_parts['basename'] = (isset($path_parts['basename'])) ? preg_replace("~\?.*~is", "", $path_parts['basename']) : "";
			$link_path_parts['extension'] = (isset($link_path_parts['extension'])) ? preg_replace("~\?.*~is", "", $link_path_parts['extension']) : "";
			$link_path_parts['basename'] = (isset($link_path_parts['basename'])) ? preg_replace("~\?.*~is", "", $link_path_parts['basename']) : "";
			
			/** 
			 * Пропускаем мусорные пути, в ссылках типа: <img src="//bs.yandex.ru/informer/26888820/2_0_FFFFFFFF_EFEFEFFF_0_pageviews"/>
			 * Это путь к картинке яндекс метрики.
			 * Ее скачать не получится.
			 */
			if ( empty($path_parts['extension']) ||
				!in_array($path_parts['extension'], array("JPG","jpg","png","PNG","jpeg","JPEG")) ||
				(!empty($path_parts['extension']) && preg_match('/[^a-z]/i', $path_parts['extension']) != 0)){
				$fr_html = str_replace($matches[0][$key], "", $fr_html);
				continue;
			}

			$filename = urldecode($path_parts['basename']); //старое имя файла
			$filename_link = $link_path_parts['basename']; //старое имя файла
			if($this->rnames ){
				$alt = "img".h_rand_str(5);
				preg_match("#\[title\]([\s\S]+?)\[\/title\]#i", $fr_html, $m_title);// определяем название
				if(isset($m_title[1]) && $m_title[1] != ""){
					$alt = ru_str2url($m_title[1]);
				}

				if($this->inames){
					$alt = ru_str2url($this->inames);
				}

				$filename = ( $this->rnames ) ? $alt."-".$this->index.".".$path_parts['extension'] : $filename; //с этим именем файл сохраняется у нас
			}

			$media_folder = wp_upload_dir();

			if(file_exists($media_folder["path"]."/".$filename)){
				unlink($media_folder["path"]."/".$filename);
				/*$fn = explode("-", $path_parts['filename']);
				$flist = glob($media_folder["path"]."/".$fn[0]."-*");
				foreach ($flist as $key => $ff) {
					unlink($ff);
				}*/
			}

			/*if(file_exists($media_folder["path"]."/".$filename)){
				//$flist = glob($media_folder["path"]."/".$path_parts["filename"]."*");
				//$filename = $path_parts["filename"]."-".count($flist).".".$path_parts['extension'];
			}*/

			$uploaded_file = $media_folder['url'].'/'.$filename;
			$uploaded_file_link = $media_folder['url'].'/'.$filename_link;

			$is_link_uploaded = false;
			$is_uploaded_file = false;

			// скачиваем то, что по ссылке
			if($url_link != "" && $link_path_parts["extension"] == $path_parts['extension'] && $url_link != $url){
				$is_link_uploaded = $this->download($furl_form_link, $media_folder["path"]."/".$link_path_parts["basename"]);
			}

			list($w_i, $h_i, $type) = getimagesize($furl);

			if(!file_exists($media_folder["path"]."/".$filename) && get_remote_size($furl) > $this->minfsize && $w_i > $this->minfwidth){ // файл не скачан - скачиваем
				$is_uploaded_file = $this->download($furl,$media_folder["path"]."/".$filename);
				if ( $is_uploaded_file === false ){
					$fr_html = str_replace($matches[0][$key], "", $fr_html);
					continue;
				}

				if($is_uploaded_file !== false && $this->add_media){
					$fn = $filename;
					if($is_link_uploaded != false) $fn = $filename_link;

					$anames = explode(".", $fn);
					$attachment_id = aftp_get_image_id($anames[0]);

					if(!$attachment_id){
						$wp_filetype = @wp_check_filetype($media_folder["path"]."/".$fn);

						$attachment = array(
							'post_mime_type' => $wp_filetype['type'],
							'guid' => $fn, 
							'post_parent' => 0,
							'post_title' => preg_replace('/\.[^.]+$/', '', $fn),
							'post_content' => '',
							'post_status' => 'inherit'
						);

						$attachment_id = @wp_insert_attachment( $attachment, $media_folder["path"]."/".$fn, 0 );
					}
					if (!is_wp_error($attachment_id)) {
						$attachment_data = @wp_generate_attachment_metadata( $attachment_id, $media_folder["path"]."/".$fn );
						@wp_update_attachment_metadata( $attachment_id,  $attachment_data );
					}

					if($Y_BOTTOM != "none"){
						$Y_BOTTOM = -1 * intval(trim($Y_BOTTOM,"-"));
						
						icrop($media_folder['path'].'/'.$filename,$media_folder['path'].'/'.$filename, array(0, 0, -1,$Y_BOTTOM));
					}
					if($min_w !=5 || $min_h != 5){
						//list($w_i, $h_i, $type) = getimagesize($media_folder['path'].'/'.$filename);
		        		if ($w_i && $w_i < $min_w) 
		        			$uploaded_file = get_random_pic();

		        		if ($h_i && $h_i < $min_h) 
		        			$uploaded_file = get_random_pic();
					}
				}
			}

			if($attr == "src" && !$this->clear_images){

				$fr_html = $is_uploaded_file === false ? str_replace($matches[0][$key] , '' , $fr_html ) : str_replace($ourl , $uploaded_file , $fr_html );
				$fr_html = $is_link_uploaded === false || $url_link == "" ? preg_replace("~<a[^><]*?>(?:[^>]*?|)(<img[\s\S]*?>)(?:[^\S]*?|)<\/a>~is", "\$1", $fr_html) : str_replace($url_link , $uploaded_file_link , $fr_html );
			
			}else{
				if($is_uploaded_file === false){
					$fr_html = str_replace($matches[0][$key] , '' , $fr_html );
				}else{

					if($is_link_uploaded === false || $url_link == ""){
						$fr_html =  str_replace($matches[0][$key] , "<img src='".$uploaded_file."'>" , $fr_html );	
					} else {
						$fr_html =  str_replace($matches[0][$key] , "<a href='".$uploaded_file_link."'><img src='".$uploaded_file."'></a>" , $fr_html );	
					}
				}

			}
			$ri+=1;
			/*$fr_html = $is_link_uploaded == false || $url_link == "" ? preg_replace("~<a[^>]*?>(?:[^>]*?|)(<img[\s\S]*?>)(?:[^\S]*?|)<\/a>~is", "\$1", $fr_html) : str_replace($url_link , $uploaded_file_link , $fr_html );
			$fr_html = $is_link_uploaded == false || $url_link == "" ? preg_replace("~<a[^>]*?>(?:[^>]*?|)(<img[\s\S]*?>)(?:[^\S]*?|)<\/a>~is", "\$1", $fr_html) : $fr_html;
			*/
			
		}
		$fr_html = preg_replace("~srcset(?:[^\S]*?)=(?:[^\S]*?|)[\"'][\r\n\t\s\S]*?[\"']~uis", "", $fr_html);
		$fr_html = preg_replace("~data-src-retina(?:[^\S]*?)=(?:[^\S]*?|)[\"'][\r\n\t\s\S]*?[\"']~uis", "", $fr_html);
		$fr_html = preg_replace("~data-src(?:[^\S]*?)=(?:[^\S]*?|)[\"'][\r\n\t\s\S]*?[\"']~uis", "", $fr_html);
		return $fr_html;
	}

	# rss content match
	public function match_rss($item, &$title, &$link, &$cat){
		$content = "";
		preg_match("#<title(?:[\s\S]*?|)>([\s\S]+?)<\/title>#i", $item, $m_title); // Название
		if($m_title[1]) $title = trim($m_title[1]);
		
		preg_match("#<link(?:[\s\S]*?|)>([\s\S]+?)<\/link>#i", $item, $mlink); // Ссылка
		if($mlink[1]) $link = trim($mlink[1]);
		
		preg_match("#<category(?:[\s\S]*?|)>([\s\S]+?)<\/category>#i", $item, $mcat); // Ссылка
		if($mcat[1]) $cat = trim($mcat[1]);
		
		$ce = preg_match("#<content:encoded>(.+?)<\/content:encoded>#uis", $item, $mce_content); 

		if(!empty($mce_content)) $content = trim($mce_content[1]);

		$dc = preg_match("#<description(?:[\s\S]*?|)>([\s\S]+?)<\/description>#i", $item, $m_content); // Содержимое
		if(!empty($m_content)) $content .= trim($m_content[1]);
		
		$ce = preg_match("#<fulltext(?:[\s\S]*?|)>([\s\S]+?)<\/fulltext>#i", $item, $mg_content); 
		if(!empty($mg_content)) $content = trim($mg_content[1]);
		
		$cy = preg_match("#<yandex:full-text(?:[\s\S]*?|)>([\s\S]+?)<\/yandex:full-text>#i", $item, $my_content); 
		if(!empty($my_content)) $content = trim($my_content[1]);

		return $content;
	}

	# Подготавлиает ссылку
	public function _prepareUrl($furl,$strip_get = false){
		if($furl == "") return "";
		
		$scheme = parse_url($this->current_url, PHP_URL_SCHEME);

		$host = parse_url($this->current_url, PHP_URL_HOST);
		if(substr( $furl, 0, 2 ) == "//"){
			$furl = $scheme.":".$furl;
		}

		if($furl[0] == "/"){
			$furl = $scheme."://".$host.$furl;
		} else
		if($furl[0] != "/" && strpos($furl, "http") === false && strpos($furl, "www") === false){
			$furl = $scheme."://".$host."/".$furl;
		}


		if(strpos($furl, "http") === false) $furl = $scheme."://".$furl;
		if(strpos($furl, "?") !== false && $strip_get != false){
			$furl = explode("?", $furl);
			$furl = $furl[0];
		}

		$host_img = parse_url($furl, PHP_URL_HOST);
		$host_base = parse_url($this->current_url, PHP_URL_HOST);
		if($host_img != "" && $host_img != $host_base){
			return $furl;
		}

		return $furl;
	}
	
	# Скачивает файл с указанного адреса
	public function upload_file($url){
		$path_parts = pathinfo(trim($url));
		
		$media_folder = wp_upload_dir();
		$n_name = "file".$this->mt_rand_str(9)."t".$path_parts['basename']; // с этим именем файл сохраняется у нас
		
		if($this->download($url, $media_folder["path"]."/".$n_name)){
			return $media_folder['url'].'/'.$n_name; // возвращаем url файла
		}else return false;
		
	}
	
	# Установка названия для материала
	public function set_title($title){
		if($title)
			return "\n[title]".$title."[/title]\n";
		else return "";
	}

	# мин-ширина картинки-описания
	public function set_desc_mw($mw){
		if($mw)
			return "\n[mw]".$mw."[/mw]\n";
		else return "";
	}

	# мин-высота картинки-описания
	public function set_desc_mh($mh){
		if($mw)
			return "\n[mh]".$mh."[/mh]\n";
		else return "";
	}

	# Установка произвольного мета-поля
	public function set_meta($name,$value){
		
		return "\n[meta=\"".$name."\"]".$value."[/title]\n";
	}

	# Получаем перевод
	function get_translation($text, $fromlg="auto", $tolg="ru", $key = "trnsl.1.1.20130728T024200Z.5e3fdb8569741490.0ce056938cd0b8e0138941f04553c6bed2b7f85a", $iters = 0){
		set_time_limit(0);
		$res = "";

		$trans = ($fromlg != "auto") ? $fromlg."-".$tolg : $tolg;

		$post_data = array(
				"key"=>$key,
				"text"=>$text,
				"lang"=>$trans,
				"format"=>"html"
			);
			
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, "https://translate.yandex.net/api/v1.5/tr.json/translate");
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POST, 1);  //0 неважно, выставляется автоматически
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_CONNECTTIMEOUT ,120); 
		curl_setopt($c, CURLOPT_TIMEOUT, 150);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post_data));
		$b = curl_exec($c);
		curl_close($c);

		$json = json_decode($b, true);
		if($json["code"] == "200"){
			$res = $json["text"][0];
		}else if($json["code"] == 404){
			// если ключиков несколько повторяем с другим ключем (рекурсия)
			if($iters == 0){
				$this->get_translation($text, $fromlg, $tolg, $key, 1);
			}else
			$res = ("Лимит текстовых запросов переводчика на сегодня - исчерпан!");
		}
		
		return trim($res);
	}
	
	# Установка ссылки для материала
	public function set_alias($alias){
		if($alias)
			return "\n[alias]".htmlentities($alias)."[/alias]\n";
		else return "";
	}

	# задает айди для случайных авторов записи
	public function set_r_aids($ids){
		if($ids)
			return "\n[post_author]".($ids)."[/post_author]\n";
		else return "";
	}
	
	# Добавление комментария к материалу
	public function add_comment($author = '',$email = '',$author_url = '',$text = ''){
		$rez  = "[comment]";
		$rez .= "[author]"	.$author.	 "[/author]";
		$rez .= "[email]"	.$email.	 "[/email]";
		$rez .= "[url]"		.$author_url."[/url]";
		$rez .= "[text]"	.$text.		 "[/text]";
		$rez .= "[/comment]";
		return $rez;
	}
	
	# Установка имени категории
	public function set_catname($catname){
		return "\n[cat]".$catname."[/cat]\n";
	}
	
	# Получаем границу по ее имени
	public function get_border($b_title, $html, $fix_pos = false){
		$rez = "";
		// Ищем порядковый номер границы в списке границ и заносим его в $index
		$index = -1;
		foreach($this->borders_list as $key=>$border){
			if($border['title'] == $b_title) {
				$index = $key;
				break;
			}
		}
		if($index == -1) return false;
		
		$top = $this->prepare_html($this->borders_list[$index]['top_border']);
		$bottom = $this->prepare_html($this->borders_list[$index]['bottom_border']);
		
		$html_t = preg_replace("/(<\/?\w+)(.*?>)/e", "strtolower('\\1') . '\\2'", $html);
		$html .= str_replace("'", "\"", $html_t);
		$f_index = strpos($html,$top,$this->border_pos);

		if($f_index === false) return false;
		$f_index = $f_index + mb_strlen($top);
		$b_index = strpos($html,$bottom,$f_index);
		if($b_index === false || $b_index <= $f_index) return false;
		$length = $b_index - $f_index;
		if($fix_pos)
			$this->border_pos = $f_index + $length;
		$rez = substr($html, $f_index, $length);
		if(trim($rez) == "") return false;
		return $rez;
	}
	
	# Возвращает массив фрагментов текста, если они повторяются несколько раз
	public function get_recurrence_border($b_title, $html){
		$rez = array();
		$data = "";
		while(($data = $this->get_border($b_title, $html, true)) != false){ // по уму надо было юзать do - while. ну да похуй...
			if($data != "")
				$rez[] = $data;
		};
		$this->border_pos = 0;
		return $rez;
	}
	
	/**
	 * Встроенный парсер ссылок
	 */
	
	# Парсит ссылки с указанной страницы
	
	public function parse_links($url){
		$this->current_url = $url;
		$html = $this->load($url, $this->cookie);
		if(!$html) return false;
		return $this->get_internal_links($html);
	}

	# меняет alt и title
	public function change_image_meta($res,$new_meta = ""){
		$new_meta = str_replace("\"", "", $new_meta);
		$new_meta = str_replace("'", "", $new_meta);
		if($new_meta == ""){
			preg_match("#\[title\]([\s\S]+?)\[\/title\]#i", $res, $m_title);// определяем название
			if(isset($m_title[1]) && $m_title[1] != ""){
				$new_meta = trim($m_title[1]);
			} else {
				return "";
			}
		}

		preg_match_all("~(?:title|alt)\s*=\s*((['\"]).*?['\"])~is", $res, $matches);
		
		if(!empty($matches[1])){

			foreach ($matches[1] as $key => $desc) {
				$gl = $matches[2][$key];
				$base = $matches[0][$key];
				$base = str_replace($desc, $gl.$new_meta.$gl, $base);
				$res = str_replace($matches[0][$key], $base, $res);
			}
		}
		return $res;
	}
	
	# Парсер карты сайта
	public function get_sitemap_links($url){
		$rez = "";
		$this->current_url = $url;
		$html = $this->load($url, $this->cookie);
		$matches = array();
		// Карты сайта бывают разные... синие белые красные....
		// Если карта сайта выполнена по стандартам, то собираем то-что между тегами <loc>
		preg_match_all("/<loc.*?>(.+?)<\/loc>/si",$html,$matches);
		if(empty($matches) || !isset($matches[1]) || count($matches[1]) == 0){
			preg_match_all("/<url.*?>(.+?)<\/url>/si",$html,$matches);
		}

		if(count($matches[1]) > 0){
			foreach($matches[1] as $key => $map_url){
				if(!empty($map_url) && strlen($map_url) > 1)
				$rez .= $map_url."\n";
			}
		}
		
		// Если карта сайта представляет из себя обычный html файл, к  примеру как тут: http://seo-keys.ru/sitemap.xml
		// То грабим ссылки через get_internal_links
		$rez .= $this->get_internal_links($html);
		
		return $rez;
	}
	
	# Возвращает все внутренние ссылки на странице
	# вы знаете, я никогда бы не подумал, что знание жанров порно поможет мне выбирать имена для функций...
	public function get_internal_links($html){
		$result = "";
		preg_match_all("/<a(?:.*?)href(?:\s*)=(?:\s*)['\"](.*?)['\"]/si",$html,$matches);
		// Добавляем в массив links все ссылки не имеющие аттрибут nofollow
		foreach($matches[1] as $key => $link){
			$url = $this->_prepareUrl($link);
			$url_host = parse_url ($url,PHP_URL_HOST);
			if(in_array(pathinfo ($url,PATHINFO_EXTENSION),array("jpg","png","js","css","jpeg","xml","txt","inc","psd","djvu","doc","docx","xls","xlsx","gif","tiff","swf","mp3","mp4","mpeg"))) continue;	//Пропускаем файлы
			$a_host = parse_url ($this->current_url,PHP_URL_HOST);
			if($url_host == $a_host) {
				$url = preg_replace('/#{2,}/','#',$url); //Иногда знак хеша повторяется подряд слишком часто. Это не есть гуд.
				$url = preg_replace('/\?{2,}/','?',$url); //Повторяющийся знак вопроса убираем тоже. Да, я в курсе что можно сделать 1 реулярку.
				$url = preg_replace('/&{2,}/','&',$url);				
				if(!empty($url) && strlen($url) > 1)
					$result .= $url."\n";
			}
		}
		return $result;
	}
	
	/**
	 * Функции загрузки и подготовки контента
	 */
	
	# Предобработка html
	public function prepare_html($str){
		return html_entity_decode(stripslashes_deep($str));
	}
	
	# Генерация случайной строки для имени картинки
	# $l- длинна строки, к примеру 9 символов. $c - из каких символов бдет эта строка состоять
	public function mt_rand_str ($l, $c = 'abcdefghijklmnopqrstuvwxyz1234567890') {
		for ($s = '', $cl = strlen($c)-1, $i = 0; $i < $l; $s .= $c[mt_rand(0, $cl)], ++$i);
		return $s;
	}
	
	# Делает из относительного урла абсолютный.
	private function rel_to_abs($rel, $base){

        if (parse_url($rel, PHP_URL_HOST) != '') return $rel; // Если урл уже является абсолютным на данный момент - возвращаем без изменений 
        if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel;	//Если эта хуйня является хешем или get запросом
        
        extract(parse_url($base));	//Эта хуйня извлекает данные из массива в локальные переменные: $scheme, $host, $path 
		
        if ($rel[0] == '/') $path = '';		// если путь - путь до главной страницы 
        $abs = rtrim($host,"/").$path."/".$rel;		// формируем урл
        

		if(startsWith($rel,"//")) return "http://".$rel;
        return $scheme.'://'.$abs;
    }

    # Очистка тегов от мусора
    public function clear_tags_from_trash($res){
    	$res = preg_replace("~<(?!img|code|a|p|br|\/|iframe|script|style|!--|ul|li|table|tr|td|ol|ld|dl|object|Object|audio|video)([A-Za-z]{1,10})\s(?:[^>]+?)>~isu", "<\$1>", $res); // я познал дзен регулярок =)
    	
    	// эта регулярка убирает пустые теги
    	$pattern = '/<(?!iframe|img|br)([a-zA-Z]+)([\s\r\n\t]*[^>]+?|)(>[\s\r\n\t]*<\/\1[\s\r\n\t]*>|\/>)/i';

		$res = preg_replace($pattern, '', $res);
		
    	$res = preg_replace("~([\n\r]){4,}?~", "\r\n", $res);
    	//$res = preg_replace("~<\!--(?!more).+?-->~is", "", $res);
    	$res = preg_replace("~(<textarea.+?>.+?<\/textarea>)~is", "", $res);
    	$res = preg_replace("~(<br\s*\/*>)~is", "\r\n", $res);
    	$res = trim($res);
    	$res = preg_replace("~\r\n~", "\r\n<br>", $res);
    	$res = $this->remove_a_href($res);

    	//require_once( AFTPARSER__PLUGIN_DIR . 'class/htmLawed.php');
		//$res = htmLawed($res,array("tidy"=>1));
    	return $res;
    }
	
	# Убираем ссылки
    public function remove_a_href($res){
		$res = preg_replace("~<a[^>]*>~isu", "", $res);
    	return preg_replace("~<\/a>~isu", "", $res);
    }

    # Нормализует пути у картинок
    public function fix_image_url($res){
    	if(preg_match_all("~src\s*=\s*[\"']([\S\s]+?)[\"']~i", $res, $matches)){
    		foreach ($matches[1] as $key => $mth) {
			
    			$url = $mth;
	    		$src = $matches[0][$key];
	    		$url = $this->_prepareUrl(trim($url));
	    		
	    		$src = str_replace($mth, $url, $src);

	    		$res = str_replace($matches[0][$key], $src, $res);
    		}
    	}
    	return $res;
    }

	# Скачка чего угодно
	public function download($file_url, $s_fname) {
		//$data = file_get_contents($file_url);
		$data = $this->load($file_url);
		if($data === false) return false;
		$res = file_put_contents(trim($s_fname), $data);
		//var_dump($file_url);
		//var_dump($res);
		if($res !== false && file_exists($s_fname)){
			return $res;
		}else{ return false; }
	}
	
	# Вырезает из $src участок начинающийся с $top и заканчивающийся $bottom
	public function cut_str($src, $top, $bottom){
		$f_index = strpos($src, $top);
		if($f_index === false) return false;
		$f_index = $f_index + mb_strlen($top);
		$b_index = strpos($src, $bottom, $f_index);
		if($b_index === false || $b_index <= $f_index) return false;
		$length = $b_index - $f_index;
		$rez = substr($src, $f_index, $length);
		return $rez;
	}

	# Проставляет отступы для первого предложения контента
	public function add_indent(){
		return '<style>p{text-indent: 1.5em;}</style>';
	}
	
	# Маскировка под браузер через утсновку юзер агента
	private function get_user_agent(){
		$a_list = array(
			"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.3) Gecko/2008092417 Firefox/3.0.3",
			"Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.105 CoRom/30.0.1599.105 Safari/537.36",
			"Mozilla/5.0 (X11; Linux i686 on x86_64; rv:12.0) Gecko/20100101 Firefox/12.0",
			"Mozilla/5.0 (Windows; U; Windows NT 5.1; cs; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8",
			"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:33.0) Gecko/20100101 Firefox/33.0",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; Media Center PC 6.0; InfoPath.3; MS-RTC LM 8; Zune 4.7)",
			"Mozilla/4.0 (Windows; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)",
			"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.6) Gecko/20071115 Firefox/2.0.0.6 LBrowser/2.0.0.6",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.154 Safari/537.36 OPR/20.0.1387.91",
			"Opera/9.80 (Linux armv6l ; U; CE-HTML/1.0 NETTV/3.0.1;; en) Presto/2.6.33 Version/10.60",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/536.5 (KHTML, like Gecko) YaBrowser/1.0.1084.5402 Chrome/19.0.1084.5402 Safari/536.5",
			"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.12785 YaBrowser/13.12.1599.12785 Safari/537.36",
			"Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/534.4 (KHTML, like Gecko) Chrome/6.0.481.0 Safari/534.4",
			"Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/22.0.1207.1 Safari/537.1",
			"Mozilla/5.0 (X11; U; Linux x86_64; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Ubuntu/10.10 Chromium/8.0.552.237 Chrome/8.0.552.237 Safari/534.10",
			"Mozilla/5.0 (X11; Linux i686) AppleWebKit/535.1 (KHTML, like Gecko) Ubuntu/10.04 Chromium/14.0.813.0 Chrome/14.0.813.0 Safari/535.1",
		);
		
		$key = array_rand($a_list);
		
		return $a_list[$key];
	}
	
	# Маскируемся под пользователя, подменяя реферер
	private function get_referer(){
		$a_list = array(
			"http://www.google.com/",
			"http://pastebin.com",
			"http://www.yandex.ru/",
			"http://www.yahoo.com/",
			"http://www.youtube.ru/",
			"http://www.carderlife.ms/",
			"http://www.hacker-pro.net/",
			"http://www.host-tracker.com/",
			"http://www.forum.antichat.ru/",
			"http://www.lenta.ru/",
			"http://www.wikpedia.org/",
			"http://www.mail.ru/",
			"http://www.vkontakte.ru/",
			"http://www.upyachka.ru/",
			"http://www.2ip.ru/",
			"http://www.webmoney.ru/",
			"http://www.live.com/",
			"http://www.libertyreserve.com/",
			"http://www.ebay.com/",
			"http://www.microsoft.com/",
			"http://www.ninemsn.com/",
			"http://oce.leagueoflegends.com/",
			"http://aftamat4ik.ru/",
			"http://vk.com/",
			"http://facebook.com/",
			"http://twitter.com/",
			"https://www.dropbox.com/"
		);
		
		$key = array_rand($a_list);
		
		return $a_list[$key];
	}
	
	# Загрузка страницы по адресу $url, с куками(пока не используем этот функционал, ибо я про него вообще забыл) и с отправкой $post данных(это мы тоже не используем пока)
	public function load($url, $cookie = "", $post = ""){
		$url = (strpos($url, "feeds.feedburner.com") !== false) ? $url."?format=xml" : $url;
		$rez = "";
		$cookie = empty($this->cookie) ? $this->cookie : $cookie;

		$proxy_list = false;
		
		$proxy_list = get_option( "aft_proxy_list" );
		$proxy = false;
		if($proxy_list && $proxy_list != ""){
			$proxy_list = str_replace("\r", "",$proxy_list);
			$proxy_list = explode("\n", $proxy_list);
			shuffle($proxy_list);
			$proxy = $proxy_list[0];
		}

		if(function_exists('curl_version')){	// Сначала пытаемся получить страницу через Curl, если он установлен, конечно-же
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);   // переходит по редиректам
			curl_setopt($ch, CURLOPT_USERAGENT, $this->get_user_agent());
			curl_setopt($ch, CURLOPT_REFERER, $this->get_referer());
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			if(!empty($cookie)){
				curl_setopt($ch, CURLOPT_COOKIE, $cookie);
			}
			if($proxy != false && $proxy != ""){
				if(strpos($proxy,"|") !== false){
					$proxy = explode("|",$proxy);
					curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
					curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy[1]);				
				}
			}
			if(!empty($post)){
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			$rez = curl_exec($ch);

			$err     = curl_errno( $ch );
			$errmsg  = curl_error( $ch );
			if($errmsg || $err != 0){
				return false;
			}
			$header  = curl_getinfo( $ch );
			if($header["http_code"] == 403 || $header["http_code"] == 404){

				return false;
			}

			//$redirectURL = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL );
			//var_dump($header["http_code"]);
			curl_close($ch);
		}
		if(empty($rez) && function_exists('file_get_contents')){	// Нет курла - не проблема.
			$method = "GET";
			$data = null;
			if(!empty($post)){
				$method = "POST";
				$data = array('content' => $post);
			}
			$opts = array(
				'http'=>array(
				'method'=>$method,
				'header'=>"Accept-language: en\r\n" .
						"Cookie: ".$cookie."\r\n",
						"User-Agent: ".$this->get_user_agent()."\r\n",
						"Referer: ".$this->get_referer()."\r\n",
						$data
				),
			);
			$context = stream_context_create($opts);
			$rez = file_get_contents($url, false, $context);
		}
		$is_gzip = 0 === mb_strpos($rez, "\x1f" . "\x8b" . "\x08", 0, "ASCII");
		if($is_gzip) $rez = gzdecode($rez);
		
		if($rez){
			$rez = $this->fix_image_url($rez);
			return $rez;
		
		}else return false;
	}
	
}
// end of file //