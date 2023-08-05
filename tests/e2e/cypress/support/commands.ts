// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

const testKonzoleEndpoint = Cypress.env('URL_WEBU') + 'test';

const pošliPříkazTestovacíKonzoli = (příkaz:
  | "db-reset"
  | "db-cisti"
  | "db-smaz"
  | "admin-vytvor"
) => {
  cy.request({
    method: 'POST',
    url: testKonzoleEndpoint,
    form: true,
    body: {
      [příkaz]: true,
    },
    headers: {
      'Content-Type': 'application/json',
    },
  })
}


const testovacíDBZeJména = (název: string) => "gamecon_test_" + název;

Cypress.Commands.add("cleanDatabase", (createAdmin = true) => {
  pošliPříkazTestovacíKonzoli("db-cisti")

  if (createAdmin)
    pošliPříkazTestovacíKonzoli("admin-vytvor")
});

Cypress.Commands.add("připravDB", (jménoDB: string) => {
  cy.setCookie("test", "1")
  cy.setCookie("gamecon_test_db", testovacíDBZeJména(jménoDB))

  pošliPříkazTestovacíKonzoli("db-reset")
  pošliPříkazTestovacíKonzoli("admin-vytvor")
});

Cypress.Commands.add("použijDB", (jménoDB: string) => {
  cy.session(jménoDB, () => {
    cy.setCookie("test", "1")
    cy.setCookie("gamecon_test_db", testovacíDBZeJména(jménoDB))
  }, {
    validate() {
      // TODO:
    }
  })
});

