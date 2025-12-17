To build the project, first, install the dependencies:

- `npm ci`
- `composer install`

### SVN CLI

Run commands in the SVN directory

To check the status: `svn status`

To revert a local change: `svn revert path/to/file`

To publish a version:
```shell
svn add tags/XXX
svn commit -m "vXXX"
```
