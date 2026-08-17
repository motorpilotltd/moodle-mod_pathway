# Pathway activity module for Moodle (mod_pathway)

A Moodle activity that presents a short list of options to each participant.
When a user selects one, they can be added automatically to the cohort and/or
course group mapped to that option. Later parts of the course can then be
opened up based on the selection, either through activity completion or with
the companion availability plugin
([availability_pathway](../moodle-availability_pathway)).

## How it works

- A teacher defines a set of options, each optionally mapped to a cohort, a
  course group, both, or neither. Each option can carry a response limit and
  an image.
- A participant makes one choice per activity. Depending on the settings they
  may or may not be able to change it later; where they cannot, the choice is
  confirmed server side before it is saved.
- Options can be shown on the course page itself (as buttons or image tiles),
  so a choice can be made without opening the activity. Tile size (small to
  extra large) is set per activity; tiles centre under the activity name.
- Option images are resized on upload (512px longest edge) and converted to
  WebP where the server's GD supports it, JPEG otherwise. Best effort: an
  image that cannot be processed is stored exactly as uploaded. Two site
  settings control this (resize on/off, WebP on/off); animated GIFs are left
  untouched to preserve the animation.
- A response summary (counts only) can be shown to participants. Only users
  with the `mod/pathway:readresponses` capability see anything beyond counts.

## Cohort and group behaviour

The plugin records whether it was the thing that created a membership
(`cohortadded` / `groupadded` flags) and only ever removes memberships it
created:

- If a user was already in the mapped cohort or group, the plugin leaves that
  membership alone permanently.
- When a choice is changed, memberships created by the previous choice are
  removed first.
- Deleting the activity leaves all memberships in place, since removing people
  from cohorts as a side effect can strip enrolments elsewhere on the site.
  Course reset is the explicit way to give plugin-created memberships back.

Only cohorts the editing teacher could assign members to by hand are offered
in the activity settings.

## Backup and restore

Options, answers (with user info) and option images are included in backups.
Cohorts are never part of a Moodle backup, so on a same-site restore
(duplication, import, restore) the plugin keeps the original cohort and group
links where the target still exists. On a different site those links cannot be
resolved; they are cleared and a warning is written to the restore log so they
can be re-mapped by hand.

## Upgrading from mod_cohortchoice

There is no upgrade path. Moodle does not support renaming a plugin's
component, so `mod_cohortchoice` and `mod_pathway` are unrelated plugins as
far as Moodle is concerned. Uninstall the old plugins first (which drops
their tables), then install these. Any test instances and their responses go
with them.

## Requirements

- Moodle 4.1 or later (tested against 4.1 to 5.2). The 4.1 floor also caps
  the code at PHP 7.4 syntax; it exists for backwards compatibility until
  all installs are on Moodle 4.5 or greater, at which point the floor rises.

## Installation

1. Copy this directory to `mod/pathway` in your Moodle root.
2. Visit Site administration, or run `php admin/cli/upgrade.php`.

## Status

Alpha. A PHPUnit suite (manager logic, lib callbacks, privacy, completion and
a backup/restore round trip), Behat features and a moodle-plugin-ci GitHub
Actions workflow are included. Still, please test in a staging environment
before using this anywhere that matters.

## Licence

Copyright © 2026 Jon Bolton, Simon Lewis

GPL v3 or later. The activity icon is the diagram-project icon from
[Font Awesome Free](https://fontawesome.com) (CC BY 4.0).
