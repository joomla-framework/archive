# The Archive Package

The archive package will intelligently load the correct adapter for the specified archive type. It knows how to properly handle the following archive types:

- zip
- tar | tgz | tbz2
- gz | gzip
- bz2 | bzip2

Loading files of the `t*` archive type will uncompress the archive using the appropriate adapter, and then extract via tar.

TODO

## Adapters

`Archive::getAdapter($type)` returns the adapter for `zip`, `tar`, `gzip` or `bzip2`, and
`Archive::setAdapter($type, $class)` replaces one with your own class implementing
`Joomla\Archive\ExtractableInterface`. Both throw a
`Joomla\Archive\Exception\UnsupportedArchiveException` if the class does not exist, does not
implement the interface, or its `isSupported()` returns `false` because a required PHP extension is
missing (for example `ext-bz2` for `bzip2`).
