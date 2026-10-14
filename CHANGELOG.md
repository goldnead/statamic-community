# Changelog

## Unreleased

### Fixed

- Links in post and comment bodies are allow-listed by scheme (`http`, `https`, `mailto`, `tel`, relative). `vbscript:`, `data:`, `file:` and others were let through before.
- Titles and regions are cut at 255 characters and bodies over 60000 bytes raise `CommunityException::tooLong()`. SQLite accepted both silently; MySQL failed the insert.

### Added

- `phpunit.mysql.xml` and a MySQL leg in CI; Laravel and `--prefer-lowest` axes in the test matrix.
- Pint, Larastan (level 5, with baseline), `.gitattributes`, `LICENSE.md`.
- `extra.statamic` carries `slug` and the developer link.
