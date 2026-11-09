<?php
return [
	'fields'   => [
		'email'   => [
			'required'    => true,
			'name'        => 'email',
			'type'        => 'email',
			'validate'    => 'email',
			'placeholder' => '',
			'label'       => 'Email',
			'vMess'       => 'Укажите Email',
		],
        'comment' => [
            'required'    => true,
            'name'        => 'comment',
            'type'        => 'textarea',
            'validate'    => '',
            'placeholder' => 'Опишите коротко свой запрос',
            'label'       => 'Комментарий',
            'vMess'       => 'Укажите комментарий',
        ],
        'tos_soglasie' => [
            'required' => true,
            'noLabel' => true,
            'name'     => 'tos_soglasie',
            'type'     => 'checkbox',
            'validate' => 'agree',
            'vMess'    => 'Предоставьте согласие на обработку персональных данных',
            'label'    => 'Даю согласие на обработку персональных данных',
            'value'    => 'да',
            'link'     => [
                'url'  => '/consent',
                'text' => 'Даю согласие на обработку персональных данных',
            ],
        ],
        'tos_privacy_policy' => [
            'required' => true,
            'noLabel' => true,
            'name'     => 'tos_privacy_policy',
            'type'     => 'checkbox',
            'validate' => 'agree',
            'vMess'    => 'Подтвердите согласие с Политикой обработки персональных данных',
            'label'    => 'Согласен с Политикой обработки персональных данных',
            'value'    => 'да',
            'link'     => [
                'url'  => '/policy',
                'text' => 'Согласен с Политикой обработки персональных данных',
            ],
        ],
	],
	'mailData' => [
		'subject'   => 'Заполнена форма [стать партнёром по Altevics]',
		'emailFrom' => 'forms@example.org',
		'nameFrom'  => 'SC Forms',
		'emailTo'   => 'forms@example.org',
	],
	'message'  => [
		'success' => '<h3>Спасибо!</h3><p>Ваше сообщение успешно отправлено.</p><p>В ближайшее время мы с вами свяжемся.</p>',
		'error'   => '',
	],
	// 'title'    => 'Заполните пожалуйста форму',
];
