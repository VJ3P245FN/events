<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Create an endpoint in frontpage.
 * Show customer loyalty systems and rewards. */
class LoyaltyEndpoint extends \LWS\WOOREWARDS\PRO\Ui\Legacy\Endpoint
{
	private $userId = 0;
	private $labels = array();
	private $pools  = array();
	private $demo   = false;

	function __construct()
	{
		if ($this->isActive('lws_woorewards_wc_my_account_endpont_loyalty', 'on')) {
			$libPage = \lws_get_option('lws_woorewards_wc_my_account_lar_label', __("Loyalty and Rewards", 'woorewards-pro'));
			parent::__construct('lws_woorewards', $libPage, "WooRewards - My Account - Loyalty and Rewards Tab Title");
		}

		\add_filter('lws_adminpanel_themer_content_get_' . 'wc_loyalty_and_rewards', array($this, 'template'));
		\add_action('wp_enqueue_scripts', array($this, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($this, 'registerScripts'));
		\add_shortcode('wr_user_loyalties', array($this, 'shortcode'));

		\add_filter('pre_update_option_lws_woorewards_wc_my_account_lar_options', array($this, 'transposeLoyaltyEndpointOptions'), 10, 3);
	}

	/** Edit and reading is not the same */
	function transposeLoyaltyEndpointOptions($value, $old_value, $option)
	{
			$transpose = array();
			if( is_array($value) && $value )
			{
					$firstK = array_keys($value)[0];
					for( $i=0 ; $i<count($value[$firstK]) ; ++$i )
					{
							$item = array();
							foreach( $value as $key => $list )
									$item[$key] = isset($list[$i]) ? $list[$i] : '';
							$transpose[] = $item;
					}
			}
			return $transpose;
	}

	/** [wr_user_loyalties] */
	function shortcode($atts = array(), $content = '')
	{
		return $this->getPage();
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-lar-expanded', LWS_WOOREWARDS_PRO_CSS . '/lar-expanded.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('woorewards-lar-endpoint', LWS_WOOREWARDS_PRO_JS . '/loyalty-and-rewards.js', array('jquery', 'jquery-ui-core', 'jquery-ui-widget'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_register_style('woorewards-lar-endpoint', LWS_WOOREWARDS_PRO_CSS . '/loyalty-and-rewards.css?themer=lws_wre_myaccount_lar_view', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		if (\lws_get_option('lws_woorewards_wc_myaccount_expanded') == 'on') {
			\wp_enqueue_style('woorewards-lar-expanded');
		}
		\wp_enqueue_script('woorewards-lar-endpoint');
		\wp_enqueue_style('woorewards-lar-endpoint');
	}

	protected function defaultLabels()
	{
		return array(
			'coverview' 	=> __("Coupons Overview", 'woorewards-pro'),
			'roverview' 	=> __("Rewards Overview", 'woorewards-pro'),
			'userhistory' 	=> __("User History", 'woorewards-pro'),
			'yourhistory' 	=> __("Recent Loyalty History", 'woorewards-pro'),
			'lsoverview' 	=> __("Loyalty Systems Overview", 'woorewards-pro'),
			'lsdetails' 	=> __("Loyalty System Details", 'woorewards-pro'),
			'curcoup' 		=> __("Your current coupons", 'woorewards-pro'),
			'ccode' 		=> __("Coupon code", 'woorewards-pro'),
			'cdesc'		 	=> __("Coupon Description", 'woorewards-pro'),
			'unlockables' 	=> __("Unlockable rewards", 'woorewards-pro'),
			'lsystem' 		=> __("Loyalty System", 'woorewards-pro'),
			'unlock' 		=> __("Unlock", 'woorewards-pro'),
			'sure' 			=> __("Are you sure ?", 'woorewards-pro'),
			'yes' 			=> __("Yes", 'woorewards-pro'),
			'cancel' 		=> __("Cancel", 'woorewards-pro'),
			'notavail' 		=> __("Won't be available anymore", 'woorewards-pro'),
			'start'	 		=> __("Start date", 'woorewards-pro'),
			'end'	 		=> __("End date", 'woorewards-pro'),
			'standard' 		=> __("Rewards", 'woorewards-pro'),
			'pointsoncart'	=> __("Points on Cart", 'woorewards-pro'),
			'desc'			=> __("Descr", 'woorewards-pro'),
			'value'			=> __("Value", 'woorewards-pro'),
			'level'			=> __("Level", 'woorewards-pro'),
			'levelling'		=> __("Levels", 'woorewards-pro'),
			'info'	 		=> __("Your information", 'woorewards-pro'),
			'ctotal'		=> __("Current Total", 'woorewards-pro'),
			'clevel'		=> __("Current Level", 'woorewards-pro'),
			'crank'			=> __("Current Rank", 'woorewards-pro'),
			'date' 			=> __("Date", 'woorewards-pro'),
			'descr'			=> __("Description", 'woorewards-pro'),
			'points' 		=> __("Points", 'woorewards-pro'),
			'sname'	 		=> __("System name", 'woorewards-pro'),
			'perform'		=> __("Action to perform", 'woorewards-pro'),
			'redet' 		=> __("Rewards details", 'woorewards-pro'),
			'emailsponsor'	=> __("Email Referrals", 'woorewards-pro'),
			'sponsorlink'	=> __("Referral Link", 'woorewards-pro'),
			'balance'		=> __("Your Current Balance", 'woorewards-pro'),
		);
	}

	protected function defaultOptions()
	{
		return array(
			array('value' => 'coupons',      'active' => 'yes', 'label' => __("Available Coupons", 'woorewards-pro')),
			array('value' => 'rewards',      'active' => 'yes', 'label' => __("Unlockable Rewards", 'woorewards-pro')),
			array('value' => 'systems',      'active' => 'yes', 'label' => __("Loyalty Systems Details", 'woorewards-pro')),
			array('value' => 'history',      'active' => 'yes', 'label' => __("Customer Points History", 'woorewards-pro')),
			array('value' => 'sponsoremail', 'active' => '',    'label' => __("Referral Mailing", 'woorewards-pro')),
			array('value' => 'sponsorlink',  'active' => '',    'label' => __("Referral Link", 'woorewards-pro')),
		);
	}

	function template($snippet)
	{
		$this->demo = true;
		return $this->getContent();
	}

	function getPage()
	{
		$this->demo = false;
		return $this->getContent();
	}

	function getContent()
	{
		$this->enqueueScripts();
		$this->userId = \get_current_user_id();
		$this->labels = $this->defaultLabels();

		$buyables = \LWS_WooRewards_Pro::getBuyablePools();
		$selected = \get_option('lws_woorewards_wc_my_account_systems_list');
		if ($selected)
			$buyables = $buyables->filterByReferences($selected);
		$this->pools = array();
		foreach ($buyables->asArray() as &$pool) {
			if ($pool->userCan($this->userId))
				$this->pools[] = $pool;
		}
		$extended = \get_option('lws_woorewards_wc_myaccount_expanded') ? ' extended' : '';
		$content = "<div class='lar_main_container{$extended} flcol'>";
		$options = \get_option('lws_woorewards_wc_my_account_lar_options', $this->defaultOptions());
		foreach ($options as $option) {
			if ($option['active'] == 'yes') {
				if (\method_exists($this, $fct = $option['value']))
					$content .= $this->$fct();
				else
					error_log("Loyalty endpoints requires an unknown function: " . $fct);
			}
		}
		$content .= "</div>";
		return $content;
	}

	function coupons()
	{
		$content = '';
		$coupons = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getCoupons($this->userId);
		if ($coupons) {
			$compte = count($coupons);
			$content .= <<<EOT
			<div class="lar_accordeon_container flcol">
				<div class="lar-accordeon-title-line flrow">
					<div class="lar-accordeon-title-text flexooa">{$this->labels['coverview']}</div>
					<div class="flexiia"></div>
				</div>
				<div class="lar-accordeon-item">
					<div class="lar-accordeon-not-expanded-cont flcol">
						<div class="flrow lar_overflow">
							<div class="flexooa lar-line-header">{$this->labels['curcoup']}</div>
							<div class="flexiia lar-line-header"></div>
							<div class="flexooa lar-line-header hlast">{$compte}</div>
						</div>
					</div>
					<div class="lar-accordeon-expanded-cont flcol flexoia">
						<div class="flrow lar-expanded-line lar-overcolors">
							<div class="flexooa lar-line-header">{$this->labels['curcoup']}</div>
							<div class="flexiia lar-line-header"></div>
							<div class="flexooa lar-line-header lar-acc-icon lws-icon lws-icon-circle-up"></div>
						</div>
						<div class="flrow">
							<div class="flex00a"></div>
							<div class="flexiia">
								<table class="lar-coupons-list" cellpadding='0' cellspacing='0'>
									<thead><tr><td>{$this->labels['ccode']}</td><td>{$this->labels['cdesc']}</td></tr></thead>
									<tbody>
EOT;

			foreach ($coupons as $coupon) {
				$code = \esc_attr($coupon->post_title);
				$descr = $coupon->post_excerpt;
				$content .= "<tr><td class='lar-coupons-list-code'>{$code}</td>";
				$content .= "<td class='lar-coupons-list-description lar-main-color'>{$descr}</td></tr>";
			}
			$content .= "</tbody></table></div></div></div></div></div>";
		}
		return $content;
	}

	function rewards()
	{
		$content = '';
		$unlockables = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUserUnlockables($this->userId, 'avail');
		if ($unlockables) {
			$compte = count($unlockables);
			$content = <<<EOT
			<div class="lar_accordeon_container flcol">
				<div class="lar-accordeon-title-line flrow">
					<div class="lar-accordeon-title-text flexooa">{$this->labels['roverview']}</div>
					<div class="flexiia"></div>
				</div>
				<div class="lar-accordeon-item">
					<div class="lar-accordeon-not-expanded-cont flcol">
						<div class="flrow lar_overflow">
							<div class="flexooa lar-line-header">{$this->labels['unlockables']}</div>
							<div class="flexiia lar-line-header"></div>
							<div class="flexooa lar-line-header hlast">{$compte}</div>
						</div>
					</div>
					<div class="lar-accordeon-expanded-cont flcol flexoia">
						<div class="flrow lar-expanded-line lar-overcolors">
							<div class="flexooa lar-line-header">{$this->labels['unlockables']}</div>
							<div class="flexiia lar-line-header"></div>
							<div class="flexooa lar-line-header lar-acc-icon lws-icon lws-icon-circle-up"></div>
						</div>
						<div class="flrow">
							<div class="lar_unlockables_list flexiia">
EOT;
			foreach ($unlockables as $unlockable) {
				$pool = $unlockable->getPool();
				$pName = $pool->getName();
				$pTitle = $pool->getOption('display_title');

				$user = \get_user_by('ID', $this->userId);
				if (!($pointName = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pName)))
					$pointName = __('Points', 'woorewards-pro');
				$unlockLink = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs(
					\LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget($this->demo),
					$unlockable,
					$user
				));

				$ypoints = sprintf(__("Your %s", 'woorewards-pro'), $pointName);
				$cost = sprintf(__("%s cost", 'woorewards-pro'), $pointName);

				$uPoints = $pool->getPoints($this->userId);
				$uCost = $unlockable->getCost('front');
				$uImg = $unlockable->getThumbnailImage();
				$uTitle = $unlockable->getTitle();
				$uDescr = $unlockable->getCustomDescription();

				$content .= <<<EOT
				<div class='lar-unlockable flrow' data-lsys='{$pName}' data-cpoints='{$uPoints}' data-cost='{$uCost}'>
					<div class='lar-unlockable-imgcol flcol flexooa'>{$uImg}</div>
					<div class='lar-unlockable-detcol flcol flexiia'>
						<div class='lar-unlockable-detcol-title'>{$uTitle}</div>
						<div class='lar-unlockable-detcol-description'>{$uDescr}</div>
					</div>
					<div class='lar-unlockable-infocol flexooa'><table class='lar-unlockable-infotable'>
						<tr><th>{$this->labels['lsystem']}</th><td>{$pTitle}</td></tr>
						<tr><th>{$ypoints}</th><td>{$uPoints}</td></tr>
						<tr><th>{$cost}</th><td>{$uCost}</td></tr>
					</table></div>
					<div class="lar-unlockable-unlockcol flcol">
						<div class="lar-unlockable-unlock-line flrow flexiia">
							<div class="lar-unlockable-unlock-btn">{$this->labels['unlock']}</div>
						</div>
						<div class="lar-unlockable-confirm-line">
							<div class="lar-unlockable-confirm-text">{$this->labels['sure']}</div>
							<div class="lar-unlockable-confirm-btns">
								<div class="lar-unlockable-confirm-no">{$this->labels['cancel']}</div>
								<div data-href="{$unlockLink}" class="lar-unlockable-confirm-yes">{$this->labels['yes']}</div>
							</div>
						</div>
					</div>
					<div class='lar-unlockable-not'>{$this->labels['notavail']}</div>
				</div>
EOT;
			}
			$content .= "</div></div></div></div></div>";
		}
		return $content;
	}

	function systems()
	{
		$content = '';
		if ($this->pools) {
			$typeAff = (\get_option('lws_woorewards_wc_myaccount_levelbars') == 'on') ? 'bars' : '';
			$content = <<<EOT
			<div class="lar_accordeon_container flcol">
				<div class="lar-accordeon-title-line flrow">
					<div class="lar-accordeon-title-text flexooa">{$this->labels['lsoverview']}</div>
					<div class="flexiia"></div>
				</div>
EOT;
			foreach ($this->pools as $pool) {
				$extraclass = \esc_attr($pool->getName());
				$userPointsWithSymbol = \LWS_WooRewards::formatPointsWithSymbol($pool->getPoints($this->userId), $pool->getName());
				$bars = '';
				$levels = array();
				$events = array();
				$rewards = array();
				$pointsOnCart = array();

				/* Ways to earn points */
				foreach ($pool->getEvents()->asArray() as $item) {
					if (!\method_exists($item, 'isValidCurrency') || $item->isValidCurrency()) {
						$eventInfo = array();
						$eventInfo['desc'] = $item->getTitle(false);
						if (!$eventInfo['desc'])
							$eventInfo['desc'] = $item->getDescription('frontend');
						$eventInfo['earned'] = $item->getGainForDisplay();
						$events[] = $eventInfo;
					}
				}
				/* levels or rewards*/
				if ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) {
					$done = \get_user_meta($this->userId, 'lws-loyalty-done-steps', false);
					if (!$done) $done = array();
					$currentLevel = 0;
					$cost = -9999;
					$level = array();
					foreach ($pool->getUnlockables()->sort()->asArray() as $item) {
						if ($item->getCost() != $cost) {
							$currentLevel += 1;
							if (!empty($level))
								$levels[] = $level;
							$level = array();
							$cost = $item->getCost();
							$level['cost'] = $cost;
							$level['title'] = $item->getGroupedTitle('view');
							$level['points'] = \LWS_WooRewards::formatPointsWithSymbol($item->getCost('front'), $item->getPoolName());
							$level['passed'] = false;
							$level['rewards'] = array();
							if ($pool->getPoints($this->userId) >= $level['cost'])
								$level['passed'] = true;
						}
						$level['rewards'][] = array( // reward
							'img'   => $item->getThumbnailImage(),
							'title' => $item->getTitle(),
							'desc'  => $item->getCustomDescription(),
							'owned' => (in_array($item->getId(), $done) || $item->noMorePurchase($this->userId))
						);
					}
					if (!empty($level))
						$levels[] = $level;
				} else {
					if ($pool->getOption('direct_reward_mode')) {
						$pointsOnCart = array(
							'rate' 			=> $pool->getOption('direct_reward_point_rate'),
							'maxpercent'	=> $pool->getOption('direct_reward_max_percent_of_cart'),
							'floor'			=> $pool->getOption('direct_reward_total_floor'),
							'subtotal'		=> $pool->getOption('direct_reward_min_subtotal'),
						);
					} else {
						foreach ($pool->getUnlockables()->sort()->asArray() as $item) {
							$rewards[] = array(
								'img'   => $item->getThumbnailImage(),
								'title' => $item->getTitle(false),
								'cost'  => $item->getCost(),
								'desc'  => $item->getCustomDescription(),
								'owned' => $item->noMorePurchase($this->userId),
							);
						}
					}
				}
				if ($typeAff == 'bars' && !empty($levels)) {
					$lastLevel = array_values(array_slice($levels, -1))[0];
					$currentPercent = intval(($pool->getPoints($this->userId) / $lastLevel['cost']) * 100);
					if ($currentPercent > 100) $currentPercent = 100;
					$percentUsed = 0;
					$curLevel = 0;
					$colpercents = '';
					$levelsDivs = '';
					$levelsPoints = '';
					foreach ($levels as $level) {
						$curLevel += 1;
						$levelPercent = intval(($level['cost'] / $lastLevel['cost']) * 100);
						$colpercents .= ($levelPercent - $percentUsed) . '% ';
						$percentUsed = $levelPercent;
						$ownedLevel = ($pool->getPoints($this->userId) >= $level['cost']) ? ' unlocked' : '';
						$levelsDivs .= '<div class="lws-lsov-sbarline-pin' . $ownedLevel . '">' . $curLevel . '</div>';
						$levelsPoints .= '<div class="lws-lsov-sbarline-points' . $ownedLevel . '">' . $level['cost'] . '</div>';
					}
					$bars = '<div class="lar-lsov-sbarline">';
					$bars .= '<div class="lar-lsov-sbarline-title">' . __('Your Current Progress', 'woorewards-pro') . '</div>';
					$bars .= '<div class="lar-lsov-sbarline-bargrid" style="grid-template-columns:' . $colpercents . '">';
					$bars .= '<div class="lar-lsov-sbarline-backbar"></div>';
					$bars .= '<div class="lar-lsov-sbarline-frontbar" style="width:' . $currentPercent . '%">' . $pool->getPoints($this->userId) . '</div>';
					$bars .= $levelsDivs;
					$bars .= $levelsPoints;
					$bars .= '</div>';
					$bars .= '</div>';
				}

				$startDate = '';
				$startDateDiv = '';
				$startDateTitleDiv = '';
				$start = $pool->getOption('period_start', '');
				if (!empty($start)) {
					$startDate = \date_i18n(\get_option('date_format'), $start->getTimestamp() + $start->getOffset());
					$startDateTitleDiv = <<<EOT
					<div class="flex00a lar-lsov-sline-couple flrow">
						<div class="flex00a lar-lsov-sline-label">{$this->labels['start']}</div>
						<div class="flex00a lar-lsov-sline-value lar-main-color">{$startDate}</div>
					</div>
EOT;
					$startDateDiv = <<<EOT
					<div class="lar-lsov-ls-cell-sep"></div>
					<div class="lar-lsov-ls-cell flexiia flrow">
						<div class="lar-lsov-ls-cell-title flexiia">{$this->labels['start']}</div>
						<div class="lar-lsov-ls-cell-value flexooa">{$startDate}</div>
					</div>
EOT;
				}

				$endDate = '';
				$endDateDiv = '';
				$endDateTitleDiv = '';
				$end = $pool->getOption('period_end', '');
				if (!empty($end)) {
					$endDate = \date_i18n(\get_option('date_format'), $end->getTimestamp() + $end->getOffset());
					$endDateTitleDiv = <<<EOT
					<div class="flex00a lar-lsov-sline-couple flrow">
						<div class="flex00a lar-lsov-sline-label">{$this->labels['end']}</div>
						<div class="flex00a lar-lsov-sline-value lar-main-color">{$endDate}</div>
					</div>
EOT;
					$endDateDiv = <<<EOT
					<div class="lar-lsov-ls-cell-sep"></div>
					<div class="lar-lsov-ls-cell flexiia flrow">
						<div class="lar-lsov-ls-cell-title flexiia">{$this->labels['end']}</div>
						<div class="lar-lsov-ls-cell-value flexooa">{$endDate}</div>
					</div>
EOT;
				}

				$pTitle = $pool->getOption('display_title');
				$pType = $pool->getOption('type');

				$eventCount = count($events);
				$rlcount = ($pType == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) ? count($levels) : count($rewards);
				$singular = apply_filters('lws_woorewards_point_symbol_translation', false, 1, $pool->getName());
				$singular = $singular ? $singular : __('Point', 'woorewards-pro');
				$plural = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pool->getName());
				$plural = $plural ? $plural : __('Points', 'woorewards-pro');
				$this->labels['ways'] = sprintf(__("Ways to earn %s", 'woorewards-pro'), $plural);
				$this->labels['earned'] = sprintf(__("Earned %s", 'woorewards-pro'), $plural);
				$this->labels['cost'] = sprintf(__("%s cost", 'woorewards-pro'), $plural);

				$content .= <<<EOT
				<div class="lar-accordeon-item {$extraclass}">
					<div class="lar-accordeon-not-expanded-cont flcol">
						<div class="lar-lsov-top-title flrow lar_overflow">
							<div class="flexooa lar-line-header">{$pTitle}</div>
							<div class="flexiia lar-line-header"></div>
							<div class="flexooa lar-line-header hlast"><strong>{$userPointsWithSymbol}</strong></div>
						</div>
					{$bars}
					<div class="lar-lsov-sline flrow">
						<div class="flexiia lar-lsov-sline-filler"></div>
						{$startDateTitleDiv}
						{$endDateTitleDiv}
						<div class="flex00a lar-lsov-sline-couple flrow">
							<div class="flex00a lar-lsov-sline-label">{$this->labels['ways']}</div>
							<div class="flex00a lar-lsov-sline-value lar-main-color">{$eventCount}</div>
						</div>
						<div class="flex00a lar-lsov-sline-couple flrow">
							<div class="flex00a lar-lsov-sline-label">{$this->labels[$pType]}</div>
EOT;
				if (!$levels && $pointsOnCart) {
					$content .= "<div class='flex00a lar-lsov-sline-value lar-main-color'>{$this->labels['pointsoncart']}</div>";
				} else {
					$content .= "<div class='flex00a lar-lsov-sline-value lar-main-color'>{$rlcount}</div>";
				}
				$content .= <<<EOT
						</div>
					</div>
				</div>
				<div class="lar-accordeon-expanded-cont flcol flexoia">
					<div class="flrow lar-expanded-line lar-overcolors">
						<div class="flexooa lar-line-header">{$pTitle}</div>
						<div class="flexiia lar-line-header"></div>
						<div class="flexooa lar-line-header lar-acc-icon lws-icon lws-icon-circle-up"></div>
					</div>
					<div class="lar-lsov-det-cont flrow">
						{$bars}
						<div class="lar-lsov-ls-info flcol flexdia">
							<div class="lar-lsov-det-top flrow">
								<div class="lar-lsov-stitle lar-main-color flexooa">{$this->labels['lsdetails']}</div>
								<div class="flexiia"></div>
							</div>
							<div class="lar-lsov-det-bodyr flcol">
								<div class="lar-lsov-ls-top flrow">
									<div class="lar-lsov-ls-cell flexiia flrow">
										<div class="lar-lsov-ls-cell-title flexiia">{$this->labels['sname']}</div>
										<div class="lar-lsov-ls-cell-value flexooa">{$pTitle}</div>
									</div>
									<div class="lar-lsov-ls-cell-sep"></div>
									<div class="lar-lsov-ls-cell flexiia flrow">
										<div class="lar-lsov-ls-cell-title flexiia">{$this->labels['balance']}</div>
										<div class="lar-lsov-ls-cell-value flexooa"><strong>{$userPointsWithSymbol}</strong></div>
									</div>
									{$startDateDiv}
									{$endDateDiv}
								</div>
								<div class="lar-lsov-ls-body flcol">
EOT;
				if (count($events) > 0) {
					$content .= <<<EOT
									<div class="lar-lsov-ls-earn-cont flcol flexiia">
										<div class="lar-lsov-ls-title-line flrow">
											<div class="lar-lsov-ls-title flexooa">{$this->labels['ways']}</div>
											<div class="flexiia"></div>
										</div>
										<div class="lar-lsov-ls-table-title flrow">
											<div class="flexiia">{$this->labels['perform']}</div>
											<div class="flexooa">{$this->labels['earned']}</div>
										</div>
EOT;
					/* Methods to earn points */
					foreach ($events as $event) {
						$content .= "<div class='lar-lsov-ls-table-line flrow'>";
						$content .= "<div class='flexiia'>{$event['desc']}</div>";
						$content .= "<div class='lar-lsov-ls-table-line-value flexooa'>{$event['earned']}</div></div>";
					}
					$content .= "</div>";
				}

				if (!empty($levels)) {
					/* Levels */
					$levelcount = 0;

					foreach ($levels as $level) {
						if (!empty($level)) {
							$levelcount += 1;
							$rowtitle = $this->labels['level'] . ' ' . $levelcount;
							$content .= "<div class='lar-lsov-ls-reward-cont flexiia'>";
							$content .= "<div class='lar-lsov-ls-title-line flrow'>";
							$content .= "<div class='lar-lsov-ls-title flexooa'>{$rowtitle}</div>";
							$content .= "<div class='flexiia'></div>";
							$content .= "<div class='lar-lsov-ls-tinfo flexooa'>{$level['title']}</div>";
							$content .= "<div class='lar-lsov-ls-tinfo flexooa'>{$level['points']}</div>";
							if ($level['passed'] == true)
								$content .= "<div class='lar-lsov-ls-passed flexooa lws-icon lws-icon-checkmark'></div>";
							$content .= "</div>";
							foreach ($level['rewards'] as $reward) {
								$libelle = '<b>' . $reward['title'] . '</b> - ' . $reward['desc'];
								$content .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>{$libelle}</div></div>";
							}
							$content .= "</div>";
						}
					}
				} else {
					if (!empty($pointsOnCart)) {
						/* Points on Cart */
						$content .= <<<EOT
						<div class="lar-lsov-ls-reward-cont flexiia">
							<div class="lar-lsov-ls-title-line flrow">
								<div class="lar-lsov-ls-title flexooa">{$this->labels['pointsoncart']}</div>
								<div class="flexiia"></div>
							</div>
							<div class="lar-lsov-ls-table-title flrow">
								<div class="flexiia">{$this->labels['descr']}</div>
							</div>
EOT;
						if (!empty($pointsOnCart['rate'])) {
							$content .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>";
							$content .= sprintf(__('Every %s you use is worth %s', 'woorewards-pro'), $singular, \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($pointsOnCart['rate'], true));
							$content .= "</div></div>";
						}
						if (!empty($pointsOnCart['maxpercent']) && $pointsOnCart['maxpercent'] < 100.0) {
							$content .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>";
							$content .= sprintf(__('You can only use your %s to reduce the cart total by %s%%', 'woorewards-pro'), $plural, $pointsOnCart['maxpercent']);
							$content .= "</div></div>";
						}
						if (!empty($pointsOnCart['floor'])  && $pointsOnCart['floor'] > 0.0) {
							$content .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>";
							$content .= sprintf(__('You can only use your %s to reduce the cart total to %s', 'woorewards-pro'), $plural, \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($pointsOnCart['floor']));
							$content .= "</div></div>";
						}
						if (!empty($pointsOnCart['subtotal']) && $pointsOnCart['subtotal'] > 0.0) {
							$content .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>";
							$content .= sprintf(__('The cart subtotal needs to be over %s if you want to use %s', 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($pointsOnCart['subtotal']), $plural);
							$content .= "</div></div>";
						}
						$content .= "</div>";
					} else {
						/* Rewards */
						if (count($rewards) > 0) {
							$content .= <<<EOT
						<div class="lar-lsov-ls-reward-cont flexiia">
							<div class="lar-lsov-ls-title-line flrow">
								<div class="lar-lsov-ls-title flexooa">{$this->labels['standard']}</div>
								<div class="flexiia"></div>
							</div>
							<div class="lar-lsov-ls-table-title flrow">
								<div class="flexiia">{$this->labels['redet']}</div>
								<div class="flexooa">{$this->labels['cost']}</div>
							</div>
EOT;
							foreach ($rewards as $reward) {
								$cost = $reward['cost'];
								$name = $reward['title'] ? $reward['title'] : $reward['desc'];
								if ($reward['owned'])
									$cost = "<div class='lar-lsov-ls-passed flexooa lws-icon lws-icon-checkmark'></div>";

								$content .= "<div class='lar-lsov-ls-table-line flrow'>";
								$content .= "<div class='flexiia'>{$name}</div>";
								$content .= "<div class='lar-lsov-ls-table-line-value flexooa'>{$cost}</div></div>";
							}
							$content .= "</div>";
						}
					}
				}
				$content .= "</div></div></div></div></div></div>";
			}
			$content .= "</div>";
		}
		return $content;
	}


	function history()
	{
		$content = <<<EOT
		<div class="lar_accordeon_container flcol">
			<div class="lar-accordeon-title-line flrow">
				<div class="lar-accordeon-title-text flexooa">{$this->labels['userhistory']}</div>
				<div class="flexiia"></div>
			</div>
			<div class="lar-accordeon-item">
				<div class="lar-accordeon-not-expanded-cont flcol">
					<div class="flrow lar_overflow">
						<div class="flexooa lar-line-header">{$this->labels['yourhistory']}</div>
						<div class="flexiia lar-line-header"></div>
						<div class="flexooa lar-line-header hlast"></div>
					</div>
				</div>
				<div class="lar-accordeon-expanded-cont flcol flexoia">
					<div class="flrow lar-expanded-line lar-overcolors">
						<div class="flexooa lar-line-header">{$this->labels['yourhistory']}</div>
						<div class="flexiia lar-line-header"></div>
						<div class="flexooa lar-line-header lar-acc-icon lws-icon lws-icon-circle-up"></div>
					</div>
EOT;

		$history = array();
		$doneStacks = array();
		foreach ($this->pools as $pool) {
			$stack = $pool->getStack($this->userId);
			if (!in_array($stack, $doneStacks)) {
				$doneStacks[] = $stack;
				if ($hist = $stack->getHistory(false, true, 0, 10)) {
					$poolName = $pool->getOption('display_title');
					foreach ($hist as $item) {
						$item['pool'] = $poolName;
						$history[] = $item;
					}
				}
			}
		}
		usort($history, function ($a1, $a2) {
			return strtotime($a2["op_date"]) - strtotime($a1["op_date"]);
		});
		$history = array_slice($history, 0, 15);

		$content .= "<div class='lar-history-grid'>";
		$content .= "<div class='lar-history-grid-title'>{$this->labels['lsystem']}</div>";
		$content .= "<div class='lar-history-grid-title'>{$this->labels['date']}</div>";
		$content .= "<div class='lar-history-grid-title'>{$this->labels['descr']}</div>";
		$content .= "<div class='lar-history-grid-title'>{$this->labels['points']}</div>";
		foreach ($history as $item) {
			$content .= "<div class='lar-history-grid-value'>{$item['pool']}</div>";
			$date = \LWS\WOOREWARDS\Core\PointStack::dateI18n($item['op_date']);
			$content .= "<div class='lar-history-grid-date'>{$date}</div>";
			$content .= "<div class='lar-history-grid-value'>{$item['op_reason']}</div>";
			$content .= "<div class='lar-history-grid-number'>{$item['op_value']}</div>";
		}
		$content .= '</div>';

		$after = \apply_filters('lws_woorewards_loyalty_page_after_history', '', $this->userId, $this->pools);
		if ($after)
			$content .= $after;
		$content .= '</div></div></div>';

		return $content;
	}

	function sponsoremail()
	{
		$widget = new \LWS\WOOREWARDS\PRO\Ui\Widgets\SponsorWidget(false);
		$shortcode = $this->demo ? $widget->template() : $widget->shortcode();

		return <<<EOT
	<div class="lar_accordeon_container flcol">
		<div class="lar-accordeon-title-line flrow">
			<div class="lar-accordeon-title-text flexooa">{$this->labels['emailsponsor']}</div>
			<div class="flexiia"></div>
		</div>
		<div class='lar-widget-container'>
			{$shortcode}
		</div>
	</div>
EOT;
	}

	function sponsorlink()
	{
		$widget = new \LWS\WOOREWARDS\PRO\Ui\Legacy\ReferralWidget(false);
		$shortcode = $this->demo ? $widget->template() : $widget->shortcode();
		return <<<EOT
		<div class="lar_accordeon_container flcol">
			<div class="lar-accordeon-title-line flrow">
				<div class="lar-accordeon-title-text flexooa">{$this->labels['sponsorlink']}</div>
				<div class="flexiia"></div>
			</div>
			<div class='lar-widget-container'>
			{$shortcode}
		</div>
	</div>
EOT;
	}
}
