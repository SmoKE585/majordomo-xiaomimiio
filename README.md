# Xiaomi miIO для MajorDoMo

Модуль для Wi-Fi устройств Xiaomi / Mijia / Aqara по локальному протоколу
**miIO** (UDP 54321). Без облака Xiaomi и подписок: IP и токен устройства
хранятся в MajorDoMo, обмен идёт напрямую.

Версия **4.0** — переписанный интерфейс и цикл поверх проверенного движка
устройств из 3.2b. Таблицы и привязки сохраняются при обновлении.

## Возможности

- список устройств с поиском, статусом, моделью и быстрыми действиями;
- карточка устройства: настройки, диагностика, свойства, справка;
- свойства miIO и привязка к свойствам/методам объектов MajorDoMo;
- опрос по расписанию с разбросом и backoff после ошибок;
- очередь команд, очистка очереди, прямой запуск метода miIO;
- шлюзы Aqara: радио, сопряжение и отвязка Zigbee, LAN-ключ;
- ИК-обучение для `lumi.acpartner.v3` и `chuangmi.ir.v2`;
- токены: безопасный импорт вывода `token_extractor.py` без передачи пароля;
- чистые URL, тёмная тема, мобильная ширина, `MDJUI`-компоненты;
- документация для «Базы знаний» MajorDoMo.

## Совместимость

- MajorDoMo с новой админкой (`MDJUI`, `md-*`, чистые `/panel/...`), PHP 8.1+.
- Устройства, которые поддерживал модуль 3.2b: Yeelight, Philips, Chuangmi,
  Zhimi, Deerma, Roborock, Viomi, Dreame, Aqara, Mijia и другие. Полный список —
  `modules/xiaomimiio/Catalog.php` и справки в `templates/xiaomimiio/help/ru/`.
- Обновление с 3.x: данные не удаляются, колонки `FAILURES` и `LAST_SEEN`
  добавляются автоматически.

## Установка

1. Скопируйте содержимое репозитория в корень MajorDoMo, сохранив пути:
   `modules/`, `templates/`, `public/assets/`, `scripts/`.
2. Откройте `/panel/xiaomimiio.html`. Модуль зарегистрируется в
   `project_modules`, создаст таблицы и запустит цикл.
3. Проверьте цикл `cycle_xiaomimiio` в X-Ray.
4. Добавьте устройство: IP, токен, при желании модель и период опроса.

Обновление с 3.x — те же шаги: файлы заменяются, таблицы не пересоздаются.
Подробности: [modules/xiaomimiio/docs/upgrade.md](modules/xiaomimiio/docs/upgrade.md).

## Токены

Токен — 32 шестнадцатеричных символа. Получается скриптом
`token_extractor.py` на вашем компьютере; пароль Xiaomi на сервер не
передаётся. Вывод скрипта импортируется на странице «Токены».

Подробно: [modules/xiaomimiio/docs/tokens.md](modules/xiaomimiio/docs/tokens.md).

## Структура

```
modules/xiaomimiio/
  xiaomimiio.class.php   контроллер: страницы, действия, цикл
  Engine.php             trait с движком miIO (опрос, очередь, разбор ответов)
  Constants.php          справочники свойств и кодов состояний
  Catalog.php            названия моделей, подписи и единицы свойств
  Repository.php         доступ к таблицам модуля через Core::database()
  lib/                   протокол miIO (miio.class.php, mipacket.class.php)
  docs/                  документация для «Базы знаний»
templates/xiaomimiio/    PHP-шаблоны (MDJUI) и справки по моделям
public/assets/modules/xiaomimiio/  CSS, JS, изображения, token_extractor.py
public/assets/img/modules/xiaomimiio.png  иконка модуля
scripts/cycle_xiaomimiio.php       тонкий запускатор цикла
```

## Документация

- [Обзор модуля](modules/xiaomimiio/docs/overview.md)
- [Устройства и опрос](modules/xiaomimiio/docs/devices.md)
- [Свойства и привязки](modules/xiaomimiio/docs/bindings.md)
- [Шлюзы Aqara](modules/xiaomimiio/docs/gateways.md)
- [Токены](modules/xiaomimiio/docs/tokens.md)
- [Что берём из Home Assistant](modules/xiaomimiio/docs/roadmap.md)
- [Обновление с 3.x](modules/xiaomimiio/docs/upgrade.md)

## Благодарности

Движок устройств и протокол miIO — Agaphonov Dmitri (skysilver) и участники
модуля 3.2b, библиотека [php-miio](https://github.com/skysilver-lab/php-miio).
Сравнение с Home Assistant — по интеграциям
[`xiaomi_miio`](https://www.home-assistant.io/integrations/xiaomi_miio/),
[`XiaoMi/ha_xiaomi_home`](https://github.com/XiaoMi/ha_xiaomi_home),
[`al-one/hass-xiaomi-miot`](https://github.com/al-one/hass-xiaomi-miot).
