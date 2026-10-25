<?php
/**
 * Страница модуля Xiaomi miIO: общая оболочка и переключение разделов.
 *
 * Шаблон только выводит данные, подготовленные классом модуля. Все действия —
 * обычные POST-формы с CSRF-токеном, поэтому страница работает без сборки JS и
 * после partLoad.
 */
if (!isset($view, $csrf) || !class_exists('MDJUI', false)) {
    http_response_code(404);
    return;
}

$e = static fn($value) => MDJUI::e((string)($value ?? ''));
$url = static function (string $page = '', int $id = 0, string $tab = '') use ($view): string {
    if ($page === 'list') return ROOTHTML . 'panel/xiaomimiio.html';
    if ($page !== '') return ROOTHTML . 'panel/xiaomimiio/0/' . $page . '.html';
    $base = ROOTHTML . 'panel/xiaomimiio/' . max(0, $id) . '.html';
    if ($tab !== '') $base = ROOTHTML . 'panel/xiaomimiio/' . max(0, $id) . '/' . rawurlencode($tab) . '.html';
    return $base;
};
$hidden = static function (array $fields) use ($e, $csrf): void {
    // _md_csrf проверяет AuthGate, _token — сам модуль. Оба значения одинаковы.
    echo '<input type="hidden" name="_md_csrf" value="' . $e($csrf) . '">';
    echo '<input type="hidden" name="_token" value="' . $e($csrf) . '">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . $e($name) . '" value="' . $e($value) . '">';
    }
};
$card = static function (string $title, callable $body, array $props = array()): void {
    ob_start();
    $body();
    $slot = (string)ob_get_clean();
    echo MDJUI::card(array_merge(array('title' => $title), $props), $slot);
};
$deviceImage = static function (string $model, bool $large = false) use ($e): void {
    $src = XiaomiMiioCatalog::imageUrl($model);
    ?><span class="md-table__icon md-xiaomi-image<?= $large ? ' md-xiaomi-image--large' : '' ?>" data-md-xiaomi-image>
        <?php if ($src !== null): ?><img src="<?= $e($src) ?>" alt="" width="<?= $large ? 96 : 44 ?>" height="<?= $large ? 96 : 44 ?>" loading="lazy" decoding="async" data-md-xiaomi-photo><?php endif; ?>
        <?= MDJUI::icon('cpu') ?>
    </span><?php
};
$statusOf = static function (array $device, string $online): string {
    if (trim((string)($device['DEVICE_TYPE'] ?? '')) === '') return 'idle';
    if ($online === '1') return 'online';
    if ($online === '0') return 'offline';
    return 'idle';
};

$isDevice = $view === 'edit_miio_devices';
$title = 'Xiaomi miIO';
$lede = 'Wi-Fi устройства Xiaomi по локальному протоколу miIO — без облака и подписок.';
if ($view === 'settings') {
    $title = 'Настройки Xiaomi miIO';
    $lede = 'Сетевые параметры цикла и уровень диагностики.';
} elseif ($view === 'tokens') {
    $title = 'Токены устройств';
    $lede = 'Как получить токен miIO и перенести его в модуль, не отправляя пароль Xiaomi на сервер.';
} elseif ($isDevice && !$isNew) {
    $title = (string)($device['TITLE'] ?: XiaomiMiioCatalog::title((string)$device['DEVICE_TYPE']));
    $lede = trim((string)$device['IP']) . ' · ' . XiaomiMiioCatalog::title((string)$device['DEVICE_TYPE']);
} elseif ($isDevice && $isNew) {
    $title = 'Новое устройство';
    $lede = 'IP и токен miIO. Модель можно определить автоматически по ответу устройства.';
}
?>
<link rel="stylesheet" href="<?= $e(ROOTHTML) ?>assets/modules/xiaomimiio/xiaomimiio.css?v=20261025-1">
<script src="<?= $e(ROOTHTML) ?>assets/modules/xiaomimiio/xiaomimiio.js?v=20261025-1" defer></script>
<div class="md-ui-page md-ui-page--stack md-xiaomi-page" data-md-xiaomi
     data-md-csrf="<?= $e($csrf) ?>"
     data-md-ajax-url="<?= $e(ROOTHTML . 'ajax/xiaomimiio.html') ?>"
     data-md-assets="<?= $e(ROOTHTML . 'assets/modules/xiaomimiio/img/small/') ?>">
    <header class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><?= MDJUI::icon('cpu') ?> Xiaomi miIO</div>
            <h1 class="md-ui-hero__title"><?= $e($title) ?></h1>
            <p class="md-ui-hero__lede"><?= $e($lede) ?></p>
        </div>
        <div class="md-ui-hero__actions">
            <?php if ($view === 'search_miio_devices'): ?>
                <?= MDJUI::button('Добавить устройство', array('href' => $url('add'), 'icon' => 'plus')) ?>
                <form method="post" class="md-xiaomi-inline-form">
                    <?php $hidden(array('operation' => 'discover')); ?>
                    <?= MDJUI::button('Найти в сети', array('type' => 'submit', 'variant' => 'secondary', 'icon' => 'radar', 'attrs' => array('data-md-confirm' => 'Отправить широковещательный запрос? Поиск занимает до 10 секунд.'))) ?>
                </form>
                <?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'ghost', 'icon' => 'settings')) ?>
            <?php elseif ($isDevice && !$isNew): ?>
                <?= MDJUI::button('К устройствам', array('href' => $url('list'), 'variant' => 'secondary', 'icon' => 'arrow-left')) ?>
                <form method="post" class="md-xiaomi-inline-form">
                    <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $device['ID'], 'return_url' => $url('', (int)$device['ID'], $tab === 'bind' ? 'data' : $tab))); ?>
                    <?= MDJUI::button('Опросить', array('type' => 'submit', 'icon' => 'refresh')) ?>
                </form>
                <?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'ghost', 'icon' => 'settings')) ?>
            <?php else: ?>
                <?= MDJUI::button('К устройствам', array('href' => $url('list'), 'variant' => 'secondary', 'icon' => 'arrow-left')) ?>
                <?php if ($view === 'settings'): ?><?= MDJUI::button('Токены', array('href' => $url('tokens'), 'variant' => 'ghost', 'icon' => 'key')) ?><?php endif; ?>
                <?php if ($view === 'tokens'): ?><?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'ghost', 'icon' => 'settings')) ?><?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <?= MDJUI::alert('danger', 'Действие не выполнено', $error) ?>
    <?php endif; ?>
    <?php if (is_array($flash) && !empty($flash['title'])): ?>
        <?= MDJUI::alert($flash['type'] ?? 'success', (string)$flash['title'], (string)($flash['text'] ?? '')) ?>
    <?php endif; ?>

    <?php if (is_array($directResult) && !empty($directResult['json'])): ?>
        <?php $card('Ответ устройства · ' . $directResult['method'] . ' · ' . $directResult['at'], static function () use ($directResult): void { ?>
            <pre class="md-xiaomi-json" tabindex="0"><?= htmlspecialchars((string)$directResult['json'], ENT_QUOTES, 'UTF-8') ?></pre>
            <p class="md-field__hint">Это разовый ответ. Он не записан в историю свойств и не изменил привязки.</p>
        <?php }, array('icon' => 'terminal-2', 'class' => 'md-xiaomi-result')); ?>
    <?php endif; ?>

    <?php if ($view === 'search_miio_devices'): ?>
        <?php require __DIR__ . '/devices.php'; ?>
    <?php elseif ($view === 'edit_miio_devices'): ?>
        <?php require __DIR__ . '/device.php'; ?>
    <?php elseif ($view === 'settings'): ?>
        <?php require __DIR__ . '/settings.php'; ?>
    <?php elseif ($view === 'tokens'): ?>
        <?php require __DIR__ . '/tokens.php'; ?>
    <?php endif; ?>
</div>
