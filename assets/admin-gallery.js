(function ($) {
    const parseIds = (val) => {
        if (!val) return [];
        return val
            .split(',')
            .map((id) => parseInt(id, 10))
            .filter((id) => !isNaN(id));
    };

    const ensureAttachment = (id) => {
        const attachment = wp.media.attachment(id);
        if (!attachment.get('url')) {
            attachment.fetch();
        }
        return attachment;
    };

    const renderThumbs = (ids, $preview) => {
        $preview.empty();
        if (!ids.length) {
            return;
        }
        ids.forEach((id) => {
            const attachment = ensureAttachment(id);
            const sizes = attachment.get('sizes') || {};
            const url = sizes.thumbnail?.url || attachment.get('url');
            if (!url) {
                return;
            }
            const img = $('<img />', {
                src: url,
                class: 'winmo-gallery-thumb',
                loading: 'lazy',
            });
            $preview.append(img);
        });
    };

    $(document).ready(function () {
        const $wrapper = $('.winmo-gallery-wrapper');
        if (!$wrapper.length) return;

        if (typeof wp === 'undefined' || !wp.media || !wp.media.attachment) {
            return;
        }

        const locked = !!(window.winmoGallery && window.winmoGallery.locked);
        const $input = $('#winmo_gallery_ids');
        const $preview = $('#winmo-gallery-preview');

        const initIds = parseIds($input.val());
        renderThumbs(initIds, $preview);

        if (locked) {
            return;
        }

        let frame;

        $('.winmo-gallery-add').on('click', function (e) {
            e.preventDefault();

            const openFrame = () => {
                frame = wp.media({
                    frame: 'post',
                    state: 'gallery-edit',
                    multiple: true,
                });

                frame.on('open', function () {
                    const ids = parseIds($input.val());
                    const library = frame.states.get('gallery-edit').get('library');
                    library.reset();
                    ids.forEach((id) => {
                        const attachment = ensureAttachment(id);
                        if (attachment) {
                            library.add(attachment);
                        }
                    });
                });

                frame.on('update', function () {
                    const library = frame.states.get('gallery-edit').get('library');
                    const ids = library.map((model) => model.get('id'));
                    $input.val(ids.join(','));
                    renderThumbs(ids, $preview);
                });

                frame.open();
            };

            if (frame) {
                frame.open();
                return;
            }

            openFrame();
        });

        $('.winmo-gallery-clear').on('click', function (e) {
            e.preventDefault();
            $input.val('');
            $preview.empty();
            if (frame) {
                const library = frame.states.get('gallery-edit').get('library');
                library.reset();
            }
        });
    });
})(jQuery);
