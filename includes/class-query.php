<?php
/**
 * incuSlider — Query filter para Elementor Loop Carousel/Grid.
 *
 * @package incuSlider
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Query {

    public static function init() {
        add_action('elementor/query/incuslider_main', array(__CLASS__, 'apply_user_filters'));
        // Permitir IDs adicionales con prefix incuslider_
        add_action('init', function() {
            // hook generic — Elementor dispara elementor/query/{id}, así que cada ID custom necesita su action.
            // Para v1.1 documentamos incuslider_main como ID default.
        });
    }

    public static function apply_user_filters($query, $user_id = null) {
        if (!($query instanceof WP_Query)) return;
        if ($user_id === null) $user_id = get_current_user_id();

        $query->set('post_type', incuSlider_CPT::POST_TYPE);
        $query->set('posts_per_page', 20);
        $query->set('orderby', array('menu_order' => 'ASC', 'date' => 'DESC'));
        $query->set('post_status', 'publish');

        $meta_query = self::build_meta_query($user_id);
        if (!empty($meta_query)) $query->set('meta_query', $meta_query);
    }

    public static function build_meta_query($user_id) {
        $axes = incuSlider_Axes::get_all();
        $meta = array('relation' => 'AND');

        foreach ($axes as $axis_id => $axis_def) {
            $meta_key = incuSlider_Axes::meta_key_for($axis_id);
            $user_values = incuSlider_Axes::get_user_value($axis_id, $user_id);

            $axis_clause = array('relation' => 'OR',
                array('key' => $meta_key, 'value' => '"all"', 'compare' => 'LIKE'),
                array('key' => $meta_key, 'compare' => 'NOT EXISTS'),
            );
            foreach ($user_values as $v) {
                $axis_clause[] = array('key' => $meta_key, 'value' => '"' . $v . '"', 'compare' => 'LIKE');
            }
            $meta[] = $axis_clause;
        }

        $now = current_time('Y-m-d H:i:s');
        $meta[] = array('relation' => 'OR',
            array('key' => '_incu_date_from', 'compare' => 'NOT EXISTS'),
            array('key' => '_incu_date_from', 'value' => '',  'compare' => '='),
            array('key' => '_incu_date_from', 'value' => $now, 'compare' => '<=', 'type' => 'DATETIME'),
        );
        $meta[] = array('relation' => 'OR',
            array('key' => '_incu_date_to', 'compare' => 'NOT EXISTS'),
            array('key' => '_incu_date_to', 'value' => '',   'compare' => '='),
            array('key' => '_incu_date_to', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'),
        );

        return $meta;
    }
}
