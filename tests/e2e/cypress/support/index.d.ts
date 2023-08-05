
declare namespace Cypress {
  interface Chainable<Subject> {
    /**
     * clean the database.
     */
    cleanDatabase(createAdmin?: boolean): void;

    připravDB(jménoDB: string): void;

    použijDB(jménoDB: string): void;
  }
}
