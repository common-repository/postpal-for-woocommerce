<?php
/**
 * Created by PostPal <www.postpal.eu>
 * User: Klemens Arro
 * Date: 14.02.16
 * Time: 15:53
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings for flat rate shipping.
 */
$settings = array(
    'enabled' => array(
        'title' 		=> __( 'Enable/Disable', 'woocommerce-postpal' ),
        'type' 			=> 'checkbox',
        'label' 		=> __( 'Enable PostPal shipping method', 'woocommerce-postpal' ),
        'default' 		=> 'yes',
    ),
    'api_key' => array(
        'title' 		=> __( 'API Key', 'woocommerce-postpal' ),
        'type' 			=> 'text',
        'description' 	=> __( 'You can generate the API Key from PostPal self-service', 'woocommerce-postpal' ),
        'desc_tip'		=> true
    ),
    'warehouse_code' => array(
        'title' 		=> __( 'Warehouse Code', 'woocommerce-postpal' ),
        'type' 			=> 'text',
        'description' 	=> __( 'You can setup the warehouse and get the key from PostPal self-service', 'woocommerce-postpal' ),
        'desc_tip'		=> true
    ),
    'cost' => array(
        'title' 		=> __( 'Cost', 'woocommerce-postpal' ),
        'type' 			=> 'text',
        'placeholder'	=> '',
        'description'	=> __( 'Price that you will ask from your customer', 'woocommerce-postpal' ),
        'default'		=> '5.49',
        'desc_tip'		=> true
    )
);

return $settings;
