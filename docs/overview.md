# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## Listing the entries of an archive

`Zip` and `Tar` implement `Joomla\Archive\ListableInterface`. `listContents()` returns the entries
without extracting anything, so names and sizes can be checked first:

```php
use Joomla\Archive\Zip;

foreach ((new Zip())->listContents('/path/to/upload.zip') as $entry) {
    // $entry['name']: path as stored in the archive, not sanitised (it can contain "../")
    // $entry['size']: uncompressed size as declared by the archive
    // $entry['type']: "file", "directory" or "other" (links, devices, ...)
}
```

The sizes are the ones the archive declares; a crafted archive can declare less than it holds.

`Zip::checkZipData()` accepts data that ends with a ZIP end of central directory record (plus an
optional comment) pointing to a central directory inside the data. Data that merely contains a ZIP
signature somewhere is not accepted.
