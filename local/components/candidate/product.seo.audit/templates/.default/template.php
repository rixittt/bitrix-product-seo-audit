<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
$summary = $arResult['SUMMARY'];
?>
<script>document.body.classList.add('seo-audit-page');</script>
<section class="seo-audit" aria-labelledby="seo-audit-title">
    <div class="seo-audit__hero">
        <div class="seo-audit__hero-copy">
            <p class="seo-audit__eyebrow"><span></span> Демо-проект на 1С-Битрикс</p>
            <h1 id="seo-audit-title">SEO-готовность товаров</h1>
            <p class="seo-audit__lead">Единый экран контроля контента, характеристик, изображений и мета-данных каталога.</p>
            <nav class="seo-audit__quick-links" aria-label="Разделы магазина">
                <a href="/catalog/seo-audit-demo/">Открыть каталог <span aria-hidden="true">↗</span></a>
                <a href="/">На витрину</a>
            </nav>
        </div>
        <div class="seo-audit__average" style="--average: <?= (int)$summary['average'] ?>" aria-label="Средняя оценка <?= (int)$summary['average'] ?> процентов">
            <div class="seo-audit__average-ring">
                <strong><?= (int)$summary['average'] ?>%</strong>
            </div>
            <span>средняя готовность</span>
        </div>
    </div>

    <div class="seo-audit__summary" aria-label="Сводка отчёта">
        <div><strong><?= (int)$summary['total'] ?></strong><span>товаров</span></div>
        <div class="is-ready"><strong><?= (int)$summary['ready'] ?></strong><span>готовы</span></div>
        <div class="is-attention"><strong><?= (int)$summary['attention'] ?></strong><span>требуют внимания</span></div>
        <div class="is-critical"><strong><?= (int)$summary['critical'] ?></strong><span>критично</span></div>
    </div>

    <form class="seo-audit__filters" method="get" action="">
        <label>
            <span>Оценка</span>
            <select name="seo_score">
                <option value="all"<?= $arResult['FILTER'] === 'all' ? ' selected' : '' ?>>Все товары</option>
                <option value="ready"<?= $arResult['FILTER'] === 'ready' ? ' selected' : '' ?>>80–100% — готовы</option>
                <option value="attention"<?= $arResult['FILTER'] === 'attention' ? ' selected' : '' ?>>50–79% — требуют внимания</option>
                <option value="critical"<?= $arResult['FILTER'] === 'critical' ? ' selected' : '' ?>>0–49% — критично</option>
            </select>
        </label>
        <label>
            <span>Сортировка</span>
            <select name="seo_sort">
                <option value="score_desc"<?= $arResult['SORT'] === 'score_desc' ? ' selected' : '' ?>>Сначала лучшие</option>
                <option value="score_asc"<?= $arResult['SORT'] === 'score_asc' ? ' selected' : '' ?>>Сначала проблемные</option>
                <option value="name_asc"<?= $arResult['SORT'] === 'name_asc' ? ' selected' : '' ?>>По названию</option>
            </select>
        </label>
        <button type="submit">Применить</button>
        <a href="?">Сбросить</a>
        <span class="seo-audit__visible">Показано: <?= (int)$arResult['VISIBLE_COUNT'] ?></span>
    </form>

    <?php if ($arResult['ITEMS'] === []): ?>
        <div class="seo-audit__empty">По выбранному фильтру товаров нет.</div>
    <?php else: ?>
        <div class="seo-audit__table-wrap">
            <table class="seo-audit__table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Описание</th>
                        <th>Характеристики</th>
                        <th>Фото</th>
                        <th>Meta title</th>
                        <th>Meta description</th>
                        <th>Оценка</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($arResult['ITEMS'] as $item):
                    $audit = $item['AUDIT'];
                    $blocks = $audit['blocks'];
                    $characteristicDetails = $item['CHARACTERISTIC_DETAILS'] ?? [];
                    ?>
                    <tr>
                        <td data-label="Товар" class="seo-audit__product">
                            <a href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>"><?= htmlspecialcharsbx($item['NAME']) ?></a>
                            <?php if ($audit['recommendations'] !== []): ?>
                                <small><?= htmlspecialcharsbx(implode(' · ', $audit['recommendations'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Описание">
                            <span class="seo-check <?= $blocks['description']['filled'] ? 'is-filled' : 'is-empty' ?>">
                                <?= $blocks['description']['filled'] ? 'Заполнено' : 'Нет' ?>
                            </span>
                            <small><?= (int)$blocks['description']['length'] ?> симв. · <?= (int)$blocks['description']['score'] ?>/20</small>
                        </td>
                        <td data-label="Характеристики" class="seo-audit__characteristics">
                            <span class="seo-check <?= $blocks['characteristics']['count'] >= 3 ? 'is-filled' : 'is-partial' ?>">
                                <?= (int)$blocks['characteristics']['count'] ?> из 3+
                            </span>
                            <small><?= (int)$blocks['characteristics']['score'] ?>/20</small>
                            <?php if ($characteristicDetails !== []): ?>
                                <ul class="seo-audit__characteristic-list">
                                    <?php foreach ($characteristicDetails as $characteristic): ?>
                                        <li><strong><?= htmlspecialcharsbx($characteristic['NAME']) ?>:</strong> <?= htmlspecialcharsbx($characteristic['VALUE']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td data-label="Фото">
                            <span class="seo-check <?= $blocks['photo']['score'] === 20 ? 'is-filled' : ($blocks['photo']['filled'] ? 'is-partial' : 'is-empty') ?>">
                                P: <?= $blocks['photo']['preview'] ? 'да' : 'нет' ?> · D: <?= $blocks['photo']['detail'] ? 'да' : 'нет' ?>
                            </span>
                            <small><?= (int)$blocks['photo']['score'] ?>/20</small>
                        </td>
                        <td data-label="Meta title">
                            <span class="seo-check <?= $blocks['meta_title']['filled'] ? ($blocks['meta_title']['length_ok'] ? 'is-filled' : 'is-partial') : 'is-empty' ?>">
                                <?= $blocks['meta_title']['filled'] ? (int)$blocks['meta_title']['length'] . ' симв.' : 'Нет' ?>
                            </span>
                            <small><?= (int)$blocks['meta_title']['score'] ?>/20</small>
                        </td>
                        <td data-label="Meta description">
                            <span class="seo-check <?= $blocks['meta_description']['filled'] ? ($blocks['meta_description']['length_ok'] ? 'is-filled' : 'is-partial') : 'is-empty' ?>">
                                <?= $blocks['meta_description']['filled'] ? (int)$blocks['meta_description']['length'] . ' симв.' : 'Нет' ?>
                            </span>
                            <small><?= (int)$blocks['meta_description']['score'] ?>/20</small>
                        </td>
                        <td data-label="Оценка" class="seo-audit__score">
                            <div class="seo-score seo-score--<?= htmlspecialcharsbx($audit['status']['code']) ?>">
                                <div class="seo-score__head"><strong><?= (int)$audit['score'] ?>%</strong><span><?= htmlspecialcharsbx($audit['status']['label']) ?></span></div>
                                <div class="seo-score__bar"><i style="width: <?= (int)$audit['score'] ?>%"></i></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="seo-audit__note">Оценка носит диагностический характер: по 20 баллов дают описание, характеристики, фотографии, meta title и meta description.</p>
</section>
