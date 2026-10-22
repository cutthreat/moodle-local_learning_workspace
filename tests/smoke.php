<?php
declare(strict_types=1);

define('MOODLE_INTERNAL', true);
$plugin = dirname(__DIR__);
require_once($plugin . '/classes/local/content_pack.php');
require_once($plugin . '/classes/local/lesson_catalog.php');
require_once($plugin . '/classes/local/academy_day_content.php');
require_once($plugin . '/classes/local/academy_subject_review.php');
require_once($plugin . '/classes/local/academy_question_bank.php');
require_once($plugin . '/classes/local/lesson_one.php');
require_once($plugin . '/classes/local/academy_assessment_repository.php');

use local_learning_workspace\local\academy_assessment_repository;
use local_learning_workspace\local\academy_day_content;
use local_learning_workspace\local\academy_question_bank;
use local_learning_workspace\local\content_pack;
use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\lesson_one;

$checks = 0;
$assert = static function(bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $label);
    }
    $checks++;
};

$version = file_get_contents($plugin . '/version.php');
$access = file_get_contents($plugin . '/db/access.php');
$langEn = file_get_contents($plugin . '/lang/en/local_learning_workspace.php');
$langRu = file_get_contents($plugin . '/lang/ru/local_learning_workspace.php');
$upgrade = file_get_contents($plugin . '/db/upgrade.php');
$schema = file_get_contents($plugin . '/db/install.xml');
$uninstall = file_get_contents($plugin . '/db/uninstall.php');
$quickstart = file_get_contents($plugin . '/classes/local/academy_quickstart.php');
$privacy = file_get_contents($plugin . '/classes/privacy/provider.php');
$assert(str_contains(file_get_contents($plugin . '/README.md'), 'MOODLE_ROOT/local/learning_workspace'), 'documented installation directory matches Moodle component');
$assert(str_contains($version, "\$plugin->component = 'local_learning_workspace';"), 'neutral Moodle component');
$assert(str_contains($version, "\$plugin->requires = 2026042000;"), 'source-derived Moodle 5.2.0 minimum');
$assert(str_contains($schema, 'VERSION="2026102201"'), 'install schema version tracks the cleaned schema');
$assert(str_contains($upgrade, "upgrade_plugin_savepoint(true, 2026102201, 'local', 'learning_workspace')"), 'upgrade reaches current plugin version');
$assert(str_contains($access, "'local/learning_workspace:view'"), 'neutral capability identifier');
$assert(!str_contains($access, 'confi' . 'deline'), 'no source brand in access capabilities');
$capabilitycount = preg_match_all("/'local\\/learning_workspace:([a-z]+)'\\s*=>/", $access, $capabilitymatches);
$assert($capabilitycount === 4, 'only four capabilities used by the clean package are declared');
foreach ($capabilitymatches[1] as $capability) {
    $shortcode = 'local/learning_workspace:' . $capability;
    $stringkey = 'learning_workspace:' . $capability;
    $assert(str_contains($langEn, "['" . $stringkey . "']"), 'English capability string exists: ' . $capability);
    $assert(str_contains($langRu, "['" . $stringkey . "']"), 'Russian capability string exists: ' . $capability);
    $used = false;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php' || basename($file->getPathname()) === 'access.php' ||
                str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (str_contains(file_get_contents($file->getPathname()), $shortcode)) {
            $used = true;
            break;
        }
    }
    $assert($used, 'declared capability is used by plugin code: ' . $capability);
}
$tables = preg_match_all('/<TABLE NAME="local_lworkspace_[a-z]+"/', $schema);
$assert($tables === 17, 'clean schema keeps only tables used by the demo workflow and privacy provider');
foreach (['attempt','submit','review','packet','decision','audit','idem','subject','packver','moddef','qdef',
        'progress','moduleev','practicestep','quiz','run','runassign'] as $legacytable) {
    $assert(!str_contains($schema, 'local_lworkspace_' . $legacytable . '"'), 'unused legacy table removed: ' . $legacytable);
    $assert(!str_contains($upgrade, 'local_lworkspace_' . $legacytable), 'upgrade does not recreate excluded table: ' . $legacytable);
}
$xml = simplexml_load_string($schema);
$assert($xml !== false, 'install.xml parses as XML');
$schematables = [];
foreach ($xml->TABLES->TABLE as $tablexml) {
    $tablename = (string)$tablexml['NAME'];
    $schematables[$tablename] = array_map(static fn(SimpleXMLElement $field): string => (string)$field['NAME'], iterator_to_array($tablexml->FIELDS->FIELD));
}
$metadatatables = [];
$metadatadescriptions = [];
preg_match_all("/'(local_lworkspace_[a-z]+)'\\s*=>\\s*\\[(.*?)\\],/s", $privacy, $metadatamatches, PREG_SET_ORDER);
foreach ($metadatamatches as $match) {
    preg_match_all("/'([a-z_]+)'\\s*=>\\s*'([a-z_]+)'/", $match[2], $fieldmatches, PREG_SET_ORDER);
    $metadatatables[$match[1]] = array_column($fieldmatches, 1);
    foreach ($fieldmatches as $fieldmatch) {
        $metadatadescriptions[$fieldmatch[2]] = true;
    }
}
ksort($schematables);
ksort($metadatatables);
$assert(array_keys($schematables) === array_keys($metadatatables), 'Privacy API metadata covers all tables in clean install schema');
foreach ($schematables as $tablename => $fields) {
    $missing = array_diff($fields, $metadatatables[$tablename] ?? []);
    $assert(!$missing, 'Privacy API metadata covers schema fields for ' . $tablename);
    $tablestring = 'privacy:metadata:table:' . substr($tablename, strlen('local_lworkspace_'));
    $assert(str_contains($langEn, "['" . $tablestring . "']") && str_contains($langRu, "['" . $tablestring . "']"), 'privacy table description exists in both languages: ' . $tablename);
}
foreach (array_keys($metadatadescriptions) as $description) {
    $stringkey = 'privacy:metadata:' . $description;
    $assert(str_contains($langEn, "['" . $stringkey . "']") && str_contains($langRu, "['" . $stringkey . "']"), 'privacy field description exists in both languages: ' . $description);
}
$assert(str_contains($uninstall, 'function xmldb_local_learning_workspace_uninstall()'), 'Moodle uninstall hook entry point');
$assert(str_contains($uninstall, 'academy_quickstart::uninstall()'), 'uninstall hook delegates tour cleanup');
$assert(str_contains($quickstart, 'public static function uninstall(): void'), 'tour cleanup is implemented');
$assert(str_contains($quickstart, 'get_pathmatch()') && str_contains($quickstart, 'get_name()'), 'tour removal requires ownership markers');
$assert(str_contains($quickstart, '__quickstart_') && str_contains($quickstart, "preg_match('/\\A[1-9][0-9]*\\z/'"), 'owned tours survive their display version increment');
$assert(str_contains($privacy, 'implements metadata_provider, plugin_provider, core_userlist_provider, user_preference_provider'), 'Privacy API interfaces implemented');
foreach (['get_contexts_for_userid','get_users_in_context','export_user_data','delete_data_for_all_users_in_context','delete_data_for_user','delete_data_for_users','export_user_preferences'] as $method) {
    $assert(str_contains($privacy, 'function ' . $method), 'Privacy API method exists: ' . $method);
}
$assert(is_file($plugin . '/tests/privacy_provider_test.php'), 'Privacy API integration tests included');
$assert(is_file($plugin . '/tests/quickstart_uninstall_test.php'), 'tour uninstall integration tests included');
$assert(str_contains($privacy, 'local_lworkspace_start_candidate') && str_contains($privacy, 'local_lworkspace_start_examiner') && str_contains($privacy, 'local_lworkspace_start_manager'), 'all saved start-page preferences included in privacy provider');

$modules = content_pack::modules();
$questions = content_pack::questions();
$packs = content_pack::lesson_packs();
$assert(count($modules) === 11, 'generic module count');
$assert(count($questions) === 21, 'generic sample question count');
$assert(count($packs) === 11, 'generic lesson pack count');
$assert(count($packs['M0']['scenarios']) === 10, 'M0 scenario fixture count');
$assert(count($packs['M1']['practice_steps']) === 3, 'practice step fixture count');
$assert(!str_contains(json_encode([$modules, $questions, $packs], JSON_THROW_ON_ERROR), 'Stars' . ' Academy'), 'no brand in demo content');

$catalog = lesson_catalog::all();
$assert(count($catalog) === 7, 'learner module count');
foreach ($catalog as $lesson) {
    $assert(!array_key_exists('localpdfs', $lesson) && !array_key_exists('sourceurl', $lesson), 'no lesson-file metadata');
    $assert(!array_key_exists('sources', academy_day_content::day($lesson['id'])), 'no source references');
}

$final = academy_question_bank::final(1);
$assert($final['id'] === 'FINAL', 'final assessment id');
$assert(count(array_filter($final['questions'], static fn(array $q): bool => ($q['type'] ?? '') === 'choice' && ($q['part'] ?? '') === 'foundation')) === 10, 'foundation objective section');
$assert(count(array_filter($final['questions'], static fn(array $q): bool => ($q['type'] ?? '') === 'choice' && ($q['part'] ?? '') === 'process')) === 10, 'process objective section');
$assert(count(academy_assessment_repository::five_part_spec($final['questions'])) === 15, 'generic review rubric contract');
$assert(academy_question_bank::final_variant_for_attempt(1) === 1 && academy_question_bank::final_variant_for_attempt(4) === 1, 'retake variant cycle');

$content = lesson_one::content();
$state = lesson_one::initial();
$state = lesson_one::transition($state, $content, 'next', 1, '');
$state = lesson_one::transition($state, $content, 'next', 2, '');
$state = lesson_one::transition($state, $content, 'check', 3, lesson_one::encode(['DEMO-Q1' => 'a']));
$assert($state['checkpassed'] === true, 'demo knowledge check');
$state = lesson_one::transition($state, $content, 'next', 3, '');
$state = lesson_one::transition($state, $content, 'draft', 4, 'Save, review, submit.');
$state = lesson_one::transition($state, $content, 'submit', 4, 'Save, review, submit.');
$state = lesson_one::transition($state, $content, 'next', 4, 'Save, review, submit.');
$assert($state['completed'] === true, 'draft, submit, review transition');
echo 'PASS ' . $checks . " checks\n";

