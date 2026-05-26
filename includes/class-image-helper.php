<?php
/**
 * incuSlider — Responsive image helper.
 *
 * Provee el shortcode [incuslider_image] que renderiza un <picture> element con la
 * imagen mobile (`_incu_image_mobile`) + la featured image (desktop) del slide actual.
 *
 * El browser descarga SOLO la fuente que corresponde al viewport — clave para
 * performance en mobile (no se baja la imagen desktop pesada que no se ve).
 *
 * Uso en el Loop Item template:
 *   [incuslider_image]                       — default: breakpoint 768px, lazy
 *   [incuslider_image breakpoint="900"]       — cambia el corte mobile/desktop
 *   [incuslider_image loading="eager"]        — para el primer slide above the fold
 *   [incuslider_image class="mi-clase"]       — clase adicional
 *
 * @package incuSlider
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

class incuSlider_Image_Helper {

    const SHORTCODE = 'incuslider_image';

    public static function init() {
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'render'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
    }

    /**
     * Render del shortcode.
     *
     * Atributos:
     *   - breakpoint: ancho en px a partir del cual se usa la imagen mobile (default 768).
     *   - loading:    "lazy" | "eager" (default "lazy").
     *   - class:      clases adicionales para el <picture>.
     *   - alt:        texto alt (default: title del slide).
     *   - size:       tamaño de WP a usar para desktop (default "full").
     *   - size_mobile: tamaño de WP a usar para mobile (default "large").
     *
     * @param array $atts
     * @return string
     */
    public static function render($atts) {
        $atts = shortcode_atts(array(
            'breakpoint'  => 768,
            'loading'     => 'lazy',
            'class'       => '',
            'alt'         => '',
            'size'        => 'full',
            'size_mobile' => 'large',
        ), $atts, self::SHORTCODE);

        $post_id = get_the_ID();
        if (!$post_id) return '';

        $desktop_id = (int) get_post_thumbnail_id($post_id);
        $mobile_id  = (int) get_post_meta($post_id, '_incu_image_mobile', true);

        // Si no hay desktop, no hay nada que renderizar.
        if (!$desktop_id) return '';

        $desktop_src = wp_get_attachment_image_url($desktop_id, $atts['size']);
        if (!$desktop_src) return '';

        $desktop_srcset = wp_get_attachment_image_srcset($desktop_id, $atts['size']);
        $desktop_sizes  = wp_get_attachment_image_sizes($desktop_id, $atts['size']);

        $mobile_src = '';
        $mobile_srcset = '';
        $mobile_sizes = '';
        if ($mobile_id) {
            $mobile_src    = wp_get_attachment_image_url($mobile_id, $atts['size_mobile']);
            $mobile_srcset = wp_get_attachment_image_srcset($mobile_id, $atts['size_mobile']);
            $mobile_sizes  = wp_get_attachment_image_sizes($mobile_id, $atts['size_mobile']);
        }

        $alt = $atts['alt'] !== '' ? $atts['alt'] : get_the_title($post_id);
        $loading = in_array($atts['loading'], array('lazy', 'eager'), true) ? $atts['loading'] : 'lazy';
        $breakpoint = max(1, (int) $atts['breakpoint']);

        $picture_class = 'incuslider-image';
        if ($atts['class'] !== '') $picture_class .= ' ' . sanitize_html_class($atts['class']);

        // Dimensiones para evitar layout shift (CLS).
        $desktop_meta = wp_get_attachment_metadata($desktop_id);
        $width  = isset($desktop_meta['width'])  ? (int) $desktop_meta['width']  : 0;
        $height = isset($desktop_meta['height']) ? (int) $desktop_meta['height'] : 0;

        ob_start();
        ?>
        <picture class="<?php echo esc_attr($picture_class); ?>">
            <?php if ($mobile_src): ?>
                <source
                    media="(max-width: <?php echo esc_attr($breakpoint); ?>px)"
                    srcset="<?php echo esc_attr($mobile_srcset ?: $mobile_src); ?>"
                    <?php if ($mobile_sizes): ?>sizes="<?php echo esc_attr($mobile_sizes); ?>"<?php endif; ?>
                />
            <?php endif; ?>
            <img
                src="<?php echo esc_url($desktop_src); ?>"
                <?php if ($desktop_srcset): ?>srcset="<?php echo esc_attr($desktop_srcset); ?>"<?php endif; ?>
                <?php if ($desktop_sizes): ?>sizes="<?php echo esc_attr($desktop_sizes); ?>"<?php endif; ?>
                alt="<?php echo esc_attr($alt); ?>"
                loading="<?php echo esc_attr($loading); ?>"
                decoding="async"
                <?php if ($width):  ?>width="<?php  echo esc_attr($width);  ?>"<?php endif; ?>
                <?php if ($height): ?>height="<?php echo esc_attr($height); ?>"<?php endif; ?>
                class="incuslider-image__img"
            />
        </picture>
        <?php
        return trim(ob_get_clean());
    }

    /**
     * CSS minimal para que el <picture> cubra el contenedor del Loop Item.
     */
    public static function enqueue_styles() {
        $css = '.incuslider-image{display:block;width:100%;height:100%;line-height:0;}'
             . '.incuslider-image__img{width:100%;height:100%;object-fit:cover;display:block;}';
        wp_register_style('incuslider-image', false, array(), INCUSLIDER_VERSION);
        wp_enqueue_style('incuslider-image');
        wp_add_inline_style('incuslider-image', $css);
    }
}
