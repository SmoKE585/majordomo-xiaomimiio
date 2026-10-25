(() => {
    'use strict';

    const rootOf = element => element.closest('[data-md-xiaomi]');

    const post = async (root, op, data = {}) => {
        const body = new URLSearchParams(Object.assign({op, _token: root.dataset.mdCsrf || '', _md_csrf: root.dataset.mdCsrf || ''}, data));
        const response = await fetch(root.dataset.mdAjaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest'},
            body
        });
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Сервер вернул не JSON: ' + response.status);
        }
        if (!payload || payload.ok !== true) {
            throw new Error((payload && payload.error) || ('Ошибка запроса: ' + response.status));
        }
        return payload;
    };

    const logTo = (element, text) => {
        if (!element) return;
        const line = document.createElement('span');
        line.className = 'md-xiaomi-log__line';
        line.textContent = text;
        element.append(line, document.createElement('br'));
        element.scrollTop = element.scrollHeight;
    };

    const initImages = page => {
        page.querySelectorAll('[data-md-xiaomi-photo]').forEach(img => {
            const wrap = img.closest('[data-md-xiaomi-image]');
            const loaded = () => wrap.classList.add('md-xiaomi-image--loaded');
            const failed = () => {
                img.hidden = true;
                wrap.classList.remove('md-xiaomi-image--loaded');
            };
            img.addEventListener('load', loaded, {once: true});
            img.addEventListener('error', failed, {once: true});
            if (img.complete) {
                if (img.naturalWidth > 0) loaded(); else failed();
            }
        });
    };

    const initHelp = page => {
        page.querySelectorAll('.md-xiaomi-help [data-toggle="collapse"]').forEach(toggle => {
            const target = toggle.getAttribute('href') || '';
            if (!target || target === '#') return;
            toggle.setAttribute('data-bs-toggle', 'collapse');
            toggle.setAttribute('data-bs-target', target);
            toggle.removeAttribute('data-toggle');
        });
    };

    const initLan = page => {
        page.querySelectorAll('[data-md-xiaomi-lan]').forEach(box => {
            const root = rootOf(box);
            const log = box.querySelector('[data-md-xiaomi-lan-log]');
            const run = async mode => {
                const button = box.querySelector(mode === 'on' ? '[data-md-xiaomi-lan-on]' : '[data-md-xiaomi-lan-off]');
                if (button.disabled) return;
                button.disabled = true;
                try {
                    const payload = await post(root, 'lan_mode', {device_id: box.dataset.deviceId, mode});
                    const key = payload.data && payload.data.key ? payload.data.key : 'не активен';
                    logTo(log, mode === 'on' ? 'LAN-режим включён, ключ: ' + key : 'LAN-режим выключен.');
                    const current = box.querySelector('[data-md-xiaomi-lan-current]');
                    if (current) current.textContent = key;
                } catch (error) {
                    logTo(log, 'Не удалось: ' + error.message);
                } finally {
                    button.disabled = false;
                }
            };
            box.querySelector('[data-md-xiaomi-lan-on]')?.addEventListener('click', () => run('on'));
            box.querySelector('[data-md-xiaomi-lan-off]')?.addEventListener('click', () => run('off'));
        });
    };

    const initIr = page => {
        page.querySelectorAll('[data-md-xiaomi-ir]').forEach(box => {
            const root = rootOf(box);
            const log = box.querySelector('[data-md-xiaomi-ir-log]');
            const model = box.dataset.irModel || '';
            let timer = null;
            let key = 0;
            let deadline = 0;

            const stopPolling = () => {
                if (timer) clearTimeout(timer);
                timer = null;
                deadline = 0;
            };

            const poll = async () => {
                if (!timer && deadline === 0) return;
                timer = null;
                if (Date.now() > deadline) {
                    logTo(log, 'Время обучения истекло.');
                    stopPolling();
                    return;
                }
                try {
                    const payload = await post(root, 'ir_learn_poll', {device_id: box.dataset.deviceId, key});
                    const result = payload.data && payload.data.result;
                    const code = result && typeof result === 'object' ? (result.code || '') : '';
                    if (code && String(code).length > 10) {
                        logTo(log, 'Код получен: ' + code);
                        stopPolling();
                        return;
                    }
                } catch (error) {
                    logTo(log, 'Ожидание кода: ' + error.message);
                }
                timer = setTimeout(poll, 2000);
            };

            box.querySelector('[data-md-xiaomi-ir-start]')?.addEventListener('click', async () => {
                stopPolling();
                key = Math.floor(Math.random() * 100000) + 1;
                const seconds = model === 'lumi.acpartner.v3' ? 30 : 10;
                try {
                    logTo(log, 'Обучение начато. Нажмите кнопку на пульте. Ожидание ' + seconds + ' с.');
                    await post(root, 'ir_learn_start', {device_id: box.dataset.deviceId, key});
                    deadline = Date.now() + seconds * 1000;
                    timer = setTimeout(poll, 900);
                } catch (error) {
                    logTo(log, 'Не удалось начать обучение: ' + error.message);
                    stopPolling();
                }
            });

            box.querySelector('[data-md-xiaomi-ir-stop]')?.addEventListener('click', async () => {
                stopPolling();
                try {
                    await post(root, 'ir_learn_stop', {device_id: box.dataset.deviceId, key});
                    logTo(log, 'Обучение остановлено.');
                } catch (error) {
                    logTo(log, 'Не удалось остановить: ' + error.message);
                }
            });
        });
    };

    const initZigbee = page => {
        const types = {
            1: 'switch', 2: 'motion', 3: 'magnet', 7: 'ctrl_neutral2', 8: 'cube', 9: 'ctrl_neutral1',
            10: 'sensor_ht', 11: 'plug', 12: '86sw2', 13: 'curtain', 14: '86sw1', 15: 'smoke',
            19: 'weather.v1', 20: 'ctrl_ln1', 21: 'ctrl_ln2', 51: 'sensor_switch.aq2',
            52: 'sensor_motion.aq2', 53: 'sensor_magnet.aq2', 54: 'lumi.relay.c2acn01',
            55: 'sensor_wleak.aq1', 56: 'vibration', 59: 'lock.aq1', 63: 'ctrl_ln1.aq1',
            64: 'ctrl_ln2.aq1', 65: '86plug', 68: 'sensor_cube.aqgl01', 70: 'lock.acn02',
            133: 'remote.b1acn01', 134: 'remote.b186acn01', 135: 'remote.b286acn01', 1003: 'natgas'
        };
        page.querySelectorAll('[data-md-xiaomi-zigbee]').forEach(box => {
            const root = rootOf(box);
            const body = box.querySelector('[data-md-xiaomi-zigbee-body]');
            const log = box.querySelector('[data-md-xiaomi-zigbee-log]');
            const assets = root.dataset.mdAssets || '';
            let busy = false;

            const render = rows => {
                body.textContent = '';
                if (!rows.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 5;
                    td.innerHTML = '<span class="md-table__signal">Шлюз не вернул ни одного устройства.</span>';
                    tr.append(td);
                    body.append(tr);
                    return;
                }
                rows.forEach(row => {
                    const tr = document.createElement('tr');
                    const number = document.createElement('td');
                    number.textContent = row.index;
                    const device = document.createElement('td');
                    const name = document.createElement('strong');
                    name.textContent = row.id;
                    const small = document.createElement('small');
                    small.className = 'font-monospace';
                    small.textContent = 'lumi.' + row.id;
                    device.append(name, document.createElement('br'), small);
                    const modelCell = document.createElement('td');
                    modelCell.textContent = row.model;
                    const stateCell = document.createElement('td');
                    const state = document.createElement('span');
                    state.className = 'md-status md-status--' + (row.online ? 'online' : 'offline');
                    state.innerHTML = '<span class="md-status__dot" aria-hidden="true"></span>';
                    const label = document.createElement('span');
                    label.className = 'md-status__label';
                    label.textContent = row.online ? 'Онлайн' : 'Офлайн';
                    state.append(label);
                    stateCell.append(state);
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-end';
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'md-btn md-btn--ghost md-btn--sm';
                    remove.title = 'Отвязать устройство';
                    remove.setAttribute('aria-label', 'Отвязать ' + row.id);
                    remove.innerHTML = '<i class="ti ti-unlink md-btn__icon" aria-hidden="true"></i>';
                    remove.addEventListener('click', async () => {
                        if (busy || !confirm('Отвязать устройство ' + row.id + ' от шлюза?')) return;
                        busy = true;
                        try {
                            await post(root, 'zigbee_remove', {device_id: box.dataset.deviceId, sid: row.id});
                            logTo(log, 'Устройство ' + row.id + ' отвязано.');
                            await load();
                        } catch (error) {
                            logTo(log, 'Не удалось отвязать: ' + error.message);
                        } finally {
                            busy = false;
                        }
                    });
                    actionCell.append(remove);
                    tr.append(number, device, modelCell, stateCell, actionCell);
                    body.append(tr);
                });
            };

            const load = async () => {
                logTo(log, 'Запрашиваю список устройств у шлюза…');
                try {
                    const payload = await post(root, 'zigbee_list', {device_id: box.dataset.deviceId});
                    const result = payload.data && Array.isArray(payload.data.result) ? payload.data.result : [];
                    const rows = [];
                    let index = 1;
                    for (let i = 0; i + 2 < result.length; i += 5) {
                        let id = String(result[i] || '');
                        if (id.indexOf('lumi.') === 0) id = id.substring(5);
                        if (id === '0') continue;
                        rows.push({index: index++, id, model: types[result[i + 1]] || ('тип ' + result[i + 1]), online: Number(result[i + 2]) === 1});
                    }
                    render(rows);
                    logTo(log, 'Устройств в сети шлюза: ' + rows.length + '.');
                } catch (error) {
                    logTo(log, 'Не удалось загрузить список: ' + error.message);
                }
            };

            box.querySelector('[data-md-xiaomi-zigbee-load]')?.addEventListener('click', load);
            box.querySelector('[data-md-xiaomi-zigbee-join]')?.addEventListener('click', async event => {
                const button = event.currentTarget;
                button.disabled = true;
                try {
                    await post(root, 'zigbee_join', {device_id: box.dataset.deviceId, seconds: 60});
                    logTo(log, 'Сопряжение разрешено на 60 секунд. Переведите устройство в режим подключения.');
                } catch (error) {
                    logTo(log, 'Не удалось включить сопряжение: ' + error.message);
                } finally {
                    setTimeout(() => { button.disabled = false; }, 3000);
                }
            });
        });
    };

    const toast = (type, title, text) => {
        if (window.MDJUIToast) window.MDJUIToast.show({type, title, text});
    };

    const initRefresh = page => {
        page.addEventListener('submit', async event => {
            const form = event.target.closest('form[data-md-refresh-device]');
            if (!form) return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const payload = await post(page, 'refresh', {device_id: form.dataset.mdRefreshDevice});
                const message = payload.data && payload.data.message ? payload.data.message : 'Команда поставлена в очередь.';
                toast('success', 'Запрос отправлен', message);
            } catch (error) {
                toast('danger', 'Не удалось обновить', error.message);
            } finally {
                if (button) button.disabled = false;
            }
        });
    };
    const init = (root = document) => {
        root.querySelectorAll('[data-md-xiaomi]').forEach(page => {
            if (page.dataset.mdXiaomiReady) return;
            page.dataset.mdXiaomiReady = '1';
            initImages(page);
            initHelp(page);
            initRefresh(page);
            initLan(page);
            initIr(page);
            initZigbee(page);
        });
    };

    if (window.MDJAdminUI) window.MDJAdminUI.registerModuleUI('xiaomimiio', {init});
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
})();
