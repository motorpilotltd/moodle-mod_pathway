# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
