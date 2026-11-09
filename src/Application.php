<?php

declare(strict_types=1);

namespace SCForms;

final class Application
{
    private Forms $forms;
    private Security $security;
    private array $origins;
    public function __construct(private array $config, private \Closure $sendMail)
    {
        $this->forms = new Forms(__DIR__ . '/../config/forms');
        $this->security = new Security($config['secret'], $config['storage'], $config['rate_limit'] ?? 5, $config['rate_window'] ?? 3600);
        $url = $config['public_url'] ?? '';
        if (!preg_match('~^https?://[^/?#]+$~D', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('public_url должен содержать точный источник сайта без пути и завершающего слеша');
        }
        $this->origins = array_unique(array_merge([$url], $config['allowed_origins'] ?? []));
    }

    /** @return array{status:int,headers:array,body:string} */
    public function handle(array $server, array $query, array $post): array
    {
        $headers = ['Cache-Control' => 'no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Origin'];
        $json = static function (int $status, string $message, array $errors = []) use (&$headers): array {
            return ['status' => $status, 'headers' => $headers + ['Content-Type' => 'application/json; charset=UTF-8'], 'body' => json_encode(['CODE' => $status, 'mess' => [$message], 'errors' => (object)$errors], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)];
        };
        try {
            $origin = $server['HTTP_ORIGIN'] ?? '';
            $method = $server['REQUEST_METHOD'] ?? 'GET';
            if ($origin !== '' && !$this->security->originAllowed($origin, $this->origins)) {
                return $json(403, 'Источник запроса не разрешён');
            }
            if ($origin !== '') {
                $headers['Access-Control-Allow-Origin'] = $origin;
            }
            if ($method === 'OPTIONS') {
                if ($origin === '') {
                    return $json(403, 'Источник запроса не указан');
                }
                return ['status' => 204, 'headers' => $headers + ['Access-Control-Allow-Methods' => 'GET, POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type'], 'body' => ''];
            }
            if (!in_array($method, ['GET', 'POST'], true)) {
                $headers['Allow'] = 'GET, POST, OPTIONS';
                return $json(405, 'Метод не поддерживается');
            }
            if ((int)($server['CONTENT_LENGTH'] ?? 0) > 32768) {
                return $json(413, 'Слишком большой запрос');
            }
            $input = $method === 'POST' ? $post : $query;
            $name = $input['form'] ?? 'feedback';
            if (!is_string($name)) {
                return $json(400, 'Некорректное имя формы');
            }
            try {
                $form = $this->forms->get($name);
            } catch (\InvalidArgumentException) {
                return $json(404, 'Форма не найдена');
            }
            if ($method === 'GET') {
                if (isset($query['send'])) {
                    return $json(405, 'Отправка разрешена только методом POST');
                }
                return ['status' => 200, 'headers' => $headers + ['Content-Type' => 'text/html; charset=UTF-8'], 'body' => $this->forms->render($name, $this->security->token($name))];
            }
            // Origin is normally sent by browsers. The signed token also permits non-browser clients.
            if ($origin === '' && isset($server['HTTP_REFERER'])) {
                $ref = parse_url($server['HTTP_REFERER']);
                $refOrigin = ($ref['scheme'] ?? '') . '://' . ($ref['host'] ?? '') . (isset($ref['port']) ? ':' . $ref['port'] : '');
                if (!$this->security->originAllowed($refOrigin, $this->origins)) {
                    return $json(403, 'Источник запроса не разрешён');
                }
            }
            if (!is_string($post['token'] ?? null) || !$this->security->verify($post['token'], $name)) {
                return $json(403, 'Срок действия формы истёк. Откройте форму заново.');
            }
            if (!$this->security->allow($server['REMOTE_ADDR'] ?? 'unknown')) {
                return $json(429, 'Слишком много попыток. Попробуйте позже.');
            }

            // if (($post['website'] ?? '') !== '') {
            //     return $json(422, 'Не удалось обработать форму');
            // }
            $data = $post['scform'] ?? [];
            if (!is_array($data)) {
                return $json(400, 'Некорректные данные формы');
            }
            $errors = $this->forms->validate($form, $data);
            if ($errors) {
                return $json(422, 'Проверьте заполнение полей', $errors);
            }
            // Only configured fields ever reach the mail layer.
            $data = array_map('trim', array_intersect_key($data, $form['fields']));
            $body = $this->forms->mailBody($form, $data, $server['REMOTE_ADDR'] ?? '', $origin ?: $this->config['public_url']);
            ($this->sendMail)($form, $data, $body);
            return $json(200, $form['message']['success']);
        } catch (\Throwable $error) {
            // No request values or credentials are written to the log.
            error_log('[SC Forms] ' . get_class($error) . ': request failed; check configuration, storage permissions and SMTP availability.');
            return $json(500, 'Не удалось отправить сообщение. Попробуйте позже.');
        }
    }
}
