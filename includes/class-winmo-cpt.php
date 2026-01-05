<?php

if (!defined('ABSPATH')) {
    exit;
}

class Winmo_CPT
{
    public const POST_TYPE = 'winmo_property';

    /**
     * Register the CPT.
     */
    public function register()
    {
        $labels = [
            'name' => __('Inmuebles', 'winmo-core'),
            'singular_name' => __('Inmueble', 'winmo-core'),
            'add_new' => __('Añadir nuevo', 'winmo-core'),
            'add_new_item' => __('Añadir nuevo inmueble', 'winmo-core'),
            'edit_item' => __('Editar inmueble', 'winmo-core'),
            'new_item' => __('Nuevo inmueble', 'winmo-core'),
            'view_item' => __('Ver inmueble', 'winmo-core'),
            'search_items' => __('Buscar inmuebles', 'winmo-core'),
            'not_found' => __('No se encontraron inmuebles', 'winmo-core'),
            'not_found_in_trash' => __('No hay inmuebles en la papelera', 'winmo-core'),
            'all_items' => __('Todos los inmuebles', 'winmo-core'),
            'menu_name' => __('Inmuebles', 'winmo-core'),
            'name_admin_bar' => __('Inmueble', 'winmo-core'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'exclude_from_search' => true,
            'has_archive'         => true,
            'supports'            => ['title', 'editor', 'thumbnail'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'show_in_rest'        => true,
            'rewrite'             => ['slug' => 'inmuebles'],
            'menu_icon'           => 'dashicons-building',
        ];

        register_post_type(self::POST_TYPE, $args);

        if (method_exists($this, 'block_rest_if_no_license')) {
            add_filter('rest_pre_insert_' . self::POST_TYPE, [$this, 'block_rest_if_no_license'], 10, 2);
        }
        if (method_exists($this, 'filter_row_actions')) {
            add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);
        }
        if (method_exists($this, 'maybe_hide_add_new_button')) {
            add_action('admin_head-edit.php', [$this, 'maybe_hide_add_new_button']);
        }
        add_filter('manage_edit-' . self::POST_TYPE . '_columns', [$this, 'property_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_property_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortable_property_columns']);
        add_action('pre_get_posts', [$this, 'maybe_sort_property']);
        add_action('restrict_manage_posts', [$this, 'add_owner_filter']);

        // Ajax handler registrado aquí (init) para disponibilidad en admin.
        add_action('wp_ajax_winmo_geocode', [$this, 'ajax_geocode']);
    }

    /**
     * Register post meta.
     */
    public function register_meta()
    {
        $string_meta = [
            'winmo_ref',
            'winmo_operation',
            'winmo_type',
            'winmo_city',
            'winmo_province',
            'winmo_status',
            'winmo_address',
            'winmo_postcode',
            'winmo_country',
        ];

        foreach ($string_meta as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_string'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [$this, 'auth_meta'],
            ]);
        }

        $int_meta = [
            'winmo_rooms',
            'winmo_baths',
            'winmo_built_area',
        ];

        foreach ($int_meta as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_int'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [$this, 'auth_meta'],
            ]);
        }

        register_post_meta(self::POST_TYPE, 'winmo_price', [
            'type' => 'number',
            'sanitize_callback' => [$this, 'sanitize_float'],
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        register_post_meta(self::POST_TYPE, 'winmo_lat', [
            'type' => 'number',
            'sanitize_callback' => [$this, 'sanitize_float_nullable'],
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        register_post_meta(self::POST_TYPE, 'winmo_lng', [
            'type' => 'number',
            'sanitize_callback' => [$this, 'sanitize_float_nullable'],
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        register_post_meta(self::POST_TYPE, 'winmo_gallery', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'sanitize_callback' => [$this, 'sanitize_gallery'],
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        register_post_meta(self::POST_TYPE, 'winmo_owner_id', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);
    }

    /**
     * Restrict meta editing based on capability.
     *
     * @return bool
     */
    public function auth_meta()
    {
        return current_user_can('manage_options');
    }

    /**
     * Add metabox.
     */
    public function register_metabox()
    {
        add_meta_box(
            'winmo_property_data',
            __('Datos del inmueble', 'winmo-core'),
            [$this, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * Render metabox content.
     *
     * @param WP_Post $post
     */
    public function render_metabox($post)
    {
        wp_nonce_field('winmo_property_meta', 'winmo_property_meta_nonce');
        $meta = [
            'winmo_ref' => get_post_meta($post->ID, 'winmo_ref', true),
            'winmo_operation' => get_post_meta($post->ID, 'winmo_operation', true),
            'winmo_type' => get_post_meta($post->ID, 'winmo_type', true),
            'winmo_price' => get_post_meta($post->ID, 'winmo_price', true),
            'winmo_city' => get_post_meta($post->ID, 'winmo_city', true),
            'winmo_province' => get_post_meta($post->ID, 'winmo_province', true),
            'winmo_rooms' => get_post_meta($post->ID, 'winmo_rooms', true),
            'winmo_baths' => get_post_meta($post->ID, 'winmo_baths', true),
            'winmo_built_area' => get_post_meta($post->ID, 'winmo_built_area', true),
            'winmo_lat' => get_post_meta($post->ID, 'winmo_lat', true),
            'winmo_lng' => get_post_meta($post->ID, 'winmo_lng', true),
            'winmo_status' => get_post_meta($post->ID, 'winmo_status', true),
            'winmo_gallery' => get_post_meta($post->ID, 'winmo_gallery', true),
            'winmo_address' => get_post_meta($post->ID, 'winmo_address', true),
            'winmo_postcode' => get_post_meta($post->ID, 'winmo_postcode', true),
            'winmo_country' => get_post_meta($post->ID, 'winmo_country', true),
            'winmo_owner_id' => get_post_meta($post->ID, 'winmo_owner_id', true),
        ];

        $operations = [
            'venta' => __('Venta', 'winmo-core'),
            'alquiler' => __('Alquiler', 'winmo-core'),
        ];

        $types = [
            'piso' => __('Piso', 'winmo-core'),
            'casa' => __('Casa', 'winmo-core'),
            'local' => __('Local', 'winmo-core'),
            'terreno' => __('Terreno', 'winmo-core'),
            'otro' => __('Otro', 'winmo-core'),
        ];

        $statuses = [
            'activo' => __('Activo', 'winmo-core'),
            'reservado' => __('Reservado', 'winmo-core'),
            'vendido' => __('Vendido', 'winmo-core'),
        ];

        $disabled = $this->is_premium_enabled() ? '' : 'disabled readonly aria-disabled="true"';
        $gallery_ids = is_array($meta['winmo_gallery']) ? array_map('absint', $meta['winmo_gallery']) : [];
        $country_value = $meta['winmo_country'] !== '' ? $meta['winmo_country'] : 'ES';
        $clients = $this->get_clients_for_select();
        $related_operations = $this->get_operations_for_property($post->ID);

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="winmo_ref"><?php esc_html_e('Referencia', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_ref" name="winmo_ref" value="<?php echo esc_attr($meta['winmo_ref']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_operation"><?php esc_html_e('Operación', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_operation" name="winmo_operation" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($operations as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_operation'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_type"><?php esc_html_e('Tipo', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_type" name="winmo_type" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($types as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_price"><?php esc_html_e('Precio', 'winmo-core'); ?></label></th>
                    <td><input type="number" step="0.01" id="winmo_price" name="winmo_price" value="<?php echo esc_attr($meta['winmo_price']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_rooms"><?php esc_html_e('Habitaciones', 'winmo-core'); ?></label></th>
                    <td><input type="number" id="winmo_rooms" name="winmo_rooms" value="<?php echo esc_attr($meta['winmo_rooms']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_baths"><?php esc_html_e('Baños', 'winmo-core'); ?></label></th>
                    <td><input type="number" id="winmo_baths" name="winmo_baths" value="<?php echo esc_attr($meta['winmo_baths']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_built_area"><?php esc_html_e('Superficie construida (m²)', 'winmo-core'); ?></label></th>
                    <td><input type="number" id="winmo_built_area" name="winmo_built_area" value="<?php echo esc_attr($meta['winmo_built_area']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_status"><?php esc_html_e('Estado', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_status" name="winmo_status" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($statuses as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_status'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_owner_id"><?php esc_html_e('Cliente / Propietario', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_owner_id" name="winmo_owner_id" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Sin asignar', 'winmo-core'); ?></option>
                            <?php if (!empty($clients)) : ?>
                                <?php foreach ($clients as $client_id => $client_title) : ?>
                                    <option value="<?php echo esc_attr($client_id); ?>" <?php selected((int) $meta['winmo_owner_id'], (int) $client_id); ?>><?php echo esc_html($client_title); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Selecciona el cliente propietario.', 'winmo-core'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_gallery_ids"><?php esc_html_e('Galería', 'winmo-core'); ?></label></th>
                    <td>
                        <div class="winmo-gallery-wrapper" data-locked="<?php echo $this->is_premium_enabled() ? 'false' : 'true'; ?>">
                            <div class="winmo-gallery-preview" id="winmo-gallery-preview">
                                <?php if (!empty($gallery_ids)) : ?>
                                    <?php foreach ($gallery_ids as $image_id) : ?>
                                        <?php echo wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'winmo-gallery-thumb']); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="winmo_gallery_ids" name="winmo_gallery" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>" <?php echo $disabled; ?> />
                            <p class="winmo-gallery-actions">
                                <button type="button" class="button winmo-gallery-add" <?php echo $disabled; ?>><?php esc_html_e('Añadir/editar galería', 'winmo-core'); ?></button>
                                <button type="button" class="button winmo-gallery-clear" <?php echo $disabled; ?>><?php esc_html_e('Vaciar', 'winmo-core'); ?></button>
                            </p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Operaciones relacionadas', 'winmo-core'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Fecha/Hora', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Tipo', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Estado', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Cliente', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Acciones', 'winmo-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($related_operations)) : ?>
                    <?php foreach ($related_operations as $op) : ?>
                        <tr>
                            <td><?php echo esc_html($op['datetime']); ?></td>
                            <td><?php echo esc_html($op['type']); ?></td>
                            <td><?php echo esc_html($op['status']); ?></td>
                            <td><?php echo esc_html($op['client']); ?></td>
                            <td>
                                <?php if ($op['edit_link'] && $this->is_premium_enabled()) : ?>
                                    <a href="<?php echo esc_url($op['edit_link']); ?>"><?php esc_html_e('Editar', 'winmo-core'); ?></a>
                                <?php elseif ($op['view_link']) : ?>
                                    <a href="<?php echo esc_url($op['view_link']); ?>"><?php esc_html_e('Ver', 'winmo-core'); ?></a>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e('No hay operaciones.', 'winmo-core'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($this->is_premium_enabled()) : ?>
            <p>
                <a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=winmo_operation&winmo_property_id=' . (int) $post->ID . '&winmo_client_id=' . (int) $meta['winmo_owner_id'])); ?>"><?php esc_html_e('Añadir operación', 'winmo-core'); ?></a>
            </p>
        <?php endif; ?>

        <h2><?php esc_html_e('Ubicación', 'winmo-core'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="winmo_address_lookup"><?php esc_html_e('Dirección / Buscar ubicación', 'winmo-core'); ?></label></th>
                    <td>
                        <input type="text" id="winmo_address_lookup" class="regular-text" <?php echo $disabled; ?> />
                        <p class="description"><?php esc_html_e('No se guarda. Úsalo para buscar coordenadas.', 'winmo-core'); ?></p>
                        <p class="winmo-geocode-actions">
                            <button type="button" class="button" id="winmo-geocode-btn" <?php echo $disabled; ?>><?php esc_html_e('Buscar coordenadas', 'winmo-core'); ?></button>
                            <span id="winmo-geocode-status" class="description"></span>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_address"><?php esc_html_e('Dirección', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_address" name="winmo_address" value="<?php echo esc_attr($meta['winmo_address']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_postcode"><?php esc_html_e('Código postal', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_postcode" name="winmo_postcode" value="<?php echo esc_attr($meta['winmo_postcode']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_city"><?php esc_html_e('Ciudad', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_city" name="winmo_city" value="<?php echo esc_attr($meta['winmo_city']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_province"><?php esc_html_e('Provincia', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_province" name="winmo_province" value="<?php echo esc_attr($meta['winmo_province']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_country"><?php esc_html_e('País', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_country" name="winmo_country" value="<?php echo esc_attr($country_value); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_lat"><?php esc_html_e('Latitud', 'winmo-core'); ?></label></th>
                    <td><input type="number" step="0.000001" id="winmo_lat" name="winmo_lat" value="<?php echo esc_attr($meta['winmo_lat']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_lng"><?php esc_html_e('Longitud', 'winmo-core'); ?></label></th>
                    <td><input type="number" step="0.000001" id="winmo_lng" name="winmo_lng" value="<?php echo esc_attr($meta['winmo_lng']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
            </tbody>
        </table>
        <?php
        if (!$this->is_premium_enabled()) {
            echo '<p class="description">' . esc_html__('Licencia requerida para editar.', 'winmo-core') . '</p>';
        }
    }

    /**
     * Save meta data.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_meta($post_id, $post, $update)
    {
        if (!$this->is_premium_enabled()) {
            add_filter('redirect_post_location', [$this, 'add_license_notice_query']);
            return;
        }

        if (!isset($_POST['winmo_property_meta_nonce']) || !wp_verify_nonce($_POST['winmo_property_meta_nonce'], 'winmo_property_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $fields = [
            'winmo_ref' => [$this, 'sanitize_string'],
            'winmo_operation' => [$this, 'sanitize_operation'],
            'winmo_type' => [$this, 'sanitize_type'],
            'winmo_price' => [$this, 'sanitize_float'],
            'winmo_city' => [$this, 'sanitize_string'],
            'winmo_province' => [$this, 'sanitize_string'],
            'winmo_rooms' => [$this, 'sanitize_int'],
            'winmo_baths' => [$this, 'sanitize_int'],
            'winmo_built_area' => [$this, 'sanitize_int'],
            'winmo_lat' => [$this, 'sanitize_float_nullable'],
            'winmo_lng' => [$this, 'sanitize_float_nullable'],
            'winmo_status' => [$this, 'sanitize_status'],
            'winmo_gallery' => [$this, 'sanitize_gallery'],
            'winmo_address' => [$this, 'sanitize_string'],
            'winmo_postcode' => [$this, 'sanitize_string'],
            'winmo_country' => [$this, 'sanitize_string'],
            'winmo_owner_id' => 'absint',
        ];

        foreach ($fields as $key => $callback) {
            if (!isset($_POST[$key])) {
                delete_post_meta($post_id, $key);
                continue;
            }

            $value = call_user_func($callback, wp_unslash($_POST[$key]));
            if ($value === null || $value === '') {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    /**
     * Prevent new property creation when license is invalid.
     */
    public function block_new_if_no_license()
    {
        global $pagenow;
        if ($pagenow !== 'post-new.php') {
            return;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if ($this->is_premium_enabled()) {
            return;
        }

        wp_safe_redirect(add_query_arg(['post_type' => self::POST_TYPE, 'winmo_license_required' => 1], admin_url('edit.php')));
        exit;
    }

    /**
     * Prevent data mutation when not premium.
     *
     * @param array $data
     * @param array $postarr
     * @return array
     */
    public function maybe_lock_post_data($data, $postarr)
    {
        if ($data['post_type'] !== self::POST_TYPE) {
            return $data;
        }

        if ($this->is_premium_enabled()) {
            return $data;
        }

        if (!empty($postarr['ID'])) {
            $existing = get_post((int) $postarr['ID']);
            if ($existing) {
                $data['post_title'] = $existing->post_title;
                $data['post_content'] = $existing->post_content;
                $data['post_excerpt'] = $existing->post_excerpt;
                $data['post_status'] = $existing->post_status;
            }
        }

        return $data;
    }

    /**
     * Show notice when save is blocked.
     */
    public function maybe_show_edit_notice()
    {
        if (!isset($_GET['winmo_license_required'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        $message = __('Licencia requerida para editar.', 'winmo-core');
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Append query arg when save is blocked.
     *
     * @param string $location
     * @return string
     */
    public function add_license_notice_query($location)
    {
        return add_query_arg('winmo_license_required', 1, $location);
    }

    /**
     * AJAX handler para geocodificar dirección usando Nominatim.
     */
    public function ajax_geocode()
    {
        check_ajax_referer('winmo_geocode_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'winmo-core')], 403);
        }

        if (!$this->is_premium_enabled()) {
            wp_send_json_error(['message' => __('Licencia requerida para editar.', 'winmo-core')], 403);
        }

        $address = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
        if ($address === '') {
            wp_send_json_error(['message' => __('Introduce una dirección para buscar.', 'winmo-core')], 400);
        }

        $result = $this->geocode_with_nominatim($address);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        wp_send_json_success($result);
    }

    /**
     * Geocodifica una dirección con Nominatim y cachea la respuesta.
     *
     * @param string $address
     * @return array|WP_Error
     */
    private function geocode_with_nominatim($address)
    {
        $query = trim($address);
        if ($query === '') {
            return new WP_Error('winmo_geocode_empty', __('Introduce una dirección para buscar.', 'winmo-core'));
        }

        $cache_key = 'winmo_geocode_' . md5(strtolower($query));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $endpoint = 'https://nominatim.openstreetmap.org/search';
        $args = [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WinmoCore/' . WINMO_CORE_VERSION . ' (WP plugin geocode; ' . home_url() . ')',
            ],
        ];

        $url = add_query_arg(
            [
                'format' => 'json',
                'limit' => 1,
            'q' => $query,
        ],
        $endpoint
    );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return new WP_Error('winmo_geocode_http', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('winmo_geocode_status', sprintf(__('Error de geocodificación (%d).', 'winmo-core'), $code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('winmo_geocode_json', __('Respuesta de geocodificación inválida.', 'winmo-core'));
        }

        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return new WP_Error('winmo_geocode_no_results', __('No se encontraron resultados.', 'winmo-core'), ['status' => 404]);
        }

        $result = [
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
            'display_name' => isset($data[0]['display_name']) ? sanitize_text_field($data[0]['display_name']) : '',
        ];

        set_transient($cache_key, $result, DAY_IN_SECONDS / 2);

        return $result;
    }

    /**
     * Enqueue admin assets for readonly behavior.
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook)
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $locked = !$this->is_premium_enabled();

        wp_enqueue_style('winmo-admin-gallery', WINMO_CORE_URL . 'assets/admin-gallery.css', [], WINMO_CORE_VERSION);
        wp_enqueue_script('winmo-admin-gallery', WINMO_CORE_URL . 'assets/admin-gallery.js', ['jquery'], WINMO_CORE_VERSION, true);
        wp_localize_script('winmo-admin-gallery', 'winmoGallery', [
            'locked' => $locked,
        ]);

        wp_enqueue_script('winmo-admin-geocode', WINMO_CORE_URL . 'assets/admin-geocode.js', ['jquery'], WINMO_CORE_VERSION, true);
        wp_localize_script('winmo-admin-geocode', 'winmoGeocode', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('winmo_geocode_nonce'),
            'locked' => $locked,
        ]);

        if ($locked) {
            wp_enqueue_style('winmo-admin', WINMO_CORE_URL . 'assets/admin.css', [], WINMO_CORE_VERSION);
        } else {
            wp_enqueue_media();
        }
    }

    /**
     * Add locked class to body when license invalid.
     *
     * @param string $classes
     * @return string
     */
    public function filter_admin_body_class($classes)
    {
        global $post_type;
        if ($post_type === self::POST_TYPE && !$this->is_premium_enabled()) {
            $classes .= ' winmo-locked';
        }

        return $classes;
    }

    public function property_columns($columns)
    {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = __('Título', 'winmo-core');
        $new['winmo_owner_id'] = __('Propietario', 'winmo-core');
        $new['winmo_op_type'] = __('Operación', 'winmo-core');
        $new['winmo_status'] = __('Estado', 'winmo-core');
        $new['winmo_price'] = __('Precio', 'winmo-core');
        $new['winmo_location'] = __('Ubicación', 'winmo-core');
        $new['winmo_ops_count'] = __('# Operaciones', 'winmo-core');
        return $new;
    }

    public function render_property_column($column, $post_id)
    {
        switch ($column) {
            case 'winmo_owner_id':
                $owner_id = (int) get_post_meta($post_id, 'winmo_owner_id', true);
                if ($owner_id) {
                    $title = get_the_title($owner_id);
                    $link = get_edit_post_link($owner_id);
                    echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>' : esc_html($title);
                } else {
                    esc_html_e('—', 'winmo-core');
                }
                break;
            case 'winmo_op_type':
                $type = get_post_meta($post_id, 'winmo_operation', true);
                $types = [
                    'venta' => __('Venta', 'winmo-core'),
                    'alquiler' => __('Alquiler', 'winmo-core'),
                ];
                echo isset($types[$type]) ? esc_html($types[$type]) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_status':
                $status = get_post_meta($post_id, 'winmo_status', true);
                $statuses = [
                    'activo' => __('Activo', 'winmo-core'),
                    'reservado' => __('Reservado', 'winmo-core'),
                    'vendido' => __('Vendido', 'winmo-core'),
                ];
                echo isset($statuses[$status]) ? esc_html($statuses[$status]) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_price':
                $price = get_post_meta($post_id, 'winmo_price', true);
                echo $price !== '' ? esc_html(number_format_i18n((float) $price, 0)) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_location':
                $city = get_post_meta($post_id, 'winmo_city', true);
                $prov = get_post_meta($post_id, 'winmo_province', true);
                $parts = array_filter([$city, $prov]);
                echo !empty($parts) ? esc_html(implode(', ', $parts)) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_ops_count':
                $count = $this->count_operations_for_property($post_id);
                echo esc_html($count);
                break;
        }
    }

    public function sortable_property_columns($columns)
    {
        $columns['winmo_price'] = 'winmo_price';
        $columns['winmo_status'] = 'winmo_status';
        $columns['winmo_ops_count'] = 'winmo_ops_count';
        return $columns;
    }

    public function maybe_sort_property($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'winmo_price') {
            $query->set('meta_key', 'winmo_price');
            $query->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'winmo_status') {
            $query->set('meta_key', 'winmo_status');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'winmo_ops_count') {
            // Ordenar por número de operaciones via subquery meta; simple fallback: orderby title but meta key to fetch counts.
            $query->set('meta_key', 'winmo_ops_count_cache');
            $query->set('orderby', 'meta_value_num');
        }

        $owner_filter = isset($_GET['winmo_owner_id']) ? absint($_GET['winmo_owner_id']) : 0;
        if ($owner_filter) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'key' => 'winmo_owner_id',
                'value' => $owner_filter,
                'compare' => '=',
            ];
            $query->set('meta_query', $meta_query);
        }

        $dashboard_filter = isset($_GET['winmo_filter']) ? sanitize_text_field(wp_unslash($_GET['winmo_filter'])) : '';
        if ($dashboard_filter === 'missing_gallery') {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'winmo_gallery',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'winmo_gallery',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => 'winmo_gallery',
                    'value' => 'a:0:{}',
                    'compare' => '=',
                ],
                [
                    'key' => 'winmo_gallery',
                    'value' => '[]',
                    'compare' => '=',
                ],
            ];
            $query->set('meta_query', $meta_query);
        } elseif ($dashboard_filter === 'missing_owner') {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'winmo_owner_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'winmo_owner_id',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => 'winmo_owner_id',
                    'value' => '0',
                    'compare' => '=',
                ],
            ];
            $query->set('meta_query', $meta_query);
        } elseif ($dashboard_filter === 'missing_coords') {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'winmo_lat',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => 'winmo_lng',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => 'winmo_lat',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'winmo_lng',
                    'compare' => 'NOT EXISTS',
                ],
            ];
            $query->set('meta_query', $meta_query);
        }
    }

    public function add_owner_filter($post_type)
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        $clients = $this->get_clients_for_select();
        $current = isset($_GET['winmo_owner_id']) ? absint($_GET['winmo_owner_id']) : 0;
        ?>
        <select name="winmo_owner_id">
            <option value="0"><?php esc_html_e('Todos los propietarios', 'winmo-core'); ?></option>
            <?php foreach ($clients as $id => $label) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($current, $id); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Check if premium is enabled.
     *
     * @return bool
     */
    public function is_premium_enabled()
    {
        if (function_exists('winmo_premium_enabled')) {
            return (bool) winmo_premium_enabled();
        }

        return true;
    }

    /**
     * Bloquea creación/edición vía REST cuando no hay licencia.
     *
     * @param WP_Post         $prepared_post
     * @param WP_REST_Request $request
     * @return WP_Post|WP_Error
     */
    public function block_rest_if_no_license($prepared_post, $request)
    {
        if ($this->is_premium_enabled()) {
            return $prepared_post;
        }

        return new WP_Error(
            'winmo_license_required',
            __('Licencia requerida para editar.', 'winmo-core'),
            ['status' => 403]
        );
    }

    /**
     * Quita acciones de edición cuando está en modo solo lectura.
     *
     * @param array   $actions
     * @param WP_Post $post
     * @return array
     */
    public function filter_row_actions($actions, $post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $actions;
        }

        if ($this->is_premium_enabled()) {
            return $actions;
        }

        unset($actions['edit'], $actions['inline hide-if-no-js'], $actions['trash']);

        return $actions;
    }

    /**
     * Oculta el botón "Añadir nuevo" en el listado cuando no hay licencia.
     */
    public function maybe_hide_add_new_button()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        if ($this->is_premium_enabled()) {
            return;
        }

        echo '<style>.page-title-action{display:none !important;}</style>';
    }

    public function sanitize_string($value)
    {
        return sanitize_text_field($value);
    }

    public function sanitize_int($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    public function sanitize_float($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (float) $value;
    }

    public function sanitize_float_nullable($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (float) $value;
    }

    public function sanitize_operation($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['venta', 'alquiler'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    public function sanitize_type($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['piso', 'casa', 'local', 'terreno', 'otro'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    public function sanitize_status($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['activo', 'reservado', 'vendido'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * Sanitiza la galería a un array de IDs de adjuntos.
     *
     * @param mixed $value
     * @return array
     */
    public function sanitize_gallery($value)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $value = array_filter(array_map('absint', $value));

        return array_values($value);
    }

    /**
     * Obtiene operaciones relacionadas con el inmueble.
     *
     * @param int $property_id
     * @return array
     */
    private function get_operations_for_property($property_id)
    {
        $results = [];
        $query = new WP_Query([
            'post_type' => 'winmo_operation',
            'posts_per_page' => 50,
            'post_status' => ['publish', 'draft', 'pending'],
            'meta_query' => [
                [
                    'key' => 'winmo_property_id',
                    'value' => (int) $property_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'winmo_op_datetime',
            'order' => 'DESC',
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $post) {
                $op_id = $post->ID;
                $datetime_raw = get_post_meta($op_id, 'winmo_op_datetime', true);
                $ts = $datetime_raw ? strtotime($datetime_raw) : false;
                $datetime = $ts ? date_i18n('d/m/Y H:i', $ts) : '';

                $type = get_post_meta($op_id, 'winmo_op_type', true);
                $status = get_post_meta($op_id, 'winmo_op_status', true);
                $client_id = (int) get_post_meta($op_id, 'winmo_client_id', true);

                $types = [
                    'visita' => __('Visita', 'winmo-core'),
                    'oferta' => __('Oferta', 'winmo-core'),
                    'reserva' => __('Reserva', 'winmo-core'),
                    'cierre' => __('Cierre', 'winmo-core'),
                    'cancelacion' => __('Cancelación', 'winmo-core'),
                ];

                $statuses = [
                    'pendiente' => __('Pendiente', 'winmo-core'),
                    'realizada' => __('Realizada', 'winmo-core'),
                    'cancelada' => __('Cancelada', 'winmo-core'),
                ];

                $client_label = $client_id ? get_the_title($client_id) : '';

                $results[] = [
                    'id' => $op_id,
                    'datetime' => $datetime ?: '—',
                    'type' => $types[$type] ?? '—',
                    'status' => $statuses[$status] ?? '—',
                    'client' => $client_label ?: '—',
                    'edit_link' => get_edit_post_link($op_id),
                    'view_link' => get_permalink($op_id),
                ];
            }
        }

        return $results;
    }

    /**
     * Obtiene clientes para el selector de propietario.
     *
     * @return array
     */
    private function get_clients_for_select()
    {
        $clients = [];
        $query = new WP_Query([
            'post_type' => 'winmo_client',
            'posts_per_page' => 200,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $client_id) {
                $clients[$client_id] = get_the_title($client_id);
            }
        }

        return $clients;
    }

    private function count_operations_for_property($property_id)
    {
        $query = new WP_Query([
            'post_type' => 'winmo_operation',
            'post_status' => ['publish', 'draft', 'pending'],
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'winmo_property_id',
                    'value' => (int) $property_id,
                    'compare' => '=',
                ],
            ],
        ]);
        return (int) $query->found_posts;
    }
}
