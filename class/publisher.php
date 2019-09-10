<?php
//
/**
 * 
 * Публикатор.
 * 
 */
 
class AFPublisher{
	private $cat_id 	= array();
	private $publ_mode 	= "pending";
	private $mw 	= 0;
	private $mh 	= 0;
	private $auto_thumb = true;
	private $raids = array();
	public $rss = false;
	
	# Конструктор. $data - данные, получаемые на выходе из парсера, $cat - идентификатор категории
	function __construct($mode, $cat = "random"){
		if($cat == "random"){
			// выбираем случайную категорию
			$categories = get_terms( 'category', array(
					    'orderby'    => 'count',
					    'hide_empty' => 0,
					 ) );

			$this->cat_id[] = intval( $categories[array_rand($categories)]->term_id );
		} else 
			$this->cat_id[] = intval( $cat );
			
		$this->publ_mode = $mode;
	}
	
	# Извлекает из $data название поста, название категории и комментарии
	public function publicate($data,$op_url = "",$auto_thumb = true){
		$this->auto_thumb = $auto_thumb;
		if(empty($data)) return false;

		$a_id = $this->process_article($data,$op_url);
		if($a_id != false && $a_id != 0){
			$this->process_comments($data, $a_id);
			return $a_id;
		}else
			return false;
		return false;
	}
	
	
	function process_article($data, $op_url = ""){
		require_once(ABSPATH. 'wp-config.php'); 
		require_once(ABSPATH. 'wp-includes/wp-db.php'); 
		require_once(ABSPATH. 'wp-admin/includes/taxonomy.php');

		$title = "Untitled";
		$text = "Not Set Already";
		$alias = "";
		
		$t = preg_match("#\[title\]([\s\S]+?)\[\/title\]#i", $data, $m_title);// определяем название
		if($t == 1 && isset($m_title[1])) $title = trim($m_title[1]);
		
		$a = preg_match("#\[alias\]([\s\S]+?)\[\/alias\]#i", $data, $m_alias);// определяем ссылку, по которой будет находиться материал
		if($a == 1 && isset($m_alias[1])) $alias = trim($m_alias[1]);

		$r = preg_match("#\[post_author\]([\s\S]+?)\[\/post_author\]#i", $data, $m_raids );
		if($r == 1 && isset($m_raids[1])) $this->raids = explode(",",$m_raids[1]);

		if(preg_match("#\[mw\]([\s\S]+?)\[\/mw\]#i", $data, $m_w)) $this->mw = intval(trim($m_w[1]));
		if(preg_match("#\[mh\]([\s\S]+?)\[\/mh\]#i", $data, $m_h)) $this->mh = intval(trim($m_h[1]));
		
		$c = preg_match_all("#\[cat\]([\s\S]+?)\[\/cat\]#i", $data, $m_cat);		// определяем категорию
		
		if(isset($m_cat[1]) && !empty($m_cat[1])){
			$this->cat_id = array();
			foreach ($m_cat[1] as $key => $sub) {

				$cats = explode(",", $sub);
				$cats = $cats == NULL ?  $sub : $cats;
				foreach ($cats as $k => $cat) {
					$cat = trim($cat);

					$term = get_term_by('name', $cat, 'category');
					$id = 0;
					if(!$term){
						$id = wp_create_category($cat);	
					} else $id = $term->term_id;
					$this->cat_id[] = intval( $id );
				}
			}
		}

		$txt = preg_replace('#\[(\w+)\]([\s\S]+?)\[\/\1\]#','',$data);  // убираем всякие [info][title][comment] и прочие пометки
		
		if($txt === false) return false;								// текст не определен
		else $text = $txt;
		return intval($this->add_node($title, $alias, $text, $op_url));
	}

	# Проверяет, существует ли этот пост в базе, если существует - возвращет его ид.
	function post_exists_by_content($content){
		global $wpdb;
 
		$post_content = wp_unslash( sanitize_post_field( 'post_content', $content, 0, 'db' ) );

		$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";

		if ( !empty ( $content ) ) {
		    $query .= ' AND post_content = %s';
		    $args[] = $post_content;
		}

		if ( !empty ( $args ) )
		    return (int) $wpdb->get_var( $wpdb->prepare($query, $args) );
		 return 0;
	}

	# мать твою еще одна проверка на дубли... потом соберу в одно когда-нибудь
	function post_exists( $post_name, $post_type='post' ) {
		global $wpdb;
		$post_name = trim($post_name);
		$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
		$args = array();

		if ( !empty ( $post_name ) ) {
		     $query .= " AND post_name LIKE '%s' ";
		     $args[] = $post_name;
		}
		if ( !empty ( $post_type ) ) {
		     $query .= " AND post_type = '%s' ";
		     $args[] = $post_type;
		}

		if ( !empty ( $args ) )
		     return $wpdb->get_var( $wpdb->prepare($query, $args) );

		return 0;
	}
	
	# Обработка комментериев
	function process_comments($data, $node_id){
		preg_match_all("#\[comment\]([\s\S]+?)\[/comment\]#i", $data, $m_comment);
		if($m_comment)
			foreach($m_comment[1] as $ct){
				$author = 'anonimous';
				$email 	= 'not@mail.ru';
				$url 	= 'http://';
				$text 	= 'Текст комментария';
				
				$tmp = $this->match_tag($ct,'author');
				if($tmp) $author = $tmp;
				
				$tmp = $this->match_tag($ct,'email');
				if($tmp) $email = $tmp;
				
				$tmp = $this->match_tag($ct,'url');
				if($tmp) $url = $tmp;
				
				$tmp = $this->match_tag($ct,'text');
				if($tmp) $text = $tmp;
				
				// публикация комментария
				$this->add_comment($author, $email, $url, $text, $node_id);
			}
	}
	
	/**
	 *
	 * Вспомогательные функции
	 * 
	 */
	
	# Получаем из $data данные содержащиеся между [$tagname] и [/$tagname]
	function match_tag($data, $tagname){
		preg_match("#\[{$tagname}\]([\s\S]+?)\[/{$tagname}\]#i", $data, $mth);
		if($mth && strlen($mth[1]) > 1) return $mth[1];
		else return false;
	}
	
	# Добавляет пост
	public function add_node($title, $alias ,$text, $op_url=""){

		if(empty($title) || empty($text) ) return false;

		require_once( ABSPATH . '/wp-load.php');
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		if(!isset( $GLOBALS['wp_rewrite'] )) $GLOBALS['wp_rewrite'] = new WP_Rewrite();
		if(empty($title) || empty($text) || trim($text) == "") return false;
		if ( ! function_exists( 'post_exists' ) ) {
		    require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}
		if ( ! function_exists( 'get_current_user_id' ) ) {
		    require_once( ABSPATH . '/wp-includes/user.php'); 
		}
		require_once(ABSPATH . 'wp-config.php'); 
		require_once(ABSPATH . 'wp-includes/wp-db.php'); 
		require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 


		$text = $this->add_more($text);
		$p = get_page_by_title($title, OBJECT, 'post');
		if($p && $p->ID != null && $p->ID != 0) return $p->ID;

		$tmp = post_exists($title);
		if($tmp != false && $tmp != 0 && $this->rss == false) return $tmp;
		
		$post_title = wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
		$tmp = $this->post_exists($post_title);
		if($tmp != false && $tmp != 0) return $tmp;

		$tmp = $this->post_exists($post_title);
		if($tmp != false && $tmp != 0) return $tmp;

		if($alias == "") $alias = ru_str2url($title);

		if($op_url !="" && $this->rss == false){
			$tmp = get_id_by_meta($op_url);
			if($tmp != false || $tmp != 0) return $tmp;
		}
		$aid = 1;
		/*
			выбор случайного автора
			$users = get_users();
			$user = $users[array_rand($users)];
			$aid = $user->ID;
		*/

		if(!empty($this->raids)){
			$name = $this->raids[array_rand($this->raids)];
			$dd = explode("|",$name);

			$aid = username_exists( $dd[0] );
			if ( !$aid ) {
				$random_password = wp_generate_password(12, false);
				$aid = wp_create_user( $dd[0], $random_password, h_rand_str(8)."@mail.ru" );
			}
			if(isset($dd[1])){
				update_user_meta( $aid, 'city', $dd[1]);
			}
		}

		$a_post = array(
			'post_title' 	=> $title,
			'post_name'		=> $alias,
			'post_type'  	=> 'post',
			'post_content' 	=> $text,
			'post_status' 	=> $this->publ_mode,
			'post_author' 	=> $aid,
			'post_category' => $this->cat_id,
		);
		remove_all_filters("content_save_pre");
		remove_all_filters("pre_post_content");
		remove_all_filters("content_pre");
		$post_id = wp_insert_post($a_post, false);	// в случае ошибки вернет 0, при успехе - вернет ид созданного поста

		if($post_id != 0){
			add_post_meta($post_id, 'ap_mark', 'Это пост был добавлен через AftParser', true);
			if($this->rss == false) add_post_meta($post_id, 'ap_link', $op_url, true);
		}
		// для пользовательских типов
		// wp_set_object_terms( $post_id , $this->cat_id, 'question-category');
		if( $post_id != 0 && $this->auto_thumb ){

			
			preg_match_all("~(?:<a[^>]+?href(?:[^\S]*)=(?:[^\S]*)[\"'](.+?)[\"'][^>]+?>|)<img(?:[\S\s]*?|)src(?:[^\S]*)=(?:[^\S]*)[\"']([\S\s]*?)[\"'](?:[\S\s]*?)(?:>|$)~uis", $text, $matches);
			
			$media_folder = wp_upload_dir();

			// если картинок нет - выбираем случайную
			if(empty($matches[2][0])){
				$matches[1][0] = "";
				$matches[2][0] = get_random_pic();
			}

			if(!empty($matches[1]) || !empty($matches[2])){
				$images = $matches[1]; // чаще всего в ссылках полные размеры изображений

				$ext_img = @pathinfo($matches[2][0], PATHINFO_EXTENSION);
				$ext_a = @pathinfo($matches[1][0], PATHINFO_EXTENSION);

				if( $matches[1][0] == "" || $ext_a != $ext_img  ){ // однако если ссылки ведут на неведомую хуету - берем из тега img
					$images = $matches[2];
				}

				require_once(ABSPATH . 'wp-admin/includes/media.php');
				require_once(ABSPATH . 'wp-admin/includes/file.php');

				// Сортируем картинки по убыванию их размера
				usort($images, function ($a, $b){
					$a = ltrim($a, "/");
					$b = ltrim($b, "/");
					if(file_exists(ABSPATH.$a) && file_exists(ABSPATH.$b)){
						$sizea = @filesize(ABSPATH.$a);
						$sizeb = @filesize(ABSPATH.$b);
						$ext_img = pathinfo(ABSPATH.$a, PATHINFO_EXTENSION);
						if($sizea && $sizeb && intval($sizea) > intval($sizeb) && $ext_img != "gif"){
							return -1;
						}
					}
					return 1;
				});

				// устанавливаем картинку-описание, если это возможно
				foreach (array_reverse($images) as $key => $img) {
				//foreach ($images as $key => $img) {
					$img = html_entity_decode(trim($img, "/"));

					if( $this->mh > 0 || $this->mw > 0 ){
						if(function_exists("getimagesize")){
							list($w_i, $h_i, $type) = @getimagesize($img);
							if (!$w_i || !$h_i) {
								// nothing
							} else
							if($this->mh > 0 && intval($h_i) < $this->mh){
								$img = get_random_pic();
							} else
							if($this->mw >0 && intval($w_i) < $this->mw){
								$img = get_random_pic();
							}
						}
					}

					$pp = pathinfo($img);
					if ( empty($pp['extension']) ||
						!in_array($pp['extension'], array("JPG","jpg","png","PNG","jpeg","JPEG")) ||
						(!empty($pp['extension']) && preg_match('/[^a-z]/i', $pp['extension']) != 0)){
						continue;
					}
					$sfilename = $pp["basename"];

					if(!file_exists($media_folder["path"]."/".$sfilename)){
						$pos = strrpos($img, '/') + 1;
						$img = substr($img, 0, $pos) . urlencode(substr($img, $pos));
						$data = @file_get_contents($img);

						if( $data && file_put_contents($media_folder["path"]."/".$sfilename, $data) ){
							
							$img = $media_folder["path"]."/".$sfilename;

						}else 
							continue;
					} else $img = $media_folder["path"]."/".$sfilename;
					
					if(!file_exists($media_folder["path"]."/".$sfilename)) continue;
					
					$attach_id = aftp_get_image_id($pp["filename"]);
					
					if(!$attach_id){
						$wp_filetype = @wp_check_filetype($img);
						$attachment = array(
							'guid'           => $sfilename, 
						    'post_mime_type' => $wp_filetype['type'],
						    'post_title'     => preg_replace( '/\.[^.]+$/', '',  $sfilename ),
							'post_content'   => '',
							'post_status'    => 'inherit'
						);

						$attach_id = wp_insert_attachment( $attachment, $media_folder["path"]."/".$sfilename, 0 );	
					}

					if (!is_wp_error($attach_id)) {
						$attach_data = @wp_generate_attachment_metadata( $attach_id, $media_folder["path"]."/".$sfilename );
						@wp_update_attachment_metadata( $attach_id, $attach_data );

						set_post_thumbnail( $post_id, $attach_id );
						break;
					}

				}
			}
		}
		return $post_id;
	}
	
	# Проверяет, существует ли этот пост в базе, если существует - возвращет его ид.
	/*function post_exists($title){
		global $wpdb;
		$table_name = $wpdb->prefix.'posts';
		$tmp = $wpdb->get_row("SELECT `ID` FROM `{$table_name}` WHERE `post_title` = '{$title}';", 'ARRAY_N');
		if(empty($tmp)) 
			return false;
		else
			return $tmp;
	}*/

	# Добавляет тег <!--more-->
	function add_more($content){

		if(strpos($content, "<!--more-->") !== false)
			return $content;
	    
	    $razd="<br><br>";

	    $startPiece="";

		$pos=-strlen($razd);

		while(wordsCount($startPiece)<40){
	    	$pos=strpos($content,$razd,$pos+strlen($razd));

	    	if($pos===false) return $content;

	    	$startPiece=substr($content,0,$pos);
	    }
	    $endPiece=substr($content,$pos,strlen($content)-($pos));

	    return $startPiece."<!--more-->".$endPiece;

	}
	
	# Добавляет комментарий
	public function add_comment($author, $email, $url, $text, $node_id){
		if($email == "") $email = h_rand_str(7)."@mail.ru";
		$time = current_time('mysql');
		if(function_exists('tidy_repair_string')){
			$text = tidy_repair_string($text, array('show-body-only' => true), "utf8");
		}
		$text = trim($text);
		$user_id = username_exists( $author );
		if ( !$user_id ) {
			$random_password = wp_generate_password(12, false);
			$user_id = wp_create_user( $author, $random_password, $email );
		} else {
			$random_password = __('User already exists.  Password inherited.');
		}

		// Convert to timetamps
		$min = strtotime("-7 days");
		$max = strtotime("today");

		// Generate random number using above bounds
		$rdate = rand($min, $max);

		$data = array(
			'comment_post_ID' => $node_id,
			'comment_author' => $author,
			'comment_author_email' => $email,
			'comment_author_url' => $url,
			'comment_content' => $text,
			'comment_type' => '',
			'comment_parent' => 0,
			'user_id' => $user_id,
			'comment_author_IP' => '127.0.0.1',
			'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
			'comment_date' => date("Y-m-d H:i:s",$rdate),// или null, чтобы было текущее время
			'comment_approved' => 1,
		);
		$comments = get_comments(array("post_id"=>$node_id));
		
		foreach($comments as $comment){
			
			if($comment->comment_content == $text) return false;
		}
		
		return wp_insert_comment($data); // вернет ид комметария или false
	}
	
}
// end of file //