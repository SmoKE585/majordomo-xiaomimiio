<?php
/**
 * Цикл модуля Xiaomi miIO.
 *
 * Скрипт намеренно тонкий: поднимает рантайм и отдаёт один шаг работы модулю
 * (`cycleTick()`). Вся логика очереди, опроса и поиска устройств живёт в
 * modules/xiaomimiio, поэтому её можно менять без перезапуска формата цикла.
 *
 * 2026-10-25 — обработка ответов и поиск устройств выполняются в этом же
 * процессе, без фонового HTTP-запроса к ajax-эндпоинту.
 */

chdir(dirname(__DIR__));

require_once './config.php';
require_once './lib/loader.php';
require_once './load_settings.php';
require_once DIR_MODULES . 'xiaomimiio/xiaomimiio.class.php';

set_time_limit(0);

$cycle_name = str_replace('.php', '', basename(__FILE__));
$module = new xiaomimiio();

echo date('H:i:s') . ' running ' . basename(__FILE__) . PHP_EOL;

$last_beat = 0;
$last_error = '';

while (true) {
    if (time() - $last_beat > 5) {
        $last_beat = time();
        setGlobal($cycle_name . 'Run', time(), 1);
    }

    try {
        $module->cycleTick();
        $last_error = '';
    } catch (Throwable $failure) {
        $message = get_class($failure) . ': ' . $failure->getMessage();
        // Одну и ту же ошибку не пишем каждую секунду — только при смене текста.
        if ($message !== $last_error) {
            $last_error = $message;
            DebMes('xiaomimiio cycle: ' . $message, 'xiaomimiio', 'error');
        }
    }

    if (isRebootRequired() || isset($_GET['onetime'])) {
        echo date('H:i:s') . ' stopping ' . basename(__FILE__)
            . (isset($_GET['onetime']) ? ' (onetime)' : ' (reboot)') . PHP_EOL;
        break;
    }

    sleep(1);
}
