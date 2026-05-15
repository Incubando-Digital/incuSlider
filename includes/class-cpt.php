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

    public static function init_integrations() {
        // Elementor Pro Loop Grid/Carousel lista solo post types con
        // show_in_nav_menus=true. Nuestro CPT lo tiene en false (para no
        // ensuciar el constructor de menús). Este filtro lo agrega SOLO
        // al selector del Loop, sin tocar el resto de WP.
        add_filter('elementor_pro/utils/get_public_post_types', function ($post_types) {
            if (!isset($post_types[self::POST_TYPE])) {
                $obj = get_post_type_object(self::POST_TYPE);
                $post_types[self::POST_TYPE] = $obj ? $obj->label : 'Slide';
            }
            return $post_types;
        });

        // Theme Builder usa su propia copia del helper (Elementor Pro >= 3.x)
        add_filter('elementor/theme/get_public_post_types', function ($post_types) {
            if (is_array($post_types) && !isset($post_types[self::POST_TYPE])) {
                $obj = get_post_type_object(self::POST_TYPE);
                $post_types[self::POST_TYPE] = $obj ? $obj->label : 'Slide';
            }
            return $post_types;
        });
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
