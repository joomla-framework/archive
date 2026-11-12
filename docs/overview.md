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
example `../../index.php`. The check compares whole path segments, so an entry like
`../uploads_public/shell.php` extracted into `/var/www/uploads` is rejected as well, although
`/var/www/uploads_public` starts with the same characters. On Windows the comparison ignores case.

The check is lexical: it does not resolve symlinks, so a symlink that already exists inside the
destination is followed. Extract untrusted archives into a new, empty directory.
