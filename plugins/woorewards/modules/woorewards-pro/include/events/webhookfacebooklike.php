<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for sharing on social networks.
 * Use our local sharing widget/shortcode */
class WebHookFacebookLike extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $reactionTypes = array();

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-facebook2',
			'short' => __("The customer will earn points every time he <i>likes</i> a post on your Facebook page.", 'woorewards-pro'),
			'help'  => __("The customer has to link his Facebook account to your site to be able to earn points", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'reaction_type'] = base64_encode(json_encode($this->getReactionTypes()));
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Constraints", 'woorewards-pro'));

		// The social networks reaction types
		$label   = _x("Reaction types", "Social network like, care, grrr...", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'reaction_type', array(
			'source' => $this->getReactionTypesSource(),
			'class'  => 'above',
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'reaction_type' => array('K'),
			),
			'defaults' => array(
				$prefix.'reaction_type' => array(),
			),
			'labels'   => array(
				$prefix.'reaction_type' => __("Reaction types", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
			list($verif, $confirmed) = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getVerifiedStatus('facebook');
			if (!$confirmed) {
				return __("You must finalize your Facebook settings and verify them before saving such an earning method", 'woorewards-pro');
			}
			$this->setReactionTypes($values['values'][$prefix.'reaction_type']);
		}
		return $valid;
	}

	public function getReactionTypesSource()
	{
		$img = LWS_WOOREWARDS_PRO_IMG . '/facebook/reaction-';
		return array(
			'like'  => array('value' => 'like' , 'label' => _x("Like" , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}like.png' title='like'/>"),
			'care'  => array('value' => 'care' , 'label' => _x("Care" , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}care.png' title='care'/>"),
			'haha'  => array('value' => 'haha' , 'label' => _x("Haha" , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}haha.png' title='haha'/>"),
			'love'  => array('value' => 'love' , 'label' => _x("Love" , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}love.png' title='love'/>"),
			'wow'   => array('value' => 'wow'  , 'label' => _x("Wow"  , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}wow.png' title='wow'/>"),
			'sad'   => array('value' => 'sad'  , 'label' => _x("Sad"  , 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}sad.png' title='sad'/>"),
			'angry' => array('value' => 'angry', 'label' => _x("Angry", 'Facebook reaction', 'woorewards-pro'), 'html' => "<img class='lws-fb-react' src='{$img}angry.png' title='angry'/>"),
		);
	}

	/** @return array */
	public function getReactionTypes()
	{
		return $this->reactionTypes;
	}

	public function setReactionTypes($reactionTypes)
	{
		if( !is_array($reactionTypes) )
			$reactionTypes = @json_decode(@base64_decode($reactionTypes));
		if( is_array($reactionTypes) )
			$this->reactionTypes = $reactionTypes;
		return $this;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setReactionTypes(\get_post_meta($post->ID, 'woorewards_reaction_type', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_reaction_type', $this->getReactionTypes());
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("React to your Facebook posts", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_wh_facebook_event_reaction', array($this, 'trigger'), 10, 3);
	}

	/** @param $userId user that should earn points.
	 *	@param $value see facebook documentation
	 *	@param $dbId the wp_lws_webhooks_events table id */
	function trigger($userId, $value, $dbId)
	{
		if (!$userId)
			return;

		// direct post like only (not a comment)
		if (isset($value['comment_id']))
			return;
		// first level action: parent is this
		if (!(isset($value['post_id']) && isset($value['parent_id'])) || $value['parent_id'] != $value['post_id'])
			return;

		$types = $this->getReactionTypes();
		if ($types) {
			$type = isset($value['reaction_type']) ? \strtolower($value['reaction_type']) : '';
			if (!in_array($type, $types))
				return;
		}
		if (!$this->isCool($userId))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId, $value, $dbId) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason("React to a Facebook post", 'woorewards-pro');
			$this->addPoint($userId, $reason, $points, $dbId);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("React to a Facebook post", 'woorewards-pro');
	}

	function getDescription($context='backend')
	{
		$types = $this->getReactionTypes();
		if (!$types) {
			return __("A user reacts to a post on your Facebook page.", 'woorewards-pro');
		} else {
			$source = $this->getReactionTypesSource();
			foreach($types as &$type) {
				if (isset($source[$type]))
					$type = $source[$type]['html'];
			}
			return implode(' ', $types);
		}
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'social' => __("Social network", 'woorewards-pro'),
			'facebook' => 'Facebook',
		));
	}
}