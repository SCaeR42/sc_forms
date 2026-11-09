<?php

declare(strict_types=1);

namespace SCForms;

final class Forms
{
    private const NAMES = ['consultation', 'requestdemo', 'becomeclient', 'partner', 'feedback'];
    public function __construct(private string $directory) {}

    public function get(string $name): array
    {
        if (!in_array($name, self::NAMES, true)) {
            throw new \InvalidArgumentException('Форма не найдена');
        }
        $form = require $this->directory . '/' . $name . 'Opt.php';
        $form['formName'] = $name;
        // These two minimum lengths were defined in the original client script.
        foreach (['comment', 'certificate'] as $key) {
            if (isset($form['fields'][$key])) {
                $form['fields'][$key]['minlength'] ??= 2;
            }
        }
        return $form;
    }

    public function validate(array $form, array $data): array
    {
        $errors = [];
        foreach ($form['fields'] as $name => $field) {
            $value = $data[$name] ?? '';
            if (!is_string($value)) {
                $errors[$name] = 'Некорректное значение поля';
                continue;
            }
            $value = trim($value);
            if (($field['required'] ?? false) && $value === '') {
                $errors[$name] = $field['vMess'] ?? 'Заполните обязательное поле';
            } elseif (!preg_match('//u', $value)) {
                $errors[$name] = 'Некорректная кодировка поля';
            } elseif (strlen($value) > (($field['type'] === 'textarea') ? 10000 : 1000)) {
                $errors[$name] = 'Слишком длинное значение поля';
            } elseif ($value !== '' && isset($field['minlength']) && preg_match_all('/./us', $value) < $field['minlength']) {
                $errors[$name] = 'Введите не менее ' . (int)$field['minlength'] . ' символов';
            } elseif ($value !== '' && ($field['validate'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Укажите корректный email';
            } elseif ($field['type'] === 'checkbox' && $value !== '' && $value !== ($field['value'] ?? 'да')) {
                $errors[$name] = $field['vMess'] ?? 'Подтвердите согласие';
            }
        }
        return $errors;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(string $name, string $token): string
    {
        $form = $this->get($name);
        $id = 'scf-' . bin2hex(random_bytes(5));
        $e = self::escape(...);
        $html = '<form class="scf-form" method="post" data-sc-form-name="' . $e($name) . '">';
        $html .= '<h2 class="scf-title">' . $e($form['title'] ?? 'Заполните пожалуйста форму') . '</h2>';
        $html .= '<input type="hidden" name="form" value="' . $e($name) . '"><input type="hidden" name="token" value="' . $e($token) . '">';
        $html .= '<input type="hidden" name="send" value="Y"><div class="scf-trap" aria-hidden="true"><label>Оставьте это поле пустым<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        foreach ($form['fields'] as $key => $field) {
            $fid = $id . '-' . $key;
            $type = $field['type'];
            if (($field['validate'] ?? '') === 'email') {
                $type = 'email';
            }
            $required = !empty($field['required']) ? ' required' : '';
            $label = $e($field['label'] ?? $key) . ($required ? ' <span aria-hidden="true">*</span>' : '');
            $attrs = ' id="' . $e($fid) . '" name="scform[' . $e($key) . ']" aria-describedby="' . $e($fid) . '-error"' . $required;
            $html .= '<div class="scf-field ' . ($type === 'checkbox' ? 'scf-choice' : 'scf-floating') . '">';
            if ($type === 'checkbox') {
                $link = $field['link'] ?? null;
                $caption = $link ? '<a href="' . $e($link['url']) . '" target="_blank" rel="noopener noreferrer">' . $e($link['text']) . '</a>' : $label;
                $html .= '<label class="scf-check"><input type="checkbox"' . $attrs . ' value="' . $e($field['value'] ?? 'да') . '"><span>' . ($link && $required ? '<span aria-hidden="true">*</span> ' : '') . $caption . '</span></label>';
            } else {
                // A nonempty placeholder lets CSS float the visible label after typing.
                $attrs .= ' class="scf-control" placeholder="' . $e(($field['placeholder'] ?? '') ?: ' ') . '" maxlength="' . ($type === 'textarea' ? '10000' : '1000') . '"';
                if (isset($field['minlength'])) {
                    $attrs .= ' minlength="' . (int)$field['minlength'] . '"';
                }
                $html .= $type === 'textarea' ? '<textarea' . $attrs . ' rows="4"></textarea>' : '<input type="' . $e($type) . '"' . $attrs . ($type === 'email' ? ' autocomplete="email"' : '') . '>';
                $html .= '<label for="' . $e($fid) . '">' . $label . '</label>';
            }
            $html .= '<span class="scf-error" id="' . $e($fid) . '-error" data-sc-error="' . $e($key) . '"></span></div>';
        }
        return $html . '<div class="scf-status" role="status" tabindex="-1"></div><button class="scf-submit" type="submit">Отправить</button></form>';
    }

    public function mailBody(array $form, array $data, string $ip, string $origin): string
    {
        $html = '<!doctype html><html lang="ru"><meta charset="UTF-8"><body>';
        foreach ($form['fields'] as $name => $field) {
            $html .= '<p><strong>' . self::escape($field['label'] ?? $name) . ':</strong><br>' . nl2br(self::escape(trim($data[$name] ?? ''))) . '</p>';
        }
        return $html . '<p>IP: ' . self::escape($ip) . '</p><p>Источник: ' . self::escape($origin) . '</p></body></html>';
    }
}
