# UPGRADE GUIDE

## Upgrading from v9.x to v10.x

- `ApiResourceDataTable` support dropped, use `CollectionDataTable` instead.
- `queryBuilder()` deprecated method removed, use `query()` instead.
- Methods signature were updated to PHP8 syntax, adjust as needed if you extended the package.

## Upgrading from v8.x to v9.x

No breaking changes with only a bump on php version requirements.

## Upgrading from v7.x to v8.x

There are breaking changes since DataTables v8.x. If you are upgrading from v7.x to v8.x, please see [upgrade guide](https://yajrabox.com/docs/laravel-datatables/master/upgrade).

## Upgrading from v6.x to v7.x
  - composer require yajra/laravel-datatables-oracle 
  - composer require yajra/laravel-datatables-buttons
  - php artisan vendor:publish --tag=datatables --force
  - php artisan vendor:publish --tag=datatables-buttons --force

## Upgrading from v5.x to v6.x
  - Change all occurrences of `yajra\Datatables` to `Yajra\Datatables`. (Use Sublime's find and replace all for faster update). 
  - Remove `Datatables` facade registration.
  - Temporarily comment out `Yajra\Datatables\DatatablesServiceProvider`.
  - Update package version on your composer.json and use `yajra/laravel-datatables-oracle: ~6.0`
  - Uncomment the provider `Yajra\Datatables\DatatablesServiceProvider`. 
