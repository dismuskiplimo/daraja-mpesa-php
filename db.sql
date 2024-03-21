CREATE DATABASE IF NOT EXISTS mpesa;

USE mpesa;

CREATE TABLE IF NOT EXISTS transactions(
    id INT AUTO_INCREMENT,
    phone VARCHAR(255),
    amount INT,
    merchant_request_id VARCHAR(255),
    checkout_request_id VARCHAR(255),
    mpesa_receipt_number VARCHAR(255),
    transaction_date VARCHAR(255),
    fulfilled TINYINT(1) DEFAULT(0),
    PRIMARY KEY(id)
);