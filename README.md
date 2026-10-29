# EU VAT check (VIES) for Shopware 6

Guide with setup steps: https://wsystem.dev/shopware-eu-vat-check?ref=shopware-github

Stop fake or mistyped VAT IDs from getting the tax-free company price in your Shopware shop. This plugin checks every VAT ID live against the EU VIES database when a business customer registers or edits their profile, and stores the VIES evidence (status, consultation number, company name, date) on each order.

- Works with Shopware 6.6 and 6.7.
- Uses Shopware's own VAT ID field and its own **Tax-free for companies** setting, so prices and invoices keep working the way Shopware does them. The plugin only makes sure the VAT ID is real.
- Rejects VAT IDs that VIES reports invalid (configurable), and VAT IDs whose country doesn't match the billing country.
- If VIES is down, it accepts the VAT ID by default (configurable), so checkout isn't blocked by an EU outage.
- Free to use without a key (20 checks per day per server IP). For more, add a [free API key](https://api.wsystem.dev/account/free-key?ref=shopware-plugin) (500 units a month, no card).

## How it works

1. Customer registers as a business and enters a VAT ID (for example `DE811569869`).
2. Shopware checks the format (if you enabled the pattern check on the country), then the plugin asks VIES whether the number is registered.
3. Invalid: the customer sees "This VAT ID is not valid according to the EU VIES database" and can fix it or remove it.
4. Valid: registration goes through, and Shopware applies the tax-free company price if you enabled **Settings > Countries > (country) > Tax-free for companies**.
5. When the customer orders, the VIES result is saved in the order's custom fields (set "EU VAT check (VIES)"): VAT ID, status, consultation number (if you set your own VAT ID in the plugin settings), name in VIES, checked at, and whether the order was tax-free.

## Install

With Composer (recommended), from your Shopware root. The package is on Packagist: [wsystem/shopware-eu-vat-check](https://packagist.org/packages/wsystem/shopware-eu-vat-check).

```bash
composer require wsystem/shopware-eu-vat-check
bin/console plugin:refresh
bin/console plugin:install --activate WSystemEuVatCheck
bin/console cache:clear
```

Fallback without Composer: download `WSystemEuVatCheck-<version>.zip` from the [latest release](https://github.com/wsystemdev/shopware-eu-vat-check/releases), then upload it in **Extensions > My extensions > Upload extension** and activate it.

## Free API key

The plugin works without a key (20 checks per day per server IP). For more, get a free W System API key with 500 units a month (email only, no card) at https://api.wsystem.dev/account/free-key?ref=shopware-plugin and paste it into the plugin settings. The same key also covers the W System e-invoicing endpoints (EN 16931 / XRechnung validation, Peppol UBL creation), useful if your shop has to issue e-invoices to German or Belgian business customers.

## Settings

**Extensions > My extensions > EU VAT check (VIES) > Configure**

| Setting | Default | What it does |
|---|---|---|
| W System API key | empty | Optional. Without a key the free tier applies (20 checks per day per server IP). Free key with 500 units a month: https://api.wsystem.dev/account/free-key?ref=shopware-plugin |
| Your own VAT ID | empty | Optional. When set, VIES returns a consultation number you can keep as proof of the check. |
| When VIES says the VAT ID is invalid | Reject it | Or accept it anyway. |
| When VIES is unavailable | Accept the VAT ID | Or reject it and ask the customer to try again. |

## Privacy

Only the VAT ID (and your own VAT ID, if set) is sent to `api.wsystem.dev/verify`, which queries VIES. No names, emails or addresses leave your shop. See the [privacy policy](https://wsystem.dev/privacy).

## Development

```bash
# unit tests (need a Shopware project for the framework classes)
SHOPWARE_AUTOLOAD=/path/to/shopware/vendor/autoload.php vendor/bin/phpunit
```

CI installs Shopware 6.6 and 6.7, activates the plugin, runs the unit tests and an end-to-end Store API registration test (invalid VAT ID rejected, valid one accepted, country mismatch rejected).

## Deutsch (Kurzfassung)

Das Plugin prüft USt-IdNr. bei Registrierung und Profiländerung live über VIES. Ungültige Nummern werden abgelehnt (einstellbar), bei VIES-Ausfall wird standardmäßig akzeptiert. Das Prüfergebnis inkl. VIES-Abfragenummer wird an der Bestellung gespeichert. Die steuerfreie Berechnung übernimmt Shopware selbst über **Einstellungen > Länder > Steuerfrei für Firmenkunden**.

## About

Made by [W System](https://wsystem.dev), which also runs a VAT, company and e-invoicing API for EU builders. Support: hello@wsystem.dev. License: GPL-3.0-or-later.
