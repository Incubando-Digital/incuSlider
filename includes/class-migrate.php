<?php
/**
 * incuSlider — WP-CLI migration command.
 *
 *   wp incuslider migrate --from-page=69 --dry-run
 *   wp incuslider migrate --from-page=69 --apply
 *
 * @package incuSlider
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Migrate {

    private static $suffix_pairs = array(
        array('-desktop', '-mobile'),
        array('_desktop', '_mobile'),
        array('-dk', '-MB'), array('-dk', '-mb'),
        array('-D', '-M'),   array('_D', '_M'),
    );

    public function migrate($args, $assoc_args) {
        $page_id = isset($assoc_args['from-page']) ? (int) $assoc_args['from-page'] : 0;
        $apply   = !empty($assoc_args['apply']);
        $dry_run = !$apply;
        $limit   = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;

        if (!$page_id) \WP_CLI::error('Se requiere --from-page=<post_id>');
        $json = get_post_meta($page_id, '_elementor_data', true);
        if (!$json) \WP_CLI::error("La página $page_id no tiene _elementor_data");
        $data = json_decode($json, true);
        if (!is_array($data)) \WP_CLI::error("_elementor_data no es JSON válido");

        \WP_CLI::log('=== incuSlider Migrate ' . ($dry_run ? 'DRY-RUN' : 'APPLY') . ' (page=' . $page_id . ') ===');

        $sliders = array();
        self::find_sliders($data, $sliders);
        \WP_CLI::log('Sliders detectados: ' . count($sliders));

        $entries = self::sliders_to_entries($sliders);
        \WP_CLI::log('Slides individuales a crear: ' . count($entries));

        if ($limit > 0 && count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
            \WP_CLI::log("Limitado a $limit");
        }

        $i = 0;
        foreach ($entries as $e) {
            $i++;
            $label = $e['title'] ?: ('Slide ' . $i);
            $img_d = basename(parse_url($e['image_desktop_url'] ?? '', PHP_URL_PATH));
            $img_m = basename(parse_url($e['image_mobile_url'] ?? '', PHP_URL_PATH));
            \WP_CLI::log(sprintf("  [%2d] %s | D=%s | M=%s | rank=%s | country=%s",
                $i, $label, $img_d ?: '(none)', $img_m ?: '(same)',
                implode(',', $e['target_ranks'] ?? array('all')),
                implode(',', $e['target_countries'] ?? array('all'))));
        }

        if ($dry_run) {
            \WP_CLI::success('DRY-RUN: nada se creó.');
            return;
        }

        $created = 0;
        foreach ($entries as $e) {
            $post_id = wp_insert_post(array(
                'post_type' => incuSlider_CPT::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $e['title'] ?: ('Slide migrada ' . ($created + 1)),
                'menu_order' => $created + 1,
            ), true);
            if (is_wp_error($post_id)) {
                \WP_CLI::warning('No se pudo crear: ' . $post_id->get_error_message());
                continue;
            }
            if (!empty($e['image_desktop_id'])) set_post_thumbnail($post_id, (int) $e['image_desktop_id']);
            update_post_meta($post_id, '_incu_image_mobile', (int) ($e['image_mobile_id'] ?? 0));
            update_post_meta($post_id, '_incu_link_url',    (string) ($e['link_url'] ?? ''));
            update_post_meta($post_id, '_incu_link_target', '_self');
            update_post_meta($post_id, '_incu_heading',     (string) ($e['heading'] ?? ''));
            update_post_meta($post_id, '_incu_subheading',  (string) ($e['subheading'] ?? ''));
            update_post_meta($post_id, '_incu_filter_rank',    $e['target_ranks']     ?: array('all'));
            update_post_meta($post_id, '_incu_filter_country', $e['target_countries'] ?: array('all'));
            update_post_meta($post_id, '_incu_filter_role',    array('all'));
            update_post_meta($post_id, '_incu_migrated_from_page', $page_id);
            $created++;
        }
        \WP_CLI::success("APPLY: $created slides creadas.");
    }

    public static function find_sliders($items, &$found, $ancestor_context = array()) {
        if (!is_array($items)) return;
        foreach ($items as $item) {
            $ctx = self::merge_visibility_context($ancestor_context, $item);
            if (isset($item['widgetType']) && $item['widgetType'] === 'slides') {
                $found[] = array('widget' => $item, 'context' => $ctx);
            }
            if (!empty($item['elements'])) self::find_sliders($item['elements'], $found, $ctx);
        }
    }

    public static function merge_visibility_context($parent_ctx, $item) {
        $ctx = $parent_ctx;
        $s = $item['settings'] ?? array();
        if (!empty($s['enabled_visibility']) || !empty($s['dce_visibility_usermeta'])) {
            $key = $s['dce_visibility_usermeta'] ?? '';
            $val = $s['dce_visibility_usermeta_value'] ?? '';
            if ($key === 'mycred_rank' && $val !== '') $ctx['rank'] = (string) $val;
            if ($key === 'PAIS' && $val !== '') {
                $iso = $val;
                if (class_exists('Incuimprovements_Country_Mapping')) {
                    $maybe = Incuimprovements_Country_Mapping::name_to_iso($val);
                    if ($maybe) $iso = $maybe;
                    elseif (Incuimprovements_Country_Mapping::is_valid_iso($val)) $iso = strtoupper($val);
                }
                $ctx['country'] = (string) $iso;
            }
        }
        return $ctx;
    }

    public static function sliders_to_entries($sliders) {
        $entries = array();
        $all_slides = array();
        foreach ($sliders as $slider) {
            $slides = $slider['widget']['settings']['slides'] ?? array();
            foreach ($slides as $slide) {
                $all_slides[] = array(
                    'image_url' => $slide['background_image']['url'] ?? '',
                    'image_id'  => (int) ($slide['background_image']['id'] ?? 0),
                    'heading'   => $slide['heading'] ?? '',
                    'subheading'=> $slide['description'] ?? '',
                    'link_url'  => $slide['link']['url'] ?? '',
                    'context'   => $slider['context'],
                );
            }
        }
        $by_base = array();
        foreach ($all_slides as $s) {
            $base = self::base_name_strip_suffix($s['image_url']);
            $kind = self::detect_kind($s['image_url']);
            $key  = $base . '|' . json_encode($s['context']) . '|' . $s['link_url'];
            if (!isset($by_base[$key])) {
                $by_base[$key] = array(
                    'desktop' => null, 'mobile' => null,
                    'context' => $s['context'], 'link_url' => $s['link_url'],
                    'heading' => $s['heading'], 'subheading' => $s['subheading'],
                );
            }
            if ($kind === 'mobile') $by_base[$key]['mobile'] = $s;
            else                    $by_base[$key]['desktop'] = $s;
        }
        foreach ($by_base as $group) {
            $primary = $group['desktop'] ?: $group['mobile'];
            if (!$primary) continue;
            $title_base = $group['heading'] ?: pathinfo(parse_url($primary['image_url'], PHP_URL_PATH), PATHINFO_FILENAME);
            $entries[] = array(
                'title'            => trim((string) $title_base),
                'image_desktop_id' => (int) ($group['desktop']['image_id'] ?? $primary['image_id']),
                'image_desktop_url'=> (string) ($group['desktop']['image_url'] ?? $primary['image_url']),
                'image_mobile_id'  => (int) ($group['mobile']['image_id'] ?? 0),
                'image_mobile_url' => (string) ($group['mobile']['image_url'] ?? ''),
                'link_url'         => (string) $group['link_url'],
                'heading'          => (string) $group['heading'],
                'subheading'       => (string) $group['subheading'],
                'target_ranks'     => isset($group['context']['rank'])    ? array((string) $group['context']['rank'])    : array('all'),
                'target_countries' => isset($group['context']['country']) ? array((string) $group['context']['country']) : array('all'),
            );
        }
        return $entries;
    }

    public static function base_name_strip_suffix($url) {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        foreach (self::$suffix_pairs as $pair) {
            foreach ($pair as $suf) {
                if (substr($name, -strlen($suf)) === $suf) return substr($name, 0, -strlen($suf));
            }
        }
        return $name;
    }

    public static function detect_kind($url) {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        foreach (self::$suffix_pairs as $pair) {
            list($d_suf, $m_suf) = $pair;
            if (substr($name, -strlen($m_suf)) === $m_suf) return 'mobile';
            if (substr($name, -strlen($d_suf)) === $d_suf) return 'desktop';
        }
        return 'desktop';
    }
}
