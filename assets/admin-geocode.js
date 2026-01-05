(function ($) {
    $(document).ready(function () {
        if (!window.winmoGeocode || winmoGeocode.locked) {
            return;
        }

        const $btn = $('#winmo-geocode-btn');
        const $status = $('#winmo-geocode-status');
        const $input = $('#winmo_address_lookup');
        const $lat = $('#winmo_lat');
        const $lng = $('#winmo_lng');

        if (!$btn.length || !$input.length || !$lat.length || !$lng.length) {
            return;
        }

        const setStatus = (text, type) => {
            $status.text(text);
            $status.removeClass('winmo-status-ok winmo-status-error winmo-status-info');
            if (type) {
                $status.addClass(type);
            }
        };

        $btn.on('click', function (e) {
            e.preventDefault();
            if (winmoGeocode.locked) {
                return;
            }

            const address = $input.val().trim();
            if (!address) {
                setStatus(winmoGeocode.i18n?.empty || 'Introduce una dirección para buscar.', 'winmo-status-error');
                return;
            }

            $btn.prop('disabled', true);
            setStatus(winmoGeocode.i18n?.searching || 'Buscando...', 'winmo-status-info');

            $.post(
                winmoGeocode.ajaxUrl,
                {
                    action: 'winmo_geocode',
                    nonce: winmoGeocode.nonce,
                    address: address,
                }
            )
                .done(function (response) {
                    if (!response || !response.success || !response.data) {
                        setStatus(winmoGeocode.i18n?.error || 'Error al geocodificar.', 'winmo-status-error');
                        return;
                    }
                    const lat = parseFloat(response.data.lat);
                    const lng = parseFloat(response.data.lng);
                    if (isNaN(lat) || isNaN(lng)) {
                        setStatus(winmoGeocode.i18n?.error || 'Error al geocodificar.', 'winmo-status-error');
                        return;
                    }
                    $lat.val(lat.toFixed(6));
                    $lng.val(lng.toFixed(6));
                    setStatus(winmoGeocode.i18n?.ok || 'Coordenadas encontradas. Guarda para aplicar.', 'winmo-status-ok');
                })
                .fail(function (xhr) {
                    const msg = xhr?.responseJSON?.data?.message || winmoGeocode.i18n?.error || 'Error al geocodificar.';
                    setStatus(msg, 'winmo-status-error');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
