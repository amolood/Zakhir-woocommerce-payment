<div align="center">
  <img src="https://raw.githubusercontent.com/amolood/Zakhir-woocommerce-payment/main/assets/images/zakhir-logo.png" alt="Zakhir" width="160" />

  <h1>Zakhir Payment Gateway for WooCommerce</h1>

  <p>
    Accept payments via the <strong>Zakhir</strong> wallet in your WooCommerce store.<br/>
    Hosted checkout · Webhook notifications · Staging support · HPOS compatible.
  </p>

  <p>
    Built by <a href="https://digitalize.sd">Digitalize Lab</a> &nbsp;·&nbsp;
    Maintained by <a href="https://amolood.com">Abdalrahman Molood</a>
  </p>
</div>

---

## Requirements

| Dependency  | Version |
| ----------- | ------- |
| PHP         | `^8.0`  |
| WordPress   | `^6.0`  |
| WooCommerce | `^7.0`  |

---

## Installation

### Option A — Upload ZIP

1. Download the latest release ZIP from [GitHub Releases](https://github.com/amolood/Zakhir-Woocommerce/releases)
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**, then **Activate**

### Option B — Manual Upload

1. Clone or download this repository
2. Copy the `zakhir-payment-gateway` folder into `wp-content/plugins/`
3. Go to **Plugins → Installed Plugins** and activate **Zakhir Payment Gateway**

---

## Configuration

Go to **WooCommerce → Settings → Payments → Zakhir** and fill in the following:

### General

| Field                | Description                                           |
| -------------------- | ----------------------------------------------------- |
| **Enable / Disable** | Toggle the gateway on or off                          |
| **Environment**      | `Production` for live payments, `Staging` for testing |
| **Title**            | Payment method label shown to customers at checkout   |
| **Description**      | Short description shown under the payment method      |

### Production Credentials

Obtain these from your [Zakhir merchant dashboard](https://zakhir.net):

| Field            | Description                      |
| ---------------- | -------------------------------- |
| **API Base URL** | Provided by Zakhir upon merchant onboarding |
| **Tenant ID**    | Your merchant tenant identifier  |
| **Profile ID**   | Your merchant profile identifier |
| **API Key**      | Your secret API key              |

### Staging Credentials

Fill in the staging equivalents when **Environment** is set to `Staging`.

### Advanced

| Field             | Description                                            |
| ----------------- | ------------------------------------------------------ |
| **API Timeout**   | Seconds to wait for Zakhir API responses (default: 15) |
| **Debug Logging** | Logs API calls to WooCommerce → Status → Logs          |

---

## Webhook Setup

The plugin automatically registers a webhook endpoint at:

```
POST https://yoursite.com/wc-api/zakhir
```

Set this URL in your **Zakhir merchant dashboard** as the notification URL. Zakhir will POST payment status updates here when a payment is completed or rejected.

---

## Payment Flow

```
1. Customer selects "Zakhir Wallet" at checkout and places the order
2. Plugin calls POST /api/ecommerce/payments → receives checkout URL
3. Customer is redirected to Zakhir's hosted checkout page
4. Customer completes payment on Zakhir's platform
5. Zakhir POSTs a webhook to /wc-api/zakhir with status=COMPLETED
6. Plugin marks the WooCommerce order as Processing/Completed
7. Customer lands on the WooCommerce thank-you page
```

> The plugin also polls the payment status once on the thank-you page as a fallback in case the webhook arrives late.

---

## Architecture

```
zakhir-payment-gateway/
├── zakhir-payment-gateway.php          Plugin entry point, auto-discovery
├── includes/
│   ├── class-wc-zakhir-gateway.php     WC_Payment_Gateway — checkout, admin settings
│   ├── class-zakhir-api.php            HTTP client for Zakhir API (create, status, cancel)
│   └── class-zakhir-webhook-handler.php  Handles incoming Zakhir webhook POSTs
├── assets/
│   ├── css/checkout.css                Minimal checkout styles
│   └── images/zakhir-logo.png          Payment method icon
└── languages/                          i18n/l10n POT file location
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Credits

|             |                                           |
| ----------- | ----------------------------------------- |
| **Author**  | [Abdalrahman Molood](https://amolood.com) |
| **Company** | [Digitalize Lab](https://digitalize.sd)   |
| **Gateway** | [Zakhir](https://zakhir.net)              |

---

## License

MIT License — see [LICENSE](LICENSE).
