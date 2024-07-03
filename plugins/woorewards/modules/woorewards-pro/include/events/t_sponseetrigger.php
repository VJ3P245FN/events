<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** As Event max trigger count, but relative to the sponsee,
 *	not the one that finaly earn points (sponsor).
 *	For example, a sponsor can earn points only 3 times for each sponsee.
 *	With the Event base behavior, you can only setup a unique global number,
 *	for a sponsor, whatever the sponsee.
 */
trait T_SponseeTrigger
{
	public $maxSponseeTriggers = null;

	public function getMaxSponseeTriggers()
	{
		if (null !== $this->maxSponseeTriggers && $this->maxSponseeTriggers) {
			return $this->maxSponseeTriggers;
		} else {
			return false;
		}
	}

	public function setMaxSponseeTriggers($maxTriggers)
	{
		$this->maxSponseeTriggers = \intval($maxTriggers);
		if (!$this->maxSponseeTriggers)
			$this->maxSponseeTriggers = false;
	}

	public function incrSponseeTriggerCount($userId)
	{
		if ($userId && $this->getMaxSponseeTriggers() && ($id = $this->getId())) {
			$key = ('lws_wr_sponsee_triggered_' . $id);
			$count = \intval(\get_user_meta($userId, $key, true));
			\update_user_meta($userId, $key, 1 + $count);
		}
	}

	public function getSponseeTriggerCount($userId)
	{
		if (!($userId && $this->getMaxSponseeTriggers()))
			return 0;
		else
			return \intval(\get_user_meta($userId, 'lws_wr_sponsee_triggered_' . $this->getId(), true));
	}

	/** if feature not enabled or used, always return true.
	 *	else, a user is required to trigger the event
	 *	so we can check the counter.
	 *	@param $doIncrement (bool) if true and can be triggered, then increment the counter.
	 *	@return (bool) true if no restriction set or event trigger count is not reached. */
	public function canBeTriggeredBySponsee($sponseeId, $doIncrement=false)
	{
		$max = $this->getMaxSponseeTriggers();
		if (!$max) {
			return true;
		}
		if (!$sponseeId) {
			return false;
		}
		if ($this->getSponseeTriggerCount($sponseeId) < $max) {
			if ($doIncrement)
				$this->incrSponseeTriggerCount($sponseeId);
			return true;
		} else {
			return false;
		}
	}

	/** override Event::canBeTriggered to include sponsee test.
	 *	@param $options (array|\WP_User|int) the user earning points,
	 *		an array have to include an entry 'user' and 'sponsee'. */
	public function canBeTriggered($options, $doIncrement=false)
	{
		$sponseeId = false;
		if (\is_array($options) && isset($options['sponsee']))
			$sponseeId = $options['sponsee'];
		if ($sponseeId && \is_object($sponseeId))
			$sponseeId = $sponseeId->ID;

		if ($this->canBeTriggeredBySponsee($sponseeId, false)) {
			if (parent::canBeTriggered($options, $doIncrement)) {
				if ($doIncrement)
					$this->incrSponseeTriggerCount($sponseeId);
				return true;
			}
		}
		return false;
	}

	protected function filterSponseeTriggerData($data=array(), $prefix='')
	{
		$data[$prefix . 'sponsee_max_triggers'] = $this->getMaxSponseeTriggers();
		if (!$data[$prefix . 'sponsee_max_triggers'])
			$data[$prefix . 'sponsee_max_triggers'] = '';
		return $data;
	}

	protected function getSponseeTriggerForm($prefix='', $context='editlist')
	{
		// Max Sponsee Triggers
		$label = _x("Max Triggers per Sponsee", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("Defines how many times each sponsee can give points to his referrer by performing this action", 'woorewards-pro');
		return <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>
	<input type='text' size='5' id='{$prefix}sponsee_max_triggers' name='{$prefix}sponsee_max_triggers' placeholder='' />
</div>
EOT;
	}

	/** @return bool */
	protected function optSponseeTriggerSubmit($prefix='', $form=array(), $source='editlist')
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'sponsee_max_triggers' => '0',
			),
			'defaults' => array(
				$prefix . 'sponsee_max_triggers' => 0,
			),
			'labels'   => array(
				$prefix . 'sponsee_max_triggers' => __("Max Triggers per Sponsee", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setMaxSponseeTriggers($values['values'][$prefix . 'sponsee_max_triggers']);
		return true;
	}

	protected function optSponseeTriggerFromPost(\WP_Post $post)
	{
		$this->setMaxSponseeTriggers(\get_post_meta($post->ID, 'wre_sponsee_max_triggers', true));
		return $this;
	}

	protected function optSponseeTriggerSave($id)
	{
		\update_post_meta($id, 'wre_sponsee_max_triggers', $this->getMaxSponseeTriggers());
		return $this;
	}
}