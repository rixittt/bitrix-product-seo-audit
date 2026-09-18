<?php

declare(strict_types=1);

use Bitrix\Catalog\CatalogIblockTable;
use Bitrix\Iblock\InheritedProperty\ElementValues;
use Bitrix\Main\Loader;

$documentRoot = getenv('BITRIX_DOCUMENT_ROOT') ?: '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'bitrix.qryptoturtle.top';

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

foreach (['iblock', 'catalog', 'currency'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException(sprintf('Не удалось подключить модуль %s.', $module));
    }
}

function output(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function findProductIblock(): int
{
    $catalog = CatalogIblockTable::getList([
        'select' => ['IBLOCK_ID'],
        'filter' => ['=PRODUCT_IBLOCK_ID' => 0],
        'order' => ['IBLOCK_ID' => 'ASC'],
        'limit' => 1,
    ])->fetch();
    if ($catalog) {
        return (int)$catalog['IBLOCK_ID'];
    }

    $iblock = CIBlock::GetList(
        ['ID' => 'ASC'],
        ['ACTIVE' => 'Y', 'TYPE' => 'catalog']
    )->Fetch();
    if ($iblock) {
        return (int)$iblock['ID'];
    }

    throw new RuntimeException('Не найден товарный инфоблок демо-магазина.');
}

function ensureProperty(int $iblockId, string $code, string $name, int $sort): int
{
    $existing = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, '=CODE' => $code])->Fetch();
    if ($existing) {
        return (int)$existing['ID'];
    }

    $property = new CIBlockProperty();
    $propertyId = $property->Add([
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'SORT' => $sort,
        'NAME' => $name,
        'CODE' => $code,
        'PROPERTY_TYPE' => 'S',
        'MULTIPLE' => 'N',
        'FILTRABLE' => 'Y',
    ]);
    if (!$propertyId) {
        throw new RuntimeException(sprintf('Не удалось создать свойство %s: %s', $code, $property->LAST_ERROR));
    }

    return (int)$propertyId;
}

function ensureSection(int $iblockId): int
{
    $existing = CIBlockSection::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, '=CODE' => 'seo-audit-demo'],
        false,
        ['ID']
    )->Fetch();
    if ($existing) {
        return (int)$existing['ID'];
    }

    $section = new CIBlockSection();
    $sectionId = $section->Add([
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'SORT' => 50,
        'NAME' => 'Товары для SEO-аудита',
        'CODE' => 'seo-audit-demo',
        'DESCRIPTION' => 'Демонстрационный каталог с разной степенью заполненности карточек.',
    ]);
    if (!$sectionId) {
        throw new RuntimeException('Не удалось создать раздел: ' . $section->LAST_ERROR);
    }

    return (int)$sectionId;
}

function fitPlainText(string $prefix, int $targetLength): string
{
    $tail = ' Подробные характеристики помогают сравнить модель, выбрать подходящий вариант и принять решение о покупке.';
    $text = $prefix;
    while (mb_strlen($text) < $targetLength) {
        $text .= $tail;
    }
    return trim(mb_substr($text, 0, $targetLength));
}

function productDescription(string $name, int $targetLength): string
{
    if ($targetLength === 0) {
        return '';
    }
    $plain = fitPlainText(
        sprintf('%s разработан для ежедневного использования. ', $name),
        $targetLength
    );
    return '<p>' . htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}

function metaTitle(string $name, bool $optimal): string
{
    if (!$optimal) {
        return $name;
    }
    return fitPlainText($name . ' — обзор и характеристики.', 48);
}

function metaDescription(string $name, bool $optimal): string
{
    if (!$optimal) {
        return $name . ' в демонстрационном каталоге.';
    }
    return fitPlainText($name . ': характеристики, преимущества и сценарии использования.', 145);
}

function createProductImage(int $index): string
{
    $directory = sys_get_temp_dir() . '/seo-audit-seed';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать временный каталог изображений.');
    }
    $path = sprintf('%s/product-%02d.png', $directory, $index);

    $image = imagecreatetruecolor(1200, 800);
    $palette = [
        [36, 64, 142], [18, 128, 92], [171, 82, 23], [102, 67, 170], [18, 113, 141],
    ];
    [$red, $green, $blue] = $palette[($index - 1) % count($palette)];
    $background = imagecolorallocate($image, $red, $green, $blue);
    $card = imagecolorallocate($image, 246, 248, 252);
    $accent = imagecolorallocate($image, min(255, $red + 55), min(255, $green + 55), min(255, $blue + 55));
    $ink = imagecolorallocate($image, 26, 34, 56);
    imagefill($image, 0, 0, $background);
    imagefilledrectangle($image, 185, 110, 1015, 690, $card);
    imagefilledellipse($image, 600, 380, 330, 330, $accent);
    imagefilledrectangle($image, 480, 260, 720, 500, $background);
    imagestring($image, 5, 525, 365, sprintf('PRODUCT %02d', $index), $card);
    imagestring($image, 3, 485, 620, 'SEO AUDIT DEMO CATALOG', $ink);
    imagepng($image, $path, 6);
    imagedestroy($image);

    return $path;
}

function fileArray(string $path): array
{
    $file = CFile::MakeFileArray($path);
    $file['MODULE_ID'] = 'iblock';
    return $file;
}

function saveCatalogData(int $elementId, float $price): void
{
    $productFields = ['QUANTITY' => 25, 'AVAILABLE' => 'Y'];
    if (CCatalogProduct::GetByID($elementId)) {
        CCatalogProduct::Update($elementId, $productFields);
    } else {
        $productFields['ID'] = $elementId;
        CCatalogProduct::Add($productFields);
    }

    $baseGroup = CCatalogGroup::GetBaseGroup();
    if (!$baseGroup) {
        throw new RuntimeException('Не найдена базовая ценовая группа.');
    }
    $groupId = (int)$baseGroup['ID'];
    $priceFields = [
        'PRODUCT_ID' => $elementId,
        'CATALOG_GROUP_ID' => $groupId,
        'PRICE' => $price,
        'CURRENCY' => 'RUB',
    ];
    $existing = CPrice::GetList([], ['PRODUCT_ID' => $elementId, 'CATALOG_GROUP_ID' => $groupId])->Fetch();
    if ($existing) {
        CPrice::Update((int)$existing['ID'], $priceFields);
    } else {
        CPrice::Add($priceFields);
    }
}

$products = [
    ['Беспроводные наушники Aero Pro', 'Aero', 'Алюминий', 'Россия'],
    ['Умная колонка Север Мини', 'Север', 'Пластик', 'Россия'],
    ['Настольная лампа Focus Light', 'Focus', 'Металл', 'Китай'],
    ['Портативная акустика Wave Go', 'Wave', 'Пластик', 'Китай'],
    ['Электрический чайник Nord Steel', 'Nord', 'Сталь', 'Турция'],
    ['Робот-пылесос Orbit Clean', 'Orbit', 'Пластик', 'Китай'],
    ['Кофемолка Pulse Compact', 'Pulse', 'Сталь', 'Италия'],
    ['Увлажнитель воздуха Mist Home', 'Mist', 'Пластик', 'Китай'],
    ['Клавиатура Tactile One', 'Tactile', 'Алюминий', 'Китай'],
    ['Вертикальная мышь Ergo Point', 'Ergo', 'Пластик', 'Китай'],
    ['Монитор Vision Air 24', 'Vision', 'Пластик', 'Китай'],
    ['Внешний аккумулятор Volt 20', 'Volt', 'Алюминий', 'Китай'],
    ['Электронная книга Page Soft', 'Page', 'Пластик', 'Китай'],
    ['Фитнес-браслет Motion Fit', 'Motion', 'Силикон', 'Китай'],
    ['Сетевой фильтр Safe Line', 'Safe', 'Пластик', 'Россия'],
    ['USB-концентратор Link Seven', 'Link', 'Алюминий', 'Китай'],
    ['Автомобильный держатель Road Grip', 'Road', 'Пластик', 'Китай'],
    ['Мини-вентилятор Breeze Pocket', 'Breeze', 'Пластик', 'Китай'],
    ['Ночник Luna Touch', 'Luna', 'Пластик', 'Китай'],
    ['Кабель зарядки Flex Type-C', 'Flex', 'Нейлон', 'Китай'],
];

$iblockId = findProductIblock();
ensureProperty($iblockId, 'BRAND', 'Бренд', 100);
ensureProperty($iblockId, 'MATERIAL', 'Материал', 110);
ensureProperty($iblockId, 'COUNTRY', 'Страна производства', 120);
$sectionId = ensureSection($iblockId);
$elementApi = new CIBlockElement();

foreach ($products as $offset => [$name, $brand, $material, $country]) {
    $index = $offset + 1;
    $xmlId = sprintf('SEO_AUDIT_%03d', $index);
    $existing = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, '=XML_ID' => $xmlId],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    if ($index <= 6) {
        $descriptionLength = 520;
        $characteristicCount = 3;
        $preview = true;
        $detail = true;
        $title = metaTitle($name, true);
        $metaDescription = metaDescription($name, true);
    } elseif ($index <= 10) {
        $descriptionLength = 180;
        $characteristicCount = 2;
        $preview = true;
        $detail = false;
        $title = metaTitle($name, true);
        $metaDescription = metaDescription($name, true);
    } elseif ($index <= 14) {
        $descriptionLength = 360;
        $characteristicCount = 1;
        $preview = $index % 2 === 0;
        $detail = true;
        $title = metaTitle($name, true);
        $metaDescription = metaDescription($name, false);
    } else {
        $descriptionLength = $index % 2 === 0 ? 90 : 0;
        $characteristicCount = $index % 3;
        $preview = $index === 16;
        $detail = false;
        $title = $index <= 17 ? metaTitle($name, false) : '';
        $metaDescription = $index === 15 ? metaDescription($name, false) : '';
    }

    $fields = [
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $sectionId,
        'ACTIVE' => 'Y',
        'SORT' => 100 + $index,
        'NAME' => $name,
        'CODE' => CUtil::translit($name, 'ru', ['replace_space' => '-', 'replace_other' => '-', 'change_case' => 'L']),
        'XML_ID' => $xmlId,
        'PREVIEW_TEXT' => $descriptionLength > 0 ? mb_substr(strip_tags(productDescription($name, $descriptionLength)), 0, 180) : '',
        'PREVIEW_TEXT_TYPE' => 'text',
        'DETAIL_TEXT' => productDescription($name, $descriptionLength),
        'DETAIL_TEXT_TYPE' => 'html',
        'IPROPERTY_TEMPLATES' => [
            'ELEMENT_META_TITLE' => $title,
            'ELEMENT_META_DESCRIPTION' => $metaDescription,
        ],
    ];

    if ($preview || $detail) {
        $imagePath = createProductImage($index);
        $fields['PREVIEW_PICTURE'] = $preview ? fileArray($imagePath) : ['del' => 'Y'];
        $fields['DETAIL_PICTURE'] = $detail ? fileArray($imagePath) : ['del' => 'Y'];
    } else {
        $fields['PREVIEW_PICTURE'] = ['del' => 'Y'];
        $fields['DETAIL_PICTURE'] = ['del' => 'Y'];
    }

    if ($existing) {
        $elementId = (int)$existing['ID'];
        if (!$elementApi->Update($elementId, $fields)) {
            throw new RuntimeException(sprintf('Ошибка обновления %s: %s', $name, $elementApi->LAST_ERROR));
        }
    } else {
        $elementId = (int)$elementApi->Add($fields);
        if ($elementId <= 0) {
            throw new RuntimeException(sprintf('Ошибка создания %s: %s', $name, $elementApi->LAST_ERROR));
        }
    }

    CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
        'BRAND' => $characteristicCount >= 1 ? $brand : '',
        'MATERIAL' => $characteristicCount >= 2 ? $material : '',
        'COUNTRY' => $characteristicCount >= 3 ? $country : '',
    ]);
    saveCatalogData($elementId, 990 + ($index * 430));
    (new ElementValues($iblockId, $elementId))->clearValues();
    output(sprintf('[%02d/20] %s', $index, $name));
}

BXClearCache(true, '/');
output(sprintf('Готово. Инфоблок: %d, раздел: %d, товаров: %d.', $iblockId, $sectionId, count($products)));

