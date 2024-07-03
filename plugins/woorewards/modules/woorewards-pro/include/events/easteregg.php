<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points the first time a customer clic on easter egg image hidden somewhere in frontend.
 * Clickable element are dedicated shortcode managed in Ui namespace.
 * Each clickable element is available only once per user, hidden if visitor is log off.
 *
 * 2 images for 2 states: For seek and visited.
 * Provide a shortcode in exchange that can be hidden by a site's author. */
class EasterEgg extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $image = 0;
	protected $visitedImage = 0;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-image',
			'short' => __("The customer will earn points when he clicks on a specific image on your website.", 'woorewards-pro'),
			'help'  => __("You can use this method to set up an image hunt on your website.", 'woorewards-pro'),
		));
	}

	function getDescription($context='backend')
	{
		$img = !empty($this->getImage()) ? $this->getImageHtml(false) : '';
		if( empty($img) )
			$img = "<span class='lws-icon lws-icon-image' style='font-size: 2em;'></span>";

		if( $context == 'backend' )
		{
			$descr = "<span>". _x("Customers will seek for this image : ", "EasterEgg image backend text", 'woorewards-pro') . '&nbsp;';
			$descr .= $img;
			$descr .= "</span>";

			if( !empty($this->getVisitedImage()) )
			{
				$descr .= "<br/><span>". _x("If clicked, the image will turn into :", "EasterEgg image after found", 'woorewards-pro')
					. ' ' . $this->getImageHtml(true) . "</span>";
			}

			$shortcode = sprintf(
				"%s %s",
				__("Copy this shortcode somewhere on your site", 'woorewards-pro'),
				\apply_filters('lws_format_copypast', sprintf('[lws_easteregg p=%d]', $this->getId()))
			);

			return "<div class='lws-easteregg-descr'>$descr</div><div class='lws-easteregg-shortcode'>$shortcode</div>";
		}
		else
		{
			$descr = "<span class='lws-wr-easteregg-clue'>". _x("Seek for", "EasterEgg image frontend text", 'woorewards-pro') . "</span> ";
			$descr .= $img;
			return $descr;
		}
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'egg']     = $this->getImage();
		$data[$prefix.'egg_url'] = $this->getImageUrl(false);
		$data[$prefix.'visited_egg']     = $this->getVisitedImage();
		$data[$prefix.'visited_egg_url'] = $this->getImageUrl(true);
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = parent::getForm($context);
		$str .= $this->getFieldsetBegin(2, __("Images", 'woorewards-pro'), 'col50');

		// add for seek image here
		$label = _x("Seek", "Frontend easter egg image", 'woorewards-pro');
		$help =  __("Set images on your website that your users can click on to earn points.", 'woorewards-pro') . "<br/>";
		$help .= __("The first image is the one they have to click on.", 'woorewards-pro') . "<br/>";
		$help .= __("The second image (optional) will be displayed after the users clicks on the first one.", 'woorewards-pro') . "<br/>";
		$str .= "<div class='field-help displayed'>$help</div>";

		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= \LWS\Adminpanel\Pages\Field\Media::compose($prefix.'egg', array(
			'value'        => $this->getImage(),
			'type'         => 'image',
			'size'         => 'thumbnail',
			'classSize'    => 'lws_wr_thumbnail',
			'urlInputName' => $prefix.'egg_url'
		));

		// add visited image here
		$label = _x("Found", "Frontend easter egg image", 'woorewards-pro');
		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= \LWS\Adminpanel\Pages\Field\Media::compose($prefix.'visited_egg', array(
			'value'        => $this->getVisitedImage(),
			'type'         => 'image',
			'size'         => 'thumbnail',
			'classSize'    => 'lws_wr_thumbnail',
			'urlInputName' => $prefix.'visited_egg_url'
		));

		$str .= $this->getFieldsetEnd(2);
		return $str;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'egg'         => 'd',
				$prefix.'visited_egg' => 'd'
			),
			'defaults' => array(
				$prefix.'egg'         => '0',
				$prefix.'visited_egg' => '0'
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setImage       ($values['values'][$prefix.'egg']);
			$this->setVisitedImage($values['values'][$prefix.'visited_egg']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setImage       (intval(\get_post_meta($post->ID, 'wre_event_egg',         true)));
		$this->setVisitedImage(intval(\get_post_meta($post->ID, 'wre_event_visited_egg', true)));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_egg', $this->getImage());
		\update_post_meta($id, 'wre_event_visited_egg', $this->getVisitedImage());
		return $this;
	}

	/** @return int id */
	public function getImage()
	{
		$id = $this->image;
		if( $id && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$id = \apply_filters('wpml_object_id', $id, 'attachment', true);
		return $id;
	}

	/** @return int id */
	public function getVisitedImage()
	{
		$id = $this->visitedImage;
		if( $id && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$id = \apply_filters('wpml_object_id', $id, 'attachment', true);
		return $id;
	}

	public function setImage($id)
	{
		$this->image = $id;
		return $this;
	}

	public function setVisitedImage($id)
	{
		$this->visitedImage = $id;
		return $this;
	}

	/** @return string url */
	public function getImageUrl($visited=false)
	{
		if( empty($img = ($visited ? $this->getVisitedImage() : $this->getImage())) )
			return '';
		else
			return \wp_get_attachment_url($img);
	}

	/** @return string html <img> */
	public function getImageHtml($visited=false, $size='lws_wr_thumbnail')
	{
		if( empty($img = ($visited ? $this->getVisitedImage() : $this->getImage())) )
			return '';
		else
			return \wp_get_attachment_image($img, $size, false, array('class'=>'lws-wr-easteregg lws-wr-event-easteregg'));
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Click an Image", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_easteregg', array($this, 'listener'), 10, 2);
	}

	/** is really the first time for that user on that easter egg? */
	function listener($userId, $eggId)
	{
		if( $eggId != $this->getId() )
			return;

		$done = \get_user_meta($userId, 'lws_woorewards_easteregg', false);
		if( in_array($eggId, $done) )
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId, $eggId) )
		{
			\add_user_meta($userId, 'lws_woorewards_easteregg', $eggId, false);
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason("Image found", 'woorewards-pro');
			$this->addPoint($userId, $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Image found", 'woorewards-pro');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", 'woorewards-pro'),
			'playful' => __("Fun activities", 'woorewards-pro')
		));
	}
}