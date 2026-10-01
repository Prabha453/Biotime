<?php

/**
 * field_helper — one call per form control, rendering label + control +
 * inline `.invalid-feedback` + required marker inside a Bootstrap grid column,
 * consistently across every page. Pairs with the tt.js validation layer
 * (`err_class` / `err_text` come from ui_helper) and Tom Select / flatpickr
 * (auto-enhanced by class).
 *
 *   <?= field_text('emp_code', 'Employee ID', ['required' => true, 'col' => 'col-md-4', 'class' => 'mono']) ?>
 *   <?= field_select('department_id', 'Department', opts_from($departments, 'id', 'dept_name'), ['required' => true, 'value' => $emp['department_id'] ?? '', 'placeholder' => '— select —']) ?>
 *   <?= field_switch('is_active', 'Active', ['checked' => $emp['is_active'] ?? false]) ?>
 *   <?= field_image('photo', 'Photo', ['url' => $photoUrl, 'removable' => $isEdit, 'col' => 'col-md-3']) ?>
 */

if (! function_exists('opts_from')) {
    /** Build a [value => label] map from a result set. */
    function opts_from(array $rows, string $valueKey, string $labelKey): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r[$valueKey]] = $r[$labelKey] ?? '';
        }

        return $out;
    }
}

if (! function_exists('_field_truthy')) {
    function _field_truthy($v): bool
    {
        return in_array($v, [true, 't', '1', 1, 'on', 'yes'], true);
    }
}

if (! function_exists('_field_checkbox_state')) {
    /** Resolve a checkbox/switch checked-state honouring failed-submit `old()`. */
    function _field_checkbox_state(string $name, $default): bool
    {
        $old = session('_ci_old_input');
        if (is_array($old)) {
            return array_key_exists($name, $old['post'] ?? []);
        }

        return _field_truthy($default);
    }
}

if (! function_exists('field')) {
    /**
     * @param array{
     *   type?:string, col?:string, required?:bool, value?:mixed, class?:string,
     *   id?:string, placeholder?:string, hint?:string, readonly?:bool, disabled?:bool,
     *   multiple?:bool, options?:array, checked?:mixed, url?:string, removable?:bool,
     *   accept?:string, rows?:int, attrs?:array, maxlength?:int, min?:mixed, max?:mixed, step?:mixed
     * } $o
     */
    function field(string $name, string $label, array $o = []): string
    {
        $type  = $o['type'] ?? 'text';
        $id    = $o['id'] ?? ('f_' . preg_replace('/[^A-Za-z0-9_]/', '_', $name));
        $req   = ! empty($o['required']);
        $col   = $o['col'] ?? 'col-md-4';
        $value = old($name, $o['value'] ?? '');

        // shared attribute string
        $attr = '';
        foreach (['placeholder', 'maxlength', 'minlength', 'min', 'max', 'step', 'pattern', 'accept', 'rows', 'autocomplete', 'inputmode'] as $a) {
            if (isset($o[$a]) && $o[$a] !== '') {
                $attr .= ' ' . $a . '="' . esc((string) $o[$a], 'attr') . '"';
            }
        }
        if ($req) {
            $attr .= ' required';
        }
        if (! empty($o['readonly'])) {
            $attr .= ' readonly';
        }
        if (! empty($o['disabled'])) {
            $attr .= ' disabled';
        }
        foreach ($o['attrs'] ?? [] as $k => $v) {
            $attr .= ' ' . $k . ($v === true ? '' : '="' . esc((string) $v, 'attr') . '"');
        }

        $ctlClass = 'form-' . (in_array($type, ['select'], true) ? 'select' : 'control');
        $cls      = trim($ctlClass . ' ' . ($o['class'] ?? '') . err_class($name));

        if ($type === 'hidden') {
            return '<input type="hidden" name="' . esc($name, 'attr') . '" value="' . esc((string) $value, 'attr') . '">';
        }

        $wrapOpen  = $col === '' ? '<div>' : '<div class="' . esc($col, 'attr') . '">';

        if ($type === 'switch') {
            $checked = _field_checkbox_state($name, $o['checked'] ?? $o['value'] ?? false);

            return $wrapOpen
                . '<div class="switch-row"><div class="form-check form-switch">'
                . '<input class="form-check-input" type="checkbox" role="switch" name="' . esc($name, 'attr') . '" value="1" id="' . $id . '"'
                . ($checked ? ' checked' : '') . (! empty($o['disabled']) ? ' disabled' : '') . '>'
                . '</div><label for="' . $id . '">' . esc($label) . '</label></div>'
                . _field_hint($o) . err_text($name)
                . '</div>';
        }

        $control = match ($type) {
            'textarea' => '<textarea name="' . esc($name, 'attr') . '" id="' . $id . '" class="' . esc($cls, 'attr') . '"'
                . $attr . '>' . esc((string) $value) . '</textarea>',
            'select' => _field_select($name, $id, $cls, $attr, $value, $o),
            'file'   => '<input type="file" name="' . esc($name, 'attr') . '" id="' . $id . '" class="' . esc($cls, 'attr') . '"' . $attr . '>',
            'image'  => _field_image($name, $id, $o),
            default  => '<input type="' . esc($type, 'attr') . '" name="' . esc($name, 'attr') . '" id="' . $id . '" class="'
                . esc($cls, 'attr') . '" value="' . esc((string) $value, 'attr') . '"' . $attr . '>',
        };

        $lbl = '<label class="form-label" for="' . $id . '">' . esc($label) . ($req ? req() : '') . '</label>';

        return $wrapOpen . $lbl . $control . err_text($name) . _field_hint($o) . '</div>';
    }
}

if (! function_exists('_field_hint')) {
    function _field_hint(array $o): string
    {
        return isset($o['hint']) && $o['hint'] !== ''
            ? '<div class="form-hint">' . esc($o['hint']) . '</div>'
            : '';
    }
}

if (! function_exists('_field_select')) {
    function _field_select(string $name, string $id, string $cls, string $attr, $value, array $o): string
    {
        $multi   = ! empty($o['multiple']);
        $options = $o['options'] ?? [];
        $sel     = array_map('strval', (array) $value);

        $h = '<select name="' . esc($name, 'attr') . ($multi ? '[]' : '') . '" id="' . $id . '" class="'
            . esc($cls, 'attr') . '"' . ($multi ? ' multiple' : '') . $attr . '>';
        if (! $multi && isset($o['placeholder'])) {
            $h .= '<option value="">' . esc($o['placeholder']) . '</option>';
        }
        foreach ($options as $ov => $ol) {
            if (is_array($ol)) {                              // optgroup
                $h .= '<optgroup label="' . esc((string) $ov, 'attr') . '">';
                foreach ($ol as $gv => $gl) {
                    $h .= '<option value="' . esc((string) $gv, 'attr') . '"' . (in_array((string) $gv, $sel, true) ? ' selected' : '') . '>' . esc((string) $gl) . '</option>';
                }
                $h .= '</optgroup>';
                continue;
            }
            $h .= '<option value="' . esc((string) $ov, 'attr') . '"' . (in_array((string) $ov, $sel, true) ? ' selected' : '') . '>' . esc((string) $ol) . '</option>';
        }

        return $h . '</select>';
    }
}

if (! function_exists('_field_image')) {
    function _field_image(string $name, string $id, array $o): string
    {
        $url    = $o['url'] ?? '';
        $accept = $o['accept'] ?? 'image/jpeg,image/png';
        $pick   = $o['pickLabel'] ?? 'Choose';

        $h = '<div class="tt-upload" data-tt-upload>'
            . '<img class="tt-upload-img"' . ($url ? ' src="' . esc($url, 'attr') . '"' : ' hidden') . ' alt="">'
            . '<div class="tt-upload-empty"' . ($url ? ' hidden' : '') . '><i class="bi bi-image"></i></div>'
            . '<label class="tt-upload-pick"><i class="bi bi-camera me-1"></i>' . esc($pick)
            . '<input type="file" name="' . esc($name, 'attr') . '" id="' . $id . '" accept="' . esc($accept, 'attr') . '" hidden></label>'
            . '</div>';

        if (! empty($o['removable'])) {
            $h .= '<div class="form-check mt-2 js-rm-row"' . ($url ? '' : ' hidden') . '>'
                . '<input class="form-check-input" type="checkbox" name="' . esc($name, 'attr') . '_remove" value="1" id="' . $id . '_rm">'
                . '<label class="form-check-label small" for="' . $id . '_rm">Remove current file</label></div>';
        }

        return $h;
    }
}

/* -------- terse shortcuts ------------------------------------------------ */

if (! function_exists('field_text')) {
    function field_text(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'text'] + $o); }
}
if (! function_exists('field_email')) {
    function field_email(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'email'] + $o); }
}
if (! function_exists('field_password')) {
    function field_password(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'password', 'autocomplete' => 'new-password'] + $o); }
}
if (! function_exists('field_number')) {
    function field_number(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'number', 'inputmode' => 'decimal'] + $o); }
}
if (! function_exists('field_date')) {
    function field_date(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'date'] + $o); }
}
if (! function_exists('field_time')) {
    function field_time(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'time'] + $o); }
}
if (! function_exists('field_textarea')) {
    function field_textarea(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'textarea', 'rows' => 2] + $o); }
}
if (! function_exists('field_select')) {
    function field_select(string $n, string $l, array $options, array $o = []): string { return field($n, $l, ['type' => 'select', 'options' => $options] + $o); }
}
if (! function_exists('field_switch')) {
    function field_switch(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'switch'] + $o); }
}
if (! function_exists('field_file')) {
    function field_file(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'file'] + $o); }
}
if (! function_exists('field_image')) {
    function field_image(string $n, string $l, array $o = []): string { return field($n, $l, ['type' => 'image', 'col' => $o['col'] ?? 'col-md-3'] + $o); }
}
if (! function_exists('field_hidden')) {
    function field_hidden(string $n, $v): string { return field($n, '', ['type' => 'hidden', 'value' => $v]); }
}
