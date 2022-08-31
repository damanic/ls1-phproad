CREATE TABLE `phpr_module_versions` (
  `id` int(11) NOT NULL auto_increment,
  `module_id` varchar(255) default NULL,
  `version` int(11) default NULL,
  `date` date default NULL,
  `version_str` varchar(50),
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `phpr_module_update_history` (
  `id` int(11) NOT NULL auto_increment,
  `date` date default NULL,
  `module_id` varchar(255) default NULL,
  `version` int(11) default NULL,
  `description` text,
  `version_str` varchar(50),
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `phpr_module_applied_updates` (
  `id` int(11) NOT NULL auto_increment,
  `module_id` varchar(255) default NULL,
  `update_id` varchar(50) default NULL,
  `created_at` datetime default NULL,
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `phpr_module_params` (
    `module_id` varchar(30) NOT NULL DEFAULT '0',
    `name` varchar(100) NOT NULL DEFAULT '',
    `value` text,
    PRIMARY KEY (`module_id`,`name`)
) DEFAULT CHARSET=utf8;

