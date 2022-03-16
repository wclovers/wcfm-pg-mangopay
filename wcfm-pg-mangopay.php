<?php
/**
 * Plugin Name: WCFM Marketplace Vendor Payment - MangoPay
 * Plugin URI: https://wclovers.com/product/woocommerce-multivendor-membership
 * Description: WCFM Marketplace mangopay vendor payment gateway 
 * Author: WC Lovers
 * Version: 1.0.0
 * Author URI: https://wclovers.com
 *
 * Text Domain: wcfm-pg-mangopay
 * Domain Path: /lang/
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.0
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if(!defined('WCFM_TOKEN')) return;
if(!defined('WCFM_TEXT_DOMAIN')) return;

if ( ! class_exists( 'WCFMpgmp_Dependencies' ) )
	require_once 'helpers/class-wcfm-pg-mangopay-dependencies.php';

if( !WCFMpgmp_Dependencies::woocommerce_plugin_active_check() )
	return false;

if( !WCFMpgmp_Dependencies::wcfm_plugin_active_check() )
	return false;

if( !WCFMpgmp_Dependencies::wcfmmp_plugin_active_check() )
	return false;

if( !WCFMpgmp_Dependencies::wc_mangopay_plugin_active_check() )
	return false;

require_once 'helpers/wcfm-pg-mangopay-core-functions.php';
require_once 'wcfm-pg-mangopay-config.php';

add_action( 'woocommerce_loaded', 'load_core_class' );
function load_core_class() {
	if(!class_exists('WCFM_PG_MangoPay')) {
		include_once( 'core/class-wcfm-pg-mangopay.php' );
		global $WCFM, $WCFMpgmp, $WCFM_Query;
		$WCFMpgmp = new WCFM_PG_MangoPay( __FILE__ );
		$GLOBALS['WCFMpgmp'] = $WCFMpgmp;
	}
}