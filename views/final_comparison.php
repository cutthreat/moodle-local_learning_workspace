<?php
// Expects $comparison from academy_assessment_repository::final_comparison()
// and $comparisonpreviousurl from the current role's own route.
defined('MOODLE_INTERNAL') || die();
if (!isset($comparison) || !is_array($comparison)) {
    return;
}
$comparisonescape = static function($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$partlabels = [
    'dialogue' => 'Б · Диалог',
    'complex' => 'В · Сложные ситуации',
    'promotion' => 'Г · Продвижение',
];
$itemlabels = [
    'F-DIALOG-01' => 'Девять ходов с клиентом',
    'F-CASE-01' => 'Согласие и запуск сессии',
    'F-CASE-02' => 'Пауза и возобновление',
    'F-CASE-03' => 'Медицинский запрос',
    'F-CASE-04' => 'Кризисная ситуация',
    'F-CASE-05' => 'Спор по оплате',
    'F-PROMO-PROJECT' => 'Карточка услуги и продвижение',
];
?>
<details class="lw-details ac-compare"><summary>Сравнить с предыдущей попыткой</summary>
  <p class="ac-help">Прошлое решение сохранено: <?= $comparisonescape([
      'ready' => 'готов по экзамену', 'rework' => 'нужна доработка', 'not_ready' => 'пока не готов',
  ][$comparison['previousdecision'] ?? ''] ?? 'проверено') ?>. <a href="<?= $comparisonescape($comparisonpreviousurl) ?>">Открыть прежнюю попытку →</a></p>
  <?php if (empty($comparison['compatible'])): ?>
    <p class="ac-warning">Версии или критерии двух попыток различаются. Автоматическое сравнение и перенос оценки недопустимы; откройте прежнюю работу отдельно.</p>
  <?php else: ?>
    <p class="ac-help">Сюжет новой попытки может отличаться. Здесь сопоставлены только одинаковые навыки и критерии; это сравнение не переносит прежние баллы в новую работу.</p>
    <?php foreach ($comparison['items'] as $item): ?>
      <details class="lw-details ac-compare-item"><summary><?= $comparisonescape($partlabels[$item['part']] ?? $item['part']) ?> · <?= $comparisonescape($itemlabels[$item['id']] ?? 'Практический ответ') ?> · <?= !empty($item['changed']) ? 'ответ изменён' : 'ответ без изменений' ?></summary>
        <div class="ac-compare-grid"><div><strong>Было</strong><p><?= nl2br($comparisonescape($item['previousanswer'])) ?></p></div><div><strong>Стало</strong><p><?= nl2br($comparisonescape($item['currentanswer'])) ?></p></div></div>
        <ul class="ac-compare-criteria">
          <?php foreach ($item['criteria'] as $criterion): ?><li><strong><?= $comparisonescape($criterion['label']) ?>:</strong> <?= (int)$criterion['previous'] ?>/2 → <?= $criterion['current'] === null ? 'ожидает проверки' : (int)$criterion['current'] . '/2' ?><?php if ($criterion['previousnextstep'] !== ''): ?><small>Ранее рекомендовано: <?= $comparisonescape($criterion['previousnextstep']) ?></small><?php endif; ?></li><?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
</details>
