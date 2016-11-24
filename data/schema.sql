CREATE TABLE `subscriptions` (
  `sub_id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sub_topic` varchar(4096) NOT NULL,
  `sub_status` enum('subscribing','active','unsubscribing','unsubscribed','expired','denied') NOT NULL,
  `sub_lease_seconds` int NOT NULL,
  `sub_expires` datetime NOT NULL,
  `sub_secret` varchar(256) NOT NULL,
  `sub_capkey` varchar(128) NOT NULL,
  `sub_created` datetime NOT NULL,
  `sub_updated` datetime NOT NULL,
  `sub_pings` int NOT NULL,
  `sub_lastping` datetime NOT NULL,
  `sub_statusmessage` varchar(512) NOT NULL
) COMMENT='' COLLATE 'utf8_general_ci';
