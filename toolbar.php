<?php
/**
 * Пункты в admin toolbar
 * @since 1.7.9
 */

function custom_toolbar_link($wp_admin_bar) {
    $args = array(
        'id' => 'aftpmenu',
        'title' => '<img style="width: 17px;vertical-align: middle; display: inline-block;" src="'.AFTPARSER__PLUGIN_URL.'img/admin_icon.png" alt=""> Aftparser', 
        'href'   => admin_url("admin.php?page=aft_parser_index"),
        'meta' => array(
            'class' => 'aftpmenu', 
            'title' => 'Aftparser'
            )
    );
    $wp_admin_bar->add_node($args);

    $args = array(
        'id' => 'aftpmenu-sites',
        'title' => 'Парсер ссылок', 
        'href' => admin_url("admin.php?page=aft_parser_plinks_parser"),
        'parent' => 'aftpmenu', 
        'meta' => array(
            'class' => 'aftpmenu-sites', 
            'title' => 'Парсер ссылок'
            )
    );
    $wp_admin_bar->add_node($args);

    $args = array(
        'id' => 'aftpmenu-settings',
        'title' => 'Дополнительно', 
        'href' => admin_url("admin.php?page=aft_parser_settings"),
        'parent' => 'aftpmenu', 
        'meta' => array(
            'class' => 'aftpmenu-settings', 
            'title' => 'Дополнительно'
            )
    );
    $wp_admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'custom_toolbar_link', 999);