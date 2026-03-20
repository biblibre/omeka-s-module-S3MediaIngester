Cypress.Screenshot.defaults({
    capture: 'viewport',
})

Cypress.Commands.add('loginAsAdmin', () => {
    cy.env(['adminEmail', 'adminPassword']).then(env => {
        cy.visit('/login')
        cy.get('input[name="email"]').type(env.adminEmail);
        cy.get('input[name="password"]').type(env.adminPassword);
        cy.get('#loginform input[type="submit"]').click();
    });
});
Cypress.Commands.add('logout', () => {
    cy.visit('/logout');
});

describe('screenshots', () => {
    before(function() {
        cy.loginAsAdmin();
        const omekaLang = Cypress.expose('omekaLang');
        if (omekaLang) {
            cy.visit('/admin/setting');
            cy.get('#locale').select(omekaLang, { force: true });
            cy.get('#page-actions button').click();
        }
        cy.logout();
    });

    it('configure module', () => {
        cy.loginAsAdmin();
        cy.visit('/admin/module/configure?id=S3MediaIngester');
        cy.screenshot('images/module-configuration');
    });
})
