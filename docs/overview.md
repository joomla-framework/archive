# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It
knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter,
and then extract via tar.

```bash
composer require joomla/archive
```

## Extracting

`Archive::extract()` picks the adapter from the file extension:

```php
use Joomla\Archive\Archive;

$archive = new Archive(['tmp_path' => sys_get_temp_dir()]);

$archive->extract('/path/to/package.zip', '/path/to/destination');
$archive->extract('/path/to/backup.tar.gz', '/path/to/destination');
```

`tmp_path` is required for the gzip and bzip2 paths, which decompress to a temporary file before
handing the result to the tar adapter. An unknown extension raises
`Joomla\Archive\Exception\UnknownArchiveException`.

To use one adapter directly:

```php
use Joomla\Archive\Zip;

$zip = new Zip();

if (!$zip::isSupported()) {
    throw new \RuntimeException('The zip adapter is not available in this environment.');
}

$zip->extract('/path/to/package.zip', '/path/to/destination');
```

`Archive::getAdapter($type)` returns the same object the dispatcher would use, and
`setAdapter($type, $class)` replaces one.

## The adapters

| Class | Handles | `isSupported()` requires |
|---|---|---|
| `Archive\Zip` | `.zip` | `ext-zip` for the fast path; falls back to a pure-PHP reader with `ext-zlib` |
| `Archive\Tar` | `.tar` | nothing |
| `Archive\Gzip` | `.gz`, `.gzip`, `.tgz` | `ext-zlib` |
| `Archive\Bzip2` | `.bz2`, `.bzip2`, `.tbz2` | `ext-bz2` |

All four implement `Joomla\Archive\ExtractableInterface`, so a custom adapter only needs
`extract()` and the static `isSupported()`.

## Creating archives

Only ZIP can be written:

```php
$zip = new Zip();
$zip->create('/path/to/out.zip', [
    ['name' => 'readme.txt', 'data' => 'Hello'],
    ['name' => 'dir/file.txt', 'data' => file_get_contents($file)],
]);
```

`Tar`, `Gzip` and `Bzip2` are extract-only.

## Things to know before you extract untrusted archives

Entry names and sizes in an archive are attacker-controlled whenever the archive itself is. The
package guards the obvious path, but not everything.

**The containment check compares path prefixes.** Both `Zip` and `Tar` verify that an entry stays
inside the destination with a string prefix test rather than a path-boundary test, so a sibling
directory whose name starts with the same characters is accepted:

| Destination | Entry resolves to | Accepted |
|---|---|---|
| `/var/www/uploads` | `/var/www/uploads/a.txt` | yes, correctly |
| `/var/www/uploads` | `/var/www/uploads_public/shell.php` | **yes, incorrectly** |

Extract into a directory whose name is not a prefix of a sibling — a freshly created temporary
directory is the simplest way — and move the result afterwards:

```php
$staging = sys_get_temp_dir() . '/extract-' . bin2hex(random_bytes(8));
mkdir($staging, 0700);

$archive->extract($uploaded, $staging);
// inspect $staging, then move what you want to keep
```

Note also that the check is lexical: it does not resolve symlinks, so a symlink already present in
the destination is followed.

**There is no limit on the extracted size.** No adapter compares the declared size against the
compressed one, caps the total, or limits the number of entries, and the ZIP and gzip paths read
whole entries into memory. A small archive can therefore exhaust `memory_limit` or fill the disk.
Check before extracting:

```php
$zip = new \ZipArchive();

if ($zip->open($file) === true) {
    $total = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat   = $zip->statIndex($i);
        $total += $stat['size'];

        if ($total > 50 * 1024 * 1024 || $zip->numFiles > 1000) {
            throw new \RuntimeException('Archive too large');
        }
    }

    $zip->close();
}
```

**The type comes from the file name, not the content.** A `.gz` file that contains a single
non-tar file is written out under the archive's own base name, so `shell.php.gz` becomes
`shell.php` in the destination. Decide what extensions you accept after extraction, not before.

**Temporary files use predictable names.** The gzip and bzip2 paths build the temporary file name
from `uniqid()`. On a shared `tmp_path`, prefer a private directory you create yourself and pass
that as `tmp_path`.

**`Zip::checkZipData()` is a weak test.** It reports success if the ZIP signature appears anywhere
in the data, not only at the start.

**TAR symlink and hardlink entries are skipped silently.** Only regular files and directories are
extracted — which is the right call for safety, but a link-bearing archive extracts incompletely
without any message.
