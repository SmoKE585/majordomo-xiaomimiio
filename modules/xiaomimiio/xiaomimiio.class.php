<?php

use MajorDoMo\Core\Core;

require_once __DIR__ . '/Constants.php';
require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Engine.php';

/**
 * Xiaomi miIO — устройства Xiaomi/Mijia/Aqara по локальному протоколу miIO.
 *
 * Модуль работает поверх UDP 54321 и не требует облака: IP и токен устройства
 * хранятся в собственной таблице `miio_devices`, значения и привязки — в
 * `miio_commands`, очередь команд — в `miio_queue`. Движок устройств остался
 * прежним (см. Engine.php), новый слой отвечает за интерфейс, проверку форм,
 * цикл и аккуратную работу с ядром.
 *
 * 2026-10-25 — новый интерфейс на MDJUI и чистых URL, настройки с проверкой,
 * цикл без HTTP-перезапуска, backoff опроса, импорт токенов из вывода
 * token_extractor, диагностика устройства. Старые таблицы и привязки
 * сохраняются; недостающие колонки добавляются при первом открытии.
 */
class xiaomimiio extends module
{
    use XiaomiMiioEngine;

    private $repo = null;
    private $client = null;
    private $msgId = 1;
    private $lastDiscover = 0;
    private static $schemaChecked = false;

    public function __construct()
    {
        $this->name = 'xiaomimiio';
        $this->title = 'Xiaomi miIO';
        $this->module_category = '<#LANG_SECTION_DEVICES#>';
        $this->checkInstalled();
    }

    /**
     * Настройки модуля с значениями по умолчанию.
     *
     * Движок читает ключи API_* напрямую, поэтому массив должен содержать их
     * всегда — даже на свежей установке, где project_modules.DATA ещё пуст.
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        if (!is_array($this->config)) $this->config = array();
        $this->config += array(
            'API_IP' => '',
            'API_DISC_PERIOD' => 120,
            'API_SOCKET_TIMEOUT' => 2,
            'API_LOG_DEBMES' => 0,
            'API_LOG_CYCLE' => 0,
            'API_LOG_MIIO' => 0,
        );
        return $this->config;
    }
    public function getParams()
    {
        global $id, $view_mode, $tab, $mode, $data_source;
        $this->id = isset($id) && is_scalar($id) ? (int)$id : 0;
        $this->view_mode = isset($view_mode) && is_scalar($view_mode) ? (string)$view_mode : '';
        $this->tab = isset($tab) && is_scalar($tab) ? (string)$tab : '';
        $this->mode = isset($mode) && is_scalar($mode) ? (string)$mode : '';
        $this->data_source = isset($data_source) && is_scalar($data_source) ? (string)$data_source : '';
        if ($this->view_mode === 'update_settings') $this->view_mode = 'settings';
        if ($this->view_mode === 'get_tokens') $this->view_mode = 'tokens';
    }

    public function saveParams($data = 0)
    {
        return parent::saveParams(array(
            'id' => (int)$this->id,
            'view_mode' => (string)$this->view_mode,
            'tab' => (string)$this->tab,
        ));
    }

    public function install($parent_name = '')
    {
        parent::install($parent_name);
        try {
            setGlobal('cycle_xiaomimiioControl', 'start');
        } catch (Throwable $failure) {
            // Установка не должна падать из-за отсутствующего объекта ThisComputer.
            DebMes('xiaomimiio: cannot start cycle on install: ' . $failure->getMessage(), 'xiaomimiio', 'warning');
        }
    }

    public function uninstall()
    {
        try {
            setGlobal('cycle_xiaomimiioControl', 'stop');
        } catch (Throwable $failure) {
            DebMes('xiaomimiio: cannot stop cycle on uninstall: ' . $failure->getMessage(), 'xiaomimiio', 'warning');
        }
        $this->deleteAllData();
        parent::uninstall();
    }

    public function dbInstall($data = '')
    {
        $data = <<<EOD
 miio_devices: ID int(10) unsigned NOT NULL auto_increment
 miio_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 miio_devices: IP varchar(255) NOT NULL DEFAULT ''
 miio_devices: TOKEN varchar(255) NOT NULL DEFAULT ''
 miio_devices: MAC varchar(255) NOT NULL DEFAULT ''
 miio_devices: DEVICE_TYPE varchar(255) NOT NULL DEFAULT ''
 miio_devices: DEVICE_TYPE_CODE varchar(255) NOT NULL DEFAULT ''
 miio_devices: TIME_DIFF int(10) NOT NULL DEFAULT '0'
 miio_devices: MODEL varchar(255) NOT NULL DEFAULT ''
 miio_devices: HW_VER varchar(255) NOT NULL DEFAULT ''
 miio_devices: SERIAL varchar(255) NOT NULL DEFAULT ''
 miio_devices: SETTINGS text
 miio_devices: UPDATE_PERIOD int(10) NOT NULL DEFAULT '0'
 miio_devices: NEXT_UPDATE datetime
 miio_devices: UPDATED datetime
 miio_devices: FAILURES int(10) NOT NULL DEFAULT '0'
 miio_devices: LAST_SEEN datetime

 miio_commands: ID int(10) unsigned NOT NULL auto_increment
 miio_commands: TITLE varchar(100) NOT NULL DEFAULT ''
 miio_commands: NOTE varchar(100) NOT NULL DEFAULT ''
 miio_commands: VALUE text
 miio_commands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 miio_commands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 miio_commands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 miio_commands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 miio_commands: UPDATED datetime

 miio_queue: ID int(10) unsigned NOT NULL auto_increment
 miio_queue: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 miio_queue: METHOD varchar(100)
 miio_queue: DATA text
 miio_queue: ADDED datetime
EOD;

        parent::dbInstall($data);
        self::$schemaChecked = true;
    }

    /** Колонки нового цикла добавляются и на обновлении, без пересоздания таблицы. */
    private function ensureSchema(): void
    {
        if (self::$schemaChecked) return;
        self::$schemaChecked = true;
        try {
            $db = Core::database();
            $missing = array(
                'FAILURES' => 'int(10) NOT NULL DEFAULT 0',
                'LAST_SEEN' => 'datetime NULL',
            );
            foreach ($missing as $column => $definition) {
                $exists = $db->one("SHOW COLUMNS FROM miio_devices LIKE '" . $column . "'");
                if (!$exists) {
                    $db->exec('ALTER TABLE miio_devices ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
        } catch (Throwable $failure) {
            DebMes('xiaomimiio: schema update failed: ' . $failure->getMessage(), 'xiaomimiio', 'error');
        }
    }

    public function repo(): XiaomiMiioRepository
    {
        if (!$this->repo) $this->repo = new XiaomiMiioRepository();
        return $this->repo;
    }

    // ------------------------------------------------------------------
    // Маршрутизация и действия
    // ------------------------------------------------------------------

    public function run()
    {
        $this->getParams();
        $this->ensureSchema();
        require_once ROOT . 'lib/ui/MDJUI.php';

        if (gr('ajax')) {
            $this->ajaxAnswer();
        }

        $session = Core::session();
        // Тот же токен, что проверяет AuthGate на входе в панель: форма проходит
        // обе проверки и не требует отдельного состояния модуля.
        $csrf = $session->csrfToken();

        $error = '';
        $notice = '';
        $flash = $session->pull('xiaomimiio_flash');
        $directResult = $session->pull('xiaomimiio_result');

        // Старые ссылки модуля 3.x: поиск и обновление устройства.
        // Удаление оставлено только через форму, чтобы GET ничего не разрушал.
        $legacyView = (string)$this->view_mode;
        if ($legacyView === 'discover') {
            try {
                $this->discover();
                $this->flash('success', 'Поиск устройств завершён', 'Список обновлён.');
            } catch (Throwable $failure) {
                $this->flash('danger', 'Поиск не выполнен', $failure->getMessage());
            }
            $this->redirect($this->listUrl());
        }
        if ($legacyView === 'propupd_miio_devices' && $this->id > 0) {
            $this->requestStatus($this->id);
            $this->redirect($this->deviceUrl($this->id));
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                if (!$session->checkCsrf($_POST['_md_csrf'] ?? $_POST['_token'] ?? null)) {
                    throw new InvalidArgumentException('Форма устарела: страница была открыта слишком давно. Обновите её и повторите действие.');
                }
                $outcome = $this->handlePost();
                $this->flash($outcome['type'] ?? 'success', $outcome['title'] ?? 'Готово', $outcome['text'] ?? '');
                $this->redirect($outcome['url'] ?? $this->defaultUrl());
            } catch (Throwable $failure) {
                $error = $failure->getMessage();
            }
        }

        $id = (int)$this->id;
        $view = $this->resolveView();
        $tab = (string)$this->tab;
        $bindingCommand = null;
        if ($view === 'edit_miio_devices') {
            if ($id > 0 && preg_match('/^bind-(\d+)$/', $tab, $match)) {
                $bindingCommand = $this->repo()->command((int)$match[1]);
                if (!$bindingCommand || (int)$bindingCommand['DEVICE_ID'] !== $id) {
                    $bindingCommand = null;
                    $tab = '';
                    $error = $error !== '' ? $error : 'Свойство не найдено у этого устройства.';
                } else {
                    $tab = 'bind';
                }
            }
            if (!in_array($tab, array('', 'data', 'gwradio', 'gwzigbee', 'help', 'bind'), true)) $tab = '';
        }

        $device = null;
        $properties = array();
        $online = '';
        $linkedControl = '';
        $helpHtml = '';
        $binding = array();
        $gwTitles = array('add_program', 'del_program', 'current_program', 'current_progress',
            'current_volume', 'current_status', 'all_program');
        $isNew = false;

        if ($view === 'edit_miio_devices') {
            if ($id > 0) {
                $device = $this->repo()->device($id);
                if (!$device) {
                    http_response_code(404);
                    $error = 'Устройство не найдено. Возможно, оно было удалено.';
                    $view = 'search_miio_devices';
                } else {
                    $online = $this->repo()->onlineValue($id);
                    if ($tab === 'bind' && $bindingCommand) {
                        // Список нужен только для счётчика на вкладке «Свойства».
                        $properties = $this->repo()->commands($id);
                        $binding = $bindingCommand;
                    } elseif ($tab === 'gwradio') {
                        $properties = $this->repo()->commands($id, $gwTitles);
                    } else {
                        $properties = $this->repo()->commands($id);
                    }
                    if ($tab === 'help') {
                        $helpHtml = $this->renderHelp($device);
                    }
                    if ($tab === 'bind' && $bindingCommand) {
                        $linkedControl = $this->renderLinkedObject($binding);
                    }
                }
            } else {
                $isNew = true;
                $device = array(
                    'ID' => 0, 'TITLE' => '', 'IP' => '', 'TOKEN' => '', 'DEVICE_TYPE' => '',
                    'UPDATE_PERIOD' => 60, 'DEVICE_TYPE_CODE' => '', 'SERIAL' => '', 'MAC' => '',
                    'HW_VER' => '', 'MODEL' => '', 'LAST_SEEN' => null, 'UPDATED' => null,
                );
            }
        }

        // Если форма не прошла проверку, показываем введённое, а не значения из базы.
        if ($error !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $view === 'edit_miio_devices') {
            foreach (array('TITLE' => 'title', 'IP' => 'ip', 'TOKEN' => 'token',
                         'DEVICE_TYPE' => 'device_type', 'UPDATE_PERIOD' => 'update_period') as $column => $field) {
                if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                    $value = trim((string)$_POST[$field]);
                    if ($value !== '' || $column !== 'TOKEN') $device[$column] = $value;
                }
            }
        }

        $settings = $this->settings();
        $stats = $this->stats();
        $devices = array();
        $search = '';
        $sort = 'title';
        $page = 1;
        $pages = 1;
        $count = 0;

        if ($view === 'search_miio_devices') {
            $search = mb_substr($this->request('q'), 0, 100);
            $sort = in_array($this->request('sort'), array('title', 'new', 'online', 'ip'), true) ? $this->request('sort') : 'title';
            $page = max(1, (int)$this->request('page'));
            $perPage = 40;
            $count = $this->repo()->countDevices($search);
            $pages = max(1, (int)ceil($count / $perPage));
            $page = min($page, $pages);
            $devices = $this->repo()->devices($search, $sort, $perPage, ($page - 1) * $perPage);
        }

        $queue = $id > 0 ? $this->repo()->queueForDevice($id, 8) : array();
        $noTokenDevices = array();
        if ($view === 'tokens') {
            $noTokenDevices = $this->repo()->select("SELECT ID,TITLE,IP,DEVICE_TYPE FROM miio_devices WHERE TOKEN='' ORDER BY TITLE");
        }

        $vars = array(
            'view' => $view,
            'tab' => $tab,
            'device' => $device,
            'isNew' => $isNew,
            'devices' => $devices,
            'search' => $search,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'count' => $count,
            'stats' => $stats,
            'settings' => $settings,
            'properties' => $properties,
            'online' => $online,
            'binding' => $binding,
            'bindingCommand' => $bindingCommand,
            'linkedControl' => $linkedControl,
            'helpHtml' => $helpHtml,
            'queue' => $queue,
            'error' => $error,
            'flash' => $flash,
            'directResult' => $directResult,
            'csrf' => $csrf,
            'gwTitles' => $gwTitles,
            'noTokenDevices' => $noTokenDevices,
        );

        $this->result = $this->renderTemplate('xiaomimiio.php', $vars);
    }

    private function resolveView(): string
    {
        $view = (string)$this->view_mode;
        $tab = (string)$this->tab;
        if ($view === '') {
            // Чистый URL /panel/xiaomimiio/<id>.html приходит без view_mode.
            if ($this->id > 0) return 'edit_miio_devices';
            if (in_array($tab, array('settings', 'tokens'), true)) return $tab;
            if ($tab === 'add') return 'edit_miio_devices';
            return 'search_miio_devices';
        }
        if (in_array($view, array('search_miio_devices', 'edit_miio_devices', 'settings', 'tokens'), true)) return $view;
        return 'search_miio_devices';
    }

    private function defaultUrl(): string
    {
        $view = $this->resolveView();
        if ($view === 'settings') return $this->pageUrl('settings');
        if ($view === 'tokens') return $this->pageUrl('tokens');
        if ($view === 'edit_miio_devices') {
            return (int)$this->id > 0 ? $this->deviceUrl((int)$this->id, (string)$this->tab) : $this->pageUrl('add');
        }
        return $this->listUrl();
    }

    public function listUrl(): string
    {
        return ROOTHTML . 'panel/xiaomimiio.html';
    }

    public function pageUrl(string $page): string
    {
        return ROOTHTML . 'panel/xiaomimiio/0/' . $page . '.html';
    }

    public function deviceUrl(int $id, string $tab = ''): string
    {
        $url = ROOTHTML . 'panel/xiaomimiio/' . max(0, $id) . '.html';
        if ($tab !== '' && $tab !== 'data') $url = ROOTHTML . 'panel/xiaomimiio/' . max(0, $id) . '/' . rawurlencode($tab) . '.html';
        if ($tab === 'data') $url = ROOTHTML . 'panel/xiaomimiio/' . max(0, $id) . '/data.html';
        return $url;
    }

    private function renderTemplate(string $template, array $vars): string
    {
        $file = ROOT . 'templates/xiaomimiio/' . $template;
        if (!is_file($file)) {
            return MDJUI::alert('danger', 'Шаблон не найден', 'Ожидался файл ' . $file . '.');
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

    private function renderHelp(array $device): string
    {
        $model = trim((string)($device['DEVICE_TYPE'] ?? ''));
        $safeModel = preg_match('/^[a-z0-9_.-]+$/i', $model) ? $model : 'unknown';
        $file = ROOT . 'templates/xiaomimiio/help/ru/help_' . $safeModel . '.html';
        if (!is_file($file)) $file = ROOT . 'templates/xiaomimiio/help/ru/help_unknown.html';
        if (!is_file($file)) return '';
        $data = array();
        foreach ($device as $key => $value) {
            if (!is_array($value)) $data[$key] = (string)$value;
        }
        $data['DEVICE_TYPE'] = $model;
        $data['ROOTHTML'] = ROOTHTML;
        $parser = new parser($file, $data, $this);
        return (string)$parser->result;
    }

    private function renderLinkedObject(array $binding): string
    {
        if (!class_exists('linkedobject', false)) {
            require_once DIR_MODULES . 'linkedobject/linkedobject.class.php';
        }
        $this->data = array(
            'LINKED_OBJECT' => (string)($binding['LINKED_OBJECT'] ?? ''),
            'LINKED_PROPERTY' => (string)($binding['LINKED_PROPERTY'] ?? ''),
            'LINKED_METHOD' => (string)($binding['LINKED_METHOD'] ?? ''),
        );
        $linked = new linkedobject();
        $linked->owner = $this;
        $linked->object_field = 'linked_object';
        $linked->property_field = 'linked_property';
        $linked->method_field = 'linked_method';
        $linked->width = '100%';
        $linked->run();
        return (string)$linked->result;
    }

    // ------------------------------------------------------------------
    // Обработка POST
    // ------------------------------------------------------------------

    private function handlePost(): array
    {
        $operation = $this->posted('operation');
        switch ($operation) {
            case 'save_device':
                return $this->saveDevice();
            case 'delete_device':
                return $this->deleteDevice();
            case 'refresh_device':
                return $this->refreshDevice();
            case 'clear_queue':
                return $this->clearQueue();
            case 'device_info':
                return $this->deviceInfo();
            case 'test_command':
                return $this->testCommand();
            case 'save_settings':
                return $this->saveSettings();
            case 'discover':
                return $this->runDiscovery();
            case 'save_binding':
                return $this->saveBinding();
            case 'delete_binding':
                return $this->deleteBinding();
            case 'delete_command':
                return $this->deleteCommandRow();
            case 'import_tokens':
                return $this->importTokens();
        }
        throw new InvalidArgumentException('Неизвестное действие формы.');
    }

    private function saveDevice(): array
    {
        $this->ensureSchema();
        $repo = $this->repo();
        $id = (int)($this->posted('device_id') !== '' ? $this->posted('device_id') : $this->id);
        $existing = $id > 0 ? $repo->device($id) : null;
        if ($id > 0 && !$existing) throw new InvalidArgumentException('Устройство не найдено.');

        $title = trim($this->posted('title'));
        if ($title === '') throw new InvalidArgumentException('Укажите название устройства.');
        if (mb_strlen($title) > 100) throw new InvalidArgumentException('Название длиннее 100 символов.');

        $ip = trim($this->posted('ip'));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Укажите IPv4-адрес устройства, например 192.168.1.50.');
        }

        $token = strtolower(trim($this->posted('token')));
        if ($token !== '' && !preg_match('/^[0-9a-f]{32}$/', $token)) {
            throw new InvalidArgumentException('Токен miIO состоит из 32 шестнадцатеричных символов. Скопируйте его целиком.');
        }
        if ($token === '' && $existing && trim((string)$existing['TOKEN']) !== '') {
            // Пустое поле в форме означает «оставить прежний токен», а не стереть его.
            $token = (string)$existing['TOKEN'];
        }

        $model = XiaomiMiioCatalog::normalize($this->posted('device_type'));
        $manualModel = XiaomiMiioCatalog::normalize($this->posted('device_type_manual'));
        if ($manualModel !== '') $model = $manualModel;

        $period = (int)$this->posted('update_period');
        if ($period < 0 || $period > 86400) throw new InvalidArgumentException('Период опроса должен быть от 0 до 86400 секунд.');

        $record = array(
            'ID' => $id,
            'TITLE' => $title,
            'IP' => $ip,
            'TOKEN' => $token,
            'DEVICE_TYPE' => $model,
            'UPDATE_PERIOD' => $period,
            'NEXT_UPDATE' => date('Y-m-d H:i:s', time() + min(30, max(0, $period))),
            'FAILURES' => 0,
        );
        $newId = $repo->saveDevice($record);

        foreach (XiaomiMiioCatalog::extraCommands($model) as $command) {
            $repo->ensureCommand($newId, $command);
        }
        // Новое значение online появится после первого ответа, а не из прошлой сессии.
        $this->processCommand($newId, 'online', 0);

        if ($token !== '') {
            $this->requestInfo($newId);
            if ($model !== '') $this->requestStatus($newId);
        }

        $device = $repo->device($newId);
        $suffix = $device && trim((string)$device['TOKEN']) !== ''
            ? ' Сведения и состояние запрошены у устройства; ответ появится в свойствах через несколько секунд.'
            : ' Без токена модуль не сможет опрашивать устройство: добавьте токен на вкладке настроек.';

        return array(
            'title' => $id > 0 ? 'Устройство сохранено' : 'Устройство добавлено',
            'text' => $suffix,
            'url' => $this->deviceUrl($newId),
        );
    }

    private function deleteDevice(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство уже удалено.');
        $this->DeleteLinkedProperties($id);
        $this->repo()->deleteDevice($id);
        return array(
            'title' => 'Устройство удалено',
            'text' => '«' . (string)$device['TITLE'] . '» и его привязки удалены. Объекты и сценарии MajorDoMo не изменялись.',
            'url' => $this->listUrl(),
        );
    }

    private function refreshDevice(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство не найдено.');
        if (trim((string)$device['TOKEN']) === '') throw new InvalidArgumentException('Сначала укажите токен устройства.');
        $this->requestInfo($id);
        $this->requestStatus($id);
        $this->repo()->markPoll($id, date('Y-m-d H:i:s'), 0);
        return array(
            'title' => 'Запрос отправлен',
            'text' => 'Команды miIO.info и опроса состояния стоят в очереди. Ответ появится в свойствах устройства.',
            'url' => $this->deviceUrl($id),
        );
    }

    private function clearQueue(): array
    {
        $id = (int)$this->posted('device_id');
        $this->repo()->db->requireExec($id > 0
            ? 'DELETE FROM miio_queue WHERE DEVICE_ID=' . $id
            : 'DELETE FROM miio_queue');
        return array(
            'title' => 'Очередь очищена',
            'text' => $id > 0 ? 'Команды этого устройства удалены из очереди.' : 'Все команды удалены из очереди.',
            'url' => $id > 0 ? $this->deviceUrl($id) : $this->listUrl(),
        );
    }

    private function deviceInfo(): array
    {
        $device = $this->requireDeviceForDirectCall();
        $result = $this->executeDirect($device, 'miIO.info', '[]');
        if (!$result['ok']) throw new RuntimeException('Устройство не ответило: ' . $result['error']);
        $decoded = json_decode($result['data'], true);
        if (is_array($decoded['result'] ?? null)) {
            $info = $decoded['result'];
            $record = array('ID' => (int)$device['ID'], 'UPDATED' => date('Y-m-d H:i:s'));
            if (!empty($info['model']) && preg_match('/^[a-z0-9_.-]+$/i', $info['model'])) $record['DEVICE_TYPE'] = $info['model'];
            if (!empty($info['mac'])) $record['MAC'] = mb_substr((string)$info['mac'], 0, 255);
            if (!empty($info['hw_ver'])) $record['HW_VER'] = mb_substr((string)$info['hw_ver'], 0, 255);
            if (!empty($info['model'])) $record['MODEL'] = mb_substr((string)$info['model'], 0, 255);
            $this->repo()->saveDevice($record);
            $model = (string)($record['DEVICE_TYPE'] ?? $device['DEVICE_TYPE']);
            if ($model !== '') {
                foreach (XiaomiMiioCatalog::extraCommands($model) as $command) {
                    $this->repo()->ensureCommand((int)$device['ID'], $command);
                }
                if ($model !== (string)$device['DEVICE_TYPE']) $this->requestStatus((int)$device['ID']);
            }
        }
        $this->storeDirectResult('miIO.info', $result['data']);
        return array(
            'title' => 'Устройство ответило',
            'text' => 'Сведения miIO.info сохранены. Модель и MAC обновлены, если устройство их вернуло.',
            'url' => $this->deviceUrl((int)$device['ID']),
        );
    }

    private function testCommand(): array
    {
        $device = $this->requireDeviceForDirectCall();
        $method = trim($this->posted('method'));
        if ($method === '' || !preg_match('/^[A-Za-z0-9_.]{1,100}$/', $method)) {
            throw new InvalidArgumentException('Метод miIO указывается латиницей, цифрами, точкой и подчёркиванием, например get_prop или miIO.info.');
        }
        $params = trim($this->posted('params'));
        if ($params === '') $params = '[]';
        $decoded = json_decode($params, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException('Параметры команды должны быть JSON-массивом, например ["power"] или [].');
        }
        $result = $this->executeDirect($device, $method, $params);
        if (!$result['ok']) throw new RuntimeException('Устройство не ответило: ' . $result['error']);
        $this->storeDirectResult($method, $result['data']);
        return array(
            'title' => 'Команда выполнена',
            'text' => 'Ответ устройства показан на странице. Команда не сохранялась в привязки.',
            'url' => $this->deviceUrl((int)$device['ID']),
        );
    }

    private function requireDeviceForDirectCall(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство не найдено.');
        if (trim((string)$device['TOKEN']) === '' || trim((string)$device['IP']) === '') {
            throw new InvalidArgumentException('Для прямой команды нужны IP и токен устройства.');
        }
        return $device;
    }

    private function saveSettings(): array
    {
        $bindIp = trim($this->posted('bind_ip'));
        if ($bindIp !== '' && filter_var($bindIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('IP для привязки сокета должен быть IPv4-адресом или пустым.');
        }
        $discovery = (int)$this->posted('disc_period');
        if ($discovery < 0 || $discovery > 3600) throw new InvalidArgumentException('Период поиска устройств — от 0 до 3600 секунд. 0 выключает автоматический поиск.');
        $timeout = (int)$this->posted('socket_timeout');
        if ($timeout < 1 || $timeout > 10) throw new InvalidArgumentException('Таймаут ответа — от 1 до 10 секунд.');

        $this->config = array(
            'API_IP' => $bindIp,
            'API_DISC_PERIOD' => $discovery,
            'API_SOCKET_TIMEOUT' => $timeout,
            'API_LOG_DEBMES' => $this->posted('log_debmes') === '1' ? 1 : 0,
            'API_LOG_CYCLE' => $this->posted('log_cycle') === '1' ? 1 : 0,
            'API_LOG_MIIO' => $this->posted('log_miio') === '1' ? 1 : 0,
        );
        $this->saveConfig();
        $this->settingsCache = null;
        setGlobal('cycle_xiaomimiioControl', 'restart');

        return array(
            'title' => 'Настройки сохранены',
            'text' => 'Цикл перезапущен, чтобы применить период поиска и таймаут.',
            'url' => $this->pageUrl('settings'),
        );
    }

    private function runDiscovery(): array
    {
        $before = $this->repo()->stats()['devices'];
        $this->discover();
        $after = $this->repo()->stats()['devices'];
        return array(
            'title' => 'Поиск устройств завершён',
            'text' => $after > $before
                ? 'Найдено новых устройств: ' . ($after - $before) . '. Они появятся в списке с названием «New …».'
                : 'Новых устройств не найдено. Если устройство в другой подсети или отвечает только по токену, добавьте его вручную.',
            'url' => $this->listUrl(),
        );
    }

    private function saveBinding(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        $deviceId = (int)$command['DEVICE_ID'];

        $objectName = trim($this->posted('linked_object'));
        $propertyName = trim($this->posted('linked_property'));
        $methodName = trim($this->posted('linked_method'));
        foreach (array($objectName, $propertyName, $methodName) as $name) {
            if (mb_strlen($name) > 100) throw new InvalidArgumentException('Имя объекта, свойства или метода длиннее 100 символов.');
        }
        if ($propertyName !== '' || $methodName !== '') {
            if ($objectName === '') throw new InvalidArgumentException('Сначала выберите объект MajorDoMo.');
            $object = getObject($objectName);
            if (!$object) throw new InvalidArgumentException('Объект «' . $objectName . '» не найден.');
            if ($propertyName !== '' && !$object->getPropertyByName($propertyName, $object->class_id, $object->id)) {
                throw new InvalidArgumentException('У объекта «' . $objectName . '» нет свойства «' . $propertyName . '».');
            }
            if ($methodName !== '' && !$object->getMethodByName($methodName, $object->class_id, $object->id)) {
                throw new InvalidArgumentException('У объекта «' . $objectName . '» нет метода «' . $methodName . '».');
            }
        }
        if ($objectName === '' && ($propertyName !== '' || $methodName !== '')) {
            throw new InvalidArgumentException('Без объекта свойство и метод не привязываются.');
        }
        if ($objectName !== '' && $propertyName === '' && $methodName === '') {
            $objectName = '';
        }

        $previousObject = (string)$command['LINKED_OBJECT'];
        $previousProperty = (string)$command['LINKED_PROPERTY'];

        $this->repo()->saveCommand(array(
            'ID' => $commandId,
            'LINKED_OBJECT' => $objectName,
            'LINKED_PROPERTY' => $propertyName,
            'LINKED_METHOD' => $methodName,
        ));

        if ($objectName !== '' && $propertyName !== '') {
            if (!addLinkedProperty($objectName, $propertyName, $this->name)) {
                throw new RuntimeException('Не удалось зарегистрировать привязку свойства. Проверьте объект и свойство в MajorDoMo.');
            }
        }
        if ($previousObject !== '' && $previousProperty !== ''
            && ($previousObject !== $objectName || $previousProperty !== $propertyName)) {
            removeLinkedPropertyIfNotUsed('miio_commands', $previousObject, $previousProperty, $this->name);
        }

        return array(
            'title' => 'Привязка сохранена',
            'text' => $objectName === ''
                ? 'Привязка снята: значение свойства больше не передаётся в объект.'
                : 'Значение «' . (string)$command['TITLE'] . '» будет записываться в ' . $objectName . '.' . $propertyName . '.',
            'url' => $this->deviceUrl($deviceId, 'bind-' . $commandId),
        );
    }

    private function deleteBinding(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
            removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
        }
        $this->repo()->saveCommand(array(
            'ID' => $commandId,
            'LINKED_OBJECT' => '',
            'LINKED_PROPERTY' => '',
            'LINKED_METHOD' => '',
        ));
        return array(
            'title' => 'Привязка снята',
            'text' => 'Свойство «' . (string)$command['TITLE'] . '» осталось у устройства, но больше не связано с объектом.',
            'url' => $this->deviceUrl((int)$command['DEVICE_ID'], 'bind-' . $commandId),
        );
    }

    private function deleteCommandRow(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
            removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
        }
        $this->repo()->deleteCommand($commandId);
        return array(
            'title' => 'Свойство удалено',
            'text' => 'Строка «' . (string)$command['TITLE'] . '» удалена. При следующем опросе устройство может создать её снова.',
            'url' => $this->deviceUrl((int)$command['DEVICE_ID'], 'data'),
        );
    }

    /**
     * Импорт вывода token_extractor.py. Пароль Xiaomi не попадает на сервер:
     * скрипт запускается вручную, сюда вставляется только результат.
     */
    private function importTokens(): array
    {
        $text = (string)($this->posted('tokens_output'));
        if (trim($text) === '') throw new InvalidArgumentException('Вставьте вывод token_extractor.py.');
        $devices = array();
        $current = array();
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (stripos($line, 'new-device') !== false) {
                if (!empty($current)) $devices[] = $current;
                $current = array();
                continue;
            }
            if (preg_match('/^\{([^}]+)\}=\{([^}]*)\}$/', $line, $match)) {
                $current[strtoupper(trim($match[1]))] = trim($match[2]);
            }
        }
        if (!empty($current)) $devices[] = $current;
        if (!$devices) throw new InvalidArgumentException('В тексте нет блоков {ID}={…}. Скопируйте вывод скрипта целиком.');

        $repo = $this->repo();
        $updated = 0;
        $created = 0;
        $skipped = 0;
        foreach ($devices as $row) {
            $ip = trim((string)($row['IP'] ?? ''));
            $token = strtolower(trim((string)($row['TOKEN'] ?? '')));
            if ($ip === '' || !preg_match('/^[0-9a-f]{32}$/', $token)) {
                $skipped++;
                continue;
            }
            $existing = $repo->one('SELECT * FROM miio_devices WHERE IP=' . $repo->quote($ip) . ' LIMIT 1');
            if ($existing) {
                $record = array('ID' => (int)$existing['ID'], 'TOKEN' => $token, 'UPDATED' => date('Y-m-d H:i:s'));
                if (trim((string)$existing['DEVICE_TYPE']) === '' && !empty($row['MODEL'])) {
                    $record['DEVICE_TYPE'] = XiaomiMiioCatalog::normalize((string)$row['MODEL']);
                }
                if (str_starts_with((string)$existing['TITLE'], 'New ') && !empty($row['NAME'])) {
                    $record['TITLE'] = mb_substr((string)$row['NAME'], 0, 100);
                }
                $repo->saveDevice($record);
                $updated++;
            } else {
                $record = array(
                    'TITLE' => mb_substr(trim((string)($row['NAME'] ?? 'New device')), 0, 100),
                    'IP' => $ip,
                    'TOKEN' => $token,
                    'DEVICE_TYPE' => XiaomiMiioCatalog::normalize((string)($row['MODEL'] ?? '')),
                    'UPDATE_PERIOD' => 60,
                    'NEXT_UPDATE' => date('Y-m-d H:i:s'),
                    'FAILURES' => 0,
                );
                $newId = $repo->saveDevice($record);
                foreach (XiaomiMiioCatalog::extraCommands((string)$record['DEVICE_TYPE']) as $command) {
                    $repo->ensureCommand($newId, $command);
                }
                $created++;
            }
        }
        if ($updated === 0 && $created === 0) {
            throw new InvalidArgumentException('Не удалось найти ни одного устройства с IP и токеном. Проверьте, что скопирован весь вывод.');
        }
        return array(
            'title' => 'Токены импортированы',
            'text' => 'Обновлено устройств: ' . $updated . ', добавлено: ' . $created
                . ($skipped ? ', пропущено строк без IP или токена: ' . $skipped : '')
                . '. Цикл опросит их в ближайшие секунды.',
            'url' => $this->listUrl(),
        );
    }

    // ------------------------------------------------------------------
    // Прямые вызовы и данные для шаблонов
    // ------------------------------------------------------------------

    private $settingsCache = null;

    private function settings(): array
    {
        if (is_array($this->settingsCache)) return $this->settingsCache;
        $this->getConfig();
        $config = is_array($this->config) ? $this->config : array();
        return $this->settingsCache = array(
            'bind_ip' => (string)($config['API_IP'] ?? ''),
            'disc_period' => array_key_exists('API_DISC_PERIOD', $config) ? (int)$config['API_DISC_PERIOD'] : 120,
            'socket_timeout' => max(1, min(10, (int)($config['API_SOCKET_TIMEOUT'] ?? 2))),
            'log_debmes' => !empty($config['API_LOG_DEBMES']),
            'log_cycle' => !empty($config['API_LOG_CYCLE']),
            'log_miio' => !empty($config['API_LOG_MIIO']),
        );
    }

    private function stats(): array
    {
        $stats = $this->repo()->stats();
        try {
            $lastRun = (int)gg('cycle_xiaomimiioRun');
        } catch (Throwable $failure) {
            // Таблица cached_cycles появляется вместе с супервизором циклов; без неё страница должна открываться.
            $lastRun = 0;
        }
        $stats['cycle_age'] = $lastRun > 0 ? max(0, time() - $lastRun) : null;
        $stats['cycle_running'] = $lastRun > 0 && (time() - $lastRun) < 20;
        return $stats;
    }

    private function executeDirect(array $device, string $method, string $params): array
    {
        if (!class_exists('miIO', false)) require_once DIR_MODULES . 'xiaomimiio/lib/miio.class.php';
        $settings = $this->settings();
        try {
            $client = new miIO((string)$device['IP'], $settings['bind_ip'], (string)$device['TOKEN'], $settings['log_miio']);
            $client->send_timeout = $settings['socket_timeout'];
            $client->data = '';
            $sent = $client->msgSendRcv($method, $params, time());
            if (!$sent) {
                $error = $client->data !== '' ? $client->data : 'нет ответа по UDP за ' . $settings['socket_timeout'] . ' с';
                return array('ok' => false, 'data' => '', 'error' => $error);
            }
            return array('ok' => true, 'data' => (string)$client->data, 'error' => '');
        } catch (Throwable $failure) {
            return array('ok' => false, 'data' => '', 'error' => $failure->getMessage());
        }
    }

    private function storeDirectResult(string $method, string $raw): void
    {
        $pretty = $raw;
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        Core::session()->set('xiaomimiio_result', array(
            'method' => $method,
            'at' => date('Y-m-d H:i:s'),
            'json' => mb_substr((string)$pretty, 0, 8000),
        ));
    }

    private function flash(string $type, string $title, string $text = ''): void
    {
        Core::session()->set('xiaomimiio_flash', array('type' => $type, 'title' => $title, 'text' => $text));
    }

    private function request(string $name): string
    {
        $value = $_GET[$name] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /** Для AJAX значение может прийти и в POST, и в query. */
    private function param(string $name): string
    {
        $value = $_POST[$name] ?? $_GET[$name] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function posted(string $name): string
    {
        if (!array_key_exists($name, $_POST)) return '';
        $value = $_POST[$name];
        if (!is_scalar($value)) throw new InvalidArgumentException('Некорректное поле формы: ' . $name);
        return trim((string)$value);
    }

    // ------------------------------------------------------------------
    // AJAX для многошаговых действий (ИК-обучение, Zigbee, LAN-режим)
    // ------------------------------------------------------------------

    private function ajaxAnswer(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $session = Core::session();
            if (!$session->checkCsrf($_POST['_md_csrf'] ?? $_POST['_token'] ?? $_GET['_md_csrf'] ?? $_GET['_token'] ?? null)) {
                throw new InvalidArgumentException('Сессия устарела. Обновите страницу.');
            }
            $op = $this->param('op');
            $deviceId = (int)$this->param('device_id');
            $device = $deviceId > 0 ? $this->repo()->device($deviceId) : null;
            if ($deviceId > 0 && !$device) throw new InvalidArgumentException('Устройство не найдено.');
            if ($device && (trim((string)$device['TOKEN']) === '' || trim((string)$device['IP']) === '')) {
                throw new InvalidArgumentException('У устройства не заданы IP или токен.');
            }

            switch ($op) {
                case 'info':
                    $result = $this->executeDirect($device, 'miIO.info', '[]');
                    $this->ajaxResult($result);
                    return;
                case 'command':
                    $method = trim($this->param('method'));
                    if (!preg_match('/^[A-Za-z0-9_.]{1,100}$/', $method)) throw new InvalidArgumentException('Некорректный метод miIO.');
                    $params = trim((string)($this->param('params') ?: '[]'));
                    if (json_decode($params, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException('Параметры должны быть JSON.');
                    }
                    $this->ajaxResult($this->executeDirect($device, $method, $params));
                    return;
                case 'lan_mode':
                    $this->ajaxLanMode($device);
                    return;
                case 'ir_learn_start':
                case 'ir_learn_poll':
                case 'ir_learn_stop':
                    $this->ajaxIrLearn($device, $op);
                    return;
                case 'zigbee_list':
                    $this->ajaxResult($this->executeDirect($device, 'get_device_prop', '["lumi.0","device_list"]'));
                    return;
                case 'zigbee_join':
                    $seconds = max(10, min(120, (int)$this->param('seconds')));
                    $this->ajaxResult($this->executeDirect($device, 'start_zigbee_join', '[' . $seconds . ']'));
                    return;
                case 'zigbee_remove':
                    $sid = trim($this->param('sid'));
                    if (!preg_match('/^[a-z0-9_.:-]{3,100}$/i', $sid)) throw new InvalidArgumentException('Некорректный идентификатор устройства Zigbee.');
                    $this->ajaxResult($this->executeDirect($device, 'remove_device', '["' . addcslashes($sid, '"\\') . '"]'));
                    return;
                case 'refresh':
                    $this->requestStatus((int)$device['ID']);
                    $this->json(array('ok' => true, 'data' => 'queued'));
                    return;
            }
            throw new InvalidArgumentException('Неизвестная операция.');
        } catch (Throwable $failure) {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => $failure->getMessage()), JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function ajaxResult(array $result): void
    {
        if (!$result['ok']) {
            throw new RuntimeException($result['error'] !== '' ? $result['error'] : 'Устройство не ответило.');
        }
        $decoded = json_decode($result['data'], true);
        $this->json(array('ok' => true, 'raw' => $result['data'], 'data' => $decoded));
    }

    private function json(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function ajaxLanMode(array $device): void
    {
        if (!XiaomiMiioCatalog::supportsLanKey((string)$device['DEVICE_TYPE'])) {
            throw new InvalidArgumentException('LAN-режим поддерживают только шлюзы lumi.gateway.v3 и lumi.acpartner.v3.');
        }
        $mode = $this->param('mode') === 'on' ? 'on' : 'off';
        $key = $mode === 'on' ? bin2hex(random_bytes(8)) : '';
        $result = $this->executeDirect($device, 'set_lumi_dpf_aes_key', '["' . $key . '"]');
        if (!$result['ok']) throw new RuntimeException($result['error']);
        $this->addToQueue((int)$device['ID'], 'get_lumi_dpf_aes_key', '[]');
        $this->json(array('ok' => true, 'data' => array('mode' => $mode, 'key' => $key)));
    }

    private function ajaxIrLearn(array $device, string $op): void
    {
        $model = (string)$device['DEVICE_TYPE'];
        if ($model === 'lumi.acpartner.v3') {
            if ($op === 'ir_learn_start') {
                $this->ajaxResult($this->executeDirect($device, 'start_ir_learn', '[30]'));
            }
            if ($op === 'ir_learn_poll') {
                $this->ajaxResult($this->executeDirect($device, 'get_ir_learn_result', '[]'));
            }
            $this->ajaxResult($this->executeDirect($device, 'end_ir_learn', '[]'));
            return;
        }
        if ($model === 'chuangmi.ir.v2') {
            $key = (int)$this->param('key');
            if ($op === 'ir_learn_stop') {
                // У пульта нет отдельной команды остановки — просто прекращаем опрос.
                $this->json(array('ok' => true, 'data' => 'stopped'));
            }
            if ($key <= 0) throw new InvalidArgumentException('Не задан ключ обучения.');
            if ($op === 'ir_learn_start') {
                $this->ajaxResult($this->executeDirect($device, 'miIO.ir_learn', '{"key":"' . $key . '"}'));
            }
            $this->ajaxResult($this->executeDirect($device, 'miIO.ir_read', '{"key":"' . $key . '"}'));
            return;
        }
        throw new InvalidArgumentException('ИК-обучение доступно для lumi.acpartner.v3 и chuangmi.ir.v2.');
    }

    // ------------------------------------------------------------------
    // Цикл
    // ------------------------------------------------------------------

    /**
     * Один шаг цикла: очередь, опрос и периодический поиск устройств.
     * Вызывается из scripts/cycle_xiaomimiio.php, ошибки не выходят наружу.
     */
    public function cycleTick(): void
    {
        $this->ensureSchema();
        $this->getConfig();
        $repo = $this->repo();
        $settings = $this->settings();
        $touched = array();
        // Бюджет шага: одно «молчащее» устройство ждёт до 10 секунд, а очередь
        // может быть длинной. Ограничиваем шаг, чтобы heartbeat цикла не отставал.
        $deadline = microtime(true) + 5.0;

        foreach ($repo->dueQueue(50) as $row) {
            if (microtime(true) >= $deadline) break;
            // Строку забираем до отправки: повторный запуск процесса не отправит её дважды.
            $repo->deleteQueue((int)$row['ID']);
            $device = (int)$row['DEVICE_ID'] > 0 ? $repo->device((int)$row['DEVICE_ID']) : null;
            if (!$device || trim((string)$device['TOKEN']) === '' || trim((string)$device['IP']) === '') {
                continue;
            }
            $method = (string)$row['METHOD'];
            $reply = $this->cycleSend($device, $method, (string)$row['DATA']);
            if ($reply === null) {
                $this->cycleFailure($device, 'нет ответа на ' . $method, $settings);
                $this->processMessage('{"error":"Device not answered"}', $method, (int)$device['ID']);
                continue;
            }
            $touched[(int)$device['ID']] = true;
            $this->cycleSuccess((int)$device['ID']);
            if ($reply !== '') {
                try {
                    $this->processMessage($reply, $method, (int)$device['ID']);
                } catch (Throwable $failure) {
                    DebMes('xiaomimiio: cannot process reply of ' . $method . ': ' . $failure->getMessage(), 'xiaomimiio', 'error');
                }
            }
        }

        foreach ($repo->dueDevices(20) as $device) {
            if (isset($touched[(int)$device['ID']])) continue;
            $this->scheduleNextPoll($device);
            $this->requestStatus((int)$device['ID']);
        }

        if ($settings['disc_period'] > 0 && (time() - $this->lastDiscover) >= $settings['disc_period']) {
            $this->lastDiscover = time();
            try {
                $this->discover();
            } catch (Throwable $failure) {
                DebMes('xiaomimiio: discovery failed: ' . $failure->getMessage(), 'xiaomimiio', 'warning');
            }
        }
    }

    private function cycleSend(array $device, string $method, string $params): ?string
    {
        if (!class_exists('miIO', false)) require_once DIR_MODULES . 'xiaomimiio/lib/miio.class.php';
        $settings = $this->settings();
        if (!$this->client) {
            $this->client = new miIO(null, $settings['bind_ip'], null, $settings['log_miio']);
        }
        $this->client->send_timeout = $settings['socket_timeout'];
        $this->client->ip = (string)$device['IP'];
        $this->client->token = (string)$device['TOKEN'];
        $this->client->data = '';
        $this->msgId = $this->msgId >= 1000000 ? 1 : $this->msgId + 1;
        if (!$this->client->msgSendRcv($method, $params !== '' ? $params : '[]', $this->msgId)) {
            return null;
        }
        return (string)$this->client->data;
    }

    private function cycleSuccess(int $deviceId): void
    {
        $this->repo()->markSeen($deviceId, date('Y-m-d H:i:s'));
    }

    private function cycleFailure(array $device, string $reason, array $settings): void
    {
        $failures = (int)($device['FAILURES'] ?? 0) + 1;
        $period = max(5, (int)$device['UPDATE_PERIOD']);
        $delay = $period;
        if ($failures >= 3) {
            // После трёх молчаний не долбим устройство каждые N секунд: 2×, 4×, 8×, но не более 10 минут.
            $delay = min(600, $period * (1 << min(4, $failures - 2)));
        }
        $next = date('Y-m-d H:i:s', time() + $delay + random_int(0, min(10, max(1, $period / 4))));
        $this->repo()->markPoll((int)$device['ID'], $next, $failures);
        if ($failures === 3) {
            DebMes('xiaomimiio: ' . $device['TITLE'] . ' (' . $device['IP'] . ') не отвечает: ' . $reason, 'xiaomimiio', 'warning');
        }
    }

    private function scheduleNextPoll(array $device): void
    {
        $period = max(5, (int)$device['UPDATE_PERIOD']);
        $failures = (int)($device['FAILURES'] ?? 0);
        $delay = $period;
        if ($failures >= 3) $delay = min(600, $period * (1 << min(4, $failures - 2)));
        // Небольшой разброс не даёт всем устройствам опрашиваться в одну секунду.
        $jitter = random_int(0, min(15, max(1, (int)($period / 3))));
        $this->repo()->markPoll((int)$device['ID'], date('Y-m-d H:i:s', time() + $delay + $jitter), $failures);
    }

    private function deleteAllData(): void
    {
        try {
            $devices = $this->repo()->select('SELECT ID FROM miio_devices');
            foreach ($devices as $device) {
                $this->DeleteLinkedProperties((int)$device['ID']);
            }
            foreach (array('miio_queue', 'miio_commands', 'miio_devices') as $table) {
                $this->repo()->db->requireExec('DROP TABLE IF EXISTS ' . $table);
            }
        } catch (Throwable $failure) {
            DebMes('xiaomimiio: uninstall cleanup failed: ' . $failure->getMessage(), 'xiaomimiio', 'error');
        }
    }
}
