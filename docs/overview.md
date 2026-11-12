# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## Limits against decompression bombs

A small archive can expand to gigabytes. When you extract archives you do not control, set one or
more of these options; each is disabled when missing or `0`:

| Option | Limit |
|---|---|
| `max_files` | Maximum number of entries |
| `max_size` | Maximum number of bytes extracted in total |
| `max_ratio` | Maximum ratio between the extracted bytes and the size of the archive file |

```php
use Joomla\Archive\Archive;

$archive = new Archive(['max_files' => 1000, 'max_size' => 50 * 1024 * 1024, 'max_ratio' => 100]);
$archive->extract($upload, $destination);
```

The options can be passed to `Archive` or to an adapter directly. Exceeding a limit throws a
`Joomla\Archive\Exception\ExtractionLimitException` (a `RuntimeException`). ZIP and TAR archives are
checked against the sizes in their headers before anything is written, and a ZIP entry that turns
out larger than declared is rejected while it is read. gzip and bzip2 data is decompressed in chunks
and the extraction stops as soon as a limit is exceeded. For a `.tar.gz` or `.tar.bz2`, the limits
apply to the decompressed tarball (including its headers) as well as to the files in it.

Note that `Archive::extract()` reports errors of the gzip and bzip2 adapters by returning `false`.
