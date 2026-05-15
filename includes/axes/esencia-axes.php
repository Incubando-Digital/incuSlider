<?php
/**
 * incuSlider — Built-in axes (rank, country, role).
 *
 * Cada axis se registra solo si su dependencia existe.
 *
 * @package incuSlider
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_filter('incuslider_register_axes', function($axes) {

    // Axis: MyCred Rank
    if (function_exists('mycred_get_users_rank') && post_type_exists('mycred_rank')) {
        $axes['rank'] = array(
            'id'      => 'rank',
            'label'   => __('MyCred Rank', 'incuslider'),
            'options' => function() {
                $opts = array();
                $ranks = get_posts(array(
                    'post_type' => 'mycred_rank', 'posts_per_page' => -1,
                    'orderby' => 'menu_order date', 'order' => 'ASC', 'post_status' => 'publish',
                ));
                foreach ($ranks as $r) $opts[(string) $r->ID] = $r->post_title;
                return $opts;
            },
            'resolve' => function($user_id) {
                if (!$user_id) return null;
                $rank = function_exists('mycred_get_users_rank') ? mycred_get_users_rank($user_id) : null;
                if ($rank && isset($rank->post_id)) return (string) $rank->post_id;
                if ($rank && isset($rank->ID))      return (string) $rank->ID;
                return null;
            },
        );
    }

    // Axis: Country (ISO-2 from user_meta PAIS)
    if (class_exists('Incuimprovements_Country_Mapping')) {
        $axes['country'] = array(
            'id'      => 'country',
            'label'   => __('País', 'incuslider'),
            'options' => function() {
                $opts = array();
                foreach (Incuimprovements_Country_Mapping::all_iso_codes() as $iso) {
                    $opts[$iso] = Incuimprovements_Country_Mapping::iso_to_name($iso) ?? $iso;
                }
                return $opts;
            },
            'resolve' => function($user_id) {
                if (!$user_id) return null;
                $val = get_user_meta($user_id, 'PAIS', true);
                return $val ? (string) $val : null;
            },
        );
    }

    // Axis: WP Role
    $axes['role'] = array(
        'id'      => 'role',
        'label'   => __('Rol WP', 'incuslider'),
        'options' => function() {
            $opts = array();
            $roles = wp_roles()->roles ?? array();
            foreach ($roles as $slug => $r) $opts[$slug] = $r['name'] ?? $slug;
            $opts['visitor'] = __('Visitante (no logueado)', 'incuslider');
            return $opts;
        },
        'resolve' => function($user_id) {
            if (!$user_id) return 'visitor';
            $u = get_userdata($user_id);
            if (!$u) return 'visitor';
            return $u->roles ?: 'visitor';
        },
    );

    return $axes;
});
