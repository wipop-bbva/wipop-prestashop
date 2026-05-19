## [1.6.1](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.6.0...v1.6.1) (2026-05-19)

### Bug Fixes

* **webhook:** require auth for connected verification ([d981173](https://github.com/wipop-bbva/wipop-prestashop/commit/d9811737cdedd34da37870eb0dc8c0881b80ef69))
* **webhook:** validate completed charges idempotently ([ffc2205](https://github.com/wipop-bbva/wipop-prestashop/commit/ffc2205695cf597199a9a263ef3a9602691c14ca))

## [1.6.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.5.0...v1.6.0) (2026-05-18)

### Features

* **payment:** track webhook state on payment attempts ([b30991e](https://github.com/wipop-bbva/wipop-prestashop/commit/b30991e388d14f71287dc0a83b3ea8681a7c329a))
* **return:** show payment state from Wipop attempts ([140446a](https://github.com/wipop-bbva/wipop-prestashop/commit/140446af04be209752e1495cdc70086f1c292712))
* **webhook:** sync completed Wipop charges into PrestaShop orders ([cf6d760](https://github.com/wipop-bbva/wipop-prestashop/commit/cf6d760ac57fad3e2bd70efe7a817249a363ff80))

## [1.5.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.4.0...v1.5.0) (2026-04-30)

### Features

* **admin:** add webhook credential management ([fb19fdc](https://github.com/wipop-bbva/wipop-prestashop/commit/fb19fdc053097e6b23a400a8b9e8f07f15263a13))
* **settings:** add typed storage exceptions ([ec35285](https://github.com/wipop-bbva/wipop-prestashop/commit/ec35285bf687925b1dcaee8acc7b872f3bbca5c5))
* **webhook:** add authenticated webhook endpoint ([730bb08](https://github.com/wipop-bbva/wipop-prestashop/commit/730bb0855c41d4c9811e835f197ea53709b4ad2b))
* **webhook:** add typed credentials exceptions ([5424274](https://github.com/wipop-bbva/wipop-prestashop/commit/5424274ac5b80f6c703ab4594c1f018274bf27a1))

### Bug Fixes

* **admin:** handle settings and webhook persistence failures ([6309141](https://github.com/wipop-bbva/wipop-prestashop/commit/6309141cbc615029cce755e607b5681517f8b27d))
* **front:** handle settings failures in checkout and webhook flows ([b153a9a](https://github.com/wipop-bbva/wipop-prestashop/commit/b153a9aa0b0be2fd4008d994c3a2876ee84fc89d))

## [1.4.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.3.0...v1.4.0) (2026-04-20)

### Features

* **checkout:** generate Wipop order ids from payment attempts ([99ed385](https://github.com/wipop-bbva/wipop-prestashop/commit/99ed38532008c530aa912fbe7c2d00c351739bac))

### Bug Fixes

* **checkout:** add a new line for improved code readability as requested by CI ([3606517](https://github.com/wipop-bbva/wipop-prestashop/commit/3606517fd394b3d2f3e4066d506821dccb53f8d4))

## [1.3.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.2.0...v1.3.0) (2026-04-16)

### Features

* **admin:** verify merchant gateways on settings save ([5db5e31](https://github.com/wipop-bbva/wipop-prestashop/commit/5db5e319ca569be3ae5c977da6a0b832ff1ffa25))
* **checkout:** use merchant gateways for payment options ([e1e1607](https://github.com/wipop-bbva/wipop-prestashop/commit/e1e16073b39f50e8c6ac06818c605a869d803a6f))

## [1.2.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.1.0...v1.2.0) (2026-04-15)

### Features

* **checkout:** start real Wipop charge flow for card and bizum ([ff80ae8](https://github.com/wipop-bbva/wipop-prestashop/commit/ff80ae815c06e46761b24f728b04fe90d8b3ddeb))
* **language:** add WipopLanguage enum for locale handling ([0e403e8](https://github.com/wipop-bbva/wipop-prestashop/commit/0e403e8c385ad2071e0ca6b9c59fd71d8b34410c))

### Bug Fixes

* **admin:** load module services from the PrestaShop admin container ([2240d7a](https://github.com/wipop-bbva/wipop-prestashop/commit/2240d7a8cb60d8c1edd8b878258e280c86fd9a5f))
* **library:** update library dependence ([677353a](https://github.com/wipop-bbva/wipop-prestashop/commit/677353a284426cb08131418a3322c510988eb5cf))
* **module:** centralize translation domains ([3d79342](https://github.com/wipop-bbva/wipop-prestashop/commit/3d793422774b9a379542fbcd57f83ff739c6fded))
* **template:** correct spelling of Wipop ([9f11dff](https://github.com/wipop-bbva/wipop-prestashop/commit/9f11dffb9a163722fb9a593fa6da199b64e9553d))

## [1.1.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.0.0...v1.1.0) (2026-03-17)

### Features

* **translation:** add section for managing translations in development guide ([6eaa60b](https://github.com/wipop-bbva/wipop-prestashop/commit/6eaa60b7738f30464e59548ed846cfdf4f81e147))
* **translation:** add xlf files for supported languajes ([063b419](https://github.com/wipop-bbva/wipop-prestashop/commit/063b419745004708ba48d3ec43ccd120bae92f96))
* **translation:** implement translation system for user messages and labels ([5de33f4](https://github.com/wipop-bbva/wipop-prestashop/commit/5de33f43c8604bb720d1cb0888adb210c386d3fc))

### Bug Fixes

* **translation:** update translation domain for Wipöp settings and checkout button ([3f3b08c](https://github.com/wipop-bbva/wipop-prestashop/commit/3f3b08cd34f8dd4b11ba7f32cd0b76d21d4c8231))

## 1.0.0 (2026-03-16)

### Features

* initial prestashop module scaffold ([bd2d175](https://github.com/wipop-bbva/wipop-prestashop/commit/bd2d1750383c93a05837600c0dca7c3af972cf54))
* **module:** add wipop-php-client dependency to composer.json ([b4168ca](https://github.com/wipop-bbva/wipop-prestashop/commit/b4168ca33e9e1c4b1f6ceb4cb9c3901c12422204))
* **module:** bootstrap wipop payment skeleton ([3ab6941](https://github.com/wipop-bbva/wipop-prestashop/commit/3ab6941277b737f255bc1e886bf1dd4b6d8752a6))
* **module:** refactor payment method handling to use library as the source of true for payment methods ([242f1e2](https://github.com/wipop-bbva/wipop-prestashop/commit/242f1e2679cc1036ad4da90b8d970a2c0a552737))
* **wipop:** configure PSR4 autoloading ([20dcbb9](https://github.com/wipop-bbva/wipop-prestashop/commit/20dcbb97160ac497094fb5958891f724f80daf87))

### Bug Fixes

* **ci:** align ci workflow with tools vendor path ([c2013e3](https://github.com/wipop-bbva/wipop-prestashop/commit/c2013e3a3877590c162c9e3e5ae93346925fea54))
* **release:** add conventional-changelog-conventionalcommits dependency for semantic release ([b74fc59](https://github.com/wipop-bbva/wipop-prestashop/commit/b74fc59a5fe2c8d9f5639728ab23a61546244885))
* **vendor:** refactor vendor directory to match WooCommerce plugin style ([c7f8473](https://github.com/wipop-bbva/wipop-prestashop/commit/c7f84739ec9fab64aa4829b307a7cafffb3739cf))
