<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

interface OpenGraphMagic_Image_Generator_Contract {
	public function generate(string $url);
	public function validate(): bool;
}