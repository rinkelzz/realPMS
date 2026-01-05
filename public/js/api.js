/**
 * API communication layer
 * Handles all HTTP requests to the backend API
 * @module api
 */

import { state, API_BASE } from './state.js';

/**
 * Checks if API token is set and shows error if not
 * @returns {boolean} True if token is set, false otherwise
 */
export function requireToken() {
    if (!state.token) {
        // Note: showMessage needs to be imported from ui.js when needed
        // For now, we'll use console.error
        console.error('Bitte speichern Sie einen gültigen API-Token, um Daten laden zu können.');
        return false;
    }
    return true;
}

/**
 * Makes an authenticated API request
 * @param {string} path - API endpoint path
 * @param {Object} options - Fetch options
 * @param {boolean} options.skipAuth - Skip authentication (default: false)
 * @returns {Promise<Object|null>} Response data or null
 * @throws {Error} If request fails
 */
export async function apiFetch(path, options = {}) {
    const { skipAuth = false } = options;
    const normalizedPath = path ? path.replace(/^\/+/, '') : '';
    const baseUrl = normalizedPath ? `${API_BASE}/${normalizedPath}` : API_BASE;
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

    let response = await fetch(baseUrl, { ...options, headers });

    if (
        response.status === 401 &&
        !skipAuth &&
        state.token
    ) {
        const separator = baseUrl.includes('?') ? '&' : '?';
        const fallbackUrl = `${baseUrl}${separator}token=${encodeURIComponent(state.token)}`;
        response = await fetch(fallbackUrl, { ...options, headers });
    }
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

/**
 * Fetches dashboard data
 * @param {boolean} force - Force reload data
 * @returns {Promise<Object>} Dashboard data
 */
export async function loadDashboard(force = false) {
    const data = await apiFetch('reports/occupancy?start=2024-01-01&end=2024-12-31');
    return data;
}

/**
 * Fetches reservations from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Array>} Array of reservations
 */
export async function loadReservations(force = false) {
    const data = await apiFetch('reservations');
    return data?.data || [];
}

/**
 * Fetches calendar colors from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Object>} Calendar colors object
 */
export async function loadCalendarColors(force = false) {
    const data = await apiFetch('settings/calendar-colors');
    return data?.colors || null;
}

/**
 * Fetches rooms from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Array>} Array of rooms
 */
export async function loadRooms(force = false) {
    const data = await apiFetch('rooms');
    return data?.data || [];
}

/**
 * Fetches articles from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Array>} Array of articles
 */
export async function loadArticles(force = false) {
    const data = await apiFetch('articles');
    return data?.data || [];
}

/**
 * Fetches guests from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Array>} Array of guests
 */
export async function loadGuests(force = false) {
    const data = await apiFetch('guests');
    return data?.data || [];
}

/**
 * Fetches companies from API
 * @param {boolean} force - Force reload
 * @returns {Promise<Array>} Array of companies
 */
export async function loadCompanies(force = false) {
    const data = await apiFetch('companies');
    return data?.data || [];
}

/**
 * Updates reservation status
 * @param {number} reservationId - Reservation ID
 * @param {string} status - New status
 * @returns {Promise<Object>} Updated reservation
 */
export async function updateReservationStatus(reservationId, status) {
    return await apiFetch(`reservations/${reservationId}/status`, {
        method: 'POST',
        body: JSON.stringify({ status }),
    });
}

/**
 * Searches for guests by term
 * @param {string} term - Search term
 * @param {number} limit - Maximum results
 * @returns {Promise<Array>} Array of matching guests
 */
export async function searchGuests(term, limit = 10) {
    const data = await apiFetch(`guests?search=${encodeURIComponent(term)}&limit=${limit}`);
    return data?.data || [];
}
