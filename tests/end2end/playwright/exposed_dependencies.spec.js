// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Exposed dependencies', () => {
    test('Dependencies are accessible', async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=startup';
        await page.goto(url, { waitUntil: 'networkidle' });

        // OpenLayers
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('Feature');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('Overlay');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('extent');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('format');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('geom');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('layer');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('proj');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('source');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('sphere');
        expect(await page.evaluate(() => globalThis.lizMap.ol)).toHaveProperty('style');

        // Proj4
        expect(await page.evaluate(() => globalThis.lizMap.proj4.defs('EPSG:2154'))).toBeDefined();
    })
})
