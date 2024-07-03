<?php

if (!class_exists('EW_Blocks')) :

	class EW_Blocks
	{

		/**
		 * @var null
		 */
		private static $instance = null;

		public function __construct()
		{
			// Hook: Editor assets.
			add_action('enqueue_block_editor_assets', [$this, 'ew_block_assets']);
			// Add custom block category
			add_filter(
				'block_categories_all',
				function ($categories, $post) {
					return array_merge(
						$categories,
						array(
							array(
								'slug'  => 'ew-block',
								'title' => __('EW Blocks', 'catch-blocks'),
							),
						)
					);
				},
				10,
				2
			);

			// Load all the blocks
			$this->load_blocks();
		}

		public function ew_block_assets()
		{
			// Scripts.
			wp_enqueue_script(
				'ew-block-js', // Handle.
				plugins_url('ew-block/blocks.build.js', dirname(__FILE__)), // Block.build.js: We register the block here. Built with Webpack.
				array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor') // 
			);

			// Styles.
			wp_enqueue_style(
				'ew-block-editor-css', // Handle.
				plugins_url('ew-block/blocks.editor.build.css', dirname(__FILE__)), // Block editor CSS.
				array('wp-edit-blocks') // Dependency to include the CSS after it.
			);
		} // End function catch_guten_cgb_editor_assets().

		public function load_blocks()
		{
			require_once 'blocks/ew-archive/index.php';
			require_once 'blocks/ew-author/index.php';
			require_once 'blocks/ew-category/index.php';
			require_once 'blocks/ew-page/index.php';
			require_once 'blocks/ew-post/index.php';
			require_once 'blocks/ew-tags/index.php';
			require_once 'blocks/ew-menu/index.php';
		}

		/**
		 * @return EW_Blocks|null
		 */
		public static function instance()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}

	EW_Blocks::instance();

endif;
