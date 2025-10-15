const API_BASE = '../backend/api/index.php';
const state = {
    token: null,
    roomTypes: [],
    ratePlans: [],
    rooms: [],
    reservations: [],
    roles: [],
    guests: [],
    companies: [],
    companiesLoaded: false,
    editingReservationId: null,
    editingGuestId: null,
    editingCompanyId: null,
    loadedSections: new Set(),
    calendarLabelMode: 'guest',
};

const CALENDAR_DAYS = 14;
const CALENDAR_LABEL_KEY = 'realpms_calendar_label_mode';

try {
    const storedMode = localStorage.getItem(CALENDAR_LABEL_KEY);
    if (storedMode === 'company' || storedMode === 'guest') {
        state.calendarLabelMode = storedMode;
    }
} catch (error) {
    // ignore storage access issues
}

const notificationEl = document.getElementById('notification');
const tokenInput = document.getElementById('api-token');
const dashboardDateInput = document.getElementById('dashboard-date');
const calendarLabelSelect = document.getElementById('calendar-label-mode');
const reportStartInput = document.getElementById('report-start');
const reportEndInput = document.getElementById('report-end');
const reservationForm = document.getElementById('reservation-form');
const reservationDetails = reservationForm ? reservationForm.closest('details') : null;
const reservationSummary = reservationDetails ? reservationDetails.querySelector('summary') : null;
const reservationSubmitButton = reservationForm ? reservationForm.querySelector('button[type="submit"]') : null;
const reservationCancelButton = document.getElementById('reservation-cancel-edit');
const reservationsList = document.getElementById('reservations-list');
const reservationCapacityEl = document.getElementById('reservation-capacity');
const reservationRoomsSelect = reservationForm ? reservationForm.querySelector('select[name="rooms"]') : null;
const guestSelect = reservationForm ? reservationForm.querySelector('select[name="guest_id"]') : null;
const reservationGuestCompanySelect = reservationForm ? reservationForm.querySelector('select[name="guest_company"]') : null;
const guestForm = document.getElementById('guest-form');
const guestDetails = guestForm ? guestForm.closest('details') : null;
const guestSummary = guestDetails ? guestDetails.querySelector('summary') : null;
const guestSubmitButton = guestForm ? guestForm.querySelector('button[type="submit"]') : null;
const guestCancelButton = document.getElementById('guest-cancel-edit');
const guestCompanySelect = guestForm ? guestForm.querySelector('select[name="company_id"]') : null;
const guestSummaryDefault = guestSummary ? guestSummary.textContent : 'Gast anlegen';
const guestsList = document.getElementById('guests-list');
const companiesList = document.getElementById('companies-list');
const companyForm = document.getElementById('company-form');
const companyDetails = companyForm ? companyForm.closest('details') : null;
const companySummary = companyDetails ? companyDetails.querySelector('summary') : null;
const companySubmitButton = companyForm ? companyForm.querySelector('button[type="submit"]') : null;
const companyCancelButton = document.getElementById('company-cancel-edit');
const companySummaryDefault = companySummary ? companySummary.textContent : 'Firma anlegen';

const RESERVATION_STATUS_LABELS = {
    tentative: 'Voranfrage',
    confirmed: 'Bestätigt',
    checked_in: 'Angereist',
    paid: 'Bezahlt',
    checked_out: 'Abgereist',
    cancelled: 'Storniert',
    no_show: 'Nicht erschienen',
};

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

function toLocalISODate(date = new Date()) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    return local.toISOString().slice(0, 10);
}

function parseISODate(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return null;
    }
    const [, year, month, day] = match;
    const date = new Date(Number(year), Number(month) - 1, Number(day));
    return Number.isNaN(date.getTime()) ? null : date;
}

function addDays(date, amount) {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    return result;
}

function dateKey(date) {
    return toLocalISODate(date);
}

function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
}

function normalizeStatusClass(value) {
    if (!value) {
        return '';
    }
    return value.toString().toLowerCase().replace(/[^a-z0-9_-]/g, '');
}

function formatReservationStatus(value) {
    if (!value) {
        return '';
    }
    const normalized = normalizeStatusClass(value);
    return RESERVATION_STATUS_LABELS[normalized] || value;
}

function getReservationCalendarLabel(reservation, labelMode = 'guest') {
    const guestName = `${reservation.first_name || ''} ${reservation.last_name || ''}`.trim();
    const companyName = reservation.company_name || '';
    if (labelMode === 'company') {
        if (companyName) {
            return companyName;
        }
        return guestName || reservation.confirmation_number || 'Belegt';
    }
    return guestName || companyName || reservation.confirmation_number || 'Belegt';
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
        state.roomTypes = [];
        state.ratePlans = [];
        state.rooms = [];
        state.reservations = [];
        state.roles = [];
        state.guests = [];
        state.companies = [];
        state.companiesLoaded = false;
        state.editingReservationId = null;
        state.editingGuestId = null;
        state.editingCompanyId = null;
        state.loadedSections.clear();
        populateGuestSelect();
        populateCompanyDropdowns();
        resetReservationForm();
        resetGuestForm();
        resetCompanyForm();
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
    const isoToday = toLocalISODate(today);
    if (!dashboardDateInput.value) {
        dashboardDateInput.value = isoToday;
    }
    const monthStart = toLocalISODate(new Date(today.getFullYear(), today.getMonth(), 1));
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
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const parsed = parseISODate(value);
        return parsed ? new Intl.DateTimeFormat('de-DE').format(parsed) : value;
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('de-DE');
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/.test(value)) {
        const normalized = value.replace(' ', 'T');
        const withSeconds = normalized.length === 16 ? `${normalized}:00` : normalized;
        const date = new Date(withSeconds);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString('de-DE');
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('de-DE');
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

function calculateRoomCapacity(roomIds) {
    if (!Array.isArray(roomIds) || roomIds.length === 0) {
        return 0;
    }
    return roomIds.reduce((total, roomId) => {
        const room = state.rooms.find((entry) => Number(entry.id) === Number(roomId));
        const capacity = room ? Number(room.max_occupancy ?? room.base_occupancy ?? 0) : 0;
        return Number.isFinite(capacity) ? total + capacity : total;
    }, 0);
}

function updateReservationCapacityHint() {
    if (!reservationForm || !reservationCapacityEl) {
        return;
    }
    const adults = Number(reservationForm.adults?.value || 0);
    const children = Number(reservationForm.children?.value || 0);
    const guestTotal = adults + children;
    const roomIds = reservationRoomsSelect
        ? Array.from(reservationRoomsSelect.selectedOptions).map((option) => Number(option.value))
        : [];

    if (!roomIds.length) {
        reservationCapacityEl.textContent = 'Bitte mindestens ein Zimmer auswählen.';
        reservationCapacityEl.classList.remove('text-danger');
        return;
    }

    const capacity = calculateRoomCapacity(roomIds);
    if (capacity <= 0) {
        reservationCapacityEl.textContent = '';
        reservationCapacityEl.classList.remove('text-danger');
        return;
    }

    reservationCapacityEl.textContent = `Kapazität: ${guestTotal} von ${capacity} Gästen.`;
    if (guestTotal > capacity) {
        reservationCapacityEl.classList.add('text-danger');
    } else {
        reservationCapacityEl.classList.remove('text-danger');
    }
}

function populateCompanyDropdowns() {
    const companies = [...state.companies].sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' }));

    if (guestCompanySelect) {
        const current = guestCompanySelect.value;
        const options = ['<option value="">Keine Firma</option>'];
        companies.forEach((company) => {
            options.push(`<option value="${company.id}">${company.name || ''}</option>`);
        });
        guestCompanySelect.innerHTML = options.join('');
        if (current && [...guestCompanySelect.options].some((option) => option.value === current)) {
            guestCompanySelect.value = current;
        }
    }

    if (reservationGuestCompanySelect) {
        const current = reservationGuestCompanySelect.value;
        const options = ['<option value="">Keine Firma</option>'];
        companies.forEach((company) => {
            options.push(`<option value="${company.id}">${company.name || ''}</option>`);
        });
        reservationGuestCompanySelect.innerHTML = options.join('');
        if (current && [...reservationGuestCompanySelect.options].some((option) => option.value === current)) {
            reservationGuestCompanySelect.value = current;
        }
    }
}

function populateGuestSelect() {
    if (!guestSelect) {
        return;
    }
    const currentValue = guestSelect.value;
    const options = ['<option value="">Neuer Gast</option>'];
    const sortedGuests = [...state.guests].sort((a, b) => {
        const lastCompare = (a.last_name || '').localeCompare(b.last_name || '', 'de', { sensitivity: 'base' });
        if (lastCompare !== 0) {
            return lastCompare;
        }
        return (a.first_name || '').localeCompare(b.first_name || '', 'de', { sensitivity: 'base' });
    });
    sortedGuests.forEach((guest) => {
        const companyLabel = guest.company_name ? ` – ${guest.company_name}` : '';
        options.push(`<option value="${guest.id}">${guest.last_name || ''}${guest.last_name && guest.first_name ? ', ' : ''}${guest.first_name || ''}${companyLabel}</option>`);
    });
    guestSelect.innerHTML = options.join('');
    if (currentValue && [...guestSelect.options].some((option) => option.value === currentValue)) {
        guestSelect.value = currentValue;
    } else {
        guestSelect.value = '';
    }
}

function applyGuestToForm(guest) {
    if (!reservationForm) {
        return;
    }
    reservationForm.guest_first.value = guest?.first_name || '';
    reservationForm.guest_last.value = guest?.last_name || '';
    reservationForm.guest_email.value = guest?.email || '';
    reservationForm.guest_phone.value = guest?.phone || '';
    if (reservationGuestCompanySelect) {
        reservationGuestCompanySelect.value = guest?.company_id ? String(guest.company_id) : '';
    }
}

function resetReservationForm() {
    if (!reservationForm) {
        return;
    }
    const wasOpen = reservationDetails ? reservationDetails.open : false;
    reservationForm.reset();
    state.editingReservationId = null;
    if (reservationSummary) {
        reservationSummary.textContent = 'Neue Reservierung anlegen';
    }
    if (reservationSubmitButton) {
        reservationSubmitButton.textContent = 'Reservierung speichern';
    }
    if (reservationCancelButton) {
        reservationCancelButton.classList.add('hidden');
    }
    populateRatePlanSelect();
    populateRoomOptions();
    populateGuestSelect();
    populateCompanyDropdowns();
    applyGuestToForm(null);
    if (reservationDetails) {
        reservationDetails.open = wasOpen;
    }
    updateReservationCapacityHint();
}

function fillReservationForm(reservation) {
    if (!reservationForm) {
        return;
    }
    populateRatePlanSelect();
    populateRoomOptions();
    populateGuestSelect();
    populateCompanyDropdowns();

    reservationForm.check_in.value = reservation.check_in_date ? reservation.check_in_date.slice(0, 10) : '';
    reservationForm.check_out.value = reservation.check_out_date ? reservation.check_out_date.slice(0, 10) : '';
    reservationForm.adults.value = reservation.adults ?? 1;
    reservationForm.children.value = reservation.children ?? 0;
    reservationForm.total_amount.value = reservation.total_amount ?? '';
    reservationForm.currency.value = reservation.currency || 'EUR';
    reservationForm.status.value = reservation.status || 'confirmed';
    reservationForm.booked_via.value = reservation.booked_via || '';

    const selectedRoomIds = new Set((reservation.rooms || []).map((room) => Number(room.room_id ?? room.id)));
    if (reservationRoomsSelect) {
        Array.from(reservationRoomsSelect.options).forEach((option) => {
            option.selected = selectedRoomIds.has(Number(option.value));
        });
    }

    const guestSnapshot = {
        id: reservation.guest_id || null,
        first_name: reservation.first_name || '',
        last_name: reservation.last_name || '',
        email: reservation.email || '',
        phone: reservation.phone || '',
        company_id: reservation.company_id || null,
        company_name: reservation.company_name || null,
    };

    if (reservation.guest_id) {
        const existing = state.guests.find((guest) => Number(guest.id) === Number(reservation.guest_id));
        if (!existing) {
            state.guests.push({ id: reservation.guest_id, ...guestSnapshot });
            populateGuestSelect();
        }
        if (guestSelect) {
            guestSelect.value = String(reservation.guest_id);
        }
    } else if (guestSelect) {
        guestSelect.value = '';
    }

    applyGuestToForm(guestSnapshot);
    updateReservationCapacityHint();
}

async function startReservationEdit(reservationId) {
    if (!requireToken()) {
        return;
    }
    try {
        const reservation = await apiFetch(`reservations/${reservationId}`);
        if (!reservation) {
            showMessage('Reservierung konnte nicht geladen werden.', 'error');
            return;
        }
        state.editingReservationId = reservation.id;
        if (reservationSummary) {
            const label = reservation.confirmation_number || `ID ${reservation.id}`;
            reservationSummary.textContent = `Reservierung bearbeiten (${label})`;
        }
        if (reservationSubmitButton) {
            reservationSubmitButton.textContent = 'Reservierung aktualisieren';
        }
        if (reservationCancelButton) {
            reservationCancelButton.classList.remove('hidden');
        }
        if (reservationDetails) {
            reservationDetails.open = true;
        }
        fillReservationForm(reservation);
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

function renderReservationsTable(reservations) {
    renderTable('reservations-list', [
        { key: 'confirmation_number', label: 'Bestätigungsnr.' },
        { key: 'guest', label: 'Gast', render: (row) => `${row.first_name || ''} ${row.last_name || ''}`.trim() },
        { key: 'check_in_date', label: 'Check-in', render: (row) => formatDate(row.check_in_date) },
        { key: 'check_out_date', label: 'Check-out', render: (row) => formatDate(row.check_out_date) },
        { key: 'status', label: 'Status', render: (row) => formatReservationStatus(row.status) },
        { key: 'rooms', label: 'Zimmer', render: (row) => (row.rooms || []).map((room) => room.room_number).join(', ') },
        { key: 'total_amount', label: 'Gesamt', render: (row) => formatCurrency(row.total_amount, row.currency || 'EUR') },
        { key: 'actions', label: 'Aktionen', render: (row) => `<button type="button" class="secondary reservation-edit" data-id="${row.id}">Bearbeiten</button>` },
    ], reservations);
}

function renderGuestsTable(guests) {
    renderTable('guests-list', [
        { key: 'last_name', label: 'Nachname' },
        { key: 'first_name', label: 'Vorname' },
        { key: 'company_name', label: 'Firma' },
        { key: 'email', label: 'E-Mail' },
        { key: 'phone', label: 'Telefon' },
        { key: 'notes', label: 'Notizen' },
        { key: 'actions', label: 'Aktionen', render: (row) => `<button type="button" class="secondary guest-edit" data-id="${row.id}">Bearbeiten</button>` },
    ], guests);
}

function renderCompaniesTable(companies) {
    renderTable('companies-list', [
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'E-Mail' },
        { key: 'phone', label: 'Telefon' },
        { key: 'city', label: 'Stadt' },
        { key: 'country', label: 'Land' },
        { key: 'notes', label: 'Notizen' },
        { key: 'actions', label: 'Aktionen', render: (row) => `<button type="button" class="secondary company-edit" data-id="${row.id}">Bearbeiten</button>` },
    ], companies);
}

function resetGuestForm() {
    if (!guestForm) {
        return;
    }
    const wasOpen = guestDetails ? guestDetails.open : false;
    guestForm.reset();
    state.editingGuestId = null;
    if (guestSubmitButton) {
        guestSubmitButton.textContent = 'Gast speichern';
    }
    if (guestCancelButton) {
        guestCancelButton.classList.add('hidden');
    }
    if (guestSummary) {
        guestSummary.textContent = guestSummaryDefault;
    }
    populateCompanyDropdowns();
    if (guestCompanySelect) {
        guestCompanySelect.value = '';
    }
    if (guestDetails) {
        guestDetails.open = wasOpen;
    }
}

function startGuestEdit(guestId) {
    if (!guestForm) {
        return;
    }
    const guest = state.guests.find((entry) => Number(entry.id) === Number(guestId));
    if (!guest) {
        showMessage('Gast wurde nicht gefunden.', 'error');
        return;
    }
    populateCompanyDropdowns();
    guestForm.first_name.value = guest.first_name || '';
    guestForm.last_name.value = guest.last_name || '';
    guestForm.email.value = guest.email || '';
    guestForm.phone.value = guest.phone || '';
    guestForm.address.value = guest.address || '';
    guestForm.city.value = guest.city || '';
    guestForm.country.value = guest.country || '';
    guestForm.notes.value = guest.notes || '';
    if (guestCompanySelect) {
        guestCompanySelect.value = guest.company_id ? String(guest.company_id) : '';
    }
    state.editingGuestId = Number(guest.id);
    if (guestSubmitButton) {
        guestSubmitButton.textContent = 'Gast aktualisieren';
    }
    if (guestCancelButton) {
        guestCancelButton.classList.remove('hidden');
    }
    if (guestSummary) {
        const label = [guest.last_name, guest.first_name].filter(Boolean).join(', ') || `ID ${guest.id}`;
        guestSummary.textContent = `Gast bearbeiten (${label})`;
    }
    if (guestDetails) {
        guestDetails.open = true;
    }
}

function resetCompanyForm() {
    if (!companyForm) {
        return;
    }
    const wasOpen = companyDetails ? companyDetails.open : false;
    companyForm.reset();
    state.editingCompanyId = null;
    if (companySubmitButton) {
        companySubmitButton.textContent = 'Firma speichern';
    }
    if (companyCancelButton) {
        companyCancelButton.classList.add('hidden');
    }
    if (companySummary) {
        companySummary.textContent = companySummaryDefault;
    }
    if (companyDetails) {
        companyDetails.open = wasOpen;
    }
}

function startCompanyEdit(companyId) {
    if (!companyForm) {
        return;
    }
    const company = state.companies.find((entry) => Number(entry.id) === Number(companyId));
    if (!company) {
        showMessage('Firma wurde nicht gefunden.', 'error');
        return;
    }
    companyForm.name.value = company.name || '';
    companyForm.email.value = company.email || '';
    companyForm.phone.value = company.phone || '';
    companyForm.address.value = company.address || '';
    companyForm.city.value = company.city || '';
    companyForm.country.value = company.country || '';
    companyForm.notes.value = company.notes || '';
    state.editingCompanyId = Number(company.id);
    if (companySubmitButton) {
        companySubmitButton.textContent = 'Firma aktualisieren';
    }
    if (companyCancelButton) {
        companyCancelButton.classList.remove('hidden');
    }
    if (companySummary) {
        const label = company.name || `ID ${company.id}`;
        companySummary.textContent = `Firma bearbeiten (${label})`;
    }
    if (companyDetails) {
        companyDetails.open = true;
    }
}

function renderOccupancyCalendar(rooms, reservations, startDateStr, days = CALENDAR_DAYS, labelMode = state.calendarLabelMode || 'guest') {
    const container = document.getElementById('occupancy-calendar');
    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!rooms || rooms.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'muted';
        empty.textContent = 'Bitte legen Sie Zimmer an, um die Belegung anzuzeigen.';
        container.appendChild(empty);
        return;
    }

    const startDate = parseISODate(startDateStr) || new Date();
    const totalDays = Number.isFinite(days) && days > 0 ? Math.floor(days) : CALENDAR_DAYS;
    const dayDates = [];
    for (let index = 0; index < totalDays; index += 1) {
        dayDates.push(addDays(startDate, index));
    }
    const dayKeys = dayDates.map((date) => dateKey(date));
    const dayKeySet = new Set(dayKeys);
    const rangeEndExclusive = addDays(startDate, totalDays);
    const occupancyMap = new Map();
    const reservationsList = Array.isArray(reservations) ? reservations : [];

    reservationsList.forEach((reservation) => {
        const status = normalizeStatusClass(reservation.status || '');
        if (status === 'cancelled' || status === 'no_show') {
            return;
        }
        const checkIn = parseISODate(reservation.check_in_date);
        const checkOut = parseISODate(reservation.check_out_date);
        if (!checkIn || !checkOut || checkOut <= checkIn) {
            return;
        }
        const visibleStart = checkIn > startDate ? checkIn : startDate;
        const visibleEnd = checkOut < rangeEndExclusive ? checkOut : rangeEndExclusive;
        if (visibleEnd <= visibleStart) {
            return;
        }
        (reservation.rooms || []).forEach((room) => {
            const roomId = room.room_id ?? room.id;
            if (!roomId) {
                return;
            }
            for (let cursor = new Date(visibleStart); cursor < visibleEnd; cursor = addDays(cursor, 1)) {
                const keyDate = dateKey(cursor);
                if (!dayKeySet.has(keyDate)) {
                    continue;
                }
                const key = `${roomId}_${keyDate}`;
                occupancyMap.set(key, reservation);
            }
        });
    });

    const table = document.createElement('table');
    table.className = 'calendar-table';
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    const roomHeader = document.createElement('th');
    roomHeader.className = 'room';
    roomHeader.textContent = 'Zimmer';
    headerRow.appendChild(roomHeader);

    const headerFormatter = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
    const todayKey = toLocalISODate();

    dayDates.forEach((date) => {
        const th = document.createElement('th');
        const key = dateKey(date);
        th.textContent = headerFormatter.format(date);
        th.dataset.date = key;
        if (key === todayKey) {
            th.classList.add('today');
        }
        if (isWeekend(date)) {
            th.classList.add('weekend');
        }
        headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    const sortedRooms = [...rooms].sort((a, b) => {
        const typeCompare = (a.room_type_name || '').localeCompare(b.room_type_name || '', 'de', { sensitivity: 'base' });
        if (typeCompare !== 0) {
            return typeCompare;
        }
        const aValue = (a.room_number ?? a.name ?? '').toString();
        const bValue = (b.room_number ?? b.name ?? '').toString();
        return aValue.localeCompare(bValue, 'de', { numeric: true, sensitivity: 'base' });
    });

    sortedRooms.forEach((room) => {
        const row = document.createElement('tr');
        const roomId = room.id ?? room.room_id;
        if (!roomId) {
            return;
        }
        const roomCell = document.createElement('th');
        roomCell.scope = 'row';
        roomCell.className = 'room';
        const roomLabel = room.room_number || room.name || `Zimmer ${roomId}`;
        roomCell.textContent = room.room_type_name ? `${roomLabel} (${room.room_type_name})` : roomLabel;
        row.appendChild(roomCell);

        dayDates.forEach((date) => {
            const key = `${roomId}_${dateKey(date)}`;
            const cell = document.createElement('td');
            cell.dataset.date = dateKey(date);
            if (cell.dataset.date === todayKey) {
                cell.classList.add('today');
            }
            if (isWeekend(date)) {
                cell.classList.add('weekend');
            }
            const reservation = occupancyMap.get(key);
            if (reservation) {
                const statusClass = normalizeStatusClass(reservation.status || '');
                cell.classList.add('occupied');
                if (statusClass) {
                    cell.classList.add(`status-${statusClass}`);
                }
                const guestName = `${reservation.first_name || ''} ${reservation.last_name || ''}`.trim();
                const label = getReservationCalendarLabel(reservation, labelMode);
                const statusLabel = formatReservationStatus(reservation.status);
                cell.textContent = label;
                const details = [
                    label !== guestName && guestName ? `Gast: ${guestName}` : guestName,
                    reservation.company_name ? `Firma: ${reservation.company_name}` : null,
                    reservation.confirmation_number ? `Bestätigungsnr.: ${reservation.confirmation_number}` : null,
                    statusLabel ? `Status: ${statusLabel}` : null,
                    reservation.check_in_date ? `Anreise: ${formatDate(reservation.check_in_date)}` : null,
                    reservation.check_out_date ? `Abreise: ${formatDate(reservation.check_out_date)}` : null,
                ].filter(Boolean);
                cell.title = details.join('\n');
            } else {
                cell.classList.add('vacant');
                cell.textContent = 'Frei';
            }
            row.appendChild(cell);
        });

        tbody.appendChild(row);
    });

    table.appendChild(tbody);
    container.appendChild(table);
}

async function bootstrap() {
    if (!requireToken()) {
        return;
    }
    try {
        const [roomTypes, ratePlans, rooms, roles, guests, companies] = await Promise.all([
            apiFetch('room-types'),
            apiFetch('rate-plans'),
            apiFetch('rooms'),
            apiFetch('roles'),
            apiFetch('guests'),
            apiFetch('companies'),
        ]);
        state.roomTypes = roomTypes;
        state.ratePlans = ratePlans;
        state.rooms = rooms;
        state.roles = roles;
        state.guests = guests;
        state.companies = companies;
        state.companiesLoaded = true;
        populateRoomTypeSelects();
        populateRatePlanSelect();
        populateRoomOptions();
        populateRoleCheckboxes();
        populateRoomTypeList();
        populateRatePlanList();
        populateGuestSelect();
        populateCompanyDropdowns();
        renderGuestsTable(guests);
        renderCompaniesTable(companies);
        resetReservationForm();
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
    const taskRoomSelect = document.querySelector('#task-form select[name="room"]');
    const options = state.rooms.map((room) => {
        const capacity = Number(room.max_occupancy ?? room.base_occupancy ?? 0);
        const capacityText = capacity > 0 ? ` • max. ${capacity}` : '';
        const typeLabel = room.room_type_name || 'Kategorie';
        return `<option value="${room.id}">${room.room_number} (${typeLabel}${capacityText})</option>`;
    });
    if (reservationRoomsSelect) {
        const selectedValues = new Set(Array.from(reservationRoomsSelect.selectedOptions || []).map((option) => option.value));
        reservationRoomsSelect.innerHTML = options.join('');
        Array.from(reservationRoomsSelect.options).forEach((option) => {
            option.selected = selectedValues.has(option.value);
        });
    }
    if (taskRoomSelect) {
        taskRoomSelect.innerHTML = `<option value="">Kein Zimmer</option>${options.join('')}`;
    }
    updateReservationCapacityHint();
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
        const targetDateValue = dashboardDateInput.value || toLocalISODate();
        const targetDate = parseISODate(targetDateValue) || new Date();
        const monthStart = toLocalISODate(new Date(targetDate.getFullYear(), targetDate.getMonth(), 1));
        const monthEnd = toLocalISODate(new Date(targetDate.getFullYear(), targetDate.getMonth() + 1, 0));
        const calendarEnd = toLocalISODate(addDays(targetDate, CALENDAR_DAYS - 1));

        const roomsPromise = state.rooms.length
            ? Promise.resolve(state.rooms)
            : apiFetch('rooms').then((rooms) => {
                state.rooms = rooms;
                populateRoomOptions();
                return rooms;
            });

        const reservationsPromise = force || state.reservations.length === 0
            ? apiFetch('reservations').then((reservations) => {
                state.reservations = reservations;
                return reservations;
            })
            : Promise.resolve(state.reservations);

        const [occupancy, revenue, openTasks, rooms, reservations] = await Promise.all([
            apiFetch(`reports/occupancy?start=${targetDateValue}&end=${calendarEnd}`),
            apiFetch(`reports/revenue?start=${monthStart}&end=${monthEnd}`),
            apiFetch('housekeeping/tasks?status=open'),
            roomsPromise,
            reservationsPromise,
        ]);

        if (Array.isArray(rooms)) {
            state.rooms = rooms;
        }
        state.reservations = Array.isArray(reservations) ? reservations : [];

        if (state.loadedSections.has('reservations')) {
            renderReservationsTable(state.reservations);
        }

        const occupancyToday = Array.isArray(occupancy)
            ? occupancy.find((entry) => entry.date === targetDateValue) || null
            : null;
        document.getElementById('dash-occupancy-rate').textContent = occupancyToday ? `${occupancyToday.occupancy_rate}%` : '--%';
        document.getElementById('dash-occupancy-detail').textContent = occupancyToday
            ? `${occupancyToday.occupied_rooms} von ${occupancyToday.available_rooms} Zimmern belegt`
            : 'Keine Daten';

        const invoiceTotals = revenue?.invoices || { invoice_total: 0, tax_total: 0 };
        document.getElementById('dash-revenue-total').textContent = formatCurrency(invoiceTotals.invoice_total || 0);
        document.getElementById('dash-revenue-detail').textContent = `Steueranteil: ${formatCurrency(invoiceTotals.tax_total || 0)}`;

        const openTaskCount = Array.isArray(openTasks) ? openTasks.length : 0;
        document.getElementById('dash-open-tasks').textContent = openTaskCount;

        renderTable('occupancy-table', [
            { key: 'date', label: 'Datum', render: (row) => formatDate(row.date) },
            { key: 'occupied_rooms', label: 'Belegte Zimmer' },
            { key: 'available_rooms', label: 'Gesamtzimmer' },
            { key: 'occupancy_rate', label: 'Auslastung', render: (row) => `${row.occupancy_rate}%` },
        ], Array.isArray(occupancy) ? occupancy : []);

        renderOccupancyCalendar(rooms, reservations, targetDateValue, CALENDAR_DAYS);
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
        state.reservations = reservations;
        renderReservationsTable(reservations);
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
        const start = reportStartInput.value || toLocalISODate();
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

async function fetchCompanies(force = false) {
    if (!state.token) {
        return [];
    }
    if (!force && state.companiesLoaded) {
        return state.companies;
    }
    const companies = await apiFetch('companies');
    state.companies = companies;
    state.companiesLoaded = true;
    populateCompanyDropdowns();
    return companies;
}

async function loadGuests(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('guests')) {
        return;
    }
    try {
        const [guests] = await Promise.all([
            apiFetch('guests'),
            fetchCompanies(force),
        ]);
        state.guests = guests;
        populateGuestSelect();
        populateCompanyDropdowns();
        renderGuestsTable(guests);
        state.loadedSections.add('guests');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function loadCompanies(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('companies')) {
        return;
    }
    try {
        const companies = await fetchCompanies(force);
        renderCompaniesTable(companies);
        state.loadedSections.add('companies');
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
    companies: () => loadCompanies(true),
    guests: () => loadGuests(true),
    integrations: () => loadIntegrations(true),
};

// Form submissions

if (reservationForm) {
    reservationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const rooms = reservationRoomsSelect
            ? Array.from(reservationRoomsSelect.selectedOptions).map((option) => Number(option.value))
            : [];
        if (rooms.length === 0) {
            showMessage('Bitte mindestens ein Zimmer auswählen.', 'error');
            return;
        }

        const adults = Number(reservationForm.adults.value || 0);
        const children = Number(reservationForm.children.value || 0);
        const totalGuests = adults + children;
        if (totalGuests < 1) {
            showMessage('Bitte mindestens einen Gast angeben.', 'error');
            return;
        }
        const capacity = calculateRoomCapacity(rooms);
        if (capacity > 0 && totalGuests > capacity) {
            showMessage(`Die ausgewählten Zimmer bieten nur Platz für ${capacity} Gäste.`, 'error');
            return;
        }

        const payload = {
            check_in_date: reservationForm.check_in.value,
            check_out_date: reservationForm.check_out.value,
            adults,
            children,
            rate_plan_id: reservationForm.rate_plan.value ? Number(reservationForm.rate_plan.value) : null,
            rooms,
            total_amount: reservationForm.total_amount.value ? Number(reservationForm.total_amount.value) : null,
            currency: reservationForm.currency.value || 'EUR',
            status: reservationForm.status.value,
            booked_via: reservationForm.booked_via.value || null,
        };

        const selectedGuestId = guestSelect && guestSelect.value ? Number(guestSelect.value) : null;
        const guestPayload = {
            first_name: reservationForm.guest_first.value,
            last_name: reservationForm.guest_last.value,
            email: reservationForm.guest_email.value || null,
            phone: reservationForm.guest_phone.value || null,
        };
        if (reservationGuestCompanySelect) {
            guestPayload.company_id = reservationGuestCompanySelect.value ? Number(reservationGuestCompanySelect.value) : null;
        }

        const isEdit = Boolean(state.editingReservationId);

        if (selectedGuestId) {
            payload.guest_id = selectedGuestId;
            if (isEdit) {
                payload.guest = guestPayload;
            }
        } else {
            payload.guest = guestPayload;
        }

        const endpoint = isEdit ? `reservations/${state.editingReservationId}` : 'reservations';
        const method = isEdit ? 'PUT' : 'POST';

        try {
            await apiFetch(endpoint, {
                method,
                body: JSON.stringify(payload),
            });
            showMessage(isEdit ? 'Reservierung wurde aktualisiert.' : 'Reservierung wurde angelegt.', 'success');
            await Promise.all([
                loadReservations(true),
                loadDashboard(true),
            ]);
            resetReservationForm();
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (reservationCancelButton) {
    reservationCancelButton.addEventListener('click', () => {
        resetReservationForm();
    });
}

if (reservationRoomsSelect) {
    reservationRoomsSelect.addEventListener('change', updateReservationCapacityHint);
}

if (reservationForm) {
    reservationForm.adults.addEventListener('input', updateReservationCapacityHint);
    reservationForm.children.addEventListener('input', updateReservationCapacityHint);
}

if (guestSelect) {
    guestSelect.addEventListener('change', () => {
        if (!guestSelect.value) {
            applyGuestToForm(null);
            return;
        }
        const guest = state.guests.find((entry) => Number(entry.id) === Number(guestSelect.value));
        applyGuestToForm(guest || null);
    });
}

document.getElementById('reload-reservations').addEventListener('click', () => loadReservations(true));
document.getElementById('reload-rooms').addEventListener('click', () => loadRooms(true));
document.getElementById('reload-tasks').addEventListener('click', () => loadHousekeeping(true));
document.getElementById('reload-invoices').addEventListener('click', () => loadBilling(true));
document.getElementById('reload-payments').addEventListener('click', () => loadBilling(true));
document.getElementById('reload-users').addEventListener('click', () => loadUsers(true));
document.getElementById('reload-companies').addEventListener('click', () => loadCompanies(true));
document.getElementById('reload-guests').addEventListener('click', () => loadGuests(true));
document.getElementById('reload-integrations').addEventListener('click', () => loadIntegrations(true));
document.getElementById('refresh-dashboard').addEventListener('click', () => loadDashboard(true));
dashboardDateInput.addEventListener('change', () => loadDashboard(true));
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

if (guestForm) {
    guestForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const payload = {
            first_name: guestForm.first_name.value,
            last_name: guestForm.last_name.value,
            email: guestForm.email.value || null,
            phone: guestForm.phone.value || null,
            address: guestForm.address.value || null,
            city: guestForm.city.value || null,
            country: guestForm.country.value || null,
            notes: guestForm.notes.value || null,
            company_id: guestCompanySelect && guestCompanySelect.value ? Number(guestCompanySelect.value) : null,
        };
        const isEdit = Boolean(state.editingGuestId);
        const endpoint = isEdit ? `guests/${state.editingGuestId}` : 'guests';
        const method = isEdit ? 'PATCH' : 'POST';
        try {
            await apiFetch(endpoint, {
                method,
                body: JSON.stringify(payload),
            });
            showMessage(isEdit ? 'Gast aktualisiert.' : 'Gast gespeichert.', 'success');
            await loadGuests(true);
            resetGuestForm();
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (guestCancelButton) {
    guestCancelButton.addEventListener('click', () => resetGuestForm());
}

if (guestsList) {
    guestsList.addEventListener('click', (event) => {
        const button = event.target.closest('.guest-edit');
        if (!button) {
            return;
        }
        const guestId = Number(button.dataset.id);
        if (!Number.isFinite(guestId)) {
            return;
        }
        startGuestEdit(guestId);
    });
}

if (companyForm) {
    companyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const payload = {
            name: companyForm.name.value,
            email: companyForm.email.value || null,
            phone: companyForm.phone.value || null,
            address: companyForm.address.value || null,
            city: companyForm.city.value || null,
            country: companyForm.country.value || null,
            notes: companyForm.notes.value || null,
        };
        const isEdit = Boolean(state.editingCompanyId);
        const endpoint = isEdit ? `companies/${state.editingCompanyId}` : 'companies';
        const method = isEdit ? 'PATCH' : 'POST';
        try {
            await apiFetch(endpoint, {
                method,
                body: JSON.stringify(payload),
            });
            showMessage(isEdit ? 'Firma aktualisiert.' : 'Firma gespeichert.', 'success');
            await Promise.all([
                loadCompanies(true),
                loadGuests(true),
            ]);
            resetCompanyForm();
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (companyCancelButton) {
    companyCancelButton.addEventListener('click', () => resetCompanyForm());
}

if (companiesList) {
    companiesList.addEventListener('click', (event) => {
        const button = event.target.closest('.company-edit');
        if (!button) {
            return;
        }
        const companyId = Number(button.dataset.id);
        if (!Number.isFinite(companyId)) {
            return;
        }
        startCompanyEdit(companyId);
    });
}

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

if (reservationsList) {
    reservationsList.addEventListener('click', (event) => {
        const button = event.target.closest('.reservation-edit');
        if (!button) {
            return;
        }
        const reservationId = Number(button.dataset.id);
        if (!Number.isFinite(reservationId)) {
            return;
        }
        startReservationEdit(reservationId);
    });
}

if (calendarLabelSelect) {
    calendarLabelSelect.value = state.calendarLabelMode;
    calendarLabelSelect.addEventListener('change', () => {
        const nextMode = calendarLabelSelect.value === 'company' ? 'company' : 'guest';
        state.calendarLabelMode = nextMode;
        try {
            localStorage.setItem(CALENDAR_LABEL_KEY, nextMode);
        } catch (error) {
            // ignore storage failures
        }
        renderOccupancyCalendar(
            state.rooms,
            state.reservations,
            dashboardDateInput && dashboardDateInput.value ? dashboardDateInput.value : toLocalISODate(),
            CALENDAR_DAYS,
            nextMode,
        );
    });
}

const storedToken = localStorage.getItem('realpms_api_token');
if (storedToken) {
    state.token = storedToken;
    tokenInput.value = storedToken;
    bootstrap();
}

setDefaultDates();
showSection('dashboard');
