describe('login', () => {
  it('přihlásí se jako admin', () => {
    cy.visit(Cypress.env("URL_WEBU") + 'prihlaseni');

    cy.get('input[name=login]').type("localAdmin");

    cy.get('input[name=heslo]').type("Gamecon");

    cy.get('input[type=submit]').click();

    cy.url().should('contain', "prihlaska");
  })

  it('nepřihlásí se pokud je špatně heslo', () => {
    cy.visit(Cypress.env("URL_WEBU") + 'prihlaseni');

    cy.get('input[name=login]').type("localAdmin");

    cy.get('input[name=heslo]').type("GaMeCoM");

    cy.get('input[type=submit]').click();

    cy.get('input[name=heslo]').should("be.empty");

    cy.get('.errorHlaska').should("be.visible");

    cy.url().should('equal', Cypress.env("URL_WEBU") + 'prihlaseni');
  })
})