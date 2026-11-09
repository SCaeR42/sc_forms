# SC Forms Implementation Plan

Goal: перенос пяти форм в независимый PHP-пакет с SMTP.
Architecture: серверный Application вызывает FormService, Security и Mailer; статический JS управляет модалкой и встроенными формами.
Tech stack: PHP 8.1+, PHPMailer 6/7, vanilla JavaScript, CSS.
Spec: design.md.

Constraints: без Joomla, без jQuery; тексты и поля из исходного плагина; SMTP-реквизиты вне public; рабочую почту не отправлять.
Review focus: поддельный Origin; массив вместо поля; истечение токена; недоступный SMTP; несколько форм и повторное открытие модалки.

## Task 1: Forms and validation
Files: config/forms/*.php, src/Forms.php, tests/run.php.
- [x] Написать тесты неизвестной формы, обязательных полей, email, массивов, согласий и HTML-экранирования; запустить до реализации.
- [x] Реализовать Forms::get, validate, render, mailBody, перенести пять конфигов.
- [x] Выполнить php tests/run.php: все тесты проходят.

## Task 2: Security and HTTP
Files: src/Security.php, src/Application.php, public/form.php, config/config.example.php, tests/http.php.
- [x] Написать тесты токенов, Origin, лимита отправок, GET/POST и кодов ошибок; проверить RED.
- [x] Реализовать подписанные токены на 1 час, разрешённые источники, атомарный файловый лимит, POST-only отправку, лимит 32 KiB, структурированные ответы.
- [x] Проверить GREEN и синтаксис PHP.

## Task 3: SMTP
Files: composer.json, src/Mailer.php, tests/smtp-server.php, tests/smtp.php.
- [x] Написать интеграционный тест локального SMTP с HTML, Reply-To, адресатом и ошибкой SMTP; проверить RED.
- [x] Подключить PHPMailer и SMTP-конфигурацию без отключения TLS-проверок.
- [x] Проверить отправку на локальный SMTP без реальных адресатов.

## Task 4: Browser integration and delivery
Files: public/assets/sc-forms.js, public/assets/sc-forms.css, public/demo.html, README.md.
- [x] Реализовать открытие/закрытие модалки, фокус, data-sc-form, SCForms.open, встроенные формы и адаптер jQuery.
- [x] Проверить синтаксис JS, серверные тесты, доступность demo и DOM-сценарий; нативный браузер недоступен (verification.md).
- [x] Выполнить независимый обзор кода, исправить значимые замечания с тестами.

## Ledger
Task 1–3: complete, php tests/http.php and php tests/smtp.php pass.
Task 4: complete except native browser verification, node tests/dom.cjs and node tests/http-live.cjs pass.
Final: fixed queued close-event race — DOM regression RED→GREEN; full suite passes.
Ruling: no Git operations or worktree — this is a standalone deliverable and user requested a directory, not repository integration.
Ruling: build in outputs while W: write permission is unavailable — package can be reviewed/copied but requested placement remains incomplete.
Ruling: native browser verification replaced by DOM and live HTTP integration because Chrome launch and browser loopback access failed — visual/native-dialog behavior remains unverified.

Execution: реализовать в текущем сеансе согласно прямому указанию пользователя. Репозитория в папке нет; не создавать worktree и не делать коммиты чужого сайта. Пока право записи W: не предоставлено, сборка находится в outputs/sc_forms; перенос не заявлять выполненным.
