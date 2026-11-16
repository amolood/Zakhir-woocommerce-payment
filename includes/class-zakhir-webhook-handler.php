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

        // Zakhir does not sign its callbacks, so anyone can POST status=COMPLETED for a known
        // referenceId. The request only says which order to look at: its status (and Zakhir's
        // payment id) are read back from Zakhir, and nothing changes when Zakhir can't be asked.
        $gateway = WC()->payment_gateways()->payment_gateways()['zakhir'] ?? null;

        if ( ! $gateway instanceof WC_Zakhir_Gateway ) {
            self::respond( 'received' );
        }

        try {
            $verified = $gateway->get_api()->get_payment_status( $reference_id );
        } catch ( Throwable $e ) {
            wc_get_logger()->warning(
                'Zakhir webhook: could not confirm the status with Zakhir: ' . $e->getMessage(),
                [ 'source' => 'zakhir', 'reference_id' => $reference_id ]
            );
            self::respond( 'received' );
        }

        $status    = strtoupper( sanitize_text_field( (string) ( $verified['status'] ?? '' ) ) );
        $zakhir_id = sanitize_text_field( (string) ( $verified['id'] ?? $zakhir_id ) );
        $payload   = $verified;

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
            return;
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
