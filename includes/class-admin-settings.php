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
        add_action('wp_ajax_incuslider_preview_count', array(__CLASS__, 'ajax_preview_count'));
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

        // Guardar URL de preview (tab General)
        if (isset($_POST['incuslider_save_general'])
            && check_admin_referer('incuslider_save_general')
            && current_user_can('manage_options')) {
            $url = isset($_POST['incuslider_preview_url'])
                ? esc_url_raw(wp_unslash($_POST['incuslider_preview_url']))
                : '';
            if ($url) update_option('incuslider_preview_url', $url);
            wp_safe_redirect(add_query_arg('saved', '1',
                admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&tab=general')
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
        $preview_url = get_option('incuslider_preview_url', home_url('/test-incuslider/'));

        // Iframe inicial: usar el contexto del current user (sin override)
        $iframe_src_initial = add_query_arg('incuslider_no_admin_bar', '1', $preview_url);
        ?>
        <p class="description">
            <?php esc_html_e('Simulá qué slides verá un usuario con un contexto específico. El iframe renderiza el slider tal cual lo verá ese usuario.', 'incuslider'); ?>
        </p>

        <form id="incuslider-preview-form" style="background:#f6f7f7;padding:12px 16px;border:1px solid #ccd0d4;border-radius:4px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;max-width:1100px">
            <?php foreach ($axes as $axis_id => $axis):
                $opts = incuSlider_Axes::get_options($axis_id);
            ?>
                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-weight:600;font-size:12px"><?php echo esc_html($axis['label']); ?></label>
                    <select name="ctx_<?php echo esc_attr($axis_id); ?>" style="min-width:200px">
                        <option value="">— <?php esc_html_e('Sin valor', 'incuslider'); ?> —</option>
                        <?php foreach ($opts as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div style="display:flex;gap:8px">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-visibility" style="margin-top:3px"></span>
                    <?php esc_html_e('Simular', 'incuslider'); ?>
                </button>
                <button type="button" id="incuslider-preview-reset" class="button">
                    <?php esc_html_e('Reset (current user)', 'incuslider'); ?>
                </button>
            </div>
        </form>

        <div id="incuslider-preview-meta" style="margin:12px 0;font-size:13px;color:#50575e">
            <strong><?php esc_html_e('Slides que matchean:', 'incuslider'); ?></strong>
            <span id="incuslider-preview-count">— <em><?php esc_html_e('aún no simulado', 'incuslider'); ?></em></span>
            &nbsp;|&nbsp; <strong><?php esc_html_e('URL preview:', 'incuslider'); ?></strong>
            <code id="incuslider-preview-url"><?php echo esc_html($preview_url); ?></code>
        </div>

        <div style="border:1px solid #ccd0d4;border-radius:4px;overflow:hidden;background:#fff;max-width:1100px">
            <iframe id="incuslider-preview-iframe"
                    src="<?php echo esc_url($iframe_src_initial); ?>"
                    style="width:100%;height:600px;border:0;display:block"
                    title="<?php esc_attr_e('incuSlider preview', 'incuslider'); ?>"></iframe>
        </div>

        <p class="description" style="margin-top:12px">
            <?php
            printf(
                /* translators: %s = URL */
                esc_html__('Iframe apunta a %s. Si querés que apunte a otra página (ej. /bienvenida/), cambialo en la tab General.', 'incuslider'),
                '<code>' . esc_html($preview_url) . '</code>'
            );
            ?>
        </p>

        <script>
        jQuery(function($){
            var baseUrl = <?php echo wp_json_encode($preview_url); ?>;
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce   = <?php echo wp_json_encode(wp_create_nonce('incuslider_preview_count')); ?>;

            function buildCtxParam() {
                var parts = [];
                $('#incuslider-preview-form select').each(function(){
                    var name = $(this).attr('name').replace('ctx_', '');
                    var val = $(this).val();
                    if (val) parts.push(name + ':' + val);
                });
                return parts.join(',');
            }

            $('#incuslider-preview-form').on('submit', function(e){
                e.preventDefault();
                var ctx = buildCtxParam();
                var url = baseUrl + (baseUrl.indexOf('?') > -1 ? '&' : '?')
                    + 'incuslider_no_admin_bar=1'
                    + (ctx ? '&incuslider_preview_ctx=' + encodeURIComponent(ctx) : '')
                    + '&_t=' + Date.now();
                $('#incuslider-preview-iframe').attr('src', url);
                $('#incuslider-preview-url').text(url);

                // Contador AJAX
                $('#incuslider-preview-count').html('<em>Calculando…</em>');
                $.post(ajaxUrl, {
                    action: 'incuslider_preview_count',
                    nonce: nonce,
                    ctx: ctx
                }, function(res){
                    if (res.success) {
                        var n = res.data.count;
                        $('#incuslider-preview-count').html('<strong>' + n + '</strong> slide' + (n === 1 ? '' : 's'));
                    } else {
                        $('#incuslider-preview-count').html('—');
                    }
                });
            });

            $('#incuslider-preview-reset').on('click', function(){
                $('#incuslider-preview-form select').val('');
                var url = baseUrl + (baseUrl.indexOf('?') > -1 ? '&' : '?') + 'incuslider_no_admin_bar=1&_t=' + Date.now();
                $('#incuslider-preview-iframe').attr('src', url);
                $('#incuslider-preview-url').text(baseUrl);
                $('#incuslider-preview-count').html('— <em>current user</em>');
            });
        });
        </script>
        <?php
    }

    // ─── Tab: General ─────────────────────────────────────────────────
    public static function render_tab_general() {
        $preview_url = get_option('incuslider_preview_url', home_url('/test-incuslider/'));
        ?>
        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Configuración guardada ✓', 'incuslider'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('incuslider_save_general'); ?>
            <input type="hidden" name="incuslider_save_general" value="1" />

            <h2><?php esc_html_e('Configuración', 'incuslider'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="incuslider_preview_url"><?php esc_html_e('URL de preview (iframe)', 'incuslider'); ?></label></th>
                    <td>
                        <input type="url" id="incuslider_preview_url" name="incuslider_preview_url"
                               value="<?php echo esc_attr($preview_url); ?>" class="regular-text"
                               placeholder="<?php echo esc_attr(home_url('/test-incuslider/')); ?>" />
                        <p class="description">
                            <?php esc_html_e('Página donde está colocado el widget Loop Carousel. El tab "Vista previa" embebe esta URL en un iframe. Por defecto: /test-incuslider/.', 'incuslider'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'incuslider'); ?></button></p>
        </form>

        <h2 style="margin-top:32px"><?php esc_html_e('Información del plugin', 'incuslider'); ?></h2>
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
     * AJAX: count de slides que matchean un contexto simulado.
     * Usa Incu_Slider_Query::build_meta_query con el preview ctx.
     */
    public static function ajax_preview_count() {
        check_ajax_referer('incuslider_preview_count', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('forbidden');

        $raw_ctx = isset($_POST['ctx']) ? sanitize_text_field(wp_unslash($_POST['ctx'])) : '';
        $ctx = array();
        foreach (explode(',', $raw_ctx) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) continue;
            $ctx[sanitize_key($parts[0])] = sanitize_text_field($parts[1]);
        }

        $meta = incuSlider_Query::build_meta_query(0, $ctx ?: null);
        $q = new WP_Query(array(
            'post_type'      => incuSlider_CPT::POST_TYPE,
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => $meta,
        ));
        wp_send_json_success(array('count' => count($q->posts)));
    }
}
