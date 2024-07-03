<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class OrderPointsPreview
{
	public static function install()
	{
		$me = new self();
		// Shortcode
		\add_shortcode('wr_order_points_preview', array($me, 'shortcode'));
		// Admin
		\add_filter('lws_woorewards_woocommerce_shortcodes', array($me, 'admin'), 5);
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		// Ajax
		\add_action('wp_ajax_lws_woorewards_get_order_preview', array($me, 'updateDisplay'));
		\add_action('wp_ajax_nopriv_lws_woorewards_get_order_preview', array($me, 'updateDisplay'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-order-points-preview', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/order-points-preview.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('wr-order-points-preview', LWS_WOOREWARDS_PRO_JS . '/orderpreview.js', array('jquery', 'lws-tools'), LWS_WOOREWARDS_PRO_VERSION, true);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-order-points-preview');
		\wp_enqueue_script('wr-order-points-preview');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['orderpointspreview'] = array(
			'id' => 'lws_woorewards_order_points_preview',
			'title' => __("Order Points Preview", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_order_points_preview]',
				'description' =>  __("Use this shortcode to show to your customers the points they will earn by completing the current order.", 'woorewards-pro') . "<br/>" .
					__("Use the following options to change how information is displayed.", 'woorewards-pro'),
				'options' => array(
					'system' => array(
						'option' => 'system',
						'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, all active systems are displayed", 'woorewards-pro') .
							"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-pro'),
					),
					'layout' => array(
						'option' => 'layout',
						'desc' => __("(Optional) Select how elements are organized . 4 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'vertical',
								'desc'   => __("Default value. Elements are displayed on top of another", 'woorewards-pro'),
							),
							array(
								'option' => 'horizontal',
								'desc'   => __("Elements are displayed in row", 'woorewards-pro'),
							),
							array(
								'option' => 'grid',
								'desc'   => __("Elements are displayed in a responsive grid", 'woorewards-pro'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Simple text without stylable elements", 'woorewards-pro'),
							),
						),
						'example' => '[wr_order_points_preview layout="grid"]'
					),
					'element' => array(
						'option' => 'element',
						'desc' => __("(Optional) Select how an element is displayed. 3 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'line',
								'desc'   => __("Default value. Horizontal display in stylable elements", 'woorewards-pro'),
							),
							array(
								'option' => 'tile',
								'desc'   => __("Stylable tile with a background color", 'woorewards-pro'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Simple text without stylable elements", 'woorewards-pro'),
							),
						),
						'example' => '[wr_order_points_preview element="tile"]'
					),
					'display' => array(
						'option' => 'display',
						'desc' => __("(Optional) Select how the points are displayed. 2 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'formatted',
								'desc'   => __("Default. Points are formatted with the points currency/name", 'woorewards-pro'),
							),
							array(
								'option' => 'simple',
								'desc'   => __("Only the points numeric value is displayed", 'woorewards-pro'),
							),
						),
						'example' => '[wr_order_points_preview display="simple"]'
					),
					'showname' => array(
						'option' => 'showname',
						'desc' => __("(Optional) If set, will display the points and rewards system name for each element", 'woorewards-pro'),
						'example' => '[wr_order_points_preview showname="true"]'
					),
					'showdetail' => array(
						'option' => 'showdetail',
						'desc' => __("(Optional) If set, for each system, a detailed list of actions that give points is displayed for each system", 'woorewards-pro'),
						'example' => '[wr_order_points_preview showdetail="true"]'
					),
					'force' => array(
						'option' => 'force',
						'desc' => __("(Optional) If set, elements are displayed even if users don't have access to the points and rewards systems.", 'woorewards-pro'),
						'example' => '[wr_order_points_preview force="true"]'
					),
					'showunlogged' => array(
						'option' => 'showunlogged',
						'desc' => __("(Optional) If set, elements are displayed even if users are not logged in.", 'woorewards-pro'),
						'example' => '[wr_order_points_preview showunlogged="true"]'
					),
					'totallabel' => array(
						'option' => 'totallabel',
						'desc' => __("(Optional) Set the 'Total' label. Only displayed if showdetail is set to true", 'woorewards-pro'),
						'example' => '[wr_order_points_preview totallabel="Your Total"]'
					),
				),
			)
		);
		return $fields;
	}

	function updateDisplay()
	{
		$atts = isset($_REQUEST['atts']) ? json_decode(base64_decode($_REQUEST['atts'])) : array();
		echo ($this->shortcode($atts));
		exit();
	}

	/** Shows points earned for placing an order
	 * [wr_order_points_preview systems='poolname1, poolname2']
	 * @param $atts array :
	 * * 'system' 		→ Default: '' | Pools
	 * * 'layout' 		→ Default: 'vertical' | 4 possible values : grid, vertical, horizontal, none.
	 * * 'element' 		→ Default: 'line' | 3 possible values : tile, line, none.
	 * * 'display'		→ Default: 'formatted' | Points display
	 * * 'showname'		→ Default: false | Shows the name of the points and rewards system if set
	 * * 'showdetail'	→ Default: false | Show events
	 * * 'force'			→ Default: false | Force Display
	 * * 'showunlogged'	→ Default: false | Show to unlogged customers
	 * * 'totallabel'	→ Default: Total | Only displayed if detail is set to true
	 */
	public function shortcode($atts = array(), $content = null)
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args(
			$atts,
			array(
				'system' => '',
				'layout' => 'vertical',
				'element' => 'line',
				'display' => 'formatted',
				'showname' => false,
				'showdetail' => false,
				'force' => false,
				'showunlogged' => false,
				'totallabel' => __("Total", 'woorewards-pro'),
				'sep' => '',
			)
		));
		// Basic verifications
		$cart = \WC()->cart;
		if (!$cart) {
			return \do_shortcode($content);
		}
		if (!$atts['system']) {
			$atts['showall'] = true;
		}
		if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showunlogged'])) {
			$atts['force'] = true;
		} else if (!\get_current_user_id()) {
			return \do_shortcode($content);
		}
		$atts['layout'] = \strtolower($atts['layout']);
		$atts['element'] = \strtolower($atts['element']);
		$shapePts = ('s' != \strtolower(\substr($atts['display'], 0, 1)));
		if ($atts['element'] == 'none' && !\strlen($atts['sep']))
			$atts['sep'] = ' ';

		$this->enqueueScripts();

		// Get the data
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		$items = array();

		foreach ($pools->asArray() as $pool) {
			$system = $pool->getOption('display_title');
			$sum = 0;
			$events = array();
			foreach ($pool->getEvents()->asArray() as $event) {
				if (\is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview') && 0 < ($points = $event->getPointsForCart($cart))) {
					/** @var \LWS\WOOREWARDS\Abstracts\Event $event */
					$cat = $event->getCategories();
					if (!isset($cat['sponsorship'])) {
						$sum += $points;
						$name = $event->getTitle(false);
						if (!$name) {
							$name = $event->getDescription('frontend');
						}
						$events[] = array(
							'name' => $name,
							'points' => ($shapePts ? \LWS_WooRewards::formatPointsWithSymbol($points, $pool->getName()) : $points),
						);
					}
				}
			}
			if ($sum > 0) {
				$items[] = array(
					'system' => $system,
					'points' => ($shapePts ? \LWS_WooRewards::formatPointsWithSymbol($sum, $pool->getName()) : $sum),
					'events' => $events,
				);
			}
		}
		$attributes = base64_encode(json_encode($atts));
		$tag = 'div';
		if (\substr($atts['layout'], 0, 2) == 'no'
			&& \substr($atts['element'], 0, 2) == 'no'
		)
			$tag = 'span';

		if ($items) {
			return "<{$tag} class='wr_order_points_preview_main' data-atts='{$attributes}'>" . $this->getContent($atts, $items) . "</{$tag}>";
		}
		return "<{$tag} class='wr_order_points_preview_main' data-atts='{$attributes}'>" . \do_shortcode($content) . "</{$tag}>";
	}

	protected function getContent($atts, $items)
	{
		$elements = array();
		foreach ($items as $item) {
			$detail = '';
			$total = '';
			$poolname = '';
			$addclass = '';
			$poolitem = '';
			if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showname'])) {
				$poolname = $item['system'];
				$poolitem = "<div class='poolname'>{$poolname}</div>";
				$addclass = ' haspoolname';
			}
			if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
				if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showdetail'])) {
					$detail .= "<div class='details$addclass'>";
					foreach ($item['events'] as $event) {
						$detail .= "<div class='detailline'>";
						$detail .= "<div class='event title'>{$event["name"]}</div>";
						$detail .= "<div class='event points'>{$event["points"]}</div>";
						$detail .= "</div>";
					}
					$detail .= "</div>";
					$total = "<div class='total'>{$atts['totallabel']}</div>";
				}
				$elements[] = <<<EOT
	<div class='item {$atts['element']}'>
		{$poolitem}
		{$detail}
		<div class='preview-total-line'>
			$total
			<div class='points'>{$item['points']}</div>
		</div>
	</div>
EOT;
			} else {
				$elements[] = ($poolname ? ($poolname . ' ' . $item['points']) : $item['points']);
			}
		}
		$elements = implode($atts['sep'], $elements);

		switch (\substr($atts['layout'], 0, 3)) {
			case 'gri':
				return "<div class='wr-order-points-preview wr-shortcode-grid'>$elements</div>";
			case 'hor':
				return "<div class='wr-order-points-preview wr-shortcode-hflex'>$elements</div>";
			case 'ver':
				return "<div class='wr-order-points-preview wr-shortcode-vflex'>$elements</div>";
			default:
				return $elements;
		}
	}
}
