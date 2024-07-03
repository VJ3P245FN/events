<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for article comment.
 * That must be the first comment of that customer on that article. */
class PostComment extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $postTypes = array();

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-pencil',
			'short' => __("The customer will earn points for posting a comment on a page or a post.", 'woorewards-pro'),
			'help'  => __("Points are only given if the comment is approved", 'woorewards-pro'),
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
		$text .= __("Points are still given once per post. The cooldown simply limits the number of times a user can earn points for his comments during a set period.", 'woorewards-pro');
		return $text;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'post_types'] = base64_encode(json_encode($this->getPostTypes()));
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'post_types' => array('t'),
			),
			'defaults' => array(
			),
			'required' => array(
				$prefix . 'post_types' => true
			),
			'labels'   => array(
				$prefix . 'post_types' => __("Post Types", 'woorewards-pro')
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setPostTypes($values['values'][$prefix . 'post_types']);
		}
		return $valid;
	}

	function getForm($context='editlist')
	{

		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Allowed Post Types", 'woorewards-pro'));

		// Post Types
		$label   = _x("Post Types", "Post Comment Event", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'post_types', array(
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
		$this->setPostTypes(\get_post_meta($post->ID, 'wre_event_comment_post_types', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_comment_post_types', $this->getPostTypes());
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
		return _x("Post a comment", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('comment_post', array($this, 'trigger'), 10, 2);
		\add_action('comment_unapproved_to_approved', array($this, 'delayedApproval'), 10, 1);
	}

	function delayedApproval($comment)
	{
		if( $this->isValid($comment) )
			$this->process($comment);
	}

	/** When a registered customer comment a product for the very first time. */
	function trigger($comment_id, $comment_approved)
	{
		if( !empty($comment = $this->getValidComment($comment_id, $comment_approved)) )
			$this->process($comment);
	}

	protected function oncekey()
	{
		return 'lws_wre_event_comment_'.$this->getId();
	}

	protected function process($comment, $force=false)
	{
		if (!$force && !$this->isCool($comment->user_id))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $comment) )
		{
			\add_user_meta($comment->user_id, $this->oncekey(), $comment->comment_post_ID, false);
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Post a comment about '%s'", \get_the_title($comment->comment_post_ID)), 'woorewards-pro');
			$this->addPoint($comment->user_id, $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Post a comment about '%s'", 'woorewards-pro');
	}

	protected function isValid($comment)
	{
		if( empty($comment->user_id) ) // not anonymous
			return false;
		if( in_array($comment->comment_post_ID, \get_user_meta($comment->user_id, $this->oncekey(), false)) ) // already commented by him
			return false;
		if( !$this->isInPostTypes(\get_post_type($comment->comment_post_ID)) ) // it is a type we looking for
			return false;
		return true;
	}

	/** @return (false|object{userId, postId} */
	protected function getValidComment($comment_id, $comment_approved)
	{
		if( !$comment_approved )
			return false;
		if( !isset($_POST['comment_post_ID']) ) // it is a comment
			return false;
		if( empty($comment = \get_comment($comment_id, OBJECT)) ) // it is a valid comment
			return false;
		if( !$this->isValid($comment) )
			return false;

		return $comment;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", 'woorewards-pro')
		));
	}
}
