<?php

declare(strict_types=1);

namespace Candidate\SeoAudit;

final class SeoScoreCalculator
{
    private const STOP_WORDS = [
        'для', 'или', 'при', 'это', 'как', 'the', 'and', 'with', 'from',
        'товар', 'купить', 'новый', 'серия', 'модель',
    ];

    public static function evaluate(array $product): array
    {
        $name = self::plainText((string)($product['NAME'] ?? ''));
        $description = self::plainText((string)($product['DESCRIPTION'] ?? ''));
        $metaTitle = self::plainText((string)($product['META_TITLE'] ?? ''));
        $metaDescription = self::plainText((string)($product['META_DESCRIPTION'] ?? ''));
        $keywords = self::extractKeywords($name);
        $characteristics = self::nonEmptyValues((array)($product['CHARACTERISTICS'] ?? []));

        $descriptionLength = mb_strlen($description);
        $descriptionFilled = $descriptionLength > 0;
        $descriptionLengthOk = $descriptionLength >= 300 && $descriptionLength <= 2000;
        $descriptionKeyword = self::containsKeyword($description, $keywords);
        $descriptionScore = ($descriptionFilled ? 5 : 0)
            + ($descriptionLengthOk ? 10 : 0)
            + ($descriptionKeyword ? 5 : 0);

        $characteristicCount = count($characteristics);
        $characteristicsScore = ($characteristicCount >= 1 ? 5 : 0)
            + ($characteristicCount >= 2 ? 5 : 0)
            + ($characteristicCount >= 3 ? 10 : 0);

        $hasPreviewPicture = !empty($product['PREVIEW_PICTURE']);
        $hasDetailPicture = !empty($product['DETAIL_PICTURE']);
        $photoScore = ($hasPreviewPicture ? 10 : 0) + ($hasDetailPicture ? 10 : 0);

        $titleLength = mb_strlen($metaTitle);
        $titleFilled = $titleLength > 0;
        $titleLengthOk = $titleLength >= 30 && $titleLength <= 60;
        $titleKeyword = self::containsKeyword($metaTitle, $keywords);
        $titleScore = ($titleFilled ? 5 : 0) + ($titleLengthOk ? 10 : 0) + ($titleKeyword ? 5 : 0);

        $metaDescriptionLength = mb_strlen($metaDescription);
        $metaDescriptionFilled = $metaDescriptionLength > 0;
        $metaDescriptionLengthOk = $metaDescriptionLength >= 120 && $metaDescriptionLength <= 160;
        $metaDescriptionKeyword = self::containsKeyword($metaDescription, $keywords);
        $metaDescriptionScore = ($metaDescriptionFilled ? 5 : 0)
            + ($metaDescriptionLengthOk ? 10 : 0)
            + ($metaDescriptionKeyword ? 5 : 0);

        $score = max(0, min(100, $descriptionScore + $characteristicsScore + $photoScore + $titleScore + $metaDescriptionScore));
        $status = self::statusFor($score);

        $blocks = [
            'description' => [
                'filled' => $descriptionFilled,
                'score' => $descriptionScore,
                'max' => 20,
                'length' => $descriptionLength,
                'length_ok' => $descriptionLengthOk,
                'keyword' => $descriptionKeyword,
            ],
            'characteristics' => [
                'filled' => $characteristicCount > 0,
                'score' => $characteristicsScore,
                'max' => 20,
                'count' => $characteristicCount,
            ],
            'photo' => [
                'filled' => $hasPreviewPicture || $hasDetailPicture,
                'score' => $photoScore,
                'max' => 20,
                'preview' => $hasPreviewPicture,
                'detail' => $hasDetailPicture,
            ],
            'meta_title' => [
                'filled' => $titleFilled,
                'score' => $titleScore,
                'max' => 20,
                'length' => $titleLength,
                'length_ok' => $titleLengthOk,
                'keyword' => $titleKeyword,
            ],
            'meta_description' => [
                'filled' => $metaDescriptionFilled,
                'score' => $metaDescriptionScore,
                'max' => 20,
                'length' => $metaDescriptionLength,
                'length_ok' => $metaDescriptionLengthOk,
                'keyword' => $metaDescriptionKeyword,
            ],
        ];

        return [
            'score' => $score,
            'status' => $status,
            'keywords' => $keywords,
            'blocks' => $blocks,
            'recommendations' => self::recommendations($blocks),
        ];
    }

    public static function extractKeywords(string $name): array
    {
        $normalized = mb_strtolower(self::plainText($name));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn(string $token): bool => mb_strlen($token) >= 4 && !in_array($token, self::STOP_WORDS, true)
        )));
    }

    private static function statusFor(int $score): array
    {
        if ($score >= 80) {
            return ['code' => 'ready', 'label' => 'Готов'];
        }
        if ($score >= 50) {
            return ['code' => 'attention', 'label' => 'Требует внимания'];
        }

        return ['code' => 'critical', 'label' => 'Критично'];
    }

    private static function recommendations(array $blocks): array
    {
        $recommendations = [];
        if (!$blocks['description']['filled']) {
            $recommendations[] = 'Добавить описание товара';
        } elseif (!$blocks['description']['length_ok']) {
            $recommendations[] = 'Довести описание до 300–2000 символов';
        } elseif (!$blocks['description']['keyword']) {
            $recommendations[] = 'Добавить в описание слово из названия';
        }
        if ($blocks['characteristics']['count'] < 3) {
            $recommendations[] = 'Заполнить минимум три характеристики';
        }
        if (!$blocks['photo']['preview'] || !$blocks['photo']['detail']) {
            $recommendations[] = 'Добавить preview и detail изображения';
        }
        if (!$blocks['meta_title']['filled']) {
            $recommendations[] = 'Заполнить meta title';
        } elseif (!$blocks['meta_title']['length_ok']) {
            $recommendations[] = 'Скорректировать meta title до 30–60 символов';
        }
        if (!$blocks['meta_description']['filled']) {
            $recommendations[] = 'Заполнить meta description';
        } elseif (!$blocks['meta_description']['length_ok']) {
            $recommendations[] = 'Скорректировать meta description до 120–160 символов';
        }

        return array_slice($recommendations, 0, 3);
    }

    private static function containsKeyword(string $text, array $keywords): bool
    {
        if ($text === '' || $keywords === []) {
            return false;
        }
        $haystack = mb_strtolower($text);
        foreach ($keywords as $keyword) {
            if (mb_stripos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $value));
    }

    private static function nonEmptyValues(array $values): array
    {
        $flat = [];
        array_walk_recursive($values, static function ($value) use (&$flat): void {
            if (is_scalar($value) && trim((string)$value) !== '') {
                $flat[] = trim((string)$value);
            }
        });

        return array_values(array_unique($flat));
    }
}

