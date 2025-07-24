CREATE TABLE gcoa_clients (
    id_client int unsigned auto_increment not null unique primary key,
    identifier varchar(100),
    name varchar(100),
    confidential tinyint(1) default 0 not null,
    redirect_uri varchar(200)
);

CREATE TABLE gcoa_auth_codes (
    id_auth_code int unsigned auto_increment not null unique primary key ,
    redirect_uri varchar(200),
    identifier varchar(100),
    client_id varchar(100),
    user_identifier varchar(100)
);

CREATE TABLE gcoa_access_tokens (
    id_access_token int unsigned auto_increment not null unique primary key ,
    client_id varchar(100),
    user_id varchar(100),
    identifier varchar(100)
);

CREATE TABLE gcoa_refresh_tokens (
    id_refresh_token int unsigned auto_increment not null unique primary key,
    identifier varchar(100)
);
