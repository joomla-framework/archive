# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## Entries outside the destination

`Zip` and `Tar` refuse to write an entry whose path resolves outside the destination directory, for
example `../../index.php`, and throw a `Joomla\Archive\Exception\UnsafeArchiveEntryException`
(code `100`). It extends `OutOfBoundsException` and therefore `RuntimeException`, so it is caught by
handlers for either, whichever ZIP implementation (`ext-zip` or the PHP fallback) is used:

```php
use Joomla\Archive\Archive;
use Joomla\Archive\Exception\UnsafeArchiveEntryException;

try {
    (new Archive())->extract($upload, $destination);
} catch (UnsafeArchiveEntryException $e) {
    // The archive tried to write outside of $destination
}
```

Directory entries are extracted as well, so empty directories in an archive are kept.
