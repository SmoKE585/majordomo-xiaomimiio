<?php
/**
 * Каталог моделей и подписи свойств miIO.
 *
 * Модуль исторически знал десятки моделей, но в интерфейсе они выглядели как
 * технические строки вида `zhimi.airpurifier.ma2`, а свойства — как `temp_dec`
 * и `filter1_life`. Здесь собраны человеческие названия моделей, подписи,
 * единицы измерения и ссылка на спецификацию MIoT. Это только представление:
 * движок устройств по-прежнему работает с исходными ключами miIO.
 *
 * 2026-10-25 — каталог добавлен при переводе модуля на новый интерфейс.
 */
final class XiaomiMiioCatalog
{
    /** Человеческие названия моделей. Ключ — поле DEVICE_TYPE. */
    public const TITLES = array(
        '090615.switch.xswitch03' => 'Mi PTX ThreeKey Switch',
        'cgllc.airmonitor.b1' => 'ClearGrass Air Monitor B1',
        'cgllc.airmonitor.s1' => 'ClearGrass Air Monitor S1',
        'chuangmi.ir.v2' => 'Mi IR Remote 360',
        'chuangmi.plug.212a01' => 'Mi Smart Plug 2 (BLE)',
        'chuangmi.plug.m1' => 'Mi Smart Socket Plug 2',
        'chuangmi.plug.v1' => 'Mi Smart Socket Plug with USB',
        'chuangmi.plug.v3' => 'Mi Smart Socket Plug with 2 USB',
        'deerma.humidifier.jsq' => 'Mi Smart Sterilization Humidifier',
        'deerma.humidifier.jsq1' => 'Mi Smart Sterilization Humidifier S',
        'deerma.humidifier.jsq2g' => 'Mi Smart Antibacterial Humidifier',
        'deerma.humidifier.jsq5' => 'Mi Smart Antibacterial Humidifier (jsq5)',
        'deerma.humidifier.mjjsq' => 'Mi Air Humidifier MJJSQ',
        'dmaker.airfresh.a1' => 'Mi Fresh Air Ventilator A1-150',
        'dreame.vacuum.mc1808' => 'Mi Robot Vacuum-Mop 1C',
        'dreame.vacuum.p2140' => 'Dreame Robot Vacuum (p2140)',
        'dreame.vacuum.p2140p' => 'Mi Robot Vacuum-Mop 2',
        'hfjh.fishbowl.v1' => 'Mi Smart Fish Tank',
        'isa.camera.df3' => 'Mi Dafang Smart Camera 120',
        'isa.camera.hlc7' => 'Mi 360° Home Camera 1080p',
        'isa.camera.isc5' => 'Mi Square Smart Camera',
        'loock.cateye.v01' => 'Loock CatY Smart Cat Eye',
        'lumi.acpartner.v3' => 'Aqara AC Companion Gateway',
        'lumi.camera.aq1' => 'Aqara Smart Camera Gateway',
        'lumi.gateway.v3' => 'Mi Smart Home Gateway 2',
        'mmgg.pet_waterer.s1' => 'Mijia Smart Pet Water Dispenser',
        'mrbond.airer.m1pro' => 'Mr. Bond M1 Pro Airer',
        'philips.light.bulb' => 'Philips Light Bulb',
        'philips.light.candle' => 'Philips Rui Chi Candle Light',
        'philips.light.ceiling' => 'Philips EyeCare Smart Ceiling Lamp',
        'philips.light.downlight' => 'Philips Zhirui Downlight',
        'philips.light.sread1' => 'Philips EyeCare Smart Desk Lamp 2',
        'qmi.powerstrip.v1' => 'Mi Smart Power Strip 5 Plugs',
        'roborock.vacuum.m1s' => 'Roborock M1S',
        'roborock.vacuum.s5' => 'Roborock S50/S51',
        'rockrobo.vacuum.v1' => 'Mi Vacuum Cleaner',
        'uvfive.s_lamp.slmap2' => 'Mi Five Disinfection Lamp',
        'viomi.vacuum.v7' => 'Mi Robot Vacuum-Mop P',
        'xiaomi.repeater.v2' => 'Mi WiFi Amplifier 2',
        'xiaomi.repeater.v3' => 'Mi WiFi Amplifier 3',
        'xiaomi.repeater.v6' => 'Mi WiFi Range Extender AC1200',
        'xiaomi.wifispeaker.v1' => 'Mi Internet Speaker',
        'yeelink.light.bslamp1' => 'Yeelight Bedside Lamp',
        'yeelink.light.ceiling1' => 'Yeelight Ceiling Light',
        'yeelink.light.ceiling3' => 'Yeelight Bright Moon Ceiling',
        'yeelink.light.ceiling4' => 'Yeelight Jiaoyue 650 Ceiling',
        'yeelink.light.ceiling8' => 'Yeelight Square Ceiling Light',
        'yeelink.light.color1' => 'Yeelight Color Bulb',
        'yeelink.light.lamp1' => 'Mi LED Desk Lamp',
        'yeelink.light.lamp2' => 'Mi LED Desk Lamp 1S',
        'yeelink.light.lamp4' => 'Mi LED Desk Lamp 1S (lamp4)',
        'yeelink.light.mono1' => 'Yeelight White Bulb',
        'yeelink.light.strip1' => 'Yeelight LED Lightstrip',
        'yunmi.kettle.r1' => 'Mi Heating Water Dispenser MINI',
        'yunmi.kettle.r2' => 'Mi Heating Water Dispenser MINI',
        'yunmi.kettle.r3' => 'Mi Heating Water Dispenser 4A',
        'zhimi.airpurifier.ma2' => 'Mi Air Purifier 2S',
        'zhimi.airpurifier.mb3' => 'Mi Air Purifier 3/3H',
        'zhimi.airpurifier.mb4' => 'Mi Air Purifier 3 (mb4)',
        'zhimi.airpurifier.v3' => 'Mi Air Purifier',
        'zhimi.fan.sa1' => 'Mi Smart Fan SA1',
        'zhimi.heater.mc2' => 'Smartmi Convector Heater 1S',
        'zhimi.heater.zb1a' => 'Smartmi Smart Heater (zb1a)',
        'zhimi.humidifier.ca1' => 'Mi Air Humidifier 2',
        'zhimi.humidifier.ca4' => 'Mi Air Humidifier 2 Display',
        'zhimi.humidifier.v1' => 'Mi Air Humidifier',
        'zimi.powerstrip.v2' => 'Mi Smart Power Strip 6 Plugs',
    );

    /**
     * Подписи свойств. Ключи совпадают с TITLE в miio_commands, поэтому таблица
     * свойств остаётся понятной и для новых, и для старых установок.
     */
    private const LABELS = array(
        'online' => 'Доступность',
        'command' => 'Команда',
        'message' => 'Ответ устройства',
        'power' => 'Питание',
        'on' => 'Включено',
        'bright' => 'Яркость',
        'brightness' => 'Яркость',
        'ct' => 'Цветовая температура',
        'cct' => 'Цветовая температура',
        'rgb' => 'Цвет RGB',
        'hue' => 'Оттенок',
        'sat' => 'Насыщенность',
        'color_mode' => 'Режим цвета',
        'nl_br' => 'Ночная подсветка',
        'flow_params' => 'Параметры сценария',
        'flowing' => 'Сценарий включён',
        'temperature' => 'Температура',
        'temp_dec' => 'Температура',
        'humidity' => 'Влажность',
        'aqi' => 'Индекс качества воздуха',
        'average_aqi' => 'Средний AQI',
        'pm25' => 'PM2.5',
        'tvoc' => 'TVOC',
        'co2' => 'CO₂',
        'battery' => 'Батарея',
        'battery_state' => 'Состояние батареи',
        'mode' => 'Режим',
        'favorite_level' => 'Уровень «Любимый»',
        'filter1_life' => 'Ресурс фильтра',
        'filter_rate' => 'Загрязнение фильтра',
        'filter_day' => 'Дней до замены фильтра',
        'use_time' => 'Время работы',
        'purify_volume' => 'Очищено воздуха',
        'led' => 'Подсветка',
        'led_b' => 'Подсветка',
        'led_brightness' => 'Яркость подсветки',
        'buzzer' => 'Звук',
        'child_lock' => 'Блокировка от детей',
        'wifi_led' => 'Индикатор Wi-Fi',
        'usb_on' => 'USB-питание',
        'current' => 'Ток',
        'voltage' => 'Напряжение',
        'power_consume_rate' => 'Расход энергии',
        'load_power' => 'Мощность нагрузки',
        'temperature_outside' => 'Температура снаружи',
        'depth' => 'Уровень воды',
        'speed' => 'Скорость',
        'speed_level' => 'Уровень скорости',
        'natural_level' => 'Естественный ветер',
        'angle' => 'Угол поворота',
        'dry' => 'Сушка',
        'limit_hum' => 'Целевая влажность',
        'target_humidity' => 'Целевая влажность',
        'water_level' => 'Уровень воды',
        'waterstatus' => 'Наличие воды',
        'watertankstatus' => 'Бак установлен',
        'water_shortage_fault' => 'Нет воды',
        'the_tank_filed' => 'Бак заполнен',
        'volume' => 'Громкость',
        'rel_time' => 'Время трека',
        'umi' => 'Сцена голоса',
        'water_remain_time' => 'Осталось воды',
        'flush_time' => 'Время кипячения',
        'tds' => 'Качество воды (TDS)',
        'curr_tempe' => 'Текущая температура',
        'setup_tempe' => 'Заданная температура',
        'run_status' => 'Состояние',
        'work_mode' => 'Режим работы',
        'drink_remind' => 'Напоминание о воде',
        'ir_play' => 'ИК-команда',
        'arming_mode' => 'Охрана',
        'zigbee_channel' => 'Канал Zigbee',
        'lumi_dpf_aes_key' => 'Ключ LAN-режима',
        'add_program' => 'Добавить радиостанцию',
        'del_program' => 'Удалить радиостанцию',
        'current_program' => 'Текущая радиостанция',
        'current_progress' => 'Прогресс воспроизведения',
        'current_volume' => 'Громкость радио',
        'current_status' => 'Статус радио',
        'all_program' => 'Список радиостанций',
        'goto_target' => 'Точка уборки',
        'zoned_clean' => 'Зональная уборка',
        'segment_clean' => 'Уборка по комнатам',
        'run_state' => 'Состояние уборки',
        'err_state' => 'Ошибка',
        'battary_life' => 'Заряд батареи',
        'box_type' => 'Тип контейнера',
        's_time' => 'Время уборки',
        's_area' => 'Площадь уборки',
        'suction_grade' => 'Мощность всасывания',
        'water_grade' => 'Подача воды',
        'vol_up' => 'Громче',
        'vol_down' => 'Тише',
        'auto_feed' => 'Автокормление',
        'single_feed' => 'Порция корма',
        'switch_all' => 'Все выключатели',
        'Equipment_Status' => 'Состояние оборудования',
        'feed_switch' => 'Кормушка',
        'heater_switch' => 'Обогреватель',
        'led_board_brightness' => 'Яркость подсветки',
        'led_board_color' => 'Цвет подсветки',
        'led_board_model' => 'Режим подсветки',
        'led_board_speed' => 'Скорость подсветки',
        'pump_switch' => 'Помпа',
        'pump_value' => 'Производительность помпы',
        'water_tds' => 'Качество воды (TDS)',
        'water_temp' => 'Температура воды',
        'water_temp_value' => 'Целевая температура воды',
        'OnOff_State' => 'Питание',
        'TemperatureValue' => 'Температура',
        'Humidity_Value' => 'Влажность',
        'HumiSet_Value' => 'Целевая влажность',
        'Humidifier_Gear' => 'Скорость увлажнения',
        'Led_State' => 'Подсветка',
        'TipSound_State' => 'Звук',
        'fault' => 'Неисправность',
        'fan_level' => 'Скорость вентилятора',
        'relative_humidity' => 'Влажность',
        'power_time' => 'Время включения',
        'clean_mode' => 'Режим очистки',
        'fahrenheit' => 'Шкала Фаренгейта',
        'button_pressed' => 'Кнопка нажата',
        'actual_speed' => 'Фактическая скорость',
        'no-water-time' => 'Время без воды',
        'pump-block-flag' => 'Блокировка помпы',
        'lid-up-flag' => 'Крышка открыта',
        'remain-clean-time' => 'До очистки',
        'cotton-left-time' => 'Ресурс ваты',
        'filter-left-time' => 'Ресурс фильтра',
        'water' => 'Вода',
    );

    private const UNITS = array(
        'temperature' => '°C',
        'temp_dec' => '°C',
        'temperature_outside' => '°C',
        'curr_tempe' => '°C',
        'setup_tempe' => '°C',
        'water_temp' => '°C',
        'water_temp_value' => '°C',
        'humidity' => ' %',
        'relative_humidity' => ' %',
        'limit_hum' => ' %',
        'target_humidity' => ' %',
        'depth' => ' %',
        'bright' => ' %',
        'brightness' => ' %',
        'volume' => ' %',
        'battery' => ' %',
        'battary_life' => ' %',
        'filter1_life' => ' %',
        'filter_rate' => ' %',
        'co2' => ' ppm',
        'tds' => ' ppm',
        'water_tds' => ' ppm',
        'pm25' => ' мкг/м³',
        'tvoc' => ' мг/м³',
        'purify_volume' => ' м³',
        'power_consume_rate' => ' кВт·ч',
        'current' => ' мА',
        'voltage' => ' В',
        'load_power' => ' Вт',
        'angle' => '°',
        'water_remain_time' => ' с',
        'flush_time' => ' с',
        's_time' => ' мин',
        's_area' => ' м²',
    );

    private const GATEWAYS = array('lumi.gateway.v3', 'lumi.acpartner.v3');
    private const IR_DEVICES = array('lumi.acpartner.v3', 'chuangmi.ir.v2');
    private const LAN_KEY_DEVICES = array('lumi.gateway.v3', 'lumi.acpartner.v3');

    public static function models(): array
    {
        return self::TITLES;
    }

    public static function normalize(string $model): string
    {
        $model = trim($model);
        return preg_match('/^[a-z0-9_.-]{3,120}$/i', $model) ? $model : '';
    }

    public static function title(string $model): string
    {
        $model = trim($model);
        if ($model === '') return 'Модель не определена';
        return self::TITLES[$model] ?? $model;
    }

    public static function isKnown(string $model): bool
    {
        return isset(self::TITLES[trim($model)]);
    }

    /** Варианты для селекта: сначала известные, затем текущая неизвестная модель. */
    public static function options(string $current = ''): array
    {
        $options = self::TITLES;
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $current = trim($current);
        if ($current !== '' && !isset($options[$current])) {
            $options = array($current => 'Неизвестная модель — ' . $current) + $options;
        }
        return $options;
    }

    public static function imageFile(string $model): ?string
    {
        $model = trim($model);
        if ($model === '' || !preg_match('/^[a-z0-9_.-]+$/i', $model)) return null;
        $file = ROOT . 'public/assets/modules/xiaomimiio/img/small/' . $model . '.png';
        return is_file($file) ? $file : null;
    }

    public static function imageUrl(string $model): ?string
    {
        if (!self::imageFile($model)) return null;
        return ROOTHTML . 'assets/modules/xiaomimiio/img/small/' . rawurlencode(trim($model)) . '.png';
    }

    public static function specUrl(string $model): string
    {
        return 'https://home.miot-spec.com/spec/' . rawurlencode(trim($model));
    }

    public static function label(string $key): string
    {
        if (isset(self::LABELS[$key])) return self::LABELS[$key];
        $readable = str_replace(array('_', '-'), ' ', $key);
        $readable = preg_replace('/(?<=[a-zа-я])(?=[A-ZА-Я])/u', ' ', $readable);
        return mb_convert_case(trim($readable), MB_CASE_TITLE, 'UTF-8');
    }

    public static function unit(string $key): string
    {
        return self::UNITS[$key] ?? '';
    }

    /** Значение для показа: единицы добавляются только к числам. */
    public static function display(string $key, $value): string
    {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Да' : 'Нет';
        if (is_array($value)) return self::shortJson($value);
        $text = trim((string)$value);
        $unit = self::unit($key);
        if ($unit !== '' && is_numeric($text)) return $text . $unit;
        return $text;
    }

    public static function shortJson($value, int $limit = 300): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) return '—';
        return mb_strlen($json) > $limit ? mb_substr($json, 0, $limit) . '…' : $json;
    }

    public static function isGateway(string $model): bool
    {
        return in_array(trim($model), self::GATEWAYS, true);
    }

    public static function hasIr(string $model): bool
    {
        return in_array(trim($model), self::IR_DEVICES, true);
    }

    public static function supportsLanKey(string $model): bool
    {
        return in_array(trim($model), self::LAN_KEY_DEVICES, true);
    }

    /**
     * Служебные свойства, которые модуль создаёт при сохранении модели.
     * Список перенесён из старого обработчика формы без изменений состава.
     */
    public static function extraCommands(string $model): array
    {
        $commands = array('online', 'command', 'message');
        switch (trim($model)) {
            case 'lumi.gateway.v3':
                $commands[] = 'add_program';
                $commands[] = 'del_program';
                break;
            case 'lumi.acpartner.v3':
                $commands = array_merge($commands, array('add_program', 'del_program', 'ir_play', 'power', 'load_power'));
                break;
            case 'chuangmi.ir.v2':
                $commands[] = 'ir_play';
                break;
            case 'xiaomi.wifispeaker.v1':
                $commands[] = 'vol_up';
                $commands[] = 'vol_down';
                break;
            case 'rockrobo.vacuum.v1':
            case 'roborock.vacuum.s5':
                $commands[] = 'goto_target';
                $commands[] = 'zoned_clean';
                if (trim($model) === 'roborock.vacuum.s5') $commands[] = 'segment_clean';
                break;
            case 'hfjh.fishbowl.v1':
                $commands[] = 'auto_feed';
                $commands[] = 'single_feed';
                break;
            case '090615.switch.xswitch03':
                $commands[] = 'switch_all';
                break;
        }
        return array_values(array_unique($commands));
    }
}
