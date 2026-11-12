# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## Decompressing gzip and bzip2 files through streams

By default `Gzip` and `Bzip2` read the whole archive into memory. With the `use_streams` option they
decompress in chunks through `Joomla\Filesystem\Stream` instead:

```php
use Joomla\Archive\Gzip;

(new Gzip(['use_streams' => true]))->extract('/path/to/dump.sql.gz', '/path/to/dump.sql');
```

The streams are opened without a path prefix, so this works outside the Joomla CMS as well: no
`JPATH_ROOT` constant is needed, and both paths are used exactly as given.
