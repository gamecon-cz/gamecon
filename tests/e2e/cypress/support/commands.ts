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


Cypress.Commands.add("cleanDatabase", (createAdmin = true) => {
  const e2eEndpoint = Cypress.env('URL_WEBU') + 'e2e';

  cy.request({
    method: 'POST',
    url: e2eEndpoint,
    form: true,
    body: {
      'db-cisti': true,
    },
    headers: {
      'Content-Type': 'application/json',
    },
  })


  if (createAdmin)
    cy.request({
      method: 'POST',
      url: e2eEndpoint,
      form: true,
      body: {
        'admin-vytvor': true,
      },
      headers: {
        'Content-Type': 'application/json',
      },
    });
});

