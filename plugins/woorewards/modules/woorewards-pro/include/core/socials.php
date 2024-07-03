<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Keep list of supported social networks.
 * Manage names, icons, url sharing format. */
class Socials
{
	public $socials = array();

	static function instance()
	{
		static $_instance = false;
		if( !$_instance )
			$_instance = \apply_filters('lws_woorewards_socials_instance', new self());
		return $_instance;
	}

	function __construct()
	{
		$this->socials = array(
			'facebook'  => (object)array(
				'label'   => __("Facebook", 'woorewards-pro'),
				'icon'    => 'lws-icon-facebook2',
				'sharing' => array('url', 'https://www.facebook.com/share.php', 'u')
			),
			'twitter'   => (object)array(
				'label'   => __("Twitter", 'woorewards-pro'),
				'icon'    => 'lws-icon-twitter1',
				'sharing' => array('url', 'https://twitter.com/intent/tweet', 'url')
			),
			'pinterest' => (object)array(
				'label'   => __("Pinterest", 'woorewards-pro'),
				'icon'    => 'lws-icon-pinterest1',
				'sharing' => array('url', 'https://pinterest.com/pin/create/button', 'url')
			),
			'linkedin'  => (object)array(
				'label'   => __("Linkedin", 'woorewards-pro'),
				'icon'    => 'lws-icon-linkedin1',
				'sharing' => array('url', 'https://www.linkedin.com/cws/share', 'url')
			),
			'whatsapp'  => (object)array(
				'label'   => __("WhatsApp", 'woorewards-pro'),
				'icon'    => 'lws-icon-whatsapp',
				'sharing' => array('url', 'https://api.whatsapp.com/send', 'text')
			),
			'mewe'  => (object)array(
				'label'   => __("MeWe", 'woorewards-pro'),
				'icon'    => 'lws-icon-lw_mewe',
				'sharing' => array('url', 'https://www.mewe.com/share', 'link')
			),
		);
	}

	/** Add a network to the list.
	 * @param $slug (string) a key to identify the network.
	 * @param $label (string) human readable name.
	 * @param $iconCssClass (string) a css class that show the network icon
	 * 	Hook the use action 'lws_woorewards_socials_scripts' to enqueue specific css file if needed.
	 * @param $sharing (array) how to form the sharing link.
	 * first value is the method: url or callable:
	 * * url: second value is the network url and third is the argument that must be appended for the url to share.
	 * * callable: a callable function that take the url to share as argument and return the well formated sharing url.
	 * @return $this */
	function add($slug, $label, $iconCssClass, $sharing)
	{
		$this->socials[$slug] = (object)array(
			'label'   => $label,
			'icon'    => $iconCssClass,
			'sharing' => $sharing
		);
		return $this;
	}

	function asDataSource()
	{
		$src = array();
		foreach( $this->socials as $value => $social )
			$src[] = array('value' => $value, 'label' => $social->label);
		return $src;
	}

	function isSupportedNetwork($social)
	{
		return isset($this->socials[$social]);
	}

	function getSupportedNetworks()
	{
		return array_keys($this->socials);
	}

	/** @return string the label for the given key.
	 * If key is an array, return an array of label.
	 * If glue is a string, a string is always returned, several label joint with glue. */
	function getLabel($slug, $glue=false)
	{
		$labels = $slug;
		if( is_array($slug) )
		{
			$labels = array();
			foreach( $slug as $v )
			{
				if( isset($this->socials[$v]) )
					$labels[$v] = $this->socials[$v]->label;
			}
			if( $glue )
				$labels = implode($glue, $labels);
		}
		else if( isset($this->socials[$slug]) )
			$labels = $this->socials[$slug]->label;

		return $labels;
	}

	/** @return string the icon css class for the given key.
	 * If key is an array, return an array of icon css class.
	 * To enqueue a custom css file (and return a new class here),
	 * use the action hook 'lws_woorewards_socials_scripts' */
	function getIcon($slug)
	{
		$icons = $slug;
		if( is_array($slug) )
		{
			$icons = array();
			foreach( $slug as $v )
			{
				if( isset($this->socials[$v]) )
					$icons[$v] = $this->socials[$v]->icon;
			}
		}
		else if( isset($this->socials[$slug]) )
			$icons = $this->socials[$slug]->icon;

		return $icons;
	}

	/** @param $slug (string) the key of the social network @see getSupportedNetworks
	 * @param $pageUrl (string) the url to share on the social network.
	 * @return '#' on error. */
	function getShareLink($slug, $pageUrl)
	{
		$sharing = '#';
		if( isset($this->socials[$slug]) )
		{
			$pageUrl = urlencode($pageUrl);

			if( $this->socials[$slug]->sharing[0] == 'url' )
				$sharing = \add_query_arg($this->socials[$slug]->sharing[2], $pageUrl, $this->socials[$slug]->sharing[1]);
			else if( $this->socials[$slug]->sharing[0] == 'callable' )
				$sharing = call_user_func($this->socials[$slug]->sharing[1], $pageUrl);
		}
		return $sharing;
	}

	/** Provided for convenience.
	 * @return (string) the current page url.
	 * @param $args (array of key(string) => value(string)) arguments that will be append to url before it is returned. */
	function getCurrentPageUrl($args=array())
	{
		$protocol = 'http://';
		if( (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') )
			$protocol = 'https://';

		$url = ($protocol . $_SERVER['HTTP_HOST'] . \add_query_arg($args, false));
		return $url;
	}

	/** Identify the current page (id stored).
	 * Never compute hash for admin page, return empty string.
	 * @return string */
	function getCurrentPageHash()
	{
		$hash = '';
		if( !\is_admin() )
		{
			if( \is_singular() && ($post = \get_post()) )
			{
				if( empty($hash = \get_post_meta($post->ID, 'lws_woorewards_social_hash', true)) )
				{
					$hash = \sanitize_key(\wp_hash('wr_social'.json_encode($post).rand()));
					\update_post_meta($post->ID, 'lws_woorewards_social_hash', $hash);
				}
			}
			else
			{
				global $wp_query;
				$id = (\get_site_url() . '|');
				$id .= json_encode(array_intersect_key(
					is_array($wp_query->query) ? $wp_query->query : array(),
					array(
//						's' => true, // search field
						'post_type' => true,
//						'category_name' => true,
//						'tag' => true,
					)
				));

				$hashes = \get_option('lws_woorewards_plural_hashes', array());
				if( !is_array($hashes) )
					$hashes = array();

				$hash = \sanitize_key(\wp_hash($id));
				if( !isset($hashes[$hash]) )
				{
					$hashes[$hash] = true;
					\update_option('lws_woorewards_plural_hashes', $hashes);
				}
			}
		}
		return $hash;
	}

	function getCustomPageHash($url)
	{
		$hash = \sanitize_key('v'.\wp_hash($url));
		$hashes = \get_option('lws_woorewards_plural_hashes', array());
		if( !is_array($hashes) )
			$hashes = array();
		if( !isset($hashes[$hash]) )
		{
			$hashes[$hash] = true;
			\update_option('lws_woorewards_plural_hashes', $hashes);
		}
		return $hash;
	}

	/** Is the hash match a real page
	 * @param $hash (string) @see getCurrentPageHash()
	 * @return bool */
	function isValidPageHash($hash)
	{
		if( empty($hash) )
			return false;

		global $wpdb;
		$c = $wpdb->get_var($wpdb->prepare("SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key='lws_woorewards_social_hash' AND meta_value=%s", $hash));
		if( $c )
			return true;

		$hashes = \get_option('lws_woorewards_plural_hashes', array());
		if( is_array($hashes) && isset($hashes[$hash]) )
			return true;

		return false;
	}

	/** check points not already earned for that page and that event.
	 * @param $metaKey (string) user_meta.meta_key to store data.
	 * @param $userId (int)
	 * @param $pageHash (string) @see getCurrentPageHash()
	 * @param $check (bool) if true, the page is marked as earned for that event.
	 * @return true if hash is valid and page never been checked */
	function isPageUntouched($metaKey, $userId, $pageHash, $check=true)
	{
		if( !$this->isValidPageHash($pageHash) )
			return false;

		$marks = \get_user_meta($userId, $metaKey, true);
		if( empty($marks) )
			$marks = array();

		$first = !isset($marks[$pageHash]);
		if( $first && $check )
		{
			$marks[$pageHash] = \date_create()->format('Y-m-d');
			\update_user_meta($userId, $metaKey, $marks);
		}
		return $first;
	}

	static function getRemoteIP()
	{
		if (!isset($_SERVER))
			return false;
		elseif (isset($_SERVER['HTTP_CLIENT_IP']))
			return $_SERVER['HTTP_CLIENT_IP']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			return $_SERVER['HTTP_X_FORWARDED_FOR']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		elseif(isset($_SERVER['HTTP_X_FORWARDED']))
			return $_SERVER['HTTP_X_FORWARDED']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		elseif(isset($_SERVER['HTTP_FORWARDED_FOR']))
			return $_SERVER['HTTP_FORWARDED_FOR']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		elseif(isset($_SERVER['HTTP_FORWARDED']))
			return $_SERVER['HTTP_FORWARDED']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		elseif(isset($_SERVER['REMOTE_ADDR']))
			return $_SERVER['REMOTE_ADDR']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		else
			return false;
	}

	/**	Try to represent visitor with a unique sequence.
	 *	Return all possible representation.
	 *	@return false|array of strings */
	function getVisitorFingerprints()
	{
		$visitors = array();
		// if already visited
		$remote = isset($_COOKIE['lws_wr_svisitor']) ? \sanitize_key($_COOKIE['lws_wr_svisitor']) : false;
		if ($remote)
			$visitors['remote'] = $remote;
		// if logged in
		$user = \get_current_user_id();
		if ($user)
			$visitors['user'] = ('user_' . $user);
		// ensure a local one
		$ip = self::getRemoteIP();
		if ($ip) {
			static $salt = false;
			if (false === $salt) {
				if (\defined('LWS_SALT'))
					$salt = LWS_SALT;
				if (!$salt)
					$salt = \get_option('lws_salt');
				if (!$salt) {
					$salt = 'lws' . \time();
					\update_option('lws_salt', $salt);
				}
			}
			$visitors['ip'] = \substr(\hash('sha256', $salt.$ip), 0, 64); // hash ip to be rgpd compliant
		}

		if (!$visitors)
			return false;
		// save it for later
		\setcookie('lws_wr_svisitor', \reset($visitors), 5 * YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
		return \array_unique($visitors);
	}

	function isLinkUsedBy($pageHash, $visitors, $ref='')
	{
		if (!$visitors)
			return true;
		if (!is_array($visitors))
			$visitors = array($visitors);
		$user = isset($visitors['user']) ? \intval(\substr($visitors['user'], 5)) : 0;
		$remote = sprintf('remote_user_id IN ("%s")', implode('","', \esc_sql($visitors)));

		global $wpdb;
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWebhooksEvents, 'w');
		$query->where(array(
			sprintf('network="%s"', \esc_sql($ref)),
			'event="visit"',
			sprintf('origin="%s"', \esc_sql($pageHash)),
		));

		if ($user) {
			$query->where(array(
				'condition' => 'OR',
				sprintf('user_id=%d', $user),
				$remote,
			));
		} else {
			$query->where($remote);
		}

		$query->select('COUNT(*)');
		return $query->getVar();
	}

	function setLinkUsed($pageHash, $visitors, $ref='')
	{
		if (!is_array($visitors))
			$visitors = array($visitors);
		$user = isset($visitors['user']) ? \intval(\substr($visitors['user'], 5)) : 0;

		global $wpdb;
		$base = $wpdb->prepare('%d, %s, "visit", %s', $user, $ref, $pageHash);
		$values = array();
		foreach ($visitors as $visitor) {
			$values[] = sprintf('(%s, "%s")', $base, \esc_sql($visitor));
		}

		$sql = "INSERT INTO {$wpdb->lwsWebhooksEvents} (user_id, network, event, origin, remote_user_id) VALUES ";
		$sql .= implode(',', $values);
		$wpdb->query($sql);
	}
}
