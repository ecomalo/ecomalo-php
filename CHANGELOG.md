# Changelog

All notable changes to `ecomalo/ecomalo-php` are listed here. Versions follow [Semantic Versioning](https://semver.org).

## 1.0.0 - 2026-11-04

First release.

- Connect a shop with a one-time connection code from the EcomAlo panel.
- Send new orders to EcomAlo, and send your product list with stock counts.
- Verify signed webhooks for order status and parcel tracking updates.
- Read and change EcomAlo stock, and look up orders and parcels, with a merchant API key.
- Laravel: auto-discovered service provider, facade, publishable `config/ecomalo.php`, `ecomalo:connect` command, and a webhook route that fires Laravel events.
