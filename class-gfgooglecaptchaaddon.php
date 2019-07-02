<?php

GFForms::include_addon_framework();

class GFGoogleCaptchaAddOn extends GFAddOn {

	protected $_version = GF_GOOGLE_CAPTCHA_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'gravityformsgooglecaptcha';
	protected $_path = 'gravityformsgooglecaptcha/index.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Google Captcha V3 Add-On';
	protected $_short_title = 'Google Captcha V3';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFGoogleCaptchaAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFGoogleCaptchaAddOn();
		}

		return self::$_instance;
	}

	public function pre_init() {
	    parent::pre_init();

	    if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
	        require_once( 'fields/class-gf-recaptcha-score-field.php' );
	    }
	}

	/**
	 * Fire the GF hook to load Google holder
	 */
	public function init() {
		parent::init();
		add_filter( 'body_class', array( $this, 'set_body_class_name') );
		add_action( 'wp_ajax_check_google_token_request', array( $this, 'check_google_token_request'), 99 );
		add_action( 'wp_ajax_nopriv_check_google_token_request', array( $this, 'check_google_token_request' ), 99 );
		add_filter( 'gform_form_tag', array( $this, 'gf_google_captcha' ), 10, 2 );
	}

	/**
	 * Loop through all forms and add a new div tag for Google scripts
	 * @param  string $form_tag The tag
	 * @param  [type] $form     The form
	 * @return string           The form string with the new code pre-pended
	 */
	function gf_google_captcha( $form_tag, $form  ){
		// Add Google Captcha div for holding async code
		$form_tag = $form_tag . '<div class="gf-recaptcha-div"></div>';
		return $form_tag;
	}

	public function check_google_token_request() {

		// Check nonce and referrer
		check_ajax_referer( 'google-captcha', 'security' );

		$token = isset( $_POST['token'] ) ? filter_var( trim( $_POST['token'] ), FILTER_SANITIZE_STRING) : false;

		if(!$token){ die; }

		$secret_key = $this->get_plugin_setting( 'google_site_secret_key');

		$url = 'https://www.google.com/recaptcha/api/siteverify';
	    $data = array(
			'secret' 	=> $secret_key,
			'response' 	=> $token
		);

		$options = array(
	      'http' => array(
	        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
	        'method'  => 'POST',
	        'content' => http_build_query($data)
	      )
	    );

	    $context  = stream_context_create($options);
	    $response = file_get_contents($url, false, $context);
	    $responseKeys = json_decode($response,true);

	    header('Content-type: application/json');

		$recaptchaSuccess = $responseKeys["success"] ? true : false;

		echo json_encode(array(
			'success' => $recaptchaSuccess,
			'score' => $responseKeys['score'],
		));

		die;
	}

	// If user has checked "Hide label" checkbox, add
	// .hide-recaptcha to the body classlist
	function set_body_class_name($classes){
		$hide_label = $this->get_plugin_setting('google_recaptcha_badge_visibility');
		if($hide_label){
			$classes[] = 'hide-recaptcha';
		}
		return $classes;
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {

		$key = trim($this->get_plugin_setting( 'google_site_key'));

		$scripts = array(
			array(
				'handle'  => 'gfGoogleCaptchaScriptFrontend',
				'src'     => $this->get_base_url() . '/js/frontend.js',
				'version' => $this->_version,
				'deps'    => array('googleRecaptcha', 'axios', 'qs-script'),
				'strings' => array(
					'key'  		=> $key,
					'ajaxurl' 	=> admin_url( 'admin-ajax.php' ),
					'security' 	=> wp_create_nonce('google-captcha'),
				),
				'enqueue' => array(
	                array( $this, 'requires_script' )
	            )
			),
			array(
				'handle'  => 'axios',
				'src'     => 'https://unpkg.com/axios/dist/axios.min.js',
				'version' => $this->_version,
				'deps'    => array(),
				'enqueue' => array(
	                array( $this, 'requires_script' )
	            )
			),
			array(
				'handle'  => 'qs-script',
				'src'     => 'https://unpkg.com/qs/dist/qs.js',
				'version' => $this->_version,
				'deps'    => array(),
				'enqueue' => array(
	                array( $this, 'requires_script' )
	            )
			),
			array(
				'handle'  => 'googleRecaptcha',
				'src'     => 'https://www.google.com/recaptcha/api.js?render=' . $key,
				'version' => null,
				'deps'    => array(),
				'enqueue' => array(
	                array( $this, 'requires_script' )
	            )
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	// Set where the scripts are enqueued
	function requires_script(){
		if(!is_admin()){
			return true;
		}
		return false;
	}

	/**
	 * Return the styles which should be enqueued.
	 *
	 * @return array
	 */

	public function styles() {
	    $styles = array(
	        array(
	            'handle'  => 'gfGoogleCaptchaStylesFrontend',
	            'src'     => $this->get_base_url() . '/css/frontend.css',
	            'version' => $this->_version,
	            'enqueue' => array(
	                array( $this, 'requires_script' )
	            )
	        )
	    );

	    return array_merge( parent::styles(), $styles );
	}


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */

	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Google Recaptcha Keys', 'gfgooglecaptchaaddon' ),
				'fields' => array(
					array(
						'label'             => esc_html__( 'Site Key', 'gfgooglecaptchaaddon' ),
						'type'              => 'text',
						'name'              => 'google_site_key',
						'tooltip'           => esc_html__( 'This is the key for the client side. Users can see this.', 'gfgooglecaptchaaddon' ),
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'label'             => esc_html__( 'Site Secret Key', 'gfgooglecaptchaaddon' ),
						'type'              => 'text',
						'name'              => 'google_site_secret_key',
						'tooltip'           => esc_html__( 'Keep secret! This is the key for the server site.', 'gfgooglecaptchaaddon' ),
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'label'             => esc_html__( 'reCaptcha Badge', 'gfgooglecaptchaaddon' ),
						'type'              => 'checkbox',
						'name'              => 'google_recaptcha_badge_visibility',
						'description' 		=> 'If you hide the badge, Googles T&Cs must be visible in the user flow. See the <a target="_blank" href="https://developers.google.com/recaptcha/docs/faq" title="This link opens the Google FAQs in a new window">FAQs</a> for more information.',
						'tooltip'           => esc_html__( 'Hide the Google reCaptcha badge from your site', 'gfgooglecaptchaaddon' ),
						'choices' => array(
			                array(
			                    'label'         => esc_html__( 'Hide', 'gfgooglecaptchaaddon' ),
			                    'name'          => 'google_recaptcha_badge_visibility',
			                    'default_value' => 0,
			                ),
						),
					),
				)
			)
		);
	}

}
