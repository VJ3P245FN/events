<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a badge column to customer editlist.
 * Add a filter on user badge. */
class UsersPointsBadgeFilter extends \LWS\Adminpanel\EditList\Filter
{
	private $args = false;
	private $name = '';

	function __construct($name)
	{
		parent::__construct("lws-editlist-filter-search lws-editlist-filter-" . strtolower($name));
		$this->name = $name;

		static $once = true;
		if( $once )
		{
			\add_filter('lws_woorewards_admin_userspoints_request', array($this, 'filter'), 10, 2);
			\add_filter('lws_woorewards_ui_userspoints_rewards_cell', array($this, 'rewardCellContent'), 11, 2);
		}
		$once = false;
	}

	function rewardCellContent($content, $user)
	{
		if ($user && isset($user['user_id'])) {
			$c = \LWS\WOOREWARDS\PRO\Core\Badge::countByUser($user['user_id']);
			if( $c )
			{
				$url = \esc_attr(\add_query_arg(array('post_type'=>\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, 'user_id'=>$user['user_id']), \admin_url('edit.php')));
				if ($url) {
					static $link = false;
					if( $link === false )
						$link = __("See badges (%d)", 'woorewards-pro');
					$label = sprintf($link, $c);
					$content[] = "<a class='lws-adm-btn lws_wre_rewards_link' href='{$url}' target='_blank'>{$label}</a>";
				}
			} else {
				static $disp = false;
				if ($disp === false)
					$disp = __("No badge", 'woorewards-pro');
				$content[] = "<div class='lws-adm-btn disabled lws_wre_rewards_no_link'>{$disp}</div>";
			}
		}
		return $content;
	}

	function filter($request, $list=true)
	{
		$args = $this->getArgs();
		if ($this->args->value) {
			$request->innerJoin(
				\LWS\WOOREWARDS\PRO\Core\Badge::getLinkTable(),
				'badge',
				sprintf('badge.user_id=u.ID AND badge.badge_id=%d', (int)$this->args->value)
			);
		}
		return $request;
	}

	function input($above=true)
	{
		$args = $this->getArgs();
		$label = __('Filter by badge', 'woorewards-pro');
		$apply = __('Apply', 'woorewards-pro');
		$ph = __('Badge ...', 'woorewards-pro');

		$retour = <<<EOT
<div class='lws-editlist-filter-box'>
	<div class='lws-editlist-filter-box-title'>{$label}</div>
	<div class='lws-editlist-filter-box-content'>
		<input name='{$args->key}' class='lac_select lws-ignore-confirm' value='{$args->value}' data-ajax='lws_woorewards_badge_list' data-placeholder='{$ph}'>
		<button class='lws-adm-btn lws-editlist-filter-btn'>{$apply}</button>
	</div>
</div>
EOT;
		return $retour;
	}

	private function getArgs()
	{
		if( false === $this->args )
		{
			$this->args = (object)array(
				'key'   => $this->name,
				'value' => ''
			);

			if( isset($_GET[$this->args->key]) && !empty($badge = trim($_GET[$this->args->key])) && is_numeric($badge) )
				$this->args->value = \absint($badge);
		}
		return $this->args;
	}

}