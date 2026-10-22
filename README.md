# Stars Academy Community

An experimental Moodle local plugin for reusable learning-workspace flows: learner and reviewer views, draft and submission history, review queues, group sessions, and feedback.

- Moodle component: local_learning_workspace
- Plugin directory: local/learning_workspace
- Release: 0.1.0 experimental
- License: GNU GPL version 3 or any later version (GPL-3.0-or-later)

## Status

This is an experimental technical preview. It has not passed a clean Moodle installation, Moodle PHPUnit integration, browser workflow testing, cross-database testing, production validation, or a security audit. Use it only in an isolated disposable test site with synthetic data. No external users or deployments are claimed.

The examples demonstrate generic workflows for saving, submitting, and reviewing revisions. They are synthetic examples, not a course, qualification, or admission decision system.

The plugin declares Moodle build 2026042000 or later and depends on the Moodle-supplied tool_usertours component at build 2026042000 or later. This minimum is derived from version.php. Moodle 5.2 itself requires PHP 8.3.0 or later, 64-bit PHP, sodium, max_input_vars of at least 5000, and a supported database. See the [official Moodle 5.2 requirements](https://moodledev.io/general/releases/5.2). The plugin has not been integration-tested on Moodle 5.2 or later.

## Checks completed

The local preparation environment reported:

- Standalone smoke test: 151 checks passed with php tests/smoke.php.
- PHP syntax lint: 92 PHP files passed on PHP 8.1.34. This is a syntax check, not a supported Moodle runtime test.
- JavaScript syntax: 7 files passed with Node.js node --check.
- Static schema and Privacy API consistency checks: 17 plugin tables and associated metadata checked.

Moodle PHPUnit tests are included but were not run. These checks do not establish that Moodle can install or run the plugin.

## Installation for a disposable test site

1. Use a fresh Moodle test site that meets the requirements for the Moodle version being tested.
2. Copy the contents of this repository to MOODLE_ROOT/local/learning_workspace.
3. Complete Moodle's normal plugin installation flow from the site administration notifications page.
4. Exercise the sample workflows, Privacy API export and deletion, and uninstall behaviour using synthetic data only.

This release is a fresh-install technical preview. It does not provide an upgrade path from a previous installation of this component.

## License

This plugin is distributed under the GNU General Public License, version 3 or any later version. See [LICENSE](LICENSE) for the full license text. Moodle's core GPL does not automatically assign a license to add-ons; see [Moodle's licensing guidance](https://moodledev.io/general/license).

This project is an independent community plugin and is not affiliated with or endorsed by Moodle.

See [DEPENDENCIES.md](DEPENDENCIES.md) for runtime requirements and third-party notices, and [RELEASE_MANIFEST.md](RELEASE_MANIFEST.md) for the release file inventory and SHA-256 checksums.