<?php

namespace LWS\WOOREWARDS\PRO\Ui\Woocommerce;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Show Content in a specific location inside a product page or product archive page */
class ProductContent
{
	private $variable = false;
	private $regular  = false;

	static function install()
	{
		$product = \get_option('lws_woorewards_product_preview_position', 'none');
		if ($product && ('none' != $product)) {
			\add_action($product, array(new self(), 'displayProductContent'));
		}
		$archive = \get_option('lws_woorewards_archive_product_preview_position', 'none');
		if ($archive && ('none' != $archive)) {
			\add_action($archive, array(new self(), 'displayArchiveProductContent'));
		}
	}

	function displayProductContent()
	{
		$this->displayContent(array(
			'regular' => array(
				'option' => 'lws_woorewards_product_preview_regular',
				'wpml'   => "WooRewards - Products Points Preview - Regular Product Content"
			),
			'variable' => array(
				'option' => 'lws_woorewards_product_preview_variable',
				'wpml'   => "WooRewards - Products Points Preview - Variable Product Content"
			)
		));
	}

	function displayArchiveProductContent()
	{
		$this->displayContent(array(
			'regular' => array(
				'option' => 'lws_woorewards_archive_product_preview_regular',
				'wpml'   => "WooRewards - Shop Points Preview - Regular Product Content"
			),
			'variable' => array(
				'option' => 'lws_woorewards_archive_product_preview_variable',
				'wpml'   => "WooRewards - Shop Points Preview - Variable Product Content"
			),
		));
	}

	function displayContent($settings = array())
	{
		global $product;
		if ($product && $settings && \is_a($product, 'WC_Product')) {
			$text = false;
			// variable could have a specific text
			if ($product->is_type('variable')) {
				if (false === $this->variable) {
					$this->variable = \trim(\get_option($settings['variable']['option'], ''));
					if ($this->variable) {
						$this->variable = \apply_filters('wpml_translate_single_string', $this->variable, 'Widgets', $settings['variable']['wpml']);
					}
				}
				$text = $this->variable;
			}
			// simple product or fallback
			if (!$text) {
				if (false === $this->regular) {
					$this->regular = \trim(\get_option($settings['regular']['option'], ''));
					if ($this->regular) {
						$this->regular = \apply_filters('wpml_translate_single_string', $this->regular, 'Widgets', $settings['regular']['wpml']);
					}
				}
				$text = $this->regular;
			}
			// show text
			if ($text) {
				echo \do_shortcode($text);
			}
		}
	}
}
