# Upgrade to OpenMage LTS 20.18.0 / PHP 8.4

This branch replaces the Magento CE 1.9.4.5 codebase with **OpenMage LTS v20.18.0**
(latest stable release) and makes the theme and modules run on **PHP 8.4**.

It was tested locally against a copy of the production database (`int.sql.gz`) on
PHP 8.4.14 + MariaDB 10.11: setup/upgrade scripts, storefront (ICM, French and MVO
store views), customer pages, cart, guest checkout with Quebec taxes, invoice PDF,
order email rendering, the admin screens and the Dataflow product import.

## Deploying

1. **Back up** the database and the current files (including `media/`).
2. Switch the account to **PHP 8.4** (cPanel MultiPHP: `ea-php84`). Required extensions:
   `ctype curl dom ftp gd hash iconv intl json libxml mbstring mysqli pdo_mysql posix
   simplexml soap sodium zip zlib` (OpenMage checks these in `composer.json`).
3. Deploy the code, keep the **existing `app/etc/local.xml`** (it holds the DB
   credentials and the encryption key used for saved passwords/API keys) and `media/`.
4. Run `composer install --no-dev` in the site root. This installs `vendor/` and also
   copies TinyMCE, jQuery, the uploader and `Cm_RedisSession` into place; those are
   not committed (same as the official OpenMage repository).
5. Delete `var/cache/`, then open the site once so the DB upgrade scripts run (this takes
   a minute the first time). Then run `php shell/indexer.php --reindexall` and flush the
   cache in the admin.
6. Re-check System > Configuration > Payment Methods (Authorize.net) and send a test
   order: the MailGun API and the payment gateway could not be reached from the test
   environment.

The `.htaccess` keeps the bot blocking rules and now selects `ea-php84`;
`.user.ini` was updated to the `ea-php84` session path.

## What changed

### Core
* OpenMage 20.18.0 core, lib and default themes. Zend Framework now comes from
  `shardj/zf1-future` (composer) instead of `lib/Zend`.
* Removed by OpenMage (unused here): Mage_Poll, Mage_Sendfriend, Mage_PageCache,
  Mage_XmlConnect, Mage_Compiler, Phoenix_Moneybookers, the Authorize.net Direct Post
  method (Authorize.net AIM, which is the active one, is still there), Magento Connect
  (`downloader/`, `mage`), `includes/config.php`.
* The old edits inside `app/code/core` were reviewed:
  * `CatalogSearch/controllers/AdvancedController.php` (checkout messages on the advanced
    search results page) - not in OpenMage, moved to `ICM_Customizations` (controller override).
  * `Core/Model/Cookie.php` (SameSite) - built into OpenMage.
  * `lib/Zend/Http/Response.php` ("Invalid header line detected") - fixed in zf1-future.
  * `Payment/etc/config.xml` - only a commented out block, nothing to keep.
  * `index.php` maintenance IP check (was broken) and forced `display_errors` - dropped.
    OpenMage supports a `maintenance.ip` file next to `maintenance.flag` for this.

### `app/code/local/Mage` overrides -> `ICM_Customizations`
The old full-file copies of core classes are gone. The custom behaviour now lives in
`app/code/local/ICM/Customizations` as class rewrites that only override the changed
methods (so OpenMage fixes to the rest of those classes are not lost):

| Old override | Now |
| --- | --- |
| `Tax/Model/Resource/Calculation.php` (separate GST/PST/QST rows) | `Model/Tax/Resource/Calculation.php` |
| `Tax/Helper/Data.php` (shipping tax split per rate on invoices/credit memos) | `Helper/Tax/Data.php` |
| `Tax/Model/Sales/Pdf/Tax.php` (no extra combined tax line in PDFs) | `Model/Tax/Sales/Pdf/Tax.php` |
| `Sales/Model/Order.php` (delivery note as `{{var notes}}` in the new order email) | `Model/Sales/Order.php` |
| `Checkout/Block/Links.php` (header Checkout link goes to the cart first) | `Block/Checkout/Links.php` |
| `Catalog/.../Backend/Media.php` (keep image file names, no `_1` copies) | `Model/Catalog/Product/Attribute/Backend/Media.php` |
| `Catalog/Model/Convert/Adapter/Product.php` (Dataflow import extras) | `Model/Catalog/Convert/Adapter/Product.php` |

`Sales/Model/Config/Ordered.php` (totals sort fix) and `Varien/Image/Adapter/Gd2.php`
(white background noise cleanup) cannot be class rewrites, so they stay in
`app/code/local` but are now based on the OpenMage versions of those files.

The Dataflow import adapter keeps all extra CSV columns (configurable/grouped
linking, `parent`, related/upsell/crosssell, `tier_prices`, `group_prices`,
`categories` by name with positions, custom options columns, image URLs, image and
gallery labels, `remove_all_images`, `status=DELETE`); see the class doc block. Image
and gallery labels are now actually saved (the old code passed them to a method that
ignored them).

The old `Mage_Sales_Model_Order::getNote()` addition was dropped: it sent the order
email as a side effect and nothing called it.

### Modules
* From ysrtech GitHub (maintained, PHP 8 ready): **BL_CustomGrid 2.0.0.1**
  (`ysrtech/BL_CustomGrid`) and **DC_Catalog** (`ysrtech/DC_Catalog`) replace the old copies.
* Kept and fixed for PHP 8.4 / OpenMage 20: Aoe_CacheCleaner, BusinessKing_AttributeQuickAdd,
  CeicomExtends_CBanners, Clarion_Customerattribute, Dh_Deliverynote, DigitalPianism_AjaxLogin,
  Fishpig_iBanners, FreeLunchLabs_MailGun, JetRails_Cloudflare, Kaushik_Outofstock,
  Llian_MassProductLinker, Netzarbeiter_CustomerActivation, Netzarbeiter_GroupsCatalog2,
  ProxiBlue_ReCaptcha, RicoNeitzel_PaymentFilter, Thirty4_CatalogNew, Thirty4_CatalogSale,
  Uni_Common, Uni_Fileuploader, VF_AjaxCart, VF_EasyAjax, VladimirPopov_WebForms.
  Fixes: removed `Mysql4`/`Entity` class names mapped to the `Resource` classes,
  `$str{$i}` offsets, implicit nullable parameters, method signatures (WebForms captcha),
  `null` passed to string functions, MailGun failed-send handling.
* Removed: Maven_Html5uploader (OpenMage has its own HTML5 uploader), and code that was not
  enabled: Inchoo_PHP7, ICM_Adminhtml (+ admin theme `icm`), Santhosh_* (+ admin theme
  `santhosh`), TBT_MassRelater, JetRails_TwoFactor, FreeLunchLabs SendGrid/CloudFront,
  DM_Cloudflare, the disabled `Uni_` copy and `Cm_RedisSession` (now from composer).
* `Uni_Fileuploader`: `getAllFiles()`/`getFeaturedFiles()` (used by the "videos-all"
  and featured videos templates) only existed in the disabled `Uni_` copy, so the videos
  page was empty; they were added to the active module.

### Theme (`icm/icm`, `default/ICM`, `default/MVO`) and skin
Migrated unchanged except for PHP 8 fixes: `htmlEscape()`/`urlEscape()` (removed in
OpenMage) -> `escapeHtml()`/`escapeUrl()`, `count()` on undefined variables in the
product media/video templates, undefined `$grcount` in the category navigation,
`end(explode())` in the video templates. Customized email templates (`account_new`,
`order_new`, `order_new_guest`) were re-applied on top of the OpenMage versions.

### Root files
Kept: `completeOrders.php`, `magento-cleanup.php`, `favicon.png`, Google verification
file, `errors/local.xml`. Removed: `i.php` (public `phpinfo()`), `error_log`,
`maintenance.flag_` and the disabled `*.php_` scripts (still in git history).
