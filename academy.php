<?php
/** Pilot assessment route. Learning evidence only; never grants work access. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_result_view.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_branding.php');
require_once(__DIR__ . '/classes/local/lesson_catalog.php');
require_once(__DIR__ . '/classes/local/academy_question_bank.php');
require_once(__DIR__ . '/classes/local/academy_assessment_repository.php');
require_once(__DIR__ . '/classes/local/academy_dialogue_snapshot_view.php');
require_once(__DIR__ . '/classes/local/academy_dialogue_answer_codec.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_once(__DIR__ . '/classes/local/academy_next_step.php');
require_once(__DIR__ . '/classes/local/academy_option_order.php');

use local_learning_workspace\local\academy_assessment_repository as assessments;
use local_learning_workspace\local\academy_dialogue_snapshot_view;
use local_learning_workspace\local\academy_question_bank as bank;
use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\academy_access;
use local_learning_workspace\local\academy_option_order;

require_login();
$context = academy_access::require_viewer();
$canwrite = academy_access::is_candidate();
$view = optional_param('view', 'home', PARAM_ALPHA);
if (!in_array($view, ['home', 'day', 'final'], true)) {
    $view = 'home';
}
$lessonid = strtoupper(optional_param('lesson', 'L1', PARAM_ALPHANUMEXT));
if (!preg_match('/^L[1-7]$/', $lessonid)) {
    $lessonid = 'L1';
}
$requestedattemptid = optional_param('attempt', 0, PARAM_INT);
$pageparams = ['view' => $view];
if ($view === 'day') {
    $pageparams['lesson'] = $lessonid;
}
if (in_array($view, ['day', 'final'], true) && $requestedattemptid > 0) {
    $pageparams['attempt'] = $requestedattemptid;
}
$pageurl = new moodle_url('/local/learning_workspace/academy.php', $pageparams);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Проверка знаний · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy.css', ['v' => '20261013-attempts']));

function ac_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ac_url(string $view = 'home', ?string $lessonid = null): string {
    $params = ['view' => $view];
    if ($lessonid !== null) {
        $params['lesson'] = $lessonid;
    }
    return (new moodle_url('/local/learning_workspace/academy.php', $params))->out(false);
}
function ac_lesson_step_url(string $lessonid, int $step): string {
    if (!preg_match('/^L[1-7]$/', $lessonid)) {
        throw new InvalidArgumentException('Unknown academy lesson id');
    }
    if ($lessonid === 'L1') {
        return (new moodle_url('/local/learning_workspace/lesson_one.php',
            ['step' => $step === 3 ? 4 : 1]))->out(false);
    }
    return (new moodle_url('/local/learning_workspace/academy_day.php',
        ['lesson' => $lessonid, 'step' => $step === 3 ? 3 : 1]))->out(false);
}
/** Repair the assessed answer in the same test; lesson practice is optional preparation. */
function ac_render_day_rework_actions(string $lessonid, bool $canwrite): void {
    if ($canwrite) {
        echo '<p><a href="#ac-day-current-test">Доработать ответ в новой попытке →</a></p>' .
            '<p class="ac-help">Перечитайте все вопросы текущей версии теста и ответьте заново. ' .
            'После отправки новая попытка сохранится отдельно; прежний ответ и замечание останутся в истории.</p>';
    }
    echo '<p><a href="' . ac_e(ac_lesson_step_url($lessonid, 3)) .
        '">Повторить практику урока →</a></p>';
}
function ac_final_remediation(array $question): ?array {
    $id = (string)($question['id'] ?? '');
    $source = (string)($question['source_ref'] ?? '');
    if ($id === 'F-PROCESS-01' && str_contains($source, 'work:academy_work')) {
        return [
            'url' => (new moodle_url('/local/learning_workspace/academy_work.php'))->out(false) . '#prepare',
            'label' => 'Проверить подготовку к первой смене',
            'external' => false,
        ];
    }
    if ($id === 'F-PROMO-PROJECT') {
        return [
            'url' => '',
            'label' => 'Сверить справочник площадок',
            'external' => true,
        ];
    }
    if (in_array($id, ['F-CASE-03', 'F-CASE-04', 'F-CASE-05'], true)) {
        return [
            'url' => (new moodle_url('/local/learning_workspace/academy_work.php'))->out(false) . '#fallback',
            'label' => 'Повторить памятку по сложным ситуациям',
            'external' => false,
        ];
    }
    if ($id === 'F-DIALOG-01') {
        return ['url' => ac_lesson_step_url('L4', 1), 'label' => 'Повторить день 4 · полный путь клиента',
            'external' => false, 'related' => [
                ['url' => ac_lesson_step_url('L7', 1), 'label' => 'Повторить демо-модуль 7',
                    'external' => false],
            ]];
    }
    if (in_array($id, ['F-CASE-01', 'F-CASE-02'], true)) {
        return ['url' => ac_lesson_step_url('L4', 1), 'label' => 'Повторить день 4 · полный путь клиента',
            'external' => false];
    }
    if (preg_match('/lesson:[^;]*?(L[1-7])/', $source, $match)) {
        $day = $match[1];
        return ['url' => ac_lesson_step_url($day, 1),
            'label' => 'Повторить день ' . (int)substr($day, 1), 'external' => false];
    }
    return null;
}
function ac_final_answer_needs_remediation(array $question, array $feedback, array $rubric,
        bool $reviewed): bool {
    if (($question['type'] ?? '') === 'choice') {
        return ($feedback['correct'] ?? null) === false;
    }
    if (!$reviewed) {
        return false;
    }
    if (($rubric['schema'] ?? '') === 'five_part_v1') {
        foreach ((array)($question['criteria'] ?? []) as $criterion) {
            $score = (int)($rubric['criteria'][(string)($criterion['id'] ?? '')]['score'] ?? 0);
            if ($score < 2) {
                return true;
            }
        }
        return false;
    }
    $mark = $rubric[(string)($question['id'] ?? '')] ?? null;
    return $mark === 'fail' || (is_array($mark) && ($mark['result'] ?? '') === 'fail');
}
function ac_value($record, string $key, $fallback = null) {
    return is_object($record) ? ($record->{$key} ?? $fallback) : (is_array($record) ? ($record[$key] ?? $fallback) : $fallback);
}
function ac_answer_display(array $question, string $answer): string {
    if (($question['type'] ?? '') !== 'choice') {
        return $answer;
    }
    foreach ((array)($question['options'] ?? []) as $option) {
        if ((string)($option['id'] ?? '') === $answer) {
            return (string)($option['text'] ?? $answer);
        }
    }
    return $answer;
}
function ac_decode($value): array {
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}
function ac_status(string $value): array {
    $statuses = [
        'passed' => ['Закрытая часть пройдена', 'is-success'],
        'failed' => ['Есть темы для разбора', 'is-warn'],
        'draft_unverified' => ['Сохранённая версия задания требует проверки', 'is-warn'],
        'awaiting_review' => ['Ожидает проверки', 'is-warn'],
        'ready' => ['Готов по экзамену', 'is-success'],
        'rework' => ['Нужна доработка', 'is-warn'],
        'not_ready' => ['Пока не готов', 'is-warn'],
        'practice_accepted' => ['Свободный ответ принят', 'is-success'],
        'practice_rework' => ['Доработать свободный ответ', 'is-warn'],
        'practice_feedback' => ['Комментарий без зачёта', 'is-muted'],
    ];
    return $statuses[$value] ?? ['Не начат', 'is-muted'];
}
function ac_question_list(array $pack): array {
    return array_values((array)($pack['questions'] ?? []));
}
function ac_dialogue_answer(array $turns): string {
    return \local_learning_workspace\local\academy_dialogue_answer_codec::encode($turns);
}
function ac_dialogue_turns(string $answer): array {
    return \local_learning_workspace\local\academy_dialogue_answer_codec::decode($answer);
}
function ac_part_label(string $part): string {
    $labels = [
        'foundation' => 'А1 · Базовые знания',
        'process' => 'А2 · Клиентский путь',
        'dialogue' => 'Б · Диалог с клиентом',
        'complex' => 'В · Сложные ситуации',
        'domain' => 'Демо-проверка',
        'safety' => 'Сложные ситуации',
        'regulation' => 'Регламент и подготовка',
        'promotion' => 'Самопрезентация',
    ];
    return $labels[$part] ?? 'Практический блок';
}
function ac_error_message(Throwable $exception, string $view = 'final', array $answers = [], array $pack = []): string {
    $code = $exception instanceof moodle_exception ? $exception->errorcode : '';
    if ($view === 'day' && in_array($code, ['assessmentincomplete', 'invalidassessmentanswer'], true)) {
        $haschoices = false;
        $validchoices = true;
        $emptytext = false;
        foreach (ac_question_list($pack) as $question) {
            $id = (string)($question['id'] ?? '');
            if (($question['type'] ?? '') === 'choice') {
                $haschoices = true;
                $validchoices = $validchoices && isset($answers[$id]) && is_string($answers[$id]) &&
                    in_array($answers[$id], array_column((array)($question['options'] ?? []), 'id'), true);
            } elseif (($question['type'] ?? '') === 'text') {
                $emptytext = $emptytext || !array_key_exists($id, $answers) ||
                    (is_string($answers[$id]) && trim($answers[$id]) === '');
            }
        }
        // A stale/invalid option must keep its own diagnosis, not hide behind a blank text field.
        if ($haschoices && $validchoices && $emptytext) {
            return 'Тест дня не отправлен: свободный ответ пуст или состоит только из пробелов. ' .
                'Напишите ответ своими словами в этой форме и повторите отправку; новая попытка пока не создана.';
        }
        if ($code === 'assessmentincomplete') {
            return 'Тест дня пока не отправлен: заполните каждый вопрос в этой форме. Новая попытка не создана.';
        }
    }
    $messages = [
        'assessmentincomplete' => 'Экзамен пока не отправлен: заполните пропущенные ответы. Введённое осталось в форме, но перед уходом со страницы сохраните черновик.',
        'assessmentawaitingreview' => 'Предыдущая работа ещё проверяется. Дождитесь решения тренера; при необходимости он разрешит повтор.',
        'assessmentattemptlocked' => 'Ответы уже отправлены. Повторную отправку может разрешить тренер после проверки.',
        'invalidassessmentanswer' => 'Один из ответов не подходит текущей версии теста. Обновите страницу и заполните тест заново.',
        'assessmentidempotencyconflict' => 'Эта отправка уже была использована с другим набором ответов. Обновите страницу и отправьте новую попытку.',
        'concurrencyconflict' => 'Черновик уже изменён в другой вкладке или открыта новая попытка. Эта отправка ничего не сохранила и не заменила. Скопируйте нужный текст из формы, затем обновите страницу и сверьте сохранённые ответы.',
        'invalidlesson' => 'Такого учебного дня нет. Вернитесь к карте проверок.',
        'assessmentreviewforbidden' => 'У вашей роли нет права отправлять ответы в этом разделе.',
    ];
    return $messages[$code] ?? 'Ответы не сохранены. Проверьте заполнение и попробуйте ещё раз. Если ошибка повторится, обратитесь к администратору обучения.';
}
function ac_render_question(array $question, int $number, bool $canwrite, array $postedanswers = [],
        array $postedturns = [], string $attemptseed = '', string $tokenseed = ''): void {
    $id = (string)($question['id'] ?? '');
    $type = (string)($question['type'] ?? 'choice');
    $previous = (string)($postedanswers[$id] ?? '');
    $question = \local_learning_workspace\local\academy_branding::copy($question); $draft = ($question['status'] ?? '') !== 'approved_structure';
    $prompt = \local_learning_workspace\local\academy_branding::text($question['prompt'] ?? '');
    $longtext = $type === 'text' && \core_text::strlen($prompt) > 240;
    $promptid = 'ac-question-context-' . $number;
    echo '<fieldset class="ac-question" data-ac-part="' . ac_e($question['part'] ?? '') . '"' .
        ($longtext ? ' aria-describedby="' . $promptid . '"' : '') . '><legend><small>ВОПРОС ' . $number .
        ($draft ? ' · ЧЕРНОВИК ДЛЯ АПРОБАЦИИ' : '') . '</small>' .
        ($longtext ? (!empty($question['turns']) ? 'Диалог с клиентом' : 'Ответ на ситуацию') : ac_e($prompt)) . '</legend>';
    if ($longtext) {
        $sections = preg_split('/(?<=\.)\s+(?=(?:Напишите|Покажите|Объясните|Составьте)\b)/u', $prompt, 2);
        echo '<div class="ac-question-context" id="' . $promptid . '">';
        foreach ($sections as $section) { echo '<p>' . nl2br(ac_e($section)) . '</p>'; }
        echo '</div>';
    }
    if ($type === 'text' && !empty($question['criteria'])) {
        echo '<details class="lw-details"><summary>Как проверят ответ</summary>' .
            '<p>0 — критерий не выполнен; 1 — выполнен частично; 2 — выполнен полностью. Для отмеченных обязательных критериев нужны именно 2 балла.</p><ul>';
        foreach ($question['criteria'] as $criterion) {
            echo '<li>' . ac_e($criterion['label'] ?? '') . ' · 0–2 балла' .
                (!empty($criterion['required_score']) ? ' · обязательно 2' : '') . '</li>';
        }
        echo '</ul></details>';
    }
    if ($type === 'text' && !empty($question['turns'])) {
        $turns = $postedturns ?: ac_dialogue_turns($previous);
        echo '<p class="ac-help">Ответьте на каждый ход отдельно. Это учебный чат, а не переписка с реальным клиентом.</p>';
        foreach ($question['turns'] as $turn) {
            $step = (int)$turn['number'];
            echo '<section class="ac-dialogue-turn"><span class="ac-dialogue-phase">ХОД ' . $step . ' · ' . ac_e($turn['phase']) . '</span>';
            if ($step === 3 && !empty($question['training_offer'])) {
                $offer = $question['training_offer'];
                echo '<div class="ac-training-card"><strong>Учебная карточка услуги</strong><p>' . ac_e($offer['format']) . ' · ' .
                    ac_e($offer['duration']) . ' · ' . ac_e($offer['price']) . '</p><small>' . ac_e(\local_learning_workspace\local\academy_branding::text($offer['notice'])) . '</small></div>';
            }
            if ($step === 6 && !empty($question['training_spread'])) {
                echo '<div class="ac-training-card"><strong>Учебный расклад · три позиции</strong><ol>';
                foreach ($question['training_spread'] as $card) {
                    echo '<li><b>' . ac_e($card['position']) . ' — ' . ac_e($card['card']) . '</b><span>' . ac_e($card['hint']) . '</span></li>';
                }
                echo '</ol><small>' . ac_e($question['spread_notice'] ??
                    'Подсказки задают тему карты, но не готовый персональный вывод.') . '</small></div>';
            }
            echo '<p class="ac-client-message"><strong>Клиент</strong><br>' . ac_e($turn['client']) . '</p>';
            echo '<label class="ac-text-label" for="ac-dialogue-' . $step . '">Ваш ответ на ход ' . $step . '</label>';
            echo '<textarea id="ac-dialogue-' . $step . '" name="dialogue_turn_' . $step . '" maxlength="900" required' .
                (!$canwrite ? ' disabled' : '') . '>' . ac_e($turns[$step] ?? '') . '</textarea></section>';
        }
    } else if ($type === 'text') {
        $fieldid = 'ac-answer-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
        echo '<label class="ac-text-label" for="' . ac_e($fieldid) . '">Ваш ответ</label>';
        echo '<textarea id="' . ac_e($fieldid) . '" name="answers[' . ac_e($id) . ']" maxlength="10000" required aria-describedby="' . ac_e($fieldid) . '-help"' . (!$canwrite ? ' disabled' : '') . '>' . ac_e($previous) . '</textarea>';
        echo '<p class="ac-help" id="' . ac_e($fieldid) . '-help">Ответьте своими словами на учебную ситуацию. Не используйте реальные данные клиентов.</p>';
    } else {
        foreach (academy_option_order::ordered($question, $attemptseed) as $option) {
            $optionid = (string)($option['id'] ?? '');
            $token = academy_option_order::answer_token($id, $optionid, $tokenseed);
            echo '<label class="lw-option"><input type="radio" name="answers[' . ac_e($id) . ']" value="' . ac_e($token) . '" required' . ($previous === $optionid ? ' checked' : '') . (!$canwrite ? ' disabled' : '') . '><span>' . ac_e($option['text'] ?? '') . '</span></label>';
        }
    }
    echo '</fieldset>';
}
/** Rejected old-form input stays copyable, without becoming the next attempt's prefill. */
function ac_render_rejected_final_answers(array $answers, array $pack): void {
    $questions = [];
    foreach ($pack['questions'] ?? [] as $question) {
        $questions[(string)$question['id']] = $question;
    }
    echo '<details class="lw-details" open><summary>Неотправленные ответы из прежней формы</summary>' .
        '<p>Эта отправка не сохранена и не перенесена в новую попытку. Здесь можно скопировать введённое, затем обновить страницу и сверить сохранённую работу.</p>';
    $number = 0;
    foreach ($answers as $id => $answer) {
        if (!is_string($answer) || trim($answer) === '') { continue; }
        $number++;
        $display = isset($questions[$id]) ? ac_answer_display($questions[$id], $answer) : $answer;
        echo '<label class="ac-text-label" for="ac-recovery-' . $number . '">Ответ ' . $number . ' из прежней формы</label>' .
            '<textarea id="ac-recovery-' . $number . '" readonly rows="5">' . ac_e($display) . '</textarea>';
    }
    echo '</details>';
}
function ac_render_sidebar(array $days, string $view, string $lessonid, array $final): void {
    echo '<nav class="lw-sidebar" aria-label="Маршрут аттестации"><p class="lw-navcaption">ВАШ МАРШРУТ</p><ol class="lw-stepnav">';
    foreach (lesson_catalog::all() as $index => $lesson) {
        $id = $lesson['id'];
        $latest = $days[$id]['latest'] ?? null;
        $state = ac_status((string)ac_value($latest, 'status', ''));
        $state[0] = \local_learning_workspace\local\academy_result_view::state($latest,$days[$id]['review']??null);
        if (!$latest || ($days[$id]['review']->decision??'')!=='practice_accepted') { $state[1]='is-muted'; }
        $current = $view === 'day' && $lessonid === $id;
        echo '<li class="' . ($current ? 'is-current ' : '') . ($state[1] === 'is-success' ? 'is-done' : '') . '"><a href="' . ac_e(ac_url('day', $id)) . '"' . ($current ? ' aria-current="page"' : '') . '><b aria-hidden="true">' . ($state[1] === 'is-success' ? '✓' : sprintf('%02d', $index + 1)) . '</b><span>' . ac_e($lesson['title']) . '<small>' . ac_e($state[0]) . '</small></span></a></li>';
    }
    $current = $view === 'final';
    $finalstate = empty($final['latest']) ? 'Можно пройти сразу' :
        \local_learning_workspace\local\academy_result_view::state($final['latest'], $final['review'] ?? null);
    echo '<li class="' . ($current ? 'is-current' : '') . '"><a href="' . ac_e(ac_url('final')) . '"' . ($current ? ' aria-current="page"' : '') . '><b aria-hidden="true">★</b><span>Итоговый экзамен<small>' . ac_e($finalstate) . '</small></span></a></li>';
    echo '</ol></nav>';
}

$error = '';
$postedanswers = [];
$postedturns = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    academy_access::require_candidate();
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $idemkey = required_param('idemkey', PARAM_ALPHANUMEXT);
    $answers = optional_param_array('answers', [], PARAM_RAW_TRIMMED);
    if ($view === 'final') {
        for ($number = 1; $number <= 9; $number++) {
            $postedturns[$number] = optional_param('dialogue_turn_' . $number, '', PARAM_RAW_TRIMMED);
        }
        $answers['F-DIALOG-01'] = ac_dialogue_answer($postedturns);
    }
    $postedanswers = $answers;
    try {
        if (($action === 'day' && $view === 'day') ||
                (in_array($action, ['draft', 'final'], true) && $view === 'final')) {
            $submittedpack = $view === 'day' ? bank::day($lessonid) :
                bank::final(required_param('variant', PARAM_INT));
            $tokenseed = implode('|', [(int)$USER->id, (string)$submittedpack['version']]);
            try {
                $answers = academy_option_order::decode_answers($submittedpack, $answers, $tokenseed);
            } catch (InvalidArgumentException $exception) {
                throw new moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
            }
            $postedanswers = $answers;
        }
        if ($action === 'day' && $view === 'day') {
            assessments::submit_day((int)$USER->id, $lessonid, $answers, $idemkey);
        } else if ($action === 'draft' && $view === 'final') {
            assessments::save_final_draft((int)$USER->id, required_param('variant', PARAM_INT), $answers,
                required_param('bankhash', PARAM_ALPHANUM), required_param('draftrevision', PARAM_INT),
                required_param('expectedattemptno', PARAM_INT));
            redirect(new moodle_url('/local/learning_workspace/academy.php', ['view' => 'final', 'draftsaved' => 1]));
        } else if ($action === 'final' && $view === 'final') {
            $submittedvariant = required_param('variant', PARAM_INT);
            if (count(array_filter($postedturns, static fn($value): bool => trim((string)$value) !== '')) !== 9) {
                throw new moodle_exception('assessmentincomplete', 'local_learning_workspace');
            }
            assessments::submit_final((int)$USER->id, $answers, $idemkey,
                $submittedvariant, required_param('expectedattemptno', PARAM_INT),
                required_param('bankhash', PARAM_ALPHANUM), required_param('draftrevision', PARAM_INT));
        } else {
            throw new moodle_exception('invalidparameter');
        }
        redirect(new moodle_url('/local/learning_workspace/academy.php',
            array_merge(['view' => $view, 'saved' => 1], $view === 'day' ? ['lesson' => $lessonid] : [])));
    } catch (Throwable $exception) {
        $error = ac_error_message($exception, $view, $postedanswers, $submittedpack ?? []);
    }
}

$days = assessments::day_summary((int)$USER->id);
$final = assessments::final_summary((int)$USER->id);
$catalog = lesson_catalog::all();
$day = $days[$lessonid] ?? ['attempts' => 0, 'latest' => null, 'history' => []];
$displaydayattempt = $day['latest'] ?? null;
if ($view === 'day' && $requestedattemptid > 0) {
    $displaydayattempt = null;
    foreach ($day['history'] as $historicalattempt) {
        if ((int)$historicalattempt->id === $requestedattemptid) {
            $displaydayattempt = $historicalattempt;
            break;
        }
    }
    if ($displaydayattempt === null) {
        $error = 'Эта попытка недоступна. Вернитесь к истории своих попыток.';
    }
}
$displayfinalattempt = null;
if ($view === 'final' && $requestedattemptid > 0) {
    foreach ($final['history'] as $historicalattempt) {
        if ((int)$historicalattempt->id === $requestedattemptid) {
            $displayfinalattempt = $historicalattempt;
            break;
        }
    }
    if ($displayfinalattempt === null) {
        $error = 'Эта попытка недоступна. Вернитесь к истории своих итоговых попыток.';
    }
}
$finalvariant = bank::final_variant_for_attempt(1 + (int)($final['attempts'] ?? 0));
$finalstatus = (string)($final['status'] ?? 'not_started');
$latestfinal = $final['latest'] ?? null;
$latestfinalhistorical = $latestfinal && (string)$latestfinal->bankversion !==
    (string)bank::final(bank::final_variant_for_attempt((int)$latestfinal->attemptno))['version'];
$homenext = \local_learning_workspace\local\academy_next_step::for_final_status($finalstatus);
if ($latestfinalhistorical) {
    $homenext['text'] = 'Последняя попытка относится к прежней версии экзамена. ' .
        ($finalstatus === 'awaiting_review' ?
            'Ответы сохранены и ожидают проверки; новые отправки зависят от правил курса.' :
            'Решение сохранено; необходимость пересдачи определяет ответственный за обучение.');
    $homenext['label'] = $finalstatus === 'awaiting_review' ?
        'Посмотреть прежнюю попытку →' : 'Посмотреть решение и актуальный вариант →';
}
$homenexturl = $homenext['destination'] === 'final'
    ? ac_url('final') : (new moodle_url('/local/learning_workspace/lesson_one.php'))->out(false);
$pack = $view === 'day' ? bank::day($lessonid) : ($view === 'final' ? bank::final($finalvariant) : []);
$attemptstate = null;
if ($canwrite && in_array($view, ['day','final'], true)) {
    $attemptkind = $view === 'day' ? 'day' : 'final';
    $attemptlesson = $view === 'day' ? $lessonid : 'FINAL';
    $attemptstate = \local_learning_workspace\local\academy_attempt_policy::state((int)$USER->id, $attemptkind, $attemptlesson);
    if ($attemptstate['allowed'] && $requestedattemptid === 0) {
        try { \local_learning_workspace\local\academy_attempt_policy::begin((int)$USER->id, $attemptkind, $attemptlesson); }
        catch (Throwable $exception) { $error = ac_error_message($exception); $attemptstate['allowed'] = false; }
    }
}
$submissionallowed = !$canwrite || !empty($attemptstate['allowed']);
$draftstate = $view === 'final' && $canwrite && $finalstatus !== 'awaiting_review'
    ? assessments::final_draft_state((int)$USER->id, $finalvariant) : null;
$draftanswers = $draftstate['answers'] ?? null;
$draftrevision = $draftstate['revision'] ?? 0;
$draftattemptno = 1 + (int)($final['attempts'] ?? 0);
if ($view === 'final' && $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    // Keep a rejected form fenced; do not silently rebase stale answers on the current revision.
    $draftrevision = optional_param('draftrevision', -1, PARAM_INT);
    $draftattemptno = optional_param('expectedattemptno', -1, PARAM_INT);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $draftanswers !== null) {
    $postedanswers = $draftanswers;
    $postedturns = ac_dialogue_turns((string)($draftanswers['F-DIALOG-01'] ?? ''));
}
$bankhash = $view === 'final' ? hash('sha256', json_encode($pack,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) : '';
$rejectedfinalanswers = [];
$rejectedfinalpack = [];
if ($view === 'final' && $_SERVER['REQUEST_METHOD'] === 'POST' &&
        $error !== '' && ($finalstatus === 'awaiting_review' ||
            optional_param('variant', 0, PARAM_INT) !== $finalvariant ||
            optional_param('expectedattemptno', 0, PARAM_INT) !== 1 + (int)($final['attempts'] ?? 0))) {
    // Preserve rejected input separately; never rebase it into the current case.
    $rejectedfinalanswers = $postedanswers;
    $rejectedfinalpack = $submittedpack ?? [];
    $postedanswers = [];
    $postedturns = [];
}
$saved = optional_param('saved', 0, PARAM_INT) === 1;
if ($error !== '') {
    $PAGE->requires->js_init_code('setTimeout(function(){var e=document.querySelector(".lw-academy .lw-error");if(e){e.focus();}},0);');
}
if ($view === 'final') {
    $PAGE->requires->js_init_code(<<<'JS'
(function () {
  function init() {
    var form = document.querySelector('.lw-academy form[data-ac-final-form]');
    if (!form) { return; }
    var map = form.querySelector('.ac-exam-map');
    if (!map) { return; }
    var fields = Array.from(form.querySelectorAll('fieldset.ac-question[data-ac-part]'));
    var find = map.querySelector('[data-ac-find-missing]');
    var status = map.querySelector('[data-ac-progress-status]');
    var completeMessage = 'Все поля заполнены. Проверьте ответы и отправьте экзамен.';
    var incompleteMessage = 'Есть незаполненные ответы. Найдите первый пропуск.';
    function complete(field) {
      var textareas = Array.from(field.querySelectorAll('textarea'));
      return textareas.length ? textareas.every(function (input) { return input.value.trim() !== ''; }) :
          !!field.querySelector('input[type="radio"]:checked');
    }
    function update() {
      map.querySelectorAll('[data-ac-progress-part]').forEach(function (badge) {
        var part = badge.getAttribute('data-ac-progress-part');
        var questions = fields.filter(function (field) { return field.getAttribute('data-ac-part') === part; });
        var filled = questions.filter(complete).length;
        badge.textContent = filled + '/' + questions.length;
      });
      // A helper message must remain true after the candidate edits an answer.
      // Do not repeat the same live-region announcement on every keystroke.
      var message = fields.every(complete) ? completeMessage : incompleteMessage;
      if (status && status.textContent && status.textContent !== message) {
        status.textContent = message;
      }
    }
    if (find) {
      find.hidden = false;
      find.addEventListener('click', function () {
        var missing = fields.find(function (field) { return !complete(field); });
        if (!missing) {
          if (status) { status.textContent = completeMessage; }
          return;
        }
        if (status) { status.textContent = 'Переход к первому незаполненному вопросу.'; }
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        missing.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'start'});
        var target = Array.from(missing.querySelectorAll('textarea')).find(function (input) {
          return input.value.trim() === '';
        }) || missing.querySelector('input[type="radio"]:not(:checked)');
        if (target) { target.focus({preventScroll: true}); }
      });
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JS
    );
}
$title = $view === 'day' ? 'Проверка знаний · ' . ($pack['title'] ?? $lessonid) : ($view === 'final' ? 'Итоговый экзамен' : 'Проверка знаний');

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-academy" lang="ru">
  <a class="lw-skip" href="#academy-main">К основному содержанию</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">УЧЕБНАЯ АТТЕСТАЦИЯ</p><h1><?= ac_e($title) ?></h1></div>
    <div class="lw-layout">
      <?php ac_render_sidebar($days, $view, $lessonid, $final); ?>
      <main id="academy-main" class="lw-main" tabindex="-1">
        <?php if ($error !== ''): ?><div class="lw-alert lw-error" role="alert" tabindex="-1"><?= ac_e($error) ?></div><?php endif; ?>
        <?php if ($rejectedfinalanswers): ac_render_rejected_final_answers($rejectedfinalanswers, $rejectedfinalpack); endif; ?>
        <?php if ($saved && $error === ''): ?><div class="lw-alert" role="status">Ответы сохранены.</div><?php endif; ?>
        <?php if (!$canwrite): ?><div class="lw-alert" role="status">Режим чтения. Отправка ответов доступна учащемуся.</div><?php endif; ?>
        <article class="lw-sheet"><div class="lw-workbenchbar"><span><?= $view === 'home' ? 'Карта проверок' : ($view === 'day' ? 'Тест дня' : 'Финальная проверка') ?></span></div><div class="lw-sheetbody">
<?php if ($view === 'final' && $finalstatus !== 'awaiting_review' && $requestedattemptid === 0 && $submissionallowed): ?><p class="ac-exam-start"><a class="lw-primary ac-jump-answers" href="#ac-exam-start">К вопросам экзамена ↓</a></p><?php endif; ?>
        <?php if ($view === 'final' || ($view === 'day' && in_array($lessonid, ['L6', 'L7'], true))): ?><div class="ac-warning">Практика дней 6–7 и предметная часть экзамена ещё не утверждены: доступна обратная связь без подтверждения готовности.</div><?php endif; ?>
        <?php if ($view === 'home'): ?>
          <p class="ac-intro">После каждого дня пройдите короткий тест. Уверены в подготовке? Можно сразу открыть итоговый экзамен — требования к ответам одинаковы для всех.</p>
          <div class="ac-primary-path"><h2>Следующий шаг</h2><p><?= ac_e($homenext['text']) ?></p><a class="lw-primary" href="<?= ac_e($homenexturl) ?>"><?= ac_e($homenext['label']) ?></a></div>
          <h2>Тесты по дням</h2><ol class="ac-day-list">
          <?php foreach ($catalog as $index => $lesson): $state = ac_status((string)ac_value($days[$lesson['id']]['latest'] ?? null, 'status', '')); ?>
            <li><b aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></b><span><strong><?= ac_e($lesson['title']) ?></strong><small><?= ac_e(\local_learning_workspace\local\academy_result_view::state($days[$lesson['id']]['latest']??null,$days[$lesson['id']]['review']??null)) ?></small><?php if (!empty($days[$lesson['id']]['latest'])): ?><?= \local_learning_workspace\local\academy_result_view::timeline($days[$lesson['id']]['latest'],$days[$lesson['id']]['review']??null) ?><?php if (!empty($days[$lesson['id']]['review'])): ?><small><?= ac_e(\core_text::substr($days[$lesson['id']]['review']->feedback,0,180)) ?></small><?php endif; endif; ?></span><a href="<?= ac_e((new moodle_url('/local/learning_workspace/academy.php',['view'=>'day','lesson'=>$lesson['id']]))->out(false)) ?>"><?= !empty($days[$lesson['id']]['latest'])?'Открыть результат →':'Открыть тест →' ?></a></li>
          <?php endforeach; ?>
          </ol><section class="ac-result <?= ($final['review']->decision??'')==='ready' && !$latestfinalhistorical?'':'is-warn' ?>"><h2>Итоговый экзамен</h2><p><?= ac_e(\local_learning_workspace\local\academy_result_view::state($final['latest']??null,$final['review']??null)) ?></p><?php if (!empty($final['latest'])): ?><?= \local_learning_workspace\local\academy_result_view::timeline($final['latest'],$final['review']??null) ?><?php if (!empty($final['review'])): ?><p><?= ac_e(\core_text::substr($final['review']->feedback,0,180)) ?></p><?php endif; endif; ?><a href="<?= ac_e(ac_url('final')) ?>"><?= !empty($final['latest'])?'Открыть результат экзамена →':'Открыть итоговый экзамен →' ?></a></section>
        <?php elseif ($view === 'day'): $latest = $displaydayattempt; $state = ac_status((string)ac_value($latest, 'status', '')); $dayreview = $latest ? ($day['reviews_by_attempt'][(int)$latest->id] ?? null) : null; ?>
          <div class="ac-statusline"><span class="ac-pill <?= ac_e($state[1]) ?>"><?= ac_e($state[0]) ?></span><span class="ac-pill is-muted">Попыток: <?= (int)($day['attempts'] ?? 0) ?></span></div>
          <?php if ($latest && (int)$latest->id !== (int)ac_value($day['latest'] ?? null, 'id', 0)): ?><p class="ac-muted">Вы смотрите попытку <?= (int)$latest->attemptno ?>. Её ответы и решение сохранены в истории.</p><?php endif; ?>
          <?php if ($latest): $daystatus = (string)ac_value($latest, 'status', ''); $savedquestions = ac_decode(ac_value($latest, 'questionsjson', '')); $daysummary = \local_learning_workspace\local\academy_result_view::day_summary($latest, $dayreview ?: null); ?><section class="ac-result <?= ac_e($daysummary['tone']) ?>" aria-labelledby="ac-day-result"><h2 id="ac-day-result"><?= ac_e($daysummary['title']) ?></h2><p><?= ac_e(\local_learning_workspace\local\academy_result_view::text('score',(object)['score'=>(int)ac_value($latest,'score',0),'passmark'=>(int)ac_value($latest,'passmark',0)])) ?></p><p><?= ac_e($daysummary['next']) ?></p></section>
<?php if ($latest): echo \local_learning_workspace\local\academy_result_view::timeline($latest,$dayreview ?: null); endif; ?>
<?php if ($dayreview): $daymarks = ac_decode((string)$dayreview->rubricjson); $dayneedspractice = false; ?><section class="ac-feedback" aria-labelledby="ac-day-feedback"><h2 id="ac-day-feedback"><?= ac_e(\local_learning_workspace\local\academy_result_view::text('trainer_comment')) ?></h2><p><span class="ac-pill is-muted"><?= ac_e(ac_status((string)$dayreview->decision)[0]) ?></span></p><p><?= nl2br(ac_e($dayreview->feedback)) ?></p><?php foreach ($daymarks as $daymark): if (($daymark['result'] ?? '') === 'fail'): $dayneedspractice = true; ?><p><strong>Фрагмент:</strong> «<?= nl2br(ac_e($daymark['quote'] ?? '')) ?>»</p><p><strong>Что исправить:</strong> <?= ac_e($daymark['nextstep'] ?? '') ?></p><?php endif; endforeach; ?><?php if ($dayneedspractice): ac_render_day_rework_actions($lessonid, $canwrite && $submissionallowed && $requestedattemptid===0); endif; ?></section><?php endif; ?>
            <?php if ((string)ac_value($latest, 'bankversion', '') !== (string)($pack['version'] ?? '')): ?><div class="lw-note">Ниже — вопросы вашей сохранённой попытки. Форма для повторения использует обновлённую версию теста.</div><?php endif; ?>
            <?php $showfeedback = \local_learning_workspace\local\academy_attempt_policy::feedback_visible($latest, (bool)$dayreview); $feedback = $showfeedback ? ac_decode(ac_value($latest, 'feedbackjson', '')) : []; foreach ($savedquestions as $index => $question): $item = $feedback[$question['id']] ?? null; if (!is_array($item)) { continue; } $ischoice = ($question['type'] ?? '') === 'choice'; $correct = $item['correct'] ?? null; ?><div class="ac-feedback"><h3>Вопрос <?= $index + 1 ?><?= ($item['status'] ?? '') !== 'approved_structure' ? ' · черновик' : '' ?></h3><p><?= ac_e(\local_learning_workspace\local\academy_branding::text($question['prompt'])) ?></p><p><span class="ac-pill <?= $correct === true ? 'is-success' : 'is-warn' ?>"><?= !$ischoice ? 'Ответ сохранён · без автоматической оценки' : ($correct === true ? 'Верно' : ($correct === false ? 'Неверно' : 'Черновик · без оценки')) ?></span></p><p><?= ac_e(\local_learning_workspace\local\academy_branding::text(($item['explanation'] ?? '') !== '' ? $item['explanation'] : ($dayreview?'Ответ проверен. Решение и замечания показаны выше.':'Ответ сохранён и ожидает проверки.'))) ?></p><?php if ($ischoice && $correct === false): ?><p><a href="<?= ac_e(ac_lesson_step_url($lessonid, 1)) ?>">Повторить принцип этого дня →</a></p><?php endif; ?></div><?php endforeach; ?>
            <?php $savedanswers = ac_decode(ac_value($latest, 'answersjson', '')); foreach ($savedquestions as $question): if (($question['type'] ?? '') !== 'text') { continue; } $answertext = (string)($savedanswers[$question['id']] ?? ''); if ($answertext === '') { continue; } ?><details class="lw-details"><summary>Ваш сохранённый ответ своими словами</summary><p><?= nl2br(ac_e($answertext)) ?></p></details><?php endforeach; ?>
          <?php endif; ?>
          <?php if (!$submissionallowed): ?><p class="lw-note" role="status"><?= !empty($attemptstate['awaiting_review']) ? 'Ответы отправлены и ожидают проверки тренером.' : ac_e(\local_learning_workspace\local\academy_attempt_policy::retry_message((int)$USER->id,$lessonid)) ?></p><?php endif; ?>
          <?php if ($requestedattemptid > 0): ?><p><a class="lw-secondary" href="<?= ac_e(ac_url('day', $lessonid)) ?>">К текущему тесту →</a></p><?php endif; ?>
          <?php if ($submissionallowed && $requestedattemptid === 0): ?>
          <h2 id="ac-day-current-heading"><?= !$canwrite ? 'Вопросы текущей версии теста' : ($latest ? (!empty($attemptstate['permit']) ? 'Повтор, разрешённый тренером' : 'Следующая попытка') : 'Ваши ответы') ?></h2>
          <p class="ac-intro">Выберите ответы и напишите собственный ответ на ситуацию. <?= ($attemptstate['policy']['feedback'] ?? 'after_review') === 'immediate' ? 'Пояснения к закрытым вопросам появятся после отправки.' : 'Пояснения к закрытым вопросам появятся после проверки тренером.' ?> Свободный ответ проверит экзаменатор.</p>
<form id="ac-day-current-test" tabindex="-1" aria-labelledby="ac-day-current-heading" data-ac-unsaved="<?= $error !== '' && !empty($postedanswers) ? 'true' : 'false' ?>" method="post" action="<?= ac_e(ac_url('day', $lessonid)) ?>"><input type="hidden" name="sesskey" value="<?= ac_e(sesskey()) ?>"><input type="hidden" name="action" value="day"><input type="hidden" name="idemkey" value="<?= ac_e(bin2hex(random_bytes(16))) ?>">
          <p data-ac-unsaved-hint role="status" hidden>Есть несохранённые изменения. Отправьте тест, прежде чем уходить со страницы.</p>
          <?php $dayseed = implode('|', [(int)$USER->id, 'day', $lessonid, 1 + (int)($day['attempts'] ?? 0), (string)$pack['version']]); $tokenseed = implode('|', [(int)$USER->id, (string)$pack['version']]); foreach (ac_question_list($pack) as $index => $question) { ac_render_question($question, $index + 1, $canwrite, $postedanswers, [], $dayseed, $tokenseed); } ?>
          <div class="ac-sticky-action"><button class="lw-primary" type="submit" <?= !$canwrite ? 'disabled' : '' ?>>Отправить ответы →</button><p>После отправки ответы нельзя изменить. <?= ac_e(\local_learning_workspace\local\academy_attempt_policy::retry_message((int)$USER->id,$lessonid)) ?></p></div></form>
          <?php endif; ?>
          <?php if (!empty($day['history'])): ?><details class="lw-details"<?= $requestedattemptid > 0 ? ' open' : '' ?>><summary>История попыток</summary><ol><?php foreach ($day['history'] as $attempt): $attemptreview = $day['reviews_by_attempt'][(int)$attempt->id] ?? null; ?><li><a href="<?= ac_e((new moodle_url('/local/learning_workspace/academy.php', ['view' => 'day', 'lesson' => $lessonid, 'attempt' => (int)$attempt->id]))->out(false)) ?>"<?= (int)ac_value($latest, 'id', 0) === (int)$attempt->id ? ' aria-current="page"' : '' ?>>Попытка <?= (int)ac_value($attempt, 'attemptno', 0) ?></a> · <?= ac_e(userdate((int)$attempt->timecreated, '%d.%m.%Y %H:%M')) ?> · <?= (int)ac_value($attempt, 'score', 0) ?>% · <?= ac_e(ac_status((string)ac_value($attempt, 'status', ''))[0]) ?> · <?= $attemptreview ? ac_e(ac_status((string)$attemptreview->decision)[0]) : 'свободный ответ ждёт проверки' ?></li><?php endforeach; ?></ol></details><?php endif; ?>
          <div class="lw-actions"><a class="lw-secondary" href="<?= ac_e($lessonid === 'L7' ? ac_url('final') : (new moodle_url('/local/learning_workspace/academy_day.php', ['lesson' => 'L' . ((int)substr($lessonid, 1) + 1)]))->out(false)) ?>"><?= $lessonid === 'L7' ? 'К итоговому экзамену →' : 'К следующему уроку →' ?></a></div>
        <?php else: $latest = $final['latest'] ?? null; $state = ac_status($finalstatus); ?>
<?php if ($requestedattemptid === 0): ?>          <div class="ac-statusline"><span class="ac-pill <?= ac_e($latestfinalhistorical && $state[1] === 'is-success' ? 'is-warn' : $state[1]) ?>"><?= ac_e($state[0]) ?><?= $latestfinalhistorical ? ' · прежняя версия' : '' ?></span><span class="ac-pill is-muted">Попыток: <?= (int)($final['attempts'] ?? 0) ?></span><?php if ($finalstatus !== 'awaiting_review' && $requestedattemptid === 0 && ($submissionallowed || $latest)): ?><span class="ac-pill is-muted"><?= $submissionallowed ? 'Вариант ' : 'Сданный вариант ' ?><?= $submissionallowed ? $finalvariant : bank::final_variant_for_attempt((int)$latest->attemptno) ?> из 3</span><?php endif; ?></div><?php endif; ?>
          <?php if ($requestedattemptid === 0): ?><p class="ac-intro"><a href="academy_rehearsal.php">Потренировать диалог перед экзаменом →</a></p><?php else: ?><p class="ac-intro"><a href="<?= ac_e(ac_url('final')) ?>">← К текущему экзамену</a></p><?php endif; ?>
          <?php if (optional_param('draftsaved', 0, PARAM_INT) === 1 && $draftanswers !== null): ?><div class="lw-alert" role="status">Черновик сохранён. Можно вернуться позже: ответы восстановятся для этого варианта экзамена.</div><?php endif; ?>
          <?php if ($draftanswers !== null && optional_param('draftsaved', 0, PARAM_INT) !== 1): ?><div class="lw-note"><strong>Черновик восстановлен</strong><p>Вы можете продолжить с сохранённых ответов. Отправка на проверку — отдельное действие в конце экзамена.</p></div><?php endif; ?>
           <?php if ($requestedattemptid === 0): ?><p><a href="academy_work.php" target="_blank" rel="noopener">Памятка эксперта ↗</a></p><?php endif; ?>
          <?php if ($latest && $requestedattemptid === 0): ?><div class="ac-result <?= $finalstatus === 'ready' && !$latestfinalhistorical ? '' : 'is-warn' ?>"><h2><?= ac_e($state[0]) ?><?= $latestfinalhistorical ? ' · прежняя версия' : '' ?></h2><p><?= $finalstatus === 'awaiting_review' ? 'Ответы отправлены. Проверяющий увидит их в своей очереди; вы получите решение и комментарий.' : 'Решение относится к учебной готовности. Рабочий доступ оформляется отдельно.' ?><?= $latestfinalhistorical ? ' Эта попытка относится к прежней версии экзамена; необходимость пересдачи определяет ответственный за обучение.' : '' ?></p><p><a href="<?= ac_e((new moodle_url('/local/learning_workspace/academy.php', ['view' => 'final', 'attempt' => (int)$latest->id]))->out(false)) ?>">Посмотреть ответы последней попытки →</a><?php if ($latestfinalhistorical && $finalstatus !== 'awaiting_review' && $canwrite && $submissionallowed): ?> · <a href="#ac-exam-start">Открыть актуальный вариант для повторной попытки ↓</a><?php endif; ?></p></div><?php endif; ?>
          <?php if ($latest && $finalstatus === 'ready' && !$latestfinalhistorical && $requestedattemptid === 0): ?><div class="lw-note"><strong>Экзамен принят. Что дальше?</strong><p>Перед первой сменой сверьте рабочую памятку и получите отдельное подтверждение допуска, доступа к кабинету, расписания, канала поддержки и условий работы. Пока эти условия не подтверждены, не принимайте клиентов: результат экзамена сам по себе не включает рабочие права.</p><a href="academy_work.php">Проверить подготовку к работе →</a></div><?php endif; ?>
          <?php $review = $final['review'] ?? null; if ($requestedattemptid === 0 && $review && ac_value($review, 'feedback', '') !== ''): ?><div class="lw-note"><strong>Комментарий проверяющего</strong><p><?= nl2br(ac_e(ac_value($review, 'feedback', ''))) ?></p></div><?php endif; ?>
          <?php if ($displayfinalattempt): $savedquestions = ac_decode((string)$displayfinalattempt->questionsjson); $savedanswers = ac_decode((string)$displayfinalattempt->answersjson); $savedfeedback = ac_decode((string)$displayfinalattempt->feedbackjson); $savedreview = $final['reviews_by_attempt'][(int)$displayfinalattempt->id] ?? null; $savedrubric = $savedreview ? ac_decode((string)$savedreview->rubricjson) : []; $savedstate = ac_status($savedreview ? (string)$savedreview->decision : 'awaiting_review'); ?>
          <?php $currentdisplayversion = (string)bank::final(bank::final_variant_for_attempt((int)$displayfinalattempt->attemptno))['version']; $displayhistorical = (string)$displayfinalattempt->bankversion !== $currentdisplayversion; ?>
          <section class="ac-final-attempt" aria-labelledby="ac-final-attempt-title" data-ac-saved-bank="<?= ac_e((string)$displayfinalattempt->bankversion) ?>"><h2 id="ac-final-attempt-title">Ваши ответы · попытка <?= (int)$displayfinalattempt->attemptno ?></h2><p class="ac-muted"><?= ac_e(userdate((int)$displayfinalattempt->timecreated, '%d.%m.%Y %H:%M')) ?> · Вариант <?= bank::final_variant_for_attempt((int)$displayfinalattempt->attemptno) ?> из 3 · результат тестовой части <?= (int)$displayfinalattempt->score ?>%. <span class="ac-pill <?= ac_e($displayhistorical && $savedstate[1] === 'is-success' ? 'is-warn' : $savedstate[1]) ?>"><?= ac_e($savedstate[0]) ?><?= $displayhistorical ? ' · прежняя версия' : '' ?></span></p>
           <?php if ($displayhistorical): ?><div class="lw-note"><strong>Историческая версия экзамена</strong><p>Эта попытка выполнена до обновления заданий. Ответы и решение сохранены; необходимость пересдачи определяет ответственный за обучение.</p></div><?php endif; ?>
           <?= \local_learning_workspace\local\academy_result_view::timeline($displayfinalattempt,$savedreview ?: null) ?>
<?php if ($savedreview): ?><div class="lw-note"><strong>Комментарий к этой попытке</strong><p><?= nl2br(ac_e($savedreview->feedback)) ?></p></div><?php else: ?><p class="ac-muted">Свободные ответы ещё не проверены человеком.</p><?php endif; ?>
           <?php if (($savedrubric['schema'] ?? '') === 'five_part_v1'): ?><div class="ac-statusline" aria-label="Баллы по частям"><?php foreach (['dialogue' => 'Б · Диалог', 'complex' => 'В · Ситуации', 'promotion' => 'Г · Продвижение'] as $partid => $label): $partscore = $savedrubric['parts'][$partid] ?? []; ?><span class="ac-pill <?= ($partscore['status'] ?? '') === 'passed' ? 'is-success' : 'is-warn' ?>"><?= ac_e($label) ?>: <?= (int)($partscore['score'] ?? 0) ?>/10</span><?php endforeach; ?></div><?php endif; ?>
           <?php $comparison = assessments::final_comparison((int)$displayfinalattempt->id); if ($comparison): $comparisonpreviousurl = (new moodle_url('/local/learning_workspace/academy.php', ['view' => 'final', 'attempt' => (int)$comparison['previousid']]))->out(false); require(__DIR__ . '/views/final_comparison.php'); endif; ?>
           <?php foreach ($savedquestions as $index => $question):
              $id = (string)($question['id'] ?? '');
              $type = (string)($question['type'] ?? '');
              $savedfeedbackvisible = \local_learning_workspace\local\academy_attempt_policy::feedback_visible($displayfinalattempt, (bool)$savedreview);
              $item = $savedfeedbackvisible ? (array)($savedfeedback[$id] ?? []) : [];
              $correct = $item['correct'] ?? null;
              $mark = $savedrubric[$id] ?? null;
              $markresult = is_array($mark) ? (string)($mark['result'] ?? '') : (string)$mark;
              $structured = ($savedrubric['schema'] ?? '') === 'five_part_v1';
              $answertext = ac_answer_display($question, (string)($savedanswers[$id] ?? ''));
              $inlinecase = academy_dialogue_snapshot_view::has_complete_answers((array)$question,
                  (string)($savedanswers[$id] ?? ''));
          ?><div class="ac-feedback"><h3>Вопрос <?= $index + 1 ?><?= ($question['status'] ?? '') !== 'approved_structure' ? ' · учебный черновик' : '' ?></h3><p><?= ac_e(\local_learning_workspace\local\academy_branding::text($question['prompt'] ?? '')) ?></p><?= academy_dialogue_snapshot_view::render((array)$question, false, (string)($savedanswers[$id] ?? '')) ?><?php if ($inlinecase): ?><details class="lw-details"><summary>Полный исходный текст вашего ответа</summary><p class="ac-answer-copy"><?= nl2br(ac_e($answertext)) ?></p></details><?php else: ?><p class="ac-muted"><strong>Ваш ответ:</strong></p><p class="ac-answer-copy"><?= nl2br(ac_e($answertext)) ?></p><?php endif; ?><p><span class="ac-pill <?= $structured && $type === 'text' ? 'is-muted' : ($correct === true || $markresult === 'pass' ? 'is-success' : 'is-warn') ?>"><?= $type === 'choice' ? ($correct === true ? 'Верно' : ($correct === false ? 'Ошибка' : ($savedfeedbackvisible ? 'Не оценено' : 'Разбор после проверки'))) : ($structured && $savedreview ? 'Оценено по критериям' : ($markresult === 'pass' ? 'Критерий выполнен' : ($markresult === 'fail' ? 'Нужна доработка' : 'Ожидает проверки'))) ?></span></p>
          <?php if ($structured && $type === 'text' && $savedreview): foreach ((array)($question['criteria'] ?? []) as $criterion): $cid = (string)($criterion['id'] ?? ''); $grade = $savedrubric['criteria'][$cid] ?? []; ?><div class="ac-evidence <?= (int)($grade['score'] ?? 0)===2?'is-success':'' ?>"><p><strong><?= ac_e($criterion['label'] ?? '') ?>:</strong> <?= (int)($grade['score'] ?? 0) ?>/2<?= !empty($criterion['required_score']) ? ' · обязательно 2' : '' ?></p><?php if ((int)($grade['score'] ?? 0) < 2): ?><p>Фрагмент вашего ответа: «<?= ac_e($grade['quote'] ?? '') ?>»</p><p>Что исправить: <?= ac_e($grade['nextstep'] ?? '') ?></p><?php endif; ?></div><?php endforeach; elseif (is_array($mark) && $markresult === 'fail'): ?><div class="ac-evidence"><p><strong>Фрагмент вашего ответа:</strong> «<?= ac_e($mark['quote'] ?? '') ?>»</p><p><strong>Что исправить:</strong> <?= ac_e($mark['nextstep'] ?? '') ?></p></div><?php endif; ?>
          <?php if ($type === 'choice' && ($item['explanation'] ?? '') !== ''): ?><p class="ac-muted"><?= ac_e(\local_learning_workspace\local\academy_branding::text($item['explanation'])) ?></p><?php endif; ?>
          <?php if (ac_final_answer_needs_remediation((array)$question, $item, $savedrubric, (bool)$savedreview) && ($remediation = ac_final_remediation((array)$question))): foreach (array_merge([$remediation], $remediation['related'] ?? []) as $remediationlink): ?><p class="ac-remediation"><a href="<?= ac_e($remediationlink['url']) ?>"<?= !empty($remediationlink['external']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= ac_e($remediationlink['label']) ?> →</a></p><?php endforeach; endif; ?></div><?php endforeach; ?>
           <p><a class="lw-secondary" href="<?= ac_e(ac_url('final')) ?>">К текущему экзамену →</a></p></section>
          <?php endif; ?>
           <?php if ($requestedattemptid > 0 && !$displayfinalattempt): ?><p><a class="lw-secondary" href="<?= ac_e(ac_url('final')) ?>">К текущему экзамену →</a></p><?php endif; ?>
           <?php if (!$submissionallowed && $finalstatus !== 'awaiting_review'): ?><p class="lw-note" role="status">Ответы отправлены. <?= ac_e(\local_learning_workspace\local\academy_attempt_policy::retry_message((int)$USER->id,'FINAL')) ?></p><?php endif; ?>
           <?php if ($finalstatus !== 'awaiting_review' && $requestedattemptid === 0 && $submissionallowed): ?>
           <form id="ac-current-exam" tabindex="-1" aria-label="Ответы итогового экзамена" data-ac-unsaved="<?= $error !== '' && !empty($postedanswers) ? 'true' : 'false' ?>" method="post" action="<?= ac_e(ac_url('final')) ?>" data-ac-final-form><input type="hidden" name="sesskey" value="<?= ac_e(sesskey()) ?>"><input type="hidden" name="variant" value="<?= $finalvariant ?>"><input type="hidden" name="bankhash" value="<?= ac_e($bankhash) ?>"><input type="hidden" name="draftrevision" value="<?= (int)$draftrevision ?>"><input type="hidden" name="expectedattemptno" value="<?= (int)$draftattemptno ?>"><input type="hidden" name="idemkey" value="<?= ac_e(bin2hex(random_bytes(16))) ?>">
           <p data-ac-unsaved-hint role="status" hidden>Есть несохранённые изменения. Сохраните черновик или отправьте экзамен, прежде чем уходить.</p>
           <nav id="ac-exam-start" tabindex="-1" class="ac-exam-map" aria-label="Пять частей экзамена"><strong>Пять частей · можно двигаться между разделами</strong><div><?php foreach (['foundation', 'process', 'dialogue', 'complex', 'promotion'] as $partid): ?><a href="#ac-part-<?= ac_e($partid) ?>"><?= ac_e(ac_part_label($partid)) ?><small data-ac-progress-part="<?= ac_e($partid) ?>"></small></a><?php endforeach; ?></div><button class="ac-find-missing" type="button" data-ac-find-missing hidden>Найти первый незаполненный ответ</button><span class="ac-progress-status" data-ac-progress-status role="status"></span><div class="ac-exam-map-actions"><button class="lw-secondary" type="submit" name="action" value="draft" formnovalidate <?= !$canwrite ? 'disabled' : '' ?>>Сохранить черновик</button><small>Черновик можно сохранить незаполненным; на проверку ответы попадут только после полной отправки.</small></div></nav>
          <?php $finalseed = implode('|', [(int)$USER->id, 'final', $finalvariant, 1 + (int)($final['attempts'] ?? 0), (string)$pack['version']]); $tokenseed = implode('|', [(int)$USER->id, (string)$pack['version']]); $previouspart = ''; foreach (ac_question_list($pack) as $index => $question): $part = (string)($question['part'] ?? ''); if ($part !== $previouspart): $previouspart = $part; ?><h2 class="ac-part" id="ac-part-<?= ac_e($part) ?>"><?= ac_e(ac_part_label($part)) ?> <a class="ac-part-back" href="#ac-exam-start">К разделам ↑</a></h2><?php if ($part === 'promotion'): ?><div class="lw-note"><strong>Справочник для задания</strong><p><a href="" target="_blank" rel="noopener">Открыть таблицу площадок ↗</a>. Выберите одну площадку и укажите, какие её правила, расходы и требования к данным проверите перед размещением. Актуальные условия проверяйте на самой площадке. В ходе экзамена ничего не публикуйте и не оплачивайте.</p></div><?php endif; endif; ac_render_question($question, $index + 1, $canwrite, $postedanswers, $postedturns, $finalseed, $tokenseed); endforeach; ?>
          <div class="ac-sticky-action"><button class="lw-secondary" type="submit" name="action" value="draft" formnovalidate <?= !$canwrite ? 'disabled' : '' ?>>Сохранить черновик</button><button class="lw-primary" type="submit" name="action" value="final" <?= !$canwrite ? 'disabled' : '' ?>>Отправить экзамен →</button><p>Черновик не оценивается. После отправки ответы не редактируются; для исправлений нужна новая попытка.</p></div></form>
          <?php else: ?><p><a class="lw-secondary" href="<?= ac_e(ac_url()) ?>">К карте проверок</a></p><?php endif; ?>
          <?php if (!empty($final['history'])): ?><details class="lw-details"<?= $requestedattemptid > 0 ? ' open' : '' ?>><summary>История итоговых попыток</summary><ol><?php foreach ($final['history'] as $attempt): $pastreview = $final['reviews_by_attempt'][(int)ac_value($attempt, 'id', 0)] ?? null; $paststatus = $pastreview ? (string)ac_value($pastreview, 'decision', '') : (string)ac_value($attempt, 'status', ''); $pastversion = (string)bank::final(bank::final_variant_for_attempt((int)$attempt->attemptno))['version']; ?><li><a href="<?= ac_e((new moodle_url('/local/learning_workspace/academy.php', ['view' => 'final', 'attempt' => (int)$attempt->id]))->out(false)) ?>"<?= $displayfinalattempt && (int)$displayfinalattempt->id === (int)$attempt->id ? ' aria-current="page"' : '' ?>>Попытка <?= (int)ac_value($attempt, 'attemptno', 0) ?></a> · <?= ac_e(userdate((int)$attempt->timecreated, '%d.%m.%Y %H:%M')) ?> · <?= ac_e(ac_status($paststatus)[0]) ?><?= (string)$attempt->bankversion !== $pastversion ? ' · прежняя версия' : '' ?></li><?php endforeach; ?></ol></details><?php endif; ?>
        <?php endif; ?>
        </div></article>
      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
