<?php
namespace LWS\WOOREWARDS\PRO\Ui\ShortCodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Each clickable element is available only once per user.
 *	Trigger a hook the first time a logged in user click on it.
 *
 * Content hidden if:
 * * visitor is log off.
 * * the Events\EasterEgg cannot be found or is not active or in an active pool.
 * * No visited image and customer already got it.
 *
 * 2 images for 2 states: For seek and visited. */
class EasterEgg
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('lws_easteregg', array($me, 'shortcode'));
		\add_action('wp_ajax_lws_woorewards_ee_has', array($me, 'listener')); /// volontary obfuscated name to avoid a too easy research in source of page by customer
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}
    function registerScripts()
    {
        \wp_register_script('woorewards-easteregg',LWS_WOOREWARDS_PRO_JS.'/imgclic.js',array('jquery', 'lws-tools'),LWS_WOOREWARDS_PRO_VERSION);
	}

	function listener()
	{
		if( !(isset($_GET['ee_has']) &&  isset($_GET['p'])) ) /// args volontary obfuscated name to avoid a too easy research in source of page by customer
			return;
		if( empty($userId = \get_current_user_id()) )
			return;
		if( !\wp_verify_nonce($_GET['ee_has'], 'lws_woorewards_easteregg') )
			return;

		$p = base64_decode(str_rot13($_GET['p']));
		$startWith = 'easteregg.';
		if( substr($p, 0, strlen($startWith)) != $startWith )
			return;
		if( empty(intval($eggId = substr($p, strlen($startWith)))) )
			return;

		\do_action('lws_woorewards_easteregg', $userId, $eggId);

		if (isset($_GET['resp']) && $_GET['resp']) {
			$text = \base64_decode(\sanitize_text_field($_GET['resp']));
			if ($text) {
				$text = \apply_filters('lws_woorewards_easteregg_content_found', $text, $eggId);
				\wp_die("<div class='lws-wr-easteregg-found-textual'>{$text}</div>");
			}
		}

		global $wpdb;
		$imgId = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='wre_event_visited_egg' AND post_id=%d", $eggId));
		if( !empty($imgId) && !empty($url = \esc_attr(\wp_get_attachment_url($imgId))) )
			\wp_die("<img src='{$url}' style='display:inline;' class='lws-wr-easteregg-found-img'>");
		else
			\wp_die("<span class='lws-wr-easteregg-found'></span>");
	}

	/** @brief shortcode [lws_easteregg]
	 *	Display a clickable easter egg image. */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'text' => '',
			'alt'  => '',
			'p'    => '',
			'html' => false,
		)));
		if(empty($p = intval($atts['p'])) || $p <= 0)
			return $content;
		if( empty($userId = \get_current_user_id()) )
			return $content;
		if( !$this->isActive($p) )
			return $content;

		$visited = $this->isVisited($userId, $p);
		$metaKey = $visited ? 'wre_event_visited_egg' : 'wre_event_egg';
		global $wpdb;
		$imgId = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='{$metaKey}' AND post_id={$p}");

		if( !empty($imgId) && !empty($url = \esc_attr(\wp_get_attachment_url($imgId))) )
		{
			if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['html'])) {
				$atts['alt']  = \html_entity_decode($atts['alt']);
				$atts['text'] = \html_entity_decode($atts['text']);
			}

			if( !$visited )
			{
				$alt = '';
				if ($atts['alt'])
					$alt = sprintf(' data-resp="%s"', \esc_attr(\base64_encode($atts['alt'])));

				$this->enqueueScripts();
				$nonce = \esc_attr(\wp_create_nonce('lws_woorewards_easteregg'));
				$e = \esc_attr(str_rot13(base64_encode('easteregg.'.$p)));
				if ($atts['text']) {
					$atts['text'] = \apply_filters('lws_woorewards_easteregg_content_search', $atts['text'], $p);
					$content = "<div data-p='{$e}' data-n='{$nonce}' class='lws_wre_ee_has' style='user-select:none;cursor:pointer;'{$alt}>{$atts['text']}</div>";
				} else {
					/// class volontary obfuscated name to avoid a too easy research in source of page by customer
					$content = "<img src='{$url}' data-p='{$e}' data-n='{$nonce}' class='lws_wre_ee_has' style='display:inline;'{$alt}>";
				}
			}
			else
			{
				if ($atts['alt']) {
					$atts['alt'] = \apply_filters('lws_woorewards_easteregg_content_found', $atts['alt'], $p);
					$content = "<div class='lws-wr-easteregg-found-textual'>{$atts['alt']}</div>";
				} else {
					$content = "<img src='{$url}' style='display:inline;' class='lws-wr-easteregg-found-img'>";
				}
			}
		}
		return $content;
	}

	protected function isVisited($userId, $eggId)
	{
		$done = \get_user_meta($userId, 'lws_woorewards_easteregg', false);
		return in_array($eggId, $done);
	}

	/** Is event/pool active */
	protected function isActive($eggId)
	{
		foreach( \LWS_WooRewards_Pro::getActivePools()->asArray() as $pool )
		{
			if( $pool->findEvent(intval($eggId)) )
				return true;
		}
		foreach( \LWS_WooRewards_Pro::getLoadedAchievements()->asArray() as $pool )
		{
			if( $pool->findEvent(intval($eggId)) )
				return true;
		}
		return false;
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('jquery');
		\wp_enqueue_script('lws-tools');
		\wp_enqueue_script('woorewards-easteregg');
	}

}