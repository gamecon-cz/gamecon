describe('login', () => {
  before(()=>{
    cy.cleanDatabase();
  })

  it('přihlásí se a odhlásí jako admin', () => {
    cy.visit(Cypress.env("URL_WEBU") + 'prihlaseni');

    cy.get('input[name=login]').type("localAdmin");

    cy.get('input[name=heslo]').type("Gamecon");

    cy.get('input[type=submit]').click();

    cy.url().should('contain', "prihlaska");

    // TODO: lepší selector
    cy.get('[href="#"]').click({force: true});

    cy.get('.menu_prihlasit').should("exist");
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


  it('zaregistruje se', () =>{
    cy.visit(Cypress.env("URL_WEBU") + 'registrace');

    /* ==== Generated with Cypress Studio ==== */
    cy.get('#input_email1_uzivatele').type('thrash@gamecon.cz');
    cy.get('#input_predvolba').select('+420');
    cy.get('#input_telefon_uzivatele').type('666333111');
    cy.get('#input_jmeno_uzivatele').type('TestovacíUživatel');
    cy.get('#input_prijmeni_uzivatele').type('Někdo');
    cy.get('#input_datum_narozeni').type('2001-02-01');
    cy.get('#input_statni_obcanstvi').type('ČR');
    cy.get('#input_ulice_a_cp_uzivatele').type('Doma 1');
    cy.get('#input_mesto_uzivatele').type('Atlantis');
    cy.get('#input_psc_uzivatele').type('66632');
    cy.get(':nth-child(4) > select').select('-1');
    cy.get(':nth-child(13) > :nth-child(1) > select').select('jiny');
    cy.get('#input_op').type('123456798');
    cy.get('#input_login_uzivatele').type('Jožo');
    cy.get(':nth-child(2) > select').select('m');
    cy.get('#input_heslo').type('blahblah');
    cy.get('#input_heslo_kontrola').type('blahblah');
    cy.get('.formular_polozka-checkbox > input').check();
    cy.get('.formular_sekundarni').click();
    /* ==== End Cypress Studio ==== */

    cy.get('.hlaska').should("be.visible").contains("Účet vytvořen");
  })
})