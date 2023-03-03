<?php
/**
 * Settings class file.
 *
 * @package SVG Captcha/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class SVG_Captcha_Settings {

	/**
	 * The single instance of SVG_Captcha_Settings.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 'svgca_';

		// Initialise settings.
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page.
		
		add_filter('plugin_action_links',array($this,'add_settings_link'),10,2);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter( $this->base . 'menu_settings', array( $this, 'configure_settings' ) );
	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item() {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] ) && function_exists( 'add_' . $args['location'] . '_page' ) ) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );
					break;
				case 'menu':
					$page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'], $args['icon_url'], $args['position'] );
					break;
				default:
					return;
			}
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings() {
		
		return apply_filters(
		
			$this->base . 'menu_settings',
			array(
				'location'    => 'options', // Possible settings: options, menu, submenu.
				'parent_slug' => 'options-general.php',
				'page_title'  => __( 'Captcha Settings', 'svg-captcha' ),
				'menu_title'  => __( 'Captcha', 'svg-captcha' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'settings_page' ),
				'icon_url'    => '',
				'position'    => null,
			)
		);
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( $settings = array() ) {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets() {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
		wp_enqueue_style( 'farbtastic' );
		wp_enqueue_script( 'farbtastic' );

		// We're including the WP media scripts here because they're needed for the image upload field.
		// If you're not including an image upload then you can leave this function call out.
		wp_enqueue_media();

		wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0', true );
		wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param  array $links Existing links.
	 * @return array        Modified links.
	 */
	public function add_settings_link( $links, $file ) {
		if( strpos( $file, basename( $this->parent->file ) ) !== false ) {
			$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'svg-captcha' ) . '</a>';
			array_push( $links, $settings_link );
		}
		return $links;
	}
	
	public function get_fields() {
		
		return $this->settings_fields();
	}
	
	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		
		$settings = array();

		$settings['general'] = array(
			'title'       => __( 'General', 'svg-captcha' ),
			'description' => __( 'Captcha basic settings', 'svg-captcha' ),
			'fields'      => array(
				array(
					'id'          	=> 'captcha_difficulty',
					'label'       	=> __( 'Difficulty', 'svg-captcha' ),
					'description' 	=> '',
					'type'        	=> 'select',
					'options'     	=> array(
					
						'easy'		=> 'easy', 
						'medium'	=> 'medium', 
						'hard'		=> 'hard',
					),
					'default'		=> 'easy',
				),
				array(
					'id'          	=> 'captcha_length',
					'label'       	=> __( 'Length', 'svg-captcha' ),
					'description' 	=> 'chars',
					'type'        	=> 'number',
					'placeholder' 	=> 'length',
					'default'     	=> 4,
				),
				array(
					'id'          	=> 'captcha_case_sensitive',
					'label'       	=> __( 'Case sensitive', 'svg-captcha' ),
					'description' 	=> '(AbCd != ABcD)',
					'type'        	=> 'checkbox',
				),
			),
		);
		
		$settings['locations'] = array(
			'title'       => __( 'Locations', 'svg-captcha' ),
			'description' => __( 'Captcha locations', 'svg-captcha' ),
			'fields'      => apply_filters('svgc_locations_settings',array(
				array(
					'id'          	=> 'enable_captcha_on_comments',
					'label'       	=> __( 'Comments', 'svg-captcha' ),
					'description' 	=> 'enable the captcha on comment form',
					'type'        	=> 'checkbox',
				),
				array(
					'id'          	=> 'enable_captcha_on_login',
					'label'       	=> __( 'Login', 'svg-captcha' ),
					'description' 	=> 'enable the captcha on login form',
					'type'        	=> 'checkbox',
				),
			)),
		);
		
		$settings['style'] = array(
			'title'       => __( 'Style', 'svg-captcha' ),
			'description' => __( 'Captcha appearance & style', 'svg-captcha' ),
			'fields'      => array(
				array(
					'id'          	=> 'captcha_border',
					'label'       	=> __( 'Border', 'svg-captcha' ),
					'description' 	=> '',
					'type'        	=> 'text',
					'placeholder' 	=> 'inline css',
					'default'     	=> 'border: 1px solid 0f0;',
				),
				array(
					'id'          	=> 'captcha_width',
					'label'       	=> __( 'Width', 'svg-captcha' ),
					'description' 	=> 'px',
					'type'        	=> 'number',
					'placeholder' 	=> 'width',
					'default'     	=> 150,
				),
				array(
					'id'          	=> 'captcha_height',
					'label'       	=> __( 'Height', 'svg-captcha' ),
					'description' 	=> 'px',
					'type'        	=> 'number',
					'placeholder' 	=> 'height',
					'default'     	=> 80,
				),
				/*
				array(
					'id'          	=> 'captcha_preview',
					'label'       	=> __( 'Preview', 'svg-captcha' ),
					'description' 	=> '',
					'type'        	=> 'html',
					'default'		=> $this->parent->svgc_reload_link(),
				),
				*/
			),
		);
		
		$settings['custom'] = array(
			'title'       => __( 'Custom', 'svg-captcha' ),
			'description' => __( 'Custome settings', 'svg-captcha' ),
			'fields'      => array(
				array(
					'id'          	=> 'custom_captcha',
					'label'       	=> __( 'Disable levels', 'svg-captcha' ),
					'description' 	=> 'disable the difficulty levels and use the settings bellow',
					'type'        	=> 'checkbox',
				),
				array(
					'id'          	=> 'cg_glyph_offsetting',
					'label'       	=> __( 'Offsetting', 'svg-captcha' ),
					'description' 	=> 'use glyph offsetting as a obfuscation technique',
					'type'        	=> 'checkbox',
				),
				array(
					'id'          	=> 'cg_glyph_fragments',
					'label'       	=> __( 'Fragments', 'svg-captcha' ),
					'description' 	=> 'use glyph fragments to distort the image',
					'type'        	=> 'checkbox',
				),
				array(
					'id'          	=> 'cg_transformations',
					'label'       	=> __( 'Transformations', 'svg-captcha' ),
					'description' 	=> 'affine transformations',
					'type'        	=> 'checkbox',
				),
				array(
					'id'          	=> 'cg_approx_shapes',
					'label'       	=> __( 'Approximation', 'svg-captcha' ),
					'description' 	=> 'approximate shapes',
					'type'        	=> 'checkbox',
				),	
				array(
					'id'          	=> 'cg_change_degree',
					'label'       	=> __( 'Degree', 'svg-captcha' ),
					'description' 	=> 'change the degree of splines',
					'type'        	=> 'checkbox',
				),			
				array(
					'id'          	=> 'cg_split_curve',
					'label'       	=> __( 'Curve', 'svg-captcha' ),
					'description' 	=> 'split curves as a distortion technique',
					'type'        	=> 'checkbox',
				),		
				array(
					'id'          	=> 'cg_shapeify',
					'label'       	=> __( 'Shapes', 'svg-captcha' ),
					'description' 	=> 'inject randomly generated shapes',
					'type'        	=> 'checkbox',
				),				
			),
		);
		
		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab.
			//phpcs:disable
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}
			//phpcs:enable

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section !== $section ) {
					continue;
				}

				// Add section to page.
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field.
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page.
					add_settings_field(
						$field['id'],
						$field['label'],
						array( $this->parent->admin, 'display_field' ),
						$this->parent->_token . '_settings',
						$section,
						array(
							'field'  => $field,
							'prefix' => $this->base,
						)
					);
				}

				if ( ! $current_section ) {
					break;
				}
			}
		}
	}

	/**
	 * Settings section.
	 *
	 * @param array $section Array of section ids.
	 * @return void
	 */
	public function settings_section( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html; //phpcs:ignore
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function settings_page() {

		// Build page HTML.
		$html      = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'Captcha Settings', 'svg-captcha' ) . '</h2>' . "\n";

			$tab = '';
		//phpcs:disable
		if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
			$tab .= $_GET['tab'];
		}
		//phpcs:enable

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			$c = 0;
			foreach ( $this->settings as $section => $data ) {

				// Set tab class.
				$class = 'nav-tab';
				if ( ! isset( $_GET['tab'] ) ) { //phpcs:ignore
					if ( 0 === $c ) {
						$class .= ' nav-tab-active';
					}
				} else {
					if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) { //phpcs:ignore
						$class .= ' nav-tab-active';
					}
				}

				// Set tab link.
				$tab_link = add_query_arg( array( 'tab' => $section ) );
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg( 'settings-updated', $tab_link );
				}

				// Output tab.
				$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

				++$c;
			}

			$html .= '</h2>' . "\n";
		}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields.
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html     .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'svg-captcha' ) ) . '" />' . "\n";
				$html     .= '</p>' . "\n";
			$html         .= '</form>' . "\n";
		$html             .= '</div>' . "\n";

		echo $html; //phpcs:ignore
	}

	/**
	 * Main SVG_Captcha_Settings Instance
	 *
	 * Ensures only one instance of SVG_Captcha_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see SVG_Captcha()
	 * @param object $parent Object instance.
	 * @return object SVG_Captcha_Settings instance
	 */
	public static function instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of SVG_Captcha_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of SVG_Captcha_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
