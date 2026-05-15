<?php
/**
 * incuSlider — Bulk actions custom (duplicar slide).
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Admin_Bulk {

    public static function init() {
        $pt = incuSlider_CPT::POST_TYPE;
        add_filter("bulk_actions-edit-{$pt}", array(__CLASS__, 'register'));
        add_filter("handle_bulk_actions-edit-{$pt}", array(__CLASS__, 'handle'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'notices'));

        // Row action: duplicar individual
        add_filter("post_row_actions", array(__CLASS__, 'row_action'), 10, 2);
        add_action('admin_post_incuslider_duplicate', array(__CLASS__, 'duplicate_handler'));
    }

    public static function register($actions) {
        $actions['incuslider_duplicate'] = __('Duplicar', 'incuslider');
        return $actions;
    }

    public static function handle($redirect_url, $action, $post_ids) {
        if ($action !== 'incuslider_duplicate') return $redirect_url;
        $done = 0;
        foreach ($post_ids as $pid) {
            $new_id = self::duplicate_post($pid);
            if ($new_id) $done++;
        }
        return add_query_arg('incuslider_duplicated', $done, $redirect_url);
    }

    public static function row_action($actions, $post) {
        if ($post->post_type !== incuSlider_CPT::POST_TYPE) return $actions;
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=incuslider_duplicate&post=' . $post->ID),
            'incuslider_duplicate_' . $post->ID
        );
        $actions['incuslider_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicar', 'incuslider') . '</a>';
        return $actions;
    }

    public static function duplicate_handler() {
        $pid = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$pid || !current_user_can('edit_post', $pid)) wp_die('Sin permiso');
        check_admin_referer('incuslider_duplicate_' . $pid);
        $new_id = self::duplicate_post($pid);
        wp_safe_redirect(admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&incuslider_duplicated=' . ($new_id ? '1' : '0')));
        exit;
    }

    public static function duplicate_post($post_id) {
        $orig = get_post($post_id);
        if (!$orig || $orig->post_type !== incuSlider_CPT::POST_TYPE) return false;
        $new_id = wp_insert_post(array(
            'post_type'   => incuSlider_CPT::POST_TYPE,
            'post_status' => 'draft',
            'post_title'  => $orig->post_title . ' ' . __('(copia)', 'incuslider'),
            'menu_order'  => $orig->menu_order,
        ));
        if (!$new_id || is_wp_error($new_id)) return false;

        // Copiar postmeta y thumbnail
        $thumb = get_post_thumbnail_id($post_id);
        if ($thumb) set_post_thumbnail($new_id, $thumb);

        $metas = get_post_meta($post_id);
        foreach ($metas as $key => $vals) {
            if (in_array($key, array('_edit_lock', '_edit_last'), true)) continue;
            foreach ($vals as $v) {
                $v = maybe_unserialize($v);
                add_post_meta($new_id, $key, $v);
            }
        }
        return $new_id;
    }

    public static function notices() {
        if (!isset($_GET['incuslider_duplicated'])) return;
        $n = (int) $_GET['incuslider_duplicated'];
        $cls = $n > 0 ? 'updated' : 'error';
        echo '<div class="notice notice-' . esc_attr($cls) . ' is-dismissible"><p>'
            . sprintf(esc_html(_n('%d slide duplicada.', '%d slides duplicadas.', $n, 'incuslider')), $n)
            . '</p></div>';
    }
}
