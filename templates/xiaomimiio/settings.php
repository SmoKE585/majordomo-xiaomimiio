<?php
/** Настройки модуля и состояние цикла. */
if (!isset($settings, $stats)) {
    http_response_code(404);
    return;
}
$card('Параметры сети и опроса', static function () use ($settings, $hidden): void { ?>
    <form method="post" class="md-ui-form-stack">
        <?php $hidden(array('operation' => 'save_settings')); ?>
        <div class="md-ui-form-grid">
            <?= MDJUI::input(array('name' => 'bind_ip', 'label' => 'IP для сокета', 'value' => $settings['bind_ip'], 'placeholder' => '0.0.0.0', 'hint' => 'Оставьте пустым для всех интерфейсов. Укажите адрес сервера, если в системе несколько сетевых карт.')) ?>
            <?= MDJUI::input(array('name' => 'disc_period', 'label' => 'Поиск устройств, секунд', 'value' => $settings['disc_period'], 'type' => 'number', 'attrs' => array('min' => 0, 'max' => 3600), 'hint' => 'Широковещательный поиск новых устройств. 0 — выключить.')) ?>
            <?= MDJUI::input(array('name' => 'socket_timeout', 'label' => 'Таймаут ответа, секунд', 'value' => $settings['socket_timeout'], 'type' => 'number', 'attrs' => array('min' => 1, 'max' => 10), 'hint' => 'Сколько ждать ответа устройства. 2 секунды подходит большинству.')) ?>
        </div>
        <?= MDJUI::checkbox(array('name' => 'log_debmes', 'label' => 'Писать обмен с устройствами в DebMes', 'value' => '1', 'checked' => $settings['log_debmes'], 'description' => 'Подробный журнал запросов и ответов. Включайте для разбора проблем.')) ?>
        <?= MDJUI::checkbox(array('name' => 'log_cycle', 'label' => 'Писать шаги цикла', 'value' => '1', 'checked' => $settings['log_cycle'])) ?>
        <?= MDJUI::checkbox(array('name' => 'log_miio', 'label' => 'Отладочный вывод библиотеки miIO', 'value' => '1', 'checked' => $settings['log_miio'], 'description' => 'Пакеты, сокет и тайминги. Только для диагностики.')) ?>
        <div class="md-ui-form-actions">
            <?= MDJUI::button('Сохранить настройки', array('type' => 'submit', 'icon' => 'check')) ?>
            <?= MDJUI::button('Отмена', array('href' => ROOTHTML . 'panel/xiaomimiio.html', 'variant' => 'ghost')) ?>
        </div>
    </form>
<?php }, array('icon' => 'settings')); ?>

<?php $card('Состояние цикла', static function () use ($stats): void { ?>
    <div class="md-xiaomi-state-grid">
        <?= MDJUI::status($stats['cycle_running'] ? 'online' : 'offline', $stats['cycle_running'] ? 'Цикл работает' : 'Цикл не отвечает') ?>
        <div>
            <?php if ($stats['cycle_running']): ?>
                Последний отклик <?= (int)$stats['cycle_age'] ?> с назад. Опрос и очередь работают.
            <?php elseif ($stats['cycle_age'] === null): ?>
                След цикла не найден. Запустите <code>php majordomo cycles:start xiaomimiio</code> или включите его в X-Ray.
            <?php else: ?>
                Последний отклик <?= (int)$stats['cycle_age'] ?> с назад. Проверьте процесс в X-Ray: цикл мог остановиться из-за ошибки.
            <?php endif; ?>
        </div>
    </div>
    <div class="md-ui-kpi-grid md-xiaomi-kpi-tight">
        <?= MDJUI::kpi(array('label' => 'Устройства', 'value' => $stats['devices'], 'icon' => 'cpu')) ?>
        <?= MDJUI::kpi(array('label' => 'Онлайн', 'value' => $stats['online'], 'icon' => 'wifi', 'tone' => 'success')) ?>
        <?= MDJUI::kpi(array('label' => 'Очередь', 'value' => $stats['queue'], 'icon' => 'list-check')) ?>
        <?= MDJUI::kpi(array('label' => 'Привязки', 'value' => $stats['bindings'], 'icon' => 'link')) ?>
    </div>
    <div class="md-ui-form-actions">
        <?= MDJUI::button('X-Ray: циклы и журнал', array('href' => ROOTHTML . 'panel/xray.html', 'variant' => 'secondary', 'icon' => 'activity')) ?>
        <?= MDJUI::button('Токены устройств', array('href' => ROOTHTML . 'panel/xiaomimiio/0/tokens.html', 'variant' => 'ghost', 'icon' => 'key')) ?>
    </div>
<?php }, array('icon' => 'refresh')); ?>

<?= MDJUI::hint(array(
    'title' => 'Что меняется при сохранении',
    'points' => array(
        'Настройки хранятся в записи модуля (project_modules.DATA), как и в прежних версиях. Старые значения сохраняются при обновлении.',
        'После сохранения цикл перезапускается, чтобы новый период поиска и таймаут применились сразу.',
        'Период опроса задаётся отдельно для каждого устройства на его странице.',
    ),
    'dismissible' => false,
)) ?>
