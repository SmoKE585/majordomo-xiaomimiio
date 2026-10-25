---
title: Что берём из Home Assistant
audience: agent
category: module-xiaomimiio
tags: home assistant, miot-spec, облако, oauth, push, roadmap, сравнение
description: Сравнение интеграций Xiaomi для Home Assistant и практический план развития модуля: что уже сделано, что дальше и почему.
order: 60
---

# Что берём из Home Assistant

При переписывании модуля мы смотрели, как задача решена в Home Assistant. Ниже
честное сравнение и план: что уже внедрено, что сознательно отложено.

## Как это устроено в HA

| Интеграция | Транспорт | Состояние | Токены |
| --- | --- | --- | --- |
| `xiaomi_miio` (python-miio) | miIO UDP | опрос, 15 с для суб-устройств | аккаунт `micloud` или вручную |
| `xiaomi_home` (Xiaomi) | облако + miIO LAN + MQTT шлюза | push, OAuth2 | OAuth2, refresh-токен |
| `hass-xiaomi-miot` | miIO LAN + облако | опрос 60 с | аккаунт или вручную |
| `XiaomiGateway3` | MQTT шлюза | push, TTL доступности | аккаунт для ключей |

Ссылки: [xiaomi_miio](https://www.home-assistant.io/integrations/xiaomi_miio/),
[XiaoMi/ha_xiaomi_home](https://github.com/XiaoMi/ha_xiaomi_home),
[al-one/hass-xiaomi-miot](https://github.com/al-one/hass-xiaomi-miot),
[AlexxIT/XiaomiGateway3](https://github.com/AlexxIT/XiaomiGateway3),
[MIoT-Spec-V2](https://iot.mi.com/v2/new/doc/introduction/knowledge/spec).

Главный сдвиг в HA: не «модель → список свойств в коде», а **спецификация
MIoT** (urn, services/properties/actions/events) как источник сущностей. Второй
сдвиг — **облачная авторизация** вместо ручного извлечения токенов.

## Что уже сделано в этой версии

- **Каталог моделей и подписи свойств** (`Catalog.php`): человеческие названия,
  единицы и ссылка на спецификацию MIoT вместо технических ключей.
- **Планировщик с разбросом и backoff**: после трёх ошибок интервал растёт до
  10 минут, успех сбрасывает счётчик. Это аналог `_local_fails` и
  `last_update_success`.
- **Явные статусы**: `online` — отдельное свойство, ошибки видны в карточке,
  предупреждение пишется один раз на третью ошибку.
- **Очередь с учётом времени**: отложенные команды больше не отправляются
  сразу (раньше `ADDED` игнорировался).
- **Диагностика без магии**: `miIO.info`, произвольный метод, сырой ответ,
  очередь — всё в карточке устройства.
- **Безопасный импорт токенов**: скрипт запускается у пользователя, пароль не
  попадает на сервер.

## План: P0

1. **MIoT-Spec как источник свойств.** Добавить `SpecRepository`: модель → urn
   из облачного `device_list`, запрос
   `https://miot-spec.org/miot-spec-v2/instance?type=<urn>`, кэш с TTL,
   fallback на текущие списки `Constants.php`. Это снимет жёсткие `define()` и
   даст единицы, допустимые значения и actions без правок кода.
2. **Облачный мастер токенов.** Порт логики `xiaomi_cloud.py`: serviceLogin →
   `home/device_list` с токеном, IP, MAC, моделью и `parent_id`. Токены хранить
   в БД в зашифрованном виде, ключ — из `.env`. Это заменит `token_extractor.py`.
3. **Единая модель «свойство → объект»** с типами и enum-преобразованием, как
   `MiotProperty` у `hass-xiaomi-miot`. Сейчас преобразование делает движок
   устройства; универсальный слой упростит новые модели.

## План: P1

- Универсальные `set_properties` пачками и actions с проверкой range/value-list.
- Повторное обнаружение IP/токена после серии ошибок (аналог
  `async_refresh_local_device`).
- Кнопки действий из спецификации: `app_start`, `find_me`, `reset-filter-life`.
- Отдельная таблица `miot_map` для ручного переопределения имён и масштабов.

## План: P2

- Push через miIO subscribe (локальная подписка) вместо только опроса.
- BLE-устройства и bindkey.
- Карта уборки пылесоса как вложение, а не свойство.
- Адаптер MQTT шлюза и TTL-доступность, как в `XiaomiGateway3`.

## Чего не делаем

- Не переносим логику в ядро MajorDoMo: модуль остаётся модулем.
- Не храним пароль Xiaomi на сервере без шифрования и явного согласия.
- Не тянем python-зависимости: всё, что можно, остаётся на PHP.
