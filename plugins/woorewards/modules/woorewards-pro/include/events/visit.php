<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer comes back to the site. */
class Visit extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $urls    = array();
	protected $pageIds = array();
	protected $postId  = null;

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-b-meeting',
			'short' => __("The customer will receive points for visiting your website multiple times.", 'woorewards-pro'),
			'help'  => __("You can set up a delay between visits", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'pages'] = base64_encode(json_encode($this->getPageIds()));
		$data[$prefix.'urls'] = base64_encode(json_encode($this->getURLs()));
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Restrictions", 'woorewards-pro'));

		// The pages
		$label   = _x("Pages", "Visit Website", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'pages', array(
			'predefined' => 'page',
		));
		$form .= "</div>";

		// URL list
		$label   = _x("Relative or absolute URLs", "Visit Website", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacTaglist::compose($prefix.'urls');
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
				$prefix.'pages' => array('D'),
				$prefix.'urls' => array('S'),
			),
			'defaults' => array(
				$prefix.'pages' => array(),
				$prefix.'urls' => array(),
			),
			'labels'   => array(
				$prefix.'pages' => __("Pages", 'woorewards-pro'),
				$prefix.'urls' => __("URLs", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setPageIds($values['values'][$prefix.'pages']);
			$this->setURLs($values['values'][$prefix.'urls']);
		}
		return $valid;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setPageIds(\get_post_meta($post->ID, 'wre_event_pages', true));
		$this->setURLs(\get_post_meta($post->ID, 'wre_event_urls', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_pages', $this->getPageIds());
		\update_post_meta($id, 'wre_event_urls', $this->getURLs());
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Recurrent visit", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( !(defined('DOING_AJAX') && DOING_AJAX) )
			\add_action('wp_head', array($this, 'trigger'));
	}

	/** @return array */
	function getURLs()
	{
		return $this->urls;
	}

	/** @param $pages (array|string) as string, it should be a json base64 encoded array. */
	function setURLs($urls=array())
	{
		if( !is_array($urls) )
			$urls = @json_decode(@base64_decode($urls));
		if( is_array($urls) )
			$this->urls = $urls;
		return $this;
	}

	/** @return array */
	function getPageIds()
	{
		return $this->pageIds;
	}

	/** @param $pages (array|string) as string, it should be a json base64 encoded array. */
	function setPageIds($pages=array())
	{
		if( !is_array($pages) )
			$pages = @json_decode(@base64_decode($pages));
		if( is_array($pages) )
			$this->pageIds = $pages;
		return $this;
	}

	/** @return bool|int|string a post id, an URL or bool.
	 *	If a restriction settings exists, return false if current page does not match.
	 *	@param $defaultUnset (bool) The returned value if no settings exist;
	 *	default true means ok for any pages. */
	protected function isThePage($defaultUnset=true)
	{
		if (null !== $this->postId)
			return $this->postId;
		$this->postId = $defaultUnset;

		$pages = $this->getPageIds();
		if ($pages) {
			$this->postId = false; // option set, so we can dissmatch

			if (\is_singular() && \is_page($pages)) {
				global $post;
				if( isset($post) && $post && isset($post->ID) ) {
					$this->postId = $post->ID;
					return $this->postId;
				}
			}
		}

		$urls = $this->getURLs();
		if ($urls) {
			$this->postId = false; // option set, so we can dissmatch

			$haystack = \LWS\Adminpanel\Tools\Conveniences::getCurrentPageUrl();
			if ($haystack) {
				foreach( $urls as $needle ) {
					$pattern = rtrim($needle, '*');
					if ($pattern != $needle) // ends with wildcard *
						$pattern = sprintf('#%s.*#', preg_quote($pattern));
					else
						$pattern = sprintf('#%s/?$#', preg_quote($pattern));

					if (preg_match($pattern, $haystack)) {
						$this->postId = $needle;
						return $this->postId;
					}
				}
			}
		}

		return $this->postId;
	}

	function trigger()
	{
		if( !($userId = \get_current_user_id()) )
			return;
		$page = $this->isThePage(true);
		if (!$page)
			return;
		// check if too soon
		if (!$this->isCool($userId))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId) )
		{
			if (true === $page)
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason("Recurrent visit", 'woorewards-pro');
			elseif (\is_numeric($page))
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Recurrent visit to %s", \sanitize_text_field(\get_the_title($page))), 'woorewards-pro');
			else
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Recurrent visit to %s", \sanitize_text_field($page)), 'woorewards-pro');
			$this->addPoint($userId, $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Recurrent visit", 'woorewards-pro');
		__("Recurrent visit to %s", 'woorewards-pro');
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", 'woorewards-pro')
		));
	}
}
