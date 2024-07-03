<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points the first time a customer visit a specific webpage.
 * Webpage can be identified by:
 * * post category (taxonomy) (post_type='post')
 * * post.ID for post_type='page' (since they have no taxonomy)
 * * an exact URL full or relative (the open solution, not userfriendly but can do everthing)
 *
 * In addition, selected webpages can be hidden from:
 * * robots (as much as they respect our directives) as google search and so on
 * * wordpress search.
 */
class RestrictedVisit extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $urls           = array();
	protected $pageIds        = array();
	protected $hidePage       = false;
	protected $postCategories = array();
	private $postId           = null;

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-url',
			'short' => __("The customer will earn points for viewing a post, a page or an url on your website.", 'woorewards-pro'),
			'help'  => __("You can't use this method to give points for visiting urls outside of your website.", 'woorewards-pro'),
		));
	}

	function getDescription($context='backend')
	{
		$descr = __("Earn points for visiting some pages.", 'woorewards-pro');
		return $descr;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'post_cat'] = base64_encode(json_encode($this->getPostCategories()));
		$data[$prefix.'pages'] = base64_encode(json_encode($this->getPageIds()));
		$data[$prefix.'urls'] = base64_encode(json_encode($this->getURLs()));
		$data[$prefix.'post_hidden'] = $this->isHiddenPage() ? 'on' : '';
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Targets", 'woorewards-pro'));

		// The post category
		$label   = _x("Post Categories", "Visit Category", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'post_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'category'),
			'value' => $this->getPostCategories()
		));
		$form .= "</div>";

		// The pages
		$label   = _x("Pages", "Visit Page", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'pages', array(
			'predefined' => 'page',
			'value' => $this->getPageIds()
		));
		$form .= "</div>";

		// URL list
		$label   = _x("Relative or absolute local URLs", "Visit Page", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacTaglist::compose($prefix.'urls', array(
			'value' => $this->getURLs()
		));
		$form .= "</div>";

		// hide from search and robots on/off
		$label = __("Hide from search and robots", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'post_hidden', array(
			'id'      => $prefix . 'post_hidden',
			'layout'  => 'toggle',
			'checked' => ($this->isHiddenPage() ? ' checked' : '')
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

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
				$prefix.'post_cat' => array('D'),
				$prefix.'pages' => array('D'),
				$prefix.'urls' => array('S'),
				$prefix.'post_hidden' => 's',
			),
			'defaults' => array(
				$prefix.'post_cat' => array(),
				$prefix.'pages' => array(),
				$prefix.'urls' => array(),
				$prefix.'post_hidden' => '',
			),
			'labels'   => array(
				$prefix.'post_cat' => __("Post category", 'woorewards-pro'),
				$prefix.'pages' => __("Pages", 'woorewards-pro'),
				$prefix.'urls' => __("URLs", 'woorewards-pro'),
				$prefix.'post_hidden' => __("Hide from search and robots", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setPostCategories($values['values'][$prefix.'post_cat']);
			$this->setPageIds($values['values'][$prefix.'pages']);
			$this->setURLs($values['values'][$prefix.'urls']);
			$this->setHiddenPage($values['values'][$prefix.'post_hidden']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setPostCategories(\get_post_meta($post->ID, 'wre_event_post_cat', true));
		$this->setPageIds(\get_post_meta($post->ID, 'wre_event_pages', true));
		$this->setURLs(\get_post_meta($post->ID, 'wre_event_urls', true));
		$this->setHiddenPage(\get_post_meta($post->ID, 'wre_event_post_hidden', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_post_cat', $this->getPostCategories());
		\update_post_meta($id, 'wre_event_pages', $this->getPageIds());
		\update_post_meta($id, 'wre_event_urls', $this->getURLs());
		\update_post_meta($id, 'wre_event_post_hidden', $this->isHiddenPage() ? 'on' : '');
		return $this;
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

	/** @return array */
	function getPostCategories()
	{
		return $this->postCategories;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setPostCategories($categories=array())
	{
		if( !is_array($categories) )
			$categories = @json_decode(@base64_decode($categories));
		if( is_array($categories) )
			$this->postCategories = $categories;
		return $this;
	}

	/** @return bool */
	private function isPostInCategory($postId, $whiteList)
	{
		$taxonomy = 'category';
		$terms = get_the_terms($postId, $taxonomy);
		if( empty($terms) || \is_wp_error($terms) )
			return false;

		$terms = \wp_list_pluck($terms, 'term_id');
		foreach($terms as $cat)
			$terms = array_merge($terms, \get_ancestors($cat, $taxonomy));

		// If we find an item with a cat in our allowed cat list, the post is valid.
		return !empty(array_intersect($terms, $whiteList));
	}

	public function setHiddenPage($yes=false)
	{
		$this->hidePage = boolval($yes);
		return $this;
	}

	/** @return bool */
	function isHiddenPage()
	{
		return $this->hidePage;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Visit a page, post or URL", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( !is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			\add_filter('wp_footer', array($this, 'listener'));
			if( $this->isHiddenPage() )
				\add_action('wp_head', array($this, 'head'));
		}

		if( !is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( $this->isHiddenPage() )
				\add_filter('pre_get_posts', array($this, 'searchFilter'));
		}
	}

	function head()
	{
		if( !empty($this->isThePage()) )
			echo '<meta name="robots" content="noindex,nofollow"/>';
	}

	function searchFilter($query)
	{
		if( $query->is_search )
		{
			if( !empty($categories = $this->getPostCategories()) )
			{
				if( !empty($prev = $query->get('category__not_in')) )
					$categories = array_merge($categories, is_array($prev) ? $prev : array($prev));
				$query->set('category__not_in', $categories);
			}

			if( !empty($excluded = $this->getPageIds()) )
			{
				if( !empty($prev = $query->get('post__not_in')) )
					$excluded = array_merge($excluded, is_array($prev) ? $prev : array($prev));
				$query->set('post__not_in', $excluded);
			}
		}
		return $query;
	}

	/** @return int|bool a post id or false */
	protected function isThePage()
	{
		if( null === $this->postId )
		{
			$this->postId = false;
			if( is_single() || is_page() )
			{
				global $post;
				if( isset($post) && !empty($post) && isset($post->ID) )
				{
					// if categories, is the post IN
					if( !empty($categories = $this->getPostCategories()) )
					{
						if( $this->isPostInCategory($post->ID, $categories) )
							$this->postId = $post->ID;
					}

					// if ids, is the post IN
					if( empty($this->postId) && !empty($pages = $this->getPageIds()) )
					{
						if( in_array($post->ID, $pages) )
							$this->postId = $post->ID;
					}
				}
			}

			if( empty($this->postId) && !empty($urls = $this->getURLs()) && !empty($haystack = \LWS\Adminpanel\Tools\Conveniences::getCurrentPageUrl()) )
			{
				foreach( $urls as $needle )
				{
					$pattern = rtrim($needle, '*');
					if ($pattern != $needle) // ends with wildcard *
						$pattern = sprintf('#%s.*#', preg_quote($pattern));
					else
						$pattern = sprintf('#%s/?$#', preg_quote($pattern));

					if (preg_match($pattern, $haystack)) {
						$this->postId = $needle;
						break;
					}
				}
			}
		}
		return $this->postId;
	}

	/** is really the first time for that user on that page? */
	function listener($content)
	{
		if( !empty($userId = \get_current_user_id()) && !empty($postId = $this->isThePage()) )
		{
			$metakey = $this->getType() . '-' . $this->getId();

			$done = \get_user_meta($userId, $metakey, false);
			if( !in_array($postId, $done) && ($points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId, $postId)) )
			{
				\add_user_meta($userId, $metakey, $postId, false);
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Visit site page: %s", is_numeric($postId) ? \get_the_title($postId) : $postId), 'woorewards-pro');
				$this->addPoint($userId, $reason, $points);
			}
		}
		return $content;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Visit site page: ", 'woorewards-pro');
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