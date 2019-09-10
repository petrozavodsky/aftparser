/**
 * Основной файл скриптов
 */

jQuery(function($) {
	"use strict";
 	
	// Глобальные

	/**
	 * Дескриптор окна magnificPopup (используется чтобы это оконо закрывать произвольно из любой функции)
	 */
	var magnificPopup = jQuery.magnificPopup.instance;

	var links_list = ""; //список урл адресов
	// триггер для остановки сбощика ссылок в любой момент
	var map_page_trigger = true; //false - остановка батч операции map_page_query

	// текущая итерация парсера
	var parser_index = 0;

	// триггер для остановки парсинга в любой момент
	var parser_trigger = true;	//false - остановить работу парсера


	// Собирает все ссылки на сайте, проходя по всем страницам сайта подобно поисковому роботу
	function map_page_query(p_url, map_current_index) {

		$(".map-id").text("Сейчас обрабатывается: " + p_url);
		
		// Этот участок кода сбрасывает все переменные к первоначальному состоянию. срабатывает при повторном запуске сборщика ссылок
		if(!map_page_trigger){
			var old = $('#aft_links_list').val();
			$('#aft_links_list').val((old + "\n" + links_list).replace(/^\s+|\s+$/g, ''));
			links_list = "";
			$.magnificPopup.instance.close();
			map_page_trigger = true;
			return false;
		}

		var nonce_val = $('#nonce_cheker').val();

		$.post( ajaxurl, {
				action		 : 'map_page',
				security	 : ajax_nonce_field,
				nonce_cheker : nonce_val, 
				url			 : p_url,
			}, 
			function(data){
				//если спарсить страницу не удалось, выполняем перескок на следующий урл в списке уже собранных
				if( !data || data.trim().length < 3){
					// список ссылок
					var ll = links_list.split("\n");
					map_current_index += 1;

					// если номер последующей итерации превышает кол-во ссылок в списке - (то есть перескакивать некуда) - завершаем работу. Ибо сбор ссылок считается завершенным.
					if(map_current_index >= ll.length){
						var old = $('#aft_links_list').val();
						$('#aft_links_list').val((old + "\n" + links_list).replace(/^\s+|\s+$/g, ''));
						links_list = "";
						$.magnificPopup.instance.close();
						return true;
					}
					// если все нормально и ссылка существует
					var nurl = ll[map_current_index];
					// выполняем сбор ссылок на этой странице
					setTimeout(map_page_query(nurl, map_current_index), 2000);	//рекурсия-хуюрсия...
					return true;
				}

				// А результате выполнения AJAX запроса мы получаем список ссылок на странице(ссылки разделены через \n, пришлось отказаться от JSON в данном случае ибо функция parse_links - часть АПИ плагина доступная из макросов)
				var result_links = data.split(/\n/); // разбиваем

				for(var i=0; i< result_links.length; i++){

					if(result_links[i].length > 2 && links_list.indexOf(result_links[i]) == -1){ // проверка на уникальность ссылки
						if($('#aft_index_hash').prop("checked") == true){
							if(result_links[i].indexOf('#') == -1) links_list += "\n" + result_links[i];
						}else{
							links_list += "\n" + result_links[i];
						}
					}
				
				}
				var ll = links_list.trim().split("\n");	//надо было сделать links_list массивом с самого начала. Переделывать не буду. Потому как хоть ты ассив в строку переводи, хоть строку в масив - один хуй разницы нет.
				map_current_index += 1;
				
				$('#txt_links_p_info').val(links_list);
				
				if(map_current_index >= ll.length){	//как только все ссылки пройдены - вставлем полученный список в текстовое поле и скрываем окно лоадера.
					old = $('#aft_links_list').val();
					$('#aft_links_list').val((old + "\n" + links_list).replace(/^\s+|\s+$/g, ''));
					links_list = "";
					$.magnificPopup.instance.close();
					return true;
				}
				var nurl = ll[map_current_index];
				setTimeout(map_page_query(nurl, map_current_index), 2000);	//рекурсия-хуюрсия...
				return true;
			}
		);
	}

	// Пакетная операция парсинга всех материалов по ссылкам
	function parse_all_query(str_links_list,category_id, publ_mode, macro_code){
		if(parser_trigger == false){
			$('.white_popup').append('<b>Процесс остановлен.</b>');
			parser_index = 0;
			parser_trigger = true;
			return true;
		}
		// убираем лишние пробелы(на всякий случай) и разбиваем по новой строке
		var links_list = str_links_list.replace(/^\s+|\s+$/g, '').split("\n");
		console.log(links_list);
		var lurl = $.trim(links_list[parser_index]);
		parser_index += 1;
		$.post(ajaxurl, {
				action			: 'parse_single',
				security		: ajax_nonce_field,
				nonce_cheker	: $('#nonce_cheker').val(), 
				url				: lurl,
				cat_id			: category_id,
				publication_mode: publ_mode,
				borders			: fragments,
				macro			: macro_code,
				fields		 	: $("#main-form").serialize(),
			}, 
			function(data){
				var jsonp = JSON.parse(data);
				
				$('#info_table').append("<tr>\
					<td style='min-width:350px; max-width:350px; text-align: left; padding-left: 20px; overflow: hidden;'>\
					<p>"+lurl+"</p></td><td style='background: goldenrod; vertical-align: middle !important; color: white; padding-left: 5px; padding-right: 5px;'>"+
					jsonp[1]+"</td></tr>");
				
				// если ссылки кончились
				if(parser_index >= links_list.length){
					$(".white_popup").append($('<div class="aft_info"><b>Готово.</b></div>'));
					parser_index = 0;
					return true;
				}else{
					// если ссылки еще есть - продолжаем обработку
					setTimeout(parse_all_query(str_links_list,category_id,publ_mode,macro_code), 2000);
				}
			}
		).fail(function(e){ // в случае ошибки - все равно парсинг продолжить
			// если ссылки кончились
			if(parser_index >= links_list.length){
				$(".white_popup").append($('<div class="aft_info"><b>Готово.</b></div>'));
				parser_index = 0;
				return true;
			}else{
				// если ссылки еще есть - продолжаем обработку
				setTimeout(parse_all_query(str_links_list,category_id,publ_mode,macro_code), 2000);
			}
		});
	}

	function ap_escapeHtml(str){
		if(!str) return "";
	    var map =
	    {
	        '&': '&amp;',
	        '<': '&lt;',
	        '>': '&gt;',
	        '"': '&quot;',
	        "'": '&#039;'
	    };
	    return str.replace(/[&<>"']/g, function(m) {return map[m];});
	}

	// генерирует описания фрагментов
	function get_fragment_desc(){
		$("#aborders").empty();
		var atext = "";
		for (var i = fragments.length - 1; i >= 0; i--) {
			if(!fragments[i].title || !fragments[i].top_border || !fragments[i].bottom_border) continue;
			//fragments.push({title:_title, top_border:_top_border, bottom_border:_bottom_border, mode: _mode});
			atext += "<p>Название: "+fragments[i].title+"<br>\
						<i>верх:</i> <code>"+ ap_escapeHtml(fragments[i].top_border)+"</code><br>\
						<i>низ:</i> <code>"+ap_escapeHtml(fragments[i].bottom_border)+"</code></p>";
		};
		$("#aborders").append(atext);
	}

	// генерирует имя фрагмента
	function gen_fname(iter){
		iter = iter || 0;
		console.log("123");
		var name = 'fragment_' + (fragments.length + 1 + iter);
		iter += 1;
		for (var i = fragments.length - 1; i >= 0; i--) {
			if(fragments[i].title && fragments[i].title == name) 
				return gen_fname(iter);
		};
		return name;
	}

    $(document).ready(function(){
    	// задаем базовые фрагменты
    	if($('#new_fragment_title').length > 0){
    		
    		$('#new_fragment_title').val(gen_fname());
    		$('#aborders').val(get_fragment_desc());
    	}

    	/* === Инициализация текстового редактора Ace Edit === */
		$('textarea.editor').each( function(index){
            var textarea = $(this);
         	var mode = $(this).attr('mode') || "php";
            var editDiv = $('<div>', {
                'position'	: 'absolute',
                //'width'  	: textarea.width(),
                //'height' 	: textarea.height(),
                'class' 	: textarea.attr('class')
            }).insertBefore(textarea);
            textarea.hide();
            var editor = ace.edit(editDiv[0]);
            editor.renderer.setShowGutter(true);
            editor.getSession().setValue(textarea.val());
            editor.getSession().setMode({path: "ace/mode/" + mode, inline: true});
            editor.setAutoScrollEditorIntoView(true);
            editor.setOptions({ /*maxLines: 25*/ });
            editor.setTheme("ace/theme/xcode");
            $(this).data('editor', editor);

            // copy back to textarea on form submit...
            editor.getSession().on('change', function () {
				textarea.val(editor.getSession().getValue());
			});
        });
	
		// Реверсия ссылок
		$("#btn_links_revert").on('click', function(event) {
			event.preventDefault();
			var ll = $('#aft_links_list').val();
			if(ll){
				var links_list = ll.replace(/^\s+|\s+$/g, '').split("\n");
				if(links_list) $('#aft_links_list').val(links_list.reverse().join("\n"));
			}
		});

    	// Аккордеон

    	if($('.pvariants').length != 0) 
	    	$(".pvariants").each(function(){ 
				var act = $(this).attr("nactive"); 
				$(this).accordion({
					collapsible: true, 
					heightStyle: "content",
					activate: function(event, ui) {

						if($(this).find(".editor").length > 0){
							var editor = $(this).find("textarea.editor").data('editor');
							editor.renderer.updateFull(); 
							editor.resize(); 
						}
					
					}
				}); 
				if(act){
					$(this).accordion( "option", "active", parseInt(act) );
				}
			});

	    /** Изменение режима парсера **/
	    if($("[name='use-borders']").length > 0 )
	    	$("[name='use-borders']").on('change', function(event) {
	    		if($(this).val() == "yes") {
    				$(".selectors-base").hide("fast");
					$(".easy-mode").hide("fast");
    				$(".borders-eval").fadeIn("fast");
    			} else if($(this).val() == "easy") {
					$(".borders-eval").hide("fast");
    				$(".selectors-base").hide("fast");
    				$(".easy-mode").fadeIn("fast");
				} else {
					$(".borders-eval").hide("fast");
					$(".easy-mode").hide("fast");
    				$(".selectors-base").fadeIn("fast");
    			}
	    	});


	    /** Универсальный парсер сайтов **/

	    //Парсим внутренние ссылки с сайта
		$('#btn_map_site').on('click',function(e){
			e.preventDefault();
			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<h3 class='mf-title'>Выполняется сбор ссылок, ждите.</h3>\
								<textarea id='txt_links_p_info' style='width: 100%; height: 400px; display: block; position: relative;'></textarea>\
								<br />\
								<p class='map-id'>нет ссылки</p>\
								<i>закрытие окна остановит операцию и добавит полученные ссылки в список</i>\
								</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {
						var site_url = $('#aft_map_url').val();
						map_page_trigger = true;
						if(site_url) setTimeout(map_page_query(site_url, 0), 1000); // запуск рекурсии
						else{
							alert("Урл не указан!");
							$.magnificPopup.instance.close();
						}
						return;
					},
					close: function() { // Закрытие окна автоматически отменяет операцию сора ссылок
						map_page_trigger = false;
						$(".white_popup").empty();
						return;
					}
				},
			});
		});

		//Парсим карту сайта
		$('#btn_parse_sitemap').on('click',function(e){
			e.preventDefault();
			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<h3>Выполняется индексация карты сайта, ждите.</h3>\
							</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {
						var sitemap_url = $('#aft_sitemap_url').val();
						var nonce_val = $('#nonce_cheker').val();
						$.post( ajaxurl, {
									action			: 	'parse_sitemap',
									security		:	ajax_nonce_field,
									nonce_cheker	:	nonce_val, 
									url				:	sitemap_url,
								}, 
								function(data){
									if( !data || data.trim().length < 3 ){
										$.magnificPopup.instance.close();
										return;
									}
									var old = $('#aft_links_list').val();
									$('#aft_links_list').val((old + "\n" + data).replace(/^\s+|\s+$/g, ''));
									$.magnificPopup.instance.close();
								}
							);
						return;
					},
					close: function() {
						$(".white_popup").empty();
						return;
					}
				},
			});
		});

		// Фильтрация-Редактирование списка ссылок с использованием PHP
		$('#btn_filter_links').on('click',function(e){
			e.preventDefault();
			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<b>Парсер приступил к фильтрации ссылок.</b>\
							</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {
						var links_list = $('#aft_links_list').val();
						var code = $('#aft_txt_filter_ex').val(); 
						var nonce_val = $('#nonce_cheker').val();
						if(links_list.replace(/^\s+|\s+$/g, '') == "" || links_list.length < 3) return false;
						
						$.post( ajaxurl, {
								action		 : 'filter_links',
								security	 : ajax_nonce_field,
								nonce_cheker : nonce_val, 
								links		 : links_list,
								filter		 : code
							}, 
							function(data){
								var jsonp = JSON.parse(data);
								if(jsonp[1] == "" || jsonp[1].length < 3){ 
									$(".white_popup").append('<p>Функция фильтрации вернула пустую строку. Пожалуйста проверьте ваш код на валидность.</p>');
									return false;
								}
								if(jsonp[0] == "error"){
									$(".white_popup").append('<p>'+jsonp[1]+'</p>');
									return false;
								}
								$('#aft_links_list').val(jsonp[1]);
								count_links();
								$.magnificPopup.instance.close();
							});
						return;
					},
					close: function() {
						$(".white_popup").empty();
						return;
					}
				},
			});
		});

		// Тык на кнопку простой фильтрации... 
		$('#btn_efilter_links').on('click',function(e){
			e.preventDefault();
			var lcont = $('#aft_link_cont').val();		//строка фрагментов, которые содержит ссылка
			var lncont = $('#aft_link_not_cont').val();	//строка фрагментов, которые ссылка не должна содержать
			var ll = $('#aft_links_list').val();		//список ссылок
			
			if(!ll || ll.length < 3) return false;		//ибо нехуй
			
			var rez = "";
			var links  = ll.split(/\n/);	//разбиваем ссылки на массив

			//убираем дубли
			var links = links.filter(function (e, i, arr) {
				return arr.lastIndexOf(e) === i;
			});

			if(lcont) lcont  = lcont.replace(/\r/g,"").split(/\n/); //разбираем фильтры
			if(lncont) lncont = lncont.replace(/\r/g,"").split(/\n/);
			
			//проходим по массиву и проверяем каждую ссылку на соответствие фильтрам
			for(var j = 0; j < links.length; j++){ //двойной цикл, эого я стараюсь не делать, обычно... а хотя - похуй. Ебал я в рот ваши компы, хехе.
				var current_link = links[j];
				
				var fcontains = false;
				var fnot_cont = true;
				
				if(lcont) for(var k = 0; k < lcont.length; k++){
					var filter = lcont[k];
					if(current_link.indexOf(filter) == -1) fcontains = false;
					else{ fcontains = true; break;}
				}
				
				if(lncont) for(var n = 0; n < lncont.length; n++){
					var filter = lncont[n];
					if(current_link.indexOf(filter) == -1) fnot_cont = true;
					else{ fnot_cont = false; break}
				}
				
				if(lcont && lncont){
					if(fnot_cont == true && fcontains == true) rez += current_link + "\n";
					if(fnot_cont == true && fcontains == false) rez += "";
					if(fnot_cont == false && fcontains == true) rez += current_link + "\n";
					if(fnot_cont == false && fcontains == false) rez += "";
				}
				if(lcont && !lncont)
					if(fcontains == true) rez += current_link + "\n";
				if(lncont && !lcont)
					if(fnot_cont == true) rez += current_link + "\n";
			}
			
			$('#aft_links_list').val(rez);
			count_links();
		});

		//Добавление новых границ
		$('#btn_add_fragment').on('click',function(e){
			e.preventDefault();
			var editor = $('#aft_macro').data('editor').getSession();
			var count = parseInt($( "#aft_fragments_list option" ).length);
			count++ ;
			var _title = $('#new_fragment_title').val();
			var _top_border = $('#new_fragment_top').val();
			var _bottom_border = $('#new_fragment_bottom').val();
			var _mode = $('#aft_f_role option:selected').val();

			for (var i = fragments.length - 1; i >= 0; i--) {
				if(_mode =="title" && fragments[i].mode == "title") _mode = "content";
			};

			if(!_title || !_bottom_border || !_top_border) return false;
			
			fragments.push({title:_title, top_border:_top_border, bottom_border:_bottom_border, mode: _mode});
			$('#aft_fragments_list').append('<option value="'+count+'"> Название: ' + _title + ' &amp; Режим использования: ' + _mode + '</option>');
			
			var last_text = editor.getValue();
			if(_mode == 'title'){
				last_text = last_text + "\n$res .= $this->set_title($this->get_border(\"" + _title + "\",$html));";
				$("#aft_f_role option[value='title']").remove();
			}else if(fragments.length <= 2)
				last_text = last_text + "\n$res .= $this->get_border(\"" + _title + "\",$html);";
			
			editor.setValue(last_text);
			get_fragment_desc();
			$('#new_fragment_title').val(gen_fname());
		});

		// Заготовки границ
		$("#btn_tmp_h1").on('click',function(e){
			e.preventDefault();
			$("textarea[name=n_top_border]").val("<h1>");
			$("textarea[name=n_bottom_border]").val("</h1>");
			return false;
		});

		$("#btn_tmp_title").on('click',function(e){
			e.preventDefault();
			$("textarea[name=n_top_border]").val("<title>");
			$("textarea[name=n_bottom_border]").val("</title>");
			return false;
		});

		//Удаление гранцы
		$('#btn_remove_fragment').on('click',function(e){
			e.preventDefault();

			var count = parseInt($( "#aft_fragments_list option" ).length);
			if(count>0){
				
				var editor = $('#aft_macro').data('editor').getSession();
				var etext = editor.getValue();
				var selected_item = parseInt($( "#aft_fragments_list option:selected" ).val());
				var s_index = parseInt($("#aft_fragments_list option:selected").index());
				if(!selected_item) return false;
				
				if(fragments[s_index].mode == 'title'){
					
					$("#aft_f_role").prepend("<option value='title'>Заголовок</option>");
					$('#aft_f_role option:selected').each(function(){
						if( $(this).val() == 'title' ) this.selected = true;
						else this.selected=false;
					});
					
					etext = etext.replace("\n$res .= $this->set_title($this->get_border(\"" + fragments[s_index].title + "\",$html));","");
				
				}else if(fragments.length <= 2){
					
					etext = etext.replace("\n$res .= $this->get_border(\"" + fragments[s_index].title + "\",$html);","");
				
				}
				
				editor.setValue(etext);

				fragments.splice(s_index, 1);
				$("#aft_fragments_list option[value='"+ selected_item +"']").remove();
				$('#new_fragment_title').val(gen_fname());
				get_fragment_desc();
			}
		});
		
		// Изменение настроек упрощенной обработки контента
		$("#eproc-cont input").change(function(){
			if(fragments.length == 0 || !fragments){
				alert("Пожалуйста задайте две границы(для заголовка и для контента)!");
				return false;
			}
			var editor = $('#aft_macro').data('editor').getSession();
			var text = editor.getValue();
			
			if($("input[name='chk_strip_tags']").is(":checked")){
				if(text.indexOf('clear_tags_from_trash') == -1)
					text += "\n$res = $this->clear_tags_from_trash($res);"
			}else{
				text = text.replace("$res = $this->clear_tags_from_trash($res);","");
			}
			
			if($("input[name='chk_strip_links']").is(":checked")){
				if(text.indexOf('remove_a_href') == -1)
					text += "\n$res = $this->remove_a_href($res);"
			}else{
				text = text.replace("$res = $this->remove_a_href($res);","");
			}

			if($("input[name='add_img']").is(":checked")){
				if(text.indexOf('get_one_goolge_image') == -1)
					text += '\n$res .= $this->get_one_goolge_image($this->cut_str($html,"<title>","</title>"));'; 
			}else{
				text = text.replace('$res .= $this->get_one_goolge_image($this->cut_str($html,"<title>","</title>"));','');
			}

			
			if($("input[name=chk_indent]").is(":checked")){
				if(text.indexOf('add_indent') == -1)
					text += "\n$res .= $this->add_indent();"
			}else{
				text = text.replace("$res .= $this->add_indent();","");
			}

			switch($('input[name=rb_proc_img]:checked').val()){
				case 'upload_img':
					if(text.indexOf("process_images") == -1)
						text += "\n$res = $this->process_images($res);"; 
					break;
				default:
					text = text.replace('$res = $this->process_images($res);','');
					break;
			}
			editor.setValue(text);
			return false;
		});

		//Пытаемся спарсить несколько ссылок с текущими настройками
		$('#test_parse').on('click',function(e){
			e.preventDefault();
			
			var count = parseInt($( "#aft_fragments_list option" ).length);
			if(count <= 0 && $('[name="use-borders"]').val() == "yes"){ alert("Добавьте хотя-бы одну границу"); return; }

			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<h3 class='mf-title'>Выполняется тестирование...</h3>\
							</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {
						var frm = $("#main-form");
						var nonce_val = $('#nonce_cheker').val();
						$.post(ajaxurl, {
								action		 : 'test_parse',
								security	 : ajax_nonce_field,
								nonce_cheker : nonce_val,
								fields		 : frm.serialize(),
								borders		 : fragments,
								auto_run	 : "off",
								update_link	 : $("#aft_link_updates_grab").val(),
								link_regex	 : $("#aft_link_regex").val()
							}
							
						).done(function(response){
							try{
								var jsonp = $.parseJSON(response);
								if(jsonp[0] == "success")
									$(".white_popup").append("<p><b>Результаты работы:</b> <a href='#' class='ro_show_html'>[переключить]</a></p>\
									<textarea style='width: 100%; height: 400px; display: block; position: relative;'>"+jsonp[1]+"</textarea>");
								else if(jsonp[1])
									$(".white_popup").append("<p><b>Ошибка!</b></p>"+jsonp[1]);
								else
									$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
							}catch(e){
								$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
							}
							}).fail(function(e){
								$(".white_popup").append($('<p>Неизвестная ошибка! Возможно не работает макрос.</p>'+e));
						});
						
						return;
					},
					close: function() {
						return;
					}
				},
			});
			return false;
		});
		
		// Парсинг всего, что имеется
		$('#parse_all').on('click',function(e){
			e.preventDefault();
			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<h3 class='mf-title'>Выполняется парсинг <small>для отмены операции - закройте окно</small>...</h3>\
							</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {

						parser_trigger = true;

						var links = $('#aft_links_list').val();

						var category_id = $("input:radio[name='cat_id']:checked").val();
						var publ_mode = $('#aft_status option:selected').val();
						var macro_code = "";
						if($("#aft_macro").length > 0){
							var macro_code = $('#aft_macro').data('editor').getSession().getValue();
						}
						if(!links || links == "") return false;
						$(".white_popup").append($('<table id="info_table"><tr><th style="width:350px; text-align: center;">Url</th><th style="padding-left: 10px; padding-right: 10px;">Результат обработки</th></tr></table><hr>'));
						
						parse_all_query(links,category_id,publ_mode,macro_code);
					},
					close: function() {
						parser_trigger = false;
						parser_index = 0;
					}
				},
			});
			return false;
		});
			
		function count_links(){
			var ll = $("#aft_links_list").val();

			if(ll != ""){
				ll = ll.split("\n");
				$("#link-list-count").text("кол-во ссылок: "+ll.length);
			}
		}

		if($("#aft_links_list").length > 0){
			count_links();
		}

		$("#aft_links_list").keyup(function(event) {
			count_links();
		});

		$('#aft_save_parser').on('click',function(e){
			e.preventDefault();
			$.magnificPopup.open({
				items: {
					src: $("<div class='white_popup'>\
								<h3 class='mf-title'>Выполняется сохранение</h3>\
							</div>"),
					type: 'inline',
				},
				callbacks: {
					open: function() {
						var frm = $("#main-form");
						var nonce_val = $('#nonce_cheker').val();
						$.post(ajaxurl, {
								action		 : 'save_parser',
								security	 : ajax_nonce_field,
								nonce_cheker : nonce_val, 
								fields		 : frm.serialize() + '&reset=1',
								borders		 : fragments,
								auto_run	 : "run"
							}, 
							function(response){
								var jsonp = JSON.parse(response);
								$(".white_popup").append($("<p>" + jsonp[1] + "</p>"));
								if(jsonp[0] == 'success'){
									var loc = window.location.href;
									loc = updateQueryStringParameter(loc, "parser_id", jsonp[2]);
									loc = updateQueryStringParameter(loc, "action", "edit");
									window.location = loc;
								}else{
									$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
								}
							}
						);
						return;
					},
					close: function() {
						return;
					}
				},
			});
			return false;
		});

		/** Простой парсер **/
		$("[name='pmode']").change(function(event) {
			$(".pinfo").each(function(index, el) {
				$(this).slideToggle("fast");
			});
		});
		
		/** RSS парсер **/

    	// Тестирование
		$('#test_parse_rss').on('click',function(e){
			e.preventDefault();
			mfOpen("test_parse_rss","Получение результатов тестирования. Ждите.", function(response){
				var jsonp = false;
				try{
				var jsonp = $.parseJSON(response);
				if(jsonp[0] == "success")
					$(".white_popup").append("<p><b>Результаты работы:</b></p><a href='#' class='ro_show_html'>[переключить]</a>\
					<textarea style='width: 100%; height: 400px; display: block; position: relative;'>"+jsonp[1]+"</textarea>");
				else if(jsonp[1])
					$(".white_popup").append("<p><b>Ошибка!</b></p>"+jsonp[1]);
				else
					$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
				}catch(e){
					$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
				}
			});
		});

		// смена режима отображения
		$("body").on("click",".ro_show_html",function(e){
			e.preventDefault();
			if(!$(".res-bdata").length){
				var html = $(".white_popup").find('textarea');
				if(html.length){
					$(".white_popup").append($("<div class='res-bdata'>"+html.text()+"</div>"));
					$(".white_popup").find("textarea").remove();
				} 
			}else {
				var html = $(".white_popup").find('.res-bdata');
				if(html.length){
					$(".white_popup").append($("<textarea style='width: 100%; height: 400px; display: block; position: relative;'>"+
												html.html()+"</textarea>"));
					$(".white_popup").find(".res-bdata").remove();
				} 
			}
		});
		
		function uniqueArray(arr) {
			var a = [];
			for (var i=0, l=arr.length; i<l; i++)
				if (a.indexOf(arr[i]) === -1 && arr[i] !== '')
					a.push(arr[i]);
			return a;
		}

    	// Убирает дубли
		$('#btn_links_unique').on('click',function(e){
			e.preventDefault();
			var ll = $('#aft_links_list').val();
			if(ll){
				var links_list = ll.replace(/^\s+|\s+$/g, '').split("\n");
				if(links_list) $('#aft_links_list').val(uniqueArray(links_list).join("\n"));
			}
			count_links();
		});

		// Убирает кириллицу
		$('#btn_links_remrus').on('click',function(e){
			e.preventDefault();
			var ll = $('#aft_links_list').val();
			if(ll){
				var links_list = ll.replace(/^\s+|\s+$/g, '').split("\n");
				if(links_list){
					let a = [];
					for (var i = 0; i < links_list.length; i++) {
						let mth = links_list[i].match(/[a-z]+/ig);
						if(mth && mth.length > 0)
							a.push(links_list[i]);
					}
					$('#aft_links_list').val(a.join("\n"));
				}
			}
			count_links();
		});

		// Убирает латинницу
		$('#btn_links_remlat').on('click',function(e){
			e.preventDefault();
			var ll = $('#aft_links_list').val();
			if(ll){
				var links_list = ll.replace(/^\s+|\s+$/g, '').split("\n");
				if(links_list){
					let a = [];
					for (var i = 0; i < links_list.length; i++) {
						let mth = links_list[i].match(/[a-z]+/ig);
						if(!mth)
							a.push(links_list[i]);
					}
					$('#aft_links_list').val(a.join("\n"));
				}
			}
			count_links();
		});
		
		//Парсит контент со всех ссылок
		$('#rssparse_all').on('click',function(e){
			e.preventDefault();
			mfOpen("parse_all_rss","Выполняется обработка rss ленты, ждите.", function(response){
						var jsonp = JSON.parse(response);
						if(jsonp[1])
							$(".white_popup").append($("<p>" + jsonp[1] + "</p>"));
						else
							$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
					});
		});

		// Сохранение парсера на отложенный парсинг
    	$('#aft_save_rssparser').on('click',function(e){
    		e.preventDefault();
			mfOpen("save_rss","Выполняется сохранение...", function(response){
							var jsonp = JSON.parse(response);
							if(!jsonp){
								$(".white_popup").append("<p><b>Ошибка!</b></p>"+response);
							}
							if(jsonp[1]){
								$(".white_popup").append($("<p>" + jsonp[1] + "</p>"));
							}
							if(jsonp[0] == 'success'){
								var loc = window.location.href;
								loc = updateQueryStringParameter(loc, "parser_id", jsonp[2]);
								loc = updateQueryStringParameter(loc, "action", "edit");
								console.log(loc);
								window.location = loc;
							}
					});
		});
    });
	
	/**
	 * Вспомогательные функции
	 */

	// Добавление GET параметров к url
	// https://stackoverflow.com/questions/5999118/add-or-update-query-string-parameter
	function updateQueryStringParameter(uri, key, value) {
		var re = new RegExp("([?|&])" + key + "=.*?(&|#|$)", "i");
		if (uri.match(re)) {
			return uri.replace(re, '$1' + key + "=" + value + '$2');
		} else {
			var hash =  '';
			if( uri.indexOf('#') !== -1 ){
			    hash = uri.replace(/.*#/, '#');
			    uri = uri.replace(/#.*/, '');
			}
			var separator = uri.indexOf('?') !== -1 ? "&" : "?";    
			return uri + separator + key + "=" + value + hash;
		}
	}

	// открытие magnificPopup
	function mfOpen(action, msg, open_resp, mclose){

		$.magnificPopup.open({
			items: {
				src: $("<div class='white_popup'>\
							<h3 class='mf-title'>"+msg+"</h3>\
						</div>"),
				type: 'inline',
			},
			callbacks: {
				open: function(){
					var frm = $("#main-form");
					var nonce_val = $('#nonce_cheker').val();
					
					var rss_url = $('#aft_rss_url').val().trim();
					if(!rss_url) return false;

					$.post(ajaxurl, {
							action		 : action,
							security	 : ajax_nonce_field,
							nonce_cheker : nonce_val, 
							fields		 : frm.serialize()
						}
					).done(open_resp).fail(function(e){
						$(".white_popup").append($('<p>Неизвестная ошибка! Возможно не работает макрос.</p>'+e));
					});
					return true;
				},
				close: mclose,
			},
		});
	}

	/* === Похерить задачу на CRON === */
	$('body').on('click', '#remove_crontab' , function(event) {
		event.preventDefault();
		var secret = $("#cron_secret").val() || "";

		$('#remove_crontab').attr("disabled","disabled");
		$('#remove_crontab').text('Ждите!');

		$.post(ajaxurl, {
				action	: 'aft_remove_crontab',
				data    : JSON.stringify({"secret":secret})
			}
		).done(function(res){
			var json = $.parseJSON(res);
			$("#test_info").empty();
			$("#test_info").append($("<div style='word-wrap: break-word; position:relative;'><pre>"+json["e"]+"</pre></div>"));
			$("#test_info").css('display','block');
			
			if(json["l"]) $(".crontasks").html(json["l"]);

			$('#remove_crontab').parent().append('<a href="#" id="add_crontab" class="button button-primary">Добавить</a>');
			$('#remove_crontab').remove();
			// up!
			$('html, body').animate({
				scrollTop: 0
			}, 500);
		});
	});			


	/* === Установить задачу на CRON === */
	$('body').on('click', '#add_crontab' , function(event) {
		event.preventDefault();
		var secret = $("#cron_secret").val() || "";
		var discret = $("#time_for_dark_cron").val() || "0 */3 * * *";

		$('#add_crontab').attr("disabled","disabled");
		$('#add_crontab').text('Ждите!');
		$.post(ajaxurl, {
				action	: 'aft_add_crontab',
				data    : JSON.stringify({"secret":secret,"discret":discret})
			}
		).done(function(res){
			var json = $.parseJSON(res);
			$("#test_info").empty();
			$("#test_info").append($("<div style='word-wrap: break-word; position:relative;'><pre>"+json["e"]+"</pre></div>"));
			$("#test_info").css('display','block');

			if(json["l"]) $(".crontasks").html(json["l"]);

	   		$('#add_crontab').parent().append('<a id="remove_crontab" href="#" class="button button-secondary">Удалить</a>');
	   		$('#add_crontab').remove();
	   		// up!
	   		$('html, body').animate({
	   			scrollTop: 0
	   		}, 500);
	   	});
	});

});