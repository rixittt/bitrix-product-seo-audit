<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'SEO-готовность товаров',
    'DESCRIPTION' => 'Проверяет заполненность контента и базовые SEO-критерии товаров каталога.',
    'ICON' => '/images/icon.gif',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'candidate',
        'NAME' => 'Компоненты кандидата',
        'CHILD' => [
            'ID' => 'seo_audit',
            'NAME' => 'SEO-аудит',
        ],
    ],
];

