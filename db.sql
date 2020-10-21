CREATE DATABASE "tekexplorer";

CREATE USER 'tekexplorer'@localhost IDENTIFIED BY 'xxx';

GRANT ALL PRIVILEGES ON *.* TO 'tekexplorer'@localhost IDENTIFIED BY 'xxx';

CREATE TABLE `tek_keys` (
  `k_id` varchar(64) NOT NULL,
  `k_source` varchar(20) DEFAULT NULL,
  `k_date` bigint(11) DEFAULT NULL,
  `k_rolling_start_interval_number` bigint(11) DEFAULT NULL,
  `k_rolling_period` bigint(11) DEFAULT NULL,
  PRIMARY KEY (`k_id`),
  KEY `i_k_date` (`k_date`),
  KEY `i_k_source` (`k_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
