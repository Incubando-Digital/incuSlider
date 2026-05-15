<?php
/**
 * incuSlider — Metabox view (UI mejorada v1.1).
 *
 * Variables: $post, $image_mobile_id, $mobile_thumb, $mobile_filename,
 *            $link_url, $link_target, $heading, $subheading, $date_from, $date_to
 */
if (!defined('ABSPATH')) exit;
?>
<div class="incuslider-mb">

    <h3 class="incuslider-section-title">
        <span class="dashicons dashicons-format-image"></span>
        <?php esc_html_e('Contenido', 'incuslider'); ?>
    </h3>

    <table class="form-table" role="presentation">
        <tr>
            <th><label><?php esc_html_e('Imagen Mobile', 'incuslider'); ?></label></th>
            <td>
                <div class="incuslider-media-picker" data-input-name="_incu_image_mobile">
                    <input type="hidden" name="_incu_image_mobile" value="<?php echo esc_attr($image_mobile_id); ?>" class="incuslider-media-input" />
                    <div class="incuslider-media-preview" <?php echo $mobile_thumb ? '' : 'style="display:none"'; ?>>
                        <?php if ($mobile_thumb): ?>
                            <img src="<?php echo esc_url($mobile_thumb); ?>" alt="" />
                            <p class="incuslider-media-filename"><?php echo esc_html($mobile_filename); ?></p>
                        <?php endif; ?>
                    </div>
                    <p>
                        <button type="button" class="button incuslider-media-select">
                            <span class="dashicons dashicons-format-image" style="margin-top:3px"></span>
                            <?php echo $mobile_thumb ? esc_html__('Cambiar imagen', 'incuslider') : esc_html__('Seleccionar imagen', 'incuslider'); ?>
                        </button>
                        <button type="button" class="button-link incuslider-media-remove" <?php echo $mobile_thumb ? '' : 'style="display:none"'; ?>>
                            <?php esc_html_e('Quitar', 'incuslider'); ?>
                        </button>
                    </p>
                    <p class="description"><?php esc_html_e('Imagen para viewports menores a 768px. Si no hay, se usa la imagen destacada (desktop).', 'incuslider'); ?></p>
                </div>
            </td>
        </tr>

        <tr>
            <th><label for="_incu_link_url"><?php esc_html_e('URL del Link', 'incuslider'); ?></label></th>
            <td>
                <div class="incuslider-link-picker">
                    <input type="text" id="_incu_link_url" name="_incu_link_url"
                           value="<?php echo esc_attr($link_url); ?>"
                           class="regular-text incuslider-link-input"
                           placeholder="https://ejemplo.com o /pagina-interna" />
                    <button type="button" class="button incuslider-link-search">
                        <span class="dashicons dashicons-admin-links" style="margin-top:3px"></span>
                        <?php esc_html_e('Buscar contenido', 'incuslider'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Pegá una URL externa o usá "Buscar contenido" para enlazar a una página del sitio.', 'incuslider'); ?></p>
                </div>
            </td>
        </tr>

        <tr>
            <th><label><?php esc_html_e('Abrir el link en', 'incuslider'); ?></label></th>
            <td>
                <label><input type="radio" name="_incu_link_target" value="_self"  <?php checked($link_target, '_self'); ?> /> <?php esc_html_e('Misma ventana', 'incuslider'); ?></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="_incu_link_target" value="_blank" <?php checked($link_target, '_blank'); ?> /> <?php esc_html_e('Nueva ventana', 'incuslider'); ?></label>
            </td>
        </tr>

        <tr>
            <th><label for="_incu_heading"><?php esc_html_e('Heading (opcional)', 'incuslider'); ?></label></th>
            <td>
                <input type="text" id="_incu_heading" name="_incu_heading" value="<?php echo esc_attr($heading); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Texto que aparece sobre la imagen. Solo se renderiza si tu Loop Item template lo muestra.', 'incuslider'); ?></p>
            </td>
        </tr>

        <tr>
            <th><label for="_incu_subheading"><?php esc_html_e('Subheading (opcional)', 'incuslider'); ?></label></th>
            <td>
                <input type="text" id="_incu_subheading" name="_incu_subheading" value="<?php echo esc_attr($subheading); ?>" class="regular-text" />
            </td>
        </tr>
    </table>

    <h3 class="incuslider-section-title">
        <span class="dashicons dashicons-visibility"></span>
        <?php esc_html_e('Visibilidad por contexto', 'incuslider'); ?>
    </h3>
    <p class="incuslider-help">
        <?php esc_html_e('Marcá "Mostrar a todos" si la slide es global, o seleccioná uno o varios valores específicos para targeting fino. Cuando un user matchea, ve la slide.', 'incuslider'); ?>
    </p>

    <?php
    $axes = incuSlider_Axes::get_all();
    if (empty($axes)):
    ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('No hay axes registrados. La slide se mostrará a todos.', 'incuslider'); ?></p>
        </div>
    <?php
    endif;
    foreach ($axes as $axis_id => $axis):
        $current = (array) get_post_meta($post->ID, incuSlider_Axes::meta_key_for($axis_id), true);
        if (empty($current)) $current = array('all');
        $is_all  = in_array('all', $current, true);
        $options = incuSlider_Axes::get_options($axis_id);
    ?>
        <div class="incuslider-axis" data-axis="<?php echo esc_attr($axis_id); ?>">
            <div class="incuslider-axis-header">
                <strong><?php echo esc_html($axis['label']); ?></strong>
                <label class="incuslider-axis-all">
                    <input type="checkbox" name="_incu_filter_all_<?php echo esc_attr($axis_id); ?>" value="1" class="incuslider-all-toggle" <?php checked($is_all); ?> />
                    <?php esc_html_e('Mostrar a todos', 'incuslider'); ?>
                </label>
            </div>
            <select name="_incu_filter_<?php echo esc_attr($axis_id); ?>[]" multiple class="incuslider-axis-select" data-placeholder="<?php esc_attr_e('Seleccionar valores…', 'incuslider'); ?>">
                <?php foreach ($options as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php echo in_array((string) $val, array_map('strval', $current), true) ? 'selected' : ''; ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <h3 class="incuslider-section-title">
        <span class="dashicons dashicons-calendar-alt"></span>
        <?php esc_html_e('Vigencia (opcional)', 'incuslider'); ?>
    </h3>
    <p class="incuslider-help">
        <?php esc_html_e('Si setés fechas, la slide solo se muestra dentro de ese rango. Dejá vacío para que esté activa siempre.', 'incuslider'); ?>
    </p>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="_incu_date_from"><?php esc_html_e('Mostrar desde', 'incuslider'); ?></label></th>
            <td><input type="text" id="_incu_date_from" name="_incu_date_from" value="<?php echo esc_attr($date_from); ?>"
                       class="incuslider-datepicker regular-text" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
        </tr>
        <tr>
            <th><label for="_incu_date_to"><?php esc_html_e('Mostrar hasta', 'incuslider'); ?></label></th>
            <td><input type="text" id="_incu_date_to" name="_incu_date_to" value="<?php echo esc_attr($date_to); ?>"
                       class="incuslider-datepicker regular-text" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
        </tr>
    </table>
</div>
