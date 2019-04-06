DROP TABLE IF EXISTS addresses;
CREATE TABLE addresses (
   `id` varchar(255) NOT NULL,
   `sent` decimal(20, 8) NOT NULL,
   `received` decimal(20, 8) NOT NULL,
   `balance` decimal(20, 8) NOT NULL,
   `last_received` datetime,
   `last_sent` datetime,
   PRIMARY KEY (`id`)
);

DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
   `address_id` varchar(255) NOT NULL,
   `txid` varchar(255) NOT NULL,
   `type` char(1),
   `amount` decimal(20, 8) NOT NULL,
   `block_height` int NOT NULL,
   `date_added` datetime NOT NULL,
   PRIMARY KEY (`address_id`, `txid`, `type`)
);


DROP TABLE IF EXISTS jobs;
CREATE TABLE jobs (
   `id` int NOT NULL,
   `last_block` int NOT NULL,
   `last_executed` datetime NOT NULL,
   PRIMARY KEY (`id`)
);