<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

$iblocks = [];
if (Loader::includeModule('iblock')) {
    $iterator = CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($iblock = $iterator->Fetch()) {
        $iblocks[$iblock['ID']] = sprintf('[%d] %s', $iblock['ID'], $iblock['NAME']);
    }
}

$arComponentParameters = [
    'GROUPS' => [
        'AUDIT' => [
            'NAME' => 'Параметры SEO-аудита',
            'SORT' => 200,
        ],
    ],
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'AUDIT',
            'NAME' => 'Инфоблок товаров',
            'TYPE' => 'LIST',
            'VALUES' => $iblocks,
            'REFRESH' => 'Y',
        ],
        'SECTION_ID' => [
            'PARENT' => 'AUDIT',
            'NAME' => 'Раздел каталога (0 — все товары)',
            'TYPE' => 'STRING',
            'DEFAULT' => '0',
        ],
        'CHARACTERISTIC_CODES' => [
            'PARENT' => 'AUDIT',
            'NAME' => 'Коды характеристик через запятую',
            'TYPE' => 'STRING',
            'DEFAULT' => 'BRAND,MATERIAL,COUNTRY',
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];

