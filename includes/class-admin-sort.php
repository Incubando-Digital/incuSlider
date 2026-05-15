<?php
/**
 * incuSlider — Drag & drop sort en el listado.
 *
 * Aprovecha la columna `menu_order` y jQuery UI Sortable.
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Admin_Sort {

    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_ajax_incuslider_reorder', array(__CLASS__, 'ajax_reorder'));
        add_action('pre_get_posts', array(__CLASS__, 'default_order'));
    }

    public static function enqueue($hook) {
        if ($hook !== 'edit.php') return;
        if (($_GET['post_type'] ?? '') !== incuSlider_CPT::POST_TYPE) return;
        wp_enqueue_script('jquery-ui-sortable');
        wp_add_inline_script('jquery-ui-sortable', self::inline_js(), 'after');
        wp_add_inline_style('common', '
            #the-list tr.ui-sortable-helper { background: #f6fafb; }
            #the-list tr td { cursor: move; }
            #the-list tr td:not(:first-child):not(.column-incu_thumb) { cursor: default; }
        ');
    }

    public static function inline_js() {
        $nonce = wp_create_nonce('incuslider_reorder');
        $ajax_url = admin_url('admin-ajax.php');
        return "
        jQuery(function(\$){
            \$('#the-list').sortable({
                handle: '.column-incu_thumb',
                placeholder: 'sortable-placeholder',
                helper: function(e, tr) {
                    var \$originals = tr.children();
                    var \$helper = tr.clone();
                    \$helper.children().each(function(i){ \$(this).width(\$originals.eq(i).outerWidth()); });
                    return \$helper;
                },
                update: function(e, ui) {
                    var ids = [];
                    \$('#the-list tr').each(function(){ ids.push(\$(this).attr('id').replace('post-','')); });
                    \$.post('{$ajax_url}', { action: 'incuslider_reorder', nonce: '{$nonce}', order: ids });
                }
            });
        });
        ";
    }

    public static function ajax_reorder() {
        check_ajax_referer('incuslider_reorder', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('forbidden');
        $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : array();
        foreach ($order as $i => $pid) {
            wp_update_post(array('ID' => $pid, 'menu_order' => $i + 1));
        }
        wp_send_json_success(array('count' => count($order)));
    }

    public static function default_order($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if (($_GET['post_type'] ?? '') !== incuSlider_CPT::POST_TYPE) return;
        if (empty($_GET['orderby'])) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }
}
