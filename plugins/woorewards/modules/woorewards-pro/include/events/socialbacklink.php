<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for sharing on social networks.
 * Use our local sharing widget/shortcode */
class SocialBacklink extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $socials = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-a-share',
			'short' => __("The customer will earn points every time a new visitor visits a link he shared on social media.", 'woorewards-pro'),
			'help'  => __("This method will only reward the sharer, not the person who visited the link", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'socials'] = base64_encode(json_encode($this->getSocials()));
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Constraints", 'woorewards-pro'));

		// The social networks
		$label   = _x("Social network", "Social network sharing Event", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'socials', array(
			'comprehensive' => true,
			'source' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource(),
			'value' => $this->getSocials(),
			'class' => 'above',
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
				$prefix.'socials' => array('S'),
			),
			'defaults' => array(
				$prefix.'socials' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks()
			),
			'labels'   => array(
				$prefix.'socials' => __("Social networks", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setSocials($values['values'][$prefix.'socials']);
		}
		return $valid;
	}

	/** @return array */
	public function getSocials()
	{
		return (false !== $this->socials) ? $this->socials : \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks();
	}

	public function setSocials($socials)
	{
		if( !is_array($socials) )
			$socials = @json_decode(@base64_decode($socials));
		if( is_array($socials) )
			$this->socials = $socials;
		return $this;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setSocials(\get_post_meta($post->ID, 'woorewards_socials', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_socials', $this->getSocials());
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Visitor clicks a social share", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_social_backlink', array($this, 'trigger'), 10, 3);
	}

	/** @param $userId is the one who share the page and never the currently connected. */
	function trigger($userId, $social, $pageHash)
	{
		if( $userId && $social
		&& ($userId != \get_current_user_id())
		&& in_array($social, $this->getSocials()) )
		{
			$validator = \LWS\WOOREWARDS\PRO\Core\Socials::instance();
			if (!$validator->isValidPageHash($pageHash))
				return;

			$visitors = $validator->getVisitorFingerprints();
			if (!$visitors)
				return;
			if ($validator->isLinkUsedBy($pageHash, $visitors, $this->getId()))
				return;
			if (!$this->isCool($userId))
				return;

			if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId, $social, $pageHash) )
			{
				$validator->setLinkUsed($pageHash, $visitors, $this->getId());

				$name = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getLabel($social);
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("A friend came from '%s'", $name), 'woorewards-pro');
				$this->addPoint($userId, $reason, $points, \get_current_user_id());
			}
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("A friend came from '%s'", 'woorewards-pro');
	}

	function getDescription($context='backend')
	{
		$names = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getLabel($this->getSocials(), ', ');
		$str = sprintf(__("A visitor follows your share on %s", 'woorewards-pro'), $names);
		return $str;
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'social' => __("Social network", 'woorewards-pro')
		));
	}
}