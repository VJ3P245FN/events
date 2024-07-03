<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rewardclaim.php';

/** Setup mail about unlockables available to a user.
 * For each unlockable, a link is provided to unlock it.
 * $data should be an array as:
 *	*	'user' => a WP_User instance
 *	*	'pool' => a LWS\WOOREWARDS\PRO\Core\Pool instance
 *	*	'points' => integer value, remaining points
 *	*	'unlockables' => a LWS\WOOREWARDS\Collections\Unlockables instance */
class AvailableUnlockables
{
	protected $template = 'wr_available_unlockables';

	public function __construct()
	{
		\add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 21 );// priority: mail order in settings
		\add_filter('lws_mail_arguments_' . $this->template, array($this, 'attributes'), 10, 2);
		\add_filter( 'lws_mail_settings_' . $this->template, array($this, 'settings'));
		\add_filter( 'lws_mail_body_' . $this->template, array($this, 'body'), 10, 3);
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function attributes($settings, $data)
	{
		if( \is_wp_error($data) )
			$data = $this->placeholders();

		$name = $this->getUserName($data);
		foreach( array('subject', 'title', 'header', 'footer', 'preheader') as $key )
			$settings[$key] = str_replace('[user_name]', $name, $settings[$key]);
		return $settings;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings']      = 'Reward Choice';
		$settings['settings_name'] = __("Reward Choice", 'woorewards-pro');
		$settings['about']         = __("Inform a customer about available rewards. Available rewards depend on customer point count. This email provide links to pick one and consume points.", 'woorewards-pro');
		$settings['subject']       = __("Rewards are waiting for you", 'woorewards-pro');
		$settings['title']         = __("Rewards are waiting for you !", 'woorewards-pro');
		$settings['header']        = __("Pick a reward in the following list", 'woorewards-pro');
		$settings['footer']        = __("Powered by MyRewards", 'woorewards-pro');
		$settings['doclink']       = \LWS\WOOREWARDS\PRO\DocLinks::get('emails');
		$settings['icon']          = 'lws-icon-questionnaire';
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/availableunlockables.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", 'woorewards-pro'),
			'type' => 'box',
			'extra' => array(
				'default' => '',
				'layout' => 'toggle',
			)
		);
		$settings['about'] .= '<br/><span class="lws_wr_email_shortcode_help">'.sprintf(__("Use the shortcode %s to insert the name of the user", 'woorewards-pro'),'<b>[user_name]</b>').'</span>';
		return $settings;
	}


	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html = "<tr><td class='lws-middle-cell'>";

		$total = sprintf(
			__("You have %s", 'woorewards-pro'),
			\LWS_WooRewards::formatPointsWithSymbol($data['points'], empty($data['pool']) ? '' : $data['pool']->getName())
		);
		$html .= "<div class='lwss_selectable lws-middle-cell-points' data-type='Points total'>$total</div>";

		$first = true;
		$sep = "<tr><td class='lwss_selectable lws-rewards-sep' data-type='Rewards Separator' colspan='3'></td></tr>";
		$html .= "<table class='lwss_selectable lws-rewards-table' data-type='Rewards Table'>";

		foreach( $data['unlockables']->asArray() as $unlockable )
		{
			if( $unlockable->isPurchasable() )
			{
				if( !$first )
					$html .= $sep;
				$html .= $this->getUnlockableRow($unlockable, $data['points'], $data['user'], $demo);
				$first = false;
			}
		}

		if( !empty($data['pool']) )
		{
			// what a customer could earn with more points
			$floor = $data['points'];
			$forthcoming = $data['pool']->getUnlockables()->filter(function($item)use($floor){return $item->getCost()>$floor;})->sort();
			foreach( $forthcoming->asArray() as $unlockable )
			{
				if( !$first )
					$html .= $sep;
				$html .= $this->getUnlockableRow($unlockable, $data['points'], $data['user'], $demo);
				$first = false;
			}
		}

		$html .= "</table>";
		$html .= "</td></tr>";
		return $html;
	}

	protected function getUnlockableRow($unlockable, $points, $user, $demo=false)
	{
		$html = "<tr><td class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>";
		// unlocable image
		$img = $unlockable->getThumbnailImage();
		$html .= (empty($img) && $demo) ? "<div class='lws-rewards-thumbnail lws-icon lws-icon-image'></div>" : $img;

		$html .= "</td><td class='lwss_selectable lws-rewards-cell-left' data-type='Rewards Cell' width='100%'>";
		// unlocable details
		$html .= "<div class='lwss_selectable lws-rewards-name' data-type='Reward Name'>".$unlockable->getTitle()."</div>";
		$html .= "<div class='lwss_selectable lws-rewards-desc' data-type='Reward Description'>".$unlockable->getCustomDescription()."</div>"; // purpose
		if( $points >= $unlockable->getCost() )
		{
			$cost = sprintf(
				__("This reward is worth %s", 'woorewards-pro'),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost(), $unlockable->getPoolName())
			);
			$html .= "<div class='lwss_selectable lws-rewards-cost' data-type='Reward Cost'>{$cost}</div>"; // cost
		}
		else
		{
			$cost = sprintf(
				__("This reward is worth %s. You still need %s. ", 'woorewards-pro'),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost(), $unlockable->getPoolName()),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost()-$points, $unlockable->getPoolName())
			);
			$html .= "<div class='lwss_selectable lws-rewards-more' data-type='Need More points'>{$cost}</div>"; // cost
		}

		$html .= "</td><td class='lwss_selectable lws-rewards-cell-right' data-type='Rewards Cell'>";
		// redeem button
		if( $this->isPurchasable($unlockable, $points, $user->ID) )
		{
			$btn = __("Unlock", 'woorewards-pro');
			$href = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs(
				\LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget($demo),
				$unlockable,
				$user
			));
			$html .= "<a href='$href' class='lwss_selectable lws-rewards-redeem' data-type='Redeem button'>{$btn}</a>";
		}
		else
		{
			$btn = _x("Locked", "redeem button need more points", 'woorewards-pro');
			$html .= "<div href='#' class='lwss_selectable lws-rewards-redeem-not' data-type='Not Redeemable button'>{$btn}</div>";
		}
		$html .= "</td></tr>";
		return $html;
	}

	protected function isPurchasable(&$unlockable, $points, $userId)
	{
		if( !$unlockable->isPurchasable($points, $userId) )
			return false;
		if( $unlockable->getPool() && $unlockable->getPool()->isUnlockPrevented() )
			return false;
		return true;
	}

	protected function placeholders()
	{
		$examples = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(false, array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD));
		$cost = 0;
		$examples->apply(function($item)use(&$cost){
			$cost += 10;
			$item->setCost($cost);
			if( \method_exists($item, 'setTestValues') )
				$item->setTestValues();
		});
		$pts = 42;
		if( $examples->count() > 1 )
			$pts = $cost - 8;

		return array(
			'user'   => \wp_get_current_user(),
			'points' => $pts,
			'pool'   => null,
			'unlockables' => $examples
		);
	}

	function getUserName($data)
	{
		$name = '';
		if( isset($data['user']) && $data['user'] && \is_a($data['user'], '\WP_User') )
		{
			if( $data['user']->display_name )
				$name = $data['user']->display_name;
			else if( $data['user']->user_nicename )
				$name = $data['user']->user_nicename;
			else
				$name = $data['user']->user_login;
		}
		return $name;
	}

}
?>