# Extend Page Plugin for DokuWiki

Extend/Replace pages with id matching a regex by content from a different page.

## Installing

If you install this plugin manually, make sure it is installed in
lib/plugins/extendpage/ - if the folder is called different it
will not work!

Please refer to http://www.dokuwiki.org/plugins for additional info
on how to install plugins in DokuWiki.

## Usage

Go to the admin configuration menu and select the "Extend Page Plugin" entry.
Add or delete entries in the table to assign page(s)/namespace(s) to be extended
in the first column with a page to be used as extension in the third column.

Use the "Position" option to specify if the extension should be prepended or
appended to the original content or if it should replace the original content
altogether.

The syntax for the first column follows the same behavior as the [assignments in
the struct plugin](https://www.dokuwiki.org/plugin:struct:assignments). This
means you can do assignments either on base of the page id, or on base of
namespaces, or use a regular expression.

## Copyright

Copyright (C) Frieder Schrempf <dev@fris.de>

## License

GPL-2.0-only, see LICENSE file