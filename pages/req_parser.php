<?php
//
/**
 * 
 * Страница парсера ссылок
 * 
 */

# Защита от мудаков
if (!defined( 'ABSPATH' )){
	header('HTTP/1.0 403 Forbidden');
	exit('Вызов файлов плагина напрямую запрещен.');
}

$links_list 	= "";
$borders_html	= "";
$title 			= "";
$macro 			= "";
$is_fr_has_title = false;
$id 			= NULL;

global $wpdb;
$table_name = $wpdb->prefix.'aft_parser';
// кол-во парсеров
$pcount = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

// наговнокодил а менять уже поздно - совместимость поломается. 
// вот в следующей версии наведу порядок, пусть патч к базе применится сначала у людей.
$opt = null;
$cdata = null;
?>

<script type="text/javascript">
	/**
	 * глобальный массив границ фрагментов 
	 * каждый фрагмент идет в формате json-объекта {title:_title, top_border:_top_border, bottom_border:_bottom_border, mode: _mode}
	 */
	var fragments = !fragments ? [] : fragments;			 
</script>
<?php

// режим редактирования
if(isset($_GET['action']) && $_GET['action'] == "edit"){
	
	$id = trim($_GET['parser_id']);
	$query = $wpdb->prepare("SELECT * FROM {$table_name}
									WHERE `id` = '%d' AND `mode` = '2'", 
										array(
											$id,
										)
									);
	
	$data = $wpdb->get_results($query, ARRAY_A);
	$data = isset($data[0]) ? $data[0] : false;
	if(!empty($data)){	
		$links_list = $data['links_list'];
		$macro = $data['macro'];
		$title = $data['title'];
		$opt = $data;
		$cdata = unserialize($data['custom_data']);
	}
}

/*$ttt = 1; 
@eval("\$ttt=2;"); // проверяем работает ли eval как надо*/
$ttt = 2; 
?>

<div class="wrap">
<h2>Универсальный парсер сайтов</h2>
	<div class="parse_list">
	<form method="POST" id="main-form" page="rss">
	<input type="hidden" name="use-borders" value="req_parser">
	<input type="hidden" name="macro" value="&lt;?php // nothing ?&gt;">
	<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row">Ссылки:</th>
			<td class='top_al'>
				<label for="aft_links_list"><b>Добавьте запросы</b></label>
				<br>
				<span id="link-list-count"></span><br>
				<?php if($opt['last_parsed'] && $opt['last_parsed'] != ""): ?>
					<span>сейчас обрабатывается: <code><?= $opt['last_parsed'] ?></code></span>
				<?php endif; ?>
				<p><textarea id="aft_links_list" style="width:100%; height:210px;" wrap="off" name="links_list"><?php echo $links_list; ?></textarea><p>
				<a id="btn_links_revert" href="#" class='button-secondary'>Реверсия</a>
				<a id="btn_links_unique" href="#" class='button-secondary'>Убрать дубли</a>
				<a id="btn_links_remrus" href="#" class='button-secondary'>Убрать кириллицу</a>
				<a id="btn_links_remlat" href="#" class='button-secondary'>Убрать латинницу</a>
				<br>
				<br>

				<p>
					<label for="query_args"><b>Аргументы командной строки</b></label><br>
					<small>Эти аргументы будут добавлены к поисковому запросу. <a href="https://yandex.ru/support/search/query-language/search-context.html" target="_blank">[контекст запроса]</a> и <a href="https://yandex.ru/support/search/query-language/search-operators.html" target="_blank">[операторы поиска]</a><br>
						например: <code>date:<<?= date("Ymd") ?></code> или <code>date:<?= date("Ymd",strtotime("-3 months")) ?>..<?= date("Ymd") ?></code> или <code>lang:en</code>
					</small><br>
					
					<input type="text" name="query_args" id="query_args" class="regular-text" value="<?php echo isset($cdata["query_args"]) ? $cdata["query_args"] : ""; ?>" placeholder="date:<<?= date("Ymd") ?>">
				</p>
				<p>
					<label for="exclude_urls"><b>Черный список сайтов (глобальный):</b></label><br>
					<small>Укажите домен сайта или часть его урл адреса, чтобы пропускать его при парсинге. Этот список работает для всех парсеров.</small><br>
					<textarea id="exclude_urls" style="width:100%; height:70px;" wrap="off" placeholder="aftamat4ik.ru" name="exclude_urls">sunmag.me
platinental.ru
<?php $urls = get_option( "aft_exclude_urls" ); echo $urls ? $urls : ""; ?></textarea>
				</p>
				<br>
				<br>
				<label for="aft_proxy"><b>Прокси (глобальный список)</b></label>
				<br>
				<small>формат: 127.0.0.1:8888|ЛОГИН:ПАРОЛЬ(если есть)</small>
				<br>
				<p><textarea id="aft_proxy" style="width:100%; height:70px;" wrap="off" name="aft_proxy"><?php 
					$proxy_list = get_option( "aft_proxy_list" );
					
					if($proxy_list && $proxy_list != "")
						echo $proxy_list; 
				?></textarea></p>
				<br>
				<b>Чтобы парсить тематические статьи, нужно иметь доступ к yandex.xml, который получается через сервис <a target="_blank" rel="noindex nofollow" href="http://xmlproxy.ru">xmlproxy.ru</a></b>
				<br><label for="yemail">E-mail</label><br>
				<input type="text" id="yemail" name="yemail" class="regular-text" value="<?php $ye = get_option( "aft_yemail" ); echo $ye ? $ye : "aftamat4ik@gmail.com"; ?>">
				<br><label for="ykey">Key</label><br>
				<input type="text" id="ykey" name="ykey" class="regular-text" value="<?php $ykey = get_option( "aft_ykey" ); echo $ykey ? $ykey : "MTUxNjk2NTMyNjc2NTMyMjcyNjMzODc4OTM5"; ?>">
				<br>
				<br><label for="yregion">RegionCode</label><br>
				<p>популярные: 225:россия, 187:украина, 159:казахстан, 111:евросовок, 96:германия, 84:пиндостан <a href="https://yandex.ru/yaca/geo.c2n" target="_blank">[полный список]</a></p>
				<input type="text" id="yregion" name="yregion" class="regular-text" value="<?php $yregion = get_option( "aft_yregion" ); echo $yregion ? $yregion : "111"; ?>">
				<br>
				<br>
					<label>
						<input type="checkbox" <?php $ykey = get_option( "use_y_xml" ); echo $ykey && $ykey == "yandex" ? "checked" : ""; ?> name="use_y_xml" value="yandex" > Использовать Yandex XML вместо XMLPROXY
					</label>
				<br>
				<br>
				<br><label for="page">Страница поиска, с которой будут собираться посты(0-19)</label><br>
				<input type="number" min="0" max="19" step="1" id="page" name="page" class="regular-text" value="<?php echo isset($cdata["page"]) ? $cdata["page"] : "0"; ?>">
				<br><label for="collect-count">Кол-во страниц для объединения</label><br>
				<input type="number" min="1" max="9" step="1" id="collect-count" name="collect-count" class="regular-text" value="<?php echo isset($cdata["collect-count"]) ? $cdata["collect-count"] : "4"; ?>">


				<br>
				<br>
				<label><b>Изображения</b></label>
				<br>

				<p>
				<label>
					<input type="checkbox" <?= isset($cdata["add_media"]) && $cdata["add_media"] == "1" ? "checked" : ""; ?> name="add_media" value="1" > Каждую картинку добавлять в media библиотеку
				</label>
				</p>
				<p>
				<label>
					<input type="checkbox" <?= isset($cdata["clear_images"]) && $cdata["clear_images"] == "1" ? "checked" : ""; ?> name="clear_images" value="1" > Обнулять верстку картинок
				</label>
				</p>
				<p>
				<label>
					<input type="checkbox" <?= !isset($cdata["auto_thumb"]) || $cdata["auto_thumb"] == "1" ? "checked" : ""; ?> name="auto_thumb" value="1" > Автоустановка изображения записи
				</label>
				</p>
				<p>
				<label>
					<input type="number" name="minfsize" value="<?= isset($cdata["minfsize"]) ? intval($cdata["minfsize"]) : "10000"; ?>" > Минимальный размер картинки
				</label>
				</p>
				<p>
				<label>
					<input type="number" name="minfwidth" value="<?= isset($cdata["minfwidth"])? intval($cdata["minfwidth"]) : "300"; ?>" > Минимальная ширина картинки
				</label>
				</p>
				<p>
				<label>
					<input type="number" name="maxipp" value="<?= isset($cdata["maxipp"])? intval($cdata["maxipp"]) : "1000"; ?>" > Максимальное кол-во картинок в одном материале
				</label>
				</p>
				
			</td>
			<td>
				<div class="aft_attention">
					<b>Информация:</b>
					<ol>
						<li> Размещайте по одному запросу на строку.</li>
						<li> Запросы лучше всего брать на <a href="https://www.keys.so" rel="nofollow noindex" target="_blank">keys.so</a> или на <a href="http://mutagen.ru" rel="nofollow noindex" target="_blank">mutagen.ru</a>.</li>
						<li> Если нет денег на предложенные выше варианты, присмотритесь к этому <a href="http://beta.bukvarix.com/" rel="nofollow noindex" target="_blank">beta.bukvarix.com</a>.</li>
					</ol>
				</div>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Обработка</th>
			<td class='top_al' colspan="2">			
				<div class="pvariants" nactive="none">
					<h4>Простой фильтр ссылок</h4>
					<div>
						<div class='aft_attention'>
						<b>Информация:</b>
						<p>Чтобы отфильтровать ссылки из списка, укажите параметры фильрации. Каждый - с новой строки.</p>
						<b>Механизм работы фильтров:</b>
						<p>Если прописать в список "не содержит" строчки: #,about и feed, то ссылки, содержищие одно из этих слов - будут исключены из результата фильтрации.</p>
						<p>Если прописать в список "содержит" строчки: category и page, то ссылки, содержищие одно из этих слов - будут включены в результаты фильтрации, при условии, что они не содержат слова из списка "не содержит".</p>
						</div>
						<table><!-- таблицами верстает... наркоман наверное =) -->
							<tr valign="top">
								<td class='top_al'>
									<p>Строка содержит:</p>
									<textarea id="aft_link_cont" style="width:100%; height: 80px;" wrap="off" name="link_cont"></textarea>
								</td>
								<td class='top_al'>
									<p>Строка не содержит:</p>
									<textarea id="aft_link_not_cont" style="width:100%; height: 80px;" wrap="off" name="link_not_cont">#
тест
калькулят
фото
скрин
картинк
видео
купит
вики
wiki
2017
2018
i
2016
форум
сайт
онлайн
2015
отзывы
скачать</textarea>
								</td>
							</tr>
						</table>
						<a id="btn_efilter_links" href="#" class='button-primary'>Фильтровать</a>
					</div>

					<h4>Продвинутый фильтр/редактор ссылок(php)</h4>
					<div>
						<div class='aft_attention'>
						<b>Информация:</b>
						<p>Переменная $link представлет из себя ссылку, обрабатываемую в текущий момент времени.</p>
						<p>Переменная-результат, отвечающая за то, выводить ссылку или нет, называется $result.</p>
						<p>Дубли удаляются автоматически.</p>
						<p>Так-же можно изменять содержимое ссылок. К примеру так: $result = $link.'123';</p>
						<p>Доступен весь функционал языка PHP, за исключением опасных функций. Но! Кто вас <s>дибилоидов</s> знает... Короче будьте осторожны.</p>
						</div>
						<br>
						<textarea id="aft_txt_filter_ex" class="editor" style="width:100%; height: 130px; " wrap="off" name="txt_filter_ex">
/**
 * Код, приведенный ниже проверяет урл на знаки #
 * все адреса, содержащие в себе знак # будут отфильтрованы и исключены из результата
 */
if(strpos($link,'#') === false) $result = $link;
						</textarea>
						<a id="btn_filter_links" href="#" class='button-primary'>Фильтровать</a>
					</div>

				</div>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row">Категория</th>
			<td>
				<label>
					<input type="radio" name="cat_id" id="cat_id" value="random" 
					<?= !isset($cdata["cat_id"]) || $cdata["cat_id"] == "random" ? "checked" : ""; ?>> Случайная категория
				</label>
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
		</tr>
		</tbody>
	</table>

	<table class="form-table selectors">
		<tbody>
			<tr valign="top">
				<th scope="row">Задайте селекторы:</th>
				<td>
					<div class="aft_attention">
						<p>Для выборки контента используются jQuery-подобные селекторы.</p>
						<a href="https://api.jquery.com/category/selectors/">документация</a>
						<p>Разумеется работают не все селекторы, но основные работают.</p>
						<p>Вместо селектора <code>:nth-child(номер)</code> используется просто [номер], к примеру: <code>p[2] эквивалентен p:nth-child(2) в jQuery</code> то есть это второй блок &lt;p&gt; в элементе. Отсчет начинается <b>не с нуля, а с единицы</b>. <code>[1]</code> - первый элемент.</p>
					</div>
					<br>
					<br>
					<label for="content_selector"><b>Возможные селекторы содержимого: <i title="Обязательно!" style="color:red;">*</i></b></label>
					<p>к примеру <code>article</code> или <code>.entry-content</code> или <code>#post-content</code></p>
					<p>плагин пытается найти на сайтах блоки с такими элементами</p>
					<textarea id="content_selectors" style="width:40%; height: 80px;" wrap="off" name="content_selectors"><?php
					echo isset($cdata["content_selectors"]) ? $cdata["content_selectors"] : ".content
.entry-content
.text-content
#content
.single
.hentry
.article
.post-body
.entry
.newsitem_text
#main
.post
.toc
.toc-header
.toc-title
#post
.inner
.innery";
					?></textarea>
					<br>
					<br>
					
					<label>
						Минимальный размер статьи (0 для отключения) <input type="number" name="min_content_len" value="<?= isset($cdata["min_content_len"])? intval($cdata["min_content_len"]) : "300"; ?>" >
					</label>	
					<br>
					<br>
					
					<label>
						Максимальный размер статьи (0 для отключения) <input type="number" name="max_content_len" value="<?= isset($cdata["max_content_len"])? intval($cdata["max_content_len"]) : "0"; ?>" >
					</label>
					
					<br>
					<br>
					<label for="exclude_selectors"><b>Исключить блоки по селекторам:</b></label>
					<p>к примеру <code>#adsense</code> или <code>.share-links</code></code></p>
					<p>каждый селектор с новой строки</p>
					<textarea id="exclude_selectors" style="width:40%; height: 80px;" wrap="off" name="exclude_selectors"><?php
					echo isset($cdata["exclude_selectors"]) ? $cdata["exclude_selectors"] : ".hidearea
noscript
.breadcrumb
.bread
.hidden
noindex
#respond
.comment
code
.post-view-views
.views
.native-block
.reply
.ss_cats
.toc
.table-of-contents
.social-likes
.social
.hide
.adsbygoogle
.hidebody";
					?></textarea>
					<br>
					<br>
					<label for="exclude_replace"><b>Исключить/Заменить по регулярному выражению:</b></label>
					<p>к примеру <code>&lt;a[^>]+?&gt;([^&lt;]+?)&lt;\/a&gt;[|]$1</code> - заменяет ссылки на текст, который находится в этих ссылках, или <code>&lt;a class='share-button'&gt;поделиться&lt;/a&gt;</code></p>
					<p>Если надо не просто исключить но и заменить(preg_replace) то пропишите после регулярки последовательность <code>[|]</code> и строку на которую надо заменить выражение. Так-же допускается выборка групп из регулярки, к примеру $1 выведет то, что находится в регулярвк в первых () скобочках.</p>
					<p>Каждое регулярное выражение - с новой строки</p>
					<textarea id="exclude_replace" style="width:40%; height: 80px;" wrap="off" name="exclude_replace"><?php
						echo isset($cdata["exclude_replace"]) ? stripslashes($cdata["exclude_replace"]) : "<div\s+id=['\"]down['\"]>(.+?)<\/div>[|]\r\nЗагрузка\.+*[|]\r\nВидео[|]\r\n©[|]\r\n\S+\.ru[|]";
					?></textarea>
					<p>
					<label>
						<input type="checkbox" <?= isset($cdata["extract"]) && $cdata["extract"] == "1" ? "checked" : ""; ?> name="extract" value="1" > Извлекать только блоки с заголовками
					</label>
					</p>
					<p>
					<label>
						<input type="checkbox" <?= !isset($cdata["noiframe"]) || $cdata["noiframe"] == "1" ? "checked" : ""; ?> name="noiframe" value="1" > без iframe
					</label>
					</p>
					<p>
					<label>
						<input type="checkbox" <?= isset($cdata["noimages"]) && $cdata["noimages"] == "1" ? "checked" : ""; ?> name="noimages" value="1" > Вообще без картинок
					</label>
					</p>
					<p>
					<label>
						<input type="checkbox" <?= !isset($cdata["add_toc"]) || $cdata["add_toc"] == "1" ? "checked" : ""; ?> name="add_toc" value="1" > добавлять оглавление
					</label>
					</p>
					<p>
					<label>
						<input type="radio" <?= !isset($cdata["add_backlink"]) || $cdata["add_backlink"] == "2" || $cdata["add_backlink"] == "0" ? "checked" : ""; ?> name="add_backlink" value="2" > не добавлять ссылку на оригинал
					</label>
					</p>
					<p>
					<label>
						<input type="radio" <?= isset($cdata["add_backlink"]) && $cdata["add_backlink"] == "1" ? "checked" : ""; ?> name="add_backlink" value="1" > добавлять прямую ссылку на оригинал
					</label>
					</p>
					<p>
					<label>
						<input type="radio" <?= isset($cdata["add_backlink"]) && $cdata["add_backlink"] == "3" ? "checked" : ""; ?> name="add_backlink" value="3" > добавлять замаскированную ссылку на оригинал
					</label>
					</p>



				</td>
			</tr>
		</tbody>
	</table>
	<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row">Разное:</th>
			<td class='top_al'>
				<p>Статус добавляемых материалов:</p>
				<select name='status' id='aft_status'>
					<option value="publish" <?php echo (!isset($opt['publ_mode']) || $opt['publ_mode'] == 'publish') ? 'selected' : ''; ?>>Опубликовано</option>
					<option value="pending" <?php echo (isset($opt['publ_mode']) && $opt['publ_mode'] == 'pending') ? 'selected' : ''; ?>>На утверждении</option>
				</select>
			</td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Обработка:<br><small>Прежде чем тыкать тут кнопки - проверьте, все ли поля у вас заполнены.</small></th>
			<td colspan="2">
				Протестировать работу, обработав <input id="aft_test_count" type="number" name="tcount" min="1" step="1" value ="1"/> ссылок.<br /> 
				<div class='aft_attention'>
					<b>Внимание:</b>
					<p>Если вы укажите слишком большое число, то ваш хостинг, скорее всего, упадет. Будьте осторожны.</p>
					<p>После нажатия кнопки будет выведено окно с информацией, содержащей результаты работы парсера.</p>
				</div>
				<p class="submit"><a id='test_parse' class="button-primary" href="#"><?php _e('Сохранить и тестировать') ?></a></p>
				<div>
				<hr>
				<b>Парсить все страницы.</b><br>
				<div class='aft_attention'>
					<b>Информация:</b>
					<p>В результате работы парсера будут добавлены новые материалы.</p>
					<br />
					<p>Чтобы сберечь ресурсы вашего хостинга, парсер обрабатывает по 1 материалу в секунду(максимум).</p>
					<p>Процесс может быть длительным, особенно если ссылок много.</p>
					<p>К примеру: парсинг 1000 материалов занимает от 17 до 30 минут(зависит от скорости сетевого соединения).</p>
					<br />
				</div>
				<p class="submit" style="position: relative;"><a id='parse_all' style="width:100%;text-align:center;" class="button-primary" href="#"><?php _e('Бабло!') ?></a></p>
				</div>
			</td>
		</tr>
		<?php if($id != NULL){ ?> 
			<input type="hidden" name="pid" value="<?php echo $id; ?>">
		<?php } ?>
		<tr valign="top">
			<th scope="row">Отложенный парсинг:</small></th>
			<td colspan="2">
				<p>Название парсера: <input id="aft_ptitle" type="text" name="title" placeholder="Это поле обязательно!" value="<?php echo !empty($title)? $title : "site_parser".$pcount; ?>" />&nbsp;
				<p>Количество ссылок, обрабатываемое за 1 проход: <input id="aft_links_pd" name="nl" type="number" min="1" step="1" max="10" value ="<?php echo ($opt != null) ? $opt['num_links'] : '1'; ?>"/></p>
				
				<p class="submit"><a id="aft_save_parser" class="button-primary" href="#"><?php _e('Сохранить для отложенного парсинга'); ?></a></p>
				<div class='aft_attention'>
					<b>Инфо:</b>
					<p><i>Отложенный парсинг</i> - это процесс обработки страниц, растянутый на определенный временной промежуток.</p> 
					<p>Каждый день парсер будет обрабатывать указанное число ссылок, создавая таким образом видимость обновления сайта.</p>
					<p>Этот метод наиболее практичен и экономит ресурсы сервера.</p>
				</div>
			<td>
		</tr>
		
		<?php
			//Т.к. код, отправленный в поле macro выполняется нативно через eval, нам приходится прибегать к особым мерам безопасности. К примеру тут у нас два проверочных поля.
			//Одно - стандартное, для форм, второе - для ajax
			//Инъекций и прочей хуеты навроде eval'а можно не бояться, т.к. без их валидации ни один из обработчиков ajax не работает.
			wp_nonce_field('new_site_parser_action','nonce_cheker'); 
			$ajax_nonce = wp_create_nonce( "aft_parser_key" );
		?>
		</tbody>
	</table>
	</form>
	</div>

</div>

<script type="text/javascript">
	var ajax_nonce_field = '<?php echo $ajax_nonce; ?>';
</script>
<!-- end -->