# Updating from v3 to v4

Release 4.0.0 raises the PHP requirement, adds archive creation to every adapter, and fixes how tar
archives with long paths are extracted. The remaining changes are internal type corrections that
surface only in edge cases.

## At a glance

| | v3 (3.0.4) | v4 (4.0.0) |
|---|---|---|
| PHP | `^8.1.0` | `^8.3.0` |
| Existing method signatures | — | unchanged |
| Creating archives | `Zip` only | every adapter, via `CreatableInterface` |
| Tar entries using the USTAR prefix field | extracted without their directories | extracted to the full path |
| ZIP entry names outside ASCII | written unflagged, read as CP437 | written and flagged as UTF-8 |
| ZIP entries beyond the format's limits | silently truncated | rejected with an exception |
| `Zip::create()` on a write failure | returned `false` | throws `\RuntimeException` |
| `$data` property initial value | `null` | `''` |

## Minimum supported PHP version raised

All Framework packages now require **PHP 8.3** or newer.

## Every adapter can write archives

`Tar`, `Gzip` and `Bzip2` gained the `create()` method that `Zip` already had, and all four now
implement the new `Joomla\Archive\CreatableInterface`:

```php
$tar->create('/path/to/out.tar', [
    ['name' => 'readme.txt', 'data' => 'Hello'],
]);
```

This is additive — nothing that worked before behaves differently, and a custom adapter
implementing only `ExtractableInterface` is still a valid adapter. See
[Creating archives](overview.md#creating-archives) for the entry format, the compression options,
and how gzip and bzip2 decide whether to wrap their payload in a tar.

## The ZIP writer was finished off

`Zip::create()` and the two private methods behind it carried "finish implementation" notes since
1.0. Four defects came out of working through them:

* **Entry names outside ASCII were unusable.** The name bytes were written as UTF-8 but the archive
  said nothing about it and declared an MS-DOS host, so a reader following the spec decoded them as
  CP437: `Größe.txt` arrived as `Gr÷ÐŸe.txt`. The UTF-8 flag is now set when the name needs it, and
  the host is declared as Unix so readers stop re-encoding. Pure ASCII archives are byte for byte
  what they were before.
* **Timestamps before 1994 corrupted the header.** The DOS timestamp was formatted as hex and
  sliced into byte pairs, which reads past the end of the string whenever the value needs fewer
  than eight hex digits — every date clamped to the 1980 epoch included. That raised
  `Uninitialized string offset` warnings and wrote a wrong timestamp.
* **Creating an archive cost time proportional to the square of its size.** The offset of each new
  entry was found by re-joining every entry written so far. 800 entries of 20 kB took 2.9 seconds;
  they now take 0.18.
* **The format's limits were overrun silently.** Entries over 4 GiB, archives over 4 GiB and more
  than 65,535 entries wrapped their 32 and 16 bit fields and produced a corrupt archive. They now
  raise an `\InvalidArgumentException`. ZIP64, which would lift the limits, is still not implemented.

Two smaller changes came with them: `Zip::create()` now throws a `\RuntimeException` when the file
cannot be written instead of returning `false` — which is what `CreatableInterface` documents and
what the other three adapters already did — and an entry without a `name` is rejected rather than
written as an entry called `0`.

If you extract ZIP archives this package wrote earlier, nothing changes; the reader was not
touched.

## Tar entries stored in the prefix field keep their directories

The tar header has 100 characters for an entry name; USTAR splits anything longer at a slash and
stores the leading directories in a separate prefix field. `Tar::extract()` never read that field,
so those entries were written to the destination root under their last path segment alone:

```
docs/very/long/path/index.html   ->   index.html      (v3)
docs/very/long/path/index.html   ->   docs/very/long/path/index.html   (v4)
```

Any archive produced by GNU tar with paths over 100 characters was affected, not only archives
written by this package. Two entries whose base names matched would overwrite each other, so an
extraction that looked successful could be missing files.

If you worked around this — by flattening the destination yourself, or by matching on base names
after extraction — that code now sees the real paths.

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
