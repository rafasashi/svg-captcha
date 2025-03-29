<?php
/**
 * Main plugin class file.
 *
 * @package SVG Captcha/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class SVG_Captcha {

	/**
	 * The single instance of SVG_Captcha.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * Local instance of SVG_Captcha_Admin_API
	 *
	 * @var SVG_Captcha_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;
	

    /**
     * The current administration menu options.
     */
    private $captcha_options 	= array();
	private $captcha_locations 	= array();

    /**
     * The SVGCaptcha instance.
     */
    private $svgCaptcha;

    /**
     * SVGCaptcha data (SVG string)
     */
    private $svg_output;

    /**
     * SVGCaptcha answer;
     */
    private $captcha_answer;

    /**
     *  Should we consider the case of the captcha? 
     */
    private $case_sensitive = true;


	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		
		$this->_version = $version;
		$this->_token   = 'svg_captcha';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions.
		
		if ( is_admin() ) {
			
			$this->admin = new SVG_Captcha_Admin_API();
		}

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
		
		add_action( 'init', array( $this, 'init_captcha' ), 0 );
		
	} // End __construct ()
	
	public function init_captcha(){
		
		if( $settings = $this->settings->get_fields() ){
			
			foreach( $settings as $type => $setting ){
				
				foreach( $setting['fields'] as $field ){
					
					$id = $field['id'];
					
					$default = isset($field['default']) ? $field['default'] : false;
					
					$value = get_option($this->settings->base . $id,$default);
					
					if( $type == 'locations' ){
						
						$this->captcha_locations[$id] = $value;
					}
					else{
						
						$this->captcha_options[$id] = $value;
					}
				}
			}
		}

        $this->case_sensitive = $this->captcha_options['captcha_case_sensitive'];

        // Enqueue and register ajax script for reload captcha capability
        
		wp_register_script('captcha-reload', esc_url( $this->assets_url ) . 'js/reload_captcha.js', array('jquery'));
        wp_enqueue_script('captcha-reload');
        
		// code to declare the URL to the file handling the AJAX request
        
		wp_localize_script('captcha-reload', 'captchaObject', array('ajaxurl' => admin_url('admin-ajax.php')));

        // Add ajax reload link handler
        
		add_action('wp_ajax_nopriv_svgc_captcha_reload', array($this, 'svgc_captcha_reload'));
        add_action('wp_ajax_svgc_captcha_reload', array($this, 'svgc_captcha_reload'));
		
		add_action('svgc_render_form_input', array($this,'svgc_render_form_input'),9999);
		
		add_action('svgc_validate_register_captcha', array($this,'svgc_validate_register_captcha'),9999,3);
		
		add_filter('svgc_location_enable_captcha_on_register',function(){
            
			// Add custom captcha field to login form
            
			add_action('register_form', array($this,'svgc_render_form_input'),9999);
			
			add_action('registration_errors', array($this,'svgc_validate_register_captcha'),10,3);
		});
		
		add_filter('svgc_location_enable_captcha_on_login',function(){
            
			// Add custom captcha field to login form
            
			add_action('login_form', array($this, 'svgc_login_form'),10,1); // using default page

			add_action('login_form_middle', array($this, 'svgc_login_form'),10,2); // using wp_login_form
            
			add_filter('authenticate', array($this, 'svgc_validate_login_captcha'), 30, 3); // Validate captcha in login form.
        });
		
		add_filter('svgc_location_enable_captcha_on_comments',function(){
			
			// Add captcha to comment form
            
			add_filter('comment_form_field_comment', array($this, 'svgc_form_input')); // Add a filter to verify if the captcha in the comment section was correct.
			
			add_filter('preprocess_comment', array($this, 'svgc_validate_form_captcha'));
        });
		
		if( !empty($this->captcha_locations) ){
		
			foreach( $this->captcha_locations as $location => $status ){

				if( $status == 'on' ){
					
					do_action('svgc_location_'.$location);
				}
			}
		}

		//register_uninstall_hook($this->file, array($this, 'on_uninstall'));
		//register_activation_hook($this->file, array($this, 'on_activation'));
	}

	/**
	 * Register post type function.
	 *
	 * @param string $post_type Post Type.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param string $description Description.
	 * @param array  $options Options array.
	 *
	 * @return bool|string|SVG_Captcha_Post_Type
	 */
	public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) {
			return false;
		}

		$post_type = new SVG_Captcha_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param array  $post_types Post types to register this taxonomy for.
	 * @param array  $taxonomy_args Taxonomy arguments.
	 *
	 * @return bool|string|SVG_Captcha_Taxonomy
	 */
	public function register_taxonomy( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) {
			return false;
		}

		$taxonomy = new SVG_Captcha_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles() {
		
		//wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-frontend' );
	
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		
		//wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		//wp_enqueue_script( $this->_token . '-frontend' );
	
	} // End enqueue_scripts ()

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( $hook = '' ) {
		
		//wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-admin' );
	
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( $hook = '' ) {
		
		//wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		//wp_enqueue_script( $this->_token . '-admin' );
	
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localisation() {
		
		load_plugin_textdomain( 'svg-captcha', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		
		$domain = 'svg-captcha';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	
	} // End load_plugin_textdomain ()

    public function on_uninstall() {
        if (!current_user_can('activate_plugins'))
            return;
        check_admin_referer('bulk-plugins');

        // Important: Check if the file is the one
        // that was registered during the uninstall hook.
        if ($this->file != WP_UNINSTALL_PLUGIN)
            return;

        if (!delete_option('svgc_options')) {
            wp_die("Couldn't uninstall plugin");
        }
    }

    public function on_activation() {
        if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("activate-plugin_{$plugin}");

        // If there is such a option, delete it.
        if (get_option('svgc_options', False) != False) {
            delete_option('svgc_options');
        }
    }

    /**
     * Create the captcha and store the encrypted solution in a hidden field.
     * Alternatives:
     * Use a session variable (Bad: Uses files = slow). Maybe the best.
     * Use add_option() [Using a database] (Bad: Needs to be written to = slow). Maybe usable.
     * Use a encrypted hidden field. Bad, unsecure.
     * Use a cookie. (Bad: Can't make it working with wordpress.)
     * 
     * @param type $default
     * @return string
     */
    public function svgc_form_input($html='') {
		
        if( !is_admin() ) {
			
            $this->svgc_get_captcha();
			
			$html .='<label for="svgc_answer">' . __('Captcha', 'svg-captcha') . '<span class="required"> *</span></label>';
				
				$html .='<div style="width:100%;display:inline-block;">';
					
					$len = intval($this->captcha_options['captcha_length']);
				
					for($i = 0; $i < $len; $i++){
						
						$html .='<input id="svgc_input_'.($i+1).'" data-next="'.($i+2).'" style="width:30px;float:left;padding:0 6px;font-weight:bold;margin-right:5px;font-size:20px;" maxlength="1" type="text" name="svgc_answer[]" class="form-control" value="" required="required" oninput="a=this.attributes;n=parseInt(a[\'maxlength\'].value);if(this.value.length===n)document.getElementById(\'svgc_input_\'+a[\'data-next\'].value).focus()"/>';
					}
					
				$html .='</div>';
				
                $html .= '<img id="SVGCaptchaLoader" src="'.$this->assets_url.'images/loader.gif">';
                    
				$html .= '<div id="SVGCaptchaContainer" style="padding:10px 0px;display:inline-block;">';
                
                    //$html .= $this->svg_output;
                
                $html .= '</div>';
				
				$html .= $this->svgc_reload_link();
        }
		
        return $html;
    }
	
	public function svgc_render_form_input($html=''){
		
		echo $this->svgc_form_input($html);
	}
	
    public function svgc_validate_register_captcha($errors, $sanitized_user_login, $user_email) {
		
		if( !is_admin() ) {
            
			if( empty($_POST['svgc_answer']) || !is_array($_POST['svgc_answer']) ) {
                
				$errors->add('invalid_captcha', __( 'You need to enter a captcha', 'svg-captcha' ) );
            } 
			else {
                
				$answer = $this->sanitize_answer($_POST['svgc_answer']);

                if( !$this->svgc_check($answer) ) {
                    
					$errors->add('invalid_captcha', __( 'Invalid captcha, try again', 'svg-captcha' ) );
				}
            }
        }
		
		return $errors;
    }

    public function svgc_validate_form_captcha($data){
       
		if( !is_admin() ) {
            
			if( empty($_POST['svgc_answer']) || !is_array($_POST['svgc_answer']) )
                
				wp_die(__('Error: You need to enter the captcha.', 'svg-captcha'));
			
			$answer = $this->sanitize_answer($_POST['svgc_answer']);
			
            if (!$this->svgc_check($answer)){
                
				wp_die(__('Error: Your supplied captcha is incorrect.', 'svg-captcha'));
			}
		}
		
        return $data;
    }
	
	private function sanitize_answer($answer=array()){
		
		if( is_array($answer) ){
			
			return sanitize_text_field(implode('',$answer));
		}
		
		return false;
	}
	
    public function svgc_login_form($html,$args=array()) {
        
		if( !is_admin() ) {
			
            $this->svgc_get_captcha();
			
            //Get and set any values already sent
           
			$html .='<p class="login-captcha">';
                
				$html .='<label for="svgc_answer">' . __('Captcha', 'svg-captcha') . '</label>';
                
				$html .='<div style="width:100%;display:inline-block;">';
					
					$len = intval($this->captcha_options['captcha_length']);
				
					for($i = 0; $i < $len; $i++){
						
						$html .='<input id="svgc_input_'.($i+1).'" data-next="'.($i+2).'" style="width:30px;float:left;padding:0 6px;font-weight:bold;margin-right:5px;font-size:20px;" maxlength="1" type="text" name="svgc_answer[]" class="form-control" value="" required="required" oninput="a=this.attributes;n=parseInt(a[\'maxlength\'].value);if(this.value.length===n)document.getElementById(\'svgc_input_\'+a[\'data-next\'].value).focus()"/>';
					}
					
				$html .='</div>';

                $html .= '<img id="SVGCaptchaLoader" src="'.$this->assets_url.'images/loader.gif">';
                    
				$html .='<div id="SVGCaptchaContainer" style="padding: 10px 0px;display:inline-block;">';
					
					//$html .= $this->svg_output;
				
				$html .='</div>';
				
				$html .= $this->svgc_reload_link();
               
            $html .='</p>';
        }
		
		if( isset($args['echo']) && $args['echo'] === false ){
			
			return $html;
		}
		else{
			
			echo $html;
		}
    }
	
    public function svgc_validate_login_captcha($user, $username, $password) {
        
		if( !is_admin() ) { /* Whenever a admin tries to login -.- */
            
			if ( empty($_POST['svgc_answer']) || !is_array($_POST['svgc_answer']) ) {
                
				return new WP_Error('invalid_captcha', __("You need to enter a captcha in order to login.", 'svg-captcha'));
            } 
			else {
                
				$answer = $this->sanitize_answer($_POST['svgc_answer']);

                if (!$this->svgc_check($answer)) {
                    
					return new WP_Error('invalid_captcha', __("Your supplied captcha is incorrect.", 'svg-captcha'));
                } 
				else {
                    
					return $user;
                }
            }
        }
    }

    /**
     * Returns html that provides a capability to reload the current captcha via ajax.
     * 
     * https://codex.wordpress.org/AJAX_in_Plugins
     */
    public function svgc_reload_link() {
		
        return '<a id="svgc-reload" style="cursor:pointer;display:none;width:15px;height:15px;margin-left:5px;"><svg id="Layer_1" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 118.04 122.88"><path d="M16.08,59.26A8,8,0,0,1,0,59.26a59,59,0,0,1,97.13-45V8a8,8,0,1,1,16.08,0V33.35a8,8,0,0,1-8,8L80.82,43.62a8,8,0,1,1-1.44-15.95l8-.73A43,43,0,0,0,16.08,59.26Zm22.77,19.6a8,8,0,0,1,1.44,16l-10.08.91A42.95,42.95,0,0,0,102,63.86a8,8,0,0,1,16.08,0A59,59,0,0,1,22.3,110v4.18a8,8,0,0,1-16.08,0V89.14h0a8,8,0,0,1,7.29-8l25.31-2.3Z"/></svg></a>';
    }

    public function svgc_captcha_reload() {
		
        if ($_REQUEST["reload"] == "reload") {
            
			$this->svgc_get_captcha();
           
			echo $this->svg_output;
        }
		
        die();
    }

    /**
     * Checks whether the user provided answer is correct.
     */
    public function svgc_check($answer) {
        
		if( !empty($_COOKIE['svgc_tok']) ){
		
			$this->captcha_answer = get_transient('svgc_session_' . sanitize_text_field($_COOKIE['svgc_tok']));
		
			if ($this->case_sensitive) {
				
				return (strcmp($answer, $this->captcha_answer) == 0) ? True : False;
			} 
			else {
				
				return (strcasecmp($answer, $this->captcha_answer) == 0) ? True : False;
			}		
		}
		
		return false;
    }

    /**
     * Choses a random captcha from the pool and returns the corresponding image path.
     * Sets global variable $captcha_value to the value (The solution the user has to enter)
     * of the captcha.
     */
    public function svgc_get_captcha() {
		
		if( empty($_COOKIE['svgc_tok']) ){
		
			$svgc_tok = substr( wp_hash( 'svgc_tok_' . time(), 'nonce' ), -12, 10 );
			
			if( !headers_sent() ){
			
				setcookie('svgc_tok', $svgc_tok, time()+3600, '/');
			}
			
			$_COOKIE['svgc_tok'] = $svgc_tok;
		}

		// and immediately create a instance determined by the specified settings (if given) or else by the default variables.
        
		$lu = array('easy' => SVG_Captcha_Generator::EASY, 'medium' => SVG_Captcha_Generator::MEDIUM, 'hard' => SVG_Captcha_Generator::HARD);
        
		// Check if we have a custom specified captcha or a predefined one (easy/medium/hard)
       
		if ($this->captcha_options["custom_captcha"] == True) {
           
			$custom_settings = array(
                
				'glyph_offsetting' 	=> array('apply' => False, 'h' => 1, 'v' => 0.5, 'mh' => 8), // Needs to be anabled by default
                'glyph_fragments' 	=> array('apply' => False, 'r_num_frag' => range(0, 6), 'frag_factor' => 2),
                'transformations' 	=> array('apply' => False, 'rotate' => True, 'skew' => True, 'scale' => True, 'shear' => False, 'translate' => True),
                'approx_shapes' 	=> array('apply' => False, 'p' => 3, 'r_al_num_lines' => range(10, 30)),
                'change_degree' 	=> array('apply' => False, 'p' => 5),
                'split_curve' 		=> array('apply' => False, 'p' => 5),
                'shapeify' 			=> array('apply' => False, 'r_num_shapes' => range(0, 6), 'r_num_gp' => range(4, 10))
            );
            
			foreach ($custom_settings as $key => $value) {
                
				$custom_settings[$key]["apply"] = $this->captcha_options["cg_" . $key];
            }

            $this->svgCaptcha = SVG_Captcha_Generator::getInstance(
                
				$this->captcha_options['captcha_length'], $width = $this->captcha_options['captcha_width'], $height = $this->captcha_options['captcha_height'], $difficulty = $custom_settings
            );
        } 
		else {
            
			$this->svgCaptcha = SVG_Captcha_Generator::getInstance(
                
				$this->captcha_options['captcha_length'], $width = $this->captcha_options['captcha_width'], $height = $this->captcha_options['captcha_height'], $difficulty = $lu[$this->captcha_options['captcha_difficulty']]
            );
        }


        list($this->captcha_answer, $this->svg_output) = $this->svgCaptcha->getSVGCaptcha();

        $this->captcha_answer = ($this->captcha_options['captcha_case_sensitive'] == True) ? $this->captcha_answer : strtolower($this->captcha_answer);
		
		if( !empty($_COOKIE['svgc_tok']) ){
		
			set_transient('svgc_session_' . sanitize_text_field($_COOKIE['svgc_tok']),$this->captcha_answer, 60 * 60 );
		}
	}

    /**
     * Get random pseudo bytes for encryption.
     */
    public function svgc_random_hex_bytes($length = 32) {
        $cstrong = False;
        $bytes = openssl_random_pseudo_bytes($length, $cstrong);
        if ($cstrong == False)
            return False;
        else
            return $bytes;
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        // This page will be under "Settings"
        $hook_suffix = add_options_page(
                __('SVG-Captcha settings', 'svg-captcha'), __('SVG-Captcha', 'svg-captcha'), 'manage_options', 'svgc_submenu', array($this, 'create_admin_page')
        );

        // Add javascript to the admin page
        add_action("load-" . $hook_suffix, array($this, "svgc_load_submenu_js"));
    }

    public function svgc_load_submenu_js() {
        add_action("admin_enqueue_scripts", array($this, 'svgc_captcha_reload_admin'));
        add_action("admin_enqueue_scripts", array($this, 'svgc_custom_captcha_toggle_admin'));
    }

    public function svgc_captcha_reload_admin() {
        wp_register_script('captcha-reload-admin', esc_url( $this->assets_url ) . 'js/reload_captcha.js', array('jquery'));
        wp_enqueue_script('captcha-reload-admin');
    }

    public function svgc_custom_captcha_toggle_admin() {
        wp_register_script('toggle-custom-captcha', esc_url( $this->assets_url ) . 'js/toggle-custom-captcha.js', array('jquery'));
        wp_enqueue_script('toggle-custom-captcha');
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
        $new_input = array();
        foreach ($input as $key => $value) {
            $new_input[$key] = sanitize_text_field($value);
        }

        return $new_input;
    }

    public function text_callback($d) {
        printf(
                '<input type="text" id="%2$s" name="svgc_options[%2$s]" value="%1$s" />', isset($this->captcha_options[$d[0]]) ? esc_attr($this->captcha_options[$d[0]]) : esc_attr($d[1]), esc_attr($d[0])
        );
    }

    /*
     * d[0] = the key of the field.
     * d[1] = the default value for the field.
     * d[2] = field title
     */

    public function select_callback($d) {
        if (!is_array($d[2]) || $d[2] == null)
            wp_die(__('Invalid value for all possible values', 'svg-captcha'));

        // Build select options
        foreach ($d[2] as $option) {
            $select_html .= '<option value="' . $option . '" ' . selected($this->captcha_options[$d[0]], $option, false) . ' >' . strtoupper(substr($option, 0, 1)) . substr($option, 1, strlen(option));
        }

        printf(
                '<select id="%1$s" name="svgc_options[%1$s]">%2$s</select>', esc_attr($d[0]), $select_html
        );
    }

    public function checkbox_callback($d) {
        $checked = isset($this->captcha_options[$d[0]]) ? checked($this->captcha_options[$d[0]], True, False) : checked($this->by_key($this->dsettings, $d[0]), True, False);

        printf(
                '<input type="checkbox" id="%1$s" name="svgc_options[%1$s]" value="1" ' . $checked . ' />', esc_attr($d[0])
        );
    }

    public function captcha_preview_callback($d) {
        $this->svgc_get_captcha();
        print '<figure id="SVGCaptchaContainer"' . $this->svg_output . '<figcaption>The solution for the generated captcha is <strong style="color:red">' . $this->captcha_answer . '</strong></figcaption></div>';
    }

    public function custom_captcha_preview_callback($d) {
        $this->svgc_get_captcha();
        print '<figure id="SVGCaptchaContainer"' . $this->svg_output . '<figcaption>The solution for the generated captcha is <strong style="color:red">' . $this->captcha_answer . '</strong></figcaption></div>';
    }

    /**
     * Print the Section text
     */
    public function print_section_info() {
        print 'Enter your settings below: ';
    }

    public function by_key($arr, $key) {
        if (isset($arr[$key])) {
            return $arr[$key];
        } else
        if (is_array($arr)) {
            foreach ($arr as $value) {
                $this->by_key($value, $key);
            }
        }
    }

    /**
     * Encrypt data using AES_256 with CBC mode. Prepends IV on ciphertext.
     * 
     */
    public function svgc_encrypt($plaintext) {
        if (false == ($key = get_option('ccpatcha_encryption_key')))
            wp_die(__('Encryption error: could not retrieve encryption key from options database.', 'svg-captcha'));

        $key = base64_decode($key); /* Get binary key */

        if (32 != ($key_size = strlen($key)))
            wp_die(__('Encryption error: Invalid keysize.', 'svg-captcha'));

        # Create random IV to use with CBC mode.
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

        $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $plaintext, MCRYPT_MODE_CBC, $iv);

        # Prepend the IV on the ciphertext for decryption (Must not be confidential).
        $ciphertext = $iv . $ciphertext;

        # Encode such that it can be represented as astring.
        return base64_encode($ciphertext);
    }

    /**
     * Decrypt using AES_256 with the IV prepended on base64_encoded ciphertext.
     */
    public function svgc_decrypt($ciphertext) {
        if (false == ($key = get_option('ccpatcha_encryption_key')))
            wp_die(__('Decryption error: could not retrieve encryption key from options database.', 'svg-captcha'));

        $key = base64_decode($key); /* Get binary key */

        $ciphertext = base64_decode($ciphertext);

        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $iv = substr($ciphertext, 0, $iv_size);
        $ciphertext = substr($ciphertext, $iv_size);

        $plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $ciphertext, MCRYPT_MODE_CBC, $iv);
        return $plaintext;
    }



	/**
	 * Main SVG_Captcha Instance
	 *
	 * Ensures only one instance of SVG_Captcha is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object SVG_Captcha instance
	 * @see SVG_Captcha()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of SVG_Captcha is forbidden' ) ), esc_attr( $this->_version ) );

	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of SVG_Captcha is forbidden' ) ), esc_attr( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
