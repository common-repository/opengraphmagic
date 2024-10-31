<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OpenGraphMagic_Admin_Form {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array($this, 'settings') );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( "admin_post_opengraphmagic_clear_cache", array ( $this, 'clear_cache_action' ) );
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script( 'opengraphmagic-admin-script', plugin_dir_url( __FILE__ ) . 'js/opengraphmagic-admin.js', array('jquery'), '1.0.0', true );
    }

    public function admin_menu(): void
    {
        $page = add_menu_page(
            __('OpenGraphMagic Settings', 'opengraphmagic'),
	        'OpenGraphMagic',
            'manage_options',
            'opengraph-magic', array($this, 'settings_form'),
            'dashicons-images-alt2'
        );

	    add_action( "load-$page", array ( $this, 'show_messages' ) );
    }

    public function settings_form(): void
    {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html(get_admin_page_title()) ?></h2>
            <div style="max-width: 800px;">
            <p>Welcome to the OpenGraphMagic plugin settings page! OpenGraphMagic simplifies and automates the creation of OpenGraph images for your website pages by generating real-time screenshots. This enhances your website’s click-through rate (CTR) when shared across various social media platforms like X, LinkedIn, Slack, and more.</p>
            <p>Our plugin integrates with the powerful ScreenshotOne and Pikwy screenshot services to capture high-quality images of your webpages, ensuring your shared content always looks professional and engaging.</p>
            <p>Configure your plugin settings below to start boosting your social media presence with eye-catching OpenGraph images.</p>
            </div>
            <?php settings_errors('opengraphmagic_messages'); ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="POST">
                <?php
                settings_fields( 'opengraphmagic_setting_group' );
                do_settings_sections( 'opengraphmagic_setting_page' );
                submit_button();
                ?>
            </form>
            <hr>
            <h4 class="title">There are <?php echo (int) $this->get_screenshot_count(); ?> cached opengraph images cached at the moment</h4>
            <form action="<?php echo esc_url(admin_url( 'admin-post.php' )); ?>" method="POST">
                <input type="hidden" name="action" value="opengraphmagic_clear_cache">
                <?php wp_nonce_field( 'opengraphmagic_clear_cache', 'opengraphmagic_clear_cache_nonce', FALSE ); ?>
                <?php submit_button( 'Clear cache', 'large', 'submit', true,  ['id' => 'clear-cache'] ); ?>
            </form>
        </div>
        <?php
    }

    private function get_screenshot_count()
    {
        global $wpdb;
	    $cache_key = 'opengraphmagic_image_count';

	    $db_result = wp_cache_get( $cache_key );
	    if( false === $db_result ){
		    $db_result = $wpdb->get_row("SELECT COUNT(*) AS THE_COUNT FROM $wpdb->postmeta WHERE meta_key = 'opengraphmagic_image_creation_time'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		    wp_cache_set( $cache_key, $db_result, '', 3600 );
	    }

	    return $db_result->THE_COUNT;
    }

    public function settings(): void
    {
	    add_settings_section( 'section_opengraphmagic_keys', __( 'Third-Party API Settings', 'opengraphmagic' ), '', 'opengraphmagic_setting_page' );
	    register_setting( 'opengraphmagic_setting_group', 'opengraphmagic_service_options', [
		    'type'              => 'array',
		    'sanitize_callback' => array ( $this, 'sanitize_service_options')
        ]);
	    add_settings_field(
		    'opengraphmagic_service_type',
		    'Select Third-Party Service',
		    array($this, 'service_type_display'),
		    'opengraphmagic_setting_page',
		    'section_opengraphmagic_keys',
		    array()
	    );
        add_settings_field(
            'opengraphmagic_screenshot_one_key_field',
            'ScreenshotOne API key',
            array($this, 'screenshot_one_key_display'),
            'opengraphmagic_setting_page',
            'section_opengraphmagic_keys',
            array()
        );
	    add_settings_field(
		    'opengraphmagic_pikwy_access_token_field',
		    'Pikwy Access Token',
		    array($this, 'pikwy_access_token_display'),
		    'opengraphmagic_setting_page',
		    'section_opengraphmagic_keys',
		    array()
	    );
	    add_settings_section( 'section_opengraphmagic_cache', __( 'Cache Settings', 'opengraphmagic' ), '', 'opengraphmagic_setting_page' );
	    register_setting( 'opengraphmagic_setting_group', 'opengraphmagic_ttl', [
            'type' => 'integer',
            'sanitize_callback' => array ( $this, 'validate_ttl')
        ]);
	    add_settings_field(
		    'opengraphmagic_ttl_field',
		    'TTL for cached images (days)',
		    array($this, 'ttl_field_display'),
		    'opengraphmagic_setting_page',
		    'section_opengraphmagic_cache',
		    array()
	    );
    }

    public function sanitize_service_options($data) {
	    $old_options = get_option('opengraphmagic_service_options');
	    $has_errors = false;

        $data['service_type'] = sanitize_key($data['service_type'] ?? '');
	    if (empty($data['service_type']) || !in_array($data['service_type'], ['screenshot_one', 'pikwy'], true)) {
		    add_settings_error('opengraphmagic_messages', 'opengraphmagic_service_type', __('Invalid Type', 'opengraphmagic'));

		    $has_errors = true;
	    }

	    $data['screenshot_one_key'] = sanitize_text_field($data['screenshot_one_key'] ?? '');
	    if ($data['service_type'] === 'screenshot_one' && empty($data['screenshot_one_key'])) {
		    add_settings_error(
                'opengraphmagic_messages',
                'opengraphmagic_screenshot_one_key',
                __('ScreenshotOne API key is required when ScreenshotOne service is selected', 'opengraphmagic')
            );

		    $has_errors = true;
	    }

	    if (!empty($data['screenshot_one_key'])) {
		    $pikwyService = new OpenGraphMagic_ScreenshotOne_Generator($data['screenshot_one_key']);
		    if (!$pikwyService->validate()) {
			    add_settings_error(
				    'opengraphmagic_messages',
				    'opengraphmagic_screenshot_one_key_invalid',
				    __('Invalid ScreenshotOne API key', 'opengraphmagic')
			    );

			    $has_errors = true;
		    }
	    }

	    $data['pikwy_access_token'] = sanitize_text_field($data['pikwy_access_token'] ?? '');
	    if ($data['service_type'] === 'pikwy' && empty($data['pikwy_access_token'])) {
		    add_settings_error(
			    'opengraphmagic_messages',
			    'opengraphmagic_pikwy_access_token',
			    __('Pikwy Access Token is required when Pikwy service is selected', 'opengraphmagic')
		    );

		    $has_errors = true;
	    }

	    if (!empty($data['pikwy_access_token'])) {
            $pikwyService = new OpenGraphMagic_Pikwy($data['pikwy_access_token']);
            if (!$pikwyService->validate()) {
	            add_settings_error(
		            'opengraphmagic_messages',
		            'opengraphmagic_pikwy_access_token_invalid',
		            __('Invalid Pikwy Access Token', 'opengraphmagic')
	            );

	            $has_errors = true;
            }
	    }

	    if ($has_errors) {
		    $data = $old_options;
	    }

	    return $data;
    }

	public function validate_ttl($ttl) {
        if ($ttl < 0) {
	        add_settings_error('opengraphmagic_messages', 'opengraphmagic_ttl', __('Invalid TTL', 'opengraphmagic'));
            return get_option('opengraphmagic_ttl');
        }

        return $ttl;
    }

    public function screenshot_one_key_display(): void
    {
        $val = get_option('opengraphmagic_service_options');
        $val = $val['screenshot_one_key'] ?? '';
        ?>
        <div id="screenshotone-section">
            <input id="opengraphmagic_service_options_screenshot_one_key" name="opengraphmagic_service_options[screenshot_one_key]" type="password" value="<?php echo esc_attr($val);?>">
            <p class="description">Visit <a href="https://screenshotone.com/?ref=opengraphmagic.com" target="_blank">screenshotone.com</a> website to create an API key.</p>
        </div>
        <?php
    }

    public function service_type_display() {
        $services = [
            'screenshot_one' => __('ScreenshotOne', 'opengraphmagic'),
            'pikwy' => __('Pikwy', 'opengraphmagic')
        ];
        $val = get_option('opengraphmagic_service_options');
        $val = $val['service_type'] ?? 'screenshot_one';
        foreach ( $services as $key => $serviceName ) {
            ?>
            <p>
                <label>
                    <input
                            name="opengraphmagic_service_options[service_type]"
                            type="radio"
                            value="<?php echo esc_attr($key);?>"
                        <?php echo ($key === $val) ? 'checked' : ''?>
                            class="opengraphmagic-service-type"
                    >
                    <?php echo esc_html($serviceName) ?>
                </label>
            </p>
            <?php
        }
    }

    public function pikwy_access_token_display(): void
    {
        $val = get_option('opengraphmagic_service_options');
        $val = $val['pikwy_access_token'] ?? '';
        ?>
        <div id="pikwy-section">
            <input id="opengraphmagic_service_options_pikwy_access_token" name="opengraphmagic_service_options[pikwy_access_token]" type="password" value="<?php echo esc_attr($val);?>">
            <p class="description">Visit <a href="https://pikwy.com/?ref=opengraphmagic.com" target="_blank">pikwy.com</a> website to create an API key.</p>
        </div>
        <?php
    }

    public function ttl_field_display(): void
    {
	    $val = get_option('opengraphmagic_ttl', 60);
	    ?>
        <input name="opengraphmagic_ttl" type="number" min="0" value="<?php echo esc_attr($val);?>">
	    <?php
    }

    public function clear_cache_action() {
        if (!isset($_POST['opengraphmagic_clear_cache_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opengraphmagic_clear_cache_nonce'])), 'opengraphmagic_clear_cache')) {
            die('Invalid nonce.');
        }

	    delete_post_meta_by_key('opengraphmagic_image_creation_time');
	    $dir = wp_upload_dir()['basedir'] . '/opengraphmagic-images';
	    $files = list_files($dir);
        foreach ($files as $file) {
	        wp_delete_file_from_directory($file, $dir);
        }
	    wp_cache_delete('opengraphmagic_image_count');
	    wp_safe_redirect( admin_url( 'admin.php?page=opengraph-magic' ) );

    }

    public function show_messages() {
        $is_images_folder_created = opengraphmagic_create_images_folder();
        if ( ! $is_images_folder_created ) {
	        add_action( 'admin_notices', array( $this, 'render_folder_error_msg' ) );
        }
    }

    public function render_folder_error_msg() {
        ?>
	    <div class="notice notice-error">
            <p>Unable to create directory for cached images. Check permissions.</p>
        </div>
        <?php
    }
}

new OpenGraphMagic_Admin_Form();