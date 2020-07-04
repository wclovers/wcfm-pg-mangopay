<?php

if( !function_exists( 'mangopay_log' ) ) {
	function mangopay_log( $message, $level = 'debug' ) {
		wcfm_create_log( $message, $level, 'mangopay' );
	}
}

if( !function_exists( 'get_mangopay_kyc_document_types' ) ) {
	function get_mangopay_kyc_document_types() {
		return array(
			\MangoPay\KycDocumentType::IdentityProof 			=> __( 'IDENTITY PROOF', 'wc-multivendor-marketplace' ),
			\MangoPay\KycDocumentType::AddressProof				=> __( 'ADDRESS PROOF', 'wc-multivendor-marketplace' ),
			\MangoPay\KycDocumentType::RegistrationProof		=> __( 'REGISTRATION PROOF', 'wc-multivendor-marketplace' ),
			\MangoPay\KycDocumentType::ArticlesOfAssociation	=> __( 'ARTICLES OF ASSOCIATION', 'wc-multivendor-marketplace' ),
			\MangoPay\KycDocumentType::ShareholderDeclaration	=> __( 'SHAREHOLDER DECLARATION', 'wc-multivendor-marketplace' ),
		);
	}
}

if( !function_exists( 'get_mangopay_kyc_document_types_required' ) ) {
	function get_mangopay_kyc_document_types_required( $user_type = 'natural' ) {
		switch( $user_type ) {
			case 'individual':
			case 'natural':
				return array(
					\MangoPay\KycDocumentType::IdentityProof 	=> __( 'IDENTITY PROOF', 'wc-multivendor-marketplace' ),
					\MangoPay\KycDocumentType::AddressProof		=> __( 'ADDRESS PROOF', 'wc-multivendor-marketplace' ),
				);
				break;
			
			case 'business':
				return array(
					\MangoPay\KycDocumentType::IdentityProof 			=> __( 'IDENTITY PROOF', 'wc-multivendor-marketplace' ),
					\MangoPay\KycDocumentType::RegistrationProof		=> __( 'REGISTRATION PROOF', 'wc-multivendor-marketplace' ),
					\MangoPay\KycDocumentType::ShareholderDeclaration	=> __( 'SHAREHOLDER DECLARATION', 'wc-multivendor-marketplace' ),
				);
				break;
		}
	}
}

if( !function_exists( 'get_mangopay_bank_types' ) ) {
	function get_mangopay_bank_types() {
		return array(
			'IBAN'	=> __( 'IBAN', 'wc-multivendor-marketplace' ),
			'GB'	=> __( 'GB', 'wc-multivendor-marketplace' ),
			'US'	=> __( 'US', 'wc-multivendor-marketplace' ),
			'CA'	=> __( 'CA', 'wc-multivendor-marketplace' ),
			'OTHER'	=> __( 'OTHER', 'wc-multivendor-marketplace' ),
		);
	}
}

if( !function_exists( 'get_mangopay_deposit_account_types' ) ) {
	function get_mangopay_deposit_account_types() {
		return array(
			'CHECKING'	=> __( 'CHECKING', 'wc-multivendor-marketplace' ),
			'SAVINGS'	=> __( 'SAVINGS', 'wc-multivendor-marketplace' ),
		);
	}
}