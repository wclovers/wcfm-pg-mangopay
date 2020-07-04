<?php

if (!defined('ABSPATH')) {
    exit;
}

class WCFMmp_Gateway_MangoPay extends WCFMmp_Abstract_Gateway {

	public $id;
	public $message = array();
	public $gateway_title;
	public $payment_gateway;
	public $withdrawal_id;
	public $vendor_id;
	public $withdraw_amount = 0;
	public $withdraw_charges = 0;
	public $currency;
	public $transaction_mode;
	public $test_mode = false;
	public $client_id;
	public $client_secret;
	public $mp;
	
	public function __construct() {
		
		$this->id = WCFMpgmp_GATEWAY;
		$this->gateway_title = __( WCFMpgmp_GATEWAY_LABEL, 'wcfm-pg-mangopay' );
		$this->payment_gateway = $this->id;
		$this->mp = mpAccess::getInstance();

	}
	
	public function gateway_logo() { 
		global $WCFMpgmp;
		return $WCFMpgmp->plugin_url . 'assets/images/'.$this->id.'.png';
	}
	
	public function process_payment( $withdrawal_id, $vendor_id, $withdraw_amount, $withdraw_charges, $transaction_mode = 'auto' ) {
	
		global $WCFM, $WCFMmp;
		
		$this->withdrawal_id    = $withdrawal_id;
		$this->vendor_id        = $vendor_id;
		$this->withdraw_amount  = $withdraw_amount;
		$this->withdraw_charges = $withdraw_charges;
		$this->currency         = get_woocommerce_currency();
		$this->transaction_mode = $transaction_mode;

		$payout_amount = 0;
		$payout_fees = 0;

		if ( $this->validate_request() ) {
			// Updating withdrawal meta
			$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'withdraw_amount', $this->withdraw_amount );
			$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'currency', $this->currency );
			$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'recived_by_user', $this->vendor_id );

			$mp_vendor_id	= $this->mp->set_mp_user( $this->vendor_id );
			$wallets 		= $this->mp->set_mp_wallet( $mp_vendor_id );

			$transfer_list = array();

			// get all commissions mapped to orders from $withdrawl_id
			$withdrawal_info = $this->get_withdrawal_info( $withdrawal_id );

			// get mangopay transaction info from each order mapped to each commission_id
			// get vendor commission amount for each commission_id(i.e. marketplace_order_id)
			// then create list of mangopay wallet transfer needed 
			foreach( $withdrawal_info as $commission_id => $order_id ) {
				$order = wc_get_order( $order_id );

				$commission_details = $this->get_commission_details( $commission_id );
				$gross_sales_total = $WCFMmp->wcfmmp_commission->wcfmmp_get_commission_meta( $commission_id, 'gross_sales_total' );

				$fees = $gross_sales_total - $commission_details->total_commission;

				$transaction_id = get_post_meta( $order_id, 'mp_transaction_id', true );

				if( $transaction_id ) {
					$transfer_list[$commission_id] = array(
						'order_id'			=> $order_id,
						'mp_transaction_id'	=> $transaction_id,
						'wp_user_id'		=> $order->get_customer_id(),
						'vendor_id'			=> $this->vendor_id,
						'mp_amount'			=> $gross_sales_total, // total transfer amount
						'mp_fees'			=> $fees, // goes to admin wallet
						'mp_currency'		=> $this->currency,
					);
				}
			}

			// transfer commission amount(for each commission_id) from customer wallet to vendor wallet
			$this->process_mangopay_transfers( $transfer_list );

			// PayOut withdrawl amount to vendor bank a/c
			$mp_payout = $this->process_mangopay_payout();

			// Verify PayOut for success status
			return $this->verify_mangopay_payout( $mp_payout->Id );

		} else {
			return array( $this->message );
		}
	}
	
	public function validate_request() {
		global $WCFMmp;

		$mp_user_id = $this->mp->set_mp_user( $this->vendor_id );

		// mangopay environment set?
		if( ! $this->mp->connection_test() ) {
			$this->message['message'] = __( 'Mangopay setting is not configured properly please contact site administrator', 'wc-multivendor-marketplace' );
			mangopay_log( $this->message['message'], 'error' );

			return false;
		}

		// kyc valid?
		if( ! $this->mp->test_vendor_kyc( $mp_user_id ) ) {
			$this->message['message'] = __( 'Mangopay KYC not verified', 'wc-multivendor-marketplace' );
			mangopay_log( $this->message['message'], 'error' );
			
			return false;
		} else if( 'no_count_mp_found' === $this->mp->test_vendor_kyc( $mp_user_id ) ) {
			$this->message['message'] = __( 'Mangopay user not set', 'wc-multivendor-marketplace' );
			mangopay_log( $this->message['message'], 'error' );
			
			return false;
		}

		// bank a/c set?
		$umeta_key = 'mp_account_id';
		if( !$this->mp->is_production() ) {
		    $umeta_key .= '_sandbox';
		}
		
		$existing_account_id = get_user_meta( $this->vendor_id, $umeta_key, true );

		if( ! $existing_account_id ) {
			$this->message['message'] = __( 'Mangopay Bank Account not set properly', 'wc-multivendor-marketplace' );
			mangopay_log( $this->message['message'], 'error' );

			return false;
		}
		
		return parent::validate_request();
	}

	private function get_withdrawal_info( $withdrawal_id ) {
		global $wpdb;
		
		$data = array();

		$commission_ids = $wpdb->get_var( $wpdb->prepare( "SELECT commission_ids from {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %s", $withdrawal_id ) );

		$commission_ids = explode( ',', $commission_ids );

		foreach( $commission_ids as $commission_id ) {
			$data[$commission_id] = $wpdb->get_var( $wpdb->prepare( "SELECT order_id from {$wpdb->prefix}wcfm_marketplace_orders WHERE ID = %s", $commission_id ) );
		}

		return $data;
	}

	private function get_commission_details( $commission_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * from {$wpdb->prefix}wcfm_marketplace_orders WHERE ID = %s", $commission_id ) );
	}

	private function process_mangopay_transfers( $transfer_list = array() ) {
		foreach( $transfer_list as $commission_id => $transfer_data ) {
			if( ! $this->is_mangopay_transfer_completed( $commission_id ) ) {
			    try {
					$Result = $this->mp->wallet_trans( 
						$transfer_data['order_id'],
						$transfer_data['mp_transaction_id'],
						$transfer_data['wp_user_id'],
						$transfer_data['vendor_id'],
						$transfer_data['mp_amount'],
						$transfer_data['mp_fees'],
						$transfer_data['mp_currency']
					);

					if( MangoPay\TransactionStatus::Succeeded == $Result->Status ) {
						// update marketplace_order transfer complete (use it to avoid duplicate transfer)
						$this->mark_mangopay_transfer_completed( $commission_id );
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
	}

	private function is_mangopay_transfer_completed( $commission_id ) {
		global $WCFMmp;

		return $WCFMmp->wcfmmp_commission->wcfmmp_get_commission_meta( $commission_id, 'mangopay_transfer_completed' );
	}

	private function mark_mangopay_transfer_completed( $commission_id ) {
		global $WCFMmp;

		return $WCFMmp->wcfmmp_commission->wcfmmp_update_commission_meta( $commission_id, 'mangopay_transfer_completed', true );
	}

	private function process_mangopay_payout() {

		$umeta_key = 'mp_account_id';
        if( !$this->mp->is_production() ) {
            $umeta_key .= '_sandbox';
        }

        $mp_account_id = get_user_meta( $this->vendor_id, $umeta_key, true );

		try {
			$result = $this->mp->payout( 
				$this->vendor_id, 
				$mp_account_id, 
				0, // order_id, used my MANGOPAY WooCommerce plugin, (we don't have order_id here)
				$this->currency, 
				( $this->withdraw_amount + $this->withdraw_charges ), // total PayOut amount
				$this->withdraw_charges // amount goes to admin wallet
			);

		} catch(MangoPay\Libraries\ResponseException $e) {
			mangopay_log( $e->GetMessage(), 'error' );
			$this->message['message'] = $e->GetMessage();
		} catch(MangoPay\Libraries\Exception $e) {
			mangopay_log( $e->GetMessage(), 'error' );
			$this->message['message'] = $e->GetMessage();
		}

		return $result;
	}

	private function verify_mangopay_payout( $payout_id ) {
		$response = array();

		try {
			$PayOut = $this->mp->get_payout( $payout_id );
		} catch(MangoPay\Libraries\ResponseException $e) {
			mangopay_log( $e->GetMessage(), 'error' );
			$this->message['message'] = $e->GetMessage();
		} catch(MangoPay\Libraries\Exception $e) {
			mangopay_log( $e->GetMessage(), 'error' );
			$this->message['message'] = $e->GetMessage();
		}

		switch( $PayOut->Status ) {
			case \MangoPay\PayOutStatus::Failed:
				$response = array(
					'message' => array(
						'message' => $PayOut->ResultMessage,
					)
				);
				break;

			case \MangoPay\PayOutStatus::Created:
				$response = array(
					'message' => array(
						'message' => $PayOut->ResultMessage,
					)
				);
				break;

			case \MangoPay\PayOutStatus::Succeeded:
				$response = array(
					'status' => 'success',
					'message' => array(
						'message' => $PayOut->ResultMessage,
					)
				);
				break;
		}

		return $response;
	}
}