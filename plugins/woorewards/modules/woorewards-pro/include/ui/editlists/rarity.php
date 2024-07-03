<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if (!defined('ABSPATH')) {
    exit();
}

/** Set the different badges rarity levels
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class BadgeRarity extends \LWS\Adminpanel\EditList\Source
{
    const ROW_ID = 'rarity_id';

    public function labels()
    {
        $labels = array(
            'percentage'  => __("Max Percentage", 'woorewards-pro'),
            'rarity' => __("Rarity", 'woorewards-pro')
        );
        return \apply_filters('lws_woorewards_rarity_labels', $labels);
    }

    public function read($limit=null)
    {
		$source = $this->getCollection();
		foreach( $source as &$rarity )
		{
			$rarity['rarity_id'] = $rarity['percentage'];
		}
		return $source;
    }

    public function input()
    {
        $labels = array(
            'percentage'  => __("Max Percentage", 'woorewards-pro'),
            'rarity' => __("Rarity", 'woorewards-pro')
        );

		$retour = "<div class='lws-woorewards-rarity-edit editlist-content-grid'>";
        $retour .= "<input type='hidden' name='" . self::ROW_ID . "' class='lws_woorewards_rarity_id' />";
		$retour .= "<div class='fieldset'>";
		$retour .= "<div class='title'>Rarity Percentage</div>";
		$retour .= "<div class='fieldset-grid'>";
		$retour .= "<div class='lws-editlist-opt-input label'>{$labels['percentage']}</div>";
		$retour .= "<div class='lws-editlist-opt-input value'><input name='percentage' type='text'/></div>";
		$retour .= "</div></div>";
		$retour .= "<div class='fieldset'>";
		$retour .= "<div class='title'>Rarity Description</div>";
		$retour .= "<div class='fieldset-grid'>";
		$retour .= "<div class='lws-editlist-opt-input label'>{$labels['rarity']}</div>";
		$retour .= "<div class='lws-editlist-opt-input value'><input name='rarity' type='text' /></div>";
		$retour .= "</div></div>";
        $retour .= "</div>";
        return $retour;
    }

    public function write($row)
    {
		$source = $this->getCollection();
		/* Basic verifications */
		$row['rarity'] = esc_attr($row['rarity']);
		if(!is_numeric($row['percentage'])) return new \WP_Error('404', __("The percentage must be a numeric value", 'woorewards-pro'));
		if($row['percentage']<0 || $row['percentage']>100 ) return new \WP_Error('404', __("The percentage must be number between 0 and 100", 'woorewards-pro'));
		if(empty($row['rarity'])) return new \WP_Error('404', __("The rarity label can't be empty", 'woorewards-pro'));

		/* New Value*/
		if(empty($row['rarity_id']))
		{
			if(!empty($source[$row['percentage']]))	return new \WP_Error('404', __("This percentage is already taken", 'woorewards-pro'));
		}else{
			/* Percentage Change */
			if($row['rarity_id'] != $row['percentage']) unset($source[$row['rarity_id']]);
		}

		/*Update Option */
		$row['rarity_id'] = $row['percentage'];
		$source[$row['rarity_id']]['percentage'] = $row['percentage'];
		$source[$row['rarity_id']]['rarity'] = $row['rarity'];
		asort($source);
		$source = array_reverse($source,true);
		\update_option('lws_woorewards_rarity_levels', $source);

        return $row;
    }

    public function erase($row)
    {

		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$source = $this->getCollection();
			$item = $source[$id];
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Percentage cannot be found.", 'woorewards-pro'));
			}
			else
			{
				unset($source[$id]);
				\update_option('lws_woorewards_rarity_levels', $source);
				return true;
			}
		}
        return false;
    }

    public function getCollection()
    {
		static $collection = false;
		if( $collection === false )
		{
            $defaults = array(
				'100' => array(
					'percentage'=> 100,
					'rarity'	=> __("Common", 'woorewards-pro'),
				),
				'50' => array(
					'percentage'=> 50,
					'rarity'	=> __("Uncommon", 'woorewards-pro'),
				),
				'20' => array(
					'percentage'=> 20,
					'rarity'	=> __("Rare", 'woorewards-pro'),
				),
				'10' => array(
					'percentage'=> 10,
					'rarity'	=> __("Epic", 'woorewards-pro'),
				),
				'2' => array(
					'percentage'=> 2,
					'rarity'	=> __("Legendary", 'woorewards-pro'),
				),
			);
            $collection = \lws_get_option("lws_woorewards_rarity_levels", $defaults);
		}
		return $collection;
    }
}
