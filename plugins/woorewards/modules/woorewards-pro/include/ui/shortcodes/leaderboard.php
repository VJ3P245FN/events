<?php
namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Shortcode to show a leaderboard for a loyalty system */
class Leaderboard
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_leaderboard', array($me, 'shortcode'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-leaderboard', LWS_WOOREWARDS_PRO_CSS . '/templates/leaderboard.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
        \wp_enqueue_style('wr-leaderboard');
	}

	/** Show a leaderboard for a loyalty system
	 * [wr_leaderboard system="system_name" columns="column_list" columns_headers="titles_list" count="15"]
	 * @param $atts array :
	 * * 'system' the loyalty system for which to show the progress bar
	 * * 'columns' the columns to display, possible values are :
	 * ** rank (if no columns specified, this one is displayed)
	 * ** user_nickname (if no columns specified, this one is displayed)
	 * ** points (if no columns specified, this one is displayed)
	 * ** achievements (badges images with achievement name on hover)
	 * ** badges (badges images with badge name on hover)
	 * ** last_badge (same)
	 * ** user_title
	 * ** title_date
	 * * 'columns_headers' columns headers
	 * * 'badge_ids' (optional) a restricted list of badges (default is all)
	 * * 'achievement_ids' (optional) a restricted list of achievements (default is all)
	 * * 'count' the number of rows to show
	 */
	public function shortcode($atts=array(), $content='')
	{
		if( !\get_option('lws_woorewards_enable_leaderboard') )
			return '';

		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\shortcode_atts(array(
			'system' => '',
			'columns' => 'rank, user_nickname, points',
			'columns_headers' => '',
			'badge_ids' => false,
			'achievement_ids' => false,
			'count' => 15
		), $atts, 'wr_leaderboard'));

		if( !$atts['system'] )
			return '';
		$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($atts['system'], true);
		if( !$pool )
			return sprintf("Cannot find Loyalty System '%s'. Check arguments of the [%s] shortcode", $atts['system'], 'wr_leaderboard');

		$atts['columns'] = array_map('trim', explode(',', $atts['columns']));
		$atts['columns_headers'] = $this->getColumnHeaders($atts);
		if (false !== $atts['badge_ids'])
		{
			$atts['badge_ids'] = \array_filter(\array_map('\intval', explode(',', $atts['badge_ids'])));
			$atts['badge_ids'] = array_fill_keys($atts['badge_ids'], true);
		}

		/* Get the users and their points in required system (authorized by default) */
		global $wpdb;
		$sql = <<<EOT
SELECT m.user_id, u.display_name as user_nickname, m.meta_value as user_points
FROM {$wpdb->usermeta} as m
INNER JOIN {$wpdb->users} as u ON u.ID=m.user_id
LEFT JOIN {$wpdb->usermeta} as auth ON auth.user_id=m.user_id AND auth.meta_key='wr_user_leadeboard_auth'
WHERE m.meta_key=%s
AND (auth.meta_value IS NULL OR auth.meta_value = 'yes')
GROUP BY m.user_id
ORDER BY CAST(m.meta_value as SIGNED INTEGER) DESC
LIMIT 0, %d
EOT;

		$top = $wpdb->get_results($wpdb->prepare($sql, $pool->getStack(0)->metaKey(), \absint($atts['count'])), OBJECT_K);
		if( $top )
			$top = $this->getUsersData($top, $atts, $pool);
		return $this->getContent($top, $atts);
	}

	protected function getColumnHeaders($atts)
	{
		if( \trim($atts['columns_headers']) )
			$headers = array_map('trim', explode(',', $atts['columns_headers']));
		else
			$headers = array();
		for( $i=count($headers) ; $i<count($atts['columns']) ; ++$i )
		{
			switch($atts['columns'][$i])
			{
				case 'rank':
					$headers[$i] = __("Rank", 'woorewards-pro');
					break;
				case 'user_nickname':
					$headers[$i] = __("User", 'woorewards-pro');
					break;
				case 'points':
					$headers[$i] = __("Points", 'woorewards-pro');
					break;
				case 'badges':
					$headers[$i] = __("Badges", 'woorewards-pro');
					break;
				case 'last_badge':
					$headers[$i] = __("Last Badge", 'woorewards-pro');
					break;
				case 'achievements':
					$headers[$i] = __("Achievements", 'woorewards-pro');
					break;
				case 'user_title':
					$headers[$i] = __("Title", 'woorewards-pro');
					break;
				case 'title_date':
					$headers[$i] = __("Date", 'woorewards-pro');
					break;
				default:
					$headers[$i] = $atts['columns'][$i];
			}
		}
		return $headers;
	}

	protected function getBadgesAndAchievements($atts, $columns)
	{
		$data = (object)array(
			'badges'       => array(),
			'achievements' => array(),
		);
		if( !($columns['badges'] || $columns['last_badge'] || $columns['achievements']) )
			return $data;

		$grep = (false !== $atts['achievement_ids'] ? \array_filter(\array_map('\intval', \explode(',', $atts['achievement_ids']))) : false);

		/// get badges and achievments
		foreach (\LWS_WooRewards_Pro::getLoadedAchievements()->asArray() as $achievement)
		{
			$badge = $achievement->getBadge();
			$badgeId = $badge->getId();
			if (!$grep || \in_array($badgeId, $grep) || \in_array($achievement->getId(), $grep))
			{
				$data->achievements[$badgeId] = array(
					'badge_id' => $badgeId,
					'title'    => $achievement->getOption('display_title'),
					'src'    => $badge->getThumbnailUrl(),
				);
			}
			$data->badges[$badgeId] = $badge;
		}

		// complete if badge is not an achievement
		if ($grep)
		{
			foreach ($grep as $badgeId)
			{
				if (!isset($data->achievements[$badgeId]))
				{
					$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($badgeId, true);
					if ($badge->isValid())
					{
						$data->achievements[$badgeId] = array(
							'badge_id' => $badgeId,
							'title'    => $badge->getTitle(),
							'src'      => $badge->getThumbnailUrl(),
						);
					}
					$data->badges[$badgeId] = $badge;
				}
			}
		}
		return $data;
	}

	protected function getTitlesIds($pool)
	{
		$titleIds = array();
		foreach ($pool->getUnlockables()->filterByType('lws_woorewards_pro_unlockables_usertitle')->asArray() as $title)
		{
			$titleIds[] = \intval($title->getId());
		}
		return $titleIds;
	}

	protected function getColumnsToDisplay($atts)
	{
		return array_merge(
			array(
				'rank' => false,
				'user_nickname' => false,
				'points' => false,
				'badges' => false,
				'last_badge' => false,
				'achievements' => false,
				'user_title' => false,
				'title_date' => false,
			),
			\array_fill_keys($atts['columns'], true)
		);
	}

	/** Fill each item in $users with data required by $atts['columns']
	 *	@$users (array of object) with user id as key */
	protected function getUsersData($users, $atts, $pool)
	{
		global $wpdb;
		$columns = $this->getColumnsToDisplay($atts);
		$badges = $this->getBadgesAndAchievements($atts, $columns);
		$userIds = \array_keys($users);
		$acquired = array();
		foreach ($userIds as $uid)
			$acquired[$uid] = (object)array('badges' => array(), 'titles' => '',);
		$userIds = implode(',', \array_map('\intval', $userIds));
		$dateFormat = \get_option('date_format');

		/// read aquired badges
		if( $columns['badges'] || $columns['last_badge'] || $columns['achievements'] )
		{
			$badgeSql = <<<EOT
SELECT *
FROM {$wpdb->lwsWooRewardsBadges} as b
WHERE b.user_id IN ({$userIds})
ORDER BY b.user_id, b.assign_date DESC
EOT;
			foreach ($wpdb->get_results($badgeSql) as $earning)
			{
				$acquired[$earning->user_id]->badges[$earning->badge_id] = \mysql2date($dateFormat, $earning->assign_date);
			}
		}

		/// get title acquirement dates
		if( $columns['title_date'] && ($titleIds = $this->getTitlesIds($pool)) )
		{
			$titleIds = implode(',', $this->getTitlesIds($pool));
			$titleSql = <<<EOT
SELECT h.user_id, MAX(h.mvt_date) as title_date
FROM {$wpdb->lwsWooRewardsHistoric} as h
WHERE h.origin IN ({$titleIds})
AND h.user_id IN ({$userIds})
GROUP BY h.user_id
EOT;
			foreach ($wpdb->get_results($titleSql) as $earning)
			{
				$acquired[$earning->user_id]->titles = \LWS\WOOREWARDS\Core\PointStack::dateI18n($earning->title_date);
			}
		}

		/// prepare the rows
		$poolName = $pool->getName();
		$index = 0;
		foreach( $users as $userId => &$user )
		{
			$user->rank = ++$index;

			if( $columns['user_title'] )
				$user->user_title = \LWS\WOOREWARDS\PRO\Core\UserTitle::getTitle($userId);

			if( $columns['title_date'] )
				$user->title_date = $acquired[$userId]->titles;

			if( $columns['points'] )
				$user->points = \LWS_WooRewards::formatPointsWithSymbol($user->user_points, $poolName);

			if( $columns['badges'] || $columns['last_badge'] )
			{
				$user->badges = '';
				$user->last_badge = '';
				if( $acquired[$userId]->badges )
				{
					foreach( $acquired[$userId]->badges as $badgeId => $acquire )
					{
						if( false === $atts['badge_ids'] || isset($atts['badge_ids'][$badgeId]) )
						{
							if( !isset($badges->badges[$badgeId]) )
								$badges->badges[$badgeId] = new \LWS\WOOREWARDS\PRO\Core\Badge($badgeId, true);

							if( $badges->badges[$badgeId]->isValid() )
							{
								$title = \esc_attr($badges->badges[$badgeId]->getTitle());
								$user->last_badge = sprintf(
									"<img title='%s' alt='%s'  src='%s'>",
									$title,
									$title,
									\esc_attr($badges->badges[$badgeId]->getThumbnailUrl())
								);
								$user->badges .= $user->last_badge;
							}
						}
					}
				}
			}

			if( $columns['achievements'] )
			{
				$user->achievements = '';
				if ($badges->achievements && $acquired[$userId]->badges)
				{
					foreach ($badges->achievements as $badgeId => $achievement)
					{
						if (isset($acquired[$userId]->badges[$badgeId]))
						{
							$user->achievements .= sprintf(
								"<img title='%s' alt='%s'  src='%s'>",
								\esc_attr($achievement['title']),
								\esc_attr($achievement['title']),
								\esc_attr($achievement['src'])
							);
						}
					}
				}
			}
		}
		return \apply_filters('lws_woorewards_leaderboard_users', $users, $atts, $pool, $badges, $acquired);
	}

	protected function getContent($users, $atts)
	{
		$this->enqueueScripts();

		$content = '';
		foreach( $users as $user )
		{
			$content .= "\n\t<div class='wr-leaderboard-line'>";
			foreach( $atts['columns'] as $column )
			{
				$value = isset($user->{$column}) ? $user->{$column} : '';
				$content .= sprintf("\n\t\t<div class='wr-leaderboard-cell %s'>%s</div>", \esc_attr($column), $value);
			}
			$content .= "\t</div>";
		}

		$gridTemplateColumns = implode(' ', array_fill_keys($atts['columns'], 'auto'));
		$head = '';
		for( $i=0 ; $i<count($atts['columns']) ; ++$i )
			$head .= sprintf("\n\t\t<div class='wr-leaderboard-header-cell %s'>%s</div>", \esc_attr($atts['columns'][$i]), isset($atts['columns_headers'][$i]) ? $atts['columns_headers'][$i] : '');

		return <<<EOT
<div class='wr-leaderboard-grid' style='grid-template-columns:{$gridTemplateColumns}'>
	<div class='wr-leaderboard-header-line'>{$head}</div>{$content}
</div>
EOT;
	}
}
