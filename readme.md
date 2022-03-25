# PHP Application Framework for LS v1+

This branch includes the entire framework structure required to host LSV1 modules.

## Status 
- PRE-ALPHA !
- Contributions welcome

## Considerations
- Progress should be backwards compatible with Lemonstand v1 modules and DB schema



## Roadmap
- PHP7.4+ compatible
- Namespaced
- Drop deprecated methods and features (looking at you Mcrypt :eyes:)
- Backwards compatible with LSv1 modules and DB schema
- DB migrate schema (preferred over incremental sql files)
- Installer
  - configure DB connection 
  - generate a unique install ID
  - pull in application modules from GitHub
  - update db schema
  - demo theme, and products
- Docker container

### Requirements

- PHP >= 7.4

## DEV Helpers

#### Install required composer packages
`composer update --with-all-dependencies`

#### Code Style and Compatibility (PHPCS)

To check code style is inline with PSR2 standard and compatible between currently supported PHP versions run:
`composer cs-check`

PHP supported versions: 7.4+


#### Code Analysis (PSALM)
`composer psalm-check`

