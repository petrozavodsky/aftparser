/**
 * 
 * Основной файл скриптов.
 * 
 */
(function ($, root, undefined) {
	$(function () {
		"use strict";

		$(document).ready(function(){

			/**
			 * Table of contents
			 */
			var toc_path = ".toc-wrapper .toc";
			if($(toc_path).length != 0){

				var toc_selectors = "h1,h2,h3,h4";//$(toc_path).attr("toc-selectors");
				if (typeof toc_selectors !== typeof undefined && toc_selectors !== false) {
					if($(".toc-wrapper .toc-title").length == 0) $(".toc-wrapper").addClass('toc-hm');
					
					$(".toc").toc({
						'container': $(".toc-wrapper").parent().get(0),
		    			'selectors': toc_selectors,
		    			'highlightOnScroll':false,
		    		});
				}
				$(".toc-title").on('click', function(event) {
					event.preventDefault();
					let tx = $(this).text();
					if(tx.indexOf("[Показать]") >= 0){
						tx = tx.replace("[Показать]","[Скрыть]");
					}else {
						tx = tx.replace("[Скрыть]","[Показать]");
					}
					$(this).text(tx);
					$(".toc-wrapper").toggleClass('toc-hm');
				});
			}

			$('span[data-go]').replaceWith(function() {
			    let link = $(this).attr('data-go');
			    let text = $(this).text();
			    return '<a href="' + link + '" target="_blank">' + text + '</a>';
			});
	    	
	    	$.post(ajaxurl, {	// Асинхронно выполняем запуск отложенных операций, дабы не травмировать пользователя долой загрузкой страницы.
					action			: 	'aft_docron',
					data			: 	'run'
			});
			
		});

		
	});
})(jQuery, this);