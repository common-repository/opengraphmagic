<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Opengraph_Image {
	protected ?OpenGraphMagic_Image_Generator_Contract $generator = null;
	public function __construct() {
		$options = get_option('opengraphmagic_service_options');
		$generatorType = $options['service_type'] ?? 'screenshot_one';

		switch ($generatorType) {
			case 'screenshot_one':
				$this->generator = new OpenGraphMagic_ScreenshotOne_Generator();
				break;
			case 'pikwy':
				$this->generator = new OpenGraphMagic_Pikwy();
				break;
		}

		add_action('template_redirect', array($this, 'handle_image_endpoint'));
		add_action('wp_head', array($this, 'add_opengraph_tag'), 99);
		add_filter('wpseo_opengraph_image', array($this, 'alter_wpseo_opengraph_image'), 99);
	}

	public function handle_image_endpoint() {
		global $wp_filesystem;

		if( ! $wp_filesystem ){
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$connect_fs = WP_Filesystem(false, wp_upload_dir()['basedir'] . '/opengraphmagic-images', true);
			if (!$connect_fs) {
				wp_die('Unable to create a image. Check permissions.');
			}
		}

		$post_id = (int) get_query_var('opengraphmagic_post_id');
		if (!empty($post_id) && 'publish' === get_post_status($post_id)) {
			$ttl = get_option('opengraphmagic_ttl', 60);
			$timestamp = get_post_meta($post_id, 'opengraphmagic_image_creation_time', true);
			$dir = $wp_filesystem->find_folder(wp_upload_dir()['basedir'] . "/opengraphmagic-images");
			$image_path = trailingslashit($dir) . $post_id . '.jpg';
			if (!$timestamp || (time() - $timestamp > 86400 * $ttl) || !$wp_filesystem->exists($image_path)) {
				$image = $this->create_image($post_id);
				if ($image) {
					update_post_meta($post_id, "opengraphmagic_image_creation_time", time());
					wp_cache_delete('opengraphmagic_image_count');
					header('Content-Type: image/jpeg');
					echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					wp_die('Third-Party Service is not configured or returns an error response');
				}
			} else {
				header('Content-Type: image/jpeg');
                header('Content-Length: ' . filesize($image_path));
                echo $wp_filesystem->get_contents($image_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
			exit;
		}
	}

	private function create_image(int $post_id) {
		global $wp_filesystem;
		if (!$this->generator) {
			return false;
		}
		$url = get_permalink($post_id);
		$dir = $wp_filesystem->find_folder(wp_upload_dir()['basedir'] . "/opengraphmagic-images");
		$file = trailingslashit($dir) . $post_id . '.jpg';

		$image = $this->generator->generate($url);
		if ($image) {
			$wp_filesystem->put_contents($file, $image);
		}

		return $image;
	}

	function add_opengraph_tag() {
		if (!class_exists('WPSEO_Frontend')) {
			if (is_single() || is_page()) {
				$post_id = get_the_ID();
				$image_url = home_url('/og-image/' . $post_id);
				echo '<meta property="og:image" content="' . esc_url($image_url) . '" />';
			}
		}
	}

	function alter_wpseo_opengraph_image($image) {
		$post_id = get_the_ID();
		return home_url('/og-image/' . $post_id);
	}
}

new Opengraph_Image();