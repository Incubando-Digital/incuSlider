<?php
/**
 * incuSlider — Onboarding wizard zero-code.
 *
 * Al activar el plugin por primera vez, muestra un wizard de setup que:
 * - Detecta los axes disponibles según plugins instalados
 * - Permite elegir cuáles activar
 * - Crea un Loop Item template básico de muestra
 *
 * @package incuSlider
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Onboarding {

    const OPTION = 'incuslider_onboarding_completed';
    const PAGE_SLUG = 'incuslider-onboarding';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_page'), 12);
        add_action('admin_notices', array(__CLASS__, 'maybe_notice'));
        add_action('admin_init', array(__CLASS__, 'handle_submit'));
    }

    public static function register_page() {
        add_submenu_page(
            'edit.php?post_type=' . incuSlider_CPT::POST_TYPE,
            __('Bienvenida', 'incuslider'),
            __('🚀 Setup', 'incuslider'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render')
        );
    }

    public static function maybe_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->id === 'incu_slide_page_' . self::PAGE_SLUG) return;
        if (get_option(self::OPTION)) return;
        if (!get_transient('incuslider_show_onboarding') && !isset($_GET['incuslider_force_onboarding'])) return;
        $url = admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Bienvenido a incuSlider 🎉', 'incuslider'); ?></strong> —
                <?php esc_html_e('Te invitamos a un setup de 2 minutos para configurar tu primer slider.', 'incuslider'); ?>
                &nbsp; <a href="<?php echo esc_url($url); ?>" class="button button-primary"><?php esc_html_e('Empezar setup', 'incuslider'); ?></a>
            </p>
        </div>
        <?php
    }

    public static function render() {
        $detected = self::detect_dependencies();
        $axes = incuSlider_Axes::get_all();
        $cpt_count = wp_count_posts(incuSlider_CPT::POST_TYPE)->publish ?? 0;
        ?>
        <div class="wrap incuslider-onboarding">
            <h1>🚀 <?php esc_html_e('incuSlider — Setup', 'incuslider'); ?></h1>

            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;max-width:900px">

                <h2>1. <?php esc_html_e('Detección de dependencias', 'incuslider'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr><th><?php esc_html_e('Dependencia', 'incuslider'); ?></th><th><?php esc_html_e('Estado', 'incuslider'); ?></th><th><?php esc_html_e('Habilita axis', 'incuslider'); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detected as $row): ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['label']); ?></strong></td>
                            <td>
                                <?php if ($row['ok']): ?>
                                    <span style="color:#00611f">✓ <?php esc_html_e('Detectado', 'incuslider'); ?></span>
                                <?php else: ?>
                                    <span style="color:#a82318">✗ <?php esc_html_e('No instalado', 'incuslider'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($row['axis']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:30px">2. <?php esc_html_e('Axes registrados', 'incuslider'); ?></h2>
                <p>
                    <?php echo sprintf(
                        esc_html(_n('Hay %d axis registrado.', 'Hay %d axes registrados.', count($axes), 'incuslider')),
                        count($axes)
                    ); ?>
                </p>
                <ul style="margin-left:20px">
                    <?php foreach ($axes as $axis_id => $axis): ?>
                        <li><code><?php echo esc_html($axis_id); ?></code> — <?php echo esc_html($axis['label']); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="description">
                    <?php esc_html_e('Estos axes aparecen automáticamente en el metabox de cada slide. Para agregar más (BuddyBoss member_type, LearnDash group, etc.) ver la documentación del filter incuslider_register_axes.', 'incuslider'); ?>
                </p>

                <h2 style="margin-top:30px">3. <?php esc_html_e('Slides existentes', 'incuslider'); ?></h2>
                <?php if ($cpt_count > 0): ?>
                    <p>✓ <?php echo sprintf(esc_html__('Tenés %d slides publicadas.', 'incuslider'), $cpt_count); ?></p>
                <?php else: ?>
                    <p><?php esc_html_e('Todavía no hay slides cargadas.', 'incuslider'); ?>
                       <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . incuSlider_CPT::POST_TYPE)); ?>" class="button">+ <?php esc_html_e('Crear primera slide', 'incuslider'); ?></a></p>
                <?php endif; ?>

                <h2 style="margin-top:30px">4. <?php esc_html_e('Cómo insertar el slider en una página', 'incuslider'); ?></h2>
                <ol style="margin-left:20px">
                    <li><?php esc_html_e('En Elementor, agregá un widget "Loop Carousel" (Elementor Pro)', 'incuslider'); ?></li>
                    <li>
                        <?php echo wp_kses_post(__('En <strong>Layout → Source</strong>: <code>Custom</code>', 'incuslider')); ?>
                    </li>
                    <li>
                        <?php echo wp_kses_post(__('En <strong>Layout → Custom Type</strong>: <code>incu_slide</code>', 'incuslider')); ?>
                    </li>
                    <li>
                        <?php echo wp_kses_post(__('En <strong>Layout → Custom Query ID</strong>: <code>incuslider_main</code>', 'incuslider')); ?>
                    </li>
                    <li>
                        <?php echo wp_kses_post(__('Elegí un <strong>Choose a template</strong> (loop item) que diseñes en Theme Builder', 'incuslider')); ?>
                    </li>
                </ol>

                <form method="post" action="" style="margin-top:30px">
                    <?php wp_nonce_field('incuslider_onboarding_done'); ?>
                    <input type="hidden" name="incuslider_onboarding_done" value="1" />
                    <p>
                        <button type="submit" class="button button-primary button-large">
                            <?php esc_html_e('Listo, ya configuré todo', 'incuslider'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE)); ?>" class="button">
                            <?php esc_html_e('Ir al listado de slides', 'incuslider'); ?>
                        </a>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public static function detect_dependencies() {
        return array(
            array(
                'label' => 'MyCred (para axis "rank")',
                'ok'    => function_exists('mycred_get_users_rank'),
                'axis'  => 'rank',
            ),
            array(
                'label' => 'Incuimprovements Country Module (para axis "country")',
                'ok'    => class_exists('Incuimprovements_Country_Mapping'),
                'axis'  => 'country',
            ),
            array(
                'label' => 'WordPress core (para axis "role")',
                'ok'    => true,
                'axis'  => 'role',
            ),
            array(
                'label' => 'Elementor Pro (Loop Carousel widget)',
                'ok'    => defined('ELEMENTOR_PRO_VERSION'),
                'axis'  => '—',
            ),
        );
    }

    public static function handle_submit() {
        if (!isset($_POST['incuslider_onboarding_done'])) return;
        if (!check_admin_referer('incuslider_onboarding_done')) return;
        if (!current_user_can('manage_options')) return;

        update_option(self::OPTION, time());
        delete_transient('incuslider_show_onboarding');

        wp_safe_redirect(admin_url('edit.php?post_type=' . incuSlider_CPT::POST_TYPE . '&incuslider_setup_done=1'));
        exit;
    }
}
