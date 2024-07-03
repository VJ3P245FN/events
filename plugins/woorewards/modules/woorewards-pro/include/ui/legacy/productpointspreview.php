<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Compute a earning point estimation and insert it in the product page. */
class ProductPointsPreview
{
	const POS_BEFORE = 'before_summary';
	const POS_INSIDE = 'inside_summary';
	const POS_AFTER  = 'after_summary';
	const POS_FORM   = 'after_form';
	const POS_NONE   = 'not_displayed';

	private $pools   = false;
	private $poolIds = false;
	private $stygen  = false;

	static function register()
	{
		$position = \lws_get_option('lws_woorewards_product_potential_position', self::POS_NONE);
		$poolIds = \lws_get_option('lws_woorewards_product_potential_pool', array());
		$me = new self($poolIds, $position);

		\add_filter('lws_adminpanel_stygen_content_get_' . 'productpointspreview', array($me, 'template'));
		\add_shortcode('wr_product_points_preview', array($me, 'shortcode'));

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		if (\get_option('lws_woorewards_product_loop_points_preview')) {
			\add_action('woocommerce_after_shop_loop_item_title', array($me, 'inLoop'), 20);
		}

		/** Admin */
		\add_filter('lws_woorewards_legacy_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['productpreview'] = array(
			'id' => 'lws_woorewards_sc_product_previews',
			'title' => __("Product Points Preview", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_product_points_preview]',
				'description' =>  __("This shortcode shows the points the customers can get if he purchases the actual product.", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'id',
						'desc' => __("The product id. If not specified, the shortcode will try to find the actual product from the page.", 'woorewards-pro'),
					),
					'system' => array(
						'option' => 'system',
						'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, all active systems are displayed", 'woorewards-pro') .
							"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-pro'),
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

	function registerScripts()
	{
		\wp_register_style('lws_wre_product_points_preview', LWS_WOOREWARDS_PRO_CSS . '/templates/productpointspreview.css?stygen=lws_wre_product_points_preview', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	function __construct($poolIds = false, $position = self::POS_NONE)
	{
		$this->poolIds = $poolIds;
		$this->position = $position;

		if ($hook = $this->getHook($position))
			\add_action($hook, array($this, 'display'));
	}

	protected function getHook($position)
	{
		if ($position == self::POS_BEFORE)
			return 'woocommerce_before_single_product_summary';
		else if ($position == self::POS_INSIDE)
			return 'woocommerce_single_product_summary';
		else if ($position == self::POS_AFTER)
			return 'woocommerce_after_single_product_summary';
		else if ($position == self::POS_FORM)
			return 'woocommerce_after_add_to_cart_form';
		else
			return false;
	}

	function template($snippet)
	{
		$this->stygen = true;
		$snippet = $this->getContent(
			array(
				array('system' => 'Standard System', 'points' => '256 ' . __('Points', 'woorewards-pro')),
				array('system' => 'Levelling System', 'points' => '128 ' . __('Points', 'woorewards-pro')),
			),
			false,
			false
		);
		$this->stygen = false;
		return $snippet;
	}

	function shortcode($atts = array(), $content = '')
	{
		if (false === $this->poolIds)
			$this->poolIds = \get_option('lws_woorewards_product_potential_pool');

		if ($atts && \is_array($atts) && isset($atts['id']))
			$product = \wc_get_product(\intval($atts['id']));
		else
			$product = $this->getCurrentProduct();

		if ($product) {
			if ($points = $this->getPointsForProduct($product, $atts)) {
				\wp_enqueue_style('lws_wre_product_points_preview');
				return $this->getContent($points, \get_current_user_id(), \get_option('lws_woorewards_ppp_show_unlogged', 'on'));
			} else
				return $content;
		}
		return '';
	}

	function display()
	{
		if ($product = $this->getCurrentProduct()) {
			if ($points = $this->getPointsForProduct($product)) {
				\wp_enqueue_style('lws_wre_product_points_preview');
				echo $this->getContent($points, \get_current_user_id(), \get_option('lws_woorewards_ppp_show_unlogged', 'on'));
			}
		}
	}

	protected function getCurrentProduct()
	{
		global $product;
		if ($product && \is_a($product, 'WC_Product'))
			return $product;
		else
			return false;
	}

	protected function getPools()
	{
		if (false === $this->pools) {
			$this->pools = array();
			if ($this->poolIds && \is_array($this->poolIds)) {
				foreach (\LWS_WooRewards_Pro::getActivePools()->asArray() as $pool) {
					if (in_array($pool->getId(), $this->poolIds)) {
						$this->pools[] = $pool;
					}
				}
			} else
				$this->pools = \LWS_WooRewards_Pro::getActivePools()->asArray();
		}
		return $this->pools;
	}

	/**	$atts['system'] a string with coma separated system reference.
	 *	if $atts['intern'], use id instead of name to get Loyalty System.
	 *	@return false|array of Pool in same order as $atts['system'],
	 *	or false if $atts['system'] is missing. */
	protected function getRequiredPools($atts)
	{
		if (!(isset($atts['system']) || isset($atts['showall'])))
			return false;
		$intern = (isset($atts['intern']) && $atts['intern']);
		$pools = array();

		if (!$intern) {
			$collection = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			if ($collection) {
				foreach ($collection->asArray() as $pool) {
					$pools[$pool->getName()] = $pool;
				}
			}
		} else {
			foreach (\LWS_WooRewards_Pro::getActivePools()->asArray() as $pool) {
				$pools[$pool->getId()] = $pool;
			}
		}

		if (isset($atts['system'])) {
			$sorted = array();
			foreach (\array_map('trim', explode(',', $atts['system'])) as $system) {
				if (isset($pools[$system]))
					$sorted[] = $pools[$system];
			}
			return $sorted;
		} else {
			return $pools;
		}
	}

	protected function getForcedPools($atts)
	{
		$forcedPool = array();
		if ($atts && \is_array($atts) && isset($atts['force']) && $atts['force'])
			$forcedPool = \is_array($atts['force']) ? $atts['force'] : explode(',', $atts['force']);
		return \array_map('\trim', $forcedPool);
	}

	protected function getPointsForProduct($product, $atts = array())
	{
		$points = array();
		$userId = \get_current_user_id();
		$forcedPool = $this->getForcedPools($atts);

		$pools = $this->getRequiredPools($atts);
		if (false === $pools)
			$pools = $this->getPools();

		foreach ($pools as $pool) {
			if ($pool->userCan($userId) || \in_array($pool->getName(), $forcedPool)) {
				$system = $pool->getOption('display_title');
				$total = 0;
				$min = false;
				foreach ($pool->getEvents()->asArray() as $event) {
					if (\is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview')) {
						$preview = $event->getPointsForProduct($product);
						if (is_array($preview)) {
							if ($min === false)
								$min = $total;
							$min += min($preview);
							$total += max($preview);
						} else {
							$total += $preview;
							if ($min !== false)
								$min += $preview;
						}
					}
				}
				if ($total > 0) {
					$value = \LWS_WooRewards::formatPointsWithSymbol($total, empty($pool) ? '' : $pool->getName());
					if ($min !== false) {
						$value = (\LWS_WooRewards::formatPointsWithSymbol($min, empty($pool) ? '' : $pool->getName())
							. _x(" – ", 'min/max point preview separator', 'woorewards-pro')
							. $value
						);
					}
					$points[] = array(
						'system' => $system,
						'points' => $value,
					);
				}
			}
		}
		return $points;
	}

	protected function getContent($points, $userId = false, $force = true)
	{
		$label = \lws_get_option('lws_woorewards_label_ppp', __("With this product, you will earn ", 'woorewards-pro'));
		if (!$this->stygen)
			$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "WooRewards - Product Points Preview - Title");
		$html = '';

		if (!$userId) {
			$unlogText = \lws_get_option('lws_woorewards_ppp_unlogged_text', '');
			if ($unlogText) {
				if (!$this->stygen)
					$unlogText = \apply_filters('wpml_translate_single_string', $unlogText, 'Widgets', "WooRewards - Product Points Preview - Unlogged Text");
				if ($force)
					$label = $unlogText;
				else
					$html .= "<h2 class='lwss_selectable lws-wre-ppp-nouser-text' data-type='Unlogged Text'>{$unlogText}</h2>";
			}
		}

		if ($userId || $force || $this->stygen) {
			$html .= <<<EOT
<div class='lwss_selectable lwss_modify lws-wre-productpointspreview-label' data-type='Label' data-id='lws_woorewards_label_ppp'>
	<span class='lwss_modify_content'>{$label}</span>
</div>
EOT;

			$sep = _x(", ", "product point preview separator", 'woorewards-pro');
			$last = (count($points) - 1);
			for ($i = 0; $i < count($points); ++$i) {
				$name = sprintf(_x("in %s", 'points in loyalty sytem', 'woorewards-pro'), $points[$i]['system']);
				$virgule = $i < $last ? $sep : '';
				$html .= "<div class='lwss_selectable lws-wre-productpointspreview-points' data-type='Points'>{$points[$i]['points']}<span class='lwss_selectable lws-wre-productpointspreview-lsystem' data-type='Loyalty System'> {$name}</span>{$virgule}</div>";
			}
		}

		if ($html) {
			$html = "<div class='lwss_selectable lws-wre-productpointspreview-main' data-type='Main Div'>{$html}</div>";
		}
		return $html;
	}

	public function inLoop()
	{
		global $product;
		$systems = \get_option('lws_woorewards_product_loop_preview_pools');
		$atts = array(
			'intern' => 'yes',
			'system' => $systems ? implode(',', $systems) : '',
		);
		if ($points = $this->getPointsForProduct($product, $atts)) {
			$pattern = \lws_get_option('lws_woorewards_product_loop_points_preview_pattern', __("Earn [points] in [system]", 'woorewards-pro'));
			$pattern = \apply_filters('wpml_translate_single_string', $pattern, 'Widgets', "WooRewards - Product loop - Points Preview pattern");

			foreach ($points as $point) {
				echo sprintf('<div class="lws-wr-product-points-preview">%s</div>', str_replace(
					array('[points]', '[system]'),
					array($point['points'], $point['system']),
					$pattern
				));
			}
		}
	}
}
