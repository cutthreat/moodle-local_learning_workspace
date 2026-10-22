<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Synthetic, non-instructional fixture used to demonstrate the import and
 * review workflow. Replace it only with content whose rights are documented.
 */
final class content_pack {
    public const PACK_STATUS = 'demo_only';
    public const PACK_VERSION = 'community-demo-0.1';
    public const PACK_REF = 'synthetic-local-fixture';
    public const POLICY_FIXTURE = 'no-production-policy';

    public static function modules(): array {
        $modules = [];
        for ($i = 0; $i <= 10; $i++) {
            $id = 'M' . $i;
            $modules[] = [
                'id' => $id,
                'stage' => 'demo',
                'roles' => 'learner, reviewer',
                'title' => ['ru' => 'Демо-модуль ' . ($i + 1), 'en' => 'Demo module ' . ($i + 1)],
                'outcome' => [
                    'ru' => 'Участник выполняет нейтральный пример, сохраняет черновик и отправляет его на проверку.',
                    'en' => 'The participant completes a neutral example, saves a draft, and submits it for review.',
                ],
                'materials' => 'Synthetic local example; no course source is included.',
                'evidence' => 'A saved draft and a submitted response in the demo workflow.',
                'checklist' => [
                    'Черновик сохранён отдельно от отправленного ответа.',
                    'Для отправки заполнены обязательные поля.',
                    'Отправленная версия ожидает проверки.',
                    'Замечание проверяющего видно участнику.',
                ],
            ];
        }
        return $modules;
    }

    public static function questions(): array {
        $questions = [];
        for ($i = 0; $i < 21; $i++) {
            $module = min(10, intdiv($i, 2));
            $sequence = ($module === 10) ? 1 : ($i % 2) + 1;
            $id = 'M' . $module . '-Q' . $sequence;
            $questions[] = [
                'id' => $id,
                'module' => 'M' . $module,
                'type' => 'single_choice',
                'prompt' => 'Which action keeps a draft separate from a submitted response?',
                'answer' => 'Save the draft, then submit it as a distinct action.',
                'rubric' => 'The response distinguishes saving from submission.',
            ];
        }
        return $questions;
    }

    public static function lesson_packs(): array {
        $packs = [];
        for ($i = 0; $i <= 10; $i++) {
            $id = 'M' . $i;
            $count = $id === 'M0' ? 10 : 3;
            $scenarios = [];
            for ($n = 1; $n <= $count; $n++) {
                $scenario = [
                    'id' => $id . '-S' . $n,
                    'prompt_ru' => 'Участник изменил черновик после отправки. Что происходит с предыдущей версией?',
                    'prompt_en' => 'A participant edits a draft after submission. What happens to the earlier version?',
                    'safe_ru' => 'Сохранить редакцию отдельно и показать участнику, какая версия ожидает проверки.',
                    'safe_en' => 'Save the revision separately and show which version is awaiting review.',
                    'unsafe_ru' => 'Молча заменить отправленный ответ и продолжить проверку прежней версии.',
                    'unsafe_en' => 'Silently replace the submitted response and continue reviewing the older version.',
                ];
                if ($id === 'M0') {
                    $scenario['classification_ru'] = 'Проверка версий.';
                    $scenario['classification_en'] = 'Version review.';
                    $scenario['next_owner'] = 'reviewer';
                }
                $scenarios[] = $scenario;
            }

            $selfcheck = [];
            for ($n = 1; $n <= 5; $n++) {
                $selfcheck[] = [
                    'ru' => 'Я проверил шаг ' . $n . ' в нейтральном примере.',
                    'en' => 'I checked step ' . $n . ' in the neutral example.',
                ];
            }
            $worked = [];
            for ($n = 1; $n <= 3; $n++) {
                $worked[] = [
                    'topic_ru' => 'Демо-ответ ' . $n,
                    'topic_en' => 'Demo response ' . $n,
                    'correct_ru' => 'Сохранить изменение и явно отправить выбранную версию.',
                    'correct_en' => 'Save the change and explicitly submit the selected version.',
                    'incorrect_ru' => 'Считать черновик отправленным без подтверждения участника.',
                    'incorrect_en' => 'Treat a draft as submitted without participant confirmation.',
                ];
            }
            $rubric = [];
            for ($n = 1; $n <= 4; $n++) {
                $rubric[] = [
                    'criterion_ru' => 'Критерий ' . $n,
                    'criterion_en' => 'Criterion ' . $n,
                    'pass_rule_ru' => 'Шаг явно выполнен и сохранён.',
                    'pass_rule_en' => 'The step is explicitly completed and saved.',
                ];
            }

            $pack = [
                'source_boundary' => [
                    'ru' => 'Синтетический пример; не предназначен для обучения предметной области.',
                    'en' => 'Synthetic example; not intended to teach a subject area.',
                ],
                'evidence_prompt' => [
                    'ru' => 'Покажите сохранённый черновик и отправленную версию.',
                    'en' => 'Show the saved draft and the submitted version.',
                ],
                'scenarios' => $scenarios,
                'worked_examples' => $worked,
                'rubric' => $rubric,
                'self_check' => $selfcheck,
            ];
            if ($id === 'M0') {
                $roles = [];
                for ($n = 1; $n <= 6; $n++) {
                    $roles[] = [
                        'role' => 'role_' . $n,
                        'label_ru' => 'Роль ' . $n,
                        'label_en' => 'Role ' . $n,
                        'purpose_ru' => 'Участник демонстрационного процесса.',
                        'purpose_en' => 'Participant in the demonstration workflow.',
                        'responsibility_ru' => 'Работает только в пределах назначенных действий.',
                        'responsibility_en' => 'Works only within assigned actions.',
                        'boundary_ru' => 'Не меняет чужие отправленные ответы.',
                        'boundary_en' => 'Does not alter another person’s submitted response.',
                        'handoff_ru' => 'Передаёт вопрос проверяющему.',
                        'handoff_en' => 'Routes questions to the reviewer.',
                    ];
                }
                $escalations = [];
                for ($n = 1; $n <= 5; $n++) {
                    $escalations[] = [
                        'risk_ru' => 'Версия или состояние не совпадает.',
                        'risk_en' => 'The version or state does not match.',
                        'learner_action_ru' => 'Сохранить состояние и запросить проверку.',
                        'learner_action_en' => 'Save the state and request a review.',
                        'next_owner' => 'reviewer',
                    ];
                }
                $pack['role_map'] = $roles;
                $pack['escalation_table'] = $escalations;
            } else {
                $blocks = [];
                for ($n = 1; $n <= 3; $n++) {
                    $blocks[] = [
                        'title_ru' => 'Шаг ' . $n,
                        'title_en' => 'Step ' . $n,
                        'body_ru' => 'Выполните шаг демонстрационного процесса и сохраните результат.',
                        'body_en' => 'Complete the demonstration workflow step and save the result.',
                    ];
                }
                $pack['lesson_blocks'] = $blocks;
            }
            if ($id === 'M1') {
                $steps = [];
                foreach ($scenarios as $scenario) {
                    $steps[] = [
                        'scenario_id' => $scenario['id'],
                        'situation_ru' => $scenario['prompt_ru'],
                        'situation_en' => $scenario['prompt_en'],
                        'goal_ru' => 'Сохранить границу между черновиком и отправкой.',
                        'goal_en' => 'Keep the boundary between a draft and a submission.',
                        'example_ru' => 'Сохраните новую редакцию и отправьте её отдельно.',
                        'example_en' => 'Save the revision and submit it separately.',
                        'bad_example_ru' => 'Подмените отправленный ответ без следа.',
                        'bad_example_en' => 'Replace a submitted response without a trace.',
                        'why_ru' => 'Участник и проверяющий должны видеть актуальную версию.',
                        'why_en' => 'The participant and reviewer must see the current version.',
                        'checklist_ru' => ['Состояние сохранено.'],
                        'checklist_en' => ['The state is saved.'],
                    ];
                }
                $pack['practice_steps'] = $steps;
            }
            $packs[$id] = $pack;
        }
        return $packs;
    }

    public static function m0_lesson_pack(): array {
        return self::lesson_packs()['M0'];
    }

    public static function lesson_pack(string $moduleid): array {
        return self::lesson_packs()[$moduleid] ?? [];
    }

    public static function module_source_boundaries(): array {
        $result = [];
        foreach (self::modules() as $module) {
            $result[$module['id']] = [
                'class' => 'unknown_blocked',
                'note' => 'Synthetic demo fixture; content ownership has not been assessed.',
            ];
        }
        return $result;
    }

    public static function module_source_refs(): array {
        $result = [];
        foreach (self::modules() as $module) {
            $result[$module['id']] = [[
                'Synthetic local fixture',
                'Generated for a software packaging demo; no external material is referenced.',
            ]];
        }
        return $result;
    }

    public static function unresolved_gates(): array {
        return ['Replace synthetic examples only after provenance and rights review.'];
    }

    public static function pack_manifest_ref(): string {
        return 'synthetic-local-fixture-manifest';
    }
}
