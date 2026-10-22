<?php
require_once(__DIR__.'/../../config.php');require_login();
try{$s=\local_learning_workspace\local\academy_blended::session(required_param('id',PARAM_INT));}catch(DomainException|dml_missing_record_exception $e){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo 'Занятие недоступно вашему аккаунту.';exit;}
$escape=static fn($v)=>str_replace(["\\","\r","\n",';',','],['\\\\','','\\n','\\;','\\,'],(string)$v);
$lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Learning Workspace//Demo//RU','CALSCALE:GREGORIAN','BEGIN:VEVENT','UID:learning-workspace-'.$s->id.'@'.parse_url($CFG->wwwroot,PHP_URL_HOST),'DTSTAMP:'.gmdate('Ymd\THis\Z'),'DTSTART:'.gmdate('Ymd\THis\Z',$s->startsat),'DTEND:'.gmdate('Ymd\THis\Z',$s->startsat+$s->duration*60),'SUMMARY:'.$escape($s->title),'DESCRIPTION:'.$escape($s->agenda),'LOCATION:'.$escape($s->meeturl),'STATUS:'.($s->status==='cancelled'?'CANCELLED':'CONFIRMED'),'SEQUENCE:'.(int)$s->revision,'END:VEVENT','END:VCALENDAR'];
header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="learning-session-'.(int)$s->id.'.ics"');header('Cache-Control: private, no-store');
foreach($lines as $line){while(strlen($line)>73){$part=mb_strcut($line,0,73,'UTF-8');echo $part."\r\n ";$line=substr($line,strlen($part));}echo $line."\r\n";}exit;
