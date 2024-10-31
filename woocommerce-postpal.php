<?php
/*
Plugin Name: PostPal for WooCommerce
Plugin URI: https://www.postpal.eu
Description: Connect your WooCommerce to PostPal on-demand courier platform
Version: 0.2
Author: Klemens Arro
Author URI: http://www.klemensarro.com
Text Domain: woocommerce-postpal
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    define('POSTPAL_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

    $WcPostPalShipping = null;

    function PostPal_load()
    {
        global $WcPostPalShipping;

        if ( ! class_exists( 'WC_PostPal_Shipping' ) ) {
            require( POSTPAL_PLUGIN_PATH . 'PostPal-SDK.php');
            require( POSTPAL_PLUGIN_PATH . 'WC_PostPal_Shipping.php' );
        }

        if($WcPostPalShipping == null)
            $WcPostPalShipping = new WC_PostPal_Shipping();
    }

    function PostPalWooCommerceInit()
    {
        global $WcPostPalShipping;

        PostPal_load();
        $WcPostPalShipping->setActionsAndFilters();

        if( is_admin() )
            $WcPostPalShipping->setAdminActionsAndFilters();
    }

    function PostPalPaymentCompleted( $orderId )
    {
        global $WcPostPalShipping;

        PostPal_load();
        $WcPostPalShipping->paymentCompleted( $orderId );
    }

    function PostPalOrderReview( $order )
    {
        global $WcPostPalShipping;

        PostPal_load();
        $WcPostPalShipping->orderReview( $order );
    }

    function PostPalAddWooCommerce( $methods )
    {
        $methods[] = 'WC_PostPal_Shipping';
        return $methods;
    }

    function PostPalAdminWooCommerceInit()
    {
        global $WcPostPalShipping;

        PostPal_load();
        $WcPostPalShipping->adminListeners();
    }

    function PostPalLoadGeneralPlugin()
    {
        load_plugin_textdomain( 'woocommerce-postpal', POSTPAL_PLUGIN_PATH . 'lang', basename( dirname( __FILE__ ) ) . '/lang' );
    }

    if( is_admin() ) {
        add_action( 'plugins_loaded', 'PostPalWooCommerceInit' );
        add_filter( 'woocommerce_shipping_init', 'PostPalAdminWooCommerceInit' );
    }

    add_filter( 'plugins_loaded', 'PostPalLoadGeneralPlugin' );
    add_filter( 'woocommerce_shipping_methods', 'PostPalAddWooCommerce' );
    add_action( 'woocommerce_shipping_init', 'PostPalWooCommerceInit' );
    add_action( 'woocommerce_payment_complete', 'PostPalPaymentCompleted' );
    add_action( 'woocommerce_order_details_after_order_table', 'PostPalOrderReview' );

    wp_enqueue_style( 'PostPal-styles', plugins_url( 'assets/style.css', __FILE__ ) );
}