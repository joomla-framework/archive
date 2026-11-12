# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## gzip and bzip2 files

`Archive::extract()` decompresses `.gz`/`.gzip`/`.tgz` and `.bz2`/`.bzip2`/`.tbz2` files into a
temporary file in the `tmp_path` option (default: the system temporary directory) first. The
temporary file is created with `tempnam()`, so its name cannot be predicted, and it is removed
again even when the extraction fails. Errors are reported as a `RuntimeException`, as for ZIP and
TAR archives.

If the extension is `tgz` or `tbz2` (in any case) or the name contains `.tar`, the decompressed file
is extracted as a TAR archive. Otherwise it is a single file and is copied into the destination
under the archive's name without the compression extension: `dump.sql.gz` becomes `dump.sql`, and
an uploaded `shell.php.gz` becomes `shell.php`. When you extract uploads, set the
`check_single_file` option to run `Joomla\Filesystem\File::isSafeFile()` on such a file first; an
unsafe file raises a `RuntimeException` and is not written. Pass an array instead of `true` to
hand options to `isSafeFile()`:

```php
use Joomla\Archive\Archive;

$archive = new Archive(['check_single_file' => true]);
$archive->extract('/path/to/upload.gz', '/path/to/destination');
```
