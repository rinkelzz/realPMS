/**
 * Unit tests for utils module
 */
import { describe, it, expect } from '@jest/globals';
import {
    toLocalISODate,
    parseISODate,
    addDays,
    dateKey,
    isWeekend,
    normalizeStatusClass,
    formatDate,
    formatDateTime,
    formatCurrency,
    escapeHtml,
    toSqlDateTime,
    calculateNightsBetween,
    normalizeHexColorInput,
    hexToRgb,
    rgbaFromHex,
    getReadableTextColor
} from '../utils.js';

describe('Date Utilities', () => {
    describe('toLocalISODate', () => {
        it('should convert Date to ISO date string', () => {
            const date = new Date('2024-01-15T12:00:00Z');
            const result = toLocalISODate(date);
            expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        });

        it('should return empty string for invalid date', () => {
            expect(toLocalISODate(new Date('invalid'))).toBe('');
        });

        it('should handle default parameter (current date)', () => {
            const result = toLocalISODate();
            expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        });
    });

    describe('parseISODate', () => {
        it('should parse valid ISO date string', () => {
            const result = parseISODate('2024-01-15');
            expect(result).toBeInstanceOf(Date);
            expect(result.getFullYear()).toBe(2024);
            expect(result.getMonth()).toBe(0); // January (0-indexed)
            expect(result.getDate()).toBe(15);
        });

        it('should return null for invalid format', () => {
            expect(parseISODate('15-01-2024')).toBeNull();
            expect(parseISODate('2024/01/15')).toBeNull();
            expect(parseISODate('invalid')).toBeNull();
        });

        it('should return null for non-string input', () => {
            expect(parseISODate(null)).toBeNull();
            expect(parseISODate(undefined)).toBeNull();
            expect(parseISODate(123)).toBeNull();
        });
    });

    describe('addDays', () => {
        it('should add positive days', () => {
            const date = new Date('2024-01-15');
            const result = addDays(date, 5);
            expect(result.getDate()).toBe(20);
        });

        it('should subtract days with negative amount', () => {
            const date = new Date('2024-01-15');
            const result = addDays(date, -5);
            expect(result.getDate()).toBe(10);
        });

        it('should not modify original date', () => {
            const date = new Date('2024-01-15');
            const original = date.getDate();
            addDays(date, 5);
            expect(date.getDate()).toBe(original);
        });
    });

    describe('dateKey', () => {
        it('should return ISO date string for date', () => {
            const date = new Date('2024-01-15T12:00:00');
            const result = dateKey(date);
            expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        });
    });

    describe('isWeekend', () => {
        it('should return true for Saturday', () => {
            const saturday = new Date('2024-01-13'); // Known Saturday
            expect(isWeekend(saturday)).toBe(true);
        });

        it('should return true for Sunday', () => {
            const sunday = new Date('2024-01-14'); // Known Sunday
            expect(isWeekend(sunday)).toBe(true);
        });

        it('should return false for weekday', () => {
            const monday = new Date('2024-01-15'); // Known Monday
            expect(isWeekend(monday)).toBe(false);
        });
    });

    describe('calculateNightsBetween', () => {
        it('should calculate nights between two dates', () => {
            expect(calculateNightsBetween('2024-01-10', '2024-01-15')).toBe(5);
        });

        it('should return 0 for same dates', () => {
            expect(calculateNightsBetween('2024-01-10', '2024-01-10')).toBe(0);
        });

        it('should return 0 for check-out before check-in', () => {
            expect(calculateNightsBetween('2024-01-15', '2024-01-10')).toBe(0);
        });

        it('should return 0 for invalid dates', () => {
            expect(calculateNightsBetween('invalid', '2024-01-15')).toBe(0);
            expect(calculateNightsBetween('2024-01-10', 'invalid')).toBe(0);
        });
    });
});

describe('Formatting Utilities', () => {
    describe('formatDate', () => {
        it('should format ISO date string to German locale', () => {
            const result = formatDate('2024-01-15');
            expect(result).toMatch(/15\.\s*1\.\s*2024/);
        });

        it('should handle Date objects', () => {
            const date = new Date('2024-01-15');
            const result = formatDate(date);
            expect(result).toBeTruthy();
        });

        it('should return empty string for empty input', () => {
            expect(formatDate('')).toBe('');
            expect(formatDate(null)).toBe('');
        });
    });

    describe('formatDateTime', () => {
        it('should format ISO datetime to German locale', () => {
            const result = formatDateTime('2024-01-15 10:30:00');
            expect(result).toBeTruthy();
            expect(result).toContain('15');
        });

        it('should handle T separator', () => {
            const result = formatDateTime('2024-01-15T10:30');
            expect(result).toBeTruthy();
        });

        it('should return empty string for empty input', () => {
            expect(formatDateTime('')).toBe('');
            expect(formatDateTime(null)).toBe('');
        });
    });

    describe('formatCurrency', () => {
        it('should format number as EUR currency', () => {
            const result = formatCurrency(1234.56);
            expect(result).toContain('1.234,56');
            expect(result).toContain('€');
        });

        it('should handle different currencies', () => {
            const result = formatCurrency(100, 'USD');
            expect(result).toBeTruthy();
        });

        it('should return empty string for null/undefined', () => {
            expect(formatCurrency(null)).toBe('');
            expect(formatCurrency(undefined)).toBe('');
            expect(formatCurrency('')).toBe('');
        });

        it('should handle string numbers', () => {
            const result = formatCurrency('1234.56');
            expect(result).toContain('1.234,56');
        });
    });

    describe('escapeHtml', () => {
        it('should escape HTML special characters', () => {
            expect(escapeHtml('<script>')).toBe('&lt;script&gt;');
            expect(escapeHtml('&')).toBe('&amp;');
            expect(escapeHtml('"double"')).toContain('&quot;');
            expect(escapeHtml("'single'")).toContain('&#039;');
        });

        it('should handle empty string', () => {
            expect(escapeHtml('')).toBe('');
        });

        it('should convert non-string to string', () => {
            expect(escapeHtml(123)).toBe('123');
        });
    });
});

describe('Status Utilities', () => {
    describe('normalizeStatusClass', () => {
        it('should normalize status to lowercase', () => {
            expect(normalizeStatusClass('CONFIRMED')).toBe('confirmed');
        });

        it('should remove invalid characters', () => {
            expect(normalizeStatusClass('status with spaces')).toBe('statuswithspaces');
            expect(normalizeStatusClass('status@#$%')).toBe('status');
        });

        it('should keep valid characters', () => {
            expect(normalizeStatusClass('status-123_test')).toBe('status-123_test');
        });

        it('should return empty for null/undefined', () => {
            expect(normalizeStatusClass(null)).toBe('');
            expect(normalizeStatusClass(undefined)).toBe('');
        });
    });

    describe('toSqlDateTime', () => {
        it('should convert ISO datetime with T to SQL format', () => {
            expect(toSqlDateTime('2024-01-15T10:30:00')).toBe('2024-01-15 10:30:00');
        });

        it('should add seconds if missing', () => {
            expect(toSqlDateTime('2024-01-15T10:30')).toBe('2024-01-15 10:30:00');
        });

        it('should return as-is for already SQL format', () => {
            expect(toSqlDateTime('2024-01-15 10:30:00')).toBe('2024-01-15 10:30:00');
        });

        it('should return null for empty input', () => {
            expect(toSqlDateTime('')).toBeNull();
            expect(toSqlDateTime(null)).toBeNull();
        });
    });
});

describe('Color Utilities', () => {
    describe('normalizeHexColorInput', () => {
        it('should normalize 6-digit hex colors', () => {
            expect(normalizeHexColorInput('#FF5733')).toBe('#ff5733');
            expect(normalizeHexColorInput('FF5733')).toBe('#ff5733');
        });

        it('should expand 3-digit hex colors', () => {
            expect(normalizeHexColorInput('#F53')).toBe('#ff5533');
            expect(normalizeHexColorInput('F53')).toBe('#ff5533');
        });

        it('should return null for invalid colors', () => {
            expect(normalizeHexColorInput('invalid')).toBeNull();
            expect(normalizeHexColorInput('#GG5533')).toBeNull();
            expect(normalizeHexColorInput('')).toBeNull();
        });
    });

    describe('hexToRgb', () => {
        it('should convert hex to RGB object', () => {
            const result = hexToRgb('#FF5733');
            expect(result).toEqual({ r: 255, g: 87, b: 51 });
        });

        it('should handle 3-digit hex', () => {
            const result = hexToRgb('#F53');
            expect(result).toEqual({ r: 255, g: 85, b: 51 });
        });

        it('should return null for invalid hex', () => {
            expect(hexToRgb('invalid')).toBeNull();
            expect(hexToRgb('')).toBeNull();
        });
    });

    describe('rgbaFromHex', () => {
        it('should convert hex to RGBA string', () => {
            const result = rgbaFromHex('#FF5733', 0.5);
            expect(result).toBe('rgba(255, 87, 51, 0.5)');
        });

        it('should use default alpha', () => {
            const result = rgbaFromHex('#FF5733');
            expect(result).toBe('rgba(255, 87, 51, 0.55)');
        });

        it('should clamp alpha to 0-1 range', () => {
            const result1 = rgbaFromHex('#FF5733', 1.5);
            expect(result1).toBe('rgba(255, 87, 51, 1)');
            
            const result2 = rgbaFromHex('#FF5733', -0.5);
            expect(result2).toBe('rgba(255, 87, 51, 0)');
        });

        it('should return null for invalid hex', () => {
            expect(rgbaFromHex('invalid')).toBeNull();
        });
    });

    describe('getReadableTextColor', () => {
        it('should return dark text for light backgrounds', () => {
            expect(getReadableTextColor('#FFFFFF')).toBe('#111827');
            expect(getReadableTextColor('#F0F0F0')).toBe('#111827');
        });

        it('should return light text for dark backgrounds', () => {
            expect(getReadableTextColor('#000000')).toBe('#ffffff');
            expect(getReadableTextColor('#111111')).toBe('#ffffff');
        });

        it('should return white for invalid color', () => {
            expect(getReadableTextColor('invalid')).toBe('#ffffff');
        });
    });
});
