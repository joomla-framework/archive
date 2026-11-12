# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## ZIP archives without the zip extension

Without `ext-zip`, `Zip` falls back to a reader written in PHP that needs only `ext-zlib`. It
supports stored and deflated entries, and bzip2 compressed entries when `ext-bz2` is loaded. It
checks the CRC32 of every entry. Encrypted entries, other compression methods (such as Deflate64 or
LZMA), corrupt entries and data that is not a ZIP archive raise a `RuntimeException`.
