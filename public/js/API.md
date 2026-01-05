# API Dokumentation - realPMS Frontend Module

## Inhaltsverzeichnis
- [utils.js - Utility-Funktionen](#utilsjs)
- [state.js - Zustandsverwaltung](#statejs)
- [api.js - API-Kommunikation](#apijs)

---

## utils.js

Allgemeine Hilfsfunktionen für Datums-, Format- und Validierungsoperationen.

### Datums-Funktionen

#### `toLocalISODate(date)`
Konvertiert ein Date-Objekt in einen lokalen ISO-Datumsstring (YYYY-MM-DD).

**Parameter:**
- `date` (Date, optional) - Date-Objekt, Standard: aktuelles Datum

**Rückgabe:** String im Format "YYYY-MM-DD" oder leerer String bei ungültigem Datum

**Beispiel:**
```javascript
import { toLocalISODate } from './utils.js';

const today = toLocalISODate();
// → "2024-01-15"

const specificDate = toLocalISODate(new Date('2024-12-25'));
// → "2024-12-25"
```

#### `parseISODate(value)`
Parst einen ISO-Datumsstring (YYYY-MM-DD) in ein Date-Objekt.

**Parameter:**
- `value` (string) - ISO-Datumsstring

**Rückgabe:** Date-Objekt oder null bei ungültigem Format

**Beispiel:**
```javascript
import { parseISODate } from './utils.js';

const date = parseISODate('2024-01-15');
// → Date object (2024-01-15)

parseISODate('invalid');
// → null
```

#### `addDays(date, amount)`
Fügt eine bestimmte Anzahl von Tagen zu einem Datum hinzu (kann auch negativ sein).

**Parameter:**
- `date` (Date) - Ausgangsdatum
- `amount` (number) - Anzahl der Tage (positiv oder negativ)

**Rückgabe:** Neues Date-Objekt (Original wird nicht verändert)

**Beispiel:**
```javascript
import { addDays } from './utils.js';

const date = new Date('2024-01-15');
const future = addDays(date, 7);
// → Date object (2024-01-22)

const past = addDays(date, -3);
// → Date object (2024-01-12)
```

#### `isWeekend(date)`
Prüft, ob ein Datum auf ein Wochenende fällt.

**Parameter:**
- `date` (Date) - Zu prüfendes Datum

**Rückgabe:** Boolean (true für Samstag/Sonntag)

**Beispiel:**
```javascript
import { isWeekend } from './utils.js';

isWeekend(new Date('2024-01-13')); // Samstag
// → true

isWeekend(new Date('2024-01-15')); // Montag
// → false
```

#### `calculateNightsBetween(checkInValue, checkOutValue)`
Berechnet die Anzahl der Nächte zwischen zwei Daten.

**Parameter:**
- `checkInValue` (string) - Check-in Datum (YYYY-MM-DD)
- `checkOutValue` (string) - Check-out Datum (YYYY-MM-DD)

**Rückgabe:** Number (Anzahl der Nächte, 0 bei ungültigen Daten)

**Beispiel:**
```javascript
import { calculateNightsBetween } from './utils.js';

calculateNightsBetween('2024-01-10', '2024-01-15');
// → 5
```

### Format-Funktionen

#### `formatDate(value)`
Formatiert ein Datum im deutschen Format (TT.MM.JJJJ).

**Parameter:**
- `value` (string|Date) - Zu formatierendes Datum

**Rückgabe:** String im Format "TT.MM.JJJJ"

**Beispiel:**
```javascript
import { formatDate } from './utils.js';

formatDate('2024-01-15');
// → "15.1.2024"
```

#### `formatDateTime(value)`
Formatiert ein DateTime im deutschen Format (TT.MM.JJJJ, HH:MM:SS).

**Parameter:**
- `value` (string|Date) - Zu formatierendes DateTime

**Rückgabe:** String im Format "TT.MM.JJJJ, HH:MM:SS"

**Beispiel:**
```javascript
import { formatDateTime } from './utils.js';

formatDateTime('2024-01-15T10:30:00');
// → "15.1.2024, 10:30:00"
```

#### `formatCurrency(amount, currency)`
Formatiert einen Geldbetrag im deutschen Format.

**Parameter:**
- `amount` (number|string) - Geldbetrag
- `currency` (string, optional) - Währungscode, Standard: 'EUR'

**Rückgabe:** String mit formatiertem Betrag und Währungssymbol

**Beispiel:**
```javascript
import { formatCurrency } from './utils.js';

formatCurrency(1234.56);
// → "1.234,56 €"

formatCurrency(100, 'USD');
// → "$100.00"
```

### Sicherheits-Funktionen

#### `escapeHtml(value)`
Schützt vor XSS-Angriffen durch Escaping von HTML-Sonderzeichen.

**Parameter:**
- `value` (string) - Zu escapender String

**Rückgabe:** String mit escaped Sonderzeichen

**Beispiel:**
```javascript
import { escapeHtml } from './utils.js';

escapeHtml('<script>alert("XSS")</script>');
// → "&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;"
```

### Validierungs-Funktionen

#### `normalizeStatusClass(value)`
Normalisiert einen Status-Wert zu einem gültigen CSS-Klassennamen.

**Parameter:**
- `value` (string) - Status-Wert

**Rückgabe:** String (lowercase, nur a-z, 0-9, _, -)

**Beispiel:**
```javascript
import { normalizeStatusClass } from './utils.js';

normalizeStatusClass('CHECKED IN');
// → "checkedin"
```

### Farb-Funktionen

#### `normalizeHexColorInput(value)`
Normalisiert eine Hex-Farbe zum Standard-Format (#rrggbb).

**Parameter:**
- `value` (string) - Farbe (mit oder ohne #, 3 oder 6 Zeichen)

**Rückgabe:** String im Format "#rrggbb" oder null bei ungültig

**Beispiel:**
```javascript
import { normalizeHexColorInput } from './utils.js';

normalizeHexColorInput('FF5733');
// → "#ff5733"

normalizeHexColorInput('#F53');
// → "#ff5533"
```

#### `hexToRgb(color)`
Konvertiert eine Hex-Farbe in ein RGB-Objekt.

**Parameter:**
- `color` (string) - Hex-Farbe

**Rückgabe:** Object {r, g, b} oder null bei ungültig

**Beispiel:**
```javascript
import { hexToRgb } from './utils.js';

hexToRgb('#FF5733');
// → { r: 255, g: 87, b: 51 }
```

#### `rgbaFromHex(color, alpha)`
Konvertiert eine Hex-Farbe in einen RGBA-String.

**Parameter:**
- `color` (string) - Hex-Farbe
- `alpha` (number, optional) - Alpha-Wert 0-1, Standard: 0.55

**Rückgabe:** String im Format "rgba(r, g, b, a)" oder null

**Beispiel:**
```javascript
import { rgbaFromHex } from './utils.js';

rgbaFromHex('#FF5733', 0.5);
// → "rgba(255, 87, 51, 0.5)"
```

#### `getReadableTextColor(color)`
Bestimmt eine gut lesbare Textfarbe (hell/dunkel) für eine Hintergrundfarbe.

**Parameter:**
- `color` (string) - Hex-Hintergrundfarbe

**Rückgabe:** String "#111827" (dunkel) oder "#ffffff" (hell)

**Beispiel:**
```javascript
import { getReadableTextColor } from './utils.js';

getReadableTextColor('#FFFFFF');
// → "#111827" (dunkler Text für hellen Hintergrund)

getReadableTextColor('#000000');
// → "#ffffff" (heller Text für dunklen Hintergrund)
```

---

## state.js

Zentrale Zustandsverwaltung und Konstanten für die Anwendung.

### Konstanten

#### `API_BASE`
Basis-URL für API-Anfragen.
```javascript
import { API_BASE } from './state.js';
// → '../backend/api/index.php'
```

#### `CALENDAR_STATUS_ORDER`
Definierte Reihenfolge der Kalender-Status.
```javascript
import { CALENDAR_STATUS_ORDER } from './state.js';
// → ['tentative', 'confirmed', 'checked_in', 'paid', 'checked_out', 'cancelled', 'no_show']
```

#### `CALENDAR_COLOR_DEFAULTS`
Standard-Farben für Kalender-Status.
```javascript
import { CALENDAR_COLOR_DEFAULTS } from './state.js';
// → {
//   tentative: '#f97316',
//   confirmed: '#2563eb',
//   checked_in: '#16a34a',
//   ...
// }
```

#### `ARTICLE_SCHEME_LABELS`
Deutsche Labels für Abrechnungs-Schemas.
```javascript
import { ARTICLE_SCHEME_LABELS } from './state.js';
// → {
//   per_person_per_day: 'pro Person & Tag',
//   per_room_per_day: 'pro Zimmer & Tag',
//   ...
// }
```

#### `RESERVATION_STATUS_LABELS`
Deutsche Labels für Reservierungsstatus.
```javascript
import { RESERVATION_STATUS_LABELS } from './state.js';
// → {
//   tentative: 'Voranfrage',
//   confirmed: 'Bestätigt',
//   ...
// }
```

### State-Objekt

#### `state`
Globales Zustandsobjekt der Anwendung.

**Eigenschaften:**
- `token` (string|null) - API-Token
- `roomTypes` (Array) - Zimmertypen
- `ratePlans` (Array) - Tarife
- `rooms` (Array) - Zimmer
- `reservations` (Array) - Reservierungen
- `guests` (Array) - Gäste
- `companies` (Array) - Firmen
- `articles` (Array) - Artikel
- `calendarLabelMode` (string) - 'guest' oder 'company'
- `calendarColors` (Object) - Aktuelle Kalenderfarben
- und weitere...

**Beispiel:**
```javascript
import { state } from './state.js';

// Aktuellen Token prüfen
if (state.token) {
    console.log('Token ist gesetzt');
}

// Alle Reservierungen
console.log(state.reservations);
```

### Hilfsfunktionen

#### `getRoomTypeById(roomTypeId)`
Findet einen Zimmertyp nach ID.

**Parameter:**
- `roomTypeId` (number) - Zimmertyp-ID

**Rückgabe:** Object oder undefined

**Beispiel:**
```javascript
import { getRoomTypeById } from './state.js';

const roomType = getRoomTypeById(1);
// → { id: 1, name: 'Doppelzimmer', ... }
```

#### `getRatePlanById(ratePlanId)`
Findet einen Tarif nach ID.

**Parameter:**
- `ratePlanId` (number) - Tarif-ID

**Rückgabe:** Object oder undefined

**Beispiel:**
```javascript
import { getRatePlanById } from './state.js';

const ratePlan = getRatePlanById(1);
// → { id: 1, name: 'Standardrate', ... }
```

---

## api.js

API-Kommunikationsschicht für Backend-Anfragen.

### Basis-Funktionen

#### `apiFetch(path, options)`
Zentrale Funktion für authentifizierte API-Anfragen.

**Parameter:**
- `path` (string) - API-Endpunkt-Pfad
- `options` (Object, optional) - Fetch-Optionen
  - `skipAuth` (boolean) - Authentifizierung überspringen
  - weitere Standard-Fetch-Optionen

**Rückgabe:** Promise<Object|null>

**Beispiel:**
```javascript
import { apiFetch } from './api.js';

// GET-Anfrage
const data = await apiFetch('reservations');

// POST-Anfrage
const result = await apiFetch('reservations', {
    method: 'POST',
    body: JSON.stringify({ ... })
});
```

#### `requireToken()`
Prüft, ob ein API-Token gesetzt ist.

**Rückgabe:** Boolean

**Beispiel:**
```javascript
import { requireToken } from './api.js';

if (!requireToken()) {
    alert('Bitte Token setzen');
}
```

### Lade-Funktionen

#### `loadReservations(force)`
Lädt alle Reservierungen vom Backend.

**Parameter:**
- `force` (boolean, optional) - Cache ignorieren

**Rückgabe:** Promise<Array>

**Beispiel:**
```javascript
import { loadReservations } from './api.js';

const reservations = await loadReservations();
console.log(`${reservations.length} Reservierungen geladen`);
```

#### `loadRooms(force)`
Lädt alle Zimmer vom Backend.

**Parameter:**
- `force` (boolean, optional) - Cache ignorieren

**Rückgabe:** Promise<Array>

#### `loadGuests(force)`
Lädt alle Gäste vom Backend.

**Parameter:**
- `force` (boolean, optional) - Cache ignorieren

**Rückgabe:** Promise<Array>

#### `loadCompanies(force)`
Lädt alle Firmen vom Backend.

**Parameter:**
- `force` (boolean, optional) - Cache ignorieren

**Rückgabe:** Promise<Array>

#### `loadArticles(force)`
Lädt alle Artikel vom Backend.

**Parameter:**
- `force` (boolean, optional) - Cache ignorieren

**Rückgabe:** Promise<Array>

### Update-Funktionen

#### `updateReservationStatus(reservationId, status)`
Aktualisiert den Status einer Reservierung.

**Parameter:**
- `reservationId` (number) - Reservierungs-ID
- `status` (string) - Neuer Status

**Rückgabe:** Promise<Object>

**Beispiel:**
```javascript
import { updateReservationStatus } from './api.js';

await updateReservationStatus(123, 'checked_in');
```

### Such-Funktionen

#### `searchGuests(term, limit)`
Sucht Gäste nach Suchbegriff.

**Parameter:**
- `term` (string) - Suchbegriff
- `limit` (number, optional) - Max. Ergebnisse, Standard: 10

**Rückgabe:** Promise<Array>

**Beispiel:**
```javascript
import { searchGuests } from './api.js';

const results = await searchGuests('Mustermann', 5);
console.log(`${results.length} Treffer gefunden`);
```

---

## Fehlerbehandlung

Alle API-Funktionen werfen Fehler bei Problemen:

```javascript
try {
    const data = await loadReservations();
} catch (error) {
    console.error('Fehler beim Laden:', error.message);
}
```

## Best Practices

1. **Immer try-catch verwenden** bei API-Aufrufen
2. **Token vor API-Aufrufen prüfen** mit `requireToken()`
3. **Reine Funktionen nutzen** aus utils.js (keine Seiteneffekte)
4. **State nicht direkt modifizieren** - nur über definierte Funktionen
5. **Escaping beachten** bei Ausgabe von Benutzereingaben

## Tests

Alle Funktionen sind mit Unit-Tests abgesichert:

```bash
npm test
```

Dies führt alle Tests aus und zeigt die Abdeckung.
