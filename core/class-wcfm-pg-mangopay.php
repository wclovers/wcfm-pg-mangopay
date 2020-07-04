<?php

/**
 * WCFM PG MangoPay plugin core
 *
 * Plugin intiate
 *
 * @author 		WC Lovers
 * @package 	wcfm-pg-mangopay
 * @version   1.0.0
 */

class WCFM_PG_MangoPay {
	
	public $plugin_base_name;
	public $plugin_url;
	public $plugin_path;
	public $version;
	public $token;
	public $text_domain;
	public $mp;
	
	public function __construct($file) {

		$this->file = $file;
		$this->plugin_base_name = plugin_basename( $file );
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = WCFMpgmp_TOKEN;
		$this->text_domain = WCFMpgmp_TEXT_DOMAIN;
		$this->version = WCFMpgmp_VERSION;
		$this->mp = mpAccess::getInstance();
		
		add_action( 'wcfm_init', array( &$this, 'init' ), 10 );
	}
	
	function init() {
		global $WCFM, $WCFMre;
		
		// Init Text Domain
		$this->load_plugin_textdomain();

		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		
		add_filter( 'wcfm_marketplace_withdrwal_payment_methods', array( &$this, 'wcfmmp_custom_pg' ) );		
		add_filter( 'wcfm_marketplace_settings_fields_withdrawal_charges', array( &$this, 'wcfmmp_custom_pg_withdrawal_charges' ), 50, 3 );
		
		add_filter( 'wcfm_marketplace_settings_fields_billing', array( &$this, 'wcfmmp_custom_pg_vendor_setting' ), 50, 2 );

		add_filter( 'mangopay_vendor_role', array( &$this, 'set_mangopay_vendor_role' ) );
		add_filter( 'mangopay_vendors_required_class', array( &$this, 'set_mangopay_vendors_required_class' ) );

		// add_action( 'wcfm_vendor_settings_update', array( &$this, 'update_mangopay_settings' ), 10, 2 );
		add_action( 'wcfm_wcfmmp_settings_update', array( &$this, 'update_mangopay_settings' ), 10, 2 );
		
		// Load Gateway Class
		require_once $this->plugin_path . 'gateway/class-wcfmmp-gateway-mangopay.php';
		
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_base_name, $this->plugin_url . 'assets/css/wcfm-pg-mangopay.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_base_name, $this->plugin_url . 'assets/js/wcfm-pg-mangopay.js', array( 'jquery' ), $this->version, false );
	}
	
	public function wcfmmp_custom_pg( $payment_methods ) {
		$payment_methods[WCFMpgmp_GATEWAY] = __( WCFMpgmp_GATEWAY_LABEL, 'wcfm-pg-mangopay' );
		return $payment_methods;
	}
	
	public function wcfmmp_custom_pg_withdrawal_charges( $withdrawal_charges, $wcfm_withdrawal_options, $withdrawal_charge ) {
		$gateway_slug  = WCFMpgmp_GATEWAY;
		$gateway_label = __( WCFMpgmp_GATEWAY_LABEL, 'wcfm-pg-mangopay' ) . ' ';
		
		$withdrawal_charge_brain_tree = isset( $withdrawal_charge[$gateway_slug] ) ? $withdrawal_charge[$gateway_slug] : array();
		$payment_withdrawal_charges = array(  "withdrawal_charge_".$gateway_slug => array( 'label' => $gateway_label . __('Charge', 'wcfm-pg-mangopay'), 'type' => 'multiinput', 'name' => 'wcfm_withdrawal_options[withdrawal_charge]['.$gateway_slug.']', 'class' => 'withdraw_charge_block withdraw_charge_'.$gateway_slug, 'label_class' => 'wcfm_title wcfm_ele wcfm_fill_ele withdraw_charge_block withdraw_charge_'.$gateway_slug, 'value' => $withdrawal_charge_brain_tree, 'custom_attributes' => array( 'limit' => 1 ), 'options' => array(
			"percent" => array('label' => __('Percent Charge(%)', 'wcfm-pg-mangopay'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'attributes' => array( 'min' => '0.1', 'step' => '0.1') ),
			"fixed" => array('label' => __('Fixed Charge', 'wcfm-pg-mangopay'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'attributes' => array( 'min' => '0.1', 'step' => '0.1') ),
			"tax" => array('label' => __('Charge Tax', 'wcfm-pg-mangopay'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele', 'label_class' => 'wcfm_title wcfm_ele', 'attributes' => array( 'min' => '0.1', 'step' => '0.1'), 'hints' => __( 'Tax for withdrawal charge, calculate in percent.', 'wcfm-pg-mangopay' ) ),
		) ) );
		$withdrawal_charges = array_merge( $withdrawal_charges, $payment_withdrawal_charges );
		return $withdrawal_charges;
	}
	
	public function wcfmmp_custom_pg_vendor_setting( $vendor_billing_fields, $vendor_id ) {
		$gateway_slug  = WCFMpgmp_GATEWAY;
		
		$vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
		if( !$vendor_data ) $vendor_data = array();
				
		$settings = array();

		$settings['user_mp_status'] 	= isset( $vendor_data['payment'][$gateway_slug]['user_mp_status'] ) ? $vendor_data['payment'][$gateway_slug]['user_mp_status'] : '';
		$settings['user_business_type'] = isset( $vendor_data['payment'][$gateway_slug]['user_business_type'] ) ? $vendor_data['payment'][$gateway_slug]['user_business_type'] : '';
		$settings['birthday'] 			= isset( $vendor_data['payment'][$gateway_slug]['birthday'] ) ? $vendor_data['payment'][$gateway_slug]['birthday'] : '';
		$settings['nationality'] 		= isset( $vendor_data['payment'][$gateway_slug]['nationality'] ) ? $vendor_data['payment'][$gateway_slug]['nationality'] : '';
		$settings['kyc_details'] 		= isset( $vendor_data['payment'][$gateway_slug]['kyc_details'] ) ? $vendor_data['payment'][$gateway_slug]['kyc_details'] : array();
		$settings['bank_details'] 		= isset( $vendor_data['payment'][$gateway_slug]['bank_details'] ) ? $vendor_data['payment'][$gateway_slug]['bank_details'] : array();

		$vendor_user_billing_fields = array();

		$user_mp_status = get_user_meta( $vendor_id, 'user_mp_status', true ) ? get_user_meta( $vendor_id, 'user_mp_status', true ) : '';

		if( !$user_mp_status ) {
			if( 'either' === $this->mp->default_vendor_status ) {
				$vendor_user_billing_fields += array(
					$gateway_slug.'_user_mp_status' => array(
						'label' 		=> __('User Type', 'wc-multivendor-marketplace'),
						'type' 			=> 'select', 
						'options' 		=> array(
							'individual'	=> __( 'NATURAL', 'wc-multivendor-marketplace' ),
							'business'		=> __( 'BUSINESS', 'wc-multivendor-marketplace' ),
						),
						'name' 			=> 'payment['.$gateway_slug.'][user_mp_status]', 
						'class' 		=> 'wcfm-select wcfm_ele paymode_field paymode_'.$gateway_slug, 
						'label_class' 	=> 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
						'value' 		=> $settings['user_mp_status'],
						'custom_attributes'	=> array(
							'required'	=> 'required'
						),
					),
				);
			}

			if( 'either' === $this->mp->default_vendor_status || 'businesses' === $this->mp->default_vendor_status ) {
				if( 'either' == $this->mp->default_business_type ) {
					$vendor_user_billing_fields += array(
						$gateway_slug.'_user_business_type' => array(
							'label' 		=> __('Business Type', 'wc-multivendor-marketplace'),
							'type' 			=> 'select', 
							'options' 		=> array(
								'business'		=> __( 'BUSINESS', 'wc-multivendor-marketplace' ),
								'organisation'	=> __( 'ORGANIZATION', 'wc-multivendor-marketplace' ),
								'soletrader'	=> __( 'SOLETRADER', 'wc-multivendor-marketplace' ),
							),
							'name' 			=> 'payment['.$gateway_slug.'][user_business_type]', 
							'class' 		=> 'wcfm-select wcfm_ele paymode_field paymode_'.$gateway_slug, 
							'label_class' 	=> 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
							'value' 		=> $settings['user_business_type'],
							'custom_attributes'	=> array(
								'required'	=> 'required'
							),
						),
					);
				}
			}
		}

		$vendor_user_billing_fields += array(
			$gateway_slug.'_birthday' => array(
				'label' 		=> __('Birthday', 'wc-multivendor-marketplace'),
				'type'			=> 'datepicker',
				'name' 			=> 'payment['.$gateway_slug.'][birthday]', 
				'class' 		=> 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' 		=> $settings['birthday'],
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_nationality' => array(
				'label' 		=> __('Nationality', 'wc-multivendor-marketplace'),
				'type' 			=> 'select', 
				'options' 		=> WC()->countries->get_countries(),
				'name' 			=> 'payment['.$gateway_slug.'][nationality]', 
				'class' 		=> 'wcfm-select wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' 		=> $settings['nationality'],
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
		);

		// kyc fields
		$vendor_kyc_billing_fields = array(
			$gateway_slug.'_header_kyc' => array(
				'label' => __('KYC Details', 'wc-multivendor-marketplace'),
				'type'	=> 'title',
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug,
			),
			$gateway_slug.'_kyc_notice' => array(
				'type'			=> 'html',
				'class' 		=> 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' 		=> sprintf( __( '<span class="mangopay-kyc-notice">***For NATURAL/INDIVIDUAL user : Please upload %s document(s) i.e. %s<br/>***For BUSINESS user : Please upload %s document(s) i.e. %s<br/>%s</span>', 'wc-multivendor-marketplace' ), count( get_mangopay_kyc_document_types_required( 'natural' ) ), implode( ', ', get_mangopay_kyc_document_types_required( 'natural' ) ), count( get_mangopay_kyc_document_types_required( 'business' ) ), implode( ', ', get_mangopay_kyc_document_types_required( 'business' ) ), $user_mp_status ? sprintf( __( '***you are : %s user', 'wc-multivendor-marketplace' ), strtoupper( $user_mp_status ) ) : '' ),
			),
			$gateway_slug.'_kyc_details' => array(
				'name'			=> 'payment['.$gateway_slug.'][kyc_details]',
				'type' 			=> 'multiinput',
				'class' 		=> 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title wcfm_full_title paymode_field paymode_'.$gateway_slug, 
				'value' 		=> $settings['kyc_details'],
				'options' 		=> array(
					"type" 	=> array( 
						'label' 		=> __('Document Type', 'wc-multivendor-marketplace'), 
						'type' 			=> 'select', 
						'options' 		=> get_mangopay_kyc_document_types(), 
						'class' 		=> 'wcfm-select wcfm_ele field_type_options paymode_field paymode_'.$gateway_slug, 
						'label_class' 	=> 'wcfm_title paymode_field paymode_'.$gateway_slug,
					),
					'file' => array( 
						'label' 		=> __('Upload File', 'wc-multivendor-marketplace'),
						'type' 			=> 'upload', 
						'mime' 			=> 'Uploads', 
						'class' 		=> 'wcfm_ele', 
						'label_class' 	=> 'wcfm_title',
						'hints' 		=> __( 'please upload .pdf, .doc', 'wc-multivendor-marketplace'),
					),
				),
				'custom_attributes' => array( 
					'limit' => count( get_mangopay_kyc_document_types() ),
				),
			),
			$gateway_slug.'_upload_kyc' => array(
				'label' 		=> __('Submit KYC documents', 'wc-multivendor-marketplace'), 
				'name' 			=> $gateway_slug.'_upload_kyc', 
				'type' 			=> 'checkbox', 
				'class' 		=> 'wcfm-checkbox wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title paymode_field paymode_'.$gateway_slug, 
				'value' 		=> 'yes',
				'dfvalue' 		=> 'no',
				'hints' 		=> __( 'If this field is checked kyc files will be uploaded to mangopay account.<br/> P.S. remember to uncheck it after first time use to avoid multiple uploads', 'wc-multivendor-marketplace'),
			),
		);

		// common fields
		$vendor_bank_billing_fields = array(
			$gateway_slug.'_header_bank' => array(
				'label'	=> __('Bank Details', 'wc-multivendor-marketplace'),
				'type'	=> 'title',
				'class'	=> 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug,
			),
			$gateway_slug.'_vendor_account_type' 	=> array( 
				'label' 		=> __('Type', 'wc-multivendor-marketplace'),
				'name' 			=> 'payment['.$gateway_slug.'][bank_details][vendor_account_type]', 
				'type' 			=> 'select', 
				'options' 		=> get_mangopay_bank_types(), 
				'class' 		=> 'mangopay-type wcfm-select wcfm_ele field_type_options paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title paymode_field paymode_'.$gateway_slug,
				'value' 		=> isset( $settings['bank_details']['vendor_account_type'] ) ? $settings['bank_details']['vendor_account_type'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_name' => array(
				'label' 		=> __('Owner name', 'wc-multivendor-marketplace'), 
				'name' 			=> 'payment['.$gateway_slug.'][bank_details][vendor_account_name]', 
				'type'		 	=> 'text', 
				'class' 		=> 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' 		=> isset( $settings['bank_details']['vendor_account_name'] ) ? $settings['bank_details']['vendor_account_name'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_address1' => array(
				'label' => __('Owner address line 1', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_account_address1]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_account_address1'] ) ? $settings['bank_details']['vendor_account_address1'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_address2' => array(
				'label' => __('Owner address line 2', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_account_address2]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_account_address2'] ) ? $settings['bank_details']['vendor_account_address2'] : '',
			),
			$gateway_slug.'_vendor_account_city' => array(
				'label' => __('Owner city', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_account_city]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_account_city'] ) ? $settings['bank_details']['vendor_account_city'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_region' => array(
				'label' => __('Owner region', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_account_region]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_account_region'] ) ? $settings['bank_details']['vendor_account_region'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_postcode' => array(
				'label' => __('Owner postal code', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_account_postcode]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_account_postcode'] ) ? $settings['bank_details']['vendor_account_postcode'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_account_country' 	=> array( 
				'label' 		=> __('Owner country', 'wc-multivendor-marketplace'),
				'name' 			=> 'payment['.$gateway_slug.'][bank_details][vendor_account_country]', 
				'type' 			=> 'select', 
				'options' 		=> WC()->countries->get_countries(), 
				'class' 		=> 'wcfm-select wcfm_ele field_type_options paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title paymode_field paymode_'.$gateway_slug,
				'value' 		=> isset( $settings['bank_details']['vendor_account_country'] ) ? $settings['bank_details']['vendor_account_country'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
		);

		// IBAN fields
		$vendor_iban_billing_fields = array(
			$gateway_slug.'_vendor_iban' => array(
				'label' => __('IBAN', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_iban]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-iban wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_iban'] ) ? $settings['bank_details']['vendor_iban'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_bic' => array(
				'label' => __('BIC', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_bic]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-iban wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_bic'] ) ? $settings['bank_details']['vendor_bic'] : '',
			),
		);

		// GB fields
		$vendor_gb_billing_fields = array(
			$gateway_slug.'_vendor_gb_accountnumber' => array(
				'label' => __('Account Number', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_gb_accountnumber]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-gb wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_gb_accountnumber'] ) ? $settings['bank_details']['vendor_gb_accountnumber'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_sort_code' => array(
				'label' => __('Sort Code', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][sort_code]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-gb wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['sort_code'] ) ? $settings['bank_details']['sort_code'] : '',
			),
		);

		// US fields
		$vendor_us_billing_fields = array(
			$gateway_slug.'_vendor_us_accountnumber' => array(
				'label' => __('Account Number', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_us_accountnumber]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-us wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_us_accountnumber'] ) ? $settings['bank_details']['vendor_us_accountnumber'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_us_aba' => array(
				'label' => __('ABA', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_us_aba]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-us wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_us_aba'] ) ? $settings['bank_details']['vendor_us_aba'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_us_datype' 	=> array( 
				'label' 		=> __('Deposit Account Type', 'wc-multivendor-marketplace'),
				'name' 			=> 'payment['.$gateway_slug.'][bank_details][vendor_us_datype]', 
				'type' 			=> 'select', 
				'options' 		=> get_mangopay_deposit_account_types(), 
				'class' 		=> 'bank-type bank-type-us wcfm-select wcfm_ele field_type_options paymode_field paymode_'.$gateway_slug, 
				'label_class' 	=> 'wcfm_title paymode_field paymode_'.$gateway_slug,
				'value' 		=> isset( $settings['bank_details']['vendor_us_datype'] ) ? $settings['bank_details']['vendor_us_datype'] : '',
			),
		);

		// CA fields
		$vendor_ca_billing_fields = array(
			$gateway_slug.'_vendor_ca_bankname' => array(
				'label' => __('Bank Name', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ca_bankname]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-ca wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ca_bankname'] ) ? $settings['bank_details']['vendor_ca_bankname'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_ca_instnumber' => array(
				'label' => __('Institution Number', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ca_instnumber]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-ca wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ca_instnumber'] ) ? $settings['bank_details']['vendor_ca_instnumber'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_ca_branchcode' => array(
				'label' => __('Branch Code', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ca_branchcode]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-ca wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ca_branchcode'] ) ? $settings['bank_details']['vendor_ca_branchcode'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_ca_accountnumber' => array(
				'label' => __('Account Number', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ca_accountnumber]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-ca wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ca_accountnumber'] ) ? $settings['bank_details']['vendor_ca_accountnumber'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
		);

		// OTHER fields
		$vendor_other_billing_fields = array(
			$gateway_slug.'_vendor_ot_country' => array(
				'label' => __('Country', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ot_country]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-other wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ot_country'] ) ? $settings['bank_details']['vendor_ot_country'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
			$gateway_slug.'_vendor_ot_bic' => array(
				'label' => __('BIC', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ot_bic]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-other wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ot_bic'] ) ? $settings['bank_details']['vendor_ot_bic'] : '',
			),
			$gateway_slug.'_vendor_ot_accountnumber' => array(
				'label' => __('Account Number', 'wc-multivendor-marketplace'), 
				'name' => 'payment['.$gateway_slug.'][bank_details][vendor_ot_accountnumber]', 
				'type' => 'text', 
				'class' => 'bank-type bank-type-other wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => isset( $settings['bank_details']['vendor_ot_accountnumber'] ) ? $settings['bank_details']['vendor_ot_accountnumber'] : '',
				'custom_attributes'	=> array(
					'required'	=> 'required'
				),
			),
		);
		
		return array_merge( 
			$vendor_billing_fields, 
			$vendor_user_billing_fields, 
			$vendor_kyc_billing_fields,
			$vendor_bank_billing_fields,
			$vendor_iban_billing_fields,
			$vendor_gb_billing_fields,
			$vendor_us_billing_fields,
			$vendor_ca_billing_fields,
			$vendor_other_billing_fields
		);
		
	}

	public function set_mangopay_vendor_role( $role ) {
		return 'wcfm_vendor';
	}

	public function set_mangopay_vendors_required_class( $class_name ) {
		return 'WCFMmp';
	}

	public function update_mangopay_settings( $wp_user_id, $wcfm_settings_form ) {
		$gateway_slug  	= WCFMpgmp_GATEWAY;
		$vendor_data 	= get_user_meta( $wp_user_id, 'wcfmmp_profile_settings', true );

		if( 'either' === $this->mp->default_vendor_status ) {
			if( isset( $wcfm_settings_form['payment'][$gateway_slug]['user_mp_status'] ) ) {
				update_user_meta( $wp_user_id, 'user_mp_status', $wcfm_settings_form['payment'][$gateway_slug]['user_mp_status'] );
			}	
		}

		if( 'either' === $this->mp->default_vendor_status || 'businesses' === $this->mp->default_vendor_status ) {
			if( 'either' === $this->mp->default_business_type ) {
				if( isset( $wcfm_settings_form['payment'][$gateway_slug]['user_business_type'] ) ) {
					update_user_meta( $wp_user_id, 'user_business_type', $wcfm_settings_form['payment'][$gateway_slug]['user_business_type'] );
				}	
			}
		}

		if( isset( $wcfm_settings_form['payment'][$gateway_slug]['birthday'] ) ) {
			update_user_meta( $wp_user_id, 'user_birthday', $wcfm_settings_form['payment'][$gateway_slug]['birthday'] );
		}

		if( isset( $wcfm_settings_form['payment'][$gateway_slug]['nationality'] ) ) {
			update_user_meta( $wp_user_id, 'user_nationality', $wcfm_settings_form['payment'][$gateway_slug]['nationality'] );
		}

		$mp_user_id = $this->mp->set_mp_user( $wp_user_id );

		if( !$mp_user_id ) {
			mangopay_log( __( 'Can not create mangopay user, please make sure to fill up your profile & address fields such as Fisrt Name, Last Name, Email, Billing Country etc', 'wc-multivendor-marketplace' ), 'error' );
			return;
		}

		if( isset( $wcfm_settings_form['mangopay_upload_kyc'] ) && 'yes' == $wcfm_settings_form['mangopay_upload_kyc'] ) {

			$kyc_details = isset( $wcfm_settings_form['payment'][$gateway_slug]['kyc_details'] ) ? $wcfm_settings_form['payment'][$gateway_slug]['kyc_details'] : array();
			
			if( is_array( $kyc_details ) && !empty( $kyc_details ) ) {
				$kyc_details 	= wp_list_pluck( $kyc_details, 'file', 'type' );

				foreach( $kyc_details as $type => $file ) {
					$KycDocument = new \MangoPay\KycDocument();
					$KycDocument->Tag = "wp_user_id:".$wp_user_id;
    				$KycDocument->Type = $type;

    				try{
    				    $document_created = $this->mp->create_kyc_document( $mp_user_id, $KycDocument );
    				    $kycDocumentId = $document_created->Id;

					    if( $kycDocumentId ){
							$uploaded = $this->mp->create_kyc_page_from_file( $mp_user_id, $kycDocumentId, get_attached_file( $file ) );

							if( $uploaded ) {
					    		$KycDocument = new \MangoPay\KycDocument();
								$KycDocument->Id = $kycDocumentId;
								$KycDocument->Status = \MangoPay\KycDocumentStatus::ValidationAsked;
								$Result = $this->mp->update_kyc_document( $mp_user_id, $KycDocument );

								if( $Result ){
								    $data_meta['type'] = $type;
								    $data_meta['id_mp_doc'] = $kycDocumentId;
								    $data_meta['creation_date'] = $Result->CreationDate;
								    $data_meta['document_name'] = basename( get_attached_file( $file ) );
								    update_user_meta( $wp_user_id, 'kyc_document_'.$kycDocumentId, $data_meta );
								}
							}
					    }

    				} catch(MangoPay\Libraries\ResponseException $e) {
			    		mangopay_log( $e->GetMessage(), 'error' );
			    		$this->message['message'] = $e->GetMessage();
			    	} catch(MangoPay\Libraries\Exception $e) {
			    		mangopay_log( $e->GetMessage(), 'error' );
			    		$this->message['message'] = $e->GetMessage();
			    	}
				}
			}

			// we don't need this field value to be saved
			unset( $wcfm_settings_form['mangopay_upload_kyc'] );
		}

		$umeta_key = 'mp_account_id';
		if( !$this->mp->is_production() ) {
		    $umeta_key .= '_sandbox';
		}
		
		$existing_account_id = get_user_meta( $wp_user_id, $umeta_key, true );

		$bank_details 	= $wcfm_settings_form['payment'][$gateway_slug]['bank_details'];

		$type 		= isset( $bank_details['vendor_account_type'] ) ? $bank_details['vendor_account_type'] : '';
		$name 		= isset( $bank_details['vendor_account_name'] ) ? $bank_details['vendor_account_name'] : '';
		$address1 	= isset( $bank_details['vendor_account_address1'] ) ? $bank_details['vendor_account_address1'] : '';
		$address2 	= isset( $bank_details['vendor_account_address2'] ) ? $bank_details['vendor_account_address2'] : '';
		$city 		= isset( $bank_details['vendor_account_city'] ) ? $bank_details['vendor_account_city'] : '';
		$postcode 	= isset( $bank_details['vendor_account_postcode'] ) ? $bank_details['vendor_account_postcode'] : '';
		$region 	= isset( $bank_details['vendor_account_region'] ) ? $bank_details['vendor_account_region'] : '';
		$country 	= isset( $bank_details['vendor_account_country'] ) ? $bank_details['vendor_account_country'] : '';

		$account_types 	= mangopayWCConfig::$account_types;
		$account_type 	= $account_types[$type];
		$needs_update 	= false;
		$account_data 	= array();
		
		/** Record redacted bank account data in vendor's usermeta **/
		foreach( $account_type as $field => $c ) {
		    if( isset( $bank_details[$field] ) && $bank_details[$field] && !preg_match( '/\*\*/', $bank_details[$field] ) ) {
		        if( isset( $c['redact'] ) && $c['redact'] ) {
		            $needs_update = true;
		            list( $obf_start, $obf_end ) = explode( ',', $c['redact'] );
		            $strlen = strlen( $bank_details[$field] );
		           
		            /**
		             * if its <=5 characters, lets just redact the whole thing
		             * @see: https://github.com/Mangopay/wordpress-plugin/issues/12
		             */
		            if( $strlen <= 5 ) {
		                $to_be_stored = str_repeat( '*', $strlen );
		               
		            } else {
		                $obf_center = $strlen - $obf_start - $obf_end;
		                if( $obf_center < 2 ) {
		                    $obf_center = 2;
		                }
		                $to_be_stored = substr( $bank_details[$field], 0, $obf_start ) .
		                    str_repeat( '*', $obf_center ) .
		                    substr( $bank_details[$field], -$obf_end, $obf_end );
		            }
		        } else {
		            if( get_user_meta( $wp_user_id, $field, true ) != $bank_details[$field] ) {
		                $needs_update = true;
		            }
		            $to_be_stored = $bank_details[$field];
		        }
		        $wcfm_settings_form['payment'][$gateway_slug]['bank_details'][$field] = $to_be_stored;
		        update_user_meta( $wp_user_id, $field, $to_be_stored );
		        $account_data[$field] = $bank_details[$field];
		    }
		}

        /** Record clear text bank account data in vendor's usermeta **/
        $account_clear_data = array(
			'headquarters_addressline1',
			'headquarters_addressline2',
			'headquarters_city',
			'headquarters_region',
			'headquarters_postalcode',
			'headquarters_country',
            'vendor_account_type',
            'vendor_account_name',
            'vendor_account_address1',
            'vendor_account_address2',
            'vendor_account_city',
            'vendor_account_postcode',
            'vendor_account_region',
            'vendor_account_country'
        );
        foreach( $account_clear_data as $field ) {
            /** update_user_meta() returns "false" if the value is unchanged **/
            if(isset($bank_details[$field]) && update_user_meta( $wp_user_id, $field, $bank_details[$field] ) ){
                $needs_update = true;
            }
        }

        if( $needs_update ) {
        	try {
	        	$mp_account_id = $this->mp->save_bank_account( 
	        		$mp_user_id,
	        		$wp_user_id,
	        		$existing_account_id,
	        		$type,
	        		$name, 
	        		$address1, 
	        		$address2, 
	        		$city, 
	        		$postcode, 
	        		$region,
	        		$country, 
	        		$account_data,
	        		$account_types
	        	);

	        	update_user_meta( $wp_user_id, $umeta_key, $mp_account_id );

	        } catch(MangoPay\Libraries\ResponseException $e) {
	    		mangopay_log( $e->GetMessage(), 'error' );
	    		$this->message['message'] = $e->GetMessage();
	    	} catch(MangoPay\Libraries\Exception $e) {
	    		mangopay_log( $e->GetMessage(), 'error' );
	    		$this->message['message'] = $e->GetMessage();
	    	}
        }

        update_user_meta( $wp_user_id, 'wcfmmp_profile_settings', $wcfm_settings_form );

	}

	
	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'wcfm-pg-mangopay' );
		
		//load_plugin_textdomain( 'wcfm-tuneer-orders' );
		//load_textdomain( 'wcfm-pg-mangopay', WP_LANG_DIR . "/wcfm-pg-mangopay/wcfm-pg-mangopay-$locale.mo");
		load_textdomain( 'wcfm-pg-mangopay', $this->plugin_path . "lang/wcfm-pg-mangopay-$locale.mo");
		load_textdomain( 'wcfm-pg-mangopay', ABSPATH . "wp-content/languages/plugins/wcfm-pg-mangopay-$locale.mo");
	}
	
	public function load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}
}