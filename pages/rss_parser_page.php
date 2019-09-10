<?php
//
/**
 * 
 * Страница парсера rss лент
 * 
 */
 
# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

$rss_url = "";

$title = "";
$macro = "";
$id = NULL;

// дескриптор для доступа к базе
global $wpdb;
// ИД таблицы
$table_name = $wpdb->prefix.'aft_parser';
// кол-во парсеров
$pcount = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

$opt = null;
// Режим редактирования
if(isset($_GET['action']) && $_GET['action'] == "edit"){
	$id = intval($_GET['parser_id']);
	$query = $wpdb->prepare("SELECT * FROM {$table_name}
									WHERE `id` = '%d' AND `mode` = '1'", 
										array(
											$id,
										)
									);
	$data = $wpdb->get_results($query, ARRAY_A);
	if(!empty($data)){
		$rss_url = $data[0]['links_list'];
		$macro = $data[0]['macro'];
		$title = $data[0]['title'];
		$opt = $data[0];
	}
}

?>

<div class="wrap">
	<h2>Парсер обновлений rss ленты</h2>
	<form method="POST" id="main-form" page="rss">
	<table class="form-table">
		<tr valign="top">
			<th scope="row">Начало:</th>
			<td class='top_al' colspan="2">
				<p>Путь до rss ленты: 
					<input type='text' style="width:50%" placeholder="http://sitename.ru/feed.rss" id='aft_rss_url' name='url' value="<?php echo $rss_url; ?>" />
				<p>
				<div class="aft_attention">
					<b>Информация:</b>
					<ol>
						<li>Rss поток должен удовлетворять спецификации <a href="http://validator.w3.org/feed/docs/rss2.html" target="_blank" rel="noindex external nofollow">2.0</a>.</li>
						<li>Урл должен начинаться с http://</li>
					</ol>
				</div>
			</td>
			<td />
		</tr>
		
		<tr>
			<th>Предварительная обработка:</th>
			<td colspan="2" class='top_al'>
				<div>
					<p>Код предварительной обработки:</p>
					<p><textarea id="aft_macro" class='aft_textarea editor' style="height:210px;" wrap="off" name="macro">
<?php if(!isset($macro) || $macro == ""){ ?>
/* 
* Приведение кодировки
* $blog_enc - кодировка страниц блога
* $src_enc - автоматически определенное значение кодировки. Берется из тега &lt;meta&gt; страницы.
* Если значение $src_enc определяется не верно - укажите его самостоятельно.
*/
if($blog_enc != $src_enc){
	$content = mb_convert_encoding($content, $blog_enc, $src_enc);
	$title 	 = mb_convert_encoding($title, $blog_enc, $src_enc);
}

$res .= $pr->set_title($title);				//Заголовок материала
$content = $pr->process_images($content);	//Закачиваем картинки
$res .= $content.$attachments;				//Основной текст материала
<?php }else echo stripslashes_deep($macro); ?>
					</textarea><p>
					<div class='aft_attention'>
						<b>Информация:</b>
						<p>Используется php код, если вы не знаете что это такое - не трогайте это поле, парсер и так работать будет.</p>
						<strong>Вам доступны следующие переменные:</strong>
						<p>$res - Переменная, в которой формируется результат.</p>
						<p>$rss_xml - Код rss ленты.</p>
						<p>$item - Участок ленты в теге &lt;item&gt;, который обрабатывается в данный момент.</p>
						<p>$title - Название обрабатываемой статьи.</p>
						<p>$attachments - Строка с файлами, вложенными в ленту(они сначала скачиваются на сервер, так что все в порядке).</p>
						<p>$content - Содержимое обрабатываемой в данный момент статьи.</p>
						<p>$link - Ссылка в теге &lt;link&gt;</p>
						<p>$category - Возможная категория.</p>
						<p>Если вы хотите пропустить этот материал - впишите команду <i>continue;</i></p>
						<p>Если вы хотите остановить обработку - впишите команду <i>break;</i></p>
						<p>Вам доступен весь функционал языка php. Мощнейшие функции обработки строк, регулярные выражения и мноое другое. Пишите свой код с умом!</p>
						<br />
						<strong>Методы для продвинутой обработки:</strong>
						<ul>
							<li class='aft_macro_info'><p>$html = $pr->load($url, $cookie, $post); - Загружает страницу по переданному адресу. Адрес должен начинатся с http://. Два последних параметра($cookie и $post) - не обязательные.</p></li>
							<li class='aft_macro_info'><p>$res  = $pr->process_images($res); - Ищет в $res(результате) все теги &lt;img&gt; и скачивает на сервер в стандартную папку картинки, которые в этих тегах размещены. Возвращает строку с измененными путями картинок. Имена изображений так-же меняются.</p></li>
							<li class='aft_macro_info'><p>$res = $pr->change_image_meta($res,"Новый тайтл/альт для картинки - НЕ ОБЯЗАТЕЛЬНО"); -  Меняет title или alt картинки</p></li>
							<li class='aft_macro_info'><p>$pr->use_random_image_names(); -  Меняет названия картинок</p></li>
							<li class='aft_macro_info'><p>$res .= $pr->get_translation($res, $fromlg, $tolg, $key); - перевод текста $text с языка $fromlg(например ru) на язык $tolg(en). Ключ API $key - опционален(не обязателен).</p></li>
							<li class='aft_macro_info'><p>$res .= $pr->set_title($this->get_border('fragment_1',$html)); - Устанавливает название будущего материала. В данном случае названием будет текст, размещенный в границе с именем "fragment_1"</p></li>
							<li class='aft_macro_info'><p>$res .= $pr->add_comment($author,$email,$author_url,$text); - Добавляет комментарий с текстом $text, от имени пользователя $author.</p></li>
							<li class='aft_macro_info'><p>$res .= $pr->set_catname($category); - Задает категорию, в которую публикуется материал. Если категория с таким именем не существует - она будет создана. Этот параметр можно не указывать, если вы хотите настроить этот парсер только для одной категории.</p></li>
							<li class='aft_macro_info'><p>$res .= $pr->set_alias($path); - Задает алиас, то есть адрес, по которому будет доступен создаваемый материал.</p></li>
							<li class='aft_macro_info'><p>$f_name = $pr->upload_file($url); - Загружает файл с указанного адреса и возвращает его путь, который потом удобно вставлять в ссылки. В случае неудачи вернет fasle;</p></li>
							<li class='aft_macro_info'><p>$title = $pr->cut_str($html,'&lt;title&gt;','&lt;/title&gt;'); - Вырезает из $html участок начинающийся с &lt;title&gt; и заканчивающийся &lt;/title&gt;, возвращает вырезанный участок или false.</p></li>
							<li class='aft_macro_info'><p>Работают регулярные выражения:</p>
								<ol>
									<li>preg_match ($pattern , $subject, $matches);</li>
									<li>preg_match_all ($pattern , $subject, $matches);</li>
									<li>$res = preg_replace ($pattern , $replacement, $subject);</li>
								</ol>
								<p>Для составления собственных регулярных выражений советую использовать сервис <a href="http://www.regexr.com/" target="_blank" rel="noindex external nofollow">RegExr</a></p>
							</li>
						</ul>
						<p>Нужно больше функций? Окей.<br />Пишите <a href="http://aftamat4ik.ru/contacts" target="_blank" rel="external noindex nofollow">сюда</a> свои предложения и я их рассмотрю.</p>
					</div>
				</div>
			</td>
			<td />
		</tr>
		
		<tr valign="top">
			<th scope="row">Разное:</th>
			<td>
				<p>В какой категории публиковать полученные материалы?</p>
				<label>
					<input type="radio" name="cat_id" id="cat_id" value="random" 
					<?= !isset($cdata["cat_id"]) || $cdata["cat_id"] == "random" ? "checked" : ""; ?>> Случайная категория
				</label>
				<br>
				<div id="cat_block"><?php 
					$categories = get_terms( 'category', array(
					    'orderby'    => 'count',
					    'hide_empty' => 0,
					 ) );
					
					foreach($categories as $key=>$cat){ 
						$cat_name = $cat->name;
						$ch = "";
						if($opt['cat_id'] != "random" && $cat->term_id == $opt['cat_id']) $ch = "checked";
						?>
						<label>
							<input type="radio" name="cat_id" value="<?= $cat->term_id; ?>" <?= $ch; ?>> <?= $cat_name; ?>
						</label>
						<br>
						<?php 
					} 
				?></div>
			</td>
			<td class='top_al'>
				<p>Статус добавляемых материалов:</p>
				<select name='status' id='aft_status'>
					<option value="pending" <?php echo ($opt != null && $opt['publ_mode'] == 'pending') ? 'selected' : ''; ?>>На утверждении</option>
					<option value="publish" <?php echo ($opt != null && $opt['publ_mode'] == 'publish') ? 'selected' : ''; ?>>Опубликовано</option>
				</select>
			</td>
		</tr>
		
		<tr valign="top">
			<th>Варианты использования:<br><small>Прежде чем тыкать тут кнопки - проверьте, все ли поля у вас заполнены.</small></th>
			<td class='top_al'>
				<p>Протестировать работу, обработав <input id="aft_test_count" name="tcount" type="number" min="1" step="1" max="5" size="1" value ="1"/> элементов.</p>
				<div class='aft_attention'>
					<b>Внимание:</b>
					<p>Если вы укажите слишком большое число, то ваш хостинг, скорее всего, упадет. Будьте осторожны.</p>
					<p>После нажатия кнопки будет выведено окно с информацией, содержащей результаты работы парсера.</p>
				</div>
				<p class="submit"><a id='test_parse_rss' class="button-primary" href="#"><?php _e('Запуск теста') ?></a></p>
			</td>
			<td class='top_al'>
				<p>Парсить все элементы, представленные в rss ленте.</p>
				<div class='aft_attention'>
					<b>Информация:</b>
					<p>В результате работы парсера будут добавлены новые материалы.</p>
					<p>Распределение по потокам отсутствует, так что большие ленты будут вешать ваш сервер.</p>
					<p>Тем не менее парсинг можно повтрять раз за разом, т.к. уже существующие материалы будут пропускаться.</p>
				</div>
				<p class="submit" style="position: relative;"><a id='rssparse_all' class="button-primary" href="#">Начать парсинг</a></p>
			</td>
		</tr>
		<?php if($id != NULL){ ?> 
			<input type="hidden" name="pid" value="<?php echo $id; ?>">
		<?php } ?>
 		<tr valign="top">
			<th scope="row">Парсинг обновлений:</th>
			<td colspan="2">
				<p>Название парсера: <input id="aft_ptitle" type="text" name="title" placeholder="Это поле обязательно!" value="<?php echo !empty($title)? $title : "rss_parser".$pcount; ?>" />&nbsp;
				<p>Количество ссылок, обрабатываемое 1 проход: <input id="aft_links_pd" name="nl" type="number" min="1" step="1" max="10" value ="<?php echo ($opt != null) ? $opt['num_links'] : '1'; ?>"/></p>
				
				<a id="aft_save_rssparser" class="button-primary" href="#">Сохранить для парсинга обновлений</a></p>
				<div class='aft_attention'>
					<b>Инфо:</b>
					<p><i>Парсинг обновлений</i> - это процесс сбора самой свежей информации из новостной ленты.</p> 
					<p>Каждый день парсер будет проверять rss поток на наличие обновлений и добавлять обновления материалы на сайт.</p>
					<p>Этот метод наиболее практичен и экономит ресурсы сервера.</p>
				</div>
			<td>
		</tr>
	</table>
	</form>
</div>

<?php
	wp_nonce_field('new_site_parser_action','nonce_cheker'); 
	$ajax_nonce = wp_create_nonce( "aft_parser_key" );
?>

<script type="text/javascript">
	var ajax_nonce_field = '<?php echo $ajax_nonce; ?>';
</script>