=== PeproDev Ultimate Invoice ===
Contributors: peprodev, amirhpcom, blackswanlab
Donate link: https://pepro.dev/donate
Tags: woocommerce invoice, pdf invoice, persian, WooCommerce
Requires at least: 5.0
Tested up to: 6.7
Version: 2.1.0
Stable tag: 2.1.0
Requires PHP: 7.0
WC requires at least: 5.0
WC tested up to: 9.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Most Advanced Invoice Plugin you were looking for!
Create HTML/PDF Invoices of WooCommerce Orders, Attach PDF Invoice to Mail and Let customers download beautiful-customizable styled invoices.

== Description ==

## **Ultimate Invoice plugin for WooCommerce!**

Create customizable PDF/HTML invoices for WooCommerce, attach to Email, Packing Slips, Shipping Labels, Shipping Tracking, Single-shop feature and ...
This plugin lets you to Generate Awesome Invoices for WooCommerce orders and:

-   Download PDF invoices
-   Email Styled Invoices
-   Attach PDF to WC Emails
-   Attach PDF to Invoices Emails
-   Restrict Invoices Options
-   Full Invoice Customizations
-   Make your own Invoice Template
-   Make your own PDF Invoice Template
-   Make your own Inventory Report Template
-   Make your own Packing Slips Template
-   Alter plugins via Action/Filter hooks
-   and .....


### Hot Features since Version 1.4 🔥
- Bulk Download Invoices PDF as ZIP Archive
- Bulk Print Invoices Inventory Reports
- Bulk Print Invoices Shipping Slips
- Export/Import Settings as JSON/PHP!
- Developers 😍 Bundle your PDF/HTML Invoice Template with your Theme/Plugin ([read more](https://github.com/peprodev/ultimate-invoice/wiki/Add-Customized-External-Template-to-Ultimate-Invoice))
- Added: Fully Compatibility with [PeproDev Ultimate Profile Solutions](https://wordpress.org/plugins/peprodev-ups/)
- Added: Fully Compatibility with [WPC Product Bundles by WPClever](https://wpclever.net/downloads/product-bundles)
- Added: Fully Compatibility with [WooCommerce Extra Product Options by ThemeComplete](https://codecanyon.net/item/woocommerce-extra-product-options/7908619)
- Added: Fully Compatibility with *Any Standard* plugins that adds and shows Order item metas
- Added: Added Integration Section in Settings



-----------------------

### Made by Developers for Developers
- [Github Wiki](https://github.com/peprodev/ultimate-invoice/wiki)
- Over 190 Filter Hook
- Over 15 Action Hook
- Fully Translatable
- Nice and Human Readable Variable Names
- Separated Classes for Different purposes
- Templates for HTML Invoice (tpl, css)
- Templates for PDF Invoice (tpl, css)
- Templates for Email Invoice (tpl, css)
- Templates for Inventory Report (tpl, css)
- Templates for Packing Slips(tpl, css)
- Change Email Sending Settings (from, name, and ...)
- Preserve email template style even in **Gmail**!
- and ....

### Invoice Items filtering
- Show / Hide Store National Id
- Show / Hide Store Registration Number
- Show / Hide Store Economical Number
- Show / Hide Customer Address
- Show / Hide Customer Phone
- Show / Hide Customer E-mail
- Show / Hide Order Date
- Show / Hide Payment method
- Show / Hide Shipping method
- Show / Hide Transaction Ref. ID
- Show / Hide Product Image
- Show / Hide Product Purchase note
- Show / Hide Order Items
- Show / Hide Order Total
- Show / Hide Product Weight
- Show / Hide Product Dimensions
- Show / Hide Product SKU
- Show / Hide Order Note
- and ....

### Extras
- Quick Shop Feature ( Purchase all products in one page )
- Visual Composer Widget
- Pre-order Invoices Status
- Built in DARK MODE Support (Auto, Manual)
- Disable WC Modern Dashboard
- Date parsing templates
- Jalali/Shamsi Date formats numbers?
- English, Eastern Arabic and Persian Numbers style
- RTL-ready
- Automated Email sending system
- Fully Woo-commerce integration
- and ...



### How can I report security bugs?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/pepro-ultimate-invoice)


---

#### Made by love in [Pepro Development Center](https://pepro.dev/).

#### *[Pepro Dev](https://pepro.dev/) is a registered trademark of [Pepro Co](https://pepro.co/).*

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.

1. Activate the plugin through the 'Plugins' screen in WordPress

1. Navigate to `yoursite.com/wp-admin/admin.php?page=wc-settings&tab=pepro_ultimate_invoice` to change settings.


== Frequently Asked Questions ==

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/pepro-ultimate-invoice)

= How can I show compact total order in PDF invoices? =

Use hook below and add it into your plugin or theme's `function.php`

add_filter( "puiw_return_pdf_total_prices_as_single_price","__return_true");`

= How can I contribute to this plugin? =

You can help us improve our works by committing your changes to our Github repository: [github.com/peprodev/ultimate-invoice](https://github.com/peprodev/ultimate-invoice)


== Screenshots ==

1. Sample PDF Generated by plugin
2. Store Details Setting
3. Store Details Setting in Dark mode
4. Invoices items Setting
5. Invoices items Setting in Dark mode
6. Miscellaneous Setting ( Disable WC New Dashboard and ...)
7. Privacy Setting ( Access Manager )
8. Barcode and QR Setting
9. Inventory Report Setting
10. Invoices template and theming setting
11. WooCommerce Orders → Columns → Ultimate Invoice Options → Popup Toolbox
12. WooCommerce Orders → Columns → Ultimate Invoice Options → Popup Toolbox → Email Invoice to Customer on the fly
13. WooCommerce Orders → Columns → Ultimate Invoice Options → Popup Toolbox → View Invoice on the fly
14. Edit Orders → Metabox → Ultimate Invoice Options


== Upgrade Notice ==

= v2.1.0 / 2025-02-22 / 1403-12-04 =
- Fixed Security Issue
- Changed Default Security Options

= v2.0.9 / 2024-11-13 / 1403-08-23 =
- WP 6.7 Compatibility
- Fixed Translation load



== Changelog ==

For full changelog please view [Github Repo.](https://github.com/peprodev/ultimate-invoice)

= v2.1.0 / 2025-02-22 / 1403-12-04 =
- Fixed Security Issue
- Changed Default Security Options

= v2.0.9 / 2024-11-13 / 1403-08-23 =
- WP 6.7 Compatibility
- Fixed Translation load

= v2.0.8 / 2024-09-08 / 1403-06-18 =
- Fixed PDF invoice files does not have .pdf suffix

= v2.0.7 / 2024-08-24 / 1403-06-03 =
- Fixed Vulnerability reported by LVT-tholv2k

= v2.0.6 / 2024-07-24 / 1403-05-02 =
- Fixed undefined invoice template index name
- Fix HPOS Incompatibility

= v2.0.5 / 2024-06-23 / 1403-04-03 =
- Dev: added `puiw_create_pdf_Mpdf_options` filter hook
- Dev: added `puiw_create_slips_pdf_Mpdf_options` filter hook
- Dev: added `puiw_parse_pdf_template` filter hook

= v2.0.4 / 2024-06-14 / 1403-03-25 =
- Fixed `Uncaught Error: Call to undefined method WP Post:get_id()`

= v2.0.3 / 2024-06-13 / 1403-03-24 =
- Fix HPOS error of incompatibility

= v2.0.2 / 2024-05-02 / 1403-02-13 =
- WooCommerce 8.8.3 Compatibility
- Added: Save Shipping ResID Ajax-button
- Enhanced: Security in Migration settings (Thanks to Darius S from patchstack.com)

== About Us ==

PEPRO DEV is a premium supplier of quality WordPress plugins, services and support.
Join us at [https://pepro.dev/](https://pepro.dev/) and also don't forget to check our [free offerings](http://profiles.wordpress.org/peprodev/), we hope you enjoy them!
