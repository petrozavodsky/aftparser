<?php
//
/**
 * 
 *  Класс-предок для ajax обработчиков
 * 
 */

class pAjax{
	static $instance;
	function __construct() {
        self::$instance = $this;

        add_action( 'wp_ajax_map_page',  	array($this, 'map_page') );
		add_action( 'wp_ajax_parse_sitemap',array($this, 'parse_sitemap') );
		add_action( 'wp_ajax_filter_links', array($this, 'filter_links') );
		add_action( 'wp_ajax_aft_add_crontab', array($this, 'vns_add_crontab') );      	// Ajax добавление CRON'а
		add_action( 'wp_ajax_aft_remove_crontab', array($this, 'vns_remove_crontab') );	// Ajax удаления CRON'а
	}
	
	/**
	 * 
	 * Вспомогательные методы
	 * 
	 */

	// Ajax удаления CRON'а
	function vns_remove_crontab(){
		$_POST = stripslashes_deep($_POST);
		if(!isset($_POST['data'])) die("Ошибка передачи параметров");
		$data = json_decode($_POST['data'], true); // $data[0] - cron_secret, $data[1] - cron timing
		
		$secret = isset($data["secret"]) ? $data["secret"] : "";
		if($secret == "") die(json_encode(array("e"=>$otxt."\r\nОшибка передачи POST данных!")));
		$cstring = "wget -qO /dev/null ".get_site_url()."?aftcron=".$secret;

		$otxt = print_r(shell_exec("crontab -l | grep -v '".$cstring."' | crontab -"), true);

		$l = print_r(shell_exec("crontab -l"),true);
		die(json_encode(array("e"=>$otxt."\r\nГотово!", "l"=>$l)));
	}

	// Ajax добавление CRON'а
	function vns_add_crontab(){
		$_POST = stripslashes_deep($_POST);
		if(!isset($_POST['data'])) die("Ошибка передачи параметров");
		$data = json_decode($_POST['data'], true);
		$secret = isset($data["secret"]) ? $data["secret"] : "";
		if($secret == "") die(json_encode(array("e"=>$otxt."\r\nОшибка передачи POST данных!")));
		$discret = isset($data["discret"]) ? $data["discret"] : "0 */3 * * *";
		
		$cstring = $discret." wget -qO /dev/null ".get_site_url()."?aftcron=".$secret;

		$otxt = print_r(shell_exec("crontab -l > mycron
#echo new cron into cron file
echo \"".$cstring."\" >> mycron
#install new cron file
crontab mycron
rm mycron"),true);

		$l = print_r(shell_exec("crontab -l"),true);
		die(json_encode(array("e"=>$otxt."\r\nГотово!", "l"=>$l)));
	}
	
	# Проверка значений
	public function validate_data($url_list, $borders, $cat_id, $macro, $publ_mode){
		if(!is_admin()) return false;	// Дальше этого ни один кулцхакер не прыгнет. Без доступа к админке eval не работает. Обломчик.
		
		if(empty($url_list) || empty($borders) || empty($macro)) return false;
		return true;
	}

	# Ajax обработчик, индексирующий сайт.
	public function map_page(){
		$this->validate_ajax();
		if(empty($_POST['url'])) exit(1);
		$url = htmlspecialchars($_POST['url']);
		
		$pr = new Parser();
		$links = $pr->parse_links($url);
		if(!empty($links)) echo $links; 
		die();
	}
	
	# Ajax обработчик парсера карты сайта
	public function parse_sitemap(){
		$this->validate_ajax();
		if(empty($_POST['url'])) exit(1);
		$url = htmlspecialchars($_POST['url']);
		
		$pr = new Parser();
		$links = $pr->get_sitemap_links($url);
		if(!empty($links)) echo $links;
		die();
	}
	
	# Ajax фильтровка списка ссылок. Сначала я хотел вынеси все это в класс парсера, но потом решил то не стоит.
	public function filter_links(){
		$this->validate_ajax();
		$links_list = stripslashes(urldecode($_POST['links']));
		$macro 		= stripslashes(urldecode($_POST['filter']));
		
		$deny = '#(?:[\s\S]*)include|require|readfile|show_source|highlight_file|import_request_variables|extract|base64_decode|create_function|parse_str|eval|$wpdb|global|assert|passthru|exec|system|shell_exec|proc_open|mysql_query|fopen(?:[\s\S]*)#is';
		if(preg_match_all($deny, $macro, $matches)){
				exit(json_encode(array("error","Вы используете запрещенные к вызову функции!")));
			}
		$tmp = "";
		$ll = explode("\n", $links_list);
		$ll = array_unique($ll);
		$error = "";
		foreach($ll as $link){
			$result = $link;
			ob_start();					//Перехват стандартного вывода в буфер
			eval($macro);
			$error = ob_get_contents();	//Получние данных из буфера
			ob_end_clean();
			if(!empty($result)) $tmp .= $result."\r\n";
		}
		if($error == ""){
			echo json_encode(array("success",$tmp));
		}else{
			echo json_encode(array("error",$error));
		}
		exit();
	}
	
	#Валидация Ajax запроса
	
	public function validate_ajax(){
		check_ajax_referer( 'aft_parser_key', 'security', true );
		if (!empty($_POST['nonce_cheker']) && !wp_verify_nonce($_POST['nonce_cheker'],'new_site_parser_action')) exit();
	}
}