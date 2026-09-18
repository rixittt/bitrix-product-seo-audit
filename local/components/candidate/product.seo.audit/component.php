<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\InheritedProperty\ElementValues;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Candidate\SeoAudit\SeoScoreCalculator;

require_once __DIR__ . '/lib/SeoScoreCalculator.php';

if (!Loader::includeModule('iblock')) {
    ShowError('Модуль «Информационные блоки» не установлен.');
    return;
}

$iblockId = max(0, (int)($arParams['IBLOCK_ID'] ?? 0));
$sectionId = max(0, (int)($arParams['SECTION_ID'] ?? 0));
$cacheTime = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));
$codesValue = $arParams['CHARACTERISTIC_CODES'] ?? 'BRAND,MATERIAL,COUNTRY';
$characteristicCodes = is_array($codesValue) ? $codesValue : explode(',', (string)$codesValue);
$characteristicCodes = array_values(array_unique(array_filter(array_map(
    static fn($code): string => strtoupper(trim((string)$code)),
    $characteristicCodes
))));

if ($iblockId <= 0) {
    ShowError('Не выбран инфоблок товаров.');
    return;
}

$request = Application::getInstance()->getContext()->getRequest();
$scoreFilter = (string)$request->getQuery('seo_score');
$sort = (string)$request->getQuery('seo_sort');
$allowedFilters = ['all', 'ready', 'attention', 'critical'];
$allowedSorts = ['score_desc', 'score_asc', 'name_asc'];
$scoreFilter = in_array($scoreFilter, $allowedFilters, true) ? $scoreFilter : 'all';
$sort = in_array($sort, $allowedSorts, true) ? $sort : 'score_desc';

$cacheId = [$iblockId, $sectionId, $characteristicCodes, $scoreFilter, $sort];

if ($this->StartResultCache($cacheTime, $cacheId)) {
    $elementFilter = [
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'ACTIVE_DATE' => 'Y',
    ];
    if ($sectionId > 0) {
        $elementFilter['SECTION_ID'] = $sectionId;
        $elementFilter['INCLUDE_SUBSECTIONS'] = 'Y';
    }

    $products = [];
    $iterator = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        $elementFilter,
        false,
        false,
        ['ID', 'IBLOCK_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
    );
    while ($element = $iterator->GetNext()) {
        $element['NAME'] = $element['~NAME'] ?? $element['NAME'];
        $element['PREVIEW_TEXT'] = $element['~PREVIEW_TEXT'] ?? $element['PREVIEW_TEXT'];
        $element['DETAIL_TEXT'] = $element['~DETAIL_TEXT'] ?? $element['DETAIL_TEXT'];
        $products[(int)$element['ID']] = $element;
    }

    if ($products === []) {
        $this->AbortResultCache();
        ShowError('В выбранном разделе нет активных товаров.');
        return;
    }

    $propertyValues = [];
    if ($characteristicCodes !== []) {
        CIBlockElement::GetPropertyValuesArray(
            $propertyValues,
            $iblockId,
            ['ID' => array_keys($products)],
            ['CODE' => $characteristicCodes],
            ['GET_RAW_DATA' => 'Y']
        );
    }

    $items = [];
    $summary = ['total' => 0, 'ready' => 0, 'attention' => 0, 'critical' => 0, 'score_sum' => 0];

    foreach ($products as $productId => $product) {
        $characteristics = [];
        $characteristicDetails = [];
        foreach ($characteristicCodes as $code) {
            $property = $propertyValues[$productId][$code] ?? null;
            if (!$property) {
                continue;
            }
            $values = is_array($property['VALUE'] ?? null) ? $property['VALUE'] : [$property['VALUE'] ?? null];
            foreach ($values as $value) {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $displayValue = trim((string)$value);
                    $characteristics[$code] = $displayValue;
                    $characteristicDetails[] = [
                        'CODE' => $code,
                        'NAME' => trim((string)($property['NAME'] ?? '')) ?: $code,
                        'VALUE' => $displayValue,
                    ];
                    break;
                }
            }
        }

        $inherited = (new ElementValues($iblockId, $productId))->getValues();
        $audit = SeoScoreCalculator::evaluate([
            'NAME' => $product['NAME'],
            'DESCRIPTION' => $product['DETAIL_TEXT'] ?: $product['PREVIEW_TEXT'],
            'CHARACTERISTICS' => $characteristics,
            'PREVIEW_PICTURE' => $product['PREVIEW_PICTURE'],
            'DETAIL_PICTURE' => $product['DETAIL_PICTURE'],
            'META_TITLE' => $inherited['ELEMENT_META_TITLE'] ?? '',
            'META_DESCRIPTION' => $inherited['ELEMENT_META_DESCRIPTION'] ?? '',
        ]);

        $item = [
            'ID' => $productId,
            'NAME' => $product['NAME'],
            'DETAIL_PAGE_URL' => $product['DETAIL_PAGE_URL'],
            'CHARACTERISTICS' => $characteristics,
            'CHARACTERISTIC_DETAILS' => $characteristicDetails,
            'META_TITLE' => (string)($inherited['ELEMENT_META_TITLE'] ?? ''),
            'META_DESCRIPTION' => (string)($inherited['ELEMENT_META_DESCRIPTION'] ?? ''),
            'AUDIT' => $audit,
        ];

        ++$summary['total'];
        ++$summary[$audit['status']['code']];
        $summary['score_sum'] += $audit['score'];
        $items[] = $item;
    }

    $summary['average'] = $summary['total'] > 0
        ? (int)round($summary['score_sum'] / $summary['total'])
        : 0;

    if ($scoreFilter !== 'all') {
        $items = array_values(array_filter(
            $items,
            static fn(array $item): bool => $item['AUDIT']['status']['code'] === $scoreFilter
        ));
    }

    usort($items, static function (array $left, array $right) use ($sort): int {
        return match ($sort) {
            'score_asc' => $left['AUDIT']['score'] <=> $right['AUDIT']['score'],
            'name_asc' => strnatcasecmp($left['NAME'], $right['NAME']),
            default => $right['AUDIT']['score'] <=> $left['AUDIT']['score'],
        };
    });

    $arResult = [
        'ITEMS' => $items,
        'SUMMARY' => $summary,
        'VISIBLE_COUNT' => count($items),
        'FILTER' => $scoreFilter,
        'SORT' => $sort,
    ];

    $this->IncludeComponentTemplate();
}
