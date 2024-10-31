<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class OpenGraphMagic_Pikwy implements OpenGraphMagic_Image_Generator_Contract
{
	private const BASE_URL = 'https://api.pikwy.com';
	private string $access_token;

	public function __construct(string $key = null) {
		$this->access_token = $key ?? get_option('opengraphmagic_service_options')['pikwy_access_token'] ?? '';
	}

	public function validate(): bool
	{
		$defaultUrl = 'https://wikipedia.org';
		$testImage = $this->generate($defaultUrl);
		return (bool) $testImage;
	}

	public function generate( string $url ) {
		if (!$this->access_token) {
			return false;
		}
		$api_url = self::BASE_URL . '?' . http_build_query(
				[
					'tkn' => $this->access_token,
					'w' => 1200,
					'h' => 630,
					'f' => 'jpg',
					'full_page' => 0,
					'delay' => 0,
					'timeout' => 60000,
					'u' => $url,
					'rt' => 'json'
				]
			);

		$response = wp_remote_get($api_url, ['timeout' => 20]);

		if (is_wp_error($response)) {
			return false;
		}

		$body = wp_remote_retrieve_body($response);

		$body = json_decode($body, true);
		if (! isset($body['body'])) {
			return false;
		}

		return base64_decode($body['body']);
	}
}