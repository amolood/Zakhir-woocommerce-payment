<?php
defined( 'ABSPATH' ) || exit;

/**
 * Zakhir WooCommerce Payment Gateway.
 */
class WC_Zakhir_Gateway extends WC_Payment_Gateway {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        self::$instance = $this;

        $this->id                 = 'zakhir';
        $this->method_title       = __( 'Zakhir', 'zakhir-payment-gateway' );
        $this->method_description = __( 'Accept payments through the Zakhir wallet — Sudan\'s leading digital payment gateway.', 'zakhir-payment-gateway' );
        $this->has_fields         = false;
        $this->supports           = [ 'products' ];

        $logo = ZAKHIR_WC_URL . 'assets/images/zakhir-logo.png';
        $this->icon = apply_filters( 'woocommerce_zakhir_icon', $logo );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thank_you_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    // -------------------------------------------------------------------------
    // Admin form fields
    // -------------------------------------------------------------------------

    public function init_form_fields(): void {
        $this->form_fields = [

            'enabled' => [
                'title'   => __( 'Enable / Disable', 'zakhir-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Zakhir Payment Gateway', 'zakhir-payment-gateway' ),
                'default' => 'no',
            ],

            'environment' => [
                'title'       => __( 'Environment', 'zakhir-payment-gateway' ),
                'type'        => 'select',
                'description' => __( 'Use Staging for testing, Production for live payments.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'production',
                'options'     => [
                    'production' => __( 'Production', 'zakhir-payment-gateway' ),
                    'staging'    => __( 'Staging', 'zakhir-payment-gateway' ),
                ],
            ],

            'title' => [
                'title'       => __( 'Title', 'zakhir-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Payment method title shown to the customer during checkout.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => __( 'Zakhir Wallet', 'zakhir-payment-gateway' ),
            ],

            'description' => [
                'title'       => __( 'Description', 'zakhir-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description shown to the customer during checkout.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => __( 'Pay securely using your Zakhir wallet.', 'zakhir-payment-gateway' ),
            ],

            'production_credentials' => [
                'title' => __( 'Production Credentials', 'zakhir-payment-gateway' ),
                'type'  => 'title',
            ],

            'base_url' => [
                'title'       => __( 'API Base URL', 'zakhir-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Zakhir production API base URL.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'https://zakhir.cloud/api/',
            ],

            'tenant' => [
                'title'       => __( 'Tenant ID', 'zakhir-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Your Zakhir tenant identifier.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => '',
            ],

            'profile' => [
                'title'       => __( 'Profile ID', 'zakhir-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Your Zakhir profile identifier.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => '',
            ],

            'api_key' => [
                'title'       => __( 'API Key', 'zakhir-payment-gateway' ),
                'type'        => 'password',
                'description' => __( 'Your Zakhir API key.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => '',
            ],

            'staging_credentials' => [
                'title' => __( 'Staging Credentials', 'zakhir-payment-gateway' ),
                'type'  => 'title',
            ],

            'staging_base_url' => [
                'title'    => __( 'Staging API Base URL', 'zakhir-payment-gateway' ),
                'type'     => 'text',
                'desc_tip' => true,
                'default'  => '',
            ],

            'staging_tenant' => [
                'title'    => __( 'Staging Tenant ID', 'zakhir-payment-gateway' ),
                'type'     => 'text',
                'desc_tip' => true,
                'default'  => '',
            ],

            'staging_profile' => [
                'title'    => __( 'Staging Profile ID', 'zakhir-payment-gateway' ),
                'type'     => 'text',
                'desc_tip' => true,
                'default'  => '',
            ],

            'staging_api_key' => [
                'title'    => __( 'Staging API Key', 'zakhir-payment-gateway' ),
                'type'     => 'password',
                'desc_tip' => true,
                'default'  => '',
            ],

            'advanced' => [
                'title' => __( 'Advanced', 'zakhir-payment-gateway' ),
                'type'  => 'title',
            ],

            'webhook_secret' => [
                'title'       => __( 'Webhook Secret', 'zakhir-payment-gateway' ),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __( 'Optional HMAC-SHA256 secret for webhook verification. Your webhook URL is: <code>%s</code>', 'zakhir-payment-gateway' ),
                    esc_url( home_url( '/wc-api/zakhir' ) )
                ),
                'default'     => '',
            ],

            'timeout' => [
                'title'       => __( 'API Timeout (seconds)', 'zakhir-payment-gateway' ),
                'type'        => 'number',
                'description' => __( 'Maximum seconds to wait for a response from the Zakhir API.', 'zakhir-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => '15',
                'custom_attributes' => [
                    'min'  => '5',
                    'max'  => '60',
                    'step' => '1',
                ],
            ],

            'debug' => [
                'title'       => __( 'Debug Logging', 'zakhir-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable debug logging', 'zakhir-payment-gateway' ),
                'description' => sprintf(
                    /* translators: %s: log file URL */
                    __( 'Log API requests and responses for debugging. Logs are available <a href="%s">here</a>.', 'zakhir-payment-gateway' ),
                    esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) )
                ),
                'default'     => 'no',
            ],

        ];
    }

    // -------------------------------------------------------------------------
    // Process payment
    // -------------------------------------------------------------------------

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        try {
            $api          = $this->get_api();
            $reference_id = $this->generate_reference_id();
            $return_url   = $this->get_return_url( $order );
            $notify_url   = home_url( '/wc-api/zakhir' );

            $response = $api->create_payment(
                reference_id: $reference_id,
                amount: (float) $order->get_total(),
                currency: get_woocommerce_currency(),
                note: sprintf(
                    /* translators: %s: order number */
                    __( 'Order #%s', 'zakhir-payment-gateway' ),
                    $order->get_order_number()
                ),
                return_url: $return_url,
                notify_url: $notify_url,
            );

            $checkout_url = $response['checkoutPage']['url'] ?? '';

            if ( empty( $checkout_url ) ) {
                throw new Exception( __( 'Zakhir did not return a checkout URL.', 'zakhir-payment-gateway' ) );
            }

            // Persist Zakhir identifiers on the order.
            $order->update_meta_data( '_zakhir_reference_id', $reference_id );
            $order->update_meta_data( '_zakhir_payment_id', $response['id'] ?? '' );
            $order->update_meta_data( '_zakhir_checkout_url', $checkout_url );
            $order->update_meta_data( '_zakhir_environment', $this->get_option( 'environment' ) );
            $order->update_status( 'pending', __( 'Awaiting Zakhir payment.', 'zakhir-payment-gateway' ) );
            $order->save();

            // Reduce stock immediately to prevent overselling.
            wc_reduce_stock_levels( $order_id );
            WC()->cart->empty_cart();

            $this->log( sprintf( 'Payment created for order #%s — referenceId: %s', $order_id, $reference_id ) );

            return [
                'result'   => 'success',
                'redirect' => $checkout_url,
            ];

        } catch ( Exception $e ) {
            $this->log( 'Error creating payment for order #' . $order_id . ': ' . $e->getMessage(), 'error' );

            wc_add_notice(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Payment error: %s', 'zakhir-payment-gateway' ),
                    esc_html( $e->getMessage() )
                ),
                'error'
            );

            return [ 'result' => 'failure' ];
        }
    }

    // -------------------------------------------------------------------------
    // Thank you page — poll status once to update order if webhook was fast
    // -------------------------------------------------------------------------

    public function thank_you_page( int $order_id ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->is_paid() ) {
            return;
        }

        $reference_id = $order->get_meta( '_zakhir_reference_id' );

        if ( empty( $reference_id ) ) {
            return;
        }

        try {
            $response = $this->get_api()->get_payment_status( $reference_id );
            $status   = strtoupper( $response['status'] ?? '' );

            if ( 'COMPLETED' === $status && ! $order->is_paid() ) {
                $order->payment_complete( $response['id'] ?? $reference_id );
                $order->add_order_note( __( 'Payment confirmed via Zakhir status poll on thank-you page.', 'zakhir-payment-gateway' ) );
            }
        } catch ( Exception $e ) {
            $this->log( 'Status poll failed on thank-you page for order #' . $order_id . ': ' . $e->getMessage(), 'warning' );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function get_api(): Zakhir_API {
        $is_staging = 'staging' === $this->get_option( 'environment' );

        return new Zakhir_API(
            base_url: $is_staging ? $this->get_option( 'staging_base_url' ) : $this->get_option( 'base_url' ),
            tenant:   $is_staging ? $this->get_option( 'staging_tenant' )   : $this->get_option( 'tenant' ),
            profile:  $is_staging ? $this->get_option( 'staging_profile' )  : $this->get_option( 'profile' ),
            api_key:  $is_staging ? $this->get_option( 'staging_api_key' )  : $this->get_option( 'api_key' ),
            timeout:  (int) $this->get_option( 'timeout', 15 ),
        );
    }

    private function generate_reference_id(): string {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex( random_bytes( 4 ) ),
            bin2hex( random_bytes( 2 ) ),
            bin2hex( random_bytes( 2 ) ),
            bin2hex( random_bytes( 2 ) ),
            bin2hex( random_bytes( 6 ) )
        );
    }

    public function log( string $message, string $level = 'info' ): void {
        if ( 'yes' !== $this->get_option( 'debug' ) ) {
            return;
        }

        $logger = wc_get_logger();
        $logger->log( $level, $message, [ 'source' => 'zakhir' ] );
    }

    public function enqueue_scripts(): void {
        if ( is_checkout() ) {
            wp_enqueue_style(
                'zakhir-checkout',
                ZAKHIR_WC_URL . 'assets/css/checkout.css',
                [],
                ZAKHIR_WC_VERSION
            );
        }
    }
}
