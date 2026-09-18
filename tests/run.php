<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/components/candidate/product.seo.audit/lib/SeoScoreCalculator.php';

use Candidate\SeoAudit\SeoScoreCalculator;

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

function fitText(string $prefix, int $targetLength): string
{
    $suffix = ' качественный удобный современный товар для дома и ежедневного использования';
    $text = $prefix;
    while (mb_strlen($text) < $targetLength) {
        $text .= $suffix;
    }
    $text = mb_substr($text, 0, $targetLength);
    if (preg_match('/\s$/u', $text)) {
        $text = mb_substr($text, 0, $targetLength - 1) . '.';
    }
    return $text;
}

$empty = SeoScoreCalculator::evaluate([
    'NAME' => 'Тестовый товар',
    'DESCRIPTION' => '',
    'CHARACTERISTICS' => [],
    'PREVIEW_PICTURE' => null,
    'DETAIL_PICTURE' => null,
    'META_TITLE' => '',
    'META_DESCRIPTION' => '',
]);
expectSame(0, $empty['score'], 'полностью пустой товар получает 0');
expectSame('critical', $empty['status']['code'], 'нулевая оценка имеет критичный статус');

$perfect = SeoScoreCalculator::evaluate([
    'NAME' => 'Беспроводные наушники Aero Pro',
    'DESCRIPTION' => fitText('Беспроводные наушники обеспечивают чистый звук.', 420),
    'CHARACTERISTICS' => ['Aero', 'Алюминий', 'Россия'],
    'PREVIEW_PICTURE' => 10,
    'DETAIL_PICTURE' => 11,
    'META_TITLE' => fitText('Беспроводные наушники Aero Pro — обзор', 45),
    'META_DESCRIPTION' => fitText('Беспроводные наушники Aero Pro с чистым звуком, удобной посадкой и долгой работой без подзарядки.', 145),
]);
expectSame(100, $perfect['score'], 'полностью оптимизированный товар получает 100');
expectSame('ready', $perfect['status']['code'], 'оценка 100 имеет готовый статус');

$boundary = SeoScoreCalculator::evaluate([
    'NAME' => 'Настольная лампа Focus',
    'DESCRIPTION' => fitText('Настольная лампа Focus.', 300),
    'CHARACTERISTICS' => ['Focus', 'Металл', 'Китай'],
    'PREVIEW_PICTURE' => 1,
    'DETAIL_PICTURE' => 2,
    'META_TITLE' => fitText('Настольная лампа Focus', 30),
    'META_DESCRIPTION' => fitText('Настольная лампа Focus', 120),
]);
expectSame(100, $boundary['score'], 'нижние границы рекомендуемых длин засчитываются');

$unicodeKeywords = SeoScoreCalculator::extractKeywords('Умная колонка «Север Мини»');
expectSame(true, in_array('колонка', $unicodeKeywords, true), 'кириллическое ключевое слово извлекается корректно');
expectSame(false, in_array('для', $unicodeKeywords, true), 'стоп-слова не попадают в ключевые слова');

$partial = SeoScoreCalculator::evaluate([
    'NAME' => 'Кофемолка Pulse',
    'DESCRIPTION' => 'Короткое описание кофемолки Pulse.',
    'CHARACTERISTICS' => ['Pulse'],
    'PREVIEW_PICTURE' => 1,
    'DETAIL_PICTURE' => null,
    'META_TITLE' => 'Кофемолка Pulse',
    'META_DESCRIPTION' => '',
]);
expectSame(35, $partial['score'], 'частично заполненный товар получает ожидаемую сумму');
expectSame('critical', $partial['status']['code'], '35 баллов относится к критичному статусу');

fwrite(STDOUT, "All tests passed.\n");
