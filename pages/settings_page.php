<div class="wrap">
<?php
/**
 * 
 * Страница настроек модуля
 * 
 */

$time_interval = get_option("aft_time_interval");
if($time_interval == false)
	add_option("aft_time_interval", 43200, '', 'yes');	//43200 - число секунд в 1-х сутках

$info_msg = "";

$cron_secret = get_option("ap_cron_secret");
if($cron_secret == false){
	// Если секретное слово для запуска CRON не задано - задаем его
	$cron_secret = h_rand_str(7);
	update_option("ap_cron_secret", $cron_secret);
}

if (!empty($_POST['nonce_cheker']) && wp_verify_nonce($_POST['nonce_cheker'],'settings_page')){
	if($_POST['action'] == "save"){
		$time_interval = intval($_POST['timing']);
		
		update_option( "aft_time_interval", $time_interval );
		$info_msg .= 'Настройки обновлены.';
		
	}
	if($_POST['action'] == "delete"){
		set_time_limit(0);
		// удаление всех постов, содержащих мета-кей(я пометил посты, которые добавляет плагин, специальным ключом ro_mark)
		$the_query = new WP_Query( "post_type=post&meta_key=ap_mark&order=ASC&posts_per_page=-1" );
		
		if ( $the_query->have_posts() ) {
			
			$info_msg .= '<ol>';
			while ( $the_query->have_posts() ) { $the_query->the_post();
				global $wpdb;
				global $wp_rewrite;
				wp_delete_post(get_the_ID(), true);
				$info_msg .= '<li> Пост: ' . get_the_title() . ' удален. </li>';
			}
			$info_msg .= '</ul>';
		} else {
			$info_msg .= "<p>Посты не найдены</p>";
		}
		wp_reset_postdata();
	}
}
?>

	<h2>Дополнительно</h2>
	
	<div id="test_info" class="aft_info" <?= $info_msg == "" ? "style='display:none;'" : "" ?> ><?= $info_msg; ?></div>

	<form method="post">
		<table class="form-table">
			<tr valign="top">
				<th>Cron:<br><small>Как часто парсер будет выполнять обработку rss лент и ссылок?</small></th>
				<td class='top_al'>
					<p>Cron будет срабатывать:
						<select name='timing' id='aft_timing'>
							<option value="86400" <?php echo (isset($time_interval) && $time_interval == 86400) ? "selected" : ""; ?>>Раз в сутки</option>
							<option value="43200" <?php echo ($time_interval == false || $time_interval == 43200) ? "selected" : ""; ?>>Раз в 12 часов</option>
							<option value="21600" <?php echo (isset($time_interval) && $time_interval == 21600) ? "selected" : ""; ?>>Раз в 6 часов</option>
							<option value="10800" <?php echo (isset($time_interval) && $time_interval == 10800) ? "selected" : ""; ?>>Раз в 3 часа</option>
							<option value="3600" <?php echo (isset($time_interval) && $time_interval == 3600) ? "selected" : ""; ?>>Раз в час</option>
							<option value="60" <?php echo (isset($time_interval) && $time_interval == 60) ? "selected" : ""; ?>>Раз в минуту</option>
						</select>
					</p>
					<p>В данный момент установлено значение: 
					<?php
						switch($time_interval){
							case 86400	: echo "Раз в сутки"; 		break;
							case 43200	: echo "Раз в 12 часов"; 	break;
							case 21600	: echo "Раз в 6 часов"; 	break;
							case 10800	: echo "Раз в 3 часа"; 		break;
							case 3600	: echo "Раз в час"; 		break;
							case 60		: echo "Раз в минуту"; 		break;
						}
					?>.</p>
				</td>
			</tr>
			<tr valign="top">
				<th>Системный CRON</th>
				<td>
					<div class="box">
						<h4>Интеграция с системным Cron'ом:</h4>
						<p>
							<label for="cron_secret">Секретный ключ для запуска CRON(Для каждого парсера - уникален. Тем не менее вы можете его изменить)</label>
							<input type="text" id="cron_secret" style="display: block;" class="regular-text" name="cron_secret"
							 placeholder="secret" 
							 value="<?php 
							 	echo ($cron_secret != false) ? $cron_secret : h_rand_str(9); 
							 	?>">
						</p>
					</div>
					<div class="box">
						<h4>Информация по подключению системного Cron'а:</h4>
						<div class="aft_info">
							<p>Для того, чтобы материалы парсились каждый день, надо установить в системный CRON действие на вызов парсера по ключу.</p>
							<p>Для этого вбейте в консоль сервера команду:</p>

							<pre>$ crontab -e</pre>
							<p>После этого вы увидите перед собой в консоли открытый текстовый редактор nano/vim.</p>
							<p>Вставьте в конец файла эту строчку:</p>
							<pre>0 */3 * * * wget -qO /dev/null <?php echo get_site_url(); ?>?aftcron=<?php echo $cron_secret; ?></pre>
							<a class="button-primary" target="_blank" href="<?php echo get_site_url(); ?>?aftcron=<?php echo $cron_secret; ?>">Проверить работоспособность</a>

							<br>
							<br>
							<?php if(is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec')){
								?>
									<?php 
									$ntasks = shell_exec("crontab -l"); 
									if( $ntasks == null ){ ?>
										<h4 style="color:#000;">Автоустановка недоступна, установите крон вручную, через SSH.</h4>
									<?php }else{
									?>
									<h4>Внимание: черная магия!</h4>
									<p>Вы можете прописать CRON через консоль прямо из скрипта, если у пользователя, 
										под которым работает сервер, хватит на это прав. Просто нажмите кнопку, указанную ниже.</p>
									<b>Текущие задачи:</b> <small>[отредактируте из консоли SSH через команду <code>crontab -e</code>]</small>
									
									<pre class="crontasks"><?php echo $ntasks; ?></pre>

									<p>
										<label for="time_for_dark_cron">
											Частота выполнения.
										</label><br>
										<small>Первая звездочка - минуты. Например: <code>*/5 * * * *</code> - раз в 5 минут.<br> Вторая звездочка - часы. Например: <code>0 */3 * * *</code> - раз в 3 часа. Дальше месяцы, дни недели и так далее. На это вообще не смотрите.</small>
										<input style="display: block; width: 40%;" type="text" class="regular-text" value="0 */3 * * *" id="time_for_dark_cron">
									</p>
									
									<p>
										<?php if( strpos($ntasks,get_site_url()."?aftcron=") === false){ ?>
											<a href="#" id="add_crontab" class="button button-primary">Добавить</a>
										<?php } else { ?>
											<a href="#" id="remove_crontab" class="button button-secondary">Удалить</a>
										<?php } ?>
									</p>
								<?php } ?>
							<?php } ?>
						</div>

					</div>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field('settings_page','nonce_cheker');  ?>
		<p class="submit"><button class="button-primary" type="submit" name="action" value="save">Сохранить</button>
		<button class="button-secondary" onclick="confirm('Это действие удалит ВСЕ материалы, которые добавил плагин. Вы действительно хотите это сделать?')" type="submit" name="action" value="delete">Удалить все спарсенные материалы</button></p>
	</form>
</div>