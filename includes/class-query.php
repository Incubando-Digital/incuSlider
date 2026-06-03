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

        // Guard de re-entrada — PREVIENE RECURSIÓN INFINITA.
        // build_meta_query() resuelve el contexto del user vía los `resolve` de cada
        // axis. Algunos resolve ejecutan su PROPIA WP_Query (ej. el axis
        // community_role → learndash_get_users_group_ids() → WP_Query en LearnDash).
        // Elementor tiene un filtro pre_get_posts (Elementor_Post_Query) que re-dispara
        // este mismo action `elementor/query/incuslider_main` para CUALQUIER WP_Query
        // anidada, así que sin este guard apply_user_filters se llama a sí mismo en
        // bucle (8000+ veces) hasta agotar la memoria (2GB → HTTP 502). Solo ocurría
        // con usuario logueado, porque los resolve cortan temprano si !$user_id.
        // Al re-entrar, salimos sin tocar la query anidada (no es del slider).
        static $running = false;
        if ($running) return;

        if ($user_id === null) $user_id = get_current_user_id();

        $query->set('post_type', incuSlider_CPT::POST_TYPE);
        $query->set('posts_per_page', 20);
        $query->set('orderby', array('menu_order' => 'ASC', 'date' => 'DESC'));
        $query->set('post_status', 'publish');

        // En el EDITOR de Elementor NO aplicar el meta_query de axes.
        // El editor renderiza el preview del Loop de forma re-entrante; el
        // meta_query (varios NOT EXISTS + LIKE por axis sobre wp_postmeta, que
        // acá pesa ~160MB) genera SQL con múltiples LEFT JOIN que cuelga el
        // editor. El preview NO necesita filtrado real — mostrar todas las
        // slides ahí es correcto. El filtrado por contexto se aplica en
        // frontend, que es donde importa.
        if (self::is_elementor_editor()) {
            return;
        }

        // Preview context override: si la URL trae incuslider_preview_ctx (capability-gated),
        // se usa esa data en vez del current user para previsualizar
        $preview_ctx = self::get_preview_context_override();

        // Marcar re-entrada SOLO alrededor de build_meta_query (donde los resolve de
        // axes pueden disparar WP_Query anidadas que re-entran a este método).
        $running = true;
        $meta_query = self::build_meta_query($user_id, $preview_ctx);
        $running = false;

        if (!empty($meta_query)) $query->set('meta_query', $meta_query);
    }

    /**
     * ¿Estamos dentro del editor de Elementor (panel o su preview iframe)?
     * El preview iframe (elementor-preview) SÍ debe filtrar como frontend, así
     * que solo detectamos el editor real / sus requests de construcción.
     */
    private static function is_elementor_editor() {
        if (class_exists('\Elementor\Plugin')) {
            $p = \Elementor\Plugin::$instance;
            if (isset($p->editor) && $p->editor->is_edit_mode()) {
                return true;
            }
        }
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
        return (strpos($action, 'elementor') === 0);
    }

    /**
     * Lee `incuslider_preview_ctx` de la URL si el current user tiene capability edit_posts.
     * Formato: `axis1:val1,axis2:val2`. Devuelve array assoc o null.
     */
    public static function get_preview_context_override() {
        if (empty($_GET['incuslider_preview_ctx'])) return null;
        if (!current_user_can('edit_posts')) return null;
        $raw = sanitize_text_field(wp_unslash($_GET['incuslider_preview_ctx']));
        $ctx = array();
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) continue;
            $axis = sanitize_key($parts[0]);
            $val  = sanitize_text_field($parts[1]);
            if ($axis !== '' && $val !== '') $ctx[$axis] = $val;
        }
        return $ctx ?: null;
    }

    public static function build_meta_query($user_id, $preview_ctx = null) {
        $axes = incuSlider_Axes::get_all();
        $meta = array('relation' => 'AND');

        foreach ($axes as $axis_id => $axis_def) {
            $meta_key = incuSlider_Axes::meta_key_for($axis_id);

            if ($preview_ctx !== null) {
                // Override mode: usar valor del preview o vacío
                $user_values = isset($preview_ctx[$axis_id]) && $preview_ctx[$axis_id] !== ''
                    ? array((string) $preview_ctx[$axis_id])
                    : array();
            } else {
                $user_values = incuSlider_Axes::get_user_value($axis_id, $user_id);
            }

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
