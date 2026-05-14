<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles all HTTP communication with the Zakhir API.
 */
class Zakhir_API {

    private string $base_url;
    private string $tenant;
    private string $profile;
    private string $api_key;
    private int    $timeout;

    public function __construct(
        string $base_url,
        string $tenant,
        string $profile,
        string $api_key,
        int $timeout = 15
    ) {
        $this->base_url = trailingslashit( $base_url ) . 'api/ecommerce/';
        $this->tenant   = $tenant;
        $this->profile  = $profile;
        $this->api_key  = $api_key;
        $this->timeout  = $timeout;
    }

    /**
     * Create a new payment and return the checkout URL + referenceId.
     *
     * @throws Exception On HTTP or API errors.
     */
    public function create_payment(
        string $reference_id,
        float $amount,
        string $currency,
        string $note,
        string $return_url,
        string $notify_url
    ): array {
        $body = [
            'referenceId'  => $reference_id,
            'amount'       => [
                'value'    => round( $amount, 2 ),
                'currency' => strtoupper( $currency ),
            ],
            'note'         => $note,
            'checkoutPage' => [
                'returnUrl' => $return_url,
            ],
            'notifyUrl'    => $notify_url,
        ];

        return $this->request( 'POST', 'payments', $body );
    }

    /**
     * Poll the status of a payment by referenceId.
     *
     * @throws Exception On HTTP or API errors.
     */
    public function get_payment_status( string $reference_id ): array {
        return $this->request( 'GET', 'payments/' . rawurlencode( $reference_id ) . '/status' );
    }

    /**
     * Cancel a PENDING payment.
     *
     * @throws Exception On HTTP or API errors.
     */
    public function cancel_payment( string $reference_id ): array {
        return $this->request( 'DELETE', 'payments/' . rawurlencode( $reference_id ) );
    }

    // -------------------------------------------------------------------------

    private function request( string $method, string $path, array $body = [] ): array {
        $url  = $this->base_url . $path;
        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'v-tenant'     => $this->tenant,
                'v-profile'    => $this->profile,
                'v-api-key'    => $this->api_key,
            ],
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new Exception(
                sprintf(
                    /* translators: %s: error message */
                    esc_html__( 'Zakhir API connection error: %s', 'zakhir-payment-gateway' ),
                    $response->get_error_message()
                )
            );
        }

        $status_code   = (int) wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

        if ( $status_code < 200 || $status_code >= 300 ) {
            $message = $response_body['message'] ?? $response_body['error'] ?? sprintf(
                /* translators: %d: HTTP status code */
                esc_html__( 'Zakhir API returned HTTP %d', 'zakhir-payment-gateway' ),
                $status_code
            );
            throw new Exception( esc_html( $message ), $status_code );
        }

        return $response_body;
    }
}
