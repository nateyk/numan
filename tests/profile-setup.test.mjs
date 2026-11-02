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
const { default: ContactNumberFields } = await server.ssrLoadModule(
    '/resources/js/components/profile/contact-number-fields.tsx',
);
const { default: ContactChannels } = await server.ssrLoadModule(
    '/resources/js/components/profile/contact-channels.tsx',
);

const render = (Component, props) =>
    renderToStaticMarkup(React.createElement(Component, props));

test('requires the first phone and offers country-aware inputs for every added number', () => {
    const html = render(ContactNumberFields, {
        field: 'phone_numbers',
        label: 'Phone',
        numbers: ['+251911234567', '+25377123456'],
        required: true,
        errors: {},
        onChange() {},
    });
    assert.ok(html.includes('ET +251'));
    assert.ok(html.includes('DJ +253'));
    assert.equal((html.match(/type="tel"/g) ?? []).length, 2);
    assert.equal((html.match(/required=""/g) ?? []).length, 1);
    assert.ok(html.includes('Add phone number'));
    assert.ok(html.includes('Remove phone 2'));
});

test('keeps WhatsApp optional and shows an error on the invalid row', () => {
    const html = render(ContactNumberFields, {
        field: 'whatsapp_numbers',
        label: 'WhatsApp',
        numbers: ['invalid'],
        errors: { 'whatsapp_numbers.0': 'Enter a valid number.' },
        onChange() {},
    });
    assert.ok(html.includes('Enter a valid number.'));
    assert.ok(!html.includes('required=""'));
    assert.ok(html.includes('aria-invalid="true"'));
});

test('renders all released contacts as individual call and WhatsApp links', () => {
    const html = render(ContactChannels, {
        contact: {
            phone_numbers: ['+251911234567', '+25377123456'],
            whatsapp_numbers: ['+251711234567', '+25377123456'],
        },
    });
    for (const link of [
        'tel:+251911234567',
        'tel:+25377123456',
        'https://wa.me/251711234567',
        'https://wa.me/25377123456',
    ])
        assert.ok(html.includes(link), link);
});

test('supports legacy single contacts but respects an explicitly empty released list', () => {
    assert.ok(
        render(ContactChannels, {
            contact: { phone: '+251911234567' },
        }).includes('tel:+251911234567'),
    );
    const hidden = render(ContactChannels, {
        contact: {
            phone: '+251911234567',
            phone_numbers: [],
            whatsapp_numbers: [],
        },
    });
    assert.ok(!hidden.includes('tel:'));
    assert.ok(hidden.includes('No shared contact details.'));
});
