<?php
namespace LWS\WOOREWARDS\PRO\Ui\Fields;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Define a field composed by several inputs:
 * A Date input
 * A \LWS\Adminpanel\Pages\Field\Duration input */
class PeriodicTrigger extends \LWS\Adminpanel\Pages\Field
{
	public static function install()
	{
		\add_filter('lws_adminpanel_field_types', function($types){
			$types['woorewards_periodic_trigger'] = array('\LWS\WOOREWARDS\PRO\Ui\Fields\PeriodicTrigger', __FILE__);
			return $types;
		});
	}

	/** @return string field html. */
	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		\wp_enqueue_script('lws-wooreward-periodictrigger', LWS_WOOREWARDS_PRO_JS.'/periodictrigger.js', array('jquery', 'lws-adm-duration'), LWS_WOOREWARDS_PRO_VERSION, true);

		$value = $this->readOption(false);
		$value = \LWS\WOOREWARDS\PRO\Core\Pool::transactionalExpiryFromValue($value);

		$hidden = $value['period'] && $value['period']->isNull() ? ' style="display:none"' : '';
		$dVal = $value['date'] ? $value['date']->format('Y-m-d') : '';
		$date = "<input name='{$this->m_Id}[date]' class='period-start' value='$dVal' type='date'{$hidden} />";

		$period = \LWS\Adminpanel\Pages\Field\Duration::compose(
			$this->m_Id.'[period]',
			array(
				'value' => $value['period']->toString(),
				'default' => 'P1Y',
			)
		);

		return \preg_replace('@(</(?:span|div)>)$@i', $date.'$1', \trim($period));
	}
}
