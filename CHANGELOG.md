# [12.7.0](https://github.com/yajra/laravel-datatables/compare/v12.6.3...v12.7.0) (2026-02-20)


### Bug Fixes

* address phpstan and fork phplint checkout ([7c416ef](https://github.com/yajra/laravel-datatables/commit/7c416ef38347d23bc52eca5439f0889c4e493440))
* allow editing DT_RowIndex via editColumn ([fd161f0](https://github.com/yajra/laravel-datatables/commit/fd161f019f54fc13995bd4f2664f12e4f8aa3317))
* cast index column config for static analysis ([468f44c](https://github.com/yajra/laravel-datatables/commit/468f44ce845a4a40734eaaca9d5753095ced013a))
* **ci:** support fork PR checkout in pint workflow ([fe30c00](https://github.com/yajra/laravel-datatables/commit/fe30c005f15eccc5c1d5314199f35740a5ed2192))
* **ci:** update memcached action to v8 ([c9e7f15](https://github.com/yajra/laravel-datatables/commit/c9e7f15ea3833311664c3c818e1f29c2c23918f9))


### Features

* support LengthAwarePaginator engine ([b8f9bb3](https://github.com/yajra/laravel-datatables/commit/b8f9bb33189d069d81901af59c73057aa8d84abd))

## [12.6.3](https://github.com/yajra/laravel-datatables/compare/v12.6.2...v12.6.3) (2025-12-09)


### Bug Fixes

* [#3266](https://github.com/yajra/laravel-datatables/issues/3266), replace ConfigRepository's type helper methods with get() ([#3267](https://github.com/yajra/laravel-datatables/issues/3267)) ([b9e5f78](https://github.com/yajra/laravel-datatables/commit/b9e5f785851a21779b0c88c2b0eb5c02e9714a76))
* pint :robot: ([e74e6ce](https://github.com/yajra/laravel-datatables/commit/e74e6cea55d552dd4ac66483c73cc0029050f52e))

## [12.6.2](https://github.com/yajra/laravel-datatables/compare/v12.6.1...v12.6.2) (2025-12-02)


### Bug Fixes

* pint :robot: ([41a22c7](https://github.com/yajra/laravel-datatables/commit/41a22c7b52f4d154e6ee8b9c0834c51d20001c00))
* resolve PHPStan array key type errors ([3b5e668](https://github.com/yajra/laravel-datatables/commit/3b5e66832fd4c708c2fc6c4afffc12815c43b248))

## [12.6.1](https://github.com/yajra/laravel-datatables/compare/v12.6.0...v12.6.1) (2025-10-11)


### Bug Fixes

* value when mask uses "/" ([c771900](https://github.com/yajra/laravel-datatables/commit/c77190030c713e5b64c433bd161d9f33a210f22b))

# [12.6.0](https://github.com/yajra/laravel-datatables/compare/v12.5.1...v12.6.0) (2025-10-08)


### Bug Fixes

* replace unsafe eval() with Blade::render() in compileBlade ([7f46d58](https://github.com/yajra/laravel-datatables/commit/7f46d5872b0324493c28ecc8d848c182e88f30e0))


### Features

* add __isset() method to Request for attribute existence check ([33f44d4](https://github.com/yajra/laravel-datatables/commit/33f44d42d284d6ea0a054de81ad5a57c3050867d))

# Laravel DataTables 

## CHANGELOG

### [Unreleased]

### v12.5.1 - 2025-10-02

- fix: ambiguous column in columnControlSearch() method #3252

### v12.5.0 - 2025-10-01

- feat: server-side column control #3251
- fix: https://github.com/yajra/laravel-datatables/issues/3250

### v12.4.2 - 2025-09-09

- fix: remove @internal annotation from orderColumn() method #3248

### v12.4.1 - 2025-08-29

- fix: request handling with playwright / pest 4 #3247

### v12.4.0 - 2025-06-15

- feat: add min search length control #3242
- fix: #3241

### v12.3.1 - 2025-06-10

- fix: support for array notation #3243

### v12.3.0 - 2025-05-17

- feat: add option to enable alias on relation tables #3234
- tests: Add tests to cover prefix detection #3239
- fix: https://github.com/yajra/laravel-datatables/pull/1782

### v12.2.1 - 2025-05-09

- fix: improve prefix detection #3238
- fix: #3237

### v12.2.0 - 2025-05-08

- feat: add relation resolver param to order callback #3232
- fix: improve column alias detection #3236
- fix: #3235

### v12.1.2 - 2025-05-07

- fix: prevent prefixing null/empty string #3233

### v12.1.1 - 2025-05-05

- fix: prevent ambiguous column names #3227

### v12.1.0 - 2025-04-28

- feat: add relation resolver param to filter callbacks #3229

### v12.0.1 - 2025-04-07

- fix: query results improvements #3224

### v12.0.0 - 2025-02-26

- feat: Laravel v12 Compatibility #3217
- fix: prevent duplicate table name errors #3216

[Unreleased]: https://github.com/yajra/laravel-datatables/compare/v12.0.0...master
