<?php
/**
 * Plugin Name: incuSlider
 * Plugin URI: https://github.com/Incubando-Digital/incuSlider
 * Description: Banner slider reutilizable que reemplaza el patrón "N sliders duplicados con visibility conditions" por UN solo Elementor Loop Carousel con filtrado server-side por contexto del user (rank, país, rol, fechas).
 * Version: 1.1.0
 * Author: Incubando Digital
 * Author URI: https://incubando.digital
 * License: GPL-2.0+
 * Text Domain: incuslider
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 *
 * @package incuSlider
 */

if (!defined('ABSPATH')) {
    exit;
}

define('INCUSLIDER_VERSION', '1.1.0');
define('INCUSLIDER_DIR', plugin_dir_path(__FILE__));
define('INCUSLIDER_URL', plugin_dir_url(__FILE__));
define('INCUSLIDER_FILE', __FILE__);
define('INCUSLIDER_BASENAME', plugin_basename(__FILE__));

// ─── Core classes ────────────────────────────────────────────────────
require_once INCUSLIDER_DIR . 'includes/class-cpt.php';
require_once INCUSLIDER_DIR . 'includes/class-axes.php';
require_once INCUSLIDER_DIR . 'includes/class-metabox.php';
require_once INCUSLIDER_DIR . 'includes/class-admin-columns.php';
require_once INCUSLIDER_DIR . 'includes/class-admin-bulk.php';
require_once INCUSLIDER_DIR . 'includes/class-admin-sort.php';
require_once INCUSLIDER_DIR . 'includes/class-admin-settings.php';
require_once INCUSLIDER_DIR . 'includes/class-query.php';
require_once INCUSLIDER_DIR . 'includes/class-migrate.php';
require_once INCUSLIDER_DIR . 'includes/axes/esencia-axes.php';

// ─── Bootstrap ───────────────────────────────────────────────────────
add_action('plugins_loaded', function() {
    incuSlider::instance();
});

class incuSlider {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // CPT register
        if (did_action('init')) {
            incuSlider_CPT::register();
        } else {
            add_action('init', array('incuSlider_CPT', 'register'), 5);
        }

        // NOTA: NO usar incuSlider_CPT::init_integrations().
        // El filtro elementor_pro/utils/get_public_post_types hacía que Elementor Pro
        // construyera Theme Builder conditions (singular/archive) para incu_slide en
        // CADA carga del editor; como el CPT es public=false/has_archive=false, ese
        // path cuelga el editor (>200s). NO es necesario: el Loop Carousel funciona
        // vía el Custom Query ID 'incuslider_main' — apply_user_filters() fuerza
        // post_type=incu_slide sin importar qué post type se elija en el dropdown.

        // Admin features
        if (is_admin()) {
            incuSlider_Metabox::init();
            incuSlider_Admin_Columns::init();
            incuSlider_Admin_Bulk::init();
            incuSlider_Admin_Sort::init();
            incuSlider_Admin_Settings::init();
        }

        // Hide admin bar en frontend preview (corre también en frontend)
        add_filter('show_admin_bar', function($show) {
            if (!empty($_GET['incuslider_no_admin_bar'])) return false;
            return $show;
        });

        // Query filter para Elementor Loop
        incuSlider_Query::init();

        // WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('incuslider', 'incuSlider_Migrate');
        }
    }
}

// ─── Activation / Deactivation ───────────────────────────────────────
register_activation_hook(__FILE__, function() {
    // Flush rewrites para que CPT funcione
    require_once INCUSLIDER_DIR . 'includes/class-cpt.php';
    incuSlider_CPT::register();
    flush_rewrite_rules();

    // Flag para mostrar onboarding wizard al next admin page load
    if (!get_option('incuslider_onboarding_completed')) {
        set_transient('incuslider_show_onboarding', 1, 60);
    }
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
