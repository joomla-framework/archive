# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## TAR formats

`Tar` reads archives in the old V7 format, the ustar format (including paths longer than 100
characters, which ustar splits into a prefix and a name), GNU tar's `././@LongLink` long names and
the POSIX.1-2001 pax format written by GNU tar `--format=posix`, bsdtar (macOS) and `git archive`.
From pax extended headers the `path`, `size` and `mtime` records are used; other records are
ignored. Sizes stored in base-256, as GNU tar does for entries of 8 GiB and more, are supported.

Regular files and directories are extracted, including empty directories. Symbolic links, hard
links, devices and FIFOs are skipped.
