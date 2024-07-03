<?php
namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget to display badges
 * Can be used as a Widget or a Shortcode [lws_badges]. */
class BadgesWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	public static function install()
	{
		self::register(\get_class());
	}

	/** Will be instanciated by WordPress at need */
	public function __construct()
	{
		parent::__construct(
			'lws_woorewards_badges',
			__("MyRewards Badges", 'woorewards-pro'),
			array(
				'description' => __("Display Badges", 'woorewards-pro')
			)
		);
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$dummy = new \LWS\WOOREWARDS\PRO\Ui\ShortCodes\Badges();
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$dummy->defaultArgs()
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Badges - Title", $new_instance['header']);

		return $new_instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$dummy = new \LWS\WOOREWARDS\PRO\Ui\ShortCodes\Badges();
		$instance = \wp_parse_args($instance, $dummy->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Badges List", "frontend widget", 'woorewards-pro'))
		);

		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Here is the list of badges available on this website", "frontend widget", 'woorewards-pro'))
		);

		// behavior
		$this->eFormFieldSelect(
			$this->get_field_id('display'),
			__("Filter Badges", 'woorewards-pro'),
			$this->get_field_name('display'),
			array(
				'all'      => __("All", 'woorewards-pro'),
				'owned'     => __("Owned only (requires a logged customer)", 'woorewards-pro')
			),
			$instance['display']
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
		echo \apply_filters('widget_title', empty($instance['title']) ? _x("Badges List", "frontend widget", 'woorewards-pro') : $instance['title'], $instance);
		echo $args['after_title'];
		$dummy = new \LWS\WOOREWARDS\PRO\Ui\ShortCodes\Badges();
		if (isset($instance['header']) && !$instance['header'])
			$instance['header'] = false;
		echo $dummy->shortcode($instance, '');
		echo $args['after_widget'];
	}
}
