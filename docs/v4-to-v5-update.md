## Updating from v4 to v5

The following changes were made to the Archive package between v4 and v5.

### Native types in `ExtractableInterface`

`ExtractableInterface` declares native parameter and return types, and the four adapters (`Bzip2`,
`Gzip`, `Tar`, `Zip`) implement them:

```php
public function extract(string $archive, string $destination): bool;

public static function isSupported(): bool;
```

Custom adapters implementing `ExtractableInterface` must add the same types to their `extract()`
and `isSupported()` methods, and classes extending one of the adapters must keep them compatible
when overriding these methods:

```php
// Before
public function extract($archive, $destination)
public static function isSupported()

// After
public function extract(string $archive, string $destination): bool
public static function isSupported(): bool
```

Callers passing something other than a string now get a `TypeError` (with `strict_types`) or a
coerced string instead of the adapter's own error.
