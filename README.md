# farmOS Asset Termination

This module is an add-on for the [farmOS](http://drupal.org/project/farm)
distribution.

It adds the ability to mark any log as terminating referenced assets.

Logs marked as termination will on completion mark referenced assets as
terminated and archive them. A terminated asset page will display
the termination time linked to the log that terminated the asset.

Optionally:

- termination logs can be automatically marked with configured category,
- configured log types can be by default marked as termination,
- existing logs with configured termination category can be automatically marked
  as termination,
- existing logs of configured default termination log types can be automatically marked
  as termination.

## Installation

Install as you would normally install a contributed drupal module. See:
<https://www.drupal.org/docs/extending-drupal/installing-modules> for further
information.

## Maintainers

Current maintainers:

- wotnak - <https://www.drupal.org/u/wotnak>
