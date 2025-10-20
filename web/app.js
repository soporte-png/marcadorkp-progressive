document.addEventListener('DOMContentLoaded', () => {
    initDashboardMetrics();
    initDispositionsManager();
});

function initDashboardMetrics() {
    const activeCampaignEl = document.getElementById('active-campaign-name');
    const availableAgentsEl = document.getElementById('available-agents');
    const dialingCallsEl = document.getElementById('dialing-calls');
    const connectedCallsEl = document.getElementById('connected-calls');
    const pendingLeadsEl = document.getElementById('pending-leads');

    const insightPanel = document.getElementById('insight-panel');
    const dispositionsCard = document.getElementById('dispositions-card');

    const createCard = document.getElementById('create-campaign-card');
    const lockedCard = document.getElementById('campaign-locked-card');
    const unlockButton = document.getElementById('unlock-create-form');
    let manualUnlock = false;

    if (unlockButton) {
        unlockButton.addEventListener('click', () => {
            manualUnlock = true;
            createCard?.classList.remove('hidden');
            lockedCard?.classList.add('hidden');
        });
    }

    const toggleCampaignCards = (hasActiveCampaign) => {
        if (!createCard || !lockedCard || manualUnlock) {
            return;
        }

        if (hasActiveCampaign) {
            createCard.classList.add('hidden');
            lockedCard.classList.remove('hidden');
        } else {
            createCard.classList.remove('hidden');
            lockedCard.classList.add('hidden');
        }
    };

    const toggleInsightPanels = (hasActiveCampaign) => {
        if (insightPanel) {
            insightPanel.classList.toggle('hidden', !hasActiveCampaign);
        }
    };

    const toggleDispositionsCard = (hasActiveCampaign) => {
        if (dispositionsCard) {
            dispositionsCard.classList.toggle('hidden', hasActiveCampaign);
        }
    };

    const parseNumber = (value) => {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : 0;
    };

    const updateDashboard = async () => {
        try {
            const response = await fetch('get_status.php', { cache: 'no-cache' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (activeCampaignEl) {
                activeCampaignEl.textContent = data.active_campaign_name || '--';
            }
            if (availableAgentsEl) {
                availableAgentsEl.textContent = parseNumber(data.available_agents);
            }
            if (dialingCallsEl) {
                dialingCallsEl.textContent = parseNumber(data.dialing_calls);
            }
            if (connectedCallsEl) {
                connectedCallsEl.textContent = parseNumber(data.connected_calls);
            }
            if (pendingLeadsEl) {
                pendingLeadsEl.textContent = parseNumber(data.pending_leads);
            }

            const hasActiveCampaign = Boolean(data.active_campaign_name && data.active_campaign_name !== '--');
            toggleCampaignCards(hasActiveCampaign);
            toggleInsightPanels(hasActiveCampaign);
            toggleDispositionsCard(hasActiveCampaign);
        } catch (error) {
            console.error('Error al actualizar el dashboard:', error);
        }
    };

    updateDashboard();
    setInterval(updateDashboard, 3000);
}

function initDispositionsManager() {
    const card = document.getElementById('dispositions-card');
    const form = document.getElementById('disposition-form');
    const resetButton = document.getElementById('reset-disposition-form');
    const tableBody = document.getElementById('dispositions-table-body');

    if (!card || !form || !tableBody) {
        return;
    }

    const idInput = document.getElementById('disposition-id');
    const labelInput = document.getElementById('disposition-label');
    const descriptionInput = document.getElementById('disposition-description');
    const sortInput = document.getElementById('disposition-sort');
    const activeInput = document.getElementById('disposition-active');
    const submitButton = form.querySelector('.btn-primary');

    const statusBanner = document.createElement('div');
    statusBanner.className = 'alert hidden';
    statusBanner.setAttribute('role', 'alert');
    card.insertBefore(statusBanner, form);

    const state = {
        items: new Map(),
        loading: false,
        editingId: null,
    };

    const showStatus = (message, kind = 'success') => {
        statusBanner.textContent = message;
        statusBanner.classList.remove('hidden', 'alert-success', 'alert-error');
        statusBanner.classList.add(kind === 'success' ? 'alert-success' : 'alert-error');
    };

    const clearStatus = () => {
        statusBanner.textContent = '';
        statusBanner.classList.add('hidden');
        statusBanner.classList.remove('alert-success', 'alert-error');
    };

    const formatTimestamp = (value) => {
        if (!value) {
            return '—';
        }

        const isoCandidate = value.replace(' ', 'T');
        const parsed = new Date(isoCandidate);
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleString(undefined, {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const buildActions = (id, isActive) => {
        const toggleLabel = isActive ? 'Desactivar' : 'Activar';
        return `
            <div class="row-actions">
                <button type="button" data-action="edit" data-id="${id}">Editar</button>
                <button type="button" data-action="toggle" data-id="${id}">${toggleLabel}</button>
                <button type="button" data-action="delete" data-id="${id}">Eliminar</button>
            </div>
        `;
    };

    const renderRows = (items) => {
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="empty-row">Sin disposiciones configuradas.</td></tr>';
            return;
        }

        const rows = items.map((item) => {
            const pillClass = item.is_active ? 'label-pill active' : 'label-pill inactive';
            const pillText = item.is_active ? 'Activa' : 'Inactiva';
            const updatedAt = item.updated_at || item.created_at;
            return `
                <tr data-id="${item.id}">
                    <td>${item.sort_order}</td>
                    <td>${item.label}</td>
                    <td>${item.description ? item.description : '—'}</td>
                    <td><span class="${pillClass}">${pillText}</span></td>
                    <td>${formatTimestamp(updatedAt)}</td>
                    <td class="actions-col">${buildActions(item.id, item.is_active)}</td>
                </tr>
            `;
        });

        tableBody.innerHTML = rows.join('');
    };

    const loadDispositions = async () => {
        if (state.loading) {
            return;
        }

        state.loading = true;
        tableBody.innerHTML = '<tr><td colspan="6" class="empty-row">Cargando disposiciones…</td></tr>';

        try {
            const response = await fetch(`dispositions_api.php?scope=all&_=${Date.now()}`, { cache: 'no-store' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!payload.success || !Array.isArray(payload.data)) {
                throw new Error('Respuesta inválida del servidor.');
            }

            state.items.clear();
            payload.data.forEach((item) => {
                state.items.set(String(item.id), item);
            });

            renderRows(payload.data);
        } catch (error) {
            console.error('Error al cargar disposiciones:', error);
            tableBody.innerHTML = '<tr><td colspan="6" class="empty-row">No se pudieron cargar las disposiciones.</td></tr>';
            showStatus(error.message, 'error');
        } finally {
            state.loading = false;
        }
    };

    const sendRequest = async (body) => {
        const response = await fetch('dispositions_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Ocurrió un error al procesar la solicitud.');
        }

        return result;
    };

    const resetForm = () => {
        form.reset();
        idInput.value = '';
        sortInput.value = '0';
        activeInput.checked = true;
        state.editingId = null;
        submitButton.textContent = 'Guardar disposición';
    };

    const fillForm = (item) => {
        idInput.value = item.id;
        labelInput.value = item.label;
        descriptionInput.value = item.description ?? '';
        sortInput.value = String(item.sort_order ?? 0);
        activeInput.checked = Boolean(item.is_active);
        state.editingId = item.id;
        submitButton.textContent = 'Actualizar disposición';
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            action: 'save',
            id: idInput.value ? Number(idInput.value) : undefined,
            label: labelInput.value.trim(),
            description: descriptionInput.value.trim(),
            sort_order: Number.parseInt(sortInput.value, 10) || 0,
            is_active: activeInput.checked ? 1 : 0,
        };

        if (!payload.label) {
            showStatus('El nombre de la disposición es obligatorio.', 'error');
            labelInput.focus();
            return;
        }

        try {
            clearStatus();
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando…';

            await sendRequest(payload);
            showStatus('Disposición guardada correctamente.');
            resetForm();
            await loadDispositions();
        } catch (error) {
            console.error('Error al guardar la disposición:', error);
            showStatus(error.message, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = state.editingId ? 'Actualizar disposición' : 'Guardar disposición';
        }
    });

    resetButton.addEventListener('click', () => {
        resetForm();
        clearStatus();
    });

    tableBody.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const action = target.dataset.action;
        const targetId = target.dataset.id;
        if (!action || !targetId) {
            return;
        }

        const item = state.items.get(targetId);
        if (!item) {
            showStatus('No se encontró la disposición seleccionada.', 'error');
            return;
        }

        if (action === 'edit') {
            fillForm(item);
            clearStatus();
            return;
        }

        if (action === 'toggle') {
            try {
                await sendRequest({ action: 'toggle', id: item.id });
                showStatus('Estado actualizado.');
                await loadDispositions();
            } catch (error) {
                console.error('Error al cambiar estado:', error);
                showStatus(error.message, 'error');
            }
            return;
        }

        if (action === 'delete') {
            const confirmed = window.confirm(`¿Eliminar de forma permanente "${item.label}"?`);
            if (!confirmed) {
                return;
            }

            try {
                await sendRequest({ action: 'delete', id: item.id });
                showStatus('Disposición eliminada.');
                await loadDispositions();
            } catch (error) {
                console.error('Error al eliminar la disposición:', error);
                showStatus(error.message, 'error');
            }
        }
    });

    resetForm();
    loadDispositions();
}

