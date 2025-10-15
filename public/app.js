const API_BASE = '../backend/api/index.php';
const state = {
    token: null,
    roomTypes: [],
    ratePlans: [],
    rooms: [],
    roles: [],
    loadedSections: new Set(),
};

const notificationEl = document.getElementById('notification');
const tokenInput = document.getElementById('api-token');
const dashboardDateInput = document.getElementById('dashboard-date');
const reportStartInput = document.getElementById('report-start');
const reportEndInput = document.getElementById('report-end');

function showMessage(message, type = 'info', timeout = 4000) {
    if (!notificationEl) {
        return;
    }
    notificationEl.textContent = message;
    notificationEl.className = `notification show ${type}`;
    if (timeout) {
        setTimeout(() => {
            notificationEl.className = 'notification';
        }, timeout);
    }
}

function requireToken() {
    if (!state.token) {
        showMessage('Bitte speichern Sie einen gültigen API-Token, um Daten laden zu können.', 'error');
        return false;
    }
    return true;
}

async function apiFetch(path, options = {}) {
    const { skipAuth = false } = options;
    const normalizedPath = path ? path.replace(/^\/+/, '') : '';
    const url = normalizedPath ? `${API_BASE}/${normalizedPath}` : API_BASE;
    const headers = new Headers(options.headers || {});
    if (!skipAuth) {
        if (!requireToken()) {
            throw new Error('Kein API-Token gesetzt.');
        }
        headers.set('X-API-Key', state.token);
    }
    if (options.body && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, { ...options, headers });
    if (!response.ok) {
        let message = `${response.status} ${response.statusText}`;
        try {
            const payload = await response.json();
            if (payload && payload.error) {
                message = payload.error;
            }
        } catch (error) {
            // ignore json parse errors
        }
        throw new Error(message);
    }

    try {
        return await response.json();
    } catch (error) {
        return null;
    }
}

function setToken(token) {
    state.token = token || null;
    if (state.token) {
        localStorage.setItem('realpms_api_token', state.token);
        showMessage('API-Token gespeichert. Daten werden geladen...', 'success');
        bootstrap();
    } else {
        localStorage.removeItem('realpms_api_token');
        state.loadedSections.clear();
        showMessage('API-Token entfernt. Bitte neuen Token speichern.', 'info');
    }
}

document.getElementById('token-form').addEventListener('submit', (event) => {
    event.preventDefault();
    const token = tokenInput.value.trim();
    if (!token) {
        showMessage('Token darf nicht leer sein.', 'error');
        return;
    }
    setToken(token);
});

document.getElementById('clear-token').addEventListener('click', () => {
    tokenInput.value = '';
    setToken(null);
});

function setDefaultDates() {
    const today = new Date();
    const isoToday = today.toISOString().slice(0, 10);
    if (!dashboardDateInput.value) {
        dashboardDateInput.value = isoToday;
    }
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
    if (!reportStartInput.value) {
        reportStartInput.value = monthStart;
    }
    if (!reportEndInput.value) {
        reportEndInput.value = isoToday;
    }
}

function showSection(sectionId) {
    document.querySelectorAll('.main-nav button').forEach((button) => {
        button.classList.toggle('active', button.dataset.section === sectionId);
    });
    document.querySelectorAll('section[data-section]').forEach((section) => {
        section.classList.toggle('active', section.id === sectionId);
    });
    if (sectionLoaders[sectionId]) {
        sectionLoaders[sectionId]();
    }
}

document.querySelectorAll('.main-nav button').forEach((button) => {
    button.addEventListener('click', () => showSection(button.dataset.section));
});

function formatDate(value) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString();
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleString();
}

function formatCurrency(amount, currency = 'EUR') {
    if (amount === null || amount === undefined || amount === '') {
        return '';
    }
    const number = Number(amount);
    if (Number.isNaN(number)) {
        return amount;
    }
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(number);
}

function toSqlDateTime(value) {
    if (!value) {
        return null;
    }
    if (value.includes('T')) {
        const [date, time] = value.split('T');
        const normalizedTime = time.length === 5 ? `${time}:00` : time;
        return `${date} ${normalizedTime}`;
    }
    return value;
}

function renderTable(containerId, columns, rows, emptyState = 'Keine Daten vorhanden') {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }
    container.innerHTML = '';
    if (!rows || rows.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'muted';
        empty.textContent = emptyState;
        container.appendChild(empty);
        return;
    }
    const table = document.createElement('table');
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    columns.forEach((column) => {
        const th = document.createElement('th');
        th.textContent = column.label;
        headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    rows.forEach((row) => {
        const tr = document.createElement('tr');
        columns.forEach((column) => {
            const td = document.createElement('td');
            const value = column.render ? column.render(row) : row[column.key];
            td.innerHTML = value === undefined || value === null ? '' : value;
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
}

async function bootstrap() {
    if (!requireToken()) {
        return;
    }
    try {
        const [roomTypes, ratePlans, rooms, roles] = await Promise.all([
            apiFetch('room-types'),
            apiFetch('rate-plans'),
            apiFetch('rooms'),
            apiFetch('roles'),
        ]);
        state.roomTypes = roomTypes;
        state.ratePlans = ratePlans;
        state.rooms = rooms;
        state.roles = roles;
        populateRoomTypeSelects();
        populateRatePlanSelect();
        populateRoomOptions();
        populateRoleCheckboxes();
        populateRoomTypeList();
        populateRatePlanList();
        state.loadedSections.clear();
        await Promise.all([
            loadDashboard(true),
            loadReservations(true),
            loadRooms(true),
            loadHousekeeping(true),
        ]);
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

function populateRoomTypeSelects() {
    const roomSelect = document.querySelector('#room-form select[name="room_type"]');
    if (roomSelect) {
        roomSelect.innerHTML = state.roomTypes.map((type) => `<option value="${type.id}">${type.name}</option>`).join('');
    }
}

function populateRatePlanSelect() {
    const ratePlanSelect = document.querySelector('#reservation-form select[name="rate_plan"]');
    if (ratePlanSelect) {
        const options = state.ratePlans.map((plan) => `<option value="${plan.id}">${plan.name}</option>`);
        ratePlanSelect.innerHTML = `<option value="">Ohne Rate-Plan</option>${options.join('')}`;
    }
}

function populateRoomOptions() {
    const reservationRoomSelect = document.querySelector('#reservation-form select[name="rooms"]');
    const taskRoomSelect = document.querySelector('#task-form select[name="room"]');
    const options = state.rooms.map((room) => `<option value="${room.id}">${room.room_number} (${room.room_type_name})</option>`);
    if (reservationRoomSelect) {
        reservationRoomSelect.innerHTML = options.join('');
    }
    if (taskRoomSelect) {
        taskRoomSelect.innerHTML = `<option value="">Kein Zimmer</option>${options.join('')}`;
    }
}

function populateRoleCheckboxes() {
    const container = document.getElementById('user-roles');
    if (!container) {
        return;
    }
    container.innerHTML = '';
    state.roles.forEach((role) => {
        const label = document.createElement('label');
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.value = role.id;
        label.appendChild(input);
        label.append(` ${role.name}`);
        container.appendChild(label);
    });
}

function populateRoomTypeList() {
    renderTable('room-types-list', [
        { key: 'name', label: 'Name' },
        { key: 'base_rate', label: 'Grundpreis', render: (row) => formatCurrency(row.base_rate, row.currency || 'EUR') },
    ], state.roomTypes);
}

function populateRatePlanList() {
    renderTable('rate-plans-list', [
        { key: 'name', label: 'Name' },
        { key: 'base_price', label: 'Grundpreis', render: (row) => formatCurrency(row.base_price, row.currency || 'EUR') },
        { key: 'cancellation_policy', label: 'Stornobedingungen' },
    ], state.ratePlans);
}

async function loadDashboard(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('dashboard')) {
        return;
    }
    try {
        const targetDate = dashboardDateInput.value || new Date().toISOString().slice(0, 10);
        const startOfMonth = new Date(targetDate);
        startOfMonth.setDate(1);
        const monthStart = startOfMonth.toISOString().slice(0, 10);
        const endOfMonth = new Date(startOfMonth.getFullYear(), startOfMonth.getMonth() + 1, 0).toISOString().slice(0, 10);
        const [occupancy, revenue, openTasks] = await Promise.all([
            apiFetch(`reports/occupancy?start=${targetDate}&end=${targetDate}`),
            apiFetch(`reports/revenue?start=${monthStart}&end=${endOfMonth}`),
            apiFetch('housekeeping/tasks?status=open'),
        ]);
        const occupancyToday = occupancy[0] || null;
        document.getElementById('dash-occupancy-rate').textContent = occupancyToday ? `${occupancyToday.occupancy_rate}%` : '-- %';
        document.getElementById('dash-occupancy-detail').textContent = occupancyToday
            ? `${occupancyToday.occupied_rooms} von ${occupancyToday.available_rooms} Zimmern belegt`
            : 'Keine Daten';
        const invoiceTotals = revenue?.invoices || { invoice_total: 0, tax_total: 0 };
        document.getElementById('dash-revenue-total').textContent = formatCurrency(invoiceTotals.invoice_total || 0);
        document.getElementById('dash-revenue-detail').textContent = `Steueranteil: ${formatCurrency(invoiceTotals.tax_total || 0)}`;
        document.getElementById('dash-open-tasks').textContent = openTasks.length;
        renderTable('occupancy-table', [
            { key: 'date', label: 'Datum', render: (row) => formatDate(row.date) },
            { key: 'occupied_rooms', label: 'Belegte Zimmer' },
            { key: 'available_rooms', label: 'Gesamtzimmer' },
            { key: 'occupancy_rate', label: 'Auslastung', render: (row) => `${row.occupancy_rate}%` },
        ], occupancy);
        state.loadedSections.add('dashboard');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadReservations(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('reservations')) {
        return;
    }
    try {
        const reservations = await apiFetch('reservations');
        renderTable('reservations-list', [
            { key: 'confirmation_number', label: 'Bestätigungsnr.' },
            { key: 'guest', label: 'Gast', render: (row) => `${row.first_name || ''} ${row.last_name || ''}`.trim() },
            { key: 'check_in_date', label: 'Check-in', render: (row) => formatDate(row.check_in_date) },
            { key: 'check_out_date', label: 'Check-out', render: (row) => formatDate(row.check_out_date) },
            { key: 'status', label: 'Status' },
            { key: 'rooms', label: 'Zimmer', render: (row) => (row.rooms || []).map((room) => room.room_number).join(', ') },
            { key: 'total_amount', label: 'Gesamt', render: (row) => formatCurrency(row.total_amount, row.currency || 'EUR') },
        ], reservations);
        state.loadedSections.add('reservations');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadRooms(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('rooms')) {
        return;
    }
    try {
        const rooms = await apiFetch('rooms');
        state.rooms = rooms;
        populateRoomOptions();
        renderTable('rooms-list', [
            { key: 'room_number', label: 'Zimmer' },
            { key: 'room_type_name', label: 'Kategorie' },
            { key: 'floor', label: 'Etage' },
            { key: 'status', label: 'Status' },
            { key: 'notes', label: 'Notizen' },
        ], rooms);
        state.loadedSections.add('rooms');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadHousekeeping(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('housekeeping')) {
        return;
    }
    try {
        const tasks = await apiFetch('housekeeping/tasks');
        renderTable('tasks-list', [
            { key: 'title', label: 'Titel' },
            { key: 'room_number', label: 'Zimmer' },
            { key: 'status', label: 'Status' },
            { key: 'due_date', label: 'Fällig', render: (row) => formatDateTime(row.due_date) },
            { key: 'description', label: 'Beschreibung' },
        ], tasks);
        state.loadedSections.add('housekeeping');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadBilling(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('billing')) {
        return;
    }
    try {
        const [invoices, payments] = await Promise.all([
            apiFetch('invoices'),
            apiFetch('payments'),
        ]);
        renderTable('invoices-list', [
            { key: 'invoice_number', label: 'Rechnungsnr.' },
            { key: 'reservation_id', label: 'Reservierung' },
            { key: 'issue_date', label: 'Ausgestellt am', render: (row) => formatDate(row.issue_date) },
            { key: 'due_date', label: 'Fällig am', render: (row) => formatDate(row.due_date) },
            { key: 'status', label: 'Status' },
            { key: 'total_amount', label: 'Summe', render: (row) => formatCurrency(row.total_amount) },
        ], invoices);
        renderTable('payments-list', [
            { key: 'invoice_number', label: 'Rechnungsnr.' },
            { key: 'method', label: 'Methode' },
            { key: 'amount', label: 'Betrag', render: (row) => formatCurrency(row.amount, row.currency || 'EUR') },
            { key: 'paid_at', label: 'Bezahlt am', render: (row) => formatDateTime(row.paid_at) },
            { key: 'reference', label: 'Referenz' },
        ], payments);
        state.loadedSections.add('billing');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadReports(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('reports')) {
        return;
    }
    try {
        const start = reportStartInput.value || new Date().toISOString().slice(0, 10);
        const end = reportEndInput.value || start;
        const [occupancy, revenue, forecast] = await Promise.all([
            apiFetch(`reports/occupancy?start=${start}&end=${end}`),
            apiFetch(`reports/revenue?start=${start}&end=${end}`),
            apiFetch(`reports/forecast?start=${start}&end=${end}`),
        ]);
        renderTable('report-occupancy', [
            { key: 'date', label: 'Datum', render: (row) => formatDate(row.date) },
            { key: 'occupied_rooms', label: 'Belegte Zimmer' },
            { key: 'available_rooms', label: 'Gesamtzimmer' },
            { key: 'occupancy_rate', label: 'Auslastung', render: (row) => `${row.occupancy_rate}%` },
        ], occupancy);
        renderTable('report-revenue', [
            { key: 'metric', label: 'Kennzahl' },
            { key: 'value', label: 'Wert' },
        ], [
            { metric: 'Rechnungsvolumen', value: formatCurrency(revenue?.invoices?.invoice_total || 0) },
            { metric: 'Steueranteil', value: formatCurrency(revenue?.invoices?.tax_total || 0) },
            { metric: 'Zahlungen (Summe)', value: formatCurrency((revenue?.payments || []).reduce((sum, payment) => sum + Number(payment.total_amount || payment.amount || 0), 0)) },
        ]);
        renderTable('report-forecast', [
            { key: 'check_in_date', label: 'Check-in', render: (row) => formatDate(row.check_in_date) },
            { key: 'check_out_date', label: 'Check-out', render: (row) => formatDate(row.check_out_date) },
            { key: 'rooms', label: 'Zimmer' },
            { key: 'total_amount', label: 'Erwarteter Umsatz', render: (row) => formatCurrency(row.total_amount) },
        ], forecast?.reservations || []);
        state.loadedSections.add('reports');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadUsers(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('users')) {
        return;
    }
    try {
        const [users, roles] = await Promise.all([
            apiFetch('users'),
            apiFetch('roles'),
        ]);
        state.roles = roles;
        populateRoleCheckboxes();
        renderTable('users-list', [
            { key: 'name', label: 'Name' },
            { key: 'email', label: 'E-Mail' },
            { key: 'roles', label: 'Rollen', render: (row) => (row.roles || []).map((role) => role.name).join(', ') },
            { key: 'created_at', label: 'Angelegt am', render: (row) => formatDateTime(row.created_at) },
        ], users);
        renderTable('roles-list', [
            { key: 'name', label: 'Name' },
            { key: 'description', label: 'Beschreibung' },
        ], roles);
        state.loadedSections.add('users');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadGuests(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('guests')) {
        return;
    }
    try {
        const guests = await apiFetch('guests');
        renderTable('guests-list', [
            { key: 'first_name', label: 'Vorname' },
            { key: 'last_name', label: 'Nachname' },
            { key: 'email', label: 'E-Mail' },
            { key: 'phone', label: 'Telefon' },
            { key: 'notes', label: 'Notizen' },
        ], guests);
        state.loadedSections.add('guests');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadIntegrations(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('integrations')) {
        return;
    }
    try {
        const integrations = await apiFetch('integrations');
        const container = document.getElementById('integrations-list');
        container.innerHTML = '';
        Object.entries(integrations).forEach(([key, value]) => {
            const card = document.createElement('div');
            card.className = 'integration-card';
            card.innerHTML = `<h4>${key.replace(/_/g, ' ').toUpperCase()}</h4><p>Status: <strong>${value.status}</strong></p>${value.message ? `<p class="muted">${value.message}</p>` : ''}`;
            container.appendChild(card);
        });
        state.loadedSections.add('integrations');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function lookupGuestReservation(confirmation) {
    try {
        const reservation = await apiFetch(`guest-portal/reservations/${encodeURIComponent(confirmation)}`, { skipAuth: true });
        renderTable('guest-portal-result', [
            { key: 'confirmation_number', label: 'Bestätigungsnr.' },
            { key: 'first_name', label: 'Vorname' },
            { key: 'last_name', label: 'Nachname' },
            { key: 'check_in_date', label: 'Check-in', render: (row) => formatDate(row.check_in_date) },
            { key: 'check_out_date', label: 'Check-out', render: (row) => formatDate(row.check_out_date) },
            { key: 'status', label: 'Status' },
        ], reservation ? [reservation] : []);
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

function addInvoiceItemRow() {
    const container = document.getElementById('invoice-items');
    const row = document.createElement('div');
    row.className = 'invoice-item-row';
    row.innerHTML = `
        <label>Beschreibung
            <input type="text" name="description" required>
        </label>
        <label>Menge
            <input type="number" name="quantity" min="0" step="0.01" value="1">
        </label>
        <label>Einzelpreis
            <input type="number" name="unit_price" min="0" step="0.01" value="0">
        </label>
        <label>Steuer %
            <input type="number" name="tax_rate" min="0" step="0.01" value="0">
        </label>
        <button type="button" class="secondary remove-item">Entfernen</button>
    `;
    row.querySelector('.remove-item').addEventListener('click', () => {
        row.remove();
        if (!container.querySelector('.invoice-item-row')) {
            addInvoiceItemRow();
        }
    });
    container.appendChild(row);
}

document.getElementById('add-invoice-item').addEventListener('click', (event) => {
    event.preventDefault();
    addInvoiceItemRow();
});

addInvoiceItemRow();

const sectionLoaders = {
    dashboard: () => loadDashboard(true),
    reservations: () => loadReservations(true),
    rooms: () => loadRooms(true),
    housekeeping: () => loadHousekeeping(true),
    billing: () => loadBilling(true),
    reports: () => loadReports(true),
    users: () => loadUsers(true),
    guests: () => loadGuests(true),
    integrations: () => loadIntegrations(true),
};

// Form submissions

document.getElementById('reservation-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const rooms = Array.from(form.rooms.selectedOptions).map((option) => Number(option.value));
    if (rooms.length === 0) {
        showMessage('Bitte mindestens ein Zimmer auswählen.', 'error');
        return;
    }
    const payload = {
        check_in_date: form.check_in.value,
        check_out_date: form.check_out.value,
        adults: Number(form.adults.value || 1),
        children: Number(form.children.value || 0),
        rate_plan_id: form.rate_plan.value ? Number(form.rate_plan.value) : null,
        rooms,
        guest: {
            first_name: form.guest_first.value,
            last_name: form.guest_last.value,
            email: form.guest_email.value || null,
            phone: form.guest_phone.value || null,
        },
        total_amount: form.total_amount.value ? Number(form.total_amount.value) : null,
        currency: form.currency.value || 'EUR',
        status: form.status.value,
        booked_via: form.booked_via.value || null,
    };
    try {
        await apiFetch('reservations', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        populateRatePlanSelect();
        showMessage('Reservierung wurde angelegt.', 'success');
        loadReservations(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('reload-reservations').addEventListener('click', () => loadReservations(true));
document.getElementById('reload-rooms').addEventListener('click', () => loadRooms(true));
document.getElementById('reload-tasks').addEventListener('click', () => loadHousekeeping(true));
document.getElementById('reload-invoices').addEventListener('click', () => loadBilling(true));
document.getElementById('reload-payments').addEventListener('click', () => loadBilling(true));
document.getElementById('reload-users').addEventListener('click', () => loadUsers(true));
document.getElementById('reload-guests').addEventListener('click', () => loadGuests(true));
document.getElementById('reload-integrations').addEventListener('click', () => loadIntegrations(true));
document.getElementById('refresh-dashboard').addEventListener('click', () => loadDashboard(true));
document.getElementById('refresh-reports').addEventListener('click', () => loadReports(true));

document.getElementById('room-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        room_number: form.room_number.value,
        room_type_id: Number(form.room_type.value),
        floor: form.floor.value || null,
        status: form.status.value,
        notes: form.notes.value || null,
    };
    try {
        await apiFetch('rooms', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Zimmer wurde gespeichert.', 'success');
        loadRooms(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('room-type-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        name: form.name.value,
        base_rate: form.base_price.value ? Number(form.base_price.value) : null,
        description: form.description.value || null,
    };
    try {
        await apiFetch('room-types', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Zimmerkategorie erstellt.', 'success');
        const roomTypes = await apiFetch('room-types');
        state.roomTypes = roomTypes;
        populateRoomTypeSelects();
        populateRoomTypeList();
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('rate-plan-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        name: form.name.value,
        base_price: form.base_price.value ? Number(form.base_price.value) : null,
        currency: form.currency.value || 'EUR',
        cancellation_policy: form.cancellation_policy.value || null,
    };
    try {
        await apiFetch('rate-plans', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Rate-Plan angelegt.', 'success');
        const ratePlans = await apiFetch('rate-plans');
        state.ratePlans = ratePlans;
        populateRatePlanSelect();
        populateRatePlanList();
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('task-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        room_id: form.room.value ? Number(form.room.value) : null,
        title: form.title.value,
        status: form.status.value,
        due_date: toSqlDateTime(form.due_date.value),
        description: form.description.value || null,
    };
    try {
        await apiFetch('housekeeping/tasks', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Aufgabe gespeichert.', 'success');
        loadHousekeeping(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('invoice-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const items = Array.from(document.querySelectorAll('#invoice-items .invoice-item-row')).map((row) => {
        const description = row.querySelector('input[name="description"]').value;
        if (!description) {
            return null;
        }
        return {
            description,
            quantity: Number(row.querySelector('input[name="quantity"]').value || 1),
            unit_price: Number(row.querySelector('input[name="unit_price"]').value || 0),
            tax_rate: Number(row.querySelector('input[name="tax_rate"]').value || 0),
        };
    }).filter(Boolean);
    if (items.length === 0) {
        showMessage('Bitte mindestens eine Rechnungsposition erfassen.', 'error');
        return;
    }
    const payload = {
        reservation_id: Number(form.reservation_id.value),
        issue_date: form.issue_date.value || null,
        due_date: form.due_date.value || null,
        items,
    };
    try {
        await apiFetch('invoices', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        document.getElementById('invoice-items').innerHTML = '';
        addInvoiceItemRow();
        showMessage('Rechnung erstellt.', 'success');
        loadBilling(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('payment-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        invoice_id: Number(form.invoice_id.value),
        method: form.method.value,
        amount: Number(form.amount.value),
        currency: form.currency.value || 'EUR',
        paid_at: toSqlDateTime(form.paid_at.value),
        reference: form.reference.value || null,
        notes: form.notes.value || null,
    };
    try {
        await apiFetch('payments', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Zahlung erfasst.', 'success');
        loadBilling(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('user-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const roleIds = Array.from(form.querySelectorAll('input[type="checkbox"]:checked')).map((input) => Number(input.value));
    const payload = {
        name: form.name.value,
        email: form.email.value,
        password: form.password.value,
        role_ids: roleIds,
    };
    try {
        await apiFetch('users', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Benutzer angelegt.', 'success');
        loadUsers(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('role-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        name: form.name.value,
        description: form.description.value || null,
    };
    try {
        await apiFetch('roles', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Rolle erstellt.', 'success');
        const roles = await apiFetch('roles');
        state.roles = roles;
        populateRoleCheckboxes();
        renderTable('roles-list', [
            { key: 'name', label: 'Name' },
            { key: 'description', label: 'Beschreibung' },
        ], roles);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('guest-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!requireToken()) {
        return;
    }
    const form = event.target;
    const payload = {
        first_name: form.first_name.value,
        last_name: form.last_name.value,
        email: form.email.value || null,
        phone: form.phone.value || null,
        address: form.address.value || null,
        city: form.city.value || null,
        country: form.country.value || null,
        notes: form.notes.value || null,
    };
    try {
        await apiFetch('guests', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        showMessage('Gast gespeichert.', 'success');
        loadGuests(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

document.getElementById('guest-lookup').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const confirmation = form.confirmation.value.trim();
    if (!confirmation) {
        showMessage('Bitte Bestätigungsnummer eingeben.', 'error');
        return;
    }
    await lookupGuestReservation(confirmation);
});

const storedToken = localStorage.getItem('realpms_api_token');
if (storedToken) {
    state.token = storedToken;
    tokenInput.value = storedToken;
    bootstrap();
}

setDefaultDates();
showSection('dashboard');
