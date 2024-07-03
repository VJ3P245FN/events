<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Show the methods to earn points */
class EventsWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('wr_events', array($me, 'showEvents'));
		\add_filter('lws_adminpanel_stygen_content_get_' . 'events_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-events', LWS_WOOREWARDS_PRO_CSS . '/templates/events.css?stygen=lws_woorewards_events_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-events');
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget = true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_events',
				__("MyRewards Earning Points", 'woorewards-pro'),
				array(
					'description' => __("Display Ways to Earn points on a Loyalty System", 'woorewards-pro')
				)
			);
		}
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$new_instance = $this->parseArgs($new_instance, true);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Earning methods - Header", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Earning methods - Description", $new_instance['text']);
		return $new_instance;
	}

	protected function defaultArgs()
	{
		return array(
			'title'	=> '',
			'header' => '',
			'text'  => '',
		);
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($instance, $withPoolArgs = false)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());
		if (!isset($instance['system'])) {
			if (isset($instance['pool_name']))
				$instance['system'] = $instance['pool_name'];
			else if (isset($instance['pool']))
				$instance['system'] = $instance['pool'];
		}
		if ($withPoolArgs)
			$instance = \array_merge(array('system' => '', 'shared' => '', 'force' => ''), $instance);
		return $instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = $this->parseArgs($instance, true);

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Earn Loyalty Points", "frontend widget", 'woorewards-pro'))
		);

		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("How to earn loyalty points ", "frontend widget", 'woorewards-pro'))
		);

		$options = array();
		foreach (\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool) {
			$options[$pool->getId()] = $pool->getOption('display_title');
		}

		// pool
		$this->eformFieldSelect(
			$this->get_field_id('system'),
			__("Select a Loyalty System", 'woorewards-pro'),
			$this->get_field_name('system'),
			$options,
			isset($instance['system']) ? $instance['system'] : ''
		);

		// shared systems
		$this->eformFieldCheckbox(
			$this->get_field_id('shared'),
			__("Show shared systems", 'woorewards-pro'),
			$this->get_field_name('shared'),
			\esc_attr($instance['shared'])
		);

		// force display
		$this->eformFieldCheckbox(
			$this->get_field_id('force'),
			__("Force display for all users", 'woorewards-pro'),
			$this->get_field_name('force'),
			\esc_attr($instance['force'])
		);

		// text
		$this->eFormFieldText(
			$this->get_field_id('text'),
			__("Text displayed to users", 'woorewards-pro'),
			$this->get_field_name('text'),
			\esc_attr($instance['text']),
			\esc_attr(_x("Perform the actions described below to earn loyalty points", "frontend widget", 'woorewards-pro'))
		);
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		echo $args['before_widget'];
		echo $args['before_title'];
		echo \apply_filters('widget_title', empty($instance['title']) ? _x("Earn Loyalty Points", "frontend widget", 'woorewards-pro') : $instance['title'], $instance);
		echo $args['after_title'];
		echo $this->showEvents($instance, '');
		echo $args['after_widget'];
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$atts = $this->defaultArgs();
		$events = array(
			array('desc' => 'Buy the product <a href="#">MyRewards</a>', 'earned' => '123'),
			array('desc' => 'Spend money', 'earned' => '5 Points/1 &#36;'),
			array('desc' => 'Review a product', 'earned' => '5'),
			array('desc' => 'Recurrent visit', 'earned' => '1'),
		);
		$content = $this->getContent($atts, $events);
		$this->stygen = false;
		return $content;
	}

	public function showEvents($atts = array(), $content = '')
	{
		$this->enqueueScripts();
		return $this->getContent($atts);
	}

	public function getContent($atts = array(), $events = false)
	{
		$atts = $this->parseArgs($atts);
		if (false === $events)
			$events = $this->getEvents($atts);

		if (empty($events))
			return '';

		if ($this->stygen)
			$symbol = __("Points", 'woorewards-pro');
		else
			$symbol = $events[0]['poolName'] ? \LWS_WooRewards::getPointSymbol(100, $events[0]['poolName']) : __("Points", 'woorewards-pro');

		$labels = array(
			'desc' => __("Action to perform", 'woorewards-pro'),
			'points' => sprintf(__("%s earned", 'woorewards-pro'), $symbol)
		);

		if (empty($atts['header']))
			$atts['header'] = \lws_get_option('lws_woorewards_events_widget_message', __("How to earn loyalty points", 'woorewards-pro'));
		if (empty($atts['text']))
			$atts['text'] = \lws_get_option('lws_woorewards_events_widget_text', __("Perform the actions described below to earn loyalty points", 'woorewards-pro'));

		if (!$this->stygen) {
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Earning methods - Header");
			$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Earning methods - Description");
		}

		$lines = '';
		if ($events) {
			foreach ($events as $event) {
				$lines .= <<<EOT
<div class='lwss_selectable lws-wr-event-line' data-type='Earning Method Line'>
	<div class='lwss_selectable lws-wr-event-text' data-type='Earning Method'>{$event['desc']}</div>
	<div class='lwss_selectable lws-wr-event-points' data-type='Earning Method'>{$event['earned']}</div>
</div>
EOT;
			};
		}

		$content = <<<EOT
<div class='lwss_selectable lws-wr-events-cont' data-type='Main Conteneur'>
	<div class='lwss_selectable lwss_modify lws-wr-events-header' data-id='lws_woorewards_events_widget_message' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
	<div class='lwss_selectable lwss_modify lws-wr-events-text' data-id='lws_woorewards_events_widget_text' data-type='Message to users'>
		<span class='lwss_modify_content'>{$atts['text']}</span>
	</div>
	<div class='lwss_selectable lws-wr-eventslist' data-type='Earning Methods'>
		<div class='lwss_selectable lws-wr-event-title-line' data-type='Title Line'>
			<div class='lwss_selectable lws-wr-event-title-desc' data-type='Description Title'>{$labels['desc']}</div>
			<div class='lwss_selectable lws-wr-event-title-points' data-type='Points Title'>{$labels['points']}</div>
		</div>
		$lines
	</div>
</div>
EOT;

		return $content;
	}

	private function getEvents($atts = array())
	{
		// look for the appropriate pools depending on args
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if (!($pools && $pools->count()))
			return false;

		$events = array();
		foreach ($pools->asArray() as $pool) {
			foreach ($pool->getEvents()->asArray() as $item) {
				if (!\method_exists($item, 'isValidCurrency') || $item->isValidCurrency()) {
					$eventInfo = array(
						'desc'     => $item->getTitle(),
						'earned'   => $item->getGainForDisplay(),
						'poolName' => $item->getPoolName(),
					);
					if (!$eventInfo['desc']) {
						$eventInfo['desc'] = $item->getDescription('frontend');
					}
					$events[] = $eventInfo;
				}
			}
		}
		return $events;
	}
}
