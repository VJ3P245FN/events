<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display a message in WooCommerce new order emails. */
class OrderPointInformation
{
	static function register()
	{
		$me = new self();
		/**	@param $points (array)
		 *	@param $order (\WC_Order) */
		\add_filter('lws_woorewards_get_points_report_for_order', array($me, 'getOrderLoyaltyInformation'), 10, 2);

		if (\get_option('lws_woorewards_wc_new_order_enable'))
			\add_action('woocommerce_email_order_meta', array($me, 'echoInEmails'), 10, 3);
		if (\get_option('lws_woorewards_wc_details_order_enable'))
			\add_action('woocommerce_order_details_after_order_table', array($me, 'echoInDetails'), 10, 3);

		if (\get_option('lws_woorewards_wc_thanks_order_enable'))
			\add_filter('woocommerce_thankyou_order_received_text', array($me, 'addInThanks'), 11, 2);
	}

	/**	@param $order Order Object */
	function echoInDetails($order)
	{
		$content = $this->getMessage($order);
		if ($content) {
			echo "<div class='lws-wr-resume-order-points'>{$content}</div>";
		}
	}

	/**	@param $str string
	 *	@param $order Order Object
	 *	@return string (updated $str) */
	function addInThanks($str, $order)
	{
		$content = $this->getMessage($order, true); // flat plain text only
		if ($content) {
			if ($str)
				$str .= (' ' . $content);
			else
				$str = $content;
		}
		return $str;
	}

	/**	@param $order Order Object
	 *	@param $sent_to_admin If this email is for administrator or for a customer
	 *	@param $plain_text HTML or Plain text (can be configured in WooCommerce > Settings > Emails) */
	function echoInEmails($order, $sent_to_admin, $plain_text)
	{
		$content = $this->getMessage($order);
		if ($content) {
			if( $plain_text )
			{
				static $replace = array(
					"<br" => "\n<br",
					"</p>" => "</p>\n\n",
					"</td>" => "</td>\t",
					"</tr>" => "</tr>\n",
					"<table" => "\n<table",
					"</thead>" => "</thead>\n",
					"</tbody>" => "</tbody>\n",
					"</table>" => "</table>\n",
				);
				$content = str_replace(array_keys($replace), array_values($replace), $content);
				$content = trim(\wp_kses($content, array()));
				$content = \wp_kses($content, array()); // filter out any html
			}
			else
			{
				$content .= "<br/>";
			}
			echo $content;
		}
	}

	/** @param $order Order Object
	 *	@return string */
	function getMessage($order, $plainText=false)
	{
		if (!$order)
			return '';
		$status = \apply_filters('lws_woorewards_email_order_meta_status', array('processing', 'completed'));
		if( \in_array($order->get_status('edit'), $status) )
		{
			if ($plainText) {
				$option = 'lws_woorewards_wc_new_order_content_plaintext';
				$wpml   = "WooRewards - New Order Thanks Message - Earning Points";
			} else {
				$option = 'lws_woorewards_wc_new_order_content';
				$wpml   = "WooRewards - New Order Email Message - Earning Points";
			}
			$content = \get_option($option, __("With this order, you will earn [wr_wc_order_points]", 'woorewards-pro'));
			$content = \apply_filters('wpml_translate_single_string', $content, 'Widgets', $wpml);
			if (false !== strpos($content, '[wr_wc_order_points]') || false !== strpos($content, '[order_points]') || false !== strpos($content, '[points_name]') || false !== strpos($content, '[system_name]') || false !== strpos($content, '[points_balance]'))
			{
				$items = $this->getOrderLoyaltyInformation(array(), $order, $plainText);
				if( !$items )
					return ''; // nothing is better than a truncated sentance

				$contents = array();
				foreach($items as $item)
				{
					$contents[] = str_replace(
						array('[wr_wc_order_points]', '[order_points]', '[points_name]', '[system_name]', '[points_balance]'),
						$item,
						$content
					);
				}
				$content = implode($plainText ? "\n" : '<br>', $contents);
			}
			return $content;
		} else {
			return '';
		}
	}

	/* Search for order loyalty information */
	function getOrderLoyaltyInformation($items, $order, $plainText=false)
	{
		if (!\is_array($items))
			$items = array();
		if (!$order)
			return $items;

		/* Load active and filter selected pools */
		$pools = \LWS_WooRewards_Pro::getActivePools();
		$selected = \lws_get_option('lws_woorewards_wc_new_order_pools', array());
		if( $selected && is_array($selected) )
			$pools = $pools->filter(function($p)use($selected){return in_array($p->getId(), $selected);});

		$pools = $pools->asArray();
		if (!$pools)
			return $items;

		$userId = \LWS\Adminpanel\Tools\Conveniences::getCustomerId(false, $order);
		if( $userId )
		{
			foreach( $pools as $pool )
			{
				if($pool->userCan($userId))
				{
					$poolName = $pool->getName();
					$sum = 0;

					foreach( $pool->getEvents()->asArray() as $event )
					{
						if( \is_a($event, '\LWS\WOOREWARDS\PRO\Events\I_CartPreview') )
						{
							/** @var \LWS\WOOREWARDS\Abstracts\Event $event */
							$cat = $event->getCategories();
							if(!isset($cat['sponsorship']))
							{
								if( 0 < ($points = $event->getPointsForOrder($order)) )
								{
									$sum += $points;
								}
							}
						}
					}

					if( $sum > 0 )
					{
						$title  = $pool->getOption('display_title');
						if ($plainText) {
							$title  = \wp_kses($title, array());
							$sym    = \esc_html($pool->getPointUnit($sum));
							$format = $pool->getOption('point_format');
							if (!$format) $format = '%s %s';
							$fsum   = \esc_html(\LWS_WooRewards::formatPoints($sum, $poolName));
							$points = sprintf($format, $fsum, $sym);
						} else {
							$sym    = \LWS_WooRewards::getPointSymbol($sum, $poolName);
							$fsum   = \LWS_WooRewards::formatPoints($sum, $poolName);
							$points = \LWS_WooRewards::formatPointsWithSymbol($sum, $poolName);
						}
						$items[] = array(
							'wr_wc_order_points' => sprintf(_x('%1$s in %2$s', 'Order email: [X points] in [system]', 'woorewards-pro'), $points, $title),
							'order_points'       => $fsum,
							'points_name'        => $sym,
							'system_name'        => $title,
							'points_balance'     => $pool->getPoints($userId),
						);
					}
				}
			}
		}
		return $items;
	}
}