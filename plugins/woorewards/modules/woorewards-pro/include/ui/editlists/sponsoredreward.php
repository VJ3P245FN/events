<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Edit what can be bought with points.
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class SponsoredReward extends \LWS\WOOREWARDS\Ui\Editlists\MultiFormList
{
	private $stepInfo   = false;
	private $choices    = false;
	private $collection = false;

	function labels()
	{
		$labels = array(
			'title'       => __("Public title", 'woorewards-pro'),
			'description' => __("Reward descriptions", 'woorewards-pro')
		);
		return \apply_filters('lws_woorewards_unlockablelist_labels', $labels);
	}

	function read($limit=null)
	{
		$unlockables = array();
		foreach( $this->getCollection()->asArray() as $unlockable )
		{
			$unlockables[] = $this->objectToArray($unlockable);
		}
		return $unlockables;
	}

	protected function getStepInfo()
	{
		if (false === $this->stepInfo) {
			$this->stepInfo = __("Reward Settings", 'woorewards-pro');
		}
		return $this->stepInfo;
	}
	private function objectToArray($item)
	{
		return array_merge(
			array(
				self::ROW_ID  => $item->getId(), // it is important that id is first for javascript purpose
				'wre_type'    => $item->getType(),
				'title'       => $item->getThumbnailImage('lws_wr_thumbnail_small')."<div class='lws-wr-unlockable-title'>".$item->getTitle()."</div>",
				'description' => $item->getCustomDescription(),
				'cost'        => $item->getCost()
			),
			$item->getData()
		);
	}

	protected function loadChoices()
	{
		if( false === $this->choices )
		{
			$blacklist = \LWS\Adminpanel\Tools\Conveniences::isWC() ? false : array('woocommerce'=>'woocommerce');
			$this->choices = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(
				$blacklist, array('sponsorship'), $this->getCollection()->getTypes()
			)->usort(function($a, $b){return strcmp($a->getDisplayType(), $b->getDisplayType());});
		}
		return $this->choices;
	}

	protected function getGroups()
	{
		return \LWS\WOOREWARDS\Ui\Editlists\UnlockableList::getChoiceCategories();
	}

	function write($row)
	{
		$item = null;
		$type = (is_array($row) && isset($row['wre_type'])) ? trim($row['wre_type']) : '';
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->getCollection()->find($id);
			if( empty($item) )
				return new \WP_Error('404', __("The selected Referral Reward cannot be found.", 'woorewards-pro'));

			if( $type != $item->getType() )
			{
				$item->delete();
				$item = null;
				$row[self::ROW_ID] = 0;
			}
		}
		if( empty($item) && !empty($type) )
		{
			$item = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create($type)->last();
			if( empty($item) )
				return new \WP_Error('404', __("The selected Referral Reward type cannot be found.", 'woorewards-pro'));
		}

		if( !empty($item) )
		{
			if( true === ($err = $item->submit($row)) )
			{
				$dummy = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();
				$item->save($dummy);
				\update_post_meta($item->getId(), 'wre_sponsored_reward', 'yes');
				return $this->objectToArray($item);
			}
			else
				return new \WP_Error('update', $err);
		}
		return false;
	}

	function erase($row)
	{
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->getCollection()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Referral Reward cannot be found.", 'woorewards-pro'));
			}
			else
			{
				$item->delete();
				return true;
			}
		}
		return false;
	}

	function getCollection()
	{
		if( false === $this->collection )
		{
			$this->collection = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'     => 'wre_sponsored_reward',
						'value'   => 'yes',
						'compare' => 'LIKE'
					)
				)
			));
		}
		return $this->collection;
	}
}