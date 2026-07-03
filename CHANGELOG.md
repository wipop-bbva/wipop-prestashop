## [1.15.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.14.1...v1.15.0) (2026-07-03)

### Features

* **checkout:** implement ReturnUrlToken for secure return URL handling ([478cedb](https://github.com/wipop-bbva/wipop-prestashop/commit/478cedb5973a0f18f73c606e84343e254d55eb98))

### Bug Fixes

* **admin:** simplify recurring product settings ([8309260](https://github.com/wipop-bbva/wipop-prestashop/commit/8309260d738a609d32a1e97f85ba46f03850f504))
* **checkout:** use native PrestaShop return notifications ([4a09aee](https://github.com/wipop-bbva/wipop-prestashop/commit/4a09aee01e38bc5cb1d3ef94b5ed28215c1b78a5))
* **install:** update authorized order state label to remove 'manual' ([4a583cf](https://github.com/wipop-bbva/wipop-prestashop/commit/4a583cfe4e4b8a967d9c2d0b8b0e3da1c007da1c))

## [1.14.1](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.14.0...v1.14.1) (2026-07-03)

### Bug Fixes

* **docs:** update PrestaShop version and credential names in README and LEEME ([987c1c2](https://github.com/wipop-bbva/wipop-prestashop/commit/987c1c24171c960cfd1aab7d1a06a64a37ea89da))

## [1.14.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.13.2...v1.14.0) (2026-07-03)

### Features

* **admin:** align Wipop settings labels and tooltips ([b6f889f](https://github.com/wipop-bbva/wipop-prestashop/commit/b6f889f67d672740e47c4af45fd9b1457ca7d3f7))

### Bug Fixes

* **checkout:** use native notifications for start errors ([0a665ce](https://github.com/wipop-bbva/wipop-prestashop/commit/0a665ce8ba96371461529348c4dcc5e294ad62d1))

## [1.13.2](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.13.1...v1.13.2) (2026-07-02)

### Bug Fixes

* **admin:** resolve CSRF token manager for recurring actions ([30d1eb8](https://github.com/wipop-bbva/wipop-prestashop/commit/30d1eb8871a4701bea6800c61afe6c92e1bbc237))
* **refund:** preserve requested amount for partial refunds ([8266d2f](https://github.com/wipop-bbva/wipop-prestashop/commit/8266d2f97ab595289dfcbe5ae158169c3e1f3e83))

## [1.13.1](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.13.0...v1.13.1) (2026-06-30)

### Bug Fixes

* **admin:** support CSRF token manager service aliases ([ca4fccd](https://github.com/wipop-bbva/wipop-prestashop/commit/ca4fccd526395bdcb272e0a41527cf77c577d6e3))
* **grumphp:** remove unnecessary git hook variables from configuration ([18c90d0](https://github.com/wipop-bbva/wipop-prestashop/commit/18c90d0696379c15def48aa0469c1b20dd1c535b))
* **install:** reuse Wipop authorized order state ([a78815f](https://github.com/wipop-bbva/wipop-prestashop/commit/a78815ffe1a695e5b86907cfd35911741e512b53))
* **locking:** normalize PrestaShop GET_LOCK results ([5a86c30](https://github.com/wipop-bbva/wipop-prestashop/commit/5a86c3028c84c7d0b5b823ae4eaf4f5b1b00dbad))
* **module:** align Addons metadata and autoload ([f68f98a](https://github.com/wipop-bbva/wipop-prestashop/commit/f68f98abc306b1d8718992df11934f0e302971b1))
* **module:** build recurring services safely in legacy hooks ([621f2d0](https://github.com/wipop-bbva/wipop-prestashop/commit/621f2d08925b1e9a44087cde6e53adb011fca410))
* **preauthorization:** finalize manual authorization flows consistently ([cdac2b5](https://github.com/wipop-bbva/wipop-prestashop/commit/cdac2b53a712b48cd8a208caf7a292a23adbddc7))
* **prestashop:** match legacy configuration signatures ([f752aee](https://github.com/wipop-bbva/wipop-prestashop/commit/f752aeee81813edc7659489c24028cf0c0b04b04))
* **prestashop:** update PS version compatibility to support up to 9.1.999 ([9981366](https://github.com/wipop-bbva/wipop-prestashop/commit/99813664708275ecc2e0dbd23fa7a902f944377e))
* **refund:** restrict automatic Wipop refunds to card payments ([49b0ad5](https://github.com/wipop-bbva/wipop-prestashop/commit/49b0ad56bc943eafd6a04feb0b10b390440878c5))
* **security:** harden module package output ([7aa2d4d](https://github.com/wipop-bbva/wipop-prestashop/commit/7aa2d4d449d96bcfe18d5c55c03fbd6d06b086b9))
* **webhook:** avoid SDK serializer for incoming transactions ([5ce9f6a](https://github.com/wipop-bbva/wipop-prestashop/commit/5ce9f6a0c6f9d9c596c313e5dd7bff2ef736e523))
* **webhook:** harden Wipop webhook correlation ([fd0a783](https://github.com/wipop-bbva/wipop-prestashop/commit/fd0a783761456b057b0e7f7bb36936a3eef1cc38))

## [1.13.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.12.0...v1.13.0) (2026-06-17)

### Features

* **admin:** expose recurring subscription controls ([3334120](https://github.com/wipop-bbva/wipop-prestashop/commit/3334120a903f29ff798f4ac58d901b9787d58d6c))
* **checkout:** support recurring card setup ([3ca31b0](https://github.com/wipop-bbva/wipop-prestashop/commit/3ca31b00d196d5df0c6644692fc175155c7c92ae))
* **recurring:** add product recurring settings ([3c6aecc](https://github.com/wipop-bbva/wipop-prestashop/commit/3c6aeccf604538ee7e0fe6079492ef707bcf372f))
* **recurring:** add traffic-triggered renewal runner ([189752b](https://github.com/wipop-bbva/wipop-prestashop/commit/189752babf8ddd500d969f6da8d5adabbd4fc587))
* **recurring:** process due renewal charges ([377c19a](https://github.com/wipop-bbva/wipop-prestashop/commit/377c19aad28bfc28dd102b4b6343c8e5bc126ecc))
* **recurring:** stop closed subscriptions and record lifecycle notes ([2c8b9aa](https://github.com/wipop-bbva/wipop-prestashop/commit/2c8b9aa269ad2b9086beddc3945a8b8c5836126c))
* **recurring:** sync subscriptions from webhooks ([94eea4b](https://github.com/wipop-bbva/wipop-prestashop/commit/94eea4b0396899f8272464db5b64dc338c415b07))

### Bug Fixes

* **checkout:** parse PrestaShop request values explicitly ([8bc04b5](https://github.com/wipop-bbva/wipop-prestashop/commit/8bc04b5c24d7cb92ed59f238c5c2906290c11869))
* **checkout:** translate unavailable payment errors ([9ac85e9](https://github.com/wipop-bbva/wipop-prestashop/commit/9ac85e9e8b5f82ae456951758814f6bba677e7b6))
* **config:** allow settings without public secret key ([c56ddac](https://github.com/wipop-bbva/wipop-prestashop/commit/c56ddacd91a29ee1eac4f5e220de80caa178c910))
* **payment:** align database scalar reads with PrestaShop contracts ([f41a9bd](https://github.com/wipop-bbva/wipop-prestashop/commit/f41a9bdf1dc3fde252fdce36f62825bc4b645c41))
* **preauthorization:** normalize operation status handling ([c498392](https://github.com/wipop-bbva/wipop-prestashop/commit/c4983920ffecd90d473a98d3b1505674424172ab))
* **recurring:** harden renewal charge submission recovery ([2afbd06](https://github.com/wipop-bbva/wipop-prestashop/commit/2afbd06fa3dab784a24eb456ec592a4ea36f4a5b))
* **webhook:** hydrate nested customer identifiers ([110a1c1](https://github.com/wipop-bbva/wipop-prestashop/commit/110a1c1dc7270608d54adf5a92c927a6d91b549e))

## [1.12.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.11.1...v1.12.0) (2026-06-09)

### Features

* **account:** let customers manage saved cards ([069cb7d](https://github.com/wipop-bbva/wipop-prestashop/commit/069cb7d6d934bfebab7d26d226d96bc1317a6e6e))
* **card:** add scoped stored-card persistence ([c95b359](https://github.com/wipop-bbva/wipop-prestashop/commit/c95b359657df45c7b9e6ad9cfea025be9d7795a3))
* **checkout:** support card COF selection ([f313a37](https://github.com/wipop-bbva/wipop-prestashop/commit/f313a37dc294debbbe5df4c2adf5bf8dc6a0a007))
* **webhook:** sync saved cards from confirmations ([81a928a](https://github.com/wipop-bbva/wipop-prestashop/commit/81a928a882d969d1aced44ca0b741791bb7ee8e2))

## [1.11.1](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.11.0...v1.11.1) (2026-06-05)

### Bug Fixes

* **sql:** remove LIMIT clauses from SQL queries ([1a9226c](https://github.com/wipop-bbva/wipop-prestashop/commit/1a9226c6a9d82865a9d60db0fe31033a2ecf110d))

## [1.11.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.10.0...v1.11.0) (2026-06-03)

### Features

* **admin:** expose preauthorization order actions ([9cc7d6c](https://github.com/wipop-bbva/wipop-prestashop/commit/9cc7d6c7fb93aca50a8ed5521656fdd99755c93f))
* **config:** add manual card capture mode ([f6fd116](https://github.com/wipop-bbva/wipop-prestashop/commit/f6fd1161ee38ccd832ab0cdf82c483037f2aee5f))
* **payment:** persist preauthorization attempts ([825d892](https://github.com/wipop-bbva/wipop-prestashop/commit/825d89284b46fc7218caaf2fc8b997458db3ab79))
* **preauthorization:** capture and void authorized Wipop payments ([3add318](https://github.com/wipop-bbva/wipop-prestashop/commit/3add31800946dc0bd55bb979e26ee0f477abb849))
* **webhook:** synchronize preauthorization operation webhooks ([bcf51dd](https://github.com/wipop-bbva/wipop-prestashop/commit/bcf51ddbf0d23a797fb43e1abfc2a6c0038a0283))

### Bug Fixes

* **refund:** refund captured transaction after manual capture ([ea14ac0](https://github.com/wipop-bbva/wipop-prestashop/commit/ea14ac033b0eab7b91c572d5c102f8690841146c))

## [1.10.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.9.0...v1.10.0) (2026-05-30)

### Features

* **admin:** configure and verify Wipop payment settings ([7d189a0](https://github.com/wipop-bbva/wipop-prestashop/commit/7d189a09978e699f44cb3e1544997f8e65c3253a))
* **checkout:** respect configured Wipop payment methods ([b3acb01](https://github.com/wipop-bbva/wipop-prestashop/commit/b3acb01f649e317e7ddcf3b032a004eddaba778b))

## [1.9.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.8.0...v1.9.0) (2026-05-29)

### Features

* **admin:** add Wipop manual refund order action ([4d7b805](https://github.com/wipop-bbva/wipop-prestashop/commit/4d7b805268677ba4312146aaeb7621cbd370d38b))
* **admin:** improve configuration secret handling ([3b9f154](https://github.com/wipop-bbva/wipop-prestashop/commit/3b9f15486a964ea840fac27e5c1f9c2699bd57e4))
* **checkout:** show Wipop payment return details ([1ac19e7](https://github.com/wipop-bbva/wipop-prestashop/commit/1ac19e7d4c0d1502d4ee267610ff8d2ce8cc5db6))
* **refund:** add manual refund validation model ([47c0f30](https://github.com/wipop-bbva/wipop-prestashop/commit/47c0f3051a7e9903166b05d58b4459837f32f8ac))
* **refund:** extend payment events for manual refunds ([ff45493](https://github.com/wipop-bbva/wipop-prestashop/commit/ff45493fabe61026e1934bd1531ee080e3d0e030))
* **refund:** process Wipop refunds and credit slips ([0572979](https://github.com/wipop-bbva/wipop-prestashop/commit/05729797c1915e6c75dd4c3db45cc7be492f09b5))

### Bug Fixes

* **payment:** round amounts using explicit cent precision ([01dbbd6](https://github.com/wipop-bbva/wipop-prestashop/commit/01dbbd6d58dfd337a936877e9d01b9e27ea3fc6a))

## [1.8.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.7.0...v1.8.0) (2026-05-21)

### Features

* **refund:** sync Wipop refund webhooks ([821ee0a](https://github.com/wipop-bbva/wipop-prestashop/commit/821ee0ae464c041e5658e3d4cd7b58f2a3129643))

## [1.7.0](https://github.com/wipop-bbva/wipop-prestashop/compare/v1.6.1...v1.7.0) (2026-05-20)

### Features

* **admin:** show Wipop payment summary on orders ([38d0da5](https://github.com/wipop-bbva/wipop-prestashop/commit/38d0da5391b87f993e18cf3927278c3157439f46))

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
