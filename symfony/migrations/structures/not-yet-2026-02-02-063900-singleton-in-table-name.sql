RENAME TABLE google_api_user_tokens TO google_api_user_token;
RENAME TABLE google_drive_dirs TO google_drive_dir;

ALTER TABLE google_api_user_token RENAME INDEX idx_9a526eb4a76ed395 TO IDX_E2B772D4A76ED395;
ALTER TABLE google_drive_dir RENAME INDEX idx_9e13beafa76ed395 TO IDX_78417C52A76ED395;
ALTER TABLE product_product_tag
    DROP FOREIGN KEY FK_4F897D834584665A;
ALTER TABLE product_product_tag
    DROP FOREIGN KEY FK_4F897D83BAD26311;
ALTER TABLE product_product_tag
    ADD CONSTRAINT FK_4F897D834584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu);
ALTER TABLE product_product_tag
    ADD CONSTRAINT FK_4F897D83BAD26311 FOREIGN KEY (tag_id) REFERENCES product_tag (id);
