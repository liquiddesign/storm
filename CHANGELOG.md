<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

## [2.0.16](https://github.com/liquiddesign/storm/compare/v2.0.15...v2.0.16) (2025-05-02)

### Bug Fixes

* Remove unnecessary parameter from fetchGenerator method ([624f5c](https://github.com/liquiddesign/storm/commit/624f5cb4322beac4b0a745113bb3518c05b5f8cd))


---

## [2.0.15](https://github.com/liquiddesign/storm/compare/v2.0.14...v2.0.15) (2025-05-02)

### Features

* Add SelectAttribute and methods for dynamic SELECT clause generation ([4b3b6b](https://github.com/liquiddesign/storm/commit/4b3b6b33e9e73c224880498cbf2083e6ee4bd07f))
* Better tracy ([9cc1c2](https://github.com/liquiddesign/storm/commit/9cc1c2c5988ce95b2b9f1a9d424c0148a8c49faf), [724170](https://github.com/liquiddesign/storm/commit/724170e7b71716cb288a7f23d06511d567c72aa9), [a7c7f5](https://github.com/liquiddesign/storm/commit/a7c7f596c4b915be78be16106a839da5cc431fc9), [466633](https://github.com/liquiddesign/storm/commit/466633dfab04e017757ed43cf954ed3fee104978))

### Styles

* Fix ([2c7f5d](https://github.com/liquiddesign/storm/commit/2c7f5d2bad7233c1ae46f99c33a93b64d32a308c))
* Fix phpstan ([6b09f4](https://github.com/liquiddesign/storm/commit/6b09f4195528b605e6dbfe394904c20376bc0c4b))


---

## [2.0.14](https://github.com/liquiddesign/storm/compare/v2.0.13...v2.0.14) (2025-03-20)

### Features

* Remove unnecessary brackets ([f51f64](https://github.com/liquiddesign/storm/commit/f51f64a941ed48cf8124b42001d5b595c3d974f8))


---

## [2.0.13](https://github.com/liquiddesign/storm/compare/v2.0.12...v2.0.13) (2025-03-19)

### Bug Fixes

* Revert types ([de3c8f](https://github.com/liquiddesign/storm/commit/de3c8f8ad1b1bd57ab1341be38753769685015e6))

### Builds

* Fix github action ([083fc7](https://github.com/liquiddesign/storm/commit/083fc7d0e318d2c8ddd6ea0e8fc838d3f40c9e06))


---

## [2.0.12](https://github.com/liquiddesign/storm/compare/v2.0.11...v2.0.12) (2025-03-19)

### Features

* Enhance type declarations and add whereExpression method to collections ([7fd696](https://github.com/liquiddesign/storm/commit/7fd69696a8d4a37c6d92b01028aaf91e56a2f336))

### Code Refactoring

* Fixing unclear error message in connection ([5af579](https://github.com/liquiddesign/storm/commit/5af579c7fc953528a6e8143c3ef4c1b84a982018))


---

## [2.0.11](https://github.com/liquiddesign/storm/compare/v2.0.10...v2.0.11) (2025-02-13)

### Features

* Add code quality check script and modify UUID format in Connection class ([e0efe7](https://github.com/liquiddesign/storm/commit/e0efe7cf07b97293a9c34086da287e85dea1fdcc))
* Add UUID generation support using Ramsey library ([823c42](https://github.com/liquiddesign/storm/commit/823c42ca9b9526b0b05045b538ad6b38fcfd6430))
* Refactor UUID generation methods for clarity and add UUID v7 support ([bf6257](https://github.com/liquiddesign/storm/commit/bf625793fc38a40e31e8ecb268dc21e580b21a8a))

### Styles

* Fix ([2610a1](https://github.com/liquiddesign/storm/commit/2610a151eaa385ce6993015da406c14b40c3fb49))


---

## [2.0.10](https://github.com/liquiddesign/storm/compare/v2.0.9...v2.0.10) (2025-02-11)

### Features

* Simplify trace location logging in Connection class ([e34bad](https://github.com/liquiddesign/storm/commit/e34bad4ada7b41b06e5a97ca0622bbe6607ca033))

### Bug Fixes

* Trigger_error is deprecated in PHP 8.4 ([7be5d5](https://github.com/liquiddesign/storm/commit/7be5d51c29d9ecba2b2386de32d3557b37423143))


---

## [2.0.9](https://github.com/liquiddesign/storm/compare/v2.0.8...v2.0.9) (2025-01-11)

### Features

* SetLink for replace PDO connection id Storm Connection object ([6fe93d](https://github.com/liquiddesign/storm/commit/6fe93ddae83fb8957ca21218f37d4fe9caa30a1b))


---

## [2.0.6](https://github.com/liquiddesign/storm/compare/v2.0.5...v2.0.6) (2024-06-21)

### Bug Fixes

* Fixed init union collection ([59cfaa](https://github.com/liquiddesign/storm/commit/59cfaa03c8a3873ccfa096b720751f8684de83b6))


---

## [2.0.5](https://github.com/liquiddesign/storm/compare/v2.0.4...v2.0.5) (2024-03-20)

### Bug Fixes

* Return types ([241176](https://github.com/liquiddesign/storm/commit/241176f0c2c296c99e06b2e73681c6b13cc7ec62))


---

## [2.0.4](https://github.com/liquiddesign/storm/compare/v2.0.3...v2.0.4) (2024-03-08)

### Builds

* Support >=8.0 ([a082ac](https://github.com/liquiddesign/storm/commit/a082acc7afb4ba9092a96f7af11768c5b7259a4c))


---

## [2.0.3](https://github.com/liquiddesign/storm/compare/v2.0.2...v2.0.3) (2024-03-07)


---

## [2.0.2](https://github.com/liquiddesign/storm/compare/v2.0.1...v2.0.2) (2024-03-07)

### Builds

* Support >=8.0 ([eb0aaf](https://github.com/liquiddesign/storm/commit/eb0aaff4eea478d0eef37b9dab47be31e5216fe8), [b98748](https://github.com/liquiddesign/storm/commit/b98748363c24eff0a5800b03d967a72a5c5e4942), [e42878](https://github.com/liquiddesign/storm/commit/e428786d26302e91870ab9d36b28117d0c40f8b1), [2459b9](https://github.com/liquiddesign/storm/commit/2459b907a711c7ece812aaa4410a2b268fd5f81f), [5d6803](https://github.com/liquiddesign/storm/commit/5d680307a5e93ecc0d6d27f53ac7342621d16dd0), [f91957](https://github.com/liquiddesign/storm/commit/f919571320fc6281f9250ef75ce0eb618bcad89b), [b3bb1e](https://github.com/liquiddesign/storm/commit/b3bb1e4c21db8637925001b429614d931940ed84))


---

## [2.0.1](https://github.com/liquiddesign/storm/compare/v2.0.0...v2.0.1) (2024-03-07)

### Builds

* Support >=8.0 ([47b829](https://github.com/liquiddesign/storm/commit/47b829aba2306bf10ea6c591816899d666ec381d))


---

## [2.0.0](https://github.com/liquiddesign/storm/compare/v1.2.24...v2.0.0) (2024-03-07)

### Styles

* Code style 3.0 ([be23e3](https://github.com/liquiddesign/storm/commit/be23e339461b88bb6b1266bf90f44a4ed7274eeb))


---

