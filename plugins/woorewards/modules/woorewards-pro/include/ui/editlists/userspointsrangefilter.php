<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Filter users on loyalty point in range */
class UsersPointsRangeFilter extends \LWS\Adminpanel\EditList\Filter
{
	private $args = false;
	private $data = false;
	private $name = '';

	function __construct($name)
	{
		parent::__construct("lws-editlist-filter-search lws-editlist-filter-" . strtolower($name));
		$this->name = $name;

		static $once = true;
		if( $once )
			\add_filter('lws_woorewards_admin_userspoints_request', array($this, 'filter'), 10, 2);
		$once = false;
	}

	function filter($request, $list=true)
	{
		$args = $this->getArgs();
		if ($args->sysValue && (\is_numeric($args->minValue) || \is_numeric($args->maxValue))) {
			$data = $this->load();
			if (isset($data[$args->sysValue])) {
				$clause = array();

				if (\is_numeric($args->minValue))
					$clause[] = sprintf('pts_in.meta_value >= %d', (int)$args->minValue);
				if (\is_numeric($args->maxValue))
					$clause[] = sprintf('pts_in.meta_value <= %d', (int)$args->maxValue);

				if ($clause) {
					$clause = implode(' AND ', $clause);
					if ((!is_numeric($args->minValue) || $args->minValue <= 0) && (!is_numeric($args->maxValue) || 0 <= $args->maxValue)) {
						$clause .= " OR pts_in.meta_value IS NULL";
					}
					$request->where("({$clause})");

					global $wpdb;
					$request->leftJoin($wpdb->usermeta, 'pts_in', sprintf(
						'pts_in.user_id=u.ID AND pts_in.meta_key="%s"',
						\esc_sql('lws_wre_points_' . $data[$args->sysValue]->stack_id)
					));
				}
			}
		}
		return $request;
	}

	/** @return (string) alias usable to form mysql field name */
	protected function sqlAlias($key)
	{
		return str_replace('-', '$', \sanitize_key($key));
	}

	function input($above=true)
	{
		$args = $this->getArgs();
		$opts = array(array('value' => '', 'label' => __('No filter', 'woorewards-pro')));
		foreach( $this->load() as $id => $data )
			$opts[] = array('value' => $id, 'label' => $data->post_title);
		$opts = base64_encode(json_encode($opts));

		$filterlabel = __('Filter by user points', 'woorewards-pro');
		$apply = __('Apply', 'woorewards-pro');
		$ph = __('Loyalty System ...', 'woorewards-pro');

		$select = "<input name='{$args->sysKey}' class='lac_select lws-ignore-confirm' value='{$args->sysValue}' data-source='{$opts}' data-placeholder='{$ph}'>";
		$min = "<input type='text' size='3' name='{$args->minKey}' value='{$args->minValue}' placeholder='0' class='lws-input lws-input-enter-submit lws-ignore-confirm'>";
		$max = "<input type='text' size='3' name='{$args->maxKey}' value='{$args->maxValue}' class='lws-input lws-input-enter-submit lws-ignore-confirm'>";
		$retour = "<div class='lws-editlist-filter-box'><div class='lws-editlist-filter-box-title'>{$filterlabel}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$tr = __('Between %1$s and %2$s in %3$s', 'woorewards-pro');
		$retour .= sprintf("{$tr}<button class='lws-adm-btn lws-editlist-filter-btn'>{$apply}</button>", $min, $max, $select);
		$retour .= "</div></div>";
		return $retour;
	}

	private function getArgs()
	{
		if( false === $this->args )
		{
			$this->args = (object)array(
				'sysKey'   => $this->name . '_o',
				'sysValue' => '',
				'minKey'   => $this->name . '_i',
				'minValue' => '',
				'maxKey'   => $this->name . '_a',
				'maxValue' => ''
			);

			if (isset($_GET[$this->args->sysKey]) && is_numeric($sys = trim($_GET[$this->args->sysKey])))
				$this->args->sysValue = \absint($sys);
			if (isset($_GET[$this->args->minKey]) && is_numeric($min = trim($_GET[$this->args->minKey])))
				$this->args->minValue = \intval($min);
			if (isset($_GET[$this->args->maxKey]) && is_numeric($max = trim($_GET[$this->args->maxKey])))
				$this->args->maxValue = \intval($max);

			if( is_numeric($this->args->maxValue) && is_numeric($this->args->minValue) && ($this->args->maxValue < $this->args->minValue) )
			{
				$tmp = $this->args->maxValue;
				$this->args->maxValue = $this->args->minValue;
				$this->args->minValue = $tmp;
			}
		}
		return $this->args;
	}

	/** @return array({ID, post_name, post_title, stack_id}) */
	private function load()
	{
		if( false === $this->data )
		{
			$type = \LWS\WOOREWARDS\Core\Pool::POST_TYPE;
			global $wpdb;
			$sql = <<<EOT
SELECT ID, post_name, post_title, meta_value as stack_id FROM {$wpdb->posts}
LEFT JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='wre_pool_point_stack'
WHERE post_type='$type' AND post_status NOT IN ('trash') ORDER BY menu_order ASC, post_title ASC
EOT;
			$this->data = $wpdb->get_results($sql, OBJECT_K);
		}
		return $this->data;
	}
}
