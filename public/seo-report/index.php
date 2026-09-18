<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Loader;

$APPLICATION->SetTitle('SEO-готовность товаров');
$APPLICATION->SetPageProperty('description', 'Публичный отчёт о заполненности и SEO-готовности товаров демонстрационного каталога.');

$section = null;
if (Loader::includeModule('iblock')) {
    $section = CIBlockSection::GetList(
        [],
        ['=CODE' => 'seo-audit-demo', 'ACTIVE' => 'Y'],
        false,
        ['ID', 'IBLOCK_ID']
    )->Fetch();
}

if ($section) {
    $APPLICATION->IncludeComponent(
        'candidate:product.seo.audit',
        '',
        [
            'IBLOCK_ID' => (int)$section['IBLOCK_ID'],
            'SECTION_ID' => (int)$section['ID'],
            'CHARACTERISTIC_CODES' => 'BRAND,MATERIAL,COUNTRY',
            'CACHE_TYPE' => 'A',
            'CACHE_TIME' => 3600,
        ],
        false
    );
} else {
    ShowError('Демонстрационный раздел каталога ещё не создан.');
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';

