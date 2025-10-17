const API_BASE = '../backend/api/index.php';
const CALENDAR_STATUS_ORDER = ['tentative', 'confirmed', 'checked_in', 'paid', 'checked_out', 'cancelled', 'no_show'];
const CALENDAR_COLOR_DEFAULTS = {
    tentative: '#f97316',
    confirmed: '#2563eb',
    checked_in: '#16a34a',
    paid: '#0ea5e9',
    checked_out: '#6b7280',
    cancelled: '#ef4444',
    no_show: '#7c3aed',
};

const ARTICLE_SCHEME_LABELS = {
    per_person_per_day: 'pro Person & Tag',
    per_room_per_day: 'pro Zimmer & Tag',
    per_stay: 'pro Aufenthalt',
    per_person: 'pro Person',
    per_day: 'pro Tag',
};

const RESERVATION_STATUS_ACTIONS = [
    { status: 'checked_in', label: 'Check-in', title: 'Gast als angereist markieren' },
    { status: 'paid', label: 'Bezahlt', title: 'Zahlung als erhalten markieren' },
    { status: 'checked_out', label: 'Check-out', title: 'Gast als abgereist markieren' },
    { status: 'no_show', label: 'No-Show', title: 'Gast als No-Show markieren' },
];

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
    articles: [],
    articlesLoaded: false,
    editingReservationId: null,
    editingGuestId: null,
    editingCompanyId: null,
    editingArticleId: null,
    loadedSections: new Set(),
    calendarLabelMode: 'guest',
    calendarColors: { ...CALENDAR_COLOR_DEFAULTS },
    calendarColorTokens: {},
    calendarColorsLoaded: false,
    calendarCategorySort: 'name_asc',
    collapsedCategories: new Set(),
    invoiceLogoDataUrl: null,
    currentReservationInvoices: [],
    guestLookupResults: [],
    guestLookupTerm: '',
    pendingRoomRequests: [],
};

const CALENDAR_DAYS = 14;
const CALENDAR_LABEL_KEY = 'realpms_calendar_label_mode';
const CALENDAR_CATEGORY_SORT_KEY = 'realpms_calendar_category_sort';

try {
    const storedMode = localStorage.getItem(CALENDAR_LABEL_KEY);
    if (storedMode === 'company' || storedMode === 'guest') {
        state.calendarLabelMode = storedMode;
    }
    const storedSort = localStorage.getItem(CALENDAR_CATEGORY_SORT_KEY);
    const allowedSorts = new Set(['name_asc', 'name_desc', 'room_count_asc', 'room_count_desc']);
    if (storedSort && allowedSorts.has(storedSort)) {
        state.calendarCategorySort = storedSort;
    }
} catch (error) {
    // ignore storage access issues
}

const notificationEl = document.getElementById('notification');
const tokenInput = document.getElementById('api-token');
const dashboardDateInput = document.getElementById('dashboard-date');
const calendarLabelSelect = document.getElementById('calendar-label-mode');
const calendarCategorySortSelect = document.getElementById('calendar-category-sort');
const calendarSettingsButton = document.getElementById('open-calendar-settings');
const reportStartInput = document.getElementById('report-start');
const reportEndInput = document.getElementById('report-end');
const reservationForm = document.getElementById('reservation-form');
const reservationDetails = reservationForm ? reservationForm.closest('details') : null;
const reservationSummary = reservationDetails ? reservationDetails.querySelector('summary') : null;
const reservationSubmitButton = reservationForm ? reservationForm.querySelector('button[type="submit"]') : null;
const reservationCancelButton = document.getElementById('reservation-cancel-edit');
const reservationMeta = document.getElementById('reservation-meta');
const reservationNumberEl = document.getElementById('reservation-number');
const reservationInvoiceStatus = document.getElementById('reservation-invoice-status');
const reservationInvoiceLink = document.getElementById('reservation-invoice-link');
const reservationCreateInvoiceButton = document.getElementById('reservation-create-invoice');
const reservationPayInvoiceButton = document.getElementById('reservation-pay-invoice');
const reservationsList = document.getElementById('reservations-list');
const reservationCapacityEl = document.getElementById('reservation-capacity');
const guestSearchInput = reservationForm ? reservationForm.querySelector('input[name="guest_search"]') : null;
const guestIdInput = reservationForm ? reservationForm.querySelector('input[name="guest_id"]') : null;
const guestSearchResults = document.getElementById('guest-search-results');
const guestClearSelectionButton = document.getElementById('guest-clear-selection');
const reservationGuestCompanySelect = reservationForm ? reservationForm.querySelector('select[name="guest_company"]') : null;
const reservationArticleContainer = document.getElementById('reservation-article-options');
const reservationRatePlanSelect = reservationForm ? reservationForm.querySelector('select[name="rate_plan_id"]') : null;
const reservationNightlyRateInput = reservationForm ? reservationForm.querySelector('input[name="nightly_rate"]') : null;
const reservationTotalAmountInput = reservationForm ? reservationForm.querySelector('input[name="total_amount"]') : null;
const roomRequestList = document.getElementById('reservation-room-requests');
const roomRequestTypeSelect = document.getElementById('reservation-room-request-type');
const roomRequestQuantityInput = document.getElementById('reservation-room-request-quantity');
const roomRequestAddButton = document.getElementById('reservation-room-request-add');
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
const calendarColorForm = document.getElementById('calendar-color-form');
const resetCalendarColorsButton = document.getElementById('reset-calendar-colors');
const settingsReloadButton = document.getElementById('reload-settings');
const occupancyCalendarContainer = document.getElementById('occupancy-calendar');
const articlesList = document.getElementById('articles-list');
const articleForm = document.getElementById('article-form');
const articleSummary = articleForm ? articleForm.querySelector('h4') : null;
const articleCancelButton = document.getElementById('article-cancel-edit');
const reloadArticlesButton = document.getElementById('reload-articles');
const invoiceLogoForm = document.getElementById('invoice-logo-form');
const invoiceLogoInput = invoiceLogoForm ? invoiceLogoForm.querySelector('input[type="file"]') : null;
const invoiceLogoPreview = document.getElementById('invoice-logo-preview');
const removeInvoiceLogoButton = document.getElementById('remove-invoice-logo');
const systemUpdateMessage = document.getElementById('system-update-message');
const systemUpdateLog = document.getElementById('system-update-log');
const runSystemUpdateButton = document.getElementById('run-system-update');

let guestLookupDebounceId = null;
let guestLookupRequestId = 0;
let reservationPricingOverride = null;
let isSyncingPricingFields = false;

const RESERVATION_STATUS_LABELS = {
    tentative: 'Voranfrage',
    confirmed: 'Bestätigt',
    checked_in: 'Angereist',
    paid: 'Bezahlt',
    checked_out: 'Abgereist',
    cancelled: 'Storniert',
    no_show: 'Nicht erschienen',
};

const INVOICE_STATUS_LABELS = {
    draft: 'Entwurf',
    issued: 'Offen',
    paid: 'Bezahlt',
    void: 'Storniert',
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

function setSystemUpdateMessage(message, type = 'info') {
    if (!systemUpdateMessage) {
        return;
    }
    const normalizedType = type === 'success' || type === 'error' ? type : 'info';
    systemUpdateMessage.textContent = message;
    systemUpdateMessage.className = `system-update-message small-text ${normalizedType}`;
}

function renderSystemUpdateLog(logEntries) {
    if (!systemUpdateLog) {
        return;
    }
    if (!Array.isArray(logEntries) || logEntries.length === 0) {
        systemUpdateLog.innerHTML = '';
        systemUpdateLog.classList.add('hidden');
        return;
    }

    const rows = logEntries.map((entry, index) => {
        const label = entry && entry.label ? entry.label : `Schritt ${index + 1}`;
        const command = entry && entry.command ? entry.command : '—';
        const exitCode = entry && entry.exit_code !== undefined && entry.exit_code !== null && entry.exit_code !== ''
            ? String(entry.exit_code)
            : '—';
        const output = entry && entry.output ? entry.output : '';
        const outputHtml = output
            ? `<pre>${escapeHtml(output)}</pre>`
            : '<span class="muted">Keine Ausgabe</span>';
        return `
            <tr>
                <td>${escapeHtml(label)}</td>
                <td><code>${escapeHtml(command)}</code></td>
                <td>${escapeHtml(exitCode)}</td>
                <td>${outputHtml}</td>
            </tr>
        `;
    }).join('');

    systemUpdateLog.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Schritt</th>
                    <th>Befehl</th>
                    <th>Exit-Code</th>
                    <th>Ausgabe</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    `;
    systemUpdateLog.classList.remove('hidden');
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
        state.guestLookupResults = [];
        state.guestLookupTerm = '';
        populateCompanyDropdowns();
        resetReservationForm();
        resetGuestForm();
        resetCompanyForm();
        state.calendarColorsLoaded = false;
        state.calendarColors = { ...CALENDAR_COLOR_DEFAULTS };
        state.calendarColorTokens = {};
        applyCalendarColors(state.calendarColors);
        populateCalendarColorInputs();
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

if (calendarSettingsButton) {
    calendarSettingsButton.addEventListener('click', () => {
        showSection('settings');
        const settingsSection = document.getElementById('settings');
        if (settingsSection && typeof settingsSection.scrollIntoView === 'function') {
            settingsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

if (occupancyCalendarContainer) {
    occupancyCalendarContainer.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const categoryToggle = target.closest('.category-toggle');
        if (categoryToggle) {
            const categoryId = categoryToggle.dataset.categoryId;
            if (categoryId) {
                if (state.collapsedCategories.has(categoryId)) {
                    state.collapsedCategories.delete(categoryId);
                } else {
                    state.collapsedCategories.add(categoryId);
                }
                renderOccupancyCalendar(
                    state.rooms,
                    state.reservations,
                    dashboardDateInput && dashboardDateInput.value ? dashboardDateInput.value : toLocalISODate(),
                    CALENDAR_DAYS,
                    state.calendarLabelMode,
                    state.calendarCategorySort,
                );
            }
            return;
        }
        const entry = target.closest('.calendar-entry');
        const cell = target.closest('td.occupied');
        if (!cell) {
            return;
        }
        const reservationIdAttr = entry?.dataset.reservationId || cell.dataset.reservationId;
        const reservationId = Number(reservationIdAttr);
        if (!reservationId) {
            return;
        }
        showSection('reservations');
        startReservationEdit(reservationId);
    });
}

if (reservationCreateInvoiceButton) {
    reservationCreateInvoiceButton.addEventListener('click', async () => {
        if (!state.editingReservationId) {
            showMessage('Bitte wählen Sie zuerst eine Reservierung aus.', 'error');
            return;
        }
        if (!requireToken()) {
            return;
        }
        reservationCreateInvoiceButton.disabled = true;
        try {
            const invoice = await apiFetch(`reservations/${state.editingReservationId}/invoice`, { method: 'POST' });
            const number = invoice?.invoice_number || invoice?.id || '';
            showMessage(number ? `Rechnung ${number} erstellt.` : 'Rechnung erstellt.', 'success');
            await Promise.all([
                loadReservations(true),
                loadBilling(true),
            ]);
            await startReservationEdit(state.editingReservationId);
        } catch (error) {
            reservationCreateInvoiceButton.disabled = false;
            showMessage(error.message, 'error');
        }
    });
}

if (reservationPayInvoiceButton) {
    reservationPayInvoiceButton.addEventListener('click', async () => {
        if (!state.editingReservationId) {
            showMessage('Bitte wählen Sie zuerst eine Reservierung aus.', 'error');
            return;
        }
        if (!requireToken()) {
            return;
        }
        const latestInvoice = state.currentReservationInvoices[0];
        if (!latestInvoice) {
            showMessage('Für diese Reservierung existiert noch keine Rechnung.', 'error');
            return;
        }
        if (latestInvoice.status === 'paid') {
            showMessage('Die Rechnung ist bereits als bezahlt erfasst.', 'info');
            return;
        }
        reservationPayInvoiceButton.disabled = true;
        try {
            await apiFetch(`reservations/${state.editingReservationId}/invoice-pay`, { method: 'POST' });
            showMessage('Zahlung verbucht und Rechnung aktualisiert.', 'success');
            await Promise.all([
                loadReservations(true),
                loadBilling(true),
                loadDashboard(true),
            ]);
            await startReservationEdit(state.editingReservationId);
        } catch (error) {
            reservationPayInvoiceButton.disabled = false;
            showMessage(error.message, 'error');
        }
    });
}

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

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatChargeScheme(scheme) {
    return ARTICLE_SCHEME_LABELS[scheme] || scheme;
}

function formatArticleHint(article) {
    const scheme = formatChargeScheme(article.charge_scheme);
    const price = formatCurrency(article.unit_price ?? 0, article.currency || 'EUR');
    const tax = Number(article.tax_rate ?? 0).toFixed(1);
    return `${scheme} · ${price} · MwSt ${tax}%`;
}

function updateReservationMeta(reservation = null) {
    if (!reservationMeta) {
        return;
    }

    if (!reservation) {
        reservationMeta.classList.add('hidden');
        state.currentReservationInvoices = [];
        if (reservationNumberEl) {
            reservationNumberEl.textContent = '--';
        }
        if (reservationInvoiceStatus) {
            reservationInvoiceStatus.textContent = '';
        }
        if (reservationInvoiceLink) {
            reservationInvoiceLink.href = '#';
            reservationInvoiceLink.classList.add('hidden');
        }
        if (reservationCreateInvoiceButton) {
            reservationCreateInvoiceButton.disabled = true;
            reservationCreateInvoiceButton.textContent = 'Rechnung erstellen';
        }
        if (reservationPayInvoiceButton) {
            reservationPayInvoiceButton.disabled = true;
            reservationPayInvoiceButton.textContent = 'Als bezahlt verbuchen';
        }
        return;
    }

    reservationMeta.classList.remove('hidden');
    state.currentReservationInvoices = Array.isArray(reservation.invoices) ? reservation.invoices : [];

    if (reservationNumberEl) {
        const confirmation = reservation.confirmation_number || `ID ${reservation.id}`;
        reservationNumberEl.textContent = confirmation;
    }

    const latestInvoice = state.currentReservationInvoices[0] || null;
    const reservationCurrency = reservation.currency || 'EUR';

    if (reservationCreateInvoiceButton) {
        reservationCreateInvoiceButton.disabled = false;
        reservationCreateInvoiceButton.textContent = latestInvoice ? 'Neue Rechnung erstellen' : 'Rechnung erstellen';
    }

    if (latestInvoice && reservationInvoiceLink) {
        reservationInvoiceLink.href = buildInvoicePdfUrl(latestInvoice.id);
        reservationInvoiceLink.classList.remove('hidden');
    } else if (reservationInvoiceLink) {
        reservationInvoiceLink.href = '#';
        reservationInvoiceLink.classList.add('hidden');
    }

    if (reservationInvoiceStatus) {
        if (latestInvoice) {
            const label = INVOICE_STATUS_LABELS[latestInvoice.status] || latestInvoice.status || 'Offen';
            const amount = formatCurrency(latestInvoice.total_amount ?? 0, reservationCurrency);
            const parts = [`Nr. ${latestInvoice.invoice_number || latestInvoice.id}`, label, amount];
            if (latestInvoice.due_date) {
                parts.push(`Fällig: ${formatDate(latestInvoice.due_date)}`);
            }
            reservationInvoiceStatus.textContent = parts.join(' · ');
        } else {
            reservationInvoiceStatus.textContent = 'Noch keine Rechnung erstellt.';
        }
    }

    if (reservationPayInvoiceButton) {
        if (latestInvoice && latestInvoice.status !== 'paid') {
            reservationPayInvoiceButton.disabled = false;
            reservationPayInvoiceButton.textContent = 'Als bezahlt verbuchen';
        } else {
            reservationPayInvoiceButton.disabled = true;
            reservationPayInvoiceButton.textContent = latestInvoice ? 'Bereits bezahlt' : 'Als bezahlt verbuchen';
        }
    }
}

function buildInvoicePdfUrl(invoiceId) {
    if (!invoiceId) {
        return '#';
    }
    const tokenParam = state.token ? `?token=${encodeURIComponent(state.token)}` : '';
    return `${API_BASE}/invoices/${invoiceId}/pdf${tokenParam}`;
}

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error || new Error('Datei konnte nicht gelesen werden.'));
        reader.readAsDataURL(file);
    });
}

function updateInvoiceLogoPreview(dataUrl) {
    if (!invoiceLogoPreview) {
        return;
    }
    state.invoiceLogoDataUrl = dataUrl || null;
    if (dataUrl) {
        invoiceLogoPreview.innerHTML = `<img src="${dataUrl}" alt="Rechnungslogo" class="logo-image">`;
        if (removeInvoiceLogoButton) {
            removeInvoiceLogoButton.classList.remove('hidden');
        }
    } else {
        invoiceLogoPreview.innerHTML = '<p class="muted">Noch kein Logo hochgeladen.</p>';
        if (removeInvoiceLogoButton) {
            removeInvoiceLogoButton.classList.add('hidden');
        }
    }
}

function normalizeHexColorInput(value) {
    if (!value) {
        return null;
    }
    const trimmed = value.toString().trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{6}$/.test(trimmed)) {
        return `#${trimmed.toLowerCase()}`;
    }
    if (/^[0-9a-fA-F]{3}$/.test(trimmed)) {
        return `#${trimmed[0]}${trimmed[0]}${trimmed[1]}${trimmed[1]}${trimmed[2]}${trimmed[2]}`.toLowerCase();
    }
    return null;
}

function hexToRgb(color) {
    const normalized = normalizeHexColorInput(color);
    if (!normalized) {
        return null;
    }
    const value = normalized.replace('#', '');
    const bigint = parseInt(value, 16);
    if (Number.isNaN(bigint)) {
        return null;
    }
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
    };
}

function rgbaFromHex(color, alpha = 0.55) {
    const rgb = hexToRgb(color);
    if (!rgb) {
        return null;
    }
    const safeAlpha = Math.max(0, Math.min(1, alpha));
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${safeAlpha})`;
}

function getReadableTextColor(color) {
    const rgb = hexToRgb(color);
    if (!rgb) {
        return '#ffffff';
    }
    const luminance = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
    return luminance > 0.6 ? '#111827' : '#ffffff';
}

function applyCalendarColors(colors = state.calendarColors) {
    const merged = { ...CALENDAR_COLOR_DEFAULTS, ...(colors || {}) };
    const tokens = {};
    CALENDAR_STATUS_ORDER.forEach((status) => {
        const fallback = CALENDAR_COLOR_DEFAULTS[status];
        const normalized = normalizeHexColorInput(merged[status]) || fallback;
        const border = rgbaFromHex(normalized, 0.55) || rgbaFromHex(fallback, 0.55) || 'rgba(37, 99, 235, 0.55)';
        const textColor = getReadableTextColor(normalized);
        document.documentElement.style.setProperty(`--calendar-color-${status}`, normalized);
        document.documentElement.style.setProperty(`--calendar-border-${status}`, border);
        document.documentElement.style.setProperty(`--calendar-text-${status}`, textColor);
        tokens[status] = { color: normalized, border, text: textColor };
    });
    state.calendarColors = Object.fromEntries(
        CALENDAR_STATUS_ORDER.map((status) => [status, tokens[status].color])
    );
    state.calendarColorTokens = tokens;
}

function populateCalendarColorInputs() {
    if (!calendarColorForm) {
        return;
    }
    CALENDAR_STATUS_ORDER.forEach((status) => {
        const input = calendarColorForm.querySelector(`input[data-status="${status}"]`);
        if (input) {
            input.value = (state.calendarColors[status] || CALENDAR_COLOR_DEFAULTS[status]).toLowerCase();
        }
    });
}

function getCalendarColorToken(status) {
    const normalized = normalizeStatusClass(status);
    const token = state.calendarColorTokens[normalized];
    if (token) {
        return token;
    }
    const fallback = CALENDAR_COLOR_DEFAULTS[normalized] || CALENDAR_COLOR_DEFAULTS.confirmed;
    const border = rgbaFromHex(fallback, 0.55) || 'rgba(37, 99, 235, 0.55)';
    return {
        color: fallback,
        border,
        text: getReadableTextColor(fallback),
    };
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
    table.className = 'data-table';
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

function getRoomTypeById(roomTypeId) {
    return state.roomTypes.find((type) => Number(type.id) === Number(roomTypeId)) || null;
}

function getRatePlanById(ratePlanId) {
    return state.ratePlans.find((plan) => Number(plan.id) === Number(ratePlanId)) || null;
}

function getReservationRoomCount(requests = state.pendingRoomRequests) {
    if (!Array.isArray(requests)) {
        return 0;
    }
    return requests.reduce((total, request) => {
        const quantity = Number(request?.quantity ?? 0);
        if (Number.isFinite(quantity) && quantity > 0) {
            return total + quantity;
        }
        return total;
    }, 0);
}

function calculateNightsBetween(checkInValue, checkOutValue) {
    const checkIn = parseISODate(checkInValue);
    const checkOut = parseISODate(checkOutValue);
    if (!checkIn || !checkOut) {
        return 0;
    }
    const diff = Math.round((checkOut.getTime() - checkIn.getTime()) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
}

function populateRatePlanSelect() {
    if (!reservationRatePlanSelect) {
        return;
    }
    const previousValue = reservationRatePlanSelect.value;
    const options = state.ratePlans.map((plan) => {
        const labelParts = [plan.name || `Rate ${plan.id}`];
        if (plan.base_price !== null && plan.base_price !== undefined) {
            const currency = plan.currency || 'EUR';
            labelParts.push(`(${formatCurrency(plan.base_price, currency)}/Nacht)`);
        }
        return `<option value="${plan.id}">${labelParts.join(' ')}</option>`;
    });
    reservationRatePlanSelect.innerHTML = `<option value="">Keine Rate</option>${options.join('')}`;
    if (previousValue && state.ratePlans.some((plan) => String(plan.id) === previousValue)) {
        reservationRatePlanSelect.value = previousValue;
    }
}

function updateReservationPricing() {
    if (!reservationForm || !reservationNightlyRateInput || !reservationTotalAmountInput) {
        return;
    }
    const nights = calculateNightsBetween(reservationForm.check_in?.value, reservationForm.check_out?.value);
    const roomCount = getReservationRoomCount();
    const multiplier = nights > 0 && roomCount > 0 ? nights * roomCount : 0;
    const selectedPlanId = reservationRatePlanSelect && reservationRatePlanSelect.value
        ? Number(reservationRatePlanSelect.value)
        : null;
    const selectedPlan = selectedPlanId ? getRatePlanById(selectedPlanId) : null;

    const currentNightly = parseFloat(reservationNightlyRateInput.value);
    const currentTotal = parseFloat(reservationTotalAmountInput.value);
    let nightlyValue = Number.isFinite(currentNightly) ? currentNightly : NaN;
    let totalValue = Number.isFinite(currentTotal) ? currentTotal : NaN;

    if (reservationPricingOverride === 'nightly') {
        nightlyValue = Number.isFinite(currentNightly) ? currentNightly : NaN;
        if (multiplier > 0 && Number.isFinite(nightlyValue)) {
            totalValue = nightlyValue * multiplier;
        }
    } else if (reservationPricingOverride === 'total') {
        totalValue = Number.isFinite(currentTotal) ? currentTotal : NaN;
        if (multiplier > 0 && Number.isFinite(totalValue)) {
            nightlyValue = totalValue / multiplier;
        }
    } else {
        if (!Number.isFinite(nightlyValue) || nightlyValue <= 0) {
            if (selectedPlan && selectedPlan.base_price !== undefined && selectedPlan.base_price !== null) {
                nightlyValue = Number(selectedPlan.base_price);
            }
        }
        if (multiplier > 0 && Number.isFinite(nightlyValue) && nightlyValue >= 0) {
            totalValue = nightlyValue * multiplier;
        } else if (!Number.isFinite(totalValue) || totalValue < 0) {
            totalValue = NaN;
        }
    }

    isSyncingPricingFields = true;
    if (reservationPricingOverride !== 'nightly') {
        if (Number.isFinite(nightlyValue) && nightlyValue >= 0) {
            reservationNightlyRateInput.value = nightlyValue.toFixed(2);
        } else if (!reservationNightlyRateInput.value) {
            reservationNightlyRateInput.value = '';
        }
    }

    if (reservationPricingOverride !== 'total') {
        if (multiplier > 0 && Number.isFinite(totalValue) && totalValue >= 0) {
            reservationTotalAmountInput.value = totalValue.toFixed(2);
        } else if (!reservationPricingOverride) {
            reservationTotalAmountInput.value = '';
        }
    } else if (multiplier > 0 && Number.isFinite(nightlyValue) && nightlyValue >= 0) {
        reservationNightlyRateInput.value = nightlyValue.toFixed(2);
    }
    isSyncingPricingFields = false;

    if (selectedPlan && selectedPlan.currency && reservationForm.currency) {
        const currentCurrency = (reservationForm.currency.value || '').trim().toUpperCase();
        const planCurrency = String(selectedPlan.currency).trim().toUpperCase();
        if (!currentCurrency || currentCurrency === 'EUR') {
            reservationForm.currency.value = planCurrency || 'EUR';
        }
    }
}

function normalizeClientRoomRequests(requests = []) {
    const aggregated = new Map();
    requests.forEach((request) => {
        if (!request) {
            return;
        }
        const typeId = Number(request.room_type_id ?? request.id ?? request.room_type);
        if (!Number.isFinite(typeId) || typeId <= 0) {
            return;
        }
        const quantityValue = Number(request.quantity ?? request.count ?? 1);
        const quantity = Number.isFinite(quantityValue) && quantityValue > 0 ? Math.round(quantityValue) : 1;
        const key = String(typeId);
        const current = aggregated.get(key) || { room_type_id: typeId, quantity: 0 };
        current.quantity += quantity;
        aggregated.set(key, current);
    });
    return Array.from(aggregated.values());
}

function setReservationRoomRequests(requests) {
    state.pendingRoomRequests = normalizeClientRoomRequests(Array.isArray(requests) ? requests : []);
    renderReservationRoomRequests();
    updateReservationCapacityHint();
    updateReservationPricing();
}

function addReservationRoomRequest(typeId, quantity = 1) {
    const normalizedId = Number(typeId);
    if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
        return;
    }
    const normalizedQuantity = Number.isFinite(Number(quantity)) && Number(quantity) > 0 ? Math.round(Number(quantity)) : 1;
    const existing = state.pendingRoomRequests.find((entry) => Number(entry.room_type_id) === normalizedId);
    if (existing) {
        existing.quantity += normalizedQuantity;
    } else {
        state.pendingRoomRequests.push({ room_type_id: normalizedId, quantity: normalizedQuantity });
    }
    renderReservationRoomRequests();
    updateReservationCapacityHint();
    updateReservationPricing();
}

function updateReservationRoomRequestQuantity(typeId, quantity) {
    const normalizedId = Number(typeId);
    if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
        return;
    }
    const normalizedQuantity = Number.isFinite(Number(quantity)) && Number(quantity) > 0 ? Math.round(Number(quantity)) : 1;
    state.pendingRoomRequests = state.pendingRoomRequests.map((entry) => (
        Number(entry.room_type_id) === normalizedId
            ? { ...entry, quantity: normalizedQuantity }
            : entry
    ));
    renderReservationRoomRequests();
    updateReservationCapacityHint();
    updateReservationPricing();
}

function removeReservationRoomRequest(typeId) {
    const normalizedId = Number(typeId);
    state.pendingRoomRequests = state.pendingRoomRequests.filter((entry) => Number(entry.room_type_id) !== normalizedId);
    renderReservationRoomRequests();
    updateReservationCapacityHint();
    updateReservationPricing();
}

function calculateRoomRequestCapacityClient(requests = state.pendingRoomRequests) {
    return requests.reduce((total, request) => {
        const roomType = getRoomTypeById(request.room_type_id);
        const capacity = Number(roomType?.max_occupancy ?? roomType?.base_occupancy ?? 0);
        const quantity = Number(request.quantity ?? 0);
        if (Number.isFinite(capacity) && capacity > 0 && Number.isFinite(quantity) && quantity > 0) {
            return total + capacity * quantity;
        }
        return total;
    }, 0);
}

function renderReservationRoomRequests() {
    if (!roomRequestList) {
        return;
    }

    roomRequestList.innerHTML = '';
    if (!state.pendingRoomRequests.length) {
        const empty = document.createElement('p');
        empty.className = 'muted small-text';
        empty.textContent = 'Keine Kategorie ausgewählt.';
        roomRequestList.appendChild(empty);
        return;
    }

    const sorted = [...state.pendingRoomRequests].sort((a, b) => {
        const typeA = getRoomTypeById(a.room_type_id)?.name || '';
        const typeB = getRoomTypeById(b.room_type_id)?.name || '';
        return typeA.localeCompare(typeB, 'de', { sensitivity: 'base' });
    });

    const fragment = document.createDocumentFragment();
    sorted.forEach((request) => {
        const container = document.createElement('div');
        container.className = 'room-request-row';
        container.dataset.roomTypeId = String(request.room_type_id);

        const info = document.createElement('div');
        info.className = 'room-request-info';
        const roomType = getRoomTypeById(request.room_type_id);
        const name = roomType?.name || `Kategorie ${request.room_type_id}`;
        const capacity = Number(roomType?.max_occupancy ?? roomType?.base_occupancy ?? 0);
        const title = document.createElement('strong');
        title.textContent = name;
        info.appendChild(title);
        if (capacity > 0) {
            const meta = document.createElement('span');
            meta.className = 'room-request-meta';
            meta.textContent = `Kapazität: ${capacity} Gäste`;
            info.appendChild(meta);
        }
        container.appendChild(info);

        const controls = document.createElement('div');
        controls.className = 'room-request-actions';

        const quantityLabel = document.createElement('label');
        quantityLabel.className = 'inline-label';
        quantityLabel.textContent = 'Anzahl';
        const quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.min = '1';
        quantityInput.value = String(request.quantity);
        quantityInput.dataset.role = 'room-request-quantity';
        quantityLabel.appendChild(quantityInput);
        controls.appendChild(quantityLabel);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'secondary small';
        removeButton.dataset.action = 'remove-room-request';
        removeButton.textContent = 'Entfernen';
        controls.appendChild(removeButton);

        container.appendChild(controls);
        fragment.appendChild(container);
    });

    roomRequestList.appendChild(fragment);
}

function deriveRoomRequestsFromRooms(rooms) {
    if (!Array.isArray(rooms) || rooms.length === 0) {
        return [];
    }
    const aggregated = new Map();
    rooms.forEach((room) => {
        const typeId = Number(room.room_type_id ?? room.room_type ?? 0);
        if (!Number.isFinite(typeId) || typeId <= 0) {
            return;
        }
        const key = String(typeId);
        const current = aggregated.get(key) || { room_type_id: typeId, quantity: 0 };
        current.quantity += 1;
        aggregated.set(key, current);
    });
    return Array.from(aggregated.values());
}

function updateReservationCapacityHint() {
    if (!reservationForm || !reservationCapacityEl) {
        return;
    }
    const adults = Number(reservationForm.adults?.value || 0);
    const children = Number(reservationForm.children?.value || 0);
    const guestTotal = adults + children;
    const requests = state.pendingRoomRequests;

    if (!requests.length) {
        reservationCapacityEl.textContent = 'Bitte mindestens eine Zimmerkategorie hinzufügen.';
        reservationCapacityEl.classList.remove('text-danger');
        return;
    }

    const capacity = calculateRoomRequestCapacityClient(requests);
    if (capacity <= 0) {
        reservationCapacityEl.textContent = 'Für die ausgewählten Kategorien ist keine Kapazität hinterlegt.';
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

function renderReservationArticleOptions(selectedArticles = []) {
    if (!reservationArticleContainer) {
        return;
    }
    const activeArticles = state.articles.filter((article) => Number(article.is_active ?? 1) === 1);
    if (activeArticles.length === 0) {
        reservationArticleContainer.innerHTML = '<p class="muted">Keine Zusatzleistungen hinterlegt.</p>';
        return;
    }

    const selectedMap = new Map();
    selectedArticles.forEach((entry) => {
        const articleId = Number(entry.article_id ?? entry.id);
        if (!Number.isNaN(articleId)) {
            const multiplier = Number(entry.multiplier ?? entry.quantity ?? 1);
            selectedMap.set(articleId, Number.isFinite(multiplier) ? multiplier : 1);
        }
    });

    const markup = activeArticles.map((article) => {
        const id = Number(article.id);
        const isSelected = selectedMap.has(id);
        const multiplierValue = selectedMap.get(id) ?? 1;
        const hint = formatArticleHint(article);
        const description = article.description ? `<small>${escapeHtml(article.description)}</small>` : '';
        return `
            <div class="article-option" data-article-id="${id}">
                <label>
                    <input type="checkbox" value="${id}" ${isSelected ? 'checked' : ''}>
                    <span>
                        <strong>${escapeHtml(article.name || '')}</strong>
                        ${description}
                        <em>${escapeHtml(hint)}</em>
                    </span>
                </label>
                <input type="number" class="article-multiplier" min="0" step="0.1" value="${Number(multiplierValue).toString()}" ${isSelected ? '' : 'disabled'}>
            </div>
        `;
    }).join('');

    reservationArticleContainer.innerHTML = markup;
}

function renderArticlesTable() {
    if (!articlesList) {
        return;
    }
    const columns = [
        { key: 'name', label: 'Name', render: (row) => escapeHtml(row.name || '') },
        { key: 'charge_scheme', label: 'Abrechnung', render: (row) => escapeHtml(formatChargeScheme(row.charge_scheme)) },
        { key: 'unit_price', label: 'Preis', render: (row) => escapeHtml(formatCurrency(row.unit_price ?? 0, row.currency || 'EUR')) },
        { key: 'tax_rate', label: 'MwSt', render: (row) => `${Number(row.tax_rate ?? 0).toFixed(1)} %` },
        { key: 'is_active', label: 'Status', render: (row) => (Number(row.is_active ?? 1) ? 'Aktiv' : 'Inaktiv') },
        {
            key: 'actions',
            label: 'Aktionen',
            render: (row) => {
                const id = escapeHtml(String(row.id));
                const actions = [`<button type="button" class="text-link" data-action="edit-article" data-id="${id}">Bearbeiten</button>`];
                if (Number(row.is_active ?? 1)) {
                    actions.push(`<button type="button" class="text-link" data-action="deactivate-article" data-id="${id}">Deaktivieren</button>`);
                } else {
                    actions.push(`<button type="button" class="text-link" data-action="activate-article" data-id="${id}">Aktivieren</button>`);
                }
                return actions.join(' ');
            },
        },
    ];
    renderTable('articles-list', columns, state.articles, 'Noch keine Artikel erfasst.');
}

function resetArticleForm() {
    if (!articleForm) {
        return;
    }
    articleForm.reset();
    state.editingArticleId = null;
    if (articleSummary) {
        articleSummary.textContent = 'Neuer Artikel';
    }
    if (articleCancelButton) {
        articleCancelButton.classList.add('hidden');
    }
}

function startArticleEdit(articleId) {
    if (!articleForm) {
        return;
    }
    const article = state.articles.find((entry) => Number(entry.id) === Number(articleId));
    if (!article) {
        showMessage('Artikel wurde nicht gefunden.', 'error');
        return;
    }
    state.editingArticleId = Number(articleId);
    articleForm.name.value = article.name || '';
    articleForm.description.value = article.description || '';
    if (articleForm.charge_scheme) {
        articleForm.charge_scheme.value = article.charge_scheme || 'per_person_per_day';
    }
    articleForm.unit_price.value = Number(article.unit_price ?? 0).toFixed(2);
    articleForm.tax_rate.value = Number(article.tax_rate ?? 0).toFixed(1);
    if (articleForm.is_active) {
        articleForm.is_active.checked = Number(article.is_active ?? 1) === 1;
    }
    if (articleSummary) {
        articleSummary.textContent = `Artikel bearbeiten (${article.name || ''})`;
    }
    if (articleCancelButton) {
        articleCancelButton.classList.remove('hidden');
    }
    const section = articleForm.closest('.panel');
    if (section && typeof section.scrollIntoView === 'function') {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function formatGuestLabel(guest) {
    const parts = [];
    if (guest?.last_name) {
        parts.push(guest.last_name);
    }
    if (guest?.first_name) {
        parts.push(guest.first_name);
    }
    let label = parts.join(', ').trim();
    if (!label && guest?.email) {
        label = guest.email;
    }
    if (!label && guest?.phone) {
        label = guest.phone;
    }
    if (!label && guest?.id) {
        label = `Gast #${guest.id}`;
    }
    if (guest?.company_name) {
        label = `${label}${label ? ' – ' : ''}${guest.company_name}`;
    }
    return label;
}

function hideGuestSuggestions() {
    if (!guestSearchResults) {
        return;
    }
    guestSearchResults.classList.add('hidden');
    guestSearchResults.innerHTML = '';
}

function renderGuestSuggestions(results, term) {
    if (!guestSearchResults) {
        return;
    }
    const normalizedTerm = (term || '').trim();
    if (!normalizedTerm) {
        hideGuestSuggestions();
        return;
    }
    if (!Array.isArray(results) || results.length === 0) {
        guestSearchResults.innerHTML = '<p class="muted small-text">Keine Treffer</p>';
        guestSearchResults.classList.remove('hidden');
        return;
    }
    const items = results.map((guest) => {
        const label = formatGuestLabel(guest);
        const metaParts = [];
        if (guest.email) {
            metaParts.push(guest.email);
        }
        if (guest.phone) {
            metaParts.push(guest.phone);
        }
        const meta = metaParts.join(' · ');
        return `
            <button type="button" class="guest-suggestion" data-guest-id="${escapeHtml(String(guest.id))}">
                <span class="guest-suggestion-name">${escapeHtml(label)}</span>
                ${meta ? `<span class="guest-suggestion-meta">${escapeHtml(meta)}</span>` : ''}
            </button>
        `;
    }).join('');
    guestSearchResults.innerHTML = items;
    guestSearchResults.classList.remove('hidden');
}

function setGuestSelection(guest) {
    if (guestIdInput) {
        guestIdInput.value = guest?.id ? String(guest.id) : '';
    }
    const label = guest ? formatGuestLabel(guest) : '';
    if (guestSearchInput) {
        guestSearchInput.value = label;
        if (guest?.id) {
            guestSearchInput.dataset.selectedId = String(guest.id);
            guestSearchInput.dataset.selectedLabel = label;
        } else {
            delete guestSearchInput.dataset.selectedId;
            delete guestSearchInput.dataset.selectedLabel;
        }
    }
    if (guest?.id && !state.guests.some((entry) => Number(entry.id) === Number(guest.id))) {
        state.guests.push({ ...guest });
    }
    fillGuestFields(guest);
    if (guestClearSelectionButton) {
        if (guest?.id) {
            guestClearSelectionButton.classList.remove('hidden');
        } else {
            guestClearSelectionButton.classList.add('hidden');
        }
    }
}

function clearGuestSelection() {
    setGuestSelection(null);
    hideGuestSuggestions();
    if (guestSearchInput) {
        guestSearchInput.focus();
    }
}

async function performGuestLookup(term, requestId) {
    const normalizedTerm = term.trim();
    if (!normalizedTerm || normalizedTerm.length < 2) {
        state.guestLookupResults = [];
        state.guestLookupTerm = normalizedTerm;
        hideGuestSuggestions();
        return;
    }
    try {
        const response = await apiFetch(`guests?search=${encodeURIComponent(normalizedTerm)}&limit=10`);
        if (requestId !== guestLookupRequestId) {
            return;
        }
        const currentValue = guestSearchInput ? guestSearchInput.value.trim() : '';
        if (currentValue !== normalizedTerm) {
            return;
        }
        state.guestLookupResults = Array.isArray(response) ? response : [];
        state.guestLookupTerm = normalizedTerm;
        renderGuestSuggestions(state.guestLookupResults, normalizedTerm);
    } catch (error) {
        if (requestId === guestLookupRequestId) {
            hideGuestSuggestions();
            showMessage(error.message, 'error');
        }
    }
}

function scheduleGuestLookup(term) {
    if (guestLookupDebounceId) {
        clearTimeout(guestLookupDebounceId);
    }
    const normalizedTerm = (term || '').trim();
    if (!normalizedTerm || normalizedTerm.length < 2) {
        state.guestLookupResults = [];
        state.guestLookupTerm = normalizedTerm;
        hideGuestSuggestions();
        return;
    }
    guestLookupDebounceId = setTimeout(() => {
        guestLookupRequestId += 1;
        performGuestLookup(normalizedTerm, guestLookupRequestId);
    }, 200);
}

function fillGuestFields(guest) {
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
    state.currentReservationInvoices = [];
    reservationPricingOverride = null;
    if (reservationSummary) {
        reservationSummary.textContent = 'Neue Reservierung anlegen';
    }
    if (reservationSubmitButton) {
        reservationSubmitButton.textContent = 'Reservierung speichern';
    }
    if (reservationCancelButton) {
        reservationCancelButton.classList.add('hidden');
    }
    ensureReservationReferenceData();
    populateCompanyDropdowns();
    renderReservationArticleOptions();
    setReservationRoomRequests([]);
    setGuestSelection(null);
    hideGuestSuggestions();
    if (reservationDetails) {
        reservationDetails.open = wasOpen;
    }
    updateReservationCapacityHint();
    updateReservationMeta(null);
    updateReservationPricing();
}

function fillReservationForm(reservation) {
    if (!reservationForm) {
        return;
    }
    ensureReservationReferenceData();
    populateCompanyDropdowns();

    reservationForm.check_in.value = reservation.check_in_date ? reservation.check_in_date.slice(0, 10) : '';
    reservationForm.check_out.value = reservation.check_out_date ? reservation.check_out_date.slice(0, 10) : '';
    reservationForm.adults.value = reservation.adults ?? 1;
    reservationForm.children.value = reservation.children ?? 0;
    reservationForm.total_amount.value = reservation.total_amount ?? '';
    reservationForm.currency.value = reservation.currency || 'EUR';
    reservationForm.status.value = reservation.status || 'confirmed';
    reservationForm.booked_via.value = reservation.booked_via || '';
    if (reservationRatePlanSelect) {
        reservationRatePlanSelect.value = reservation.rate_plan_id ? String(reservation.rate_plan_id) : '';
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

    setGuestSelection(guestSnapshot);
    renderReservationArticleOptions(reservation.articles || []);
    const roomRequestSource = Array.isArray(reservation.room_requests) && reservation.room_requests.length
        ? reservation.room_requests
        : deriveRoomRequestsFromRooms(reservation.rooms || []);
    if (reservationNightlyRateInput) {
        const nights = calculateNightsBetween(reservation.check_in_date, reservation.check_out_date);
        const requestRoomCount = getReservationRoomCount(roomRequestSource);
        const assignedRoomCount = Array.isArray(reservation.rooms) ? reservation.rooms.length : 0;
        const roomCountForCalc = requestRoomCount > 0 ? requestRoomCount : assignedRoomCount;
        let nightlyRate = null;
        if (Array.isArray(reservation.rooms)) {
            const roomWithRate = reservation.rooms.find((room) => Number(room.nightly_rate) > 0);
            if (roomWithRate) {
                nightlyRate = Number(roomWithRate.nightly_rate);
            }
        }
        if (!Number.isFinite(nightlyRate) || nightlyRate <= 0) {
            const selectedPlan = reservation.rate_plan_id ? getRatePlanById(reservation.rate_plan_id) : null;
            if (selectedPlan && selectedPlan.base_price !== undefined && selectedPlan.base_price !== null) {
                nightlyRate = Number(selectedPlan.base_price);
            }
        }
        if ((!Number.isFinite(nightlyRate) || nightlyRate <= 0) && Number.isFinite(Number(reservation.total_amount))) {
            const multiplier = nights > 0 ? nights * Math.max(roomCountForCalc || 1, 1) : 0;
            if (multiplier > 0) {
                nightlyRate = Number(reservation.total_amount) / multiplier;
            }
        }
        reservationNightlyRateInput.value = Number.isFinite(nightlyRate) && nightlyRate >= 0 ? nightlyRate.toFixed(2) : '';
    }
    reservationPricingOverride = null;
    setReservationRoomRequests(roomRequestSource);
    updateReservationCapacityHint();
    updateReservationMeta(reservation);
    updateReservationPricing();
}

function formatReservationAssignment(row) {
    const rooms = Array.isArray(row.rooms) ? row.rooms : [];
    if (rooms.length) {
        return rooms
            .map((room) => {
                const number = room.room_number || room.name || `Zimmer ${room.room_id ?? room.id ?? ''}`;
                const typeName = room.room_type_name ? ` (${room.room_type_name})` : '';
                return escapeHtml(`${number}${typeName}`);
            })
            .join('<br>');
    }

    const requests = Array.isArray(row.room_requests) ? row.room_requests : [];
    if (requests.length) {
        const lines = requests
            .map((request) => {
                const type = request.room_type_name || getRoomTypeById(request.room_type_id)?.name || `Kategorie ${request.room_type_id}`;
                const quantity = Number(request.quantity ?? 1);
                const suffix = Number.isFinite(quantity) && quantity > 1 ? ` × ${quantity}` : '';
                return `<div>${escapeHtml(`${type}${suffix}`)}</div>`;
            })
            .join('');
        return `<div><strong>Überbuchung</strong>${lines}</div>`;
    }

    return '<span class="muted small-text">Noch nicht zugewiesen</span>';
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
        {
            key: 'guest',
            label: 'Gast',
            render: (row) => {
                const guestName = `${row.first_name || ''} ${row.last_name || ''}`.trim();
                if (row.company_name) {
                    return `${guestName || ''}<div class="table-subline">${row.company_name}</div>`;
                }
                return guestName;
            },
        },
        { key: 'check_in_date', label: 'Check-in', render: (row) => formatDate(row.check_in_date) },
        { key: 'check_out_date', label: 'Check-out', render: (row) => formatDate(row.check_out_date) },
        { key: 'status', label: 'Status', render: (row) => formatReservationStatus(row.status) },
        { key: 'rooms', label: 'Zuweisung', render: (row) => formatReservationAssignment(row) },
        { key: 'total_amount', label: 'Gesamt', render: (row) => formatCurrency(row.total_amount, row.currency || 'EUR') },
        {
            key: 'actions',
            label: 'Aktionen',
            render: (row) => renderReservationActions(row),
        },
    ], reservations);
}

function renderReservationActions(row) {
    const status = normalizeStatusClass(row.status || '');
    const buttons = RESERVATION_STATUS_ACTIONS.map((action) => {
        const token = getCalendarColorToken(action.status);
        const isDisabled = status === action.status;
        const style = `--action-color:${token.color};--action-border:${token.border};--action-text:${token.text};`;
        const titleAttr = action.title ? ` title="${action.title}"` : '';
        return `<button type="button" class="reservation-status status-action" data-id="${row.id}" data-status="${action.status}" style="${style}"${titleAttr}${isDisabled ? ' disabled' : ''}>${action.label}</button>`;
    }).join('');

    return `<div class="table-actions">${buttons}<button type="button" class="secondary reservation-edit" data-id="${row.id}">Bearbeiten</button></div>`;
}

async function updateReservationStatus(reservationId, status) {
    if (!requireToken()) {
        return;
    }
    try {
        await apiFetch(`reservations/${reservationId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
        showMessage('Reservierungsstatus aktualisiert.', 'success');
        await Promise.all([
            loadReservations(true),
            loadDashboard(true),
        ]);
    } catch (error) {
        showMessage(error.message, 'error');
    }
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

function renderOccupancyCalendar(rooms, reservations, startDateStr, days = CALENDAR_DAYS, labelMode = state.calendarLabelMode || 'guest', categorySort = state.calendarCategorySort || 'name_asc') {
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
    const overbookingRows = new Map();
    const reservationsList = Array.isArray(reservations) ? reservations : [];

    const pushEntry = (rowId, dateKeyValue, entry) => {
        const mapKey = `${rowId}_${dateKeyValue}`;
        const bucket = occupancyMap.get(mapKey);
        if (bucket) {
            bucket.push(entry);
        } else {
            occupancyMap.set(mapKey, [entry]);
        }
    };

    reservationsList.forEach((reservation) => {
        const statusClass = normalizeStatusClass(reservation.status || '');
        if (statusClass === 'cancelled') {
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

        const requestList = Array.isArray(reservation.room_requests) ? reservation.room_requests : [];

        (reservation.rooms || []).forEach((room) => {
            const roomId = room.room_id ?? room.id;
            if (!roomId) {
                return;
            }
            const rowKey = `room-${roomId}`;
            for (let cursor = new Date(visibleStart); cursor < visibleEnd; cursor = addDays(cursor, 1)) {
                const keyDate = dateKey(cursor);
                if (!dayKeySet.has(keyDate)) {
                    continue;
                }
                pushEntry(rowKey, keyDate, {
                    reservation,
                    statusClass,
                    roomTypeName: room.room_type_name || null,
                    isOverbooking: false,
                    quantity: 1,
                });
            }
        });

        requestList.forEach((request) => {
            const typeId = Number(request.room_type_id ?? request.id ?? 0);
            if (!Number.isFinite(typeId) || typeId <= 0) {
                return;
            }
            const quantity = Number(request.quantity ?? 1);
            const normalizedQuantity = Number.isFinite(quantity) && quantity > 0 ? quantity : 1;
            const rowKey = `overbook-${typeId}`;
            if (!overbookingRows.has(rowKey)) {
                const roomType = getRoomTypeById(typeId);
                const typeName = roomType?.name || request.room_type_name || `Kategorie ${typeId}`;
                overbookingRows.set(rowKey, {
                    calendar_id: rowKey,
                    room_type_id: typeId,
                    room_type_name: typeName,
                    label: `Überbuchung – ${typeName}`,
                    isOverbooking: true,
                });
            }
            const roomTypeName = getRoomTypeById(typeId)?.name || request.room_type_name || `Kategorie ${typeId}`;
            for (let cursor = new Date(visibleStart); cursor < visibleEnd; cursor = addDays(cursor, 1)) {
                const keyDate = dateKey(cursor);
                if (!dayKeySet.has(keyDate)) {
                    continue;
                }
                pushEntry(rowKey, keyDate, {
                    reservation,
                    statusClass,
                    roomTypeName,
                    isOverbooking: true,
                    quantity: normalizedQuantity,
                });
            }
        });
    });

    const table = document.createElement('table');
    table.className = 'calendar-table';
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    const roomHeader = document.createElement('th');
    roomHeader.className = 'room';
    roomHeader.textContent = 'Kategorie / Zimmer';
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
    const sortRooms = (a, b) => {
        const aType = a.room_type_name || '';
        const bType = b.room_type_name || '';
        const typeCompare = aType.localeCompare(bType, 'de', { sensitivity: 'base' });
        if (typeCompare !== 0) {
            return typeCompare;
        }
        const aValue = (a.room_number ?? a.name ?? '').toString();
        const bValue = (b.room_number ?? b.name ?? '').toString();
        return aValue.localeCompare(bValue, 'de', { numeric: true, sensitivity: 'base' });
    };

    const roomsByCategory = new Map();
    const sortedRooms = [...rooms].sort(sortRooms);
    sortedRooms.forEach((room) => {
        const typeIdRaw = Number(room.room_type_id);
        const hasType = Number.isFinite(typeIdRaw) && typeIdRaw > 0;
        const typeId = hasType ? typeIdRaw : null;
        const categoryKey = hasType ? `type-${typeId}` : 'uncategorized';
        const typeName = hasType
            ? getRoomTypeById(typeId)?.name || room.room_type_name || `Kategorie ${typeId}`
            : room.room_type_name || 'Ohne Kategorie';
        if (!roomsByCategory.has(categoryKey)) {
            roomsByCategory.set(categoryKey, {
                key: categoryKey,
                typeId,
                name: typeName,
                rooms: [],
                overbookingRow: null,
            });
        }
        roomsByCategory.get(categoryKey).rooms.push(room);
    });

    overbookingRows.forEach((rowInfo) => {
        const typeId = Number(rowInfo.room_type_id);
        const hasType = Number.isFinite(typeId) && typeId > 0;
        const categoryKey = hasType ? `type-${typeId}` : rowInfo.calendar_id;
        if (!roomsByCategory.has(categoryKey)) {
            roomsByCategory.set(categoryKey, {
                key: categoryKey,
                typeId: hasType ? typeId : null,
                name: rowInfo.room_type_name || (hasType ? `Kategorie ${typeId}` : 'Überbuchung'),
                rooms: [],
                overbookingRow: rowInfo,
            });
        } else {
            roomsByCategory.get(categoryKey).overbookingRow = rowInfo;
        }
    });

    const categories = Array.from(roomsByCategory.values());
    categories.forEach((category) => {
        category.rooms.sort((a, b) => {
            const aValue = (a.room_number ?? a.name ?? '').toString();
            const bValue = (b.room_number ?? b.name ?? '').toString();
            return aValue.localeCompare(bValue, 'de', { numeric: true, sensitivity: 'base' });
        });
    });

    const normalizedSort = ['name_asc', 'name_desc', 'room_count_asc', 'room_count_desc'].includes(categorySort)
        ? categorySort
        : 'name_asc';

    state.calendarCategorySort = normalizedSort;

    categories.sort((a, b) => {
        if (normalizedSort === 'room_count_asc' || normalizedSort === 'room_count_desc') {
            const multiplier = normalizedSort === 'room_count_desc' ? -1 : 1;
            const diff = (a.rooms.length - b.rooms.length) * multiplier;
            if (diff !== 0) {
                return diff;
            }
            return (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' });
        }
        const direction = normalizedSort === 'name_desc' ? -1 : 1;
        const comparison = (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' });
        if (comparison !== 0) {
            return comparison * direction;
        }
        return (a.rooms.length - b.rooms.length) * (direction === 1 ? 1 : -1);
    });

    const existingCategoryKeys = new Set(categories.map((category) => category.key));
    Array.from(state.collapsedCategories).forEach((categoryKey) => {
        if (!existingCategoryKeys.has(categoryKey)) {
            state.collapsedCategories.delete(categoryKey);
        }
    });

    const appendRow = (rowInfo, categoryKey, collapsed) => {
        const row = document.createElement('tr');
        row.dataset.categoryId = categoryKey;
        row.classList.add('category-child');
        if (rowInfo.isOverbooking) {
            row.classList.add('overbooking-row');
        }
        if (collapsed) {
            row.classList.add('is-hidden');
        }
        const roomCell = document.createElement('th');
        roomCell.scope = 'row';
        roomCell.className = 'room';
        roomCell.textContent = rowInfo.label;
        if (rowInfo.isOverbooking) {
            roomCell.classList.add('overbooking');
        }
        row.appendChild(roomCell);

        dayDates.forEach((date) => {
            const key = `${rowInfo.calendar_id}_${dateKey(date)}`;
            const cell = document.createElement('td');
            cell.dataset.date = dateKey(date);
            if (cell.dataset.date === todayKey) {
                cell.classList.add('today');
            }
            if (isWeekend(date)) {
                cell.classList.add('weekend');
            }

            const entries = occupancyMap.get(key) || [];
            if (entries.length > 0) {
                cell.classList.add('occupied');
                if (entries.length > 1) {
                    cell.classList.add('multi');
                }
                const firstEntry = entries[0];
                if (firstEntry.statusClass) {
                    cell.classList.add(`status-${firstEntry.statusClass}`);
                    cell.dataset.status = firstEntry.statusClass;
                }
                if (firstEntry.reservation?.id) {
                    cell.dataset.reservationId = String(firstEntry.reservation.id);
                } else {
                    delete cell.dataset.reservationId;
                }
                cell.textContent = '';
                entries.forEach((entry) => {
                    const entryDiv = document.createElement('div');
                    entryDiv.className = 'calendar-entry';
                    if (entry.statusClass) {
                        entryDiv.classList.add(`status-${entry.statusClass}`);
                    }
                    if (entry.isOverbooking) {
                        entryDiv.classList.add('overbooking');
                    }
                    if (entry.reservation?.id) {
                        entryDiv.dataset.reservationId = String(entry.reservation.id);
                    }
                    const label = getReservationCalendarLabel(entry.reservation, labelMode);
                    const quantitySuffix = entry.isOverbooking && entry.quantity > 1 ? ` ×${entry.quantity}` : '';
                    const typeSuffix = entry.isOverbooking && entry.roomTypeName ? ` • ${entry.roomTypeName}` : '';
                    entryDiv.textContent = `${label}${quantitySuffix}${typeSuffix}`;
                    const guestName = `${entry.reservation.first_name || ''} ${entry.reservation.last_name || ''}`.trim();
                    const statusLabel = formatReservationStatus(entry.reservation.status);
                    const details = [
                        label !== guestName && guestName ? `Gast: ${guestName}` : guestName,
                        entry.reservation.company_name ? `Firma: ${entry.reservation.company_name}` : null,
                        entry.reservation.confirmation_number ? `Bestätigungsnr.: ${entry.reservation.confirmation_number}` : null,
                        statusLabel ? `Status: ${statusLabel}` : null,
                        entry.reservation.check_in_date ? `Anreise: ${formatDate(entry.reservation.check_in_date)}` : null,
                        entry.reservation.check_out_date ? `Abreise: ${formatDate(entry.reservation.check_out_date)}` : null,
                        entry.isOverbooking && entry.roomTypeName ? `Kategorie: ${entry.roomTypeName}` : null,
                        entry.isOverbooking && entry.quantity > 1 ? `Einheiten: ${entry.quantity}` : null,
                    ].filter(Boolean);
                    if (details.length) {
                        entryDiv.title = details.join('\n');
                    }
                    cell.appendChild(entryDiv);
                });
            } else {
                cell.classList.add('vacant');
                cell.textContent = rowInfo.isOverbooking ? 'Keine Überbuchung' : 'Frei';
                delete cell.dataset.reservationId;
            }
            row.appendChild(cell);
        });

        tbody.appendChild(row);
    };

    categories.forEach((category) => {
        const isCollapsed = state.collapsedCategories.has(category.key);
        const categoryRow = document.createElement('tr');
        categoryRow.classList.add('category-row');
        if (isCollapsed) {
            categoryRow.classList.add('collapsed');
        }
        categoryRow.dataset.categoryId = category.key;

        const categoryCell = document.createElement('th');
        categoryCell.scope = 'row';
        categoryCell.className = 'room';
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'category-toggle';
        toggleButton.dataset.categoryId = category.key;
        toggleButton.setAttribute('aria-expanded', String(!isCollapsed));
        const roomCountLabel = category.rooms.length > 0 ? ` (${category.rooms.length} Zimmer)` : '';
        toggleButton.textContent = `${category.name}${roomCountLabel}`;
        toggleButton.title = isCollapsed ? 'Kategorie anzeigen' : 'Kategorie ausblenden';
        categoryCell.appendChild(toggleButton);
        categoryRow.appendChild(categoryCell);

        dayDates.forEach((date) => {
            const summaryCell = document.createElement('td');
            summaryCell.classList.add('category-summary');
            const currentKey = dateKey(date);
            summaryCell.dataset.date = currentKey;
            if (currentKey === todayKey) {
                summaryCell.classList.add('today');
            }
            if (isWeekend(date)) {
                summaryCell.classList.add('weekend');
            }

            const totalRooms = category.rooms.length;
            let occupiedRooms = 0;
            category.rooms.forEach((room) => {
                const roomId = room.id ?? room.room_id;
                if (!roomId) {
                    return;
                }
                const mapKey = `room-${roomId}_${currentKey}`;
                const entries = occupancyMap.get(mapKey) || [];
                if (entries.length > 0) {
                    occupiedRooms += 1;
                }
            });

            let overbookingCount = 0;
            if (category.overbookingRow) {
                const overKey = `${category.overbookingRow.calendar_id}_${currentKey}`;
                const overEntries = occupancyMap.get(overKey) || [];
                overbookingCount = overEntries.reduce((sum, entry) => sum + (Number(entry.quantity) || 0), 0);
            }

            let summaryText = '—';
            if (totalRooms > 0) {
                summaryText = `${occupiedRooms}/${totalRooms}`;
                if (occupiedRooms === 0) {
                    summaryCell.classList.add('category-empty');
                } else if (occupiedRooms >= totalRooms) {
                    summaryCell.classList.add('category-full');
                } else {
                    summaryCell.classList.add('category-partial');
                }
                const detailParts = [`${occupiedRooms} von ${totalRooms} Zimmern belegt`];
                if (overbookingCount > 0) {
                    detailParts.push(`Überbuchung: ${overbookingCount}`);
                }
                summaryCell.title = detailParts.join('\n');
            }

            if (overbookingCount > 0) {
                summaryText = totalRooms > 0 ? `${summaryText} (+${overbookingCount})` : `+${overbookingCount}`;
                summaryCell.classList.add('category-overbooking', 'has-overbooking');
                if (!summaryCell.title) {
                    summaryCell.title = `Überbuchung: ${overbookingCount}`;
                }
            }

            summaryCell.textContent = summaryText;
            categoryRow.appendChild(summaryCell);
        });

        tbody.appendChild(categoryRow);

        category.rooms.forEach((room) => {
            const roomId = room.id ?? room.room_id;
            if (!roomId) {
                return;
            }
            const baseLabel = room.room_number || room.name || `Zimmer ${roomId}`;
            const label = room.room_type_name && room.room_type_name !== category.name
                ? `${baseLabel} (${room.room_type_name})`
                : baseLabel;
            appendRow({
                calendar_id: `room-${roomId}`,
                label,
                isOverbooking: false,
            }, category.key, isCollapsed);
        });

        if (category.overbookingRow) {
            appendRow(category.overbookingRow, category.key, isCollapsed);
        }
    });

    table.appendChild(tbody);
    container.appendChild(table);
}

async function bootstrap() {
    if (!requireToken()) {
        return;
    }
    try {
        const [roomTypes, ratePlans, rooms, roles, guests, companies, articles, logoResponse] = await Promise.all([
            apiFetch('room-types'),
            apiFetch('rate-plans'),
            apiFetch('rooms'),
            apiFetch('roles'),
            apiFetch('guests'),
            apiFetch('companies'),
            apiFetch('articles?include_inactive=1'),
            apiFetch('settings/invoice-logo'),
        ]);
        state.roomTypes = roomTypes;
        state.ratePlans = ratePlans;
        state.rooms = rooms;
        state.roles = roles;
        state.guests = guests;
        state.companies = companies;
        state.companiesLoaded = true;
        state.articles = Array.isArray(articles) ? articles : [];
        state.articlesLoaded = true;
        const logoData = logoResponse && typeof logoResponse === 'object' ? logoResponse.logo || null : null;
        updateInvoiceLogoPreview(logoData);
        await loadCalendarColors(true);
        populateRoomTypeSelects();
        populateRoomOptions();
        populateRoleCheckboxes();
        populateRoomTypeList();
        populateRatePlanList();
        populateCompanyDropdowns();
        renderArticlesTable();
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
    const sortedTypes = [...state.roomTypes].sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' }));
    const roomSelect = document.querySelector('#room-form select[name="room_type"]');
    if (roomSelect) {
        roomSelect.innerHTML = sortedTypes.map((type) => `<option value="${type.id}">${type.name}</option>`).join('');
    }
    if (roomRequestTypeSelect) {
        const previousValue = roomRequestTypeSelect.value;
        const options = sortedTypes.map((type) => `<option value="${type.id}">${type.name}</option>`);
        roomRequestTypeSelect.innerHTML = `<option value="">Kategorie wählen</option>${options.join('')}`;
        if (previousValue && [...roomRequestTypeSelect.options].some((option) => option.value === previousValue)) {
            roomRequestTypeSelect.value = previousValue;
        }
    }
    renderReservationRoomRequests();
}

function populateRoomOptions() {
    const taskRoomSelect = document.querySelector('#task-form select[name="room"]');
    const sortedRooms = [...state.rooms].sort((a, b) => {
        const typeCompare = (a.room_type_name || '').localeCompare(b.room_type_name || '', 'de', { sensitivity: 'base' });
        if (typeCompare !== 0) {
            return typeCompare;
        }
        const aValue = (a.room_number ?? a.name ?? '').toString();
        const bValue = (b.room_number ?? b.name ?? '').toString();
        return aValue.localeCompare(bValue, 'de', { numeric: true, sensitivity: 'base' });
    });
    const options = sortedRooms.map((room) => {
        const capacity = Number(room.max_occupancy ?? room.base_occupancy ?? 0);
        const capacityText = capacity > 0 ? ` • max. ${capacity}` : '';
        const typeLabel = room.room_type_name || 'Kategorie';
        return `<option value="${room.id}">${room.room_number} (${typeLabel}${capacityText})</option>`;
    });
    if (taskRoomSelect) {
        taskRoomSelect.innerHTML = `<option value="">Kein Zimmer</option>${options.join('')}`;
    }
    updateReservationCapacityHint();
}

async function ensureReservationReferenceData(force = false) {
    if (!state.token) {
        populateRoomOptions();
        populateRoomTypeSelects();
        return;
    }

    const loaders = [];
    if (force || state.roomTypes.length === 0) {
        loaders.push(
            apiFetch('room-types')
                .then((roomTypes) => {
                    state.roomTypes = Array.isArray(roomTypes) ? roomTypes : [];
                })
                .catch((error) => {
                    showMessage(error.message, 'error');
                }),
        );
    }
    if (force || state.ratePlans.length === 0) {
        loaders.push(
            apiFetch('rate-plans')
                .then((ratePlans) => {
                    state.ratePlans = Array.isArray(ratePlans) ? ratePlans : [];
                })
                .catch((error) => {
                    showMessage(error.message, 'error');
                }),
        );
    }
    if (force || state.rooms.length === 0) {
        loaders.push(
            apiFetch('rooms')
                .then((rooms) => {
                    state.rooms = Array.isArray(rooms) ? rooms : [];
                })
                .catch((error) => {
                    showMessage(error.message, 'error');
                }),
        );
    }

    if (loaders.length) {
        await Promise.all(loaders);
    }

    populateRoomOptions();
    populateRoomTypeSelects();
    populateRatePlanSelect();
    updateReservationPricing();
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

function formatOccupancyRange(row) {
    const base = Number(row?.base_occupancy ?? 0);
    const max = Number(row?.max_occupancy ?? 0);
    if (base > 0 && max > 0) {
        if (base === max) {
            return `${base} Gäste`;
        }
        return `${base}–${max} Gäste`;
    }
    if (max > 0) {
        return `${max} Gäste`;
    }
    if (base > 0) {
        return `${base} Gäste`;
    }
    return '—';
}

function populateRoomTypeList() {
    renderTable('room-types-list', [
        { key: 'name', label: 'Name' },
        { key: 'occupancy', label: 'Belegung (Basis/Max)', render: formatOccupancyRange },
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

        renderOccupancyCalendar(rooms, reservations, targetDateValue, CALENDAR_DAYS, state.calendarLabelMode, state.calendarCategorySort);
        state.loadedSections.add('dashboard');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}


async function loadReservations(force = false) {
    if (!requireToken()) {
        return;
    }
    await ensureReservationReferenceData(force);
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

async function loadCalendarColors(force = false) {
    if (!requireToken()) {
        return null;
    }
    if (!force && state.calendarColorsLoaded) {
        return state.calendarColors;
    }
    try {
        const response = await apiFetch('settings/calendar-colors');
        const colors = response && typeof response.colors === 'object' ? response.colors : {};
        applyCalendarColors({ ...CALENDAR_COLOR_DEFAULTS, ...colors });
        state.calendarColorsLoaded = true;
        return state.calendarColors;
    } catch (error) {
        showMessage(error.message, 'error');
        return state.calendarColors;
    }
}

async function loadSettings(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.loadedSections.has('settings')) {
        populateCalendarColorInputs();
        updateInvoiceLogoPreview(state.invoiceLogoDataUrl);
        return;
    }
    try {
        const [, logoResponse] = await Promise.all([
            loadCalendarColors(true),
            apiFetch('settings/invoice-logo'),
        ]);
        populateCalendarColorInputs();
        const logoData = logoResponse && typeof logoResponse === 'object' ? logoResponse.logo || null : null;
        updateInvoiceLogoPreview(logoData);
        state.loadedSections.add('settings');
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

async function runSystemUpdate() {
    if (!requireToken() || !runSystemUpdateButton) {
        return;
    }

    const originalText = runSystemUpdateButton.textContent;
    runSystemUpdateButton.disabled = true;
    runSystemUpdateButton.textContent = 'Update läuft…';
    setSystemUpdateMessage('Update wird ausgeführt…', 'info');
    renderSystemUpdateLog([]);

    try {
        const response = await fetch(`../backend/update.php?token=${encodeURIComponent(state.token)}&format=json`, {
            headers: {
                Accept: 'application/json',
            },
        });

        let data = null;
        try {
            data = await response.json();
        } catch (parseError) {
            data = null;
        }

        const logEntries = data && Array.isArray(data.log) ? data.log : [];
        if (logEntries.length > 0) {
            renderSystemUpdateLog(logEntries);
        }

        if (!response.ok || !data || typeof data !== 'object') {
            const message = data && typeof data.message === 'string'
                ? data.message
                : `Update fehlgeschlagen (Status ${response.status})`;
            throw new Error(message);
        }

        if (!data.success) {
            const message = typeof data.message === 'string' && data.message !== ''
                ? data.message
                : 'Update fehlgeschlagen.';
            if (logEntries.length === 0) {
                renderSystemUpdateLog([]);
            }
            throw new Error(message);
        }

        setSystemUpdateMessage(data.message || 'Update erfolgreich abgeschlossen.', 'success');
        if (logEntries.length === 0) {
            renderSystemUpdateLog([]);
        }
        showMessage('System wurde aktualisiert.', 'success');
    } catch (error) {
        const message = error instanceof Error && error.message ? error.message : 'Update fehlgeschlagen.';
        setSystemUpdateMessage(message, 'error');
        if (systemUpdateLog && systemUpdateLog.innerHTML !== '') {
            systemUpdateLog.classList.remove('hidden');
        }
        showMessage(message, 'error');
    } finally {
        runSystemUpdateButton.disabled = false;
        runSystemUpdateButton.textContent = originalText || 'Update starten';
    }
}

async function loadArticles(force = false) {
    if (!requireToken()) {
        return;
    }
    if (!force && state.articlesLoaded) {
        renderArticlesTable();
        renderReservationArticleOptions();
        return;
    }
    try {
        const articles = await apiFetch('articles?include_inactive=1');
        state.articles = Array.isArray(articles) ? articles : [];
        state.articlesLoaded = true;
        renderArticlesTable();
        renderReservationArticleOptions();
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
        await loadArticles(force);
        renderTable('invoices-list', [
            { key: 'invoice_number', label: 'Rechnungsnr.' },
            { key: 'reservation_id', label: 'Reservierung' },
            { key: 'issue_date', label: 'Ausgestellt am', render: (row) => formatDate(row.issue_date) },
            { key: 'due_date', label: 'Fällig am', render: (row) => formatDate(row.due_date) },
            { key: 'status', label: 'Status' },
            { key: 'total_amount', label: 'Summe', render: (row) => formatCurrency(row.total_amount) },
            {
                key: 'actions',
                label: 'Aktionen',
                render: (row) => `<a href="${buildInvoicePdfUrl(row.id)}" target="_blank" rel="noopener">PDF</a>`,
            },
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
    settings: () => loadSettings(true),
};

// Form submissions

if (reservationForm) {
    reservationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const adults = Number(reservationForm.adults.value || 0);
        const children = Number(reservationForm.children.value || 0);
        const totalGuests = adults + children;
        if (totalGuests < 1) {
            showMessage('Bitte mindestens einen Gast angeben.', 'error');
            return;
        }
        const requests = [...state.pendingRoomRequests];
        if (!requests.length) {
            showMessage('Bitte mindestens eine Zimmerkategorie auswählen.', 'error');
            return;
        }
        const capacity = calculateRoomRequestCapacityClient(requests);
        if (capacity <= 0) {
            showMessage('Für die ausgewählten Kategorien ist keine Kapazität hinterlegt.', 'error');
            return;
        }
        if (totalGuests > capacity) {
            showMessage(`Die ausgewählten Kategorien bieten nur Platz für ${capacity} Gäste.`, 'error');
            return;
        }

        const payload = {
            check_in_date: reservationForm.check_in.value,
            check_out_date: reservationForm.check_out.value,
            adults,
            children,
            total_amount: reservationForm.total_amount.value ? Number(reservationForm.total_amount.value) : null,
            currency: reservationForm.currency.value || 'EUR',
            status: reservationForm.status.value,
            booked_via: reservationForm.booked_via.value || null,
        };

        if (reservationRatePlanSelect) {
            payload.rate_plan_id = reservationRatePlanSelect.value ? Number(reservationRatePlanSelect.value) : null;
        }

        if (requests.length) {
            payload.room_requests = requests.map((request) => ({
                room_type_id: Number(request.room_type_id),
                quantity: Number(request.quantity ?? 1),
            }));
        }

        const isEdit = Boolean(state.editingReservationId);

        if (reservationArticleContainer) {
            const selections = Array.from(reservationArticleContainer.querySelectorAll('.article-option')).map((option) => {
                const checkbox = option.querySelector('input[type="checkbox"]');
                if (!checkbox || !checkbox.checked) {
                    return null;
                }
                const articleId = Number(checkbox.value);
                if (Number.isNaN(articleId)) {
                    return null;
                }
                const multiplierInput = option.querySelector('.article-multiplier');
                const multiplierValue = multiplierInput ? Number(multiplierInput.value || 1) : 1;
                return {
                    article_id: articleId,
                    multiplier: Number.isFinite(multiplierValue) ? multiplierValue : 1,
                };
            }).filter(Boolean);
            if (selections.length > 0 || isEdit) {
                payload.articles = selections;
            }
        }

        const selectedGuestId = guestIdInput && guestIdInput.value ? Number(guestIdInput.value) : null;
        const guestPayload = {
            first_name: reservationForm.guest_first.value,
            last_name: reservationForm.guest_last.value,
            email: reservationForm.guest_email.value || null,
            phone: reservationForm.guest_phone.value || null,
        };
        if (reservationGuestCompanySelect) {
            guestPayload.company_id = reservationGuestCompanySelect.value ? Number(reservationGuestCompanySelect.value) : null;
        }

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

if (reservationRatePlanSelect) {
    reservationRatePlanSelect.addEventListener('change', () => {
        reservationPricingOverride = null;
        if (reservationNightlyRateInput) {
            reservationNightlyRateInput.value = '';
        }
        if (reservationTotalAmountInput) {
            reservationTotalAmountInput.value = '';
        }
        updateReservationPricing();
    });
}

if (reservationForm?.check_in) {
    reservationForm.check_in.addEventListener('change', updateReservationPricing);
    reservationForm.check_in.addEventListener('input', updateReservationPricing);
}

if (reservationForm?.check_out) {
    reservationForm.check_out.addEventListener('change', updateReservationPricing);
    reservationForm.check_out.addEventListener('input', updateReservationPricing);
}

if (reservationNightlyRateInput) {
    reservationNightlyRateInput.addEventListener('input', () => {
        if (isSyncingPricingFields) {
            return;
        }
        const hasValue = reservationNightlyRateInput.value.trim() !== '';
        reservationPricingOverride = hasValue ? 'nightly' : null;
        updateReservationPricing();
    });
    reservationNightlyRateInput.addEventListener('change', () => {
        if (!isSyncingPricingFields) {
            updateReservationPricing();
        }
    });
}

if (reservationTotalAmountInput) {
    reservationTotalAmountInput.addEventListener('input', () => {
        if (isSyncingPricingFields) {
            return;
        }
        const hasValue = reservationTotalAmountInput.value.trim() !== '';
        reservationPricingOverride = hasValue ? 'total' : null;
        updateReservationPricing();
    });
    reservationTotalAmountInput.addEventListener('change', () => {
        if (!isSyncingPricingFields) {
            updateReservationPricing();
        }
    });
}

if (roomRequestAddButton) {
    roomRequestAddButton.addEventListener('click', () => {
        const selectedTypeId = roomRequestTypeSelect ? Number(roomRequestTypeSelect.value) : NaN;
        if (!Number.isFinite(selectedTypeId) || selectedTypeId <= 0) {
            showMessage('Bitte eine Zimmerkategorie auswählen.', 'error');
            if (roomRequestTypeSelect) {
                roomRequestTypeSelect.focus();
            }
            return;
        }
        const quantityValue = roomRequestQuantityInput ? Number(roomRequestQuantityInput.value || 1) : 1;
        addReservationRoomRequest(selectedTypeId, quantityValue);
        if (roomRequestQuantityInput) {
            roomRequestQuantityInput.value = '1';
        }
    });
}

if (roomRequestList) {
    roomRequestList.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.dataset.role !== 'room-request-quantity') {
            return;
        }
        const container = target.closest('.room-request-row');
        if (!container) {
            return;
        }
        const typeId = Number(container.dataset.roomTypeId);
        const value = Number(target.value || 1);
        updateReservationRoomRequestQuantity(typeId, value);
    });
    roomRequestList.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLButtonElement)) {
            return;
        }
        if (target.dataset.action === 'remove-room-request') {
            const container = target.closest('.room-request-row');
            if (!container) {
                return;
            }
            const typeId = Number(container.dataset.roomTypeId);
            removeReservationRoomRequest(typeId);
        }
    });
}

if (reservationArticleContainer) {
    reservationArticleContainer.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.type === 'checkbox') {
            const option = target.closest('.article-option');
            if (!option) {
                return;
            }
            const multiplierInput = option.querySelector('.article-multiplier');
            if (multiplierInput instanceof HTMLInputElement) {
                multiplierInput.disabled = !target.checked;
                if (target.checked && (!multiplierInput.value || Number(multiplierInput.value) < 0)) {
                    multiplierInput.value = '1';
                }
            }
        }
    });
}

if (reservationForm) {
    reservationForm.adults.addEventListener('input', updateReservationCapacityHint);
    reservationForm.children.addEventListener('input', updateReservationCapacityHint);
}

if (guestSearchInput) {
    guestSearchInput.addEventListener('input', () => {
        const currentValue = guestSearchInput.value;
        const selectedLabel = guestSearchInput.dataset.selectedLabel || '';
        if (guestSearchInput.dataset.selectedId && currentValue !== selectedLabel) {
            delete guestSearchInput.dataset.selectedId;
            delete guestSearchInput.dataset.selectedLabel;
            if (guestIdInput) {
                guestIdInput.value = '';
            }
            if (guestClearSelectionButton) {
                guestClearSelectionButton.classList.add('hidden');
            }
        }
        scheduleGuestLookup(currentValue);
    });

    guestSearchInput.addEventListener('focus', () => {
        const term = guestSearchInput.value.trim();
        if (term.length >= 2) {
            if (state.guestLookupResults.length === 0 || state.guestLookupTerm !== term) {
                scheduleGuestLookup(term);
            } else {
                renderGuestSuggestions(state.guestLookupResults, term);
            }
        }
    });

    guestSearchInput.addEventListener('blur', () => {
        setTimeout(() => {
            hideGuestSuggestions();
        }, 150);
    });
}

if (guestClearSelectionButton) {
    guestClearSelectionButton.addEventListener('click', () => {
        clearGuestSelection();
    });
}

if (guestSearchResults) {
    guestSearchResults.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });
    guestSearchResults.addEventListener('click', (event) => {
        const target = event.target.closest('button[data-guest-id]');
        if (!target) {
            return;
        }
        const guestId = Number(target.dataset.guestId);
        if (!Number.isFinite(guestId)) {
            return;
        }
        const guest = state.guestLookupResults.find((entry) => Number(entry.id) === guestId)
            || state.guests.find((entry) => Number(entry.id) === guestId)
            || null;
        if (guest) {
            setGuestSelection(guest);
        } else if (guestIdInput) {
            guestIdInput.value = String(guestId);
        }
        hideGuestSuggestions();
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
if (settingsReloadButton) {
    settingsReloadButton.addEventListener('click', () => loadSettings(true));
}
if (runSystemUpdateButton) {
    runSystemUpdateButton.addEventListener('click', () => runSystemUpdate());
}
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
        await loadRooms(true);
        await ensureReservationReferenceData(true);
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
    const baseOccupancyValue = Number(form.base_occupancy.value);
    const maxOccupancyValue = Number(form.max_occupancy.value);
    if (!Number.isFinite(baseOccupancyValue) || baseOccupancyValue <= 0) {
        showMessage('Basisbelegung muss größer als 0 sein.', 'error');
        return;
    }
    if (!Number.isFinite(maxOccupancyValue) || maxOccupancyValue <= 0) {
        showMessage('Maximalbelegung muss größer als 0 sein.', 'error');
        return;
    }
    const baseOccupancy = Math.max(1, Math.round(baseOccupancyValue));
    const maxOccupancy = Math.max(1, Math.round(maxOccupancyValue));
    if (baseOccupancy > maxOccupancy) {
        showMessage('Basisbelegung darf die Maximalbelegung nicht überschreiten.', 'error');
        return;
    }
    const payload = {
        name: form.name.value,
        base_occupancy: baseOccupancy,
        max_occupancy: maxOccupancy,
        description: form.description.value || null,
    };
    try {
        await apiFetch('room-types', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        form.reset();
        form.base_occupancy.value = '1';
        form.max_occupancy.value = '1';
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
        populateRatePlanList();
        await ensureReservationReferenceData(true);
    } catch (error) {
        showMessage(error.message, 'error');
    }
});

if (articleForm) {
    articleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const form = event.target;
        const payload = {
            name: form.name.value,
            description: form.description.value || null,
            charge_scheme: form.charge_scheme.value,
            unit_price: form.unit_price.value ? Number(form.unit_price.value) : 0,
            tax_rate: form.tax_rate.value ? Number(form.tax_rate.value) : 0,
            is_active: form.is_active ? form.is_active.checked : true,
        };
        const isEdit = Boolean(state.editingArticleId);
        const endpoint = isEdit ? `articles/${state.editingArticleId}` : 'articles';
        const method = isEdit ? 'PATCH' : 'POST';
        try {
            await apiFetch(endpoint, {
                method,
                body: JSON.stringify(payload),
            });
            showMessage(isEdit ? 'Artikel aktualisiert.' : 'Artikel angelegt.', 'success');
            resetArticleForm();
            await loadArticles(true);
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (articleCancelButton) {
    articleCancelButton.addEventListener('click', () => {
        resetArticleForm();
    });
}

if (reloadArticlesButton) {
    reloadArticlesButton.addEventListener('click', () => loadArticles(true));
}

if (articlesList) {
    articlesList.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const action = target.dataset.action;
        const id = Number(target.dataset.id);
        if (!action || Number.isNaN(id)) {
            return;
        }
        if (action === 'edit-article') {
            startArticleEdit(id);
            return;
        }
        if (!requireToken()) {
            return;
        }
        try {
            if (action === 'deactivate-article') {
                await apiFetch(`articles/${id}`, { method: 'DELETE' });
                showMessage('Artikel deaktiviert.', 'success');
            } else if (action === 'activate-article') {
                await apiFetch(`articles/${id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ is_active: true }),
                });
                showMessage('Artikel aktiviert.', 'success');
            }
            await loadArticles(true);
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (invoiceLogoInput) {
    invoiceLogoInput.addEventListener('change', async () => {
        if (!invoiceLogoInput.files || invoiceLogoInput.files.length === 0) {
            updateInvoiceLogoPreview(state.invoiceLogoDataUrl);
            return;
        }
        const file = invoiceLogoInput.files[0];
        try {
            const dataUrl = await readFileAsDataUrl(file);
            updateInvoiceLogoPreview(dataUrl);
        } catch (error) {
            showMessage(error.message || 'Logo konnte nicht gelesen werden.', 'error');
            updateInvoiceLogoPreview(state.invoiceLogoDataUrl);
        }
    });
}

if (invoiceLogoForm) {
    invoiceLogoForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        let dataUrl = state.invoiceLogoDataUrl;
        if (invoiceLogoInput && invoiceLogoInput.files && invoiceLogoInput.files.length > 0) {
            try {
                dataUrl = await readFileAsDataUrl(invoiceLogoInput.files[0]);
            } catch (error) {
                showMessage(error.message || 'Logo konnte nicht gelesen werden.', 'error');
                return;
            }
        }
        if (!dataUrl) {
            showMessage('Bitte wählen Sie ein Logo aus.', 'error');
            return;
        }
        try {
            const response = await apiFetch('settings/invoice-logo', {
                method: 'PUT',
                body: JSON.stringify({ image: dataUrl }),
            });
            const logoData = response && typeof response === 'object' ? response.logo || dataUrl : dataUrl;
            updateInvoiceLogoPreview(logoData);
            if (invoiceLogoInput) {
                invoiceLogoInput.value = '';
            }
            showMessage('Rechnungslogo gespeichert.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (removeInvoiceLogoButton) {
    removeInvoiceLogoButton.addEventListener('click', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        try {
            await apiFetch('settings/invoice-logo', { method: 'DELETE' });
            updateInvoiceLogoPreview(null);
            if (invoiceLogoInput) {
                invoiceLogoInput.value = '';
            }
            showMessage('Rechnungslogo entfernt.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

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
        const statusButton = event.target.closest('.reservation-status');
        if (statusButton) {
            const reservationId = Number(statusButton.dataset.id);
            const nextStatus = statusButton.dataset.status;
            if (Number.isFinite(reservationId) && nextStatus) {
                updateReservationStatus(reservationId, nextStatus);
            }
            return;
        }
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
            state.calendarCategorySort,
        );
    });
}

if (calendarCategorySortSelect) {
    calendarCategorySortSelect.value = state.calendarCategorySort;
    calendarCategorySortSelect.addEventListener('change', () => {
        const allowedSorts = ['name_asc', 'name_desc', 'room_count_asc', 'room_count_desc'];
        const selected = calendarCategorySortSelect.value;
        const nextSort = allowedSorts.includes(selected) ? selected : 'name_asc';
        state.calendarCategorySort = nextSort;
        try {
            localStorage.setItem(CALENDAR_CATEGORY_SORT_KEY, nextSort);
        } catch (error) {
            // ignore storage failures
        }
        renderOccupancyCalendar(
            state.rooms,
            state.reservations,
            dashboardDateInput && dashboardDateInput.value ? dashboardDateInput.value : toLocalISODate(),
            CALENDAR_DAYS,
            state.calendarLabelMode,
            nextSort,
        );
    });
}

if (calendarColorForm) {
    calendarColorForm.addEventListener('input', (event) => {
        const input = event.target.closest('input[type="color"][data-status]');
        if (!input) {
            return;
        }
        const status = input.dataset.status;
        const normalized = normalizeHexColorInput(input.value);
        if (status && normalized) {
            state.calendarColors[status] = normalized;
            applyCalendarColors(state.calendarColors);
        }
    });

    calendarColorForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        const payload = {};
        CALENDAR_STATUS_ORDER.forEach((status) => {
            const input = calendarColorForm.querySelector(`input[data-status="${status}"]`);
            const normalized = normalizeHexColorInput(input ? input.value : null) || CALENDAR_COLOR_DEFAULTS[status];
            payload[status] = normalized;
        });
        try {
            const response = await apiFetch('settings/calendar-colors', {
                method: 'PUT',
                body: JSON.stringify({ colors: payload }),
            });
            const colors = response && typeof response.colors === 'object' ? response.colors : payload;
            applyCalendarColors(colors);
            populateCalendarColorInputs();
            state.calendarColorsLoaded = true;
            showMessage('Kalenderfarben gespeichert.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });
}

if (resetCalendarColorsButton) {
    resetCalendarColorsButton.addEventListener('click', async (event) => {
        event.preventDefault();
        if (!requireToken()) {
            return;
        }
        try {
            const response = await apiFetch('settings/calendar-colors', { method: 'DELETE' });
            const colors = response && typeof response.colors === 'object' ? response.colors : CALENDAR_COLOR_DEFAULTS;
            applyCalendarColors(colors);
            populateCalendarColorInputs();
            state.calendarColorsLoaded = true;
            showMessage('Kalenderfarben zurückgesetzt.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        }
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
