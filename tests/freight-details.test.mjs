import react from '@vitejs/plugin-react';
import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import { after, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({
    configFile: false,
    optimizeDeps: { noDiscovery: true, include: [] },
    plugins: [react()],
    resolve: { alias: { '@': resolve('resources/js') } },
    server: { middlewareMode: true, hmr: false },
});
after(() => server.close());
const { default: FreightDetailsCard } = await server.ssrLoadModule(
    '/resources/js/components/freight/freight-details-card.tsx',
);

const post = {
    origin: {
        name: 'Djibouti',
        region_name: 'Djibouti',
        latitude: 11.6,
        longitude: 43.1,
    },
    destination: {
        name: 'Wolkite',
        region_name: 'Central Ethiopia',
        latitude: 8.3,
        longitude: 37.8,
    },
    item_name: '40FT Container',
    cargo_type: { name: 'General Cargo' },
    actual_weight: '360.00',
    weight_unit: 'quintal',
    tariff_weight: '400',
    tariff_weight_unit: 'quintal',
    tariff_amount: '1050.00',
    tariff_currency: 'ETB',
    truck_type: { name: 'Flatbed' },
    truck_count: 39,
    schedule_type: 'one_time',
    pickup_date: '2026-10-31',
    pickup_time: '14:27',
    created_at: '2026-10-21T12:40:11Z',
    route_snapshot: null,
    origin_site_note: 'Loading gate 2',
    destination_site_note: 'Call at arrival',
    cargo_description: 'Handle carefully',
    insurance_required: true,
    insurance_value: '50000',
    depreciation_rate: '2',
};

test('renders the complete freight load sheet with separate actual and tariff weights', () => {
    const html = renderToStaticMarkup(
        React.createElement(FreightDetailsCard, { post }),
    );
    for (const value of [
        'Route &amp; schedule',
        'Cargo &amp; truck',
        'Djibouti (Djibouti)',
        'Wolkite (Central Ethiopia)',
        'General Cargo',
        '360.00 quintal',
        '400 quintal',
        'ETB 1050.00',
        'Flatbed × 39',
        'Direct offer',
        '14:27',
        'Created',
        'Loading gate 2',
        'Call at arrival',
        'Handle carefully',
        'ETB 50000',
        '2%',
        'View route map',
    ]) {
        assert.ok(html.includes(value), `Missing ${value}`);
    }
    assert.ok(!html.includes('one_time'));
});

test('handles missing data without exposing invalid values or an unusable map', () => {
    const html = renderToStaticMarkup(
        React.createElement(FreightDetailsCard, { post: {} }),
    );
    assert.ok(html.includes('Not stated'));
    assert.ok(html.includes('Flexible'));
    assert.ok(html.includes('Any truck'));
    assert.ok(!/undefined|Invalid Date|NaN|View route map/.test(html));
});

test('offers the map as an on-demand dialog beside the route heading', () => {
    const html = renderToStaticMarkup(
        React.createElement(FreightDetailsCard, { post }),
    );
    assert.match(html, /aria-haspopup="dialog"/);
    assert.match(html, /aria-expanded="false"/);
    const routeSection = html.slice(
        html.indexOf('<section'),
        html.indexOf('</section>'),
    );
    assert.ok(
        routeSection.indexOf('View route map') < routeSection.indexOf('<dl>'),
    );
    assert.ok(!html.includes('Loading map'));
});
