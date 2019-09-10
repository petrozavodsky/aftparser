<?php
//
/**
 * Дополнительные функции
 */

if (!defined( 'ABSPATH' )){
	exit('Вызов файлов плагина напрямую запрещен.');
}

// размер файла по ссылке
if (!function_exists('get_remote_size')) {
    function get_remote_size($url) {
        $c = curl_init();
        curl_setopt_array($c, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array('User-Agent: Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3'),
            ));
        curl_exec($c);
        return curl_getinfo($c, CURLINFO_SIZE_DOWNLOAD);
    }
}

/**
 * Начинается с
 */
if(!function_exists('aftp_get_image_id')){
    // Media id картинки по ее урл адресу
    function aftp_get_image_id($image_url) {
        global $wpdb;
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid LIKE '%s';", '%' . $wpdb->esc_like($image_url) . '%')); 
        return isset($attachment[0]) ? $attachment[0] : false; 
    }
}

// ucfirst
if(!function_exists('mb_ucfirst')) {
    function mb_ucfirst($str, $enc = 'utf-8') { 
            return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc).mb_substr($str, 1, mb_strlen($str, $enc), $enc); 
    }
}

// проверяет существует ли такой материал по его названию
if(!function_exists("is_title_exists")){
    function is_title_exitst($title){
        require_once( ABSPATH . '/wp-load.php');
        if(!isset( $GLOBALS['wp_rewrite'] )) $GLOBALS['wp_rewrite'] = new WP_Rewrite();
        if(empty($title)) return false;
        if ( ! function_exists( 'post_exists' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/post.php' );
        }

        $tmp = post_exists($title);
        if($tmp != false && $tmp != 0) return $tmp;

        $post_title = wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
        $tmp = $this->post_exists($title);
        if($tmp != false && $tmp != 0) return $tmp;

        return false;
    }
}

function get_id_by_meta($alias){
    $args = array(
        'post_type'     => 'post',
        'meta_key'      => 'ap_link',
        'meta_value'    => $alias,
        'posts_per_page' => -1,
        'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
    );

    global $wpdb;
    global $wp_rewrite;

    $posts = get_posts($args);
    
    if(!empty($posts)) foreach($posts as $post){ 
        setup_postdata($post);
        return $post->ID;
    }

    wp_reset_postdata();
    return false;
}

/**
 * Начинается с
 */
if(!function_exists('startsWith')){
    function startsWith($haystack, $needle) {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}

/**
 * Оканчивается на
 */
if(!function_exists('endsWith')){
    function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}

/**
 * Генерация случайной строки для имени картинки
 * $l- длинна строки, к примеру 9 символов. $c - из каких символов бдет эта строка состоять
 */
if(!function_exists('h_rand_str')){
    function h_rand_str ($l, $c = 'abcdefghijklmnopqrstuvwxyz1234567890') {
        for ($s = '', $cl = strlen($c)-1, $i = 0; $i < $l; $s .= $c[mt_rand(0, $cl)], ++$i);
        return $s;
    }
}

/**
 * Считает слова
 */
if(!function_exists('wordsCount')){
	function wordsCount($string){
        $string=trim($string);
        if(empty($string))return 0;

        $ar=split(" ",$string);
        return count($ar);
    }
}

/**
 * Получение случайной картинки
 */
 if(!function_exists("get_random_pic")){
    function get_random_pic() {
        $files = glob(AFTPARSER__PLUGIN_DIR."rimage/*");
        
        $imid = get_option( "aft_rimid" );
        if(!$imid || $imid >= count($files)) $imid = 0;
        
        $f = AFTPARSER__PLUGIN_URL."rimage/".basename($files[$imid]);
        $imid ++;
        update_option( "aft_rimid",$imid );
        return $f;
    }
}


/**
 * Обрезка
 */
if(!function_exists("icrop")){
    function icrop($file_input, $file_output, $crop = 'square',$percent = false) {
        list($w_i, $h_i, $type) = getimagesize($file_input);
        if (!$w_i || !$h_i) {
            //echo 'Невозможно получить длину и ширину изображения';
            return;
        }
        
        $types = array('','gif','jpeg','png');
        $ext = $types[$type];
        if ($ext) {
                $func = 'imagecreatefrom'.$ext;
                if( !function_exists($func) ) return;
                $img = $func($file_input);
        } else {
                //echo 'Некорректный формат файла';
        return;
        }

        /*
        $image = wp_get_image_editor( 'cool_image.jpg' );
        if ( ! is_wp_error( $image ) ) {
            if ($crop == 'square') {
                $image->resize( $w_i, $w_i, true );
            }
        }else {
            list($x_o, $y_o, $w_o, $h_o) = $crop;
            $image->crop( $x_o, $y_o, $h_i, $src_h, $w_o, $h_o );

        }
        
        $image->save( $file_output );
        return;
        */
        
        if ($crop == 'square') {
            $min = $w_i;
            if ($w_i > $h_i) $min = $h_i;
            $w_o = $h_o = $min;
        } else {
            list($x_o, $y_o, $w_o, $h_o) = $crop;
            if ($percent) {
                $w_o *= $w_i / 100;
                $h_o *= $h_i / 100;
                $x_o *= $w_i / 100;
                $y_o *= $h_i / 100;
            }
                    if ($w_o < 0) $w_o += $w_i;
                $w_o -= $x_o;
            if ($h_o < 0) $h_o += $h_i;
            $h_o -= $y_o;
        }
        $img_o = imagecreatetruecolor($w_o, $h_o);
        imagecopy($img_o, $img, 0, 0, $x_o, $y_o, $w_o, $h_o);
        if ($type == 2) {
            return imagejpeg($img_o,$file_output,40);
        } else {
            $func = 'image'.$ext;
            if( !function_exists($func) ) return;
            return $func($img_o,$file_output,40);
        }
    }
}

// translit
if(!function_exists("rus2translit")){
    function rus2translit($string) {
        $converter = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',
            'г' => 'g',   'д' => 'd',   'е' => 'e',
            'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
            'и' => 'i',   'й' => 'y',   'к' => 'k',
            'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',
            'с' => 's',   'т' => 't',   'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            
            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        );
        return strtr($string, $converter);
    }
}

if(!function_exists("ru_str2url")){
    function ru_str2url($str) {
        // переводим в транслит
        $str = rus2translit($str);
        // в нижний регистр
        $str = strtolower($str);
        // заменям все ненужное нам на "-"
        $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
        // удаляем начальные и конечные '-'
        $str = trim($str, "-");
        return $str;
    }
}