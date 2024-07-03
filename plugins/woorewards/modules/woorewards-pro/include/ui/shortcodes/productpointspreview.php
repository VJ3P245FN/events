<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class ProductPointsPreview
{
	public static function install()
	{
		$me = new self();
		// Shortcode
		\add_shortcode('wr_product_points', array($me, 'shortcode'));
		// Admin
		\add_filter('lws_woorewards_woocommerce_shortcodes', array($me, 'admin'), 5);
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-product-points-preview', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/product-points-preview.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-product-points-preview');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['productpoints'] = array(
			'id' => 'lws_woorewards_product_points_preview',
			'title' => __("Product Points Preview", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_product_points]',
				'description' =>  array('join' => '<br/>',
					__("Use this shortcode to show to your customers the points they will earn by purchasing the current product.", 'woorewards-pro'),
					__("Use the following options to change how information is displayed.", 'woorewards-pro'),
				),
				'help' => array('join' => '<br/>',
					__("Note this shortcode could be used in long form too to include a full sentence that will be displayed or not if product could generate points or not for current user.", 'woorewards-pro'),
					\apply_filters('lws_format_copypast', '[wr_product_points]Earn [points]![/wr_product_points]'),
					__("In this case, you will use a placeholder inside your shortcode content that will be replaced by the points value (working as the value attribute):", 'woorewards-pro'),
					array('tag' => 'ul',
						array('[points]',
							__("Number of points earned for a regular product. Displays a points range for variable products.", 'woorewards-pro'),
						), array('[points_min]',
							__("For variable products, displays the minimum of points that can be earned.", 'woorewards-pro'),
						), array('[points_max]',
							__("For variable products, displays the maximum of points that can be earned.", 'woorewards-pro'),
						),
					),
				),
				'options' => array(
					'system' => array(
						'option' => 'system',
						'desc'   => __("Select for which points and rewards systems you want to show points earned. If left empty, only the first system is displayed", 'woorewards-pro') .
							"<br/>" . __("You can find the points and rewards systems names in WooRewards → Settings → Points and Rewards", 'woorewards-pro'),
					),
					'id' => array(
						'option' => 'id',
						'desc' => __("The product id. If not specified, the shortcode will try to find the actual product from the page.", 'woorewards-pro'),
					),
					'value' => array(
						'option' => 'value',
						'desc' => __("(Optional) Select what value you want to display :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'regular',
								'desc'   => __("Default value. Number of points earned for a regular product. Displays a points range for variable products", 'woorewards-pro'),
							),
							array(
								'option' => 'min',
								'desc'   => __("For variable products, displays the minimum of points that can be earned", 'woorewards-pro'),
							),
							array(
								'option' => 'max',
								'desc'   => __("For variable products, displays the maximum of points that can be earned", 'woorewards-pro'),
							),
						),
						'example' => '[wr_product_points value="min"]'
					),
					'showcurrency' => array(
						'option' => 'showcurrency',
						'desc'   => __("(Optional) Set if you want to display the points currency.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'true',
								'desc'   => __("Default value. Displays the points currency", 'woorewards-pro'),
							),
							array(
								'option' => 'false',
								'desc'   => __("Doesn't display the points currency", 'woorewards-pro'),
							),
						),
						'example' => '[wr_product_points showcurrency="false"]'
					),
					'force' => array(
						'option' => 'force',
						'desc' => __("(Optional) If set, elements are displayed even if users don't have access to the points and rewards systems.", 'woorewards-pro'),
					),
				),
			)
		);
		return $fields;
	}


	/** Shows points earned for purchasing a product
	 * [wr_product_points system='poolname1']
	 * @param $atts array :
	 * * 'system'       → Default: '' | Pools
	 * * 'id'           → Default: '' | Product Id
	 * * 'value'        → Default: 'regular'
	 * * 'showcurrency' → Default: 'true'
	 */
	public function shortcode($atts = array(), $content = null)
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args(
			$atts,
			array(
				'id'           => '',
				'system'       => '',
				'value'        => 'regular',
				'showcurrency' => true,
				'force'        => false,
			)
		));

		if ($atts['id']) {
			$product = \wc_get_product(\intval($atts['id']));
		} else {
			$product = $this->getCurrentProduct();
		}

		if ($product) {
			$atts['showcurrency'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showcurrency']);
			$atts['value'] = \strtolower(\substr(\trim($atts['value']), 0, 3));

			if ($points = $this->getPointsForProduct($product, $atts)) {
				return $this->getContent($points, $atts, $content);
			}
		}
		return '';
	}

	protected function getCurrentProduct()
	{
		global $product;
		if ($product && \is_a($product, 'WC_Product'))
			return $product;
		else
			return false;
	}

	protected function getPointsForProduct($product, $atts = array())
	{
		if (!$atts['system']) {
			$atts['showall'] = true;
		}
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if (!($pools && $pools->count())) {
			return false;
		}

		$points = array();
		foreach ($pools->asArray() as $pool) {
			$min   = 0;
			$max   = 0;
			foreach ($pool->getEvents()->asArray() as $event) {
				if (\is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview')) {
					$preview = $event->getPointsForProduct($product);
					if (is_array($preview)) {
						$min   += \min($preview);
						$max   += \max($preview);
					} else {
						$min   += $preview;
						$max   += $preview;
					}
				}
			}
			if ($max > 0) {
				$value = $max;
				if ($atts['showcurrency']) {
					$max = \LWS_WooRewards::formatPointsWithSymbol($max, empty($pool) ? '' : $pool->getName());
					if ($min != $value) {
						$min = \LWS_WooRewards::formatPointsWithSymbol($min, empty($pool) ? '' : $pool->getName());
						$value = ($min . _x(" – ", 'min/max point preview separator', 'woorewards-pro') . $max);
					} else {
						$value = $min = $max;
					}
				} elseif ($min != $value) {
					$value = ($min . _x(" – ", 'min/max point preview separator', 'woorewards-pro') . $max);
				}
				$points[] = array(
					'points'   => $value,
					'min'      => $min,
					'max'      => $max,
				);
			}
		}
		return $points;
	}

	protected function getContent($points, $atts, $content = '')
	{
		if (\trim($content)) {
			$content = \str_replace("[points]", \implode(', ', \array_column($points, 'points')), $content);
			$content = \str_replace("[points_min]", \implode(', ', \array_column($points, 'min')), $content);
			$content = \str_replace("[points_max]", \implode(', ', \array_column($points, 'max')), $content);
		} else {
			if ('min' == $atts['value']) {
				$content = \implode(', ', \array_column($points, 'min'));
			} elseif ('max' == $atts['value']) {
				$content = \implode(', ', \array_column($points, 'max'));
			} else {
				$content = \implode(', ', \array_column($points, 'points'));
			}
		}
		return $content;
	}
}
