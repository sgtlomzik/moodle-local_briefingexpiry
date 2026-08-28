# Changelog

All notable changes to this plugin are documented in this file.

## [1.2.0] - 2026-08-28

### Added
- `core_userlist_provider` to the privacy provider, so the plugin answers the
  "which users have data in this context" request that Moodle's data registry makes.
- `COPYING` with the licence text, a Moodle plugin CI workflow and `$plugin->supported`.
- Language strings for the course custom fields created at install time, and for the validity
  period options.

### Changed
- The custom field category, the three field names and the period options are created from
  language strings instead of hardcoded Russian text. An existing category created under the old
  Russian name is reused rather than duplicated.
- `get_period_spec()` reads two lookup tables instead of a chain of `if` branches, and accepts
  both the English labels created from this release on and the Russian labels created by earlier
  ones.
- `db/install.php` builds the three fields from one data structure rather than three copies of
  the same block.
- Variable names follow the Moodle coding style, and day arithmetic uses `DAYSECS`.
- Minimum requirement raised to Moodle 4.5 (LTS); the previous minimum, 4.4, went out of support
  in December 2025.
- README rewritten with the full setup path and an explicit account of what a reset does and does
  not remove.

### Fixed
- **Notifications carried no `courseid`.** `\core\message\message` requires one; without it
  `message_send()` raises a debugging warning on every digest and every student notice. The
  digest now uses `SITEID` and the student notice the course it is about.
- `get_contexts_for_userid()` returned the system context for every user, including those with no
  data at all, which put an empty section into every data export on the site.
- Exported records were built as a list cast to an object, which produced numeric property names
  in the export. They are now nested under a named key.
- The student reset notice has a `contexturl` pointing at the course, so the notification is
  clickable.
- The course custom field handler was rebuilt on every iteration of the course loop instead of
  once.
- All `@copyright` tags were bare years with no author.
