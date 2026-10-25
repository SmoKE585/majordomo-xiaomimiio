<?php
/** Карточка устройства miIO: настройки, свойства, шлюзовые вкладки и справка. */
if (!isset($device, $isNew, $url, $tab)) {
    http_response_code(404);
    return;
}
$model = (string)($device['DEVICE_TYPE'] ?? '');
$state = $statusOf($device, (string)$online);
$id = (int)$device['ID'];
$isGateway = XiaomiMiioCatalog::isGateway($model);
$hasIr = XiaomiMiioCatalog::hasIr($model);
$lanKey = '';
foreach ($properties as $propertyRow) {
    if (($propertyRow['TITLE'] ?? '') === 'lumi_dpf_aes_key') $lanKey = (string)$propertyRow['VALUE'];
}
$dataCount = $isGateway ? count($properties) : count($properties);
$tabTitles = array('' => 'Обзор', 'data' => 'Свойства', 'gwradio' => 'Радио', 'gwzigbee' => 'Zigbee', 'help' => 'Справка');

if ($isNew):
    ?>
    <div class="md-xiaomi-two-col">
        <?php $card('Подключение', static function () use ($device, $model, $hidden, $e, $url): void { ?>
            <form method="post" class="md-ui-form-stack">
                <?php $hidden(array('operation' => 'save_device', 'device_id' => 0)); ?>
                <div class="md-ui-form-grid">
                    <?= MDJUI::input(array('name' => 'title', 'label' => 'Название', 'value' => $device['TITLE'], 'required' => true, 'attrs' => array('maxlength' => 100, 'autofocus' => true), 'hint' => 'Как устройство будет называться в панели и сценариях.')) ?>
                    <?= MDJUI::input(array('name' => 'ip', 'label' => 'IP-адрес', 'value' => $device['IP'], 'required' => true, 'attrs' => array('inputmode' => 'decimal', 'placeholder' => '192.168.1.50'), 'hint' => 'Локальный адрес устройства в вашей сети.')) ?>
                </div>
                <?= MDJUI::input(array('name' => 'token', 'label' => 'Токен miIO', 'value' => '', 'type' => 'password', 'attrs' => array('autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => '32 шестнадцатеричных символа'), 'hint' => 'Можно оставить пустым и заполнить позже. Без токена устройство не отвечает на запросы.')) ?>
                <div class="md-ui-form-grid">
                    <?= MDJUI::select(array('name' => 'device_type', 'label' => 'Модель', 'selected' => $model, 'options' => array('' => 'Определить автоматически') + XiaomiMiioCatalog::options($model), 'attrs' => array('data-md-searchable' => true), 'hint' => 'Если модели нет в списке, укажите её вручную ниже.')) ?>
                    <?= MDJUI::input(array('name' => 'device_type_manual', 'label' => 'Другая модель', 'value' => '', 'placeholder' => 'например, zhimi.airpurifier.ma2', 'hint' => 'Точное имя модели из miIO.info.')) ?>
                </div>
                <?= MDJUI::input(array('name' => 'update_period', 'label' => 'Период опроса, секунд', 'value' => $device['UPDATE_PERIOD'], 'type' => 'number', 'attrs' => array('min' => 0, 'max' => 86400, 'step' => 1), 'hint' => '0 — не опрашивать по расписанию (только команды из очереди). Рекомендуется 60.')) ?>
                <div class="md-ui-form-actions">
                    <?= MDJUI::button('Добавить устройство', array('type' => 'submit', 'icon' => 'plus')) ?>
                    <?= MDJUI::button('Отмена', array('href' => $url('list'), 'variant' => 'ghost')) ?>
                </div>
            </form>
        <?php }, array('icon' => 'plug')); ?>
        <?php $card('Что понадобится', static function () use ($url, $e): void {
            echo MDJUI::hint(array(
                'title' => 'IP и токен',
                'points' => array(
                    'IP устройства виден в приложении Mi Home в свойствах устройства или в списке клиентов роутера.',
                    'Токен — 32-символьный ключ шифрования. Его выдаёт token_extractor.py либо приложение Mi Home (резервная копия).',
                    'Пароль Xiaomi на сервер не передавайте: скрипт запускается на вашем компьютере, сюда вставляется только результат.',
                ),
                'actions' => MDJUI::button('Как получить токен', array('href' => $url('tokens'), 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'key')),
            ));
        }, array('icon' => 'key')); ?>
    </div>
    <?php
    return;
endif;
?>

<?php if ($model === ''): ?>
    <?= MDJUI::alert('warning', 'Модель не определена', 'Пока модуль не знает модель, он не умеет запрашивать свойства. Запросите miIO.info в разделе «Диагностика» — модель определится автоматически, если токен верный.') ?>
<?php endif; ?>

<nav class="nav md-tabs" aria-label="Разделы устройства">
    <?php foreach ($tabTitles as $key => $label):
        if ($key === 'gwradio' && !$isGateway) continue;
        if ($key === 'gwzigbee' && !$isGateway) continue;
        $href = $key === '' ? $url('', $id) : $url('', $id, $key);
        $active = $tab === $key || ($tab === 'bind' && $key === 'data');
        $count = $key === 'data' ? count($properties) : ($key === 'gwradio' ? $dataCount : null);
        ?>
        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= $e($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
            <?= $e($label) ?><?= $count !== null ? ' <span class="md-tabs__count">' . (int)$count . '</span>' : '' ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === ''): ?>
    <div class="md-ui-kpi-grid">
        <?= MDJUI::kpi(array('label' => 'Доступность', 'value' => $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Неизвестно'), 'icon' => 'wifi', 'tone' => $state === 'online' ? 'success' : ($state === 'offline' ? 'warning' : 'neutral'), 'meta' => 'по последнему ответу')) ?>
        <?= MDJUI::kpi(array('label' => 'Последний ответ', 'value' => $device['LAST_SEEN'] ?: '—', 'icon' => 'clock', 'meta' => 'время сервера')) ?>
        <?= MDJUI::kpi(array('label' => 'Период опроса', 'value' => (int)$device['UPDATE_PERIOD'] > 0 ? (int)$device['UPDATE_PERIOD'] . ' с' : 'выключен', 'icon' => 'refresh', 'meta' => (int)($device['FAILURES'] ?? 0) > 0 ? 'ошибок подряд: ' . (int)$device['FAILURES'] : 'ошибок нет')) ?>
        <?= MDJUI::kpi(array('label' => 'Очередь', 'value' => count($queue), 'icon' => 'list-check', 'meta' => 'последних команд')) ?>
    </div>

    <div class="md-xiaomi-two-col">
        <?php $card('Устройство', static function () use ($deviceImage, $model, $device, $state, $id, $e, $url): void { ?>
            <div class="md-xiaomi-device-head">
                <?php $deviceImage($model, true); ?>
                <div class="md-xiaomi-device-meta">
                    <div class="md-xiaomi-device-title"><?= $e(XiaomiMiioCatalog::title($model)) ?></div>
                    <?php if ($model !== ''): ?><div class="md-xiaomi-device-model font-monospace"><?= $e($model) ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?= MDJUI::status($state, $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Нет данных')) ?>
                        <?php if ($model !== ''): ?>
                            <a class="md-field__hint" href="<?= $e(XiaomiMiioCatalog::specUrl($model)) ?>" target="_blank" rel="noopener">Спецификация MIoT <?= MDJUI::icon('external-link') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <dl class="md-xiaomi-meta-list">
                <div><dt>IP</dt><dd class="font-monospace"><?= $e($device['IP'] ?: '—') ?></dd></div>
                <div><dt>Токен</dt><dd><?= trim((string)$device['TOKEN']) !== '' ? 'задан' : 'не задан' ?></dd></div>
                <div><dt>Серийный номер</dt><dd><?= $e($device['SERIAL'] ?: '—') ?></dd></div>
                <div><dt>MAC</dt><dd class="font-monospace"><?= $e($device['MAC'] ?: '—') ?></dd></div>
                <div><dt>Аппаратная версия</dt><dd><?= $e($device['HW_VER'] ?: '—') ?></dd></div>
                <div><dt>Обновлено</dt><dd><?= $e($device['UPDATED'] ?: '—') ?></dd></div>
            </dl>
            <div class="md-ui-form-actions">
                <?= MDJUI::button('Свойства и привязки', array('href' => $url('', $id, 'data'), 'variant' => 'secondary', 'icon' => 'link')) ?>
                <?php if ($model !== ''): ?><?= MDJUI::button('Справка по модели', array('href' => $url('', $id, 'help'), 'variant' => 'ghost', 'icon' => 'book')) ?><?php endif; ?>
            </div>
        <?php }, array('icon' => 'cpu')); ?>

        <?php $card('Настройки подключения', static function () use ($device, $model, $hidden, $url): void { ?>
            <form method="post" class="md-ui-form-stack">
                <?php $hidden(array('operation' => 'save_device', 'device_id' => $device['ID'])); ?>
                <div class="md-ui-form-grid">
                    <?= MDJUI::input(array('name' => 'title', 'label' => 'Название', 'value' => $device['TITLE'], 'required' => true, 'attrs' => array('maxlength' => 100))) ?>
                    <?= MDJUI::input(array('name' => 'ip', 'label' => 'IP-адрес', 'value' => $device['IP'], 'required' => true, 'attrs' => array('inputmode' => 'decimal'))) ?>
                </div>
                <?= MDJUI::input(array('name' => 'token', 'label' => 'Токен miIO', 'value' => '', 'type' => 'password', 'attrs' => array('autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => 'оставьте пустым, чтобы не менять'), 'hint' => 'Токен хранится в таблице модуля и не выводится в журналы.')) ?>
                <div class="md-ui-form-grid">
                    <?= MDJUI::select(array('name' => 'device_type', 'label' => 'Модель', 'selected' => $model, 'options' => XiaomiMiioCatalog::options($model), 'attrs' => array('data-md-searchable' => true))) ?>
                    <?= MDJUI::input(array('name' => 'device_type_manual', 'label' => 'Другая модель', 'value' => '', 'placeholder' => 'если модели нет в списке')) ?>
                </div>
                <?= MDJUI::input(array('name' => 'update_period', 'label' => 'Период опроса, секунд', 'value' => (int)$device['UPDATE_PERIOD'], 'type' => 'number', 'attrs' => array('min' => 0, 'max' => 86400), 'hint' => '0 — только команды по запросу.')) ?>
                <div class="md-ui-form-actions">
                    <?= MDJUI::button('Сохранить', array('type' => 'submit', 'icon' => 'check')) ?>
                </div>
            </form>
            <form method="post" class="md-xiaomi-danger-zone">
                <?php $hidden(array('operation' => 'delete_device', 'device_id' => $device['ID'])); ?>
                <?= MDJUI::button('Удалить устройство', array('type' => 'submit', 'variant' => 'danger', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('data-md-confirm' => 'Удалить устройство «' . $device['TITLE'] . '» и его привязки? Объекты и сценарии останутся.'))) ?>
                <span class="md-field__hint">Удаляются только запись устройства, его свойства и привязки. Объекты и сценарии MajorDoMo не изменяются.</span>
            </form>
        <?php }, array('icon' => 'settings')); ?>
    </div>

    <?php $card('Диагностика', static function () use ($device, $hidden, $url, $model, $queue, $lanKey, $e): void { ?>
        <div class="md-xiaomi-tools">
            <form method="post" class="md-ui-form-stack">
                <?php $hidden(array('operation' => 'device_info', 'device_id' => $device['ID'])); ?>
                <div>
                    <strong>miIO.info</strong>
                    <p class="md-field__hint">Запросить модель, MAC и версию прошивки напрямую. Ответ показывается на этой странице.</p>
                </div>
                <?= MDJUI::button('Запросить сведения', array('type' => 'submit', 'variant' => 'secondary', 'icon' => 'info-circle')) ?>
            </form>
            <form method="post" class="md-ui-form-stack">
                <?php $hidden(array('operation' => 'test_command', 'device_id' => $device['ID'])); ?>
                <div class="md-ui-form-grid">
                    <?= MDJUI::input(array('name' => 'method', 'label' => 'Метод miIO', 'value' => 'get_prop', 'required' => true, 'attrs' => array('placeholder' => 'get_prop'), 'hint' => 'Например, get_prop, set_power, miIO.info, get_status.')) ?>
                    <?= MDJUI::input(array('name' => 'params', 'label' => 'Параметры (JSON)', 'value' => '["power"]', 'hint' => 'Массив параметров. Для команд без параметров — [].')) ?>
                </div>
                <p class="md-field__hint">Команда выполняется сразу и не сохраняется в привязки. Ответ — в карточке над вкладками.</p>
                <?= MDJUI::button('Выполнить команду', array('type' => 'submit', 'variant' => 'secondary', 'icon' => 'player-play')) ?>
            </form>
            <div class="md-xiaomi-tool-row">
                <form method="post" class="md-xiaomi-inline-form">
                    <?php $hidden(array('operation' => 'clear_queue', 'device_id' => $device['ID'])); ?>
                    <?= MDJUI::button('Очистить очередь', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'eraser', 'attrs' => array('data-md-confirm' => 'Удалить все команды этого устройства из очереди?'))) ?>
                </form>
                <?= MDJUI::button('Все свойства', array('href' => $url('', (int)$device['ID'], 'data'), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'list')) ?>
            </div>
        </div>

        <?php if ($queue): ?>
            <details class="md-xiaomi-details">
                <summary>Последние команды в очереди (<?= count($queue) ?>)</summary>
                <div class="md-table__scroll">
                    <table class="md-table md-table--compact">
                        <thead><tr><th scope="col">Добавлена</th><th scope="col">Метод</th><th scope="col">Параметры</th></tr></thead>
                        <tbody>
                        <?php foreach ($queue as $row): ?>
                            <tr>
                                <td><span class="md-table__signal"><?= $e($row['ADDED']) ?></span></td>
                                <td class="font-monospace"><?= $e($row['METHOD']) ?></td>
                                <td class="md-xiaomi-value"><?= $e(mb_substr((string)$row['DATA'], 0, 160)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>

        <?php if (XiaomiMiioCatalog::supportsLanKey($model)): ?>
            <div class="md-xiaomi-lan" data-md-xiaomi-lan data-device-id="<?= (int)$device['ID'] ?>">
                <div>
                    <strong>LAN-режим шлюза</strong>
                    <p class="md-field__hint">Ключ локальной сети нужен сторонним интеграциям шлюза. Текущий ключ: <span class="font-monospace" data-md-xiaomi-lan-current><?= $e($lanKey !== '' ? $lanKey : 'не активен') ?></span></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?= MDJUI::button('Включить', array('variant' => 'secondary', 'size' => 'sm', 'icon' => 'lock-open', 'attrs' => array('data-md-xiaomi-lan-on' => true))) ?>
                    <?= MDJUI::button('Выключить', array('variant' => 'ghost', 'size' => 'sm', 'icon' => 'lock', 'attrs' => array('data-md-xiaomi-lan-off' => true))) ?>
                </div>
                <p class="md-xiaomi-log" data-md-xiaomi-lan-log aria-live="polite"></p>
            </div>
        <?php endif; ?>

        <?php if (XiaomiMiioCatalog::hasIr($model)): ?>
            <div class="md-xiaomi-ir" data-md-xiaomi-ir data-device-id="<?= (int)$device['ID'] ?>" data-ir-model="<?= $e($model) ?>">
                <div>
                    <strong>ИК-обучение</strong>
                    <p class="md-field__hint">Наведите пульт на устройство и нажмите «Начать обучение», затем нажмите кнопку на пульте. Полученный код можно использовать в команде <code>ir_play</code>.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?= MDJUI::button('Начать обучение', array('variant' => 'secondary', 'size' => 'sm', 'icon' => 'player-play', 'attrs' => array('data-md-xiaomi-ir-start' => true))) ?>
                    <?= MDJUI::button('Остановить', array('variant' => 'ghost', 'size' => 'sm', 'icon' => 'player-stop', 'attrs' => array('data-md-xiaomi-ir-stop' => true))) ?>
                </div>
                <p class="md-xiaomi-log" data-md-xiaomi-ir-log aria-live="polite"></p>
            </div>
        <?php endif; ?>
    <?php }, array('icon' => 'stethoscope')); ?>

<?php elseif ($tab === 'data' || $tab === 'gwradio'): ?>
    <?php if ($tab === 'gwradio'): ?>
        <?= MDJUI::alert('info', 'Радио шлюза', 'Список радиостанций и текущее воспроизведение. Значения обновляются по опросу; привязать их к свойствам объектов можно как обычные свойства.') ?>
    <?php endif; ?>
    <section class="md-table-wrap" aria-label="Свойства устройства"
             data-md-table data-md-table-unit-one="свойство" data-md-table-unit-few="свойства" data-md-table-unit-many="свойств">
        <div class="md-table__toolbar">
            <div class="md-table__search">
                <?= MDJUI::icon('search') ?>
                <input type="search" data-md-table-search placeholder="Свойство, ключ или привязка" aria-label="Поиск свойств" autocomplete="off">
            </div>
            <span class="md-table__meta" data-md-table-meta>Свойств: <?= count($properties) ?></span>
            <form method="post" action="<?= $e($url('', $id, $tab === 'data' ? 'data' : 'gwradio')) ?>" class="md-xiaomi-inline-form" data-md-refresh-device="<?= (int)$device['ID'] ?>">
                <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $device['ID'], 'return_url' => $url('', $id, $tab === 'data' ? 'data' : 'gwradio'))); ?>
                <?= MDJUI::button('Обновить', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'refresh')) ?>
            </form>
        </div>
        <?php if (!$properties): ?>
            <?= MDJUI::emptyState(array(
                'icon' => 'list',
                'title' => 'Свойств пока нет',
                'text' => trim((string)$device['TOKEN']) === ''
                    ? 'Сначала укажите токен на вкладке «Обзор»: без него модуль не может запросить состояние.'
                    : 'Нажмите «Опросить» на вкладке «Обзор». Свойства появятся после первого ответа устройства.',
                'compact' => true,
            )) ?>
        <?php else: ?>
            <div class="md-table__scroll">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th scope="col"><button type="button" class="md-table__sort" data-md-table-sort="name">Свойство <?= MDJUI::icon('arrows-up-down') ?></button></th>
                        <th scope="col">Значение</th>
                        <th scope="col">Обновлено</th>
                        <th scope="col">Привязка</th>
                        <th scope="col" class="text-end" aria-label="Действия"></th>
                    </tr>
                    </thead>
                    <tbody data-md-table-body>
                    <?php foreach ($properties as $row):
                        $key = (string)$row['TITLE'];
                        $label = XiaomiMiioCatalog::label($key);
                        $bound = trim((string)$row['LINKED_OBJECT']) !== '' && trim((string)$row['LINKED_PROPERTY']) !== '';
                        $searchText = $label . ' ' . $key . ' ' . ($row['VALUE'] ?? '') . ' ' . ($row['LINKED_OBJECT'] ?? '') . ' ' . ($row['LINKED_PROPERTY'] ?? '');
                        ?>
                        <tr data-md-row data-sort-name="<?= $e($label) ?>" data-search="<?= $e($searchText) ?>">
                            <td>
                                <div class="md-table__name">
                                    <span class="md-table__icon"><?= MDJUI::icon($key === 'online' ? 'wifi' : ($key === 'message' ? 'message' : ($key === 'command' ? 'terminal-2' : 'adjustments-horizontal'))) ?></span>
                                    <div>
                                        <a href="<?= $e($url('', $id, 'bind-' . (int)$row['ID'])) ?>"><strong><?= $e($label) ?></strong></a>
                                        <small class="font-monospace"><?= $e($key) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="md-xiaomi-value">
                                <?php if ($key === 'online'): ?>
                                    <?= MDJUI::status($row['VALUE'] === '1' ? 'online' : 'offline') ?>
                                <?php elseif ($key === 'message' && mb_strlen((string)$row['VALUE']) > 80): ?>
                                    <span class="md-table__signal" title="<?= $e($row['VALUE']) ?>"><?= $e(mb_substr((string)$row['VALUE'], 0, 80)) ?>…</span>
                                <?php else: ?>
                                    <span><?= $e(XiaomiMiioCatalog::display($key, $row['VALUE'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="md-table__signal"><?= $e($row['UPDATED'] ?: '—') ?></span></td>
                            <td>
                                <?php if ($bound): ?>
                                    <?= MDJUI::badge($row['LINKED_OBJECT'] . '.' . $row['LINKED_PROPERTY'], 'outline', array('icon' => 'link')) ?>
                                <?php else: ?>
                                    <span class="md-table__signal">не привязано</span>
                                <?php endif; ?>
                                <?php if (trim((string)$row['LINKED_METHOD']) !== ''): ?>
                                    <?= MDJUI::badge('+' . $row['LINKED_METHOD'], 'neutral', array('icon' => 'bolt')) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="md-table__actions">
                                    <?= MDJUI::button('', array('href' => $url('', $id, 'bind-' . (int)$row['ID']), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'pencil', 'attrs' => array('title' => 'Настроить привязку', 'aria-label' => 'Настроить ' . $label))) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="md-table__empty" data-md-table-empty><?= MDJUI::emptyState(array('title' => 'Свойства не найдены', 'text' => 'Измените поиск.', 'compact' => true)) ?></div>
            <div class="md-table__footer"><span data-md-table-count>Свойств: <?= count($properties) ?></span></div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'bind' && $bindingCommand): ?>
    <?php
    $key = (string)$bindingCommand['TITLE'];
    $label = XiaomiMiioCatalog::label($key);
    $bound = trim((string)$bindingCommand['LINKED_OBJECT']) !== '' && trim((string)$bindingCommand['LINKED_PROPERTY']) !== '';
    ?>
    <p class="md-xiaomi-context">
        <a href="<?= $e($url('', $id, 'data')) ?>">Свойства устройства</a> ·
        <strong><?= $e($label) ?></strong> · <code><?= $e($key) ?></code>
    </p>
    <?php $card('Привязка к объекту MajorDoMo', static function () use ($bindingCommand, $linkedControl, $hidden, $url, $e, $bound): void { ?>
        <form method="post" class="md-ui-form-stack">
            <?php $hidden(array('operation' => 'save_binding', 'command_id' => $bindingCommand['ID'])); ?>
            <div class="md-ui-form-grid">
                <div class="md-field">
                    <span class="md-field__label">Значение от устройства</span>
                    <span class="md-xiaomi-value"><?= $e(XiaomiMiioCatalog::display((string)$bindingCommand['TITLE'], $bindingCommand['VALUE'])) ?></span>
                </div>
                <div class="md-field">
                    <span class="md-field__label">Обновлено</span>
                    <span><?= $e($bindingCommand['UPDATED'] ?: 'ещё не приходило') ?></span>
                </div>
            </div>
            <p class="md-field__hint">Когда устройство присылает новое значение, модуль записывает его в свойство объекта. Когда свойство объекта меняется из сценария или панели, модуль ставит команду устройству в очередь. Повтор одного и того же значения команду не отправляет.</p>
            <?= $linkedControl ?>
            <div class="md-ui-form-actions">
                <?= MDJUI::button('Сохранить привязку', array('type' => 'submit', 'icon' => 'check')) ?>
                <?= MDJUI::button('К свойствам', array('href' => $url('', (int)$bindingCommand['DEVICE_ID'], 'data'), 'variant' => 'ghost')) ?>
                <?php if ($bound): ?>
                    <?= MDJUI::button('Снять привязку', array('type' => 'submit', 'variant' => 'secondary', 'icon' => 'link-off', 'attrs' => array('name' => 'operation', 'value' => 'delete_binding', 'data-md-confirm' => 'Снять привязку свойства? Свойство объекта останется, но перестанет обновляться.'))) ?>
                <?php endif; ?>
            </div>
        </form>
        <hr class="md-xiaomi-divider">
        <form method="post" class="md-xiaomi-inline-form">
            <?php $hidden(array('operation' => 'delete_command', 'command_id' => $bindingCommand['ID'])); ?>
            <?= MDJUI::button('Удалить свойство из модуля', array('type' => 'submit', 'variant' => 'danger', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('data-md-confirm' => 'Удалить строку свойства? При следующем опросе устройство может создать её заново.'))) ?>
        </form>
    <?php }, array('icon' => 'link')); ?>

<?php elseif ($tab === 'gwzigbee'): ?>
    <div class="md-xiaomi-zigbee" data-md-xiaomi-zigbee data-device-id="<?= (int)$device['ID'] ?>">
        <?= MDJUI::alert('info', 'Zigbee-сеть шлюза', 'Список берётся напрямую из шлюза. Привязка и отвязка выполняются командами шлюза; после сопряжения устройства появятся в списке Mi Home.') ?>
        <div class="md-xiaomi-tool-row">
            <?= MDJUI::button('Загрузить список', array('variant' => 'secondary', 'icon' => 'refresh', 'attrs' => array('data-md-xiaomi-zigbee-load' => true))) ?>
            <?= MDJUI::button('Разрешить сопряжение (60 с)', array('variant' => 'secondary', 'icon' => 'plus', 'attrs' => array('data-md-xiaomi-zigbee-join' => true))) ?>
        </div>
        <div class="md-table__scroll">
            <table class="md-table">
                <thead><tr><th scope="col">Устройство</th><th scope="col">ID</th><th scope="col">Модель</th><th scope="col">Статус</th><th scope="col" class="text-end" aria-label="Действия"></th></tr></thead>
                <tbody data-md-xiaomi-zigbee-body>
                <tr><td colspan="5"><span class="md-table__signal">Нажмите «Загрузить список», чтобы запросить устройства у шлюза.</span></td></tr>
                </tbody>
            </table>
        </div>
        <p class="md-xiaomi-log" data-md-xiaomi-zigbee-log aria-live="polite"></p>
    </div>

<?php elseif ($tab === 'help'): ?>
    <?= MDJUI::alert('info', 'Справка по модели', 'Описание метрик и команд для этой модели. Значения и подписи приведены к тем, что используются в карточке устройства.') ?>
    <div class="md-xiaomi-help">
        <?= $helpHtml ?>
    </div>
<?php endif; ?>
