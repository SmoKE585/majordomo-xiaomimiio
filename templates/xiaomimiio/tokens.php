<?php
/** Токены: инструкция, импорт вывода token_extractor и устройства без токена. */
if (!isset($noTokenDevices)) {
    http_response_code(404);
    return;
}
?>
<?= MDJUI::alert('info', 'Пароль Xiaomi остаётся у вас', 'Модуль не просит и не хранит пароль аккаунта. Скрипт получения токенов запускается на вашем компьютере, а на сервер попадает только его вывод: IP, модель и токен устройства.') ?>

<div class="md-xiaomi-two-col">
    <?php $card('Как получить токен', static function (): void { ?>
        <?= MDJUI::hint(array(
            'title' => 'Порядок действий',
            'steps' => array(
                'Скачайте token_extractor.py — это официальный скрипт проекта python-miio.',
                'На компьютере с Python 3 установите зависимость: python3 -m pip install requests.',
                'Запустите скрипт с логином и паролем Xiaomi, указав свой регион: python3 token_extractor.py --username ВАШ_ЛОГИН --password ВАШ_ПАРОЛЬ --server ru. Регионы: cn, de, us, ru, sg, in, i2.',
                'Скопируйте весь вывод. Для каждого устройства там есть блок new-device и строки {IP}, {TOKEN}, {MODEL}, {NAME}.',
                'Вставьте вывод в поле справа и нажмите «Импортировать». Модуль обновит совпавшие по IP устройства и добавит новые.',
            ),
            'actions' => MDJUI::button('Скачать token_extractor.py', array(
                'href' => ROOTHTML . 'assets/modules/xiaomimiio/tools/token_extractor.py',
                'variant' => 'secondary',
                'icon' => 'download',
                'attrs' => array('download' => 'token_extractor.py'),
            )),
            'dismissible' => false,
        )) ?>
        <p class="md-field__hint">Если аккаунт защищён двухфакторной проверкой, скрипт может не войти. Тогда токен можно взять из резервной копии Mi Home (Android) или из интеграции Home Assistant, где устройство уже добавлено.</p>
    <?php }, array('icon' => 'key')); ?>

    <?php $card('Импорт вывода', static function () use ($hidden): void { ?>
        <form method="post" class="md-ui-form-stack">
            <?php $hidden(array('operation' => 'import_tokens')); ?>
            <?= MDJUI::textarea(array(
                'name' => 'tokens_output',
                'label' => 'Вывод token_extractor.py',
                'rows' => 14,
                'required' => true,
                'class' => 'font-monospace',
                'placeholder' => "new-device\n{MODEL}={zhimi.airpurifier.ma2}\n{NAME}={Air Purifier}\n{IP}={192.168.1.50}\n{TOKEN}={0123456789abcdef0123456789abcdef}",
                'hint' => 'Импорт не удаляет существующие привязки: обновляются только IP, токен, модель и название «New …».',
            )) ?>
            <div class="md-ui-form-actions">
                <?= MDJUI::button('Импортировать', array('type' => 'submit', 'icon' => 'upload')) ?>
                <?= MDJUI::button('К устройствам', array('href' => ROOTHTML . 'panel/xiaomimiio.html', 'variant' => 'ghost')) ?>
            </div>
        </form>
    <?php }, array('icon' => 'upload')); ?>
</div>

<div class="md-xiaomi-two-col">
    <?php $card('Устройства без токена', static function () use ($noTokenDevices): void { ?>
        <?php if (!$noTokenDevices): ?>
            <?= MDJUI::emptyState(array('title' => 'У всех устройств есть токен', 'text' => 'Опрос работает для всех добавленных устройств.', 'compact' => true)) ?>
        <?php else: ?>
            <div class="md-table__scroll">
                <table class="md-table md-table--compact">
                    <thead><tr><th scope="col">Устройство</th><th scope="col">IP</th><th scope="col">Модель</th><th scope="col" class="text-end" aria-label="Действия"></th></tr></thead>
                    <tbody>
                    <?php foreach ($noTokenDevices as $row): ?>
                        <tr>
                            <td><a href="<?= $e(ROOTHTML . 'panel/xiaomimiio/' . (int)$row['ID'] . '.html') ?>"><?= $e($row['TITLE']) ?></a></td>
                            <td class="font-monospace"><?= $e($row['IP']) ?></td>
                            <td><?= $e(XiaomiMiioCatalog::title((string)$row['DEVICE_TYPE'])) ?></td>
                            <td class="text-end"><?= MDJUI::button('Открыть', array('href' => ROOTHTML . 'panel/xiaomimiio/' . (int)$row['ID'] . '.html', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'arrow-right')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php }, array('icon' => 'key-off')); ?>

    <?php $card('Почему не через облако', static function (): void { ?>
        <p>Модуль работает локально и не зависит от доступности серверов Xiaomi. Облачный вход дал бы автоматическое получение токенов и push-события, но потребовал бы хранить пароль или refresh-токен аккаунта на сервере.</p>
        <p class="md-field__hint">Такой путь есть у интеграций Home Assistant:
            <a href="https://www.home-assistant.io/integrations/xiaomi_miio/" target="_blank" rel="noopener">xiaomi_miio</a> (вход через micloud),
            <a href="https://github.com/XiaoMi/ha_xiaomi_home" target="_blank" rel="noopener">Xiaomi Home</a> (OAuth2 и push)
            и <a href="https://github.com/al-one/hass-xiaomi-miot" target="_blank" rel="noopener">hass-xiaomi-miot</a> (MIoT-Spec и облачные токены).
            Мы сознательно оставили ручной токен в этой версии: он не создаёт новой точки отказа и не хранит секреты аккаунта.</p>
        <p class="md-field__hint">Подробнее о токенах — в <a href="https://python-miio.readthedocs.io/en/latest/troubleshooting.html#how-to-get-the-token" target="_blank" rel="noopener">документации python-miio</a>.</p>
    <?php }, array('icon' => 'cloud-off')); ?>
</div>
