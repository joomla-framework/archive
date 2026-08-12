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
`extract()` and the static `isSupported()`. All four also implement
`Joomla\Archive\CreatableInterface`, which adds `create()`.

## Creating archives

Every adapter takes the same shape: a path to write to, and an array of entries.

```php
use Joomla\Archive\Tar;

$tar = new Tar();
$tar->create('/path/to/out.tar', [
    ['name' => 'readme.txt',   'data' => 'Hello'],
    ['name' => 'dir/file.txt', 'data' => file_get_contents($file)],
]);
```

| Key | Meaning |
|---|---|
| `name` | Path of the entry inside the archive. Required |
| `data` | Raw contents. Defaults to an empty string |
| `time` | Modification time as a UNIX timestamp. Defaults to now |
| `mode` | Permissions as an integer. `Tar` and `Zip` only; defaults to `0644`, or `0755` for a directory |

`create()` returns `true`, throws `\InvalidArgumentException` for entries it cannot store, and
`\RuntimeException` if the archive cannot be written. Entries are assembled in memory, so the whole
archive has to fit into `memory_limit`.

`Tar` and `Zip` treat a `name` ending in `/` as a directory entry and ignore its data. Gzip and
bzip2 take files only, and extractors create the parent directories anyway.

### What ZIP can and cannot store

`Zip` writes the original format, not ZIP64, so the limits of that format apply and are enforced
rather than silently overrun:

| Limit | Value |
|---|---|
| Size of one entry, compressed or not | 4 GiB |
| Total archive size | 4 GiB |
| Number of entries | 65,535 |

Exceeding any of them raises an `\InvalidArgumentException`.

Entry names outside ASCII are stored as UTF-8 and flagged as such, and the archive declares a Unix
host so that readers do not re-encode the name from CP437. That combination is what makes
`Größe.txt` survive a round trip through Info-ZIP, 7-Zip and Windows Explorer.

### Compressing a single file

Gzip and bzip2 are not container formats. Each compresses exactly one stream and has nowhere to
record a file name list, so a single entry is compressed as it stands:

```php
use Joomla\Archive\Gzip;

$gzip = new Gzip();
$gzip->create('/path/to/logo.png.gz', [
    ['name' => 'logo.png', 'data' => file_get_contents('/path/to/logo.png')],
]);
```

The `name` is still worth setting: gzip stores it in the archive header, so `gunzip -N` restores
the original file name. Bzip2 has no such field and ignores it.

### Tarballs

To put more than one file into a gzip or bzip2 archive, it has to be packed into a tar first. That
happens automatically when the archive name says so — the same test `Archive::extract()` applies
when unpacking:

| Archive name | Result |
|---|---|
| `backup.tar.gz`, `backup.tgz` | a tar, compressed with gzip |
| `backup.tar.bz2`, `backup.tbz2` | a tar, compressed with bzip2 |
| `logo.png.gz`, `logo.png.bz2` | the single file, compressed as it is |

```php
$gzip->create('/path/to/backup.tar.gz', [
    ['name' => 'readme.txt',   'data' => 'Hello'],
    ['name' => 'dir/file.txt', 'data' => file_get_contents($file)],
]);
```

Passing several entries with a name that does *not* say tarball raises an
`\InvalidArgumentException` rather than writing the archive. It would produce a file nothing can
interpret: unpacking `backup.gz` yields a single stream, and neither this package nor `gunzip` has
any way to know a tar is hiding inside it.

### Compression settings

| Option | Adapter | Meaning |
|---|---|---|
| `gzip_level` | `Gzip` | Compression level, 0 to 9. Default `-1`, which leaves the choice to zlib |
| `bzip2_blocksize` | `Bzip2` | Block size in units of 100 kB, 1 to 9. Default `4` |

```php
$gzip = new Gzip(['gzip_level' => 9]);
```

Options are shared with the constructor's other settings, so the same array can be handed to
`Archive` and reaches every adapter it builds.

### Long paths in tar archives

The tar header has 100 characters for the entry name. Longer paths are split at a slash, with the
leading directories going into a separate 155 character prefix field — the standard USTAR
mechanism, understood by every tar implementation. A path over 100 characters with no slash to
split on cannot be stored and raises an `\InvalidArgumentException`.

> Reading the prefix field was added at the same time as writing it. Before that, this package
> extracted such an entry to the destination root under its last path segment alone — so
> `docs/very/long/path/index.html` landed as `index.html`, and two entries sharing a base name
> overwrote each other. Archives written by GNU tar are affected, not just ones written here.

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
