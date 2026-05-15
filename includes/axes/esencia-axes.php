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

    // Axis: Rol Comunidad (LearnDash groups — Brand Lover / Emprendedor / Embajadoras / etc.)
    if (post_type_exists('groups')) {
        $axes['community_role'] = array(
            'id'      => 'community_role',
            'label'   => __('Rol Comunidad', 'incuslider'),
            'options' => function() {
                $opts = array();
                $groups = get_posts(array(
                    'post_type'      => 'groups',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'post_status'    => 'publish',
                ));
                foreach ($groups as $g) {
                    $opts[(string) $g->ID] = $g->post_title;
                }
                return $opts;
            },
            'resolve' => function($user_id) {
                if (!$user_id) return null;

                // Preferir API oficial de LearnDash si está disponible
                if (function_exists('learndash_get_users_group_ids')) {
                    $ids = learndash_get_users_group_ids($user_id);
                    return is_array($ids) ? array_map('strval', $ids) : array();
                }

                // Fallback: leer usermeta learndash_group_{id}_enrolled_at
                global $wpdb;
                $keys = $wpdb->get_col($wpdb->prepare(
                    "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key LIKE 'learndash\\_group\\_%\\_enrolled\\_at'",
                    $user_id
                ));
                $enrolled = array();
                foreach ($keys as $k) {
                    if (preg_match('/learndash_group_(\d+)_enrolled_at/', $k, $m)) {
                        $enrolled[] = $m[1];
                    }
                }
                return $enrolled;
            },
        );
    }

    return $axes;
});
