/**
 * Utility functions for date handling, formatting, and validation
 * @module utils
 */

/**
 * Converts a Date object to a local ISO date string (YYYY-MM-DD)
 * @param {Date} date - The date to convert
 * @returns {string} ISO date string or empty string if invalid
 */
export function toLocalISODate(date = new Date()) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    return local.toISOString().slice(0, 10);
}

/**
 * Parses an ISO date string (YYYY-MM-DD) into a Date object
 * @param {string} value - ISO date string
 * @returns {Date|null} Parsed date or null if invalid
 */
export function parseISODate(value) {
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

/**
 * Adds a specified number of days to a date
 * @param {Date} date - The base date
 * @param {number} amount - Number of days to add (can be negative)
 * @returns {Date} New date object
 */
export function addDays(date, amount) {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    return result;
}

/**
 * Converts a date to a string key for use in collections
 * @param {Date} date - The date to convert
 * @returns {string} Date key string
 */
export function dateKey(date) {
    return toLocalISODate(date);
}

/**
 * Checks if a date falls on a weekend (Saturday or Sunday)
 * @param {Date} date - The date to check
 * @returns {boolean} True if weekend, false otherwise
 */
export function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
}

/**
 * Normalizes a status value to a valid CSS class name
 * @param {string} value - The status value to normalize
 * @returns {string} Normalized class name
 */
export function normalizeStatusClass(value) {
    if (!value) {
        return '';
    }
    return value.toString().toLowerCase().replace(/[^a-z0-9_-]/g, '');
}

/**
 * Formats a date value to German locale date string
 * @param {string|Date} value - Date to format
 * @returns {string} Formatted date string
 */
export function formatDate(value) {
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

/**
 * Formats a datetime value to German locale datetime string
 * @param {string|Date} value - DateTime to format
 * @returns {string} Formatted datetime string
 */
export function formatDateTime(value) {
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

/**
 * Formats a currency amount to German locale
 * @param {number|string} amount - Amount to format
 * @param {string} currency - Currency code (default: 'EUR')
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount, currency = 'EUR') {
    if (amount === null || amount === undefined || amount === '') {
        return '';
    }
    const number = Number(amount);
    if (Number.isNaN(number)) {
        return amount;
    }
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(number);
}

/**
 * Escapes HTML special characters to prevent XSS
 * @param {string} value - String to escape
 * @returns {string} HTML-escaped string
 */
export function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Converts a datetime value to SQL datetime format
 * @param {string} value - DateTime value
 * @returns {string|null} SQL datetime string or null
 */
export function toSqlDateTime(value) {
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

/**
 * Calculates the number of nights between two dates
 * @param {string} checkInValue - Check-in date (ISO format)
 * @param {string} checkOutValue - Check-out date (ISO format)
 * @returns {number} Number of nights (0 if invalid)
 */
export function calculateNightsBetween(checkInValue, checkOutValue) {
    const checkIn = parseISODate(checkInValue);
    const checkOut = parseISODate(checkOutValue);
    if (!checkIn || !checkOut || checkOut <= checkIn) {
        return 0;
    }
    const diffMs = checkOut.getTime() - checkIn.getTime();
    return Math.max(0, Math.floor(diffMs / 86400000));
}

/**
 * Normalizes a hex color input to standard format (#rrggbb)
 * @param {string} value - Color value to normalize
 * @returns {string|null} Normalized hex color or null if invalid
 */
export function normalizeHexColorInput(value) {
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

/**
 * Converts hex color to RGB object
 * @param {string} color - Hex color value
 * @returns {{r: number, g: number, b: number}|null} RGB object or null if invalid
 */
export function hexToRgb(color) {
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

/**
 * Converts hex color to RGBA string
 * @param {string} color - Hex color value
 * @param {number} alpha - Alpha value (0-1, default: 0.55)
 * @returns {string|null} RGBA string or null if invalid
 */
export function rgbaFromHex(color, alpha = 0.55) {
    const rgb = hexToRgb(color);
    if (!rgb) {
        return null;
    }
    const safeAlpha = Math.max(0, Math.min(1, alpha));
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${safeAlpha})`;
}

/**
 * Determines readable text color (black or white) for a given background color
 * @param {string} color - Hex color value
 * @returns {string} Hex color for text (#111827 or #ffffff)
 */
export function getReadableTextColor(color) {
    const rgb = hexToRgb(color);
    if (!rgb) {
        return '#ffffff';
    }
    const luminance = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
    return luminance > 0.6 ? '#111827' : '#ffffff';
}
