# Dependencies and third-party notices

## Runtime dependencies

| Dependency | Use | Version and license scope |
|---|---|---|
| Moodle LMS | Moodle APIs for pages, permissions, database, forms, file storage, Privacy API, hooks, and language strings | The plugin declares build 2026042000 or later in version.php. Moodle core is host-provided and is not included or relicensed by this repository. |
| tool_usertours | Guided-tour creation, display, and cleanup | The plugin declares build 2026042000 or later. This component is supplied by the Moodle installation and is not bundled here. |
| PHP and a Moodle-supported database | Runtime and persistent storage | Not bundled. Follow the requirements for the Moodle release being tested. |

Moodle 5.2 requires PHP 8.3.0 or later, 64-bit PHP, sodium, max_input_vars of at least 5000, and a supported database. The current code has not been integration-tested against Moodle 5.2 or later. See the [official Moodle 5.2 requirements](https://moodledev.io/general/releases/5.2).

## Bundled third-party code and assets

Manual review of this release tree found no bundled third-party libraries, fonts, images, or other vendor assets. There is no Composer or npm dependency manifest. The plugin calls APIs supplied by the Moodle installation.

No additional third-party copyright notices were identified for files in this release tree. Third-party code or assets added in future changes must retain their applicable notices and be reviewed for license compatibility before redistribution.

## License

The plugin code and documentation in this repository are distributed under GNU GPL version 3 or any later version (GPL-3.0-or-later). The full license text is in [LICENSE](LICENSE).

Moodle states that the GPL for Moodle core does not automatically extend to plugins or other add-ons. See [Moodle's licensing guidance](https://moodledev.io/general/license).