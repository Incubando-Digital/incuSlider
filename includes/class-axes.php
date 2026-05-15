<?php
/**
 * incuSlider — Axes registry (extensible filtering dimensions).
 *
 * @package incuSlider
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Axes {

    /**
     * @return array<string, array{id:string, label:string, options:callable|array, resolve:callable}>
     */
    public static function get_all() {
        $axes = apply_filters('incuslider_register_axes', array());
        $valid = array();
        foreach ($axes as $id => $axis) {
            if (!is_array($axis) || empty($axis['id']) || empty($axis['label']) || !isset($axis['options']) || !isset($axis['resolve'])) {
                continue;
            }
            $valid[$axis['id']] = $axis;
        }
        return $valid;
    }

    public static function get($axis_id) {
        $all = self::get_all();
        return $all[$axis_id] ?? null;
    }

    public static function get_options($axis_id) {
        $axis = self::get($axis_id);
        if (!$axis) return array();
        $opts = $axis['options'];
        if (is_callable($opts)) $opts = call_user_func($opts);
        return is_array($opts) ? $opts : array();
    }

    public static function get_user_value($axis_id, $user_id) {
        $axis = self::get($axis_id);
        if (!$axis) return array();
        $val = call_user_func($axis['resolve'], $user_id);
        if (is_array($val)) return array_values(array_filter(array_map('strval', $val), 'strlen'));
        if (is_scalar($val) && (string) $val !== '') return array((string) $val);
        return array();
    }

    public static function meta_key_for($axis_id) {
        return '_incu_filter_' . sanitize_key($axis_id);
    }
}
