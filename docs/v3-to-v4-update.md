# Updating from v3 to v4

Release 4.0.0 raises the PHP requirement. The rest of the changes are internal type corrections
that surface only in edge cases.

## At a glance

| | v3 (3.0.4) | v4 (4.0.0) |
|---|---|---|
| PHP | `^8.1.0` | `^8.3.0` |
| Method signatures | — | unchanged |
| `$data` property initial value | `null` | `''` |

## Minimum supported PHP version raised

All Framework packages now require **PHP 8.3** or newer.

## Internal type corrections

Three small fixes, none of which changes a method signature:

* The `$data` property of `Bzip2`, `Gzip`, `Tar` and `Zip` is now typed `?string` in its docblock
  and initialised to `''` instead of `null`. This removes a set of PHP 8.1 deprecation notices for
  passing `null` to string functions during extraction.
* `Tar::readTarData()` casts the block offset to `int` before using it
  (`(int) ceil(octdec($info['size']) / 512) * 512`), which removes an implicit float-to-int
  deprecation notice on archives whose entry sizes are not multiples of 512.
* `Archive::getAdapter()` guards its support check with `is_object($class)`, so a preconfigured
  adapter instance is no longer asked for a static `isSupported()` in a way that could fail.

If you subclassed an adapter and relied on `$this->data` being `null` before extraction, test for
an empty string instead:

```php
// Before
if ($this->data === null) { … }

// After
if ($this->data === '') { … }
```

## Dependency changes

| Package | v3 (3.0.4) | v4 (4.0.0) |
|---|---|---|
| `php` | `^8.1.0` | `^8.3.0` |
| `joomla/filesystem` | `^3.0` | `^4.0` |

`ext-bz2`, `ext-zip` and `ext-zlib` remain optional.

## Worth doing while you are here

If this package extracts archives that users upload, this is a good moment to add the size and
containment checks described in [the overview](overview.md#things-to-know-before-you-extract-untrusted-archives).
Neither is provided by the package.
