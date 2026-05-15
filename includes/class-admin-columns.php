<?php
/**
 * incuSlider — Columnas custom en el listado del admin.
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Admin_Columns {

    public static function init() {
        $pt = incuSlider_CPT::POST_TYPE;
        add_filter("manage_{$pt}_posts_columns", array(__CLASS__, 'register_columns'));
        add_action("manage_{$pt}_posts_custom_column", array(__CLASS__, 'render_column'), 10, 2);
        add_filter("manage_edit-{$pt}_sortable_columns", array(__CLASS__, 'sortable_columns'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_listing_styles'));
    }

    public static function enqueue_listing_styles($hook) {
        if ($hook !== 'edit.php') return;
        if (($_GET['post_type'] ?? '') !== incuSlider_CPT::POST_TYPE) return;
        wp_enqueue_style('incuslider-admin', INCUSLIDER_URL . 'assets/css/metabox.css', array(), INCUSLIDER_VERSION);
    }

    public static function register_columns($cols) {
        // Reordenar: checkbox, thumbnail, title, targeting, vigencia, orden, date
        $new = array();
        $new['cb']             = $cols['cb'] ?? '';
        $new['incu_thumb']     = __('Imagen', 'incuslider');
        $new['title']          = __('Título', 'incuslider');
        $new['incu_targeting'] = __('Targeting', 'incuslider');
        $new['incu_vigencia']  = __('Vigencia', 'incuslider');
        $new['menu_order']     = __('Orden', 'incuslider');
        $new['date']           = $cols['date'] ?? __('Fecha', 'incuslider');
        return $new;
    }

    public static function sortable_columns($cols) {
        $cols['menu_order'] = 'menu_order';
        return $cols;
    }

    public static function render_column($column, $post_id) {
        switch ($column) {
            case 'incu_thumb':
                $thumb = get_the_post_thumbnail($post_id, array(50, 50));
                if (!$thumb) {
                    $mobile_id = (int) get_post_meta($post_id, '_incu_image_mobile', true);
                    if ($mobile_id) $thumb = wp_get_attachment_image($mobile_id, array(50, 50));
                }
                echo $thumb ?: '<span style="color:#999">—</span>';
                break;

            case 'incu_targeting':
                $axes = incuSlider_Axes::get_all();
                $any = false;
                foreach ($axes as $axis_id => $axis_def) {
                    $values = get_post_meta($post_id, incuSlider_Axes::meta_key_for($axis_id), true);
                    // Normalizar: si está vacío string o array vacío → tratar como "all"
                    if (empty($values)) continue;
                    $values = is_array($values) ? $values : array($values);
                    // Limpiar valores vacíos dentro del array
                    $values = array_values(array_filter($values, function($v) { return $v !== '' && $v !== null; }));
                    if (empty($values) || in_array('all', $values, true)) continue;
                    $options = incuSlider_Axes::get_options($axis_id);
                    $labels = array();
                    foreach ($values as $v) {
                        $labels[] = $options[$v] ?? $v;
                    }
                    if (empty($labels)) continue;
                    $class = 'is-' . $axis_id;
                    printf('<span class="incuslider-tag %s"><strong>%s:</strong> %s</span> ',
                        esc_attr($class),
                        esc_html($axis_def['label']),
                        esc_html(implode(', ', $labels))
                    );
                    $any = true;
                }
                if (!$any) {
                    echo '<span class="incuslider-tag is-all">' . esc_html__('Todos', 'incuslider') . '</span>';
                }
                break;

            case 'incu_vigencia':
                $df = get_post_meta($post_id, '_incu_date_from', true);
                $dt = get_post_meta($post_id, '_incu_date_to', true);
                if (!$df && !$dt) {
                    echo '<span style="color:#999">—</span>';
                    break;
                }
                $now = current_time('Y-m-d H:i:s');
                $active = (!$df || $df <= $now) && (!$dt || $dt >= $now);
                $color = $active ? '#00611f' : '#a82318';
                echo '<small style="color:' . esc_attr($color) . '">';
                if ($df) echo esc_html__('Desde:', 'incuslider') . ' <code>' . esc_html($df) . '</code><br>';
                if ($dt) echo esc_html__('Hasta:', 'incuslider') . ' <code>' . esc_html($dt) . '</code>';
                echo $active ? '<br>✓ ' . esc_html__('Activa', 'incuslider') : '<br>✗ ' . esc_html__('Fuera de vigencia', 'incuslider');
                echo '</small>';
                break;

            case 'menu_order':
                $p = get_post($post_id);
                echo '<code>' . (int) $p->menu_order . '</code>';
                break;
        }
    }
}
