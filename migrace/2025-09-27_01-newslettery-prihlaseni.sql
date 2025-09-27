CREATE TABLE newsletter_prihlaseni
(
    id_newsletter_prihlaseni INT PRIMARY KEY AUTO_INCREMENT,
    email                    VARCHAR(512) NOT NULL,
    kdy                      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY email (email)
);

CREATE TABLE newsletter_prihlaseni_log
(
    id_newsletter_prihlaseni_log INT PRIMARY KEY AUTO_INCREMENT,
    email                        VARCHAR(512) NOT NULL,
    kdy                          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stav                         VARCHAR(127) NOT NULL,
    INDEX email (email)
);

INSERT INTO reporty(skript, nazev, format_html, format_xlsx)
VALUES ('newsletter-prihlaseni', 'Přihlášení k odběru newsletterů', 1, 1);
