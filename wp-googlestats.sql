CREATE TABLE `wp_googlestats` (
  `page` varchar(100) NOT NULL default '',
  `lastvisit` int(11) NOT NULL default '0',
  `frequency` int(11) NOT NULL default '0',
  `visits` int(11) NOT NULL default '0',
  `timestamp` timestamp(14) NOT NULL,
  KEY `page` (`page`)
)
