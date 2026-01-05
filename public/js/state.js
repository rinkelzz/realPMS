/**
 * Application state management and constants
 * @module state
 */

// API Configuration
export const API_BASE = '../backend/api/index.php';

// Calendar Constants
export const CALENDAR_STATUS_ORDER = ['tentative', 'confirmed', 'checked_in', 'paid', 'checked_out', 'cancelled', 'no_show'];
export const CALENDAR_DAYS = 14;
export const CALENDAR_LABEL_KEY = 'realpms_calendar_label_mode';
export const CALENDAR_CATEGORY_SORT_KEY = 'realpms_calendar_category_sort';

export const CALENDAR_COLOR_DEFAULTS = {
    tentative: '#f97316',
    confirmed: '#2563eb',
    checked_in: '#16a34a',
    paid: '#0ea5e9',
    checked_out: '#6b7280',
    cancelled: '#ef4444',
    no_show: '#7c3aed',
};

// Article/Billing Constants
export const ARTICLE_SCHEME_LABELS = {
    per_person_per_day: 'pro Person & Tag',
    per_room_per_day: 'pro Zimmer & Tag',
    per_stay: 'pro Aufenthalt',
    per_person: 'pro Person',
    per_day: 'pro Tag',
};

// Reservation Status Constants
export const RESERVATION_STATUS_ACTIONS = [
    { status: 'checked_in', label: 'Check-in', title: 'Gast als angereist markieren' },
    { status: 'paid', label: 'Bezahlt', title: 'Zahlung als erhalten markieren' },
    { status: 'checked_out', label: 'Check-out', title: 'Gast als abgereist markieren' },
    { status: 'no_show', label: 'No-Show', title: 'Gast als No-Show markieren' },
];

export const RESERVATION_STATUS_LABELS = {
    tentative: 'Voranfrage',
    confirmed: 'Bestätigt',
    checked_in: 'Angereist',
    paid: 'Bezahlt',
    checked_out: 'Abgereist',
    cancelled: 'Storniert',
    no_show: 'Nicht erschienen',
};

export const INVOICE_STATUS_LABELS = {
    draft: 'Entwurf',
    issued: 'Offen',
    paid: 'Bezahlt',
    void: 'Storniert',
};

/**
 * Application state object
 * Manages global application state including tokens, entities, and UI state
 */
export const state = {
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

// Initialize state from localStorage
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

/**
 * Gets a room type by ID
 * @param {number} roomTypeId - Room type ID
 * @returns {Object|undefined} Room type object or undefined
 */
export function getRoomTypeById(roomTypeId) {
    return state.roomTypes.find((rt) => rt.id === Number(roomTypeId));
}

/**
 * Gets a rate plan by ID
 * @param {number} ratePlanId - Rate plan ID
 * @returns {Object|undefined} Rate plan object or undefined
 */
export function getRatePlanById(ratePlanId) {
    return state.ratePlans.find((rp) => rp.id === Number(ratePlanId));
}
