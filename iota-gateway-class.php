<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PayIOTA extends WC_Payment_Gateway {

	var $ipn_url;

	function __construct() {
		
		global $woocommerce;
		
		// global ID
		$this->id = "payiota";

		//set IPN
		$this->ipn_url   = add_query_arg( 'wc-api', 'PayIOTA', home_url( '/' ) );

		//payment listener/hook
		add_action( 'woocommerce_api_payiota', array( $this, 'check_ipn_response' ) );

		// Show Title
		$this->method_title = __( "PayIOTA.me IOTA Payment Gateway", 'payiota' );

		// Show Description
		$this->method_description = __( "PayIOTA.me IOTA Payment Gateway Plugin for WooCommerce <br/><br/> <strong>IPN URL:</strong> ".$this->ipn_url, 'payiota' );

		// vertical tab title
		$this->title = __( "PayIOTA.me IOTA Payment Gateway", 'payiota' );

		$this->has_fields = false;

		// setting defines
		$this->init_form_fields();

		// load time variable setting
		$this->init_settings();
		
		// Define user set variables
		$default_title = $this->get_option( 'title' );
		$default_desc = $this->get_option( 'description' );
		
		$this->title        = !empty( $default_title ) ? $default_title : "PayIOTA.me";
		$this->description  = !empty( $default_desc ) ? $default_desc : 'Pay with IOTA, a cryptocurrency. <a href="http://iota.org" target="_blank">What is IOTA?</a>';
		
		// further check of SSL if you want
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // Here is the  End __construct()

	public function get_icon()
	{
	    return apply_filters('woocommerce_gateway_icon', '<img width="301" src="https://payiota.me/resources/paynow.png" alt="Pay now with PayIOTA.me!"/>', $this->id);
	}

	
	// administration fields for specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable PayIOTA.me Plugin', 'payiota' ),
				'label'		=> __( 'Show IOTA as an option to customers during checkout', 'payiota' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'payiota' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This controls the title which users sees during checkout.', 'payiota' ),
				'default'	=> __( 'PayIOTA.me', 'payiota' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'payiota' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'This controls the description which users sees during checkout.', 'payiota' ),
				'default'	=> __( 'Pay with IOTA, a cryptocurrency. <a href="http://iota.org" target="_blank">What is Iota?</a>', 'payiota' ),
				'css'		=> 'max-width:450px;'
			),
			'api_key' => array(
				'title'		=> __( 'PayIOTA.me API Key', 'payiota' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Key provided by payiota.me when you signed up for an account.', 'payiota' ),
			),
			'verification_key' => array(
				'title'		=> __( 'PayIOTA.me Verification Key', 'payiota' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Verificatino Key provided by payiota.me when you signed up for an account.', 'payiota' ),
			),
			'currency' => array(
				'title'		=> __( '3 Letter Currency Code', 'payiota' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This can be any 3 letter currency code, or can be IOTA. All currencies will be converted to IOTA.', 'payiota' ),
				'default'	=> __( 'USD', 'payiota' ),
			)
		);		
	}
	
	// Response handled for payment gateway
	public function process_payment( $order_id ) {
		global $woocommerce;

		$customer_order = new WC_Order( $order_id );
		
		$customer_order->add_order_note( __( 'Awaiting IOTA payment. Order status changed to pending payment.', 'payiota' ) );

		// paid order marked
		//$customer_order->payment_complete();

		// this is important part for empty cart
		$woocommerce->cart->empty_cart();

		// Redirect to thank you page
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $customer_order ),
		);

	}
	
	// Validate fields
	public function validate_fields() {
		return true;
	}

	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

	function check_ipn_response() {
		include_once( 'iota_class.php' );
		$iota_obj = new Iota_Class();
		
		$address = sanitize_text_field($_POST["address"]);
		$order_id = sanitize_text_field($_POST["custom"]);
		$verification = sanitize_text_field($_POST["verification"]);
		$paid_iota = sanitize_text_field($_POST["paid_iota"]);
		$price_iota = sanitize_text_field($_POST["price_iota"]);
		//for more variables see documentation

		if (!is_numeric($paid_iota) or !is_numeric($price_iota) or strlen($verification) !== 128 or strlen($address) !== 90) {
			echo "Invalid data received.";
			die(1);
		}

		if ($verification !== $iota_obj->verification_key) {
			echo "Verification key failed to match received verification key.";
			die(1);
		}

		$order = new WC_Order( $order_id );
		if ( ! isset( $order->id ) ) {
			echo "Invalid Order ID received.";
			die(1);
		}
		
		if( $paid_iota >= $price_iota ){

			update_option("woo_iota_order_status_".$order_id, "processing");
			$order->update_status('processing');
			$order->add_order_note( __( 'IOTA payment Complete. Order status of order '.$order_id.' changed from Pending payment to Processing.', 'payiota' ) );
			$order->save();
			
			echo "Order ".$order_id." set to processing.";
			die(0);
			
		}else{
			echo "Paid IOTA amount is less than price IOTA amount.";
			die(1);
		}

	}

}