<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class OpenGraphMagic_ScreenshotOne_Generator implements OpenGraphMagic_Image_Generator_Contract
{
	private const BASE_URL = 'https://api.screenshotone.com';
	private string $key;

	public function __construct(string $key = null) {
		$this->key = $key ?? get_option('opengraphmagic_service_options')['screenshot_one_key'] ?? '';
	}

	public function validate(): bool
	{
		$api_url = self::BASE_URL . '/usage?' . http_build_query(
			[
				'access_key' => $this->key,
			]
		);
		$response = wp_remote_get($api_url, ['timeout' => 20]);

		if (is_wp_error($response)) {
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		return ! isset( $body['error_code'] );
	}

	public function generate(string $url) {
		if (!$this->key) {
			return false;
		}
		$api_url = self::BASE_URL . '/take?' . http_build_query(
			[
				'access_key' => $this->key,
				'viewport_width' => 1200,
				'viewport_height' => 630,
				'device_scale_factor' => 1,
				'image_quality' => 80,
				'format' => 'jpg',
				'block_ads' => 'true',
				'block_cookie_banners' => 'true',
				'full_page' => 'false',
				'block_trackers' => 'true',
				'block_banners_by_heuristics' => 'false',
				'delay' => 0,
				'timeout' => 60,
				'url' => $url,
			]
		);

		$response = wp_remote_get($api_url, ['timeout' => 20]);

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return false;
		}

		return wp_remote_retrieve_body($response);
	}
}