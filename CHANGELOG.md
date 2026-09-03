# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- A learner can clear their own choice (where changing is allowed), and a
  teacher can delete any user's choice, both behind new capabilities
  (`mod/pathway:deleteownchoice`, students; `mod/pathway:deleteanychoice`,
  teachers and managers) with an explicit decision on whether memberships
  this activity added are removed too. A `choice_deleted` event is logged.
- A Manage responses page for teachers: who chose what (with whether the
  membership is activity-owned), per-user delete, and bulk assignment of an
  option to many users at once (behind its own `mod/pathway:bulkassign`
  capability, editing teachers and managers) - each treated exactly as if
  the user had chosen it themselves, respecting capacity limits and
  ownership flags. Suspended accounts and suspended enrolments are not
  offered for assignment.
- Re-saving the option a user already holds now reconciles cohort and group
  membership against the mapping, restoring a membership an admin removed by
  hand (and reclaiming ownership) without ever removing anything.
- The activity page is restyled: current choice as a highlighted panel, the
  options in a card, and the response summary as percentage bars, with the
  activity description shown once by the activity header. On the course
  page the description now renders with the options, and the tile size
  setting also controls button size for list display. Site-home (front
  page) use is documented in the README.

### Changed

- CI workflow now runs only for pushes to main (a tag push previously re-ran
  the whole matrix on an already-tested commit), actions/checkout is bumped
  to v5.1.0 (current node24 runtime), and Dependabot watches the pinned
  actions weekly so future runtime deprecations arrive as pull requests.

## [1.0.0-alpha.2] - 2026-08-20

### Fixed

- Option images converted to WebP failed the image picker's file type
  validation the next time the activity was edited, because Moodle core does
  not know the webp extension. Install/upgrade now registers webp as a site
  file type (skipped if the site already defines it), and conversion is
  skipped whenever the type is unknown so the plugin can never again write a
  file its own form rejects.
- Resizing a PNG on a server without WebP support now keeps PNG, preserving
  transparency, instead of producing a JPEG with a black background. Other
  formats falling back to JPEG are flattened onto white.

## [1.0.0-alpha.1] - 2026-08-18

### Added

- Initial pre-release of the pathway activity module: options with cohort and
  group mapping, response limits and images, course page display as buttons
  or image tiles, completion support, backup and restore, privacy provider,
  PHPUnit and Behat suites, and a moodle-plugin-ci workflow.

[1.0.0-alpha.2]: https://github.com/motorpilotltd/moodle-mod_pathway/releases/tag/v1.0.0-alpha.2
[1.0.0-alpha.1]: https://github.com/motorpilotltd/moodle-mod_pathway/releases/tag/v1.0.0-alpha.1
