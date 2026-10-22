<?php
/** Optional formative orientation before the seven-day course. No grade or work access. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access.php');

use local_learning_workspace\local\academy_access;

require_login();
$context = academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_entry.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('С чего начать · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-entry.css'));

$items = [
    'first' => [
        'topic' => 'Первый контакт',
        'prompt' => 'Клиент пишет в бесплатный чат: «Боюсь менять работу. Что меня ждёт?» Как ответить сначала?',
        'options' => [
            'a' => 'Сразу назвать точный исход и дату перемен.',
            'b' => 'Признать переживание, дать общий ориентир и задать один уточняющий вопрос.',
            'c' => 'Не отвечать по существу, пока клиент не оплатит консультацию.',
        ],
        'answer' => 'b',
        'why' => 'Бесплатный ответ должен быть полезным, но не выдавать общий разговор за персональный прогноз.',
        'lesson' => 'L1',
        'url' => 'lesson_one.php',
    ],
    'clarity' => [
        'topic' => 'Понятный ответ',
        'prompt' => 'У вас недостаточно данных для личного вывода. Что важно сказать клиенту?',
        'options' => [
            'a' => 'Отделить известное от предположения и объяснить предел ответа простыми словами.',
            'b' => 'Написать уверенный вывод, чтобы не выглядеть неопытным.',
            'c' => 'Перечислить шаги процесса без объяснения.',
        ],
        'answer' => 'a',
        'why' => 'Честная граница знаний помогает клиенту понять ответ и не создаёт ложной уверенности.',
        'lesson' => 'L2',
        'url' => 'academy_day.php?lesson=L2',
    ],
    'paid' => [
        'topic' => 'Переход к личной сессии',
        'prompt' => 'После общего ответа клиент просит разобрать именно его ситуацию. Как перейти к следующему этапу?',
        'options' => [
            'a' => 'Пообещать гарантированный результат после оплаты.',
            'b' => 'Начать персональный разбор бесплатно, а оплату попросить в конце.',
            'c' => 'Объяснить, что личный разбор проходит в отдельном платном чате, и предложить его без давления.',
        ],
        'answer' => 'c',
        'why' => 'Переход должен быть прозрачным: эксперт уже помог в бесплатном чате, а персональная работа начинается после согласия и запуска платной сессии.',
        'lesson' => 'L4',
        'url' => 'academy_day.php?lesson=L4',
    ],
    'safety' => [
        'topic' => 'Границы и безопасность',
        'prompt' => 'Клиент встревожен и просит сказать, неизбежна ли тяжёлая болезнь. Как действовать?',
        'options' => [
            'a' => 'Подтвердить риск по карте, чтобы клиент не пропустил предупреждение.',
            'b' => 'Не ставить диагноз и не запугивать; признать тревогу и предложить обратиться к профильному специалисту.',
            'c' => 'Перевести разговор на оплату, чтобы получить больше данных.',
        ],
        'answer' => 'b',
        'why' => 'Эксперт не заменяет медицинскую помощь и не использует страх как способ продажи.',
        'lesson' => 'work',
        'url' => 'academy_work.php#fallback',
    ],
];

$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$answers = [];
$missing = [];
if ($submitted) {
    require_sesskey();
    $answers = optional_param_array('answers', [], PARAM_ALPHANUMEXT);
    foreach ($items as $id => $item) {
        if (!isset($answers[$id]) || !array_key_exists((string)$answers[$id], $item['options'])) {
            $missing[] = $id;
        }
    }
}
$complete = $submitted && !$missing;
$recommended = [];
if ($complete) {
    foreach ($items as $id => $item) {
        if ($answers[$id] !== $item['answer']) {
            $recommended[$item['lesson']] = ['topic' => $item['topic'], 'url' => $item['url']];
        }
    }
}
$esc = static function($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-entry" lang="ru">
  <a class="lw-skip" href="#entry-main">К содержанию</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <main id="entry-main" class="lw-entry-main" tabindex="-1">
    <p class="lw-eyebrow">ПЕРЕД НАЧАЛОМ</p>
    <h1>С чего начать обучение?</h1>
    <p class="lw-entry-lead">Четыре короткие ситуации помогут выбрать первый урок. Самопроверка без оценки; ответы не сохраняются.</p>
    <?php if ($complete): ?>
      <section class="lw-entry-result" aria-labelledby="entry-result-title">
        <p class="lw-eyebrow">ВАШ МАРШРУТ</p>
        <h2 id="entry-result-title"><?= $recommended ? 'Начните с этих тем' : 'Базовые ситуации вам знакомы' ?></h2>
        <?php if ($recommended): ?><p>Разберите темы, в которых ответ пока неточен. Остальные уроки тоже доступны в программе.</p><ul><?php foreach ($recommended as $recommendation): ?><li><a href="<?= $esc($recommendation['url']) ?>"><?= $esc($recommendation['topic']) ?> →</a></li><?php endforeach; ?></ul><?php else: ?><p>Можете открыть любой урок или сразу перейти к итоговому экзамену. Самопроверка не заменяет проверку практических ответов.</p><?php endif; ?>
        <div class="lw-entry-actions"><a class="lw-entry-primary" href="course.php">Открыть программу</a><a href="academy.php?view=final">Сразу к итоговому экзамену →</a></div>
      </section>
    <?php endif; ?>
    <?php if ($submitted && $missing): ?><p class="lw-entry-error" role="alert">Ответьте на все четыре ситуации, чтобы получить рекомендацию. Уже выбранные ответы сохранены на этой странице.</p><?php endif; ?>
    <form method="post" action="academy_entry.php" class="lw-entry-form">
      <input type="hidden" name="sesskey" value="<?= $esc(sesskey()) ?>">
      <?php foreach ($items as $index => $item): $selected = (string)($answers[$index] ?? ''); $iswrong = $complete && $selected !== $item['answer']; ?>
        <fieldset class="lw-entry-card">
          <legend><span><?= $esc((string)(array_search($index, array_keys($items), true) + 1)) ?> / 4</span><?= $esc($item['topic']) ?></legend>
          <p class="lw-entry-prompt"><?= $esc($item['prompt']) ?></p>
          <div class="lw-entry-options">
            <?php foreach ($item['options'] as $optionid => $option): ?><label><input type="radio" name="answers[<?= $esc($index) ?>]" value="<?= $esc($optionid) ?>" <?= $selected === $optionid ? 'checked' : '' ?> required><span><?= $esc($option) ?></span></label><?php endforeach; ?>
          </div>
          <?php if ($complete): ?><p class="lw-entry-feedback <?= $iswrong ? 'is-rework' : 'is-ok' ?>"><strong><?= $iswrong ? 'Стоит повторить тему.' : 'Верный ориентир.' ?></strong> <?php if ($iswrong): ?>Рабочий вариант: <?= $esc($item['options'][$item['answer']]) ?> <?= $esc($item['why']) ?> <a href="<?= $esc($item['url']) ?>">Открыть материал →</a><?php else: ?><?= $esc($item['why']) ?><?php endif; ?></p><?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
      <div class="lw-entry-actions"><button type="submit" class="lw-entry-primary"><?= $complete ? 'Проверить ещё раз' : 'Получить рекомендацию' ?></button><a href="course.php">Пропустить и открыть программу →</a></div>
    </form>
  </main>
</div>
<?php echo $OUTPUT->footer();
