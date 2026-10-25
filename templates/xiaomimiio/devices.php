<?php
/** Список устройств miIO. */
if (!isset($devices, $stats, $url)) {
    http_response_code(404);
    return;
}
$listReturn = $url('list');
$listQuery = http_build_query(array_filter(array(
    'q' => $search,
    'sort' => $sort !== 'title' ? $sort : '',
    'page' => $page > 1 ? $page : '',
), static fn($value) => $value !== '' && $value !== null));
if ($listQuery !== '') $listReturn .= '?' . $listQuery;
$unit = static fn($n, $one, $few, $many) => MDJUI::plural((int)$n, $one, $few, $many);
?>
<div class="md-ui-kpi-grid">
    <?= MDJUI::kpi(array('label' => 'Устройства', 'value' => $stats['devices'], 'icon' => 'cpu', 'meta' => 'всего в системе')) ?>
    <?= MDJUI::kpi(array('label' => 'Онлайн', 'value' => $stats['online'], 'icon' => 'wifi', 'tone' => 'success', 'meta' => $stats['devices'] ? 'отвечают на опрос' : 'нет устройств')) ?>
    <?= MDJUI::kpi(array('label' => 'Офлайн', 'value' => $stats['offline'], 'icon' => 'wifi-off', 'tone' => $stats['offline'] ? 'warning' : 'neutral', 'meta' => 'не ответили на последний запрос')) ?>
    <?= MDJUI::kpi(array('label' => 'Привязки', 'value' => $stats['bindings'], 'icon' => 'link', 'meta' => 'свойств связано с объектами')) ?>
    <?= MDJUI::kpi(array('label' => 'Очередь', 'value' => $stats['queue'], 'icon' => 'list-check', 'tone' => $stats['queue'] > 50 ? 'warning' : 'neutral', 'meta' => $stats['queue'] ? 'команд ждут отправки' : 'пусто')) ?>
</div>

<?php if (!$stats['cycle_running']): ?>
    <?php
    $cycleHint = 'Опрос и очередь работают только при запущенном цикле cycle_xiaomimiio. ';
    $cycleHint .= $stats['cycle_age'] === null
        ? 'След цикла ещё не найден. '
        : 'Последний отклик ' . (int)$stats['cycle_age'] . ' с назад. ';
    $cycleHint .= 'Проверьте его в X-Ray или запустите командой php majordomo cycles:start xiaomimiio.';
    echo MDJUI::alert('warning', 'Цикл модуля не запущен', $cycleHint, array(
        'actions' => MDJUI::button('Открыть X-Ray', array('href' => ROOTHTML . 'panel/xray.html', 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'activity')),
    ));
    ?>
<?php endif; ?>

<section class="md-table-wrap" aria-label="Устройства Xiaomi miIO" data-md-xiaomi-list>
    <form method="get" action="<?= $e($url('list')) ?>" class="md-table__toolbar">
        <div class="md-table__search">
            <?= MDJUI::icon('search') ?>
            <input type="search" name="q" value="<?= $e($search) ?>" placeholder="Название, IP или модель" aria-label="Поиск устройств" autocomplete="off">
        </div>
        <select class="md-table__filter" name="sort" aria-label="Сортировка">
            <?php foreach (array('title' => 'По названию', 'new' => 'Сначала новые', 'online' => 'Сначала онлайн', 'ip' => 'По IP') as $key => $label): ?>
                <option value="<?= $e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?= MDJUI::button('Найти', array('type' => 'submit', 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'search')) ?>
        <?php if ($search !== ''): ?><?= MDJUI::button('Сбросить', array('href' => $url('list'), 'variant' => 'ghost', 'size' => 'sm')) ?><?php endif; ?>
        <span class="md-table__meta">Найдено: <?= (int)$count ?></span>
    </form>

    <?php if (!$devices): ?>
        <?= MDJUI::emptyState(array(
            'icon' => 'cpu',
            'title' => $search !== '' ? 'Устройства не найдены' : 'Устройств пока нет',
            'text' => $search !== ''
                ? 'Измените запрос или сбросьте поиск.'
                : 'Добавьте устройство вручную по IP и токену или найдите устройства в локальной сети широковещательным запросом.',
            'action' => MDJUI::button('Добавить устройство', array('href' => $url('add'), 'icon' => 'plus'))
                . MDJUI::button('Как получить токен', array('href' => $url('tokens'), 'variant' => 'secondary', 'icon' => 'key')),
        )) ?>
    <?php else: ?>
        <div class="md-table__scroll">
            <table class="md-table">
                <thead>
                <tr>
                    <th scope="col">Устройство</th>
                    <th scope="col">Модель</th>
                    <th scope="col">IP и токен</th>
                    <th scope="col">Доступность</th>
                    <th scope="col">Опрос</th>
                    <th scope="col">Последний ответ</th>
                    <th scope="col" class="text-end" aria-label="Действия"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($devices as $row):
                    $model = (string)$row['DEVICE_TYPE'];
                    $state = $statusOf($row, (string)($row['ONLINE'] ?? ''));
                    $hasToken = trim((string)$row['TOKEN']) !== '';
                    ?>
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <?php $deviceImage($model); ?>
                                <div>
                                    <a href="<?= $e($url('', (int)$row['ID'])) ?>"><strong><?= $e($row['TITLE'] ?: 'Без названия') ?></strong></a>
                                    <small><?= (int)$row['COMMANDS'] ?> <?= $unit($row['COMMANDS'], 'свойство', 'свойства', 'свойств') ?><?= $row['SERIAL'] ? ' · ' . $e($row['SERIAL']) : '' ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="md-table__name">
                                <div>
                                    <strong><?= $e(XiaomiMiioCatalog::title($model)) ?></strong>
                                    <?php if ($model !== ''): ?><small><?= $e($model) ?></small><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="md-xiaomi-cell">
                                <span class="font-monospace"><?= $e($row['IP'] ?: 'IP не задан') ?></span>
                                <?php if ($hasToken): ?>
                                    <?= MDJUI::badge('токен задан', 'success', array('icon' => 'key')) ?>
                                <?php else: ?>
                                    <?= MDJUI::badge('нет токена', 'warning', array('icon' => 'key-off')) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= MDJUI::status($state, $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Нет данных')) ?></td>
                        <td>
                            <span class="md-table__signal"><?= (int)$row['UPDATE_PERIOD'] > 0 ? (int)$row['UPDATE_PERIOD'] . ' с' : 'выключен' ?></span>
                            <?php if ((int)($row['FAILURES'] ?? 0) >= 3): ?>
                                <?= MDJUI::badge('ошибок: ' . (int)$row['FAILURES'], 'warning', array('attrs' => array('title' => 'После трёх молчаний опрос замедляется автоматически'))) ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="md-table__signal"><?= $e($row['LAST_SEEN'] ?: ($row['LAST_REPLY'] ?: '—')) ?></span></td>
                        <td>
                            <div class="md-table__actions">
                                <form method="post" class="md-xiaomi-inline-form">
                                    <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $row['ID'], 'return_url' => $listReturn)); ?>
                                    <?= MDJUI::button('', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'refresh', 'attrs' => array('title' => 'Опросить устройство', 'aria-label' => 'Опросить ' . $row['TITLE']))) ?>
                                </form>
                                <?= MDJUI::button('', array('href' => $url('', (int)$row['ID']), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'arrow-right', 'attrs' => array('title' => 'Открыть устройство', 'aria-label' => 'Открыть ' . $row['TITLE']))) ?>
                                <form method="post" class="md-xiaomi-inline-form">
                                    <?php $hidden(array('operation' => 'delete_device', 'device_id' => $row['ID'])); ?>
                                    <?= MDJUI::button('', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('title' => 'Удалить устройство', 'aria-label' => 'Удалить ' . $row['TITLE'], 'data-md-confirm' => 'Удалить «' . $row['TITLE'] . '» вместе с привязками? Объекты и сценарии останутся без изменений.'))) ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="md-table__footer">
            <span>Показано <?= $count ? (($page - 1) * 40 + 1) : 0 ?>–<?= min($count, $page * 40) ?> из <?= (int)$count ?></span>
            <?php if ($pages > 1): ?>
                <nav aria-label="Страницы устройств">
                    <ul class="pagination md-pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <?php $prev = $url('list') . '?' . http_build_query(array('q' => $search, 'sort' => $sort, 'page' => max(1, $page - 1))); ?>
                            <?php if ($page > 1): ?><a class="page-link" href="<?= $e($prev) ?>" aria-label="Предыдущая страница"><?= MDJUI::icon('chevron-left') ?></a><?php else: ?><span class="page-link"><?= MDJUI::icon('chevron-left') ?></span><?php endif; ?>
                        </li>
                        <li class="page-item active"><span class="page-link" aria-current="page"><?= (int)$page ?> из <?= (int)$pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <?php $next = $url('list') . '?' . http_build_query(array('q' => $search, 'sort' => $sort, 'page' => min($pages, $page + 1))); ?>
                            <?php if ($page < $pages): ?><a class="page-link" href="<?= $e($next) ?>" aria-label="Следующая страница"><?= MDJUI::icon('chevron-right') ?></a><?php else: ?><span class="page-link"><?= MDJUI::icon('chevron-right') ?></span><?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<div class="md-xiaomi-notes">
    <?= MDJUI::hint(array(
        'title' => 'Как это работает',
        'points' => array(
            'Модуль общается с устройством напрямую по UDP 54321 — облако Xiaomi не нужно.',
            'Значения свойств пишутся в привязанные свойства объектов MajorDoMo; изменение свойства объекта отправляет команду устройству.',
            'Если устройство не отвечает три раза подряд, опрос автоматически замедляется до 10 минут, чтобы не забивать сеть.',
        ),
        'dismissible' => false,
    )) ?>
</div>
