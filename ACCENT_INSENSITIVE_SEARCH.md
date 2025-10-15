# Accent-Insensitive Search

This feature allows DataTables to perform accent-insensitive searches, which is particularly useful for Portuguese and other languages that use accented characters.

## Problem

Users often don't type accents when searching but expect to find results with accented characters. For example:
- Searching for "simoes" should find "Simões"
- Searching for "joao" should find "João"
- Searching for "sao paulo" should find "São Paulo"

## Configuration

To enable accent-insensitive search, update your `config/datatables.php` file:

```php
return [
    'search' => [
        'ignore_accents' => true, // Enable accent-insensitive search
        // ... other search options
    ],
    // ... other configurations
];
```

## Supported Characters

This feature currently supports Portuguese Brazilian accents:

| Accented Characters | Base Character |
|-------------------|----------------|
| Ã/ã/Á/á/À/à/Â/â   | a              |
| É/é/Ê/ê           | e              |
| Í/í               | i              |
| Ó/ó/Ô/ô/Õ/õ       | o              |
| Ú/ú               | u              |
| Ç/ç               | c              |

## How It Works

When `ignore_accents` is enabled:

1. **For Collection DataTables**: Both the search term and the data values are normalized to remove accents before comparison
2. **For Query/Eloquent DataTables**: Database-specific functions are used to normalize characters in SQL queries

### Database Support

- **MySQL**: Uses cascaded `REPLACE()` functions
- **PostgreSQL**: Uses `UNACCENT()` extension if available, falls back to `REPLACE()`
- **SQLite**: Uses cascaded `REPLACE()` functions  
- **SQL Server**: Uses cascaded `REPLACE()` functions

## Examples

### Basic Usage

```php
use DataTables;

public function getUsersData()
{
    return DataTables::of(User::query())
        ->make(true);
}
```

With `ignore_accents => true` in config:
- Searching "simoes" will match "Simões"
- Searching "jose" will match "José" 
- Searching "coracao" will match "Coração"

### Collection Example

```php
$users = collect([
    ['name' => 'João Silva'],
    ['name' => 'María González'], 
    ['name' => 'José Santos']
]);

return DataTables::of($users)->make(true);
```

With accent-insensitive search enabled:
- Searching "joao" will find "João Silva"
- Searching "jose" will find "José Santos"

## Performance Considerations

- **Collection DataTables**: Minimal impact as normalization is done in PHP
- **Query DataTables**: May have slight performance impact due to database function calls
- Consider adding database indexes on frequently searched columns
- The feature can be toggled per DataTable instance if needed

## Extending Support

To add support for other languages/accents, modify the `Helper::normalizeAccents()` method in `src/Utilities/Helper.php`:

```php
public static function normalizeAccents(string $value): string
{
    $map = [
        // Portuguese
        'Ã' => 'a', 'ã' => 'a', 'Á' => 'a', 'á' => 'a',
        // Add more mappings for other languages
        'Ñ' => 'n', 'ñ' => 'n', // Spanish
        'Ü' => 'u', 'ü' => 'u', // German
        // ... more mappings
    ];
    return strtr($value, $map);
}
```

## Testing

The feature includes comprehensive unit tests. To run them:

```bash
./vendor/bin/phpunit tests/Unit/HelperTest.php --filter test_normalize_accents
```

## Backward Compatibility

This feature is fully backward compatible:
- Default configuration has `ignore_accents => false`
- Existing applications continue to work unchanged
- No breaking changes to existing APIs