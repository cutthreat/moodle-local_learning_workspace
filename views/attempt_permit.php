<?php
defined('MOODLE_INTERNAL') || die();
$permitstate=\local_learning_workspace\local\academy_attempt_policy::state((int)$attempt->userid,$attempt->kind,$attempt->lessonid);
if ($permitstate['next']===(int)$attempt->attemptno+1):
$permitrecord=$DB->get_record('local_lworkspace_acpermit',['userid'=>(int)$attempt->userid,'kind'=>$attempt->kind,'lessonid'=>$attempt->lessonid,'attemptno'=>$permitstate['next']]);
?>
<section class="lw-note"><h2>Повторная попытка</h2>
<?php if(!$permitstate['retakesenabled']):?><p>Пересдачи этой работы выключены управляющим. Обратная связь остаётся доступной, прежние разрешения не открывают отправку.</p>
<?php elseif($permitrecord):?><p>Разрешена одна дополнительная отправка · <?=acr_e(userdate($permitrecord->timecreated,'%d.%m.%Y %H:%M'))?>.</p><p><?=nl2br(acr_e($permitrecord->reason))?></p>
<?php elseif($permitstate['allowed']):?><p>Следующая попытка уже доступна по правилам теста.</p>
<?php else:?><details class="lw-details"><summary>Разрешить один повтор</summary><form method="post" action="<?=acr_e(acr_url($attemptid))?>">
<input type="hidden" name="sesskey" value="<?=acr_e(sesskey())?>"><input type="hidden" name="action" value="permit"><input type="hidden" name="attemptid" value="<?=(int)$attemptid?>"><input type="hidden" name="workspaceform" value="<?=acr_e(\local_learning_workspace\local\academy_workspace::form_token('permit'))?>">
<label for="permit-reason">Причина и что нужно доработать</label><textarea id="permit-reason" name="reason" required minlength="10" maxlength="1000"></textarea><div class="lw-actions"><button class="lw-secondary">Разрешить одну отправку</button></div>
</form></details><?php endif;?></section><?php endif;?>
