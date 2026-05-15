<?php
/**
 * incuSlider — Custom Post Type registration.
 *
 * @package incuSlider
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_CPT {

    const POST_TYPE = 'incu_slide';

    /**
     * NO USAR. Se removió a propósito.
     *
     * Filtrar `elementor_pro/utils/get_public_post_types` para meter incu_slide
     * en el dropdown del Loop hacía que Elementor Pro construyera Theme Builder
     * conditions (singular/archive) para este CPT en CADA carga del editor.
     * Como el CPT es public=false / has_archive=false, ese path cuelga el editor
     * (>200s). El Loop Carousel NO necesita esto: funciona vía el Custom Query ID
     * 'incuslider_main' — incuSlider_Query::apply_user_filters() fuerza
     * post_type=incu_slide sin importar el post type elegido en el panel.
     *
     * @deprecated 1.2.0 Removido por colgar el editor de Elementor.
     */
    public static function init_integrations() {
        // Intencionalmente vacío. Ver docblock.
    }

    public static function register() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name'               => __('incuSlider', 'incuslider'),
                'singular_name'      => __('Slide', 'incuslider'),
                'menu_name'          => __('incuSlider', 'incuslider'),
                'add_new'            => __('Nueva slide', 'incuslider'),
                'add_new_item'       => __('Agregar slide', 'incuslider'),
                'edit_item'          => __('Editar slide', 'incuslider'),
                'new_item'           => __('Nueva slide', 'incuslider'),
                'view_item'          => __('Ver slide', 'incuslider'),
                'search_items'       => __('Buscar slides', 'incuslider'),
                'not_found'          => __('No hay slides', 'incuslider'),
                'not_found_in_trash' => __('No hay slides en la papelera', 'incuslider'),
                'all_items'          => __('Todas las slides', 'incuslider'),
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'show_in_rest'        => false,
            'menu_position'       => 30,
            'menu_icon'           => 'dashicons-images-alt2',
            'capability_type'     => 'post',
            'supports'            => array('title', 'thumbnail', 'page-attributes'),
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'exclude_from_search' => true,
            'hierarchical'        => false,
        ));
    }
}
