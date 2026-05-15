/* incuSlider — admin metabox JS v1.1 */
(function($) {
    'use strict';

    $(function() {

        // ─── Media picker para imagen mobile ─────────────────────────
        let mediaFrame = null;
        $('.incuslider-media-select').on('click', function(e) {
            e.preventDefault();
            const $wrap = $(this).closest('.incuslider-media-picker');
            const inputName = $wrap.data('input-name');

            mediaFrame = wp.media({
                title: incuSliderL10n.selectImage,
                button: { text: incuSliderL10n.useThisImage },
                library: { type: 'image' },
                multiple: false
            });
            mediaFrame.on('select', function() {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                $wrap.find('input[type=hidden]').val(attachment.id);
                const thumb = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                $wrap.find('.incuslider-media-preview').show()
                    .html('<img src="' + thumb + '" alt="" /><p class="incuslider-media-filename">' + attachment.filename + '</p>');
                $wrap.find('.incuslider-media-remove').show();
                $wrap.find('.incuslider-media-select').text(incuSliderL10n.remove === 'Quitar imagen' ? 'Cambiar imagen' : 'Change image');
            });
            mediaFrame.open();
        });

        $('.incuslider-media-remove').on('click', function(e) {
            e.preventDefault();
            const $wrap = $(this).closest('.incuslider-media-picker');
            $wrap.find('input[type=hidden]').val('');
            $wrap.find('.incuslider-media-preview').hide().empty();
            $(this).hide();
            $wrap.find('.incuslider-media-select').html('<span class="dashicons dashicons-format-image" style="margin-top:3px"></span> ' + incuSliderL10n.selectImage);
        });

        // ─── Link picker usando wpLink (modal nativo de WP) ─────────
        $('.incuslider-link-search').on('click', function(e) {
            e.preventDefault();
            const $input = $(this).closest('.incuslider-link-picker').find('.incuslider-link-input');
            // wpLink usa el textarea/input activo
            $input.focus();
            // Workaround: wpLink necesita un textarea de TinyMCE, así que abrimos en modo "simple"
            if (typeof wpLink !== 'undefined' && wpLink.open) {
                window.wpActiveEditor = $input.attr('id');
                wpLink.open($input.attr('id'));

                // Override the close handler to write URL into our input
                const origUpdate = wpLink.update;
                wpLink.update = function() {
                    const link = wpLink.getAttrs();
                    if (link && link.href) {
                        $input.val(link.href);
                        if (link.target === '_blank') {
                            $input.closest('.incuslider-mb').find('input[name=_incu_link_target][value=_blank]').prop('checked', true);
                        }
                    }
                    wpLink.close();
                    wpLink.update = origUpdate;
                };
            } else {
                // Fallback: prompt
                const url = prompt('Ingresar URL completa o ruta interna:', $input.val() || 'https://');
                if (url !== null) $input.val(url);
            }
        });

        // ─── Select2 para los axis ──────────────────────────────────
        function applyAxisState($axis) {
            const isAll = $axis.find('.incuslider-all-toggle').is(':checked');
            $axis.toggleClass('is-all', isAll);
            if (isAll) {
                $axis.find('.incuslider-axis-select').val(null).trigger('change');
            }
        }

        $('.incuslider-axis-select').each(function() {
            $(this).select2({
                placeholder: $(this).data('placeholder') || 'Seleccionar valores…',
                width: '100%',
                allowClear: true,
                closeOnSelect: false
            });
        });

        $('.incuslider-axis').each(function() { applyAxisState($(this)); });

        $('.incuslider-all-toggle').on('change', function() {
            applyAxisState($(this).closest('.incuslider-axis'));
        });

        // Si el user selecciona algo en el select2, destildar "Mostrar a todos"
        $('.incuslider-axis-select').on('change', function() {
            const $axis = $(this).closest('.incuslider-axis');
            const vals = $(this).val();
            if (vals && vals.length > 0) {
                $axis.find('.incuslider-all-toggle').prop('checked', false);
                applyAxisState($axis);
            }
        });

        // ─── Date picker ─────────────────────────────────────────────
        $('.incuslider-datepicker').each(function() {
            const $i = $(this);
            // Replace type=text with datepicker. Use altField pattern.
            $i.datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                onSelect: function(dateStr) {
                    // Conservar hora si ya había
                    const current = $i.val();
                    const timeMatch = current.match(/\d{2}:\d{2}(?::\d{2})?$/);
                    const timeStr = timeMatch ? ' ' + timeMatch[0] : ' 00:00:00';
                    $i.val(dateStr + timeStr);
                }
            });
        });
    });
})(jQuery);
