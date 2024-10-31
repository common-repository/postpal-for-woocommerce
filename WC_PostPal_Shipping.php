<?php
/**
 * Created by PostPal <www.postpal.eu>
 * User: Klemens Arro
 * Date: 28.07.16
 * Time: 18:11
 */

class WC_PostPal_Shipping extends WC_Shipping_Method {

    private $PostPal = null;

    /**
     * Constructor for your shipping class
     *
     * @access public
     */
    public function __construct()
    {
        $this->id                 = 'postpal_shipping_woocommerce';
        $this->method_title       = __( 'PostPal Shipping', 'woocommerce-postpal' );
        $this->method_description = __( 'Connect your WooCommerce to PostPal on-demand shipping platform', 'woocommerce-postpalß' );

        $this->enabled            = 'yes';
        $this->title              = 'PostPal';

        $this->init();
    }

    function isSetupCompleted()
    {
        if($this->get_option( 'api_key' ) == null)
            return false;

        if($this->get_option( 'warehouse_code' ) == null)
            return false;

        return true;
    }

    /**
     * @access public
     * @return void
     */
    function init()
    {
        $this->initPostPal();
        $this->init_form_fields();
        $this->init_settings();
    }

    function setActionsAndFilters()
    {
        //add_action( 'woocommerce_after_shipping_rate', array( $this, 'cartDeliveryTime' ) );

        add_action( 'woocommerce_after_checkout_validation', array( $this, 'afterCheckoutValidation' ) );
        add_action( 'woocommerce_review_order_after_shipping', array( $this, 'reviewOrderDeliveryTime' ) );
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'reviewOrderDeliveryTime' ) );
        add_action( 'woocommerce_payment_complete', array( $this, 'paymentCompleted' ) );
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    function setAdminActionsAndFilters()
    {
        add_action( 'add_meta_boxes', array( $this, 'addAdminOrderBox' ) );

        $this->adminNotificationsListener();
    }

    function adminListeners()
    {
        if( !is_admin() )
            return false;

        $this->listenAdminOrder();
    }

    private function initPostPal()
    {
        $this->PostPal = new PostPal_SDK();
        $this->PostPal->setAPIKey($this->get_option('api_key'));
        $this->PostPal->setWarehouseCode($this->get_option('warehouse_code'));
        $this->PostPal->setPrice($this->get_option('cost'));
        $this->PostPal->setModule('WordPress - WooCommerce');
    }

    /**
     * Initialize Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = include( 'settings.php' );
    }

    public function isSelected()
    {
        if( ! $this->is_available() )
            return false;

        $chosenMethods = WC()->session->get( 'chosen_shipping_methods' );

        if( is_array($chosenMethods) && in_array($this->id, $chosenMethods) )
            return true;

        return false;
    }

    public function is_available( $package = array() )
    {
        if ($this->enabled == "no")
            return false;

        if (!$this->isSetupCompleted())
            return false;

        if (isset($package['contents']))
        {
            $totalWeight = 0;
            foreach ($package['contents'] as $item_id => $values)
            {
                $_product = $values['data'];

                if($_product->needs_shipping() && !empty($_product->weight))
                    $totalWeight += $_product->weight;
            }

            if($totalWeight > 10500)
                return false;
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true, $package);
    }

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping( $package = array() )
    {
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->get_option( 'cost' )
        );

        $this->add_rate( $rate );
    }

    public function reviewOrderDeliveryTime()
    {
        if ( ! $this->isSelected() )
            return false;

        $estimation = $this->PostPal->estimation();

        if(!isset($estimation->status) || $estimation->status != true)
            return false;

        ?>
        <tr class="PostPal-cart-eta">
            <td>
                <a href="http://www.postpal.ee" target="_blank">
                    <img src="<?php echo plugins_url( 'assets/PostPal_logo.png', __FILE__ ); ?>" alt="PostPal" />
                </a>
            </td>
        	<td><?php
                printf(__('If ordered now, delivery would be completed at %s approximately %s', 'woocommerce-postpal'),
                    $this->getDeliveryTimeText( $estimation->estimate ), date('H:i', strtotime($estimation->estimate))
                ); ?>
            </td>
        </tr>
        <?php
    }

    public function cartDeliveryTime( $method, $index )
    {
        if ( $method->id != $this->id )
            return false;

        $estimation = $this->PostPal->estimation();

        if(!isset($estimation->status) || $estimation->status != true)
            return false;

        ?>
        <div class="pickup_time">
            <?php
                printf( __('If ordered now, delivery would be completed at %s approximately %s', 'woocommerce-postpal'),
                    $this->getDeliveryTimeText( $estimation->estimate ), date('d.m.Y H:i', strtotime($estimation->estimate))
                );
            ?>
        </div>
        <?php
    }

    private function getDeliveryTimeText( $estimated )
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Tallinn');

        $estimateStamp = strtotime($estimated);
        $estimatedDate = date('Ymd', $estimateStamp);

        $day = date('l', $estimateStamp);

        if($estimatedDate == date('Ymd'))
            $returnDay = __('Today', 'woocommerce-postpal');

        elseif($estimatedDate == date('Ymd', strtotime('tomorrow')))
            $returnDay = __('Tomorrow', 'woocommerce-postpal');

        elseif($estimatedDate <= date('Ymd', strtotime('today +5 days')))
            $returnDay = __($day);

        else
            $returnDay = date('d.m.Y', $estimateStamp);

        date_default_timezone_set($originalTimezone);

        return $returnDay;
    }

    public function afterCheckoutValidation( $posted )
    {
        if( ! $this->isSelected() )
            return false;

        $orderDetails = $this->getShippingDetails( $posted );
        $validate = $this->PostPal->validate( $orderDetails );

        if( is_array($validate) && isset($validate['error']) )
        {
            wc_add_notice( __('PostPal shipping plugin is not configured correctly', 'woocommerce-postpal'), 'error' );
            return false;
        }

        if( isset($validate->status) && $validate->status == true )
            return true;

        if( isset($validate->errors) && count($validate->errors) > 0 )
        {
            foreach ($validate->errors as $key => $error)
                $this->addOrderValidationError( $key, $error );
        }
    }

    private function addOrderValidationError( $key, $error, $justReturn = false )
    {
        $errorText = false;

        if($key == 'token' || $key == 'warehouse')
        {
            $errorText = __('PostPal shipping plugin is not configured correctly', 'woocommerce-postpal');
        }
        elseif($error->Code == '201')
        {
            $errorText = __('This shipping address was not found', 'woocommerce-postpal');
        }
        elseif($error->Code == '202')
        {
            $errorText = __('This shipping address is out of PostPal coverage area', 'woocommerce-postpal');
        }
        elseif($error->Code == '001' && ($key == 'destinationFirstName' ||
                                         $key == 'destinationLastName' || $key == 'destinationFullName'))
        {
            $errorText = __('Name is not entered', 'woocommerce-postpal');
        }
        elseif($error->Code == '001' && $key == 'destinationAddress')
        {
            $errorText = __('Address is not entered', 'woocommerce-postpal');
        }
        elseif($error->Code == '001' && $key == 'destinationPhone')
        {
            $errorText = __('Phone number is not entered', 'woocommerce-postpal');
        }
        elseif($error->Code == '001' && $key == 'packageSize')
        {
            $errorText = __('Package size is not entered', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && ($key == 'destinationFirstName' ||
                                         $key == 'destinationLastName' || $key == 'destinationFullName'))
        {
            $errorText = __('Name is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationCompany')
        {
            $errorText = __('Company name is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationEmail')
        {
            $errorText = __('Email is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationApartment')
        {
            $errorText = __('Second shipping address field is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationAddress')
        {
            $errorText = __('Shipping address is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationLocality')
        {
            $errorText = __('City in shipping address is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationCountry')
        {
            $errorText = __('Country in shipping address is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationPostalCode')
        {
            $errorText = __('Postal code in shipping address is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'destinationPhone')
        {
            $errorText = __('Phone number is not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'notes')
        {
            $errorText = __('Order comments are not correct', 'woocommerce-postpal');
        }
        elseif($error->Code == '002' && $key == 'packageSize')
        {
            $errorText = __('Package size is not correct', 'woocommerce-postpal');
        }

        if( $justReturn == true )
            return $errorText;

        if( $error != false )
            wc_add_notice( $errorText, 'error' );
    }

    private function getShippingDetails( $posted )
    {
        if( $posted['ship_to_different_address'] == true )
        {
            return array(
                'firstName' => $posted['shipping_first_name'],
                'lastName' => $posted['shipping_last_name'],
                'company' => $posted['shipping_company'],
                'email' => $posted['billing_email'],
                'phone' => $posted['billing_phone'],
                'country' => $posted['shipping_country'],
                'address' => $posted['shipping_address_1'],
                'apartment' => $posted['shipping_address_2'],
                'locality' => $posted['shipping_city'],
                'state' => $posted['shipping_state'],
                'postalCode' => $posted['shipping_postcode'],
                'notes' => '',
                'packageSize' => 'size20x36x60D10W'
            );
        }

        return array(
            'firstName' => $posted['billing_first_name'],
            'lastName' => $posted['billing_last_name'],
            'company' => $posted['billing_company'],
            'email' => $posted['billing_email'],
            'phone' => $posted['billing_phone'],
            'country' => $posted['billing_country'],
            'address' => $posted['billing_address_1'],
            'apartment' => $posted['billing_address_2'],
            'locality' => $posted['billing_city'],
            'state' => $posted['billing_state'],
            'postalCode' => $posted['billing_postcode'],
            'notes' => '',
            'packageSize' => 'size20x36x60D10W'
        );
    }

    public function paymentCompleted( $orderId )
    {
        $order = wc_get_order( $orderId );

        if( ! $order->has_shipping_method( $this->id ) )
            return false;

        $trackingCode = get_post_meta($orderId, '_PostPal_trackingCode', true);

        if( !empty($trackingCode) )
        {
            $order->add_order_note( __('PostPal courier is already ordered. Skipping second order.', 'woocommerce-postpal') );
            return false;
        }


        $orderDetails = array(
            'firstName' => $order->shipping_first_name,
            'lastName' => $order->shipping_last_name,
            'company' => $order->shipping_company,
            'email' => $order->billing_email,
            'phone' => $order->billing_phone,
            'country' => $order->shipping_country,
            'address' => $order->shipping_address_1,
            'apartment' => $order->shipping_address_2,
            'locality' => $order->shipping_city,
            'state' => $order->shipping_state,
            'postalCode' => $order->shipping_postcode,
            'notes' => '',
            'packageSize' => 'size20x36x60D10W'
        );

        $PostPalOrder = $this->PostPal->order( $orderDetails );

        if( is_array($PostPalOrder) && isset($PostPalOrder['error']) )
        {
            $order->add_order_note( __('PostPal shipping plugin is not configured correctly!', 'woocommerce-postpal') );
            return false;
        }

        if( !isset($PostPalOrder->status) || $PostPalOrder->status != true ) {
            $message = __( 'Unable to place a PostPal order!', 'woocommerce-postpal' );


            if (isset( $PostPalOrder->errors ) && count( $PostPalOrder->errors ) > 0) {
                foreach ($PostPalOrder->errors as $key => $error) {
                    $message .= "\n- " . $this->addOrderValidationError( $key, $error, true );
                }
            }

            $order->add_order_note( $message );

            return false;
        }

        update_post_meta($orderId, '_PostPal_trackingCode', $PostPalOrder->trackingCode);
        update_post_meta($orderId, '_PostPal_trackingURL', $PostPalOrder->trackingURL);
        update_post_meta($orderId, '_PostPal_packageLabelPDF', $PostPalOrder->packageLabelPDF);

        $order->add_order_note( sprintf(__('Order for PostPal courier has been placed. Order tracking number is %s', 'woocommerce-postpal'), $PostPalOrder->trackingCode ) );
    }

    public function orderReview( $order )
    {

        if( ! $order->has_shipping_method( $this->id ))
            return false;

        $trackURL = get_post_meta($order->id, '_PostPal_trackingURL', true);

        if( !empty($trackURL) ) : ?>

        <h2><?php _e( 'Track Delivery', 'woocommerce-postpal' ); ?></h2>
        <table>
            <tr>
                <td style="text-align: center;">
                    <?php _e( 'Thank you for using PostPal for this delivery! You can follow you delivery in real time by clicking the following link:', 'woocommerce-postpal' ); ?><br>
                    <a href="<?php echo $trackURL; ?>" target="_blank"><?php echo $trackURL; ?></a>
                </td>
            </tr>
        </table> <?php

        endif;
    }

    function addAdminOrderBox()
    {
        $postId = isset($_GET['post']) ? $_GET['post'] : (isset($_POST['post_ID']) ? $_POST['post_ID'] : '');

        if( empty($postId) )
            return false;

        $order = wc_get_order( $postId );

        if( ! method_exists( $order, 'has_shipping_method' ) )
            return false;

        if( ! $order->has_shipping_method( $this->id ))
            return false;

        add_meta_box(
            'woocommerce-order-postpal',
            $this->title,
            array( $this, 'adminOrderActions' ),
            'shop_order',
            'side',
            'default'
        );
    }

    function adminOrderActions( $post )
    {
        $order = wc_get_order( $post->ID );

        if( $order->has_shipping_method( $this->id ) ) {
            $trackingCode = get_post_meta($post->ID, '_PostPal_trackingCode', true);
            $trackURL = get_post_meta($post->ID, '_PostPal_trackingURL', true);
            $packageLabelPDF = get_post_meta($post->ID, '_PostPal_packageLabelPDF', true);
        }

        if( empty($trackingCode) && empty($trackURL) && empty($packageLabelPDF) )
        {
            echo '<div style="text-align: center; font-weight: bold;">'. __('PostPal courier does not seem to be ordered.', 'woocommerce-postpal') .'</div>';
            $this->adminOrderActionPlaceOrderButton( $order );

            return false;
        }

        if( empty($trackingCode) && $order->needs_payment() )
        {
            echo '<div style="text-align: center; font-weight: bold;">'. __('PostPal order has not been placed yet, as the order is waiting for a payment.', 'woocommerce-postpal') .'</div>';
            $this->adminOrderActionPlaceOrderButton( $order );

            return false;
        }

        if( !empty($trackingCode) )
            echo __('Tracking number', 'woocommerce-postpal') .': <b>' . $trackingCode .'</b><br><br>';

        if( !empty($packageLabelPDF) )
            echo '<a href="' . $packageLabelPDF . '" class="button" target="_blank">' . __( 'Package label', 'woocommerce-postpal' ) . '</a> ';


        if( !empty($trackURL) )
            echo '<a href="' . $trackURL . '" class="button" target="_blank">' . __( 'Track delivery', 'woocommerce-postpal' ) . '</a> ';
    }

    private function adminOrderActionPlaceOrderButton( $order )
    {
        if( $order->has_status('wc-completed') )
            return false;

        if( $order->has_status('wc-cancelled') )
            return false;

        if( $order->has_status('wc-refunded') )
            return false;

        if( $order->has_status('wc-failed') )
            return false;

        ?>
        <br>
        <div style="text-align: center;">
            <a href="?post=<?php echo htmlentities($_GET['post']); ?>&action=<?php echo htmlentities($_GET['action']); ?>&sendPostPal=1" onclick="return confirm('<?php _e('Do you confirm that you want to send this order to PostPal?', 'woocommerce-postpal'); ?>');" class="button">
                <?php _e( 'Send this order to PostPal', 'woocommerce-postpal' ); ?>
            </a>
        </div>
        <?php
    }

    protected function listenAdminOrder()
    {
        if( !isset($_GET['sendPostPal']) )
            return false;

        $orderId = $_GET['post'];

        $orderResult = $this->paymentCompleted( $orderId );

        if( $orderResult === false )
            wp_redirect('?post=' . htmlentities($orderId) . '&action=' . htmlentities($_GET['action']) . '&failedPostPal=1');
        else
            wp_redirect('?post=' . htmlentities($orderId) . '&action=' . htmlentities($_GET['action']) . '&donePostPal=1');

        exit;
    }

    private function adminNotificationsListener()
    {
        if( isset($_GET['donePostPal']) )
        {
            add_action( 'admin_notices', array( $this, 'adminOrderPlacedNotification' ) );
            return false;
        }

        if( isset($_GET['donePostPal']) )
        {
            add_action( 'admin_notices', array( $this, 'adminOrderFailedNotification' ) );
            return false;
        }
    }

    function adminOrderPlacedNotification()
    {
        ?>
        <div class="updated notice">
            <p><?php _e( 'Thank you! Order has been sent to PostPal!', 'woocommerce-postpal' ); ?></p>
        </div>
        <?php
    }

    function adminOrderFailedNotification()
    {
        ?>
        <div class="error notice">
            <p><?php _e( 'Failed to send the order to PostPal!', 'woocommerce-postpal' ); ?></p>
        </div>
        <?php
    }
}