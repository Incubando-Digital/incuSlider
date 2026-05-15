<?php
/**
 * incuSlider — Página unificada de Configuración con tabs.
 *
 * Tabs: Setup | Vista previa | General
 *
 * Reemplaza las submenus separadas de Onboarding y Preview.
 *
 * @package incuSlider
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Admin_Settings {

    const PAGE_SLUG = 'incuslider-settings';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 11);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('admin_notices', array(__CLASS__, 'onboarding_notice'));
        add_action('admin_init', array(__CLASS__, 'handle_actions'));
        add_action('wp_ajax_incuslider_preview_context', array('incuSlider_Admin_Settings', 'ajax_preview'));
    }

    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=' . incuSlider_CPT::POST_TYPE,
            __('Configuración', 'incuslider'),
            __('Configuración', 'incuslider'),
            'edit_posts',
            self::PAGE_SLUG,
            array(__CLASS__, 'render')
        );
    }

    public static function enqueue($hook) {
        if (strpos($hook, self::PAGE_SLUG) === false) return;
        wp_enqueue_style('incuslider-admin', INCUSLIDER_URL . 'assets/css/metabox.css', array(), INCUSLIDER_VERSION);
    }

    public static function onboarding_notice() {
        $screen = get_current_screen();
        if (!$screen) return;
        if (strpos($screen->id, self::PAGE_SLUG) !== false) return;
        if (get_option('incuslider_onboarding_completed')) return;
        if (!get_transient('incuslider_show_onboarding') && !isset($_GET['incuslider_force_onboarding'])) return;
        $url = admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&tab=setup');
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Bienvenido a incuSlider 🎉', 'incuslider'); ?></strong> —
                <?php esc_html_e('Setup de 2 minutos para empezar.', 'incuslider'); ?>
                &nbsp; <a href="<?php echo esc_url($url); ?>" class="button button-primary"><?php esc_html_e('Empezar', 'incuslider'); ?></a>
            </p>
        </div>
        <?php
    }

    public static function handle_actions() {
        // Marcar onboarding completado
        if (isset($_POST['incuslider_onboarding_done'])
            && check_admin_referer('incuslider_onboarding_done')
            && current_user_can('manage_options')) {
            update_option('incuslider_onboarding_completed', time());
            delete_transient('incuslider_show_onboarding');
            wp_safe_redirect(add_query_arg('done', '1',
                admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&tab=setup')
            ));
            exit;
        }
    }

    public static function render() {
        $tabs = array(
            'setup'   => __('🚀 Setup', 'incuslider'),
            'preview' => __('👁 Vista previa', 'incuslider'),
            'general' => __('⚙ General', 'incuslider'),
        );
        $current = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'setup';
        $base = admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        ?>
        <div class="wrap incuslider-settings">
            <h1><?php esc_html_e('incuSlider — Configuración', 'incuslider'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $slug, $base)); ?>"
                       class="nav-tab <?php echo $current === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="incuslider-tab-content" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-top:0;max-width:1100px">
                <?php
                if ($current === 'setup')   self::render_tab_setup();
                if ($current === 'preview') self::render_tab_preview();
                if ($current === 'general') self::render_tab_general();
                ?>
            </div>
        </div>
        <?php
    }

    // ─── Tab: Setup ──────────────────────────────────────────────────
    public static function render_tab_setup() {
        $axes = incuSlider_Axes::get_all();
        $cpt_count = (int) (wp_count_posts(incuSlider_CPT::POST_TYPE)->publish ?? 0);
        $deps = array(
            array('label' => 'MyCred', 'ok' => function_exists('mycred_get_users_rank'), 'axis' => 'rank'),
            array('label' => 'Incuimprovements Country Module', 'ok' => class_exists('Incuimprovements_Country_Mapping'), 'axis' => 'country'),
            array('label' => 'LearnDash groups', 'ok' => post_type_exists('groups'), 'axis' => 'community_role'),
            array('label' => 'Elementor Pro (Loop Carousel)', 'ok' => defined('ELEMENTOR_PRO_VERSION'), 'axis' => '—'),
        );
        $completed = get_option('incuslider_onboarding_completed');
        ?>
        <?php if (isset($_GET['done'])): ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Setup marcado como completado ✓', 'incuslider'); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e('1. Detección de dependencias', 'incuslider'); ?></h2>
        <table class="widefat striped" style="max-width:700px">
            <thead><tr>
                <th><?php esc_html_e('Dependencia', 'incuslider'); ?></th>
                <th><?php esc_html_e('Estado', 'incuslider'); ?></th>
                <th><?php esc_html_e('Habilita axis', 'incuslider'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($deps as $d): ?>
                <tr>
                    <td><strong><?php echo esc_html($d['label']); ?></strong></td>
                    <td>
                        <?php if ($d['ok']): ?>
                            <span style="color:#00611f">✓ <?php esc_html_e('Detectado', 'incuslider'); ?></span>
                        <?php else: ?>
                            <span style="color:#a82318">✗ <?php esc_html_e('No instalado', 'incuslider'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html($d['axis']); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2 style="margin-top:24px"><?php esc_html_e('2. Axes registrados', 'incuslider'); ?></h2>
        <p><?php echo sprintf(esc_html(_n('Hay %d axis registrado.', 'Hay %d axes registrados.', count($axes), 'incuslider')), count($axes)); ?></p>
        <ul style="margin-left:20px">
            <?php foreach ($axes as $axis_id => $axis): ?>
                <li><code><?php echo esc_html($axis_id); ?></code> — <?php echo esc_html($axis['label']); ?></li>
            <?php endforeach; ?>
        </ul>

        <h2 style="margin-top:24px"><?php esc_html_e('3. Slides existentes', 'incuslider'); ?></h2>
        <?php if ($cpt_count > 0): ?>
            <p>✓ <?php echo sprintf(esc_html__('Tenés %d slides publicadas.', 'incuslider'), $cpt_count); ?>
               <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE)); ?>" class="button"><?php esc_html_e('Ver listado', 'incuslider'); ?></a>
            </p>
        <?php else: ?>
            <p><?php esc_html_e('Todavía no hay slides cargadas.', 'incuslider'); ?>
               <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . incuSlider_CPT::POST_TYPE)); ?>" class="button">+ <?php esc_html_e('Crear primera slide', 'incuslider'); ?></a>
            </p>
        <?php endif; ?>

        <h2 style="margin-top:24px"><?php esc_html_e('4. Cómo insertar el slider en una página', 'incuslider'); ?></h2>
        <ol style="margin-left:20px">
            <li><?php esc_html_e('En Elementor, agregá un widget "Loop Carousel" (Elementor Pro)', 'incuslider'); ?></li>
            <li><?php echo wp_kses_post(__('En <strong>Layout → Source</strong>: <code>Custom</code>', 'incuslider')); ?></li>
            <li><?php echo wp_kses_post(__('En <strong>Layout → Custom Type</strong>: <code>incu_slide</code>', 'incuslider')); ?></li>
            <li><?php echo wp_kses_post(__('En <strong>Layout → Custom Query ID</strong>: <code>incuslider_main</code>', 'incuslider')); ?></li>
            <li><?php echo wp_kses_post(__('Elegí un Loop Item template diseñado en Theme Builder', 'incuslider')); ?></li>
        </ol>

        <?php if (!$completed): ?>
            <form method="post" style="margin-top:24px">
                <?php wp_nonce_field('incuslider_onboarding_done'); ?>
                <input type="hidden" name="incuslider_onboarding_done" value="1" />
                <button type="submit" class="button button-primary button-large">
                    <?php esc_html_e('Listo, ya configuré todo', 'incuslider'); ?>
                </button>
            </form>
        <?php else: ?>
            <p style="margin-top:24px;color:#00611f">
                ✓ <?php echo sprintf(esc_html__('Setup completado el %s', 'incuslider'), date_i18n('Y-m-d H:i', (int) $completed)); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    // ─── Tab: Vista previa ────────────────────────────────────────────
    public static function render_tab_preview() {
        $axes = incuSlider_Axes::get_all();
        ?>
        <p class="description">
            <?php esc_html_e('Simulá qué slides verá un usuario con un contexto específico. No necesitás cambiar de cuenta para validar visibility.', 'incuslider'); ?>
        </p>

        <form id="incuslider-preview-form" style="max-width:700px">
            <?php wp_nonce_field('incuslider_preview', 'incuslider_preview_nonce'); ?>
            <table class="form-table">
                <?php foreach ($axes as $axis_id => $axis):
                    $opts = incuSlider_Axes::get_options($axis_id);
                ?>
                    <tr>
                        <th><label><?php echo esc_html($axis['label']); ?></label></th>
                        <td>
                            <select name="ctx[<?php echo esc_attr($axis_id); ?>]" style="min-width:280px">
                                <option value="">— <?php esc_html_e('Sin valor (no setado para ese user)', 'incuslider'); ?> —</option>
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

        <div id="incuslider-preview-results" style="margin-top:24px"></div>

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
                        html += '<div class="notice notice-warning inline"><p>Ninguna slide matchea ese contexto.</p></div>';
                    } else {
                        html += '<table class="widefat striped"><thead><tr><th width="80">Imagen</th><th>Título</th><th>Targeting</th><th>Link</th></tr></thead><tbody>';
                        slides.forEach(function(s){
                            html += '<tr><td>' + (s.thumb || '—') + '</td>' +
                                '<td><strong>' + s.title + '</strong></td>' +
                                '<td>' + s.targeting + '</td>' +
                                '<td><a href="' + s.link + '" target="_blank">' + (s.link || '—') + '</a></td></tr>';
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

    // ─── Tab: General ─────────────────────────────────────────────────
    public static function render_tab_general() {
        ?>
        <h2><?php esc_html_e('Información del plugin', 'incuslider'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('Versión', 'incuslider'); ?></th>
                <td><code><?php echo esc_html(INCUSLIDER_VERSION); ?></code></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Custom Query ID por defecto', 'incuslider'); ?></th>
                <td><code>incuslider_main</code> &nbsp;
                    <span class="description"><?php esc_html_e('Usar este ID en el widget Loop Carousel de Elementor.', 'incuslider'); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Repo GitHub', 'incuslider'); ?></th>
                <td><a href="https://github.com/Incubando-Digital/incuSlider" target="_blank">github.com/Incubando-Digital/incuSlider</a></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Extender con custom axes', 'incuslider'); ?></h2>
        <p><?php esc_html_e('Para agregar un axis nuevo (ej. BuddyBoss member_type, ACF field), registralo con el filter:', 'incuslider'); ?></p>
        <pre style="background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;border-radius:4px;overflow:auto">add_filter('incuslider_register_axes', function($axes) {
    $axes['member_type'] = array(
        'id'      => 'member_type',
        'label'   => 'BuddyBoss Member Type',
        'options' => function() {
            return array(
                'free'    => 'Free',
                'premium' => 'Premium',
            );
        },
        'resolve' => function($user_id) {
            return bp_get_member_type($user_id);
        },
    );
    return $axes;
});</pre>
        <p class="description"><?php esc_html_e('El metabox detecta automáticamente el nuevo axis y le agrega su sección.', 'incuslider'); ?></p>
        <?php
    }

    /**
     * AJAX handler para Vista previa.
     */
    public static function ajax_preview() {
        check_ajax_referer('incuslider_preview', 'incuslider_preview_nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('forbidden');

        $ctx = isset($_POST['ctx']) && is_array($_POST['ctx'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['ctx']))
            : array();

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
            'post_type'      => incuSlider_CPT::POST_TYPE,
            'posts_per_page' => 50,
            'orderby'        => array('menu_order' => 'ASC', 'date' => 'DESC'),
            'meta_query'     => $meta,
        ));

        $results = array();
        foreach ($q->posts as $p) {
            $thumb_html = get_the_post_thumbnail($p->ID, array(50, 50));
            $tags = '';
            foreach ($axes as $axis_id => $axis_def) {
                $vals = (array) get_post_meta($p->ID, incuSlider_Axes::meta_key_for($axis_id), true);
                if (empty($vals) || in_array('all', $vals, true)) continue;
                $options = incuSlider_Axes::get_options($axis_id);
                $labels = array_map(fn($v) => $options[$v] ?? $v, $vals);
                $tags .= '<span class="incuslider-tag is-' . esc_attr($axis_id) . '">'
                       . esc_html($axis_def['label']) . ': ' . esc_html(implode(', ', $labels)) . '</span> ';
            }
            if ($tags === '') $tags = '<span class="incuslider-tag is-all">' . esc_html__('Todos', 'incuslider') . '</span>';
            $results[] = array(
                'id'        => $p->ID,
                'title'     => $p->post_title,
                'thumb'     => $thumb_html,
                'targeting' => $tags,
                'link'      => get_post_meta($p->ID, '_incu_link_url', true),
            );
        }
        wp_send_json_success(array('slides' => $results, 'count' => count($results)));
    }
}
