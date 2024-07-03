<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** List all pools (starting with prefabs)
 *	Allows add, duplicate, delete[not_prefabs]
 *	Full edit for single pool is done in a dedicated page. */
class Pools extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';
	const SLUG = 'lws-wr-pools';

	static protected $filter = false;

	private $tab = 'wr_loyalty.';

	function __construct()
	{
		static $once = true;
		if( $once )
		{
			\add_filter('lws_ap_editlist_button_add_value_'.self::SLUG, array($this, 'getAddButtons'), 10, 2);
			\add_action('setup_theme', array($this, 'addNewPool'), 20);

			\add_filter('lws_ap_editlist_item_actions_'.self::SLUG, array($this, 'quickButtons'), 10, 3);
			\add_action('lws_wr_pool_admin_options_after_update', array($this, 'afterPoolUpdate'), 10, 1);
			$once = false;
		}
	}

	/** If URL contains the right args, add a new pool and redirect to edit page. */
	function addNewPool()
	{
		if( !\is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
			return;
		$action = isset($_GET['lwswr-action']) ? \sanitize_key($_GET['lwswr-action']) : false;
		if( !($action && in_array($action, array('add-standard', 'add-leveling'))) )
			return;
		$action = substr($action, 4);
		$page = isset($_GET['page']) ? \sanitize_text_field($_GET['page']) : false;
		if( false === strpos($page, LWS_WOOREWARDS_PAGE) )
			return;
		if( !(isset($_GET['nonce']) && \wp_verify_nonce($_GET['nonce'], 'lws_wr_new_system')) )
			return;

		$pool = false;
		if( isset($_GET['lwswr-source']) && ($src = \absint($_GET['lwswr-source'])) )
		{
			$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$src, 'deep'=>true))->last();
			if( !$pool )
			{
				\lws_admin_add_notice_once('lws-wre-pool-copy', __("The selected Loyalty System cannot be found for copy.", 'woorewards-pro'), array('level'=>'error'));
				return;
			}
			$pool->detach();
			$pool->setName('', true)->setOption('title', '');
		} else {
			$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('')->last();
		}

		$pool->setOption('disabled', true);
		if( 'standard' == $action )
			$pool->setOption('type', \LWS\WOOREWARDS\Core\Pool::T_STANDARD);
		else if( 'leveling' == $action )
			$pool->setOption('type', \LWS\WOOREWARDS\Core\Pool::T_LEVELLING);

		if( $pool )
		{
			$pool->save();
			if (!$pool->getId()) {
				return;
			}

			$url = \add_query_arg(
				array(
					'page' => LWS_WOOREWARDS_PAGE.'.loyalty',
					'tab' => $this->tab . $pool->getTabId(),
				),
				\admin_url('admin.php')
			);
			if( \wp_redirect($url) ) {
				exit;
			}
		}
	}

	/** Provide 3 buttons: wizard and one for each system type [standard, leveling] */
	function getAddButtons($buttons, $editlist)
	{
		$title = __('Configuration Wizard', 'woorewards-pro');
		$details = __('Select the program that matches your needs, follow the steps and let the wizard configure everything for you', 'woorewards-pro');
		$icon = 'lws-icon-wand';
		$colorstring = \lws_get_theme_colors('--button-color', '#872364');
		$url = \esc_attr(\add_query_arg(array(
			'page' => 'lwswizard-woorewards',
		), admin_url('admin.php')));

		$buttons['wizard'] = <<<EOT
		<a class="lws-big-icon-btn wizard" style="$colorstring" href="{$url}">
			<div class="icon {$icon}"></div>
			<div class="title">$title</div>
			<div class="description">$details</div>
		</a>
EOT;


		$title = __("Add a Standard Points and Rewards System", 'woorewards-pro');
		$details = __("Customers earn points by performing various actions. They can then use their points directly on the cart to get immediate discounts or spend them to unlock other rewards.", 'woorewards-pro');
		$icon = 'lws-icon-gold-coin';
		$colorstring = \lws_get_theme_colors('--button-color', '#526981');
		$nonce = \wp_create_nonce('lws_wr_new_system');
		$url = \esc_attr(\add_query_arg(array(
			'nonce' => $nonce,
			'lwswr-action' => 'add-standard',
		)));
		$buttons['standard'] =  <<<EOT
		<a class="lws-big-icon-btn standard" style="$colorstring" data-id="{$editlist->m_Id}" href="{$url}">
			<div class="icon {$icon}"></div>
			<div class="title">$title</div>
			<div class="description">$details</div>
		</a>
EOT;

		$title = __("Add a Leveling Points and Rewards System", 'woorewards-pro');
		$details = __("Customers earn points and unlock levels and rewards as they progress. <b>In a leveling system, customers never spend their points.</b>", 'woorewards-pro');
		$icon = 'lws-icon-g-chart';
		$colorstring = \lws_get_theme_colors('--button-color', '#16997a');
		$url = \esc_attr(\add_query_arg(array(
			'nonce' => $nonce,
			'lwswr-action' => 'add-leveling',
		)));
		$buttons['leveling'] =  <<<EOT
		<a class="lws-big-icon-btn leveling" style="$colorstring" data-id="{$editlist->m_Id}" href="{$url}">
			<div class="icon {$icon}"></div>
			<div class="title">$title</div>
			<div class="description">$details</div>
		</a>
EOT;

		if( isset($buttons['add']) )
			unset($buttons['add']);
		return $buttons;
	}

	/** after pool update (via options.php), the redirection should includes the new pool slug.
	 * The only way to set that is to hook into the next redirection to change location. */
	function afterPoolUpdate($pool)
	{
		if( $pool->isDeletable() ) // for prefabs, url never change whatever the name
		{
			$arg = array('tab' => $this->tab . $pool->getTabId());

			\add_filter('wp_redirect', function($location, $status)use($arg){
				return \add_query_arg($arg, $location);
			}, 10, 2);
		}
	}

	function quickButtons($btns, $id, $data)
	{
		if( isset($data['prefabs']) && $data['prefabs'] != 'no' )
			unset($btns['del']);

		static $labels = false;
		if( false === $labels )
		{
			$labels = array(
				'edit' => __("Edit", 'woorewards-pro'),
				'dup'  => __("Copy", 'woorewards-pro'),
				'on'   => __("Turn On", 'woorewards-pro'),
				'off'  => __("Turn Off", 'woorewards-pro'),
			);
		}

		$btns = array_merge(
			array(
				'edit'      => $this->coatActionButton($labels['edit'], 'lws-icon-settings-gear', 'a', array(
					'style' => 'background-color: var(--group-color, #333); font-weight: bold;',
					'href'  => \esc_attr(\add_query_arg(
						array(
							'page' => LWS_WOOREWARDS_PAGE.'.loyalty',
							'tab' => $this->tab . 'wr_upool_' . $id,
						),
						\admin_url('admin.php')
					)),
				)),
				'toggleOn'  => $this->coatActionButton($labels['on'], 'lws-icon-power', 'div', array(
					'style' => 'background-color: #0a610a;',
					'class' => 'lws_action_toggle on'.($data['enabled'] ? ' hidden' : ''),
				)),
				'toggleOff' => $this->coatActionButton($labels['off'], 'lws-icon-power', 'div', array(
					'style' => 'background-color: #aa1d25;',
					'class' => 'lws_action_toggle off'.($data['enabled'] ? '' : ' hidden'),
				)),
			),
			$btns
		);

		$btns['dup'] = $this->coatActionButton($labels['dup'], 'lws-icon-copy', 'a', array(
			'style' => 'background-color: #d82;',
			'href'  => \esc_attr(\add_query_arg(array(
				'nonce' => \wp_create_nonce('lws_wr_new_system'),
				'lwswr-action' => 'standard' == $data['type'] ? 'add-standard' : 'add-leveling',
				'lwswr-source' => \esc_attr($id),
			))),
		));

		if( !(isset($data['name']) && $data['name']) )
		{
			unset($btns['toggleOn']);
			unset($btns['toggleOff']);
		}

		return $btns;
	}

	function total()
	{
		return self::getCollection()->count();
	}

	function read($limit=null)
	{
		$pools = array();
		$collection = self::getCollection()->asArray();
		if( $limit && $limit->valid() )
			$collection = \array_slice($collection, $limit->offset, $limit->count);

		foreach( $collection as $pool )
		{
			$pools[] = $this->objectToArray($pool);
		}
		return $pools;
	}

	static public function statusList()
	{
		static $trads = false;
		if( $trads === false )
		{
			$trads = array(
				'on'		=> _x("On", "editlist cell pool status", 'woorewards-pro'),
				'off'		=> _x("Off", "editlist cell pool status", 'woorewards-pro'),
				'sch'		=> _x("Scheduled", "editlist cell pool status", 'woorewards-pro'),
				'run'		=> _x("Running", "editlist cell pool status", 'woorewards-pro'),
				'ra'		=> _x("Ended - Rewards available", "editlist cell pool status", 'woorewards-pro'),
				'end'		=> _x("Ended", "editlist cell pool status", 'woorewards-pro'),
			);
		}
		return $trads;
	}

	private function trad($key)
	{

		return self::statusList()[$key];
	}

	/** compute a rendering for actif state of pool, with date indication if any. */
	private function setStatus(&$data)
	{
		static $format = false;
		if( $format === false )
			$format = get_option('date_format');

		$dates = array();
		$txt = $this->trad('on');
		$status = 'enabled';

		if( $data['period_start'] || $data['period_mid'] || $data['period_end'] )
		{
			$txt = $this->trad('sch');
			$status = 'event';

			$now = \date_create('now', \function_exists('\wp_timezone') ? \wp_timezone() : NULL)->setTime(0, 0, 0); // dateEnd is included, so take care now is computed without time
			if( $data['period_start'] && $now < $data['period_start'] )
			{
				$txt = $this->trad('sch');
				$status = 'futur';
			}else{
				if( !$data['period_end'] || $now <= $data['period_end'] )
				{
					$txt = $this->trad('run');
					$status = 'running';
				}
				if ($data['period_mid'] && $now > $data['period_mid'])
				{
					$txt = $this->trad('ra');
					$status = 'buyable';
				}
				if ($data['period_end'] && $now > $data['period_end'])
				{
					$txt = $this->trad('end');
					$status = 'outdated';
				}
			}

			$dates['start'] = $data['period_start'] ? date_i18n($format, $data['period_start']->getTimestamp() + $data['period_start']->getOffset()) : '-';
			if( !$data['period_mid'] && !$data['period_end'] )
				$dates['end'] = '-';
			else if( $data['period_mid'] && $data['period_end'] && $data['period_mid'] == $data['period_end'] )
			$dates['end'] = date_i18n($format, $data['period_end']->getTimestamp() + $data['period_end']->getOffset());
			else
			{
				$dates['mid'] = $data['period_mid'] ? date_i18n($format, $data['period_mid']->getTimestamp() + $data['period_mid']->getOffset()) : '-';
				$dates['end'] = $data['period_end'] ? date_i18n($format, $data['period_end']->getTimestamp() + $data['period_end']->getOffset()) : '-';
			}
		}

		if( !$data['enabled'] )
		{
			$txt = $this->trad('off');
			$status = 'disabled';
		}

		$data['status'] = "<div class='pool-info status $status'>$txt</div>";
		$data['event'] = '';
		if( !empty($dates) )
		{
			$data['event'] = "<div class='pool-info event $status'>{$dates['start']} → {$dates['end']}</div>";
		}
		$data['period_start'] = $data['period_start'] ? $data['period_start']->format('Y-m-d') : '';
		$data['period_mid']   = $data['period_mid']   ? $data['period_mid']->format('Y-m-d') : '';
		$data['period_end']   = $data['period_end']   ? $data['period_end']->format('Y-m-d') : '';
	}

	function labels()
	{
		$labels = array(
			'display_title' => array(__("Points and Rewards System", 'woorewards-pro'), "1fr"),
			'status'        => array(__("Status", 'woorewards-pro'), 'min-content'),
			'event'        => array(__("Event Dates", 'woorewards-pro'), 'max-content'),
			'behavior'		=> array(__("Type", 'woorewards-pro'), 'max-content'),
			'sname'			=> array(__("Shortcode Attribute", 'woorewards-pro'), 'auto'),
			'shared'		=> array(__("Points Reserve", 'woorewards-pro'), 'max-content'),
		);
		return \apply_filters('lws_woorewards_pools_labels', $labels);
	}

	/** @param $forceShared (bool) used for freshly added pools since shared stack could be already cached. */
	private function objectToArray($pool, $forceShared=false)
	{
		$data = $pool->getOptions(array(
			'title', 'display_title', 'enabled', 'type', 'stack', 'period_start', 'period_mid', 'period_end', 'happening'
		));

		$data[self::ROW_ID] = $pool->getId();
		$data['feeder'] = $data['src_id'] = $pool->getId();
		$data['sharing'] = 'no'; // sharing and feeder are set for copy action and does not represent reallity.
		$data['prefabs'] = $pool->isDeletable() ? 'no' : 'yes';
		$data['name'] = $pool->getName();

		$data['display_title'] = $this->coatTitleToEditButton(
			$data['display_title'],
			\esc_attr(\add_query_arg(
				array('page' => LWS_WOOREWARDS_PAGE.'.loyalty', 'tab' => $this->tab . $pool->getTabId()),
				\admin_url('admin.php')
			)),
			'editlist-row-title-edit'
		);

		$data['behavior'] = "<div class='pool-info type {$data['type']}'>" . ($data['type'] == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ? __("Leveling", 'woorewards-pro') : __("Standard", 'woorewards-pro')) . "</div>";
		$this->setStatus($data);

		$data['enabled'] = $data['enabled'] ? 'on' : '';
		$data['shared'] = '';
		$data['sname'] = "<div class='pool-info shortcode-wrapper lws_ui_value_copy'><div class='content'>{$data['name']}</div><div class='copy-icon lws-icon-copy copy'></div></div>";

		$shared = $this->getSharedStackColor($data['stack']);
		if (!$shared && $forceShared) {
			$shared = ((true === $forceShared) ? 'rgba(128,128,128,0)' : $forceShared);
		}
		$title = \esc_attr($pool->getId() . ' / ' . $data['stack']); // as tooltips
		$data['shared'] = "<div class='pool-info shared' title='{$title}' style='--shared-color:{$shared};'>{$data['stack']}</div>";

		return $data;
	}

	/** @param $stackId (string) a pool stack id.
	 * @return (false|string) if stack is shared by several pools,
	 * return a CSS compliant color, else return false. */
	protected function getSharedStackColor($stackId)
	{
		static $colors = false;
		if( false === $colors )
		{
			$colors = array();
			$conv =& \LWS\WOOREWARDS\PRO\Conveniences::instance();
			// getPoolsInfo ensure order since it is sorted by ID
			foreach( $conv->getPoolsInfo() as &$info )
			{
				$colors[$info->stack_id] = isset($colors[$info->stack_id]); // true if already met
			}
			//$colors = \array_filter($colors); // keep only shared

			$index = 0;
			// fill colors
			foreach( $colors as $key => $shared )
			{
				$colors[$key] = $conv->getHSLFromIndex($index, 50, 80);
				++$index;
			}
		}
		return isset($colors[$stackId]) ? $colors[$stackId] : false;
	}

	public function defaultValues()
	{
		$values = array(
			'type'       => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			self::ROW_ID => '',
			'src_id'     => '',
			'enabled'    => '',
			'prefabs'    => 'no'
		);
		return $values;
	}

	/** no edition, use row actions.
	 * Only here to be compatible with write() method. */
	function input()
	{
		$rowId = self::ROW_ID;
		$str = <<<EOT
<input type='hidden' name='{$rowId}' class='lws_woorewards_pool_id' />
<input type='hidden' name='src_id' class='lws_woorewards_pool_duplic' />
<input type='hidden' name='prefabs' class='lws_wre_pool_edit_prefabs_value' />
<input type='hidden' name='type' class='lws_wre_pool_edit_type' />
<input type='checkbox' name='enabled' class='lws_woorewards_pool_enable' />
EOT;
		return $str;
	}

	/** Here called by a row action.
	 *	input() is only kept for compatibility/demo purpose. */
	function write($row)
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'values'   => $row,
			'format'   => array(
				self::ROW_ID => 'D', // does not support creation this way anymore
				'src_id'     => 'd',
				'type'       => '/^('.\LWS\WOOREWARDS\Core\Pool::T_STANDARD.'|'.\LWS\WOOREWARDS\Core\Pool::T_LEVELLING.')$/',
				'enabled'    => 't',
			),
			'defaults' => array(
				self::ROW_ID => '',
				'src_id'     => '',
				'enabled'    => '',
			),
			'labels'   => array(
				'type'       => __("Behavior", 'woorewards-pro'),
				'enabled'    => __("Enabled", 'woorewards-pro'),
				self::ROW_ID => __("(Missing System, Please reload the page)", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? new \WP_Error('400', $values['error']) : false;

		if( isset($row[self::ROW_ID]) && ($id = intval($row[self::ROW_ID])) )
		{
			// quick update
			$pool = self::getCollection()->find($id);
			if( $pool )
			{
				$pool->setOption('disabled', empty($row['enabled']));
				$pool->save(false, false);

				$row = $this->objectToArray($pool);
				return $row;
			}
			else
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", 'woorewards-pro'));
		}
		return false;
	}

	function erase($row)
	{
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = self::getCollection()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", 'woorewards-pro'));
			}
			else if( !$item->isDeletable() )
			{
				return new \WP_Error('403', __("The default Loyalty Systems cannot be deleted.", 'woorewards-pro'));
			}
			else
			{
				$item->delete();
				return true;
			}
		}
		return false;
	}

	static function getCollection()
	{
		static $collection = false;
		if( $collection === false )
		{
			$collection = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load();
			if( isset($_GET['poolfilter']) && !empty(self::$filter = trim($_GET['poolfilter'])) )
			{
				$collection = $collection->filter(array(\get_class(), 'passIn'));
			}
			$collection->sort();
		}
		return $collection;
	}

	/** @see $this->filter used with arg $_GET['poolfilter'] on computed status @see statusList() */
	static function passIn($pool)
	{
		$data = $pool->getOptions(array('enabled', 'period_start', 'period_mid', 'period_end'));
		$status = '';
		if( !$data['enabled'] )
		{
			$status = 'off';
		}
		else
		{
			if( $data['period_start'] || $data['period_mid'] || $data['period_end'] )
			{
				$status = 'run';
				$now = \date_create();
				if( $data['period_start'] && $now < $data['period_start'] )
					$status = 'sch';
				else if( $data['period_end'] && $now >= $data['period_end'] )
					$status = 'end';
				else if( $data['period_mid'] && $now >= $data['period_mid'] )
					$status = 'ra';
			}
			else
				$status = 'on';
		}
		return self::$filter == $status;
	}
}
