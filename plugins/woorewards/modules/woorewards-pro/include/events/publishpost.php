<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for a published post */
class PublishPost extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $postTypes = array();

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-pencil',
			'short' => __("The customer will earn points for writing a post on your website.", 'woorewards-pro'),
			'help'  => __("You can also give points for publishing custom post types", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	/** If additionnal info should be displayed in settings form. */
	protected function getCooldownTooltips($text)
	{
		$text .= '<br/>';
		$text .= __("Points are still given once per post. The cooldown simply limits the number of times a user can earn points for his publishings during a set period.", 'woorewards-pro');
		return $text;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'post_types'] = base64_encode(json_encode($this->getPostTypes()));
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'post_types' => array('t'),
			),
			'defaults' => array(
			),
			'required' => array(
				$prefix.'post_types' => true
			),
			'labels'   => array(
				$prefix.'post_types' => __("Post Types", 'woorewards-pro')
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setPostTypes	($values['values'][$prefix.'post_types']);
		}
		return $valid;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Allowed Post Types", 'woorewards-pro'));

		// Post Types
		$label   = _x("Post Types", "Publish Post Event", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'post_types', array(
			'ajax'  => 'lws_adminpanel_get_post_types',
			'class' => 'above',
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setPostTypes(\get_post_meta($post->ID, 'wre_event_post_types', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_post_types', $this->getPostTypes());
		return $this;
	}

	/** @return array */
	public function getPostTypes()
	{
		return \is_array($this->postTypes) ? \array_filter($this->postTypes) : array();
	}

	public function setPostTypes($types=array())
	{
		$this->postTypes = is_array($types) ? $types : array($types);
		return $this;
	}

	/** @return bool */
	public function isInPostTypes($type)
	{
		return in_array($type, $this->getPostTypes());
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Publish a Post", "getDisplayType", 'woorewards-pro');
	}

	function getDescription($context='backend')
	{
		$types = $this->getPostTypes();
		$names = $this->getPostTypesNames($types, $context, _x(", ", "post type separator", 'woorewards-pro'));
		return sprintf(_n("User published a post of the following type : %s", "User published a post of the following types : %s", count($types), 'woorewards-pro'), $names);
	}

	protected function getPostTypesNames($types, $context, $sep=', ')
	{
		$names = array();
		foreach($types as $type)
		{
			$names[] = get_post_type_object($type)->label;
		}
		return implode($sep, $names);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('transition_post_status',  array($this, 'changePostStatus'), 10, 3 );
	}

	function changePostStatus($new_status, $old_status, $post)
	{
		if( $this->isValid($post) && $new_status == 'publish' )
		{
			$this->process($post);
		}
	}

	protected function oncekey()
	{
		return 'lws_wre_event_post_'.$this->getId();
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Publish a post %s of type : '%s'", 'woorewards-pro');
	}

	protected function process($post, $force=false)
	{
		if (!$force && !$this->isCool($post->post_author))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $post) )
		{
			\add_user_meta($post->post_author, $this->oncekey(), $post->ID, false);
			$reason = \LWS\WOOREWARDS\Core\Trace::byOrder($post->ID);
			if( $ptype = \get_post_type_object($post->post_type) )
				$ptype = $ptype->label;
			else
				$ptype = '[unknown]';
			$reason->setReason(array("Publish a post %s of type : '%s'", $post->ID, $ptype), 'woorewards-pro');
			$this->addPoint($post->post_author, $reason, $points);
		}
	}

	protected function isValid($post)
	{
		if( empty($post->post_author) ) // not anonymous
		{
			return false;
		}
		if( in_array($post->ID, \get_user_meta($post->post_author, $this->oncekey(), false)) ) // already published by him
		{
			return false;
		}
		if( !$this->isInPostTypes($post->post_type)) // it is not a type we're looking for
		{
			return false;
		}
		return true;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", 'woorewards-pro'),
		));
	}
}