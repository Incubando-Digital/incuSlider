<?php
/**
 * incuSlider — "Vista previa por contexto" modal.
 *
 * Permite simular qué slides verá un user con rank/país/role específicos
 * sin tener que cambiar de cuenta.
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Admin_Preview {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu_page'), 11);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_ajax_incuslider_preview_context', array(__CLASS__, 'ajax_preview'));
    }

    public static function register_menu_page() {
        add_submenu_page(
            'edit.php?post_type=' . incuSlider_CPT::POST_TYPE,
            __('Vista previa', 'incuslider'),
            __('Vista previa', 'incuslider'),
            'edit_posts',
            'incuslider-preview',
            array(__CLASS__, 'render_page')
        );
    }

    public static function enqueue($hook) {
        if (strpos($hook, 'incuslider-preview') === false) return;
        wp_enqueue_style('incuslider-admin', INCUSLIDER_URL . 'assets/css/metabox.css', array(), INCUSLIDER_VERSION);
    }

    public static function render_page() {
        $axes = incuSlider_Axes::get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('incuSlider — Vista previa por contexto', 'incuslider'); ?></h1>
            <p class="description">
                <?php esc_html_e('Simulá qué slides verá un usuario con un contexto específico (rank/país/rol). No necesitás cambiar de cuenta para validar visibility.', 'incuslider'); ?>
            </p>

            <form id="incuslider-preview-form" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;max-width:700px">
                <?php wp_nonce_field('incuslider_preview', 'incuslider_preview_nonce'); ?>
                <table class="form-table">
                    <?php foreach ($axes as $axis_id => $axis):
                        $opts = incuSlider_Axes::get_options($axis_id);
                    ?>
                        <tr>
                            <th><label><?php echo esc_html($axis['label']); ?></label></th>
                            <td>
                                <select name="ctx[<?php echo esc_attr($axis_id); ?>]" style="min-width:280px">
                                    <option value="">— <?php esc_html_e('Sin valor (anónimo o no setado)', 'incuslider'); ?> —</option>
                                    <?php foreach ($opts as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p>
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-visibility" style="margin-top:3px"></span>
                        <?php esc_html_e('Simular contexto', 'incuslider'); ?>
                    </button>
                </p>
            </form>

            <div id="incuslider-preview-results" style="margin-top:30px"></div>
        </div>

        <script>
        jQuery(function($){
            $('#incuslider-preview-form').on('submit', function(e){
                e.preventDefault();
                var data = $(this).serialize() + '&action=incuslider_preview_context';
                $('#incuslider-preview-results').html('<p><em>Cargando…</em></p>');
                $.post(ajaxurl, data, function(res){
                    if (!res.success) {
                        $('#incuslider-preview-results').html('<div class="notice notice-error"><p>'+ (res.data || 'Error') +'</p></div>');
                        return;
                    }
                    var slides = res.data.slides;
                    var html = '<h2>Slides que se mostrarían (' + slides.length + ')</h2>';
                    if (slides.length === 0) {
                        html += '<div class="notice notice-warning inline"><p>Ninguna slide matchea ese contexto. Revisar visibility de las slides.</p></div>';
                    } else {
                        html += '<table class="widefat striped"><thead><tr><th width="100">Imagen</th><th>Título</th><th>Targeting</th><th>Link</th></tr></thead><tbody>';
                        slides.forEach(function(s){
                            html += '<tr>' +
                                '<td>' + (s.thumb || '—') + '</td>' +
                                '<td><strong>' + s.title + '</strong></td>' +
                                '<td>' + s.targeting + '</td>' +
                                '<td><a href="' + s.link + '" target="_blank">' + (s.link || '—') + '</a></td>' +
                                '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    $('#incuslider-preview-results').html(html);
                });
            });
        });
        </script>
        <?php
    }

    public static function ajax_preview() {
        check_ajax_referer('incuslider_preview', 'incuslider_preview_nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('forbidden');

        $ctx = isset($_POST['ctx']) && is_array($_POST['ctx']) ? array_map('sanitize_text_field', wp_unslash($_POST['ctx'])) : array();

        // Construir meta_query simulando el user
        $axes = incuSlider_Axes::get_all();
        $meta = array('relation' => 'AND');
        foreach ($axes as $axis_id => $axis_def) {
            $meta_key = incuSlider_Axes::meta_key_for($axis_id);
            $clause = array('relation' => 'OR',
                array('key' => $meta_key, 'value' => '"all"', 'compare' => 'LIKE'),
                array('key' => $meta_key, 'compare' => 'NOT EXISTS'),
            );
            $val = $ctx[$axis_id] ?? '';
            if ($val !== '') {
                $clause[] = array('key' => $meta_key, 'value' => '"' . $val . '"', 'compare' => 'LIKE');
            }
            $meta[] = $clause;
        }
        $now = current_time('Y-m-d H:i:s');
        $meta[] = array('relation' => 'OR',
            array('key' => '_incu_date_from', 'compare' => 'NOT EXISTS'),
            array('key' => '_incu_date_from', 'value' => '', 'compare' => '='),
            array('key' => '_incu_date_from', 'value' => $now, 'compare' => '<=', 'type' => 'DATETIME'),
        );
        $meta[] = array('relation' => 'OR',
            array('key' => '_incu_date_to', 'compare' => 'NOT EXISTS'),
            array('key' => '_incu_date_to', 'value' => '', 'compare' => '='),
            array('key' => '_incu_date_to', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'),
        );

        $q = new WP_Query(array(
            'post_type' => incuSlider_CPT::POST_TYPE,
            'posts_per_page' => 50,
            'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
            'meta_query' => $meta,
        ));

        $results = array();
        foreach ($q->posts as $p) {
            $thumb_html = get_the_post_thumbnail($p->ID, array(80, 80));
            $tags = '';
            foreach ($axes as $axis_id => $axis_def) {
                $vals = (array) get_post_meta($p->ID, incuSlider_Axes::meta_key_for($axis_id), true);
                if (empty($vals) || in_array('all', $vals, true)) continue;
                $tags .= '<span class="incuslider-tag is-' . esc_attr($axis_id) . '">' . esc_html($axis_def['label']) . ': ' . esc_html(implode(',', $vals)) . '</span> ';
            }
            if ($tags === '') $tags = '<span class="incuslider-tag is-all">' . esc_html__('Todos', 'incuslider') . '</span>';
            $results[] = array(
                'id' => $p->ID,
                'title' => $p->post_title,
                'thumb' => $thumb_html,
                'targeting' => $tags,
                'link' => get_post_meta($p->ID, '_incu_link_url', true),
            );
        }
        wp_send_json_success(array('slides' => $results, 'count' => count($results)));
    }
}
