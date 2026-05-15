<?php
/**
 * incuSlider — Admin metabox con UI completa (media picker, link control,
 * select2, datepicker).
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Metabox {

    const NONCE_ACTION = 'incu_slider_save';
    const NONCE_NAME   = 'incu_slider_nonce';

    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'register'));
        add_action('save_post_' . incuSlider_CPT::POST_TYPE, array(__CLASS__, 'save'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets($hook) {
        global $post;
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
        if (!$post || $post->post_type !== incuSlider_CPT::POST_TYPE) return;

        // Media library
        wp_enqueue_media();
        // Select2 (WP admin lo trae como `selectWoo` con prefijo `select2` también disponible)
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

        // Select2 CDN (WP admin trae versión legacy en algunos contextos, mejor garantizar)
        wp_enqueue_style('incuslider-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('incuslider-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        wp_enqueue_style('incuslider-admin', INCUSLIDER_URL . 'assets/css/metabox.css', array(), INCUSLIDER_VERSION);
        wp_enqueue_script('incuslider-admin', INCUSLIDER_URL . 'assets/js/metabox.js', array('jquery', 'incuslider-select2', 'jquery-ui-datepicker'), INCUSLIDER_VERSION, true);

        wp_localize_script('incuslider-admin', 'incuSliderL10n', array(
            'selectImage'      => __('Seleccionar imagen', 'incuslider'),
            'useThisImage'     => __('Usar esta imagen', 'incuslider'),
            'remove'           => __('Quitar imagen', 'incuslider'),
            'chooseLink'       => __('Elegir link…', 'incuslider'),
            'searchPlaceholder'=> __('Buscar páginas o pegar URL externa', 'incuslider'),
        ));
    }

    public static function register() {
        add_meta_box('incu-slider-config', __('incuSlider — Configuración', 'incuslider'),
            array(__CLASS__, 'render'), incuSlider_CPT::POST_TYPE, 'normal', 'high');
    }

    public static function render($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $image_mobile_id = (int) get_post_meta($post->ID, '_incu_image_mobile', true);
        $link_url        = get_post_meta($post->ID, '_incu_link_url', true);
        $link_target     = get_post_meta($post->ID, '_incu_link_target', true) ?: '_self';
        $heading         = get_post_meta($post->ID, '_incu_heading', true);
        $subheading      = get_post_meta($post->ID, '_incu_subheading', true);
        $date_from       = get_post_meta($post->ID, '_incu_date_from', true);
        $date_to         = get_post_meta($post->ID, '_incu_date_to', true);

        $mobile_thumb = $image_mobile_id ? wp_get_attachment_image_url($image_mobile_id, 'medium') : '';
        $mobile_filename = $image_mobile_id ? basename(get_attached_file($image_mobile_id) ?: '') : '';

        include INCUSLIDER_DIR . 'views/metabox.php';
    }

    public static function save($post_id, $post) {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $simple = array(
            '_incu_image_mobile' => isset($_POST['_incu_image_mobile']) ? absint($_POST['_incu_image_mobile']) : 0,
            '_incu_link_url'     => isset($_POST['_incu_link_url'])     ? esc_url_raw(wp_unslash($_POST['_incu_link_url'])) : '',
            '_incu_link_target'  => isset($_POST['_incu_link_target']) && $_POST['_incu_link_target'] === '_blank' ? '_blank' : '_self',
            '_incu_heading'      => isset($_POST['_incu_heading'])      ? sanitize_text_field(wp_unslash($_POST['_incu_heading'])) : '',
            '_incu_subheading'   => isset($_POST['_incu_subheading'])   ? sanitize_text_field(wp_unslash($_POST['_incu_subheading'])) : '',
            '_incu_date_from'    => isset($_POST['_incu_date_from'])    ? sanitize_text_field($_POST['_incu_date_from']) : '',
            '_incu_date_to'      => isset($_POST['_incu_date_to'])      ? sanitize_text_field($_POST['_incu_date_to']) : '',
        );
        foreach ($simple as $k => $v) update_post_meta($post_id, $k, $v);

        $axes = incuSlider_Axes::get_all();
        foreach ($axes as $axis_id => $axis) {
            $meta_key = incuSlider_Axes::meta_key_for($axis_id);
            $all_flag = !empty($_POST['_incu_filter_all_' . $axis_id]);
            $raw = isset($_POST['_incu_filter_' . $axis_id]) && is_array($_POST['_incu_filter_' . $axis_id])
                ? array_map('sanitize_text_field', wp_unslash($_POST['_incu_filter_' . $axis_id]))
                : array();

            if ($all_flag || empty($raw)) update_post_meta($post_id, $meta_key, array('all'));
            else                          update_post_meta($post_id, $meta_key, array_values(array_unique($raw)));
        }
    }
}
