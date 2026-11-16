# Changelog

## [Unreleased]

### Security
- Webhook: the callback is no longer trusted. Zakhir does not sign its callbacks, so anyone could POST
  `status=COMPLETED` for a known `referenceId` and have the order marked paid. The handler now reads the
  status back from Zakhir (`GET payments/{referenceId}/status`) and changes nothing when Zakhir can't be reached.

## [1.0.0] — 2025-05-14

### Added
- Initial release
- Hosted checkout payment flow via Zakhir API
- Production and staging environment support
- Webhook handler at `/wc-api/zakhir`
- Optional HMAC-SHA256 webhook signature verification
- Status polling on thank-you page
- Debug logging via WooCommerce logger
- HPOS (High-Performance Order Storage) compatibility
