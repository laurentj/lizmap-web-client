describe('Form edition all field type', function() {
    beforeEach(function(){
        // Runs before each tests in the block
        cy.visit('/index.php/view/map/?repository=testsrepository&project=form_edition_value_relation_field')
        // Start by launching feature form
        cy.get('#button-edition').click()
        cy.get('#edition-draw').click()
    })

    it('Check initial states', function() {
        context('No expression menulist 4 values + 1 empty value', function () {
            cy.get('#jforms_view_edition_code_without_exp option').should('have.length', 5)
            cy.get('#jforms_view_edition_code_without_exp option').first().should('have.text', '')
                .nextAll().each(($opt, index, $options) => {
                    cy.wrap($opt).should('not.have.text', '')
                })
        })

        context('Simple expression menulist 2 values + 1 empty value', function () {
            cy.get('#jforms_view_edition_code_with_simple_exp option').should('have.length', 3)
            cy.get('#jforms_view_edition_code_with_simple_exp option').first().should('have.text', '')
                .nextAll().each(($opt, index, $options) => {
                    cy.wrap($opt).should('not.have.text', '')
                })
        })

        context('Parent field menulist 3 values + 1 empty value', function () {
            cy.get('#jforms_view_edition_code_for_drill_down_exp option').should('have.length', 4)
            cy.get('#jforms_view_edition_code_for_drill_down_exp option').first().should('have.text', '')
                .nextAll().each(($opt, index, $options) => {
                    cy.wrap($opt).should('not.have.text', '')
                })
        })

        context('Child field menulist 0 value + 1 empty value', function () {
            cy.get('#jforms_view_edition_code_with_drill_down_exp option').should('have.length', 1)
            cy.get('#jforms_view_edition_code_with_drill_down_exp option').first().should('have.text', '')
        })

        context('Geom expression menulist 0 value + 1 empty value', function () {
            cy.get('#jforms_view_edition_code_with_geom_exp option').should('have.length', 1)
            cy.get('#jforms_view_edition_code_with_geom_exp option').first().should('have.text', '')
        })
    })

    it('Child field menulist after parent select', function () {
        // Intercept getListData query to wait for its end
        cy.intercept('/index.php/jelix/jforms/getListData*').as('getListData')

        // Select A in parent field
        cy.get('#jforms_view_edition_code_for_drill_down_exp').select('A').should('have.value', 'A')
        // Wait getListData query ends + slight delay for UI to be ready
        cy.wait('@getListData')
        // Child field menulist 2 values + 1 empty value
        cy.get('#jforms_view_edition_code_with_drill_down_exp option').should('have.length', 3)
        cy.get('#jforms_view_edition_code_with_drill_down_exp option').first().should('have.text', '')
            .nextAll().then(options => {
                const actual = [...options].map(o => o.text)
                expect(actual).to.match(/^Zone A/)
            })

        // Select B in parent field
        cy.get('#jforms_view_edition_code_for_drill_down_exp').select('B').should('have.value', 'B')
        // Wait getListData query ends + slight delay for UI to be ready
        cy.wait('@getListData')
        // Child field menulist 2 values + 1 empty value
        cy.get('#jforms_view_edition_code_with_drill_down_exp option').should('have.length', 3)
        cy.get('#jforms_view_edition_code_with_drill_down_exp option').first().should('have.text', '')
            .nextAll().then(options => {
                const actual = [...options].map(o => o.text)
                expect(actual).to.match(/^Zone B/)
            })

        // Select No Zone in parent field
        cy.get('#jforms_view_edition_code_for_drill_down_exp').select('No Zone').should('have.value', '{2839923C-8B7D-419E-B84B-CA2FE9B80EC7}')
        // Wait getListData query ends + slight delay for UI to be ready
        cy.wait('@getListData')
        // Child field menulist 0 value + 1 empty value
        cy.get('#jforms_view_edition_code_with_drill_down_exp option').should('have.length', 1)
    })

    it('Child field menulist after geometry creation', function () {
        // Wait before clicking on the map
        cy.wait(800)

        // Intercept getListData query to wait for its end
        cy.intercept('/index.php/jelix/jforms/getListData*').as('getListData')

        // Click on map as form needs a geometry
        cy.get('#map').click(500, 300)
        // Wait getListData query ends + slight delay for UI to be ready
        cy.wait('@getListData')

        cy.get('#jforms_view_edition_code_with_geom_exp option').should('have.length', 2)
        cy.get('#jforms_view_edition_code_with_geom_exp option').first().should('have.text', '')
        cy.get('#jforms_view_edition_code_with_geom_exp option').last().should('have.text', 'Zone A1')
    })

})