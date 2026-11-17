# Updating from v2 to v3

Release 3.0.0 raises the PHP requirement and reformats the codebase. **No public or protected
method signature changed**, so code written against 2.x keeps working on PHP 8.1.

## At a glance

| | v2 (2.0.x) | v3 (3.0.0) |
|---|---|---|
| PHP | `^7.2.5` | `^8.1.0` |
| Public API | — | unchanged |
| Coding style | Joomla Coding Standard | PSR-12 |

## Minimum supported PHP version raised

All Framework packages now require **PHP 8.1** or newer.

## No API changes

`Archive`, the four adapters, `ExtractableInterface` and the exception classes have the same
signatures in 3.0.0 as in 2.0.0.

## Codebase converted to PSR-12

The package was reformatted from the Joomla Coding Standard to PSR-12. This touches nearly every
line and changes no behaviour.

## Dependency changes

| Package | v2 (2.0.x) | v3 (3.0.0) |
|---|---|---|
| `php` | `^7.2.5` | `^8.1.0` |
| `joomla/filesystem` | `^2.0@rc` | `^3.0` |

`ext-bz2`, `ext-zip` and `ext-zlib` remain optional, in `suggest`, one per adapter.
