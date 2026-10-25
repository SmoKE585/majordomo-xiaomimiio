<?php

use MajorDoMo\Core\Core;

/**
 * Доступ к собственным таблицам модуля.
 *
 * Все запросы нового интерфейса и цикла идут через Core::database(): так
 * сохраняются единые правила экранирования, повторного подключения и отлова
 * ошибок SQL. Имена таблиц и полей здесь не собираются из ввода пользователя.
 *
 * 2026-10-25 — слой добавлен при переводе модуля на новый интерфейс.
 */
final class XiaomiMiioRepository
{
    public $db;

    public function __construct()
    {
        $this->db = Core::database();
    }

    public function select(string $sql): array
    {
        return $this->db->select($sql);
    }

    public function one(string $sql): ?array
    {
        return $this->db->one($sql);
    }

    public function quote($value): string
    {
        return "'" . $this->db->escape($value) . "'";
    }

    public function quoteLike(string $value): string
    {
        // escapeLike уже экранирует % и _ обратным слешем для LIKE.
        return "'" . $this->db->escapeLike($value) . "'";
    }

    /** Условие поиска по названию, IP, модели и серийному номеру. */
    public function searchWhere(string $search): string
    {
        $search = trim($search);
        if ($search === '') return '1';
        $like = "'%" . $this->db->escapeLike($search) . "%'";
        return '(d.TITLE LIKE ' . $like . ""
            . ' OR d.IP LIKE ' . $like . ""
            . ' OR d.DEVICE_TYPE LIKE ' . $like . ""
            . ' OR d.SERIAL LIKE ' . $like . ")";
    }

    public function devices(string $search, string $sort, int $limit, int $offset): array
    {
        $order = 'd.TITLE ASC';
        if ($sort === 'new') $order = 'd.ID DESC';
        if ($sort === 'online') $order = 'ONLINE DESC, d.TITLE ASC';
        if ($sort === 'ip') $order = 'd.IP ASC';
        $sql = 'SELECT d.*,'
            . " (SELECT c.VALUE FROM miio_commands c WHERE c.DEVICE_ID=d.ID AND c.TITLE='online' LIMIT 1) AS ONLINE,"
            . ' (SELECT COUNT(*) FROM miio_commands c2 WHERE c2.DEVICE_ID=d.ID) AS COMMANDS,'
            . ' (SELECT MAX(c3.UPDATED) FROM miio_commands c3 WHERE c3.DEVICE_ID=d.ID AND c3.TITLE=\'message\') AS LAST_REPLY'
            . ' FROM miio_devices d WHERE ' . $this->searchWhere($search)
            . ' ORDER BY ' . $order . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return $this->db->select($sql);
    }

    public function countDevices(string $search): int
    {
        $row = $this->db->one('SELECT COUNT(*) AS CNT FROM miio_devices d WHERE ' . $this->searchWhere($search));
        return (int)($row['CNT'] ?? 0);
    }

    public function device(int $id): ?array
    {
        if ($id <= 0) return null;
        return $this->db->one('SELECT * FROM miio_devices WHERE ID=' . $id);
    }

    /** Сохраняет только известные колонки; ID != 0 обновляет запись. */
    public function saveDevice(array $data): int
    {
        $allowed = array('ID', 'TITLE', 'IP', 'TOKEN', 'DEVICE_TYPE', 'UPDATE_PERIOD', 'NEXT_UPDATE',
            'MAC', 'DEVICE_TYPE_CODE', 'SERIAL', 'MODEL', 'HW_VER', 'TIME_DIFF', 'SETTINGS', 'UPDATED');
        $record = array();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) $record[$field] = $data[$field];
        }
        $id = (int)($record['ID'] ?? 0);
        unset($record['ID']);
        if ($id > 0) {
            $record['ID'] = $id;
            $this->db->update('miio_devices', $record);
            return $id;
        }
        return $this->db->insert('miio_devices', $record);
    }

    public function deleteDevice(int $id): void
    {
        if ($id <= 0) return;
        $this->db->requireExec('DELETE FROM miio_queue WHERE DEVICE_ID=' . $id);
        $this->db->requireExec('DELETE FROM miio_commands WHERE DEVICE_ID=' . $id);
        $this->db->requireExec('DELETE FROM miio_devices WHERE ID=' . $id);
    }

    /** Свойства и команды устройства. $titles ограничивает список для вкладок шлюза. */
    public function commands(int $deviceId, array $titles = array()): array
    {
        if ($deviceId <= 0) return array();
        $where = 'DEVICE_ID=' . $deviceId;
        if ($titles) {
            $where .= ' AND TITLE IN (' . implode(',', array_map(array($this, 'quote'), $titles)) . ')';
        }
        return $this->db->select('SELECT * FROM miio_commands WHERE ' . $where . ' ORDER BY TITLE');
    }

    public function command(int $id): ?array
    {
        if ($id <= 0) return null;
        return $this->db->one('SELECT * FROM miio_commands WHERE ID=' . $id);
    }

    public function commandByTitle(int $deviceId, string $title): ?array
    {
        return $this->db->one('SELECT * FROM miio_commands WHERE DEVICE_ID=' . max(0, $deviceId)
            . ' AND TITLE=' . $this->quote($title) . ' LIMIT 1');
    }

    public function ensureCommand(int $deviceId, string $title): array
    {
        $existing = $this->commandByTitle($deviceId, $title);
        if ($existing) return $existing;
        $record = array('TITLE' => $title, 'DEVICE_ID' => $deviceId, 'VALUE' => '', 'UPDATED' => date('Y-m-d H:i:s'));
        $record['ID'] = $this->db->insert('miio_commands', $record);
        return $record;
    }

    public function saveCommand(array $data): int
    {
        $allowed = array('ID', 'TITLE', 'NOTE', 'VALUE', 'DEVICE_ID', 'LINKED_OBJECT', 'LINKED_PROPERTY',
            'LINKED_METHOD', 'UPDATED');
        $record = array();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) $record[$field] = $data[$field];
        }
        $id = (int)($record['ID'] ?? 0);
        unset($record['ID']);
        if ($id > 0) {
            $record['ID'] = $id;
            $this->db->update('miio_commands', $record);
            return $id;
        }
        return $this->db->insert('miio_commands', $record);
    }

    public function deleteCommand(int $id): void
    {
        if ($id > 0) $this->db->requireExec('DELETE FROM miio_commands WHERE ID=' . $id);
    }

    public function queueCount(): int
    {
        $row = $this->db->one('SELECT COUNT(*) AS CNT FROM miio_queue');
        return (int)($row['CNT'] ?? 0);
    }

    public function queueForDevice(int $deviceId, int $limit = 10): array
    {
        if ($deviceId <= 0) return array();
        return $this->db->select('SELECT * FROM miio_queue WHERE DEVICE_ID=' . $deviceId
            . ' ORDER BY ID DESC LIMIT ' . max(1, $limit));
    }

    /** Команды, время которых пришло. Раньше цикл отправлял и отложенные строки тоже. */
    public function dueQueue(int $limit = 50): array
    {
        return $this->db->select('SELECT q.*, d.TOKEN, d.DEVICE_TYPE, d.IP FROM miio_queue q'
            . ' LEFT JOIN miio_devices d ON d.ID=q.DEVICE_ID'
            . ' WHERE q.ADDED<=NOW() ORDER BY q.ID LIMIT ' . max(1, $limit));
    }

    public function deleteQueue(int $id): void
    {
        if ($id > 0) $this->db->requireExec('DELETE FROM miio_queue WHERE ID=' . $id);
    }

    public function addQueue(int $deviceId, string $method, string $data = '[]', int $shift = 0): int
    {
        $record = array(
            'DEVICE_ID' => max(0, $deviceId),
            'METHOD' => mb_substr($method, 0, 100),
            'DATA' => $data,
            'ADDED' => date('Y-m-d H:i:s', time() + max(0, $shift)),
        );
        return $this->db->insert('miio_queue', $record);
    }

    public function dueDevices(int $limit = 20): array
    {
        return $this->db->select("SELECT * FROM miio_devices WHERE UPDATE_PERIOD>0"
            . " AND (NEXT_UPDATE IS NULL OR NEXT_UPDATE<=NOW()) AND DEVICE_TYPE<>'' AND TOKEN<>''"
            . ' ORDER BY NEXT_UPDATE LIMIT ' . max(1, $limit));
    }

    public function markPoll(int $deviceId, string $nextUpdate, int $failures, ?string $lastSeen = null): void
    {
        $sql = 'UPDATE miio_devices SET NEXT_UPDATE=' . $this->quote($nextUpdate)
            . ', FAILURES=' . max(0, $failures);
        if ($lastSeen !== null) $sql .= ', LAST_SEEN=' . $this->quote($lastSeen);
        $sql .= ' WHERE ID=' . max(0, $deviceId);
        $this->db->requireExec($sql);
    }

    /** Успешный ответ: сбрасываем счётчик ошибок и отмечаем время последнего ответа. */
    public function markSeen(int $deviceId, string $lastSeen): void
    {
        $this->db->requireExec('UPDATE miio_devices SET FAILURES=0, LAST_SEEN=' . $this->quote($lastSeen)
            . ' WHERE ID=' . max(0, $deviceId));
    }

    public function onlineValue(int $deviceId): string
    {
        $row = $this->db->one("SELECT VALUE FROM miio_commands WHERE DEVICE_ID=" . max(0, $deviceId)
            . " AND TITLE='online' LIMIT 1");
        return (string)($row['VALUE'] ?? '');
    }

    public function stats(): array
    {
        $devices = (int)($this->db->one('SELECT COUNT(*) AS CNT FROM miio_devices')['CNT'] ?? 0);
        $online = (int)($this->db->one("SELECT COUNT(*) AS CNT FROM miio_commands WHERE TITLE='online' AND VALUE='1'")['CNT'] ?? 0);
        $bindings = (int)($this->db->one("SELECT COUNT(*) AS CNT FROM miio_commands WHERE LINKED_OBJECT<>'' AND LINKED_PROPERTY<>''")['CNT'] ?? 0);
        return array(
            'devices' => $devices,
            'online' => $online,
            'offline' => max(0, $devices - $online),
            'bindings' => $bindings,
            'queue' => $this->queueCount(),
        );
    }
}
