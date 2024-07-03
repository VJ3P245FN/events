<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Expect user follow a unlock link to generate a reward.
 * This class is able to generate url argument to create a reward redeem lin.
 * and answer that kind of link.
 * Then, found back unlockable generate the reward, then provide a simple feedback to user.
 * Should produce redirection. */
class RewardClaim
{
	/** add arguments to url to redeem an unlockable and generate the reward.
	 * The Unlockable must belong to a pool. */
	static public function addUrlUnlockArgs($url, $unlockable, $user, $apply = false)
	{
		if( empty($pool = $unlockable->getPool()) || empty($pool->getId()) )
			return $url;

		static $lastUserKey = '';
		static $lastUser = null;
		if( $lastUser != $user || empty($lastUserKey) )
		{
			$lastUser = $user;
			$lastUserKey = \get_user_meta($user->ID, 'lws_woorewards_user_key', true);
			if( empty($lastUserKey) )
			{
				\update_user_meta($user->ID, 'lws_woorewards_user_key', $lastUserKey = \sanitize_key(\wp_hash(implode('*', array(
					$user->ID,
					$user->user_email,
					rand()
				)))));
			}
		}

		static $lastPoolKey = '';
		static $lastPool = null;
		if( empty($lastPool) || $lastPool->getId() != $pool->getId() || empty($lastPoolKey) )
		{
			$lastPool = &$pool;
			$lastPoolKey = \get_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', true);
			if( empty($lastPoolKey) )
			{
				\update_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', $lastPoolKey = \sanitize_key(\wp_hash(implode('*', array(
					$pool->getId(),
					$pool->getStackId(),
					rand()
				)))));
			}
		}

		$key = \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			$unlockable->getType(),
			$user->ID,
			$user->user_email
		))));

		$args = array(
			'lwsrewardclaim' => $lastUserKey,
			'lwstoken1' => $lastPoolKey,
			'lwstoken2' => self::getUnlockableKey($user, $lastPool, $unlockable),
			'lwsnoc' => \date_create()->getTimestamp(), // unused arg, generated to bypass some cache system
		);
		if ($apply)
			$args['lwsapply'] = '';
		return \add_query_arg($args, $url);
	}

	static public function getUnlockableKey($user, $pool, $unlockable)
	{
		return \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			json_encode($unlockable->getData(true)),
			$user->ID,
			$user->user_email
		))));
	}

	function __construct()
	{
		\add_action('query_vars', array($this, 'addVars'));
		\add_action('parse_request', array($this, 'unlock'));
		\add_action('wp_footer', array($this, 'addPopup'));
	}

	/** Check arguments, then generate the reward and register a user notification.
	 * Finally redirect to erase the argument from url. */
	public function unlock($query)
	{
		$userKey = isset($query->query_vars['lwsrewardclaim']) ? trim($query->query_vars['lwsrewardclaim']) : '';
		$poolKey = isset($query->query_vars['lwstoken1']) ? trim($query->query_vars['lwstoken1']) : '';
		$key = isset($query->query_vars['lwstoken2']) ? trim($query->query_vars['lwstoken2']) : '';

		if ($userKey && $poolKey && $key) {
			global $wpdb;
			// find claimer user
			$user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_user_key' AND meta_value=%s", $userKey));
			$user = ($user_id ? \get_user_by('ID', $user_id) : false);
			// find claimed pool
			$pool_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='lws_woorewards_pool_rkey' AND meta_value=%s", $poolKey));
			$pool = ($pool_id ? \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($pool_id, true) : false);

			$claimed = false;
			if ($user && $pool) {
				// check current user if any
				$loggedUserId = \get_current_user_id();
				if ($loggedUserId && ($loggedUserId != $user->ID)) {
					// another user is logged, avoid confusion
					$this->redirect(array('lws-wr-claimi' => 'ucf'));
				}
				// find the claimed unlockable
				foreach ($pool->getUnlockables()->asArray() as $unlockable) {
					if ($key == self::getUnlockableKey($user, $pool, $unlockable)) {
						if (isset($query->query_vars['lwsapply']) && \method_exists($unlockable, 'setAutoApply')) {
							$unlockable->setAutoApply(true);
						}
						$claimed = $unlockable;
						break;
					}
				}

				if($claimed) {
					if ($pool->unlock($user, $claimed)) {
						// success
						$this->redirect(array('lws-wr-claimi' => -(int)$claimed->getId()));
					} elseif (!$pool->isBuyable()) {
						// fail, pool passed away
						$this->redirect(array('lws-wr-claimi' => 'nbu'));
					}
					else {
						// fail, perhaps insufisent point
						$this->redirect(array('lws-wr-claimi' => 'fai'));
					}
				}
				else {
					// no reward found
					$this->redirect(array('lws-wr-claimi' => 'nou'));
				}
			}
			else {
				// user or pool not found
				$this->redirect(array('lws-wr-claimi' => 'nup'));
			}
		}
	}

	// compute url, redirect to and die.
	protected function redirect($args, $keepPage=true) {
		$url = false;
		if (!$keepPage && \LWS\Adminpanel\Tools\Conveniences::isWC() && \get_current_user_id()) {
			if (\get_option('lws_woorewards_wc_my_account_endpont_loyalty', 'on'))
				$url = \LWS_WooRewards_Pro::getEndpointUrl('lws_woorewards');
		}
		if (!$url) {
			$url = \remove_query_arg($this->addVars(array('lwsnoc')));
		}
		\wp_redirect(\add_query_arg($args, $url));
		exit;
	}

	/** Tell wordpress to look for our url argument (then readable in $query_vars) */
	public function addVars($query_vars=array())
	{
		// unlock reward
		$query_vars[] = 'lwsrewardclaim';
		$query_vars[] = 'lwstoken1';
		$query_vars[] = 'lwstoken2';
		$query_vars[] = 'lwsapply';
		// show result (after redirect)
		$query_vars[] = 'lws-wr-claimi';
		return $query_vars;
	}

	function getNotice($force=false)
	{
		if (\get_option('lws_woorewards_reward_popup_legacy') == 'on') {
			if (\get_option('lws_wr_rewardclaim_popup_disable', ''))
				return false;
		} else {
			if (!\get_option('lws_wr_reward_popup_enable', 'on'))
				return false;
		}

		if (!$force) {
			global $wp_query;
			if (isset($wp_query->query_vars['lws-wr-claimi'])) {
				$force = \sanitize_key($wp_query->query_vars['lws-wr-claimi']);
			}
		}

		if ($force) {
			switch($force) {
				case 'ood':
					return array(
						'title'   => __("Out-of-date link detected", 'woorewards-pro'),
						'message' => __("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'ucf':
					return array(
						'title'   => __("User account conflict", 'woorewards-pro'),
						'message' => __("This reward was not unlocked by this account.  Please login using the account from which the reward was unlocked.", 'woorewards-pro'),
					);
				case 'nup':
					return array(
						'title'   => __("User or loyalty system cannot be found", 'woorewards-pro'),
						'message' => __("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'fai':
					return array(
						'title'   => __("The requested reward cannot be unlocked", 'woorewards-pro'),
						'message' => __("The requested reward cannot be unlocked, perhaps have you already spent the required point amount? Please, have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'nbu':
					return array(
							'title'   => __("The requested reward cannot be unlocked", 'woorewards-pro'),
							'message' => __("The requested reward cannot be unlocked. The loyalty system has expired.", 'woorewards-pro'),
					);
				case 'nou':
					$force = 0; // let it go to default
				default:
					if (\is_numeric($force)) {
						if ($force = \absint($force)) {
							$claimed = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array('p' => $force))->last();
							if ($claimed) {
								return array(
									'title'   => $claimed->getTitle(),
									'message' => \LWS\WOOREWARDS\Core\Trace::toString($claimed->getReason('frontend')),
								);
							}
						}
						return array(
							'title'   => __("The requested reward cannot be found", 'woorewards-pro'),
							'message' => __("Rewards should have been updated. Please, have a look at the reward list on the site.", 'woorewards-pro'),
						);
					}
					break;
			}
		}
		return false;
	}

	function addPopup()
	{
		$notice = false;
		if (isset($_REQUEST['lws-wr-claim']) && \trim($_REQUEST['lws-wr-claim'])) {
			// Detect a v2 claim link.
			$notice = $this->getNotice('ood');
		} else {
			$notice = $this->getNotice();
		}

		if ($notice) {
			$unlockables = false;
			// show user available rewards (if option set)
			if (\get_option('lws_wr_rewardclaim_notice_with_rest', 'on')) {
				$unlockables = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUserUnlockables(\get_current_user_id(), 'avail');
			}
			if (\get_option('lws_woorewards_reward_popup_legacy') == 'on')
				$popup = new \LWS\WOOREWARDS\PRO\Ui\Legacy\RewardClaim();
			else
				$popup = new \LWS\WOOREWARDS\PRO\Ui\Popups\RewardPopup();
			echo $popup->getPopup($notice, $unlockables, 'lws_wooreward_rewardclaimed');
		}
	}
}
