<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles incoming Zakhir payment webhook notifications.
 *
 * Endpoint: POST https://yoursite.com/wc-api/zakhir
 */
class Zakhir_Webhook_Handler {

    public static function init(): void {
        add_action( 'woocommerce_api_zakhir', [ self::class, 'handle' ] );
    }

    public static function handle(): void {
        $raw     = file_get_contents( 'php://input' );
        $payload = json_decode( $raw, true ) ?? [];

        // Zakhir may send params in the query string or in the body.
        $reference_id = sanitize_text_field(
            $_GET['referenceId'] ?? $payload['referenceId'] ?? '' // phpcs:ignore WordPress.Security.NonceVerification
        );
        $zakhir_id    = sanitize_text_field(
            $_GET['id'] ?? $payload['id'] ?? '' // phpcs:ignore WordPress.Security.NonceVerification
        );
        $status       = strtoupper( sanitize_text_field(
            $_GET['status'] ?? $payload['status'] ?? '' // phpcs:ignore WordPress.Security.NonceVerification
        ) );

        if ( empty( $reference_id ) ) {
            self::respond( 'missing referenceId' );
        }

        $order = self::find_order( $reference_id );

        if ( ! $order ) {
            self::respond( 'order not found' );
        }

        // Verify the secret token stored on the order.
        $gateway  = WC_Zakhir_Gateway::instance();
        $secret   = $gateway->get_option( 'webhook_secret' );

        if ( ! empty( $secret ) ) {
            $sig = $_SERVER['HTTP_X_ZAKHIR_SIGNATURE'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $sig = sanitize_text_field( $sig );
            $expected = hash_hmac( 'sha256', $raw, $secret );
            $provided = preg_replace( '/^sha256=/i', '', $sig );
            if ( ! hash_equals( $expected, strtolower( $provided ) ) ) {
                status_header( 401 );
                self::respond( 'invalid signature' );
            }
        }

        switch ( $status ) {
            case 'COMPLETED':
                self::handle_completed( $order, $zakhir_id, $reference_id, $payload );
                break;

            case 'REJECTED':
                self::handle_rejected( $order );
                break;
        }

        self::respond( 'received' );
    }

    // -------------------------------------------------------------------------

    private static function handle_completed(
        WC_Order $order,
        string $zakhir_id,
        string $reference_id,
        array $payload
    ): void {
        if ( $order->is_paid() ) {
            return; // Idempotent — already processed.
        }

        $transaction_id = $zakhir_id ?: $reference_id;

        $order->payment_complete( $transaction_id );
        $order->update_meta_data( '_zakhir_payment_id', $zakhir_id );
        $order->update_meta_data( '_zakhir_raw_payload', wp_json_encode( $payload ) );
        $order->save();

        /* translators: %s: Zakhir payment ID */
        $order->add_order_note( sprintf(
            esc_html__( 'Payment completed via Zakhir. Payment ID: %s', 'zakhir-payment-gateway' ),
            esc_html( $transaction_id )
        ) );
    }

    private static function handle_rejected( WC_Order $order ): void {
        if ( $order->has_status( [ 'completed', 'processing', 'cancelled', 'failed' ] ) ) {
            return;
        }

        $order->update_status(
            'failed',
            esc_html__( 'Payment rejected by Zakhir.', 'zakhir-payment-gateway' )
        );
    }

    /**
     * Find a WooCommerce order by Zakhir referenceId stored in order meta.
     */
    private static function find_order( string $reference_id ): ?WC_Order {
        $orders = wc_get_orders( [
            'meta_key'   => '_zakhir_reference_id',
            'meta_value' => $reference_id,
            'limit'      => 1,
        ] );

        return ! empty( $orders ) ? $orders[0] : null;
    }

    private static function respond( string $message ): never {
        wp_send_json( [ 'status' => $message ] );
    }
}
