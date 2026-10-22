<?php
/** Optional formative dialogue rehearsal. Nothing is submitted or graded. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access.php');

use local_learning_workspace\local\academy_access;

require_login();
$context = academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_rehearsal.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Тренировка диалога · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-rehearsal.css'));

$turns = [
    [
        'phase' => 'Бесплатный чат',
        'client' => 'Здравствуйте. Думаю запустить небольшой творческий проект с подругой. Можете помочь разобраться?',
        'task' => 'Покажите, что услышали тему, дайте один общий полезный ориентир и задайте вопрос о цели разговора.',
        'checks' => ['Я признал тему без выдуманного личного вывода.', 'До вопроса уже есть полезный ориентир.', 'Я задал один вопрос, который уточняет запрос.'],
        'hint' => 'Например, предложите сначала разделить саму идею и договорённость о совместной работе; уточните, что важнее обсудить сейчас.',
    ],
    [
        'phase' => 'Бесплатный чат',
        'client' => 'Идея мне нравится. Но я боюсь, что всё придётся делать одной.',
        'task' => 'Покажите, что поняли конкретную тревогу. Не повторяйте первый вопрос.',
        'checks' => ['Я назвал тревогу о распределении усилий.', 'Я предложил понятную точку для дальнейшего разговора.', 'Я учитываю уже сказанное и не повторяю первый вопрос.'],
        'hint' => 'Можно обсудить, какие задачи каждая готова взять, прежде чем строить прогноз о результате.',
    ],
    [
        'phase' => 'Бесплатный чат',
        'client' => 'Что я могу сделать уже сейчас, не переходя к личному разбору?',
        'task' => 'Дайте один полезный общий шаг, не превращая бесплатный ответ в пустую рекламу.',
        'checks' => ['Есть конкретный общий шаг.', 'Я не выдаю его за персональный прогноз.'],
        'hint' => 'Например: отдельно записать задачи, сроки и ожидания обеих сторон и затем сверить их вместе.',
    ],
    [
        'phase' => 'Бесплатный чат',
        'client' => 'А вы можете точно сказать, что подруга меня не подведёт?',
        'task' => 'Сохраните границу метода и поддержите клиентку без категоричного обещания.',
        'checks' => ['Я не обещаю чужое поведение или гарантированный исход.', 'Я возвращаю разговор к тому, что клиентка может прояснить сама.'],
        'hint' => 'Чужое решение нельзя гарантировать. Полезнее заранее согласовать роли и способ обсуждать перегрузку.',
    ],
    [
        'phase' => 'Переход к платной сессии',
        'client' => 'Если разобрать именно мою ситуацию, что будет по-другому?',
        'task' => 'Коротко объясните предмет личной консультации и чем она отличается от общего бесплатного ответа.',
        'checks' => ['Я назвал возможный предмет индивидуального разбора.', 'Я не изображаю, что уже проверил личные данные или сделал расклад.'],
        'hint' => 'В личной сессии можно разобрать её вопрос и проверенные исходные данные, а затем обсудить варианты действий; здесь пока был общий ориентир.',
    ],
    [
        'phase' => 'Переход к платной сессии',
        'client' => 'Я пока не уверена, стоит ли платить. Может, вы сначала скажете главное?',
        'task' => 'Дайте свободный выбор: не давите и не скрывайте, что персональная работа проходит в отдельном платном чате.',
        'checks' => ['Я уважил сомнение и не создаю срочность или страх.', 'Я прямо назвал отдельный платный формат; условия предлагаю посмотреть до согласия.'],
        'hint' => 'Можно остаться с общим шагом. Если нужен разбор именно её ситуации, предложите посмотреть условия отдельной платной сессии без обещания результата.',
    ],
    [
        'phase' => 'Граница начала сессии',
        'client' => 'Хорошо, я согласна. Но по условию задания отдельная платная сессия пока не запущена.',
        'task' => 'Отделите согласие от фактического запуска. Не начинайте персональный разбор преждевременно.',
        'checks' => ['Я признаю согласие, но не считаю его подтверждением запуска.', 'Я предлагаю дождаться подтверждённого статуса платного чата.'],
        'hint' => 'Согласие получено, но персональный ответ начнётся после подтверждения запуска отдельной сессии в интерфейсе.',
    ],
    [
        'phase' => 'Платная сессия · статус подтверждён',
        'client' => 'Сессия активна. У меня и подруги по часу в неделю. В учебной позиции «что важно учесть» дана Двойка Пентаклей: в примере исходного материала дня 7 она связана с балансом обязанностей. Что это значит для моего вопроса?',
        'task' => 'Дайте содержательную персональную интерпретацию только по данным этой вымышленной ситуации. Свяжите символ с вопросом, предложите проверяемое действие и один уточняющий вопрос.',
        'checks' => ['Я связал заданный символ именно с риском неравной нагрузки.', 'Я предложил действие с учётом двух часов в неделю, не обещая исход.', 'Я не добавил несуществующие позиции карт, натальные данные или факты о подруге.', 'Я задал один уточняющий вопрос, связанный с её ситуацией.'],
        'hint' => 'Связь с картой — не доказательство будущего. Здесь полезно распределить ограниченное время по двум-трём задачам и проверить, одинаково ли обе стороны понимают вклад.',
    ],
    [
        'phase' => 'Платная сессия · завершение',
        'client' => 'Итак, с чего мне начать на этой неделе? Подведите итог коротко.',
        'task' => 'Соберите итог личной сессии: наблюдение, один посильный шаг, граница неопределённости и завершение.',
        'checks' => ['Итог связан с её вопросом и ограничением времени.', 'Есть один конкретный шаг на неделю.', 'Я не обещаю поведение подруги или успех проекта.', 'Я ясно завершаю консультацию; решение остаётся за клиенткой.'],
        'hint' => 'Например, предложите короткую встречу, где обе распределят задачи на пробную неделю, а затем сверят реальную нагрузку. Решение о проекте остаётся за ними.',
    ],
];
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$PAGE->requires->js_init_code(<<<'JS'
(function () {
  'use strict';
  var root = document.querySelector('.lw-rehearsal');
  if (!root) { return; }
  var turns = Array.prototype.slice.call(root.querySelectorAll('[data-rh-turn]'));
  var progress = root.querySelector('[data-rh-progress]');
  var start = root.querySelector('.rh-start');
  var current = 0;
  var leaving = false;
  window.addEventListener('beforeunload', function (event) {
    if (leaving || !turns.some(function (turn) { return turn.querySelector('textarea').value.trim(); })) { return; }
    event.preventDefault();
    event.returnValue = '';
  });
  window.addEventListener('pageshow', function () { leaving = false; });
  function show(index) {
    if (index < 0 || index >= turns.length) { return; }
    turns.forEach(function (turn, i) { turn.hidden = i !== index; });
    current = index;
    progress.textContent = 'Ход ' + (index + 1) + ' из ' + turns.length;
    if (start) { start.textContent = index === 0 ? 'Начать с первого хода ↓' : 'Вернуться к первому ходу ↑'; }
    turns[index].querySelector('h2').focus({preventScroll: true});
    turns[index].scrollIntoView({block: 'start', behavior: 'auto'});
  }
  if (start) { start.addEventListener('click', function (event) { event.preventDefault(); show(0); }); }
  turns.forEach(function (turn, index) {
    var heading = turn.querySelector('h2');
    heading.setAttribute('tabindex', '-1');
    var next = turn.querySelector('[data-rh-next]');
    var prev = turn.querySelector('[data-rh-prev]');
    var finish = turn.querySelector('[data-rh-finish]');
    var input = turn.querySelector('textarea');
    var error = turn.querySelector('[data-rh-error]');
    var feedback = turn.querySelector('.rh-feedback');
    function syncFeedbackAction() {
      if (next) { next.textContent = feedback.open ? 'Я сверил ответ · Следующий ход →' : 'Сверить и продолжить →'; }
    }
    feedback.addEventListener('toggle', syncFeedbackAction);
    syncFeedbackAction();
    function advance(event) {
      if (!input.value.trim()) {
        if (event) { event.preventDefault(); }
        error.hidden = false;
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', error.id);
        input.focus();
        return;
      }
      error.hidden = true;
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
      if (!feedback.open) {
        if (event) { event.preventDefault(); }
        feedback.open = true;
        feedback.querySelector('summary').focus();
        if (next) { next.textContent = 'Я сверил ответ · Следующий ход →'; }
        return;
      }
      if (next) { show(index + 1); }
      if (finish && event && !event.defaultPrevented && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        leaving = true;
      }
    }
    input.addEventListener('input', function () {
      if (input.value.trim()) {
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        input.removeAttribute('aria-describedby');
      }
    });
    if (next) { next.addEventListener('click', advance); }
    if (finish) { finish.addEventListener('click', advance); }
    if (prev) { prev.addEventListener('click', function () { show(index - 1); }); }
  });
  turns.forEach(function (turn, index) { turn.hidden = index !== current; });
}());
JS
);

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-rehearsal" lang="ru">
  <a class="lw-skip" href="#rehearsal-main">К тренировке</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">БЕЗ ОЦЕНКИ · ПЕРЕД ЭКЗАМЕНОМ</p><h1>Потренируйте весь диалог</h1><p>Девять коротких ответов: полезный бесплатный чат → честное предложение → подтверждённая платная сессия → конкретный итог.</p><a class="rh-start" href="#turn-1">Начать с первого хода ↓</a></div>
    <div class="rh-layout">
      <main id="rehearsal-main" class="lw-main" tabindex="-1">
        <article class="lw-sheet"><div class="lw-workbenchbar"><span>Одна вымышленная ситуация</span><span>9 ходов</span></div><div class="lw-sheetbody">
          <div class="lw-note rh-intro"><strong>Как пользоваться</strong><p>Напишите ответ → сверьте с критериями → переходите к следующему ходу. Ответы не сохраняются и не отправляются экзаменатору. Не вводите реальные данные клиентов.</p><details><summary>Подробнее о тренировке</summary><p>Следующий ход откроется после ответа и самопроверки. Здесь нет балла или сохранения в базе. При случайном уходе браузер может предупредить о потере введённых ответов, но это не резервная копия: после закрытия страницы ответы не восстановятся.</p><p>Ситуация и позиция расклада созданы для упражнения. Связь Двойки Пентаклей с балансом обязанностей взята из примера 3 дополнительного материала дня 7; это не личный расклад клиентки.</p></details></div>
          <p class="rh-progress" aria-live="polite" data-rh-progress>Ход 1 из 9</p>
          <?php foreach ($turns as $index => $turn): $number = $index + 1; ?>
          <section class="rh-turn" id="turn-<?= $number ?>" aria-labelledby="turn-title-<?= $number ?>" data-rh-turn>
            <p class="lw-eyebrow"><?= $esc($turn['phase']) ?> · ХОД <?= $number ?> ИЗ 9</p>
            <h2 id="turn-title-<?= $number ?>"><?= $number < 8 ? 'Ответьте клиентке' : ($number === 8 ? 'Проведите разбор' : 'Завершите сессию') ?></h2>
            <blockquote class="rh-client"><span>Клиентка · учебная ситуация</span><p><?= $esc($turn['client']) ?></p></blockquote>
            <p class="rh-task"><strong>Ваша задача:</strong> <?= $esc($turn['task']) ?></p>
            <label class="rh-label" for="rh-answer-<?= $number ?>">Ваш ответ эксперта</label>
            <textarea id="rh-answer-<?= $number ?>" rows="5" autocomplete="off" spellcheck="true" placeholder="Напишите так, как ответили бы в чате. Только вымышленные данные."></textarea>
            <p class="rh-error" id="rh-error-<?= $number ?>" role="alert" data-rh-error hidden>Сначала напишите свою реплику. После этого можно сверить её с критериями и продолжить.</p>
            <details class="rh-feedback"><summary>Сверить ответ с критериями</summary><div><p>Проверьте сами: это не автоматическая оценка.</p><ul><?php foreach ($turn['checks'] as $check): ?><li><?= $esc($check) ?></li><?php endforeach; ?></ul><p><strong>Ориентир для доработки:</strong> <?= $esc($turn['hint']) ?></p><p>Если что-то упущено, вернитесь к полю выше и перепишите ответ. Можно повторить любой ход.</p></div></details>
            <nav class="rh-actions" aria-label="Переход между ходами">
              <?php if ($number > 1): ?><button class="lw-secondary" type="button" data-rh-prev>← Предыдущий ход</button><?php endif; ?>
              <?php if ($number < count($turns)): ?><button class="lw-primary" type="button" data-rh-next>Сверить и продолжить →</button><?php else: ?><a class="lw-primary" href="academy.php?view=final" data-rh-finish>К итоговому экзамену →</a><?php endif; ?>
            </nav>
          </section>
          <?php endforeach; ?>
          <noscript><p class="lw-note">Без JavaScript все девять ходов показаны один за другим. Можно написать ответы, открыть самопроверку и перейти к экзамену.</p></noscript>
          <p class="rh-footnote"><a href="course.php">Вернуться к семи урокам</a>.</p>
        </div></article>
      <aside class="al-context-note" aria-label="О тренировке"><div class="lw-coach-note"><span class="lw-eyebrow">ПОМНИТЕ</span><h2>Ценность до продажи</h2><p>Сначала помогите клиенту общим ответом. Личный разбор начинайте только после понятного предложения, согласия и подтверждённого запуска платного чата.</p></div></aside>
      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
