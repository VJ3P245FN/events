<?php
namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class ProgressBar
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_progress_bar', array($me, 'shortcode'));
        \add_filter('lws_adminpanel_stygen_content_get_'.'progressbar_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		/** Admin */
		\add_filter('lws_woorewards_rewards_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['progressbar'] = array(
			'id' => 'lws_woorewards_sc_progressbar',
			'title' => __("Progress Bar", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_progress_bar system="set the name of your system here" header="Your Current Progress"]',
				'description' =>  __("This shortcode displays a progress bar for a points and rewards system.", 'woorewards-pro') . "<br/>" .
				__("This is very useful to incentivise customers to reach a higher level.", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'header',
						'desc' => __("The text displayed before the progress bar", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		$fields['progressstyle'] = array(
			'id' => 'lws_woorewards_progressbar_template',
			'type' => 'stygen',
			'extra' => array(
				'purpose' => 'filter',
				'template' => 'progressbar_template',
				'html' => false,
				'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/progressbar.css',
			)
		);
		return $fields;
	}

	function registerScripts()
	{
        \wp_register_style('wr-progress-bar', LWS_WOOREWARDS_PRO_CSS.'/templates/progressbar.css?stygen=lws_woorewards_progressbar_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
        \wp_enqueue_style('wr-progress-bar');
	}

	/** Show a progress bar for a loyalty system
	 * [wr_progress_bar system='poolname']
	 * @param $atts array :
	 * * 'system' the loyalty system for which to show the progress bar
	 */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array('system' => '', 'header' => '')));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_progress_bar');
		if (!$atts['system'])
			return '';
		if (!$userId)
			return '';
		$content = '';

		/* Get the data */
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if ($pools && $pools->count())
		{
			$pool = $pools->first();
			$cost = -9999;
			$levels = array();
			foreach ($pool->getUnlockables()->sort()->asArray() as $item) {
				if ($item->getCost() != $cost) {
					$cost = $item->getCost();
					$levels[] = $cost;
				}
			}
			if (!empty($levels))
			{
				$content = $this->getContent($pool->getPoints($userId), $levels, $atts['header']);
			}
		}
		return $content;
	}

	/* StyGen */
	public function template()
	{
		$levels = array('200', '400', '800');
		$points = rand(100,800);
		$content = $this->getContent($points, $levels, '');
		return $content;
	}

	protected function getContent($userPoints, $levels, $header)
	{
		$this->enqueueScripts();
		if(empty($header)) $header = __('Your Current Progress', 'woorewards-pro');
		$lastLevel = array_values(array_slice($levels, -1))[0];
		$currentPercent = intval(($userPoints / $lastLevel) * 100);
		if($currentPercent > 100) $currentPercent = 100;
		$percentUsed = 0;
		$curLevel = 0;
		$colpercents = '';
		$levelsDivs = '';
		$levelsPoints = '';
		foreach ($levels as $level) {
			$curLevel += 1;
			$levelPercent = intval(($level / $lastLevel) * 100);
			$colpercents .= ($levelPercent - $percentUsed) . '% ';
			$percentUsed = $levelPercent;
			if($userPoints >= $level)
			{
				$levelsDivs .= '<div class="lwss_selectable pb-pin unlocked" data-Type="Unlocked Pin">' . $curLevel . '</div>';
				$levelsPoints .= '<div class="lwss_selectable pb-points unlocked" data-Type="Unlocked Points">' . $level . '</div>';
			}else{
				$levelsDivs .= '<div class="lwss_selectable pb-pin" data-Type="Pin">' . $curLevel . '</div>';
				$levelsPoints .= '<div class="lwss_selectable pb-points" data-Type="Points">' . $level . '</div>';
			}
		}
		$content = '<div class="lwss_selectable pb-container" data-Type="Main Container">';
		$content .= '<div class="lwss_selectable pb-title" data-Type="Title">' . $header . '</div>';
		$content .= '<div class="lwss_selectable pb-grid" style="grid-template-columns:' . $colpercents . '" data-Type="Grid">';
		$content .= '<div class="lwss_selectable pb-backbar" data-Type="Background Bar"></div>';
		$content .= '<div class="lwss_selectable pb-frontbar" style="width:' . $currentPercent . '%" data-Type="User Progress Bar">' . $userPoints . '</div>';
		$content .= $levelsDivs;
		$content .= $levelsPoints;
		$content .= '</div>';
		$content .= '</div>';
		return $content;
	}
}