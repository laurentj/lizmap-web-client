// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Dataviz in popup', ()=>{
    test('Check lizmap feature toolbar', async ({page}) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=popup_bar';
        await page.goto(url, { waitUntil: 'networkidle' });

        await page.locator("#dock-close").click();

        await page.waitForTimeout(300);

        let getPlot= page.waitForRequest(request => request.method() === 'POST' && request.postData().includes('getPlot'));

        await page.locator('#newOlMap').click({
            position: {
              x: 355,
              y: 280
            }
        });

        await getPlot;

        // inspect feature toolbar, expect to find only one
        await expect(page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv > lizmap-feature-toolbar .feature-toolbar")).toHaveCount(1)

        // click again on the same point
        await page.locator('#newOlMap').click({
            position: {
              x: 355,
              y: 280
            }
        });

        await getPlot;
        // inspect feature toolbar, expect to find only one
        await expect(page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv > lizmap-feature-toolbar .feature-toolbar")).toHaveCount(1)

        // click on another point
        await page.locator('#newOlMap').click({
            position: {
                x: 410,
                y: 216
            }
        });

        await getPlot;
        // inspect feature toolbar, expect to find only one
        await expect(page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv > lizmap-feature-toolbar .feature-toolbar")).toHaveCount(1)

        // click where there is no feature
        await page.locator('#newOlMap').click({
            position: {
                x: 410,
                y: 300
            }
        });

        await page.waitForTimeout(500);

        // reopen previous popup
        await page.locator('#newOlMap').click({
            position: {
                x: 410,
                y: 216
            }
        });

        await getPlot;
        // inspect feature toolbar, expect to find only one
        await expect(page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv > lizmap-feature-toolbar .feature-toolbar")).toHaveCount(1)


    })
})

test.describe('Style parameter in GetFeatureInfo request', ()=>{
    test('Click on the map to show the popup', async ({page}) => {

        // the get_feature_info_style project has one layer "natural_areas" configured with two styles: default and ids
        //
        // "default" style: shows all the 3 features of the natural_area layer, it has QGIS Html Maptip enabled
        // "ids" style: shows only 2 of the 3 features of the natural_area layer, drag & drop tooltip enabled. the layer with id = 3 is not show

        // QGIS project is saved with the "ids" style enabled on the layer natural_areas
        // Lizmap init the map with the first style found in the styles's list sorted alphabetically, in this case "default"


        const url = '/index.php/view/map/?repository=testsrepository&project=get_feature_info_style';
        await page.goto(url, { waitUntil: 'networkidle' });

        await page.locator("#dock-close").click();

        await page.waitForTimeout(300);


        // get the popup of the feature with id = 3. The STYLE property (STYLE=default) should be passed in the getfeatureinfo request.
        // Otherwise the popup would not be shown because QGIS Server query the layer natural_areas with the "ids" style

        let getPopup= page.waitForRequest(request => request.method() === 'POST' && request.postData().includes('STYLE=default'));

        await page.locator('#newOlMap').click({
          position: {
            x: 501,
            y: 488
          }
        });

        await getPopup;

        // inspect feature toolbar, expect to find only one
        const popup = page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv div.container.popup_lizmap_dd")
        await expect(popup).toHaveCount(1)
        await expect(popup.locator(".before-tabs div.field")).toHaveCount(2);
        await expect(popup.locator("#test-custom-tooltip")).toHaveText("Custom tooltip");

        await expect(popup.locator(".before-tabs div.field").nth(0)).toHaveText("3");
        await expect(popup.locator(".before-tabs div.field").nth(1)).toHaveText("Étang Saint Anne");


        // change the style of the layer
        await page.locator("#button-switcher").click()
        await page.getByTestId('natural_areas').hover();
        await page.getByTestId('natural_areas').locator('i').nth(1).click();
        await page.locator('#sub-dock').getByRole('combobox').selectOption("ids")

        // wait for the map
        await page.waitForTimeout(1000)

        let getPopupIds = page.waitForRequest(request => request.method() === 'POST' && request.postData().includes('STYLE=ids'));
        // click again on the previous point
        await page.locator('#newOlMap').click({
            position: {
              x: 501,
              y: 488
            }
          });

        await getPopupIds;

        // the popup should be empty
        const popupIds = page.locator("#popupcontent > div.menu-content > div.lizmapPopupContent > div.lizmapPopupSingleFeature > div.lizmapPopupDiv div.container.popup_lizmap_dd")

        await expect(popupIds).toHaveCount(0);

        // clean the map
        await page.locator("#hide-sub-dock").click();

        let getPopupIdsFeature = page.waitForRequest(request => request.method() === 'POST' && request.postData().includes('STYLE=ids'));
        // click on a feature to get the popup (it should fallback to the default lizmap popup)
        await page.locator('#newOlMap').click({
          position: {
            x: 404,
            y: 165
          }
        });

        await getPopupIdsFeature;

        await page.waitForTimeout(300)

        const popupIdsFeat = page.locator("#popupcontent div.lizmapPopupSingleFeature")
        await expect(popupIdsFeat).toHaveCount(1);

        // expect to have the lizmap default popup ("automatic")
        await expect(popupIdsFeat.locator("table tbody tr")).toHaveCount(2);
        await expect(popupIdsFeat.locator("table tbody tr").nth(0).locator("td")).toHaveText("1");
        await expect(popupIdsFeat.locator("table tbody tr").nth(1).locator("td")).toHaveText("Étang du Galabert");
    })
})

test.describe('Popup', () => {

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=popup';
        await page.goto(url, { waitUntil: 'networkidle' });
    });

    test('click on the shape to show the popup', async ({ page }) => {
        // When clicking on triangle feature a popup with two tabs must appear
        await page.locator('#newOlMap').click({
            position: {
                x: 510,
                y: 415
            }
        });
        await expect(page.locator('#newOlMap #liz_layer_popup')).toBeVisible();
        await expect(page.locator('#newOlMap #liz_layer_popup_contentDiv > div > div > div > ul > li.active > a')).toBeVisible();
        await expect(page.locator('#newOlMap #liz_layer_popup_contentDiv > div > div > div > ul > li:nth-child(2) > a')).toBeVisible();
    });

    test('changes popup tab', async ({ page }) => {
        // When clicking `tab2`, `tab2_value` must appear
        await page.locator('#newOlMap').click({
            position: {
                x: 510,
                y: 490
            }
        });
        await page.waitForTimeout(300);
        await page.getByRole('link', { name: 'tab2' }).click({force: true});
        await expect(page.locator('#popup_dd_1_tab2')).toHaveClass(/active/);
    });

    test('displays children popups', async ({ page }) => {
        await page.locator('#newOlMap').click({
            position: {
                x: 510,
                y: 415
            }
        });
        await expect(page.locator('#newOlMap #liz_layer_popup .lizmapPopupChildren .lizmapPopupSingleFeature')).toHaveCount(2);
    });

    test('getFeatureInfo request should contain a FILTERTOKEN parameter when the layer is filtered', async ({ page }) => {
        await page.locator('#button-filter').click();

        // Select a feature to filter and wait for GetMap request with FILTERTOKEN parameter
        let getMapRequestPromise = page.waitForRequest(/FILTERTOKEN/);
        await page.locator('#liz-filter-field-test').selectOption('1');
        await getMapRequestPromise;

        let getFeatureInfoRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData().includes('GetFeatureInfo'));
        await page.locator('#newOlMap').click({
          position: {
            x: 486,
            y: 136
          }
        });

        let getFeatureInfoRequest = await getFeatureInfoRequestPromise;
        expect(getFeatureInfoRequest.postData()).toMatch(/FILTERTOKEN/);
    });
});
