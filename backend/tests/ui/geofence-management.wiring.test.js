import { describe, test, expect, vi, beforeAll, beforeEach } from 'vitest';
import fs from 'fs';
import path from 'path';

const geofenceCode = fs.readFileSync(
    path.resolve(__dirname, '../../../frontend/resources/js/geofences/geofence-management-data.js'),
    'utf-8'
);

const addModalHtml = `
<div class="modal fade" id="add_geofence">
  <form data-gf-form="add">
    <input type="text" data-gf-field="name">
    <input type="text" data-gf-field="latitude">
    <input type="text" data-gf-field="longitude">
    <input type="number" data-gf-field="radius">
    <select data-gf-field="is_active"><option value="1">Active</option></select>
    <input type="text" data-gf-address-search="add">
    <button data-gf-search-btn="add"></button>
    <button data-gf-current-loc="add"></button>
    <div data-gf-search-results="add"></div>
    <div id="gf-add-map" style="height:360px;"></div>
  </form>
</div>
<div class="modal fade" id="edit_geofence">
  <form data-gf-form="edit">
    <input type="text" data-gf-field="name">
    <input type="text" data-gf-field="latitude">
    <input type="text" data-gf-field="longitude">
    <input type="number" data-gf-field="radius">
    <select data-gf-field="is_active"><option value="1">Active</option></select>
    <input type="text" data-gf-address-search="edit">
    <button data-gf-search-btn="edit"></button>
    <button data-gf-current-loc="edit"></button>
    <div data-gf-search-results="edit"></div>
    <div id="gf-edit-map" style="height:360px;"></div>
  </form>
</div>
<div data-hcm-toast-container></div>
<tbody data-gf-body></tbody>
<ul data-gf-pagination></ul>
<small data-gf-showing></small>
`;

function mockLeaflet() {
    var mapMock = {
        setView: vi.fn().mockReturnThis(),
        on: vi.fn(),
        remove: vi.fn(),
        invalidateSize: vi.fn(),
        fitBounds: vi.fn(),
        addLayer: vi.fn(),
        removeLayer: vi.fn(),
        _leaflet_id: 1,
    };
    var markerMock = {
        setLatLng: vi.fn(),
        addTo: vi.fn().mockReturnThis(),
        on: vi.fn(),
    };
    var circleMock = {
        setLatLng: vi.fn(),
        setRadius: vi.fn(),
        addTo: vi.fn().mockReturnThis(),
        getBounds: vi.fn().mockReturnValue({ pad: vi.fn().mockReturnThis() }),
    };
    var tileLayerMock = { addTo: vi.fn().mockReturnThis() };

    globalThis.L = {
        Map: { _maps: [] },
        map: vi.fn().mockReturnValue(mapMock),
        tileLayer: vi.fn().mockReturnValue(tileLayerMock),
        marker: vi.fn().mockReturnValue(markerMock),
        circle: vi.fn().mockReturnValue(circleMock),
        polyline: vi.fn().mockReturnValue({ addTo: vi.fn().mockReturnThis() }),
        divIcon: vi.fn().mockReturnValue({}),
        DomEvent: { on: vi.fn(), off: vi.fn() },
        Control: { Zoom: vi.fn() },
    };

    return { mapMock, markerMock, circleMock, tileLayerMock };
}

function loadGeofenceJs() {
    try {
        var fn = new Function(geofenceCode + '; return window.__geofenceTest || null;');
        return fn();
    } catch (e) {
        return null;
    }
}

describe('Geofence Management — Search & Location', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        vi.restoreAllMocks();
        delete globalThis.L;
        delete window.__geofenceTest;
        delete window.ArcavUi;
        delete window.AuthApi;
    });

    describe('DOM structure', () => {
        test('add modal has address search input', () => {
            document.body.innerHTML = addModalHtml;
            var el = document.querySelector('[data-gf-address-search="add"]');
            expect(el).not.toBeNull();
        });

        test('add modal has current location button', () => {
            document.body.innerHTML = addModalHtml;
            var el = document.querySelector('[data-gf-current-loc="add"]');
            expect(el).not.toBeNull();
        });

        test('edit modal has address search input', () => {
            document.body.innerHTML = addModalHtml;
            var el = document.querySelector('[data-gf-address-search="edit"]');
            expect(el).not.toBeNull();
        });

        test('edit modal has current location button', () => {
            document.body.innerHTML = addModalHtml;
            var el = document.querySelector('[data-gf-current-loc="edit"]');
            expect(el).not.toBeNull();
        });
    });

    describe('Current location', () => {
        test('getCurrentPosition function resolves geolocation data', async () => {
            Object.defineProperty(navigator, 'geolocation', {
                value: { getCurrentPosition: vi.fn(function (success) { success({ coords: { latitude: -6.2, longitude: 106.8 } }); }) },
                configurable: true, writable: true,
            });

            document.body.innerHTML = addModalHtml;
            mockLeaflet();
            loadGeofenceJs();

            var testApi = window.__geofenceTest;
            expect(testApi).not.toBeNull();

            var pos = await testApi.getCurrentPosition();
            expect(pos.latitude).toBe(-6.2);
            expect(pos.longitude).toBe(106.8);
        });

        test('getCurrentPosition rejects when geolocation unsupported', async () => {
            Object.defineProperty(navigator, 'geolocation', {
                value: undefined, configurable: true, writable: true,
            });

            document.body.innerHTML = addModalHtml;
            mockLeaflet();
            loadGeofenceJs();

            var testApi = window.__geofenceTest;

            await expect(testApi.getCurrentPosition()).rejects.toBeDefined();
        });
    });

    describe('Address search', () => {
        test('searchAddress returns results from Nominatim', async () => {
            document.body.innerHTML = addModalHtml;
            mockLeaflet();
            loadGeofenceJs();

            var testApi = window.__geofenceTest;
            expect(testApi).not.toBeNull();
            expect(typeof testApi.searchAddress).toBe('function');

            vi.spyOn(globalThis, 'fetch').mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue([
                    { lat: '-6.2088', lon: '106.8456', display_name: 'Jakarta, Indonesia' },
                ]),
            });

            var results = await testApi.searchAddress('Jakarta');
            expect(results).toHaveLength(1);
            expect(results[0].lat).toBe('-6.2088');
            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('nominatim.openstreetmap.org/search'),
                expect.any(Object)
            );
        });

        test('searchAddress rejects short query', async () => {
            document.body.innerHTML = addModalHtml;
            mockLeaflet();
            loadGeofenceJs();

            var testApi = window.__geofenceTest;

            await expect(testApi.searchAddress('ab')).rejects.toBeDefined();
        });
    });
});
