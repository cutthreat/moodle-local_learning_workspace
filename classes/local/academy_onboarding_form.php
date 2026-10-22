<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Shared server validation and accessible presentation; never writes candidate data. */
final class academy_onboarding_form {
    public const MAX_LANGUAGES = 8;
    public const MULTIPLE = ['channel'=>'channels','area'=>'areas','device'=>'devices'];
    /** Read old single-answer records without a database migration. */
    public static function selections(array $answers, string $key): array {
        $plural = self::MULTIPLE[$key];
        $values = $answers[$plural] ?? (($answers[$key] ?? '') !== '' ? [$answers[$key]] : []);
        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }
    public static function languages(array $answers): array {
        if (isset($answers['languages']) && is_array($answers['languages']) && $answers['languages']) {
            return array_values($answers['languages']);
        }
        return [['language'=>$answers['language'] ?? '', 'level'=>$answers['level'] ?? '',
            'otherlanguage'=>$answers['otherlanguage'] ?? '']];
    }
    public static function text(string $key, $a = null): string {
        return get_string_manager()->get_string('onboarding_'.$key, 'local_learning_workspace', $a, 'ru');
    }
    public static function labels(): array {
        $labels = [];
        foreach (['experience','channel','area','language','level','device','meet','otherlanguage',
                'timezone','timewindow','background','goal','days'] as $key) {
            $labels[$key] = self::text('label_'.$key);
        }
        return $labels;
    }
    public static function id(string $key): string { return $key === 'timewindow' ? 'on-window' : 'on-'.$key; }
    public static function validate(array $input, bool $complete, array $options, array $zones): array {
        $data = []; $errors = [];
        foreach ($options as $key => $choices) {
            if (isset(self::MULTIPLE[$key]) && array_key_exists(self::MULTIPLE[$key], $input)) {
                $plural = self::MULTIPLE[$key]; $values = $input[$plural];
                if (!is_array($values) || count(array_filter($values, 'is_string')) !== count($values)) {
                    $errors[$key] = self::text('error_multiple'); $values = [];
                }
                $values = array_values(array_unique($values));
                if (array_diff($values, array_keys($choices))) { $errors[$key] = self::text('error_multiple'); }
                $exclusive = ['channel'=>'none','area'=>'beginner'][$key] ?? '';
                if ($exclusive && in_array($exclusive, $values, true) && count($values) > 1) {
                    $errors[$key] = self::text('error_exclusive');
                }
                $data[$plural] = $values; $data[$key] = $values[0] ?? '';
                continue;
            }
            if (isset($input['languages']) && in_array($key, ['language','level'], true)) {
                $data[$key] = ''; continue;
            }
            if ($key === 'days') {
                $values = (array)($input[$key] ?? []);
                if (count(array_filter($values, 'is_string')) !== count($values)) {
                    $errors[$key] = self::text('error_days'); $values = [];
                }
                $values = array_values(array_unique($values));
                if (array_diff($values, array_keys($choices))) { $errors[$key] = self::text('error_days'); }
                $data[$key] = $values;
            } else {
                $raw = $input[$key] ?? '';
                $value = is_string($raw) ? $raw : '';
                if (!is_string($raw) || ($value !== '' && !isset($choices[$value]))) {
                    $errors[$key] = self::text('error_choice');
                }
                $data[$key] = $value;
            }
        }
        $rawzone = $input['timezone'] ?? '';
        $zone = is_string($rawzone) ? $rawzone : '';
        if (!is_string($rawzone) || ($zone !== '' && !isset($zones[$zone]))) { $errors['timezone'] = self::text('error_timezone'); }
        $data['timezone'] = $zone;
        foreach (['otherlanguage'=>60,'timewindow'=>160,'background'=>600,'goal'=>600] as $key => $limit) {
            if ($key === 'otherlanguage' && isset($input['languages'])) { $data[$key] = ''; continue; }
            $raw = $input[$key] ?? '';
            $data[$key] = trim(clean_param(is_string($raw) ? $raw : '', PARAM_TEXT));
            if (!is_string($raw)) { $errors[$key] = self::text('error_text'); }
            elseif (\core_text::strlen($data[$key]) > $limit) { $errors[$key] = self::text('error_length', $limit); }
        }
        // Keep primary values for existing consumers; the collections are authoritative.
        foreach (self::MULTIPLE as $key => $plural) {
            if (!isset($data[$plural])) { $data[$plural] = $data[$key] !== '' ? [$data[$key]] : []; }
        }
        $newlanguages = array_key_exists('languages', $input);
        if ($newlanguages) {
            $rows = $input['languages']; $data['languages'] = []; $seen = [];
            if (!is_array($rows) || count($rows) > self::MAX_LANGUAGES || !array_is_list($rows)) {
                $errors['language'] = self::text('error_languages_limit', self::MAX_LANGUAGES); $rows = [];
            }
            foreach ($rows as $index => $row) {
                $prefix = 'languages-'.$index.'-';
                if (!is_array($row)) { $errors[$prefix.'language'] = self::text('error_choice'); continue; }
                $clean = [];
                foreach (['language','level','otherlanguage'] as $field) {
                    $raw = $row[$field] ?? '';
                    if (!is_string($raw)) { $errors[$prefix.$field] = self::text('error_text'); $raw = ''; }
                    $clean[$field] = trim(clean_param($raw, PARAM_TEXT));
                }
                if ($clean === ['language'=>'','level'=>'','otherlanguage'=>''] && $index > 0) { continue; }
                foreach (['language','level'] as $field) {
                    if (($clean[$field] !== '' && !isset($options[$field][$clean[$field]])) ||
                            ($complete && $clean[$field] === '')) {
                        $errors[$prefix.$field] = self::text('error_choice');
                    }
                }
                if (\core_text::strlen($clean['otherlanguage']) > 60) { $errors[$prefix.'otherlanguage'] = self::text('error_length', 60); }
                if ($complete && $clean['language'] === 'other' && $clean['otherlanguage'] === '') {
                    $errors[$prefix.'otherlanguage'] = self::text('error_otherlanguage');
                }
                if ($clean['language'] !== 'other') { $clean['otherlanguage'] = ''; }
                $identity = $clean['language'] === 'other' ? 'other:'.mb_strtolower($clean['otherlanguage']) : $clean['language'];
                if ($identity !== '' && $identity !== 'other:') {
                    if (isset($seen[$identity])) { $errors[$prefix.'language'] = self::text('error_language_duplicate'); }
                    $seen[$identity] = true;
                }
                $data['languages'][] = $clean;
            }
            $primary = $data['languages'][0] ?? ['language'=>'','level'=>'','otherlanguage'=>''];
            foreach ($primary as $key => $value) { $data[$key] = $value; }
        } else { $data['languages'] = self::languages($data); }
        if ($complete) {
            foreach (['experience','channel','area','language','level','device','meet'] as $key) {
                if ($newlanguages && in_array($key, ['language','level'], true) && $data['languages']) { continue; }
                if ($data[$key] === '' && !isset($errors[$key])) { $errors[$key] = self::text(isset(self::MULTIPLE[$key])?'error_multiple':'error_choice'); }
            }
            if (!$data['days']) { $errors['days'] = self::text('error_days_required'); }
            if ($data['timezone'] === '') { $errors['timezone'] = self::text('error_timezone'); }
            if (empty($data['timewindow'])) { $errors['timewindow'] = self::text('error_timewindow'); }
            if (!$newlanguages && $data['language'] === 'other' && $data['otherlanguage'] === '') {
                $errors['otherlanguage'] = self::text('error_otherlanguage');
            }
        }
        return ['data' => $data, 'errors' => $errors];
    }
    private static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function multiple(string $key, string $label, array $answers, array $choices, array $errors): string {
        $plural = self::MULTIPLE[$key]; $selected = self::selections($answers, $key);
        $exclusive = ['channel'=>'none','area'=>'beginner'][$key] ?? '';
        $html = '<fieldset id="on-'.$key.'" tabindex="-1" data-multiple-choice'.self::attributes($key,$errors).'><legend>'.self::e($label).'</legend>'.
            '<p class="aw-meta">'.self::e(self::text('multiple_hint')).'</p><div class="ab-checks">';
        foreach ($choices as $value => $text) {
            $html .= '<label><input type="checkbox" name="'.$plural.'[]" value="'.self::e($value).'"'.
                ($value === $exclusive ? ' data-exclusive-choice' : '').(in_array($value,$selected,true)?' checked':'').'> '.self::e($text).'</label>';
        }
        return $html.'</div>'.($exclusive?'<p class="ab-field-error" data-choice-conflict role="status" hidden>'.self::e(self::text('error_exclusive')).'</p>':'').self::error($key,$errors).'</fieldset>';
    }
    public static function language_row(array $row, int $index, array $options, array $errors, bool $removable = true): string {
        $prefix = 'languages-'.$index.'-';
        $html = '<fieldset class="ab-language-row" data-language-row><legend><span data-language-number>'.
            self::e(self::text('language_number',$index+1)).'</span></legend><div class="ab-language-grid">';
        foreach (['language'=>'languages','level'=>'languagelevels'] as $key => $name) {
            $field = $prefix.$key;
            $html .= '<div><label for="on-'.$field.'">'.self::e(self::text('label_'.$key)).'</label><select id="on-'.$field.'" name="'.$name.'[]" data-language-'.$key.' required'.self::attributes($field,$errors).'><option value="">'.self::e(self::text('choose')).'</option>';
            foreach ($options[$key] as $value => $label) { $html .= '<option value="'.$value.'"'.(($row[$key]??'')===$value?' selected':'').'>'.self::e($label).'</option>'; }
            $html .= '</select>'.self::error($field,$errors).'</div>';
        }
        $field=$prefix.'otherlanguage';
        $html .= '<div class="ab-language-other" data-other-language><label for="on-'.$field.'">'.self::e(self::text('label_otherlanguage')).'</label><input id="on-'.$field.'" name="languagenames[]" data-language-otherlanguage maxlength="60" value="'.self::e(is_string($row['otherlanguage']??'')?($row['otherlanguage']??''):'').'"'.self::attributes($field,$errors).'>'.self::error($field,$errors).'</div></div>';
        return $html.'<button class="lw-secondary ab-language-remove" type="submit" name="removelanguage" value="'.$index.'" formnovalidate data-remove-language'.($removable?'':' hidden').'>'.self::e(self::text('remove_language')).'</button></fieldset>';
    }
    public static function display_choices(array $answers, string $key, array $choices): string {
        return implode(', ',array_map(static fn($v)=>$choices[$v]??self::text('not_provided'),self::selections($answers,$key))) ?: self::text('not_provided');
    }
    public static function display_languages(array $answers, array $options): string {
        $items=[];
        foreach (self::languages($answers) as $row) {
            $code=$row['language']??'';
            if (!$code) { continue; }
            $name=$code==='other'?($row['otherlanguage']??''):($options['language'][$code]??self::text('not_provided'));
            $items[]=self::e($name).' — '.self::e($options['level'][$row['level']??'']??self::text('not_provided'));
        }
        return $items?'<ul class="ab-language-summary"><li>'.implode('</li><li>',$items).'</li></ul>':self::e(self::text('not_provided'));
    }
    public static function attributes(string $key, array $errors): string {
        return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="'.self::id($key).'-error"' : '';
    }
    public static function error(string $key, array $errors): string {
        return isset($errors[$key]) ? '<p class="ab-field-error" id="'.self::id($key).'-error">'.self::e($errors[$key]).'</p>' : '';
    }
    public static function summary(array $errors): string {
        if (!$errors) { return ''; }
        $labels = self::labels();
        $html = '<div class="lw-alert lw-error aw-error-summary" role="alert" data-aw-error-summary tabindex="-1">'.
            '<h2>'.self::e(self::text('error_summary')).'</h2><p>'.self::e(self::text('error_saved_input')).'</p><ul>';
        foreach ($errors as $key => $message) {
            $label = $labels[$key] ?? self::text('label_language');
            if (preg_match('/^languages-(\d+)-(language|level|otherlanguage)$/D', $key, $match)) {
                $label = self::text('language_number', (int)$match[1] + 1).' — '.$labels[$match[2]];
            }
            $html .= '<li><a href="#'.self::id($key).'">'.self::e($label.': '.$errors[$key]).'</a></li>';
        }
        return $html.'</ul></div>';
    }
}
