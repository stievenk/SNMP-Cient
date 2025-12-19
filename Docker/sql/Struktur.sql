# ************************************************************
# Sequel Ace SQL dump
# Version 20095
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: localhost (MySQL 11.7.2-MariaDB)
# Database: ryzenet
# Generation Time: 2025-12-19 06:34:41 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table server_cpu_history
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_cpu_history`;

CREATE TABLE `server_cpu_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) NOT NULL,
  `load_1` float DEFAULT NULL,
  `load_5` float DEFAULT NULL,
  `load_15` float DEFAULT NULL,
  `cpu_idle` float DEFAULT NULL,
  `cpu_user` float DEFAULT NULL,
  `cpu_system` float DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cpu_time` (`server_id`,`created_at`),
  CONSTRAINT `server_cpu_history_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_disks
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_disks`;

CREATE TABLE `server_disks` (
  `server_id` bigint(20) NOT NULL,
  `disk_name` varchar(150) NOT NULL,
  `disk_used` bigint(20) DEFAULT NULL,
  `disk_total` bigint(20) DEFAULT NULL,
  `disk_free` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`server_id`,`disk_name`),
  UNIQUE KEY `server_id` (`server_id`,`disk_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_interfaces
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_interfaces`;

CREATE TABLE `server_interfaces` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) NOT NULL,
  `if_index` int(11) DEFAULT NULL,
  `if_name` varchar(50) DEFAULT NULL,
  `if_type` varchar(50) DEFAULT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `speed` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id` (`server_id`,`if_index`),
  CONSTRAINT `server_interfaces_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_interfaces_history
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_interfaces_history`;

CREATE TABLE `server_interfaces_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) unsigned NOT NULL,
  `if_name` varchar(100) NOT NULL,
  `in_bps` bigint(20) unsigned DEFAULT 0,
  `out_bps` bigint(20) unsigned DEFAULT 0,
  `delta_time` bigint(20) DEFAULT NULL,
  `create_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_ip_addresses
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_ip_addresses`;

CREATE TABLE `server_ip_addresses` (
  `server_id` bigint(20) NOT NULL,
  `if_name` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `netmask` varchar(45) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`server_id`,`if_name`),
  CONSTRAINT `server_ip_addresses_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_memory_history
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_memory_history`;

CREATE TABLE `server_memory_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) NOT NULL,
  `ram_total` bigint(20) DEFAULT NULL,
  `ram_used` bigint(20) DEFAULT NULL,
  `ram_free` bigint(20) DEFAULT NULL,
  `swap_total` bigint(20) DEFAULT NULL,
  `swap_used` bigint(20) DEFAULT NULL,
  `swap_free` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mem_time` (`server_id`,`created_at`),
  CONSTRAINT `server_memory_history_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_ports
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_ports`;

CREATE TABLE `server_ports` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) NOT NULL,
  `protocol` enum('tcp','udp') DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `state` enum('open','closed','filtered') DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `server_ports_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_snmp_profiles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_snmp_profiles`;

CREATE TABLE `server_snmp_profiles` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) NOT NULL,
  `snmp_version` enum('2c','3') NOT NULL,
  `community` varchar(100) DEFAULT NULL,
  `v3_user` varchar(100) DEFAULT NULL,
  `v3_auth_proto` enum('MD5','SHA') DEFAULT NULL,
  `v3_auth_pass` varchar(255) DEFAULT NULL,
  `v3_priv_proto` enum('DES','AES') DEFAULT NULL,
  `v3_priv_pass` varchar(255) DEFAULT NULL,
  `v3_sec_level` char(40) DEFAULT NULL,
  `polling_interval` int(11) DEFAULT 300,
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `server_snmp_profiles_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_system_info
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_system_info`;

CREATE TABLE `server_system_info` (
  `server_id` bigint(20) NOT NULL,
  `sys_descr` text DEFAULT NULL,
  `sys_name` varchar(150) DEFAULT NULL,
  `sys_location` varchar(150) DEFAULT NULL,
  `sys_contact` varchar(150) DEFAULT NULL,
  `sys_uptime` bigint(20) DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`server_id`),
  CONSTRAINT `server_system_info_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table server_traffic_history
# ------------------------------------------------------------

DROP TABLE IF EXISTS `server_traffic_history`;

CREATE TABLE `server_traffic_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) DEFAULT NULL,
  `if_name` varchar(100) DEFAULT NULL,
  `in_octets` bigint(20) DEFAULT NULL,
  `out_octets` bigint(20) DEFAULT NULL,
  `in_bps` bigint(20) DEFAULT NULL,
  `out_bps` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_if_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table servers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `servers`;

CREATE TABLE `servers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(150) DEFAULT NULL,
  `ip_public` varchar(45) DEFAULT NULL,
  `ip_local` varchar(45) DEFAULT NULL,
  `os` varchar(150) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `uptime` varchar(100) DEFAULT NULL,
  `cpu_cores` int(5) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table snmp_alerts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `snmp_alerts`;

CREATE TABLE `snmp_alerts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) DEFAULT NULL,
  `metric` varchar(100) DEFAULT NULL,
  `current_value` float DEFAULT NULL,
  `threshold` float DEFAULT NULL,
  `status` enum('warning','critical') DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_ack` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table snmp_poll_logs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `snmp_poll_logs`;

CREATE TABLE `snmp_poll_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_id` bigint(20) DEFAULT NULL,
  `status` enum('success','timeout','auth_fail','error') DEFAULT NULL,
  `message` text DEFAULT NULL,
  `execution_time` float DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table t_member
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_member`;

CREATE TABLE `t_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` char(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `tipe` char(50) DEFAULT 'USER',
  `kategori` char(50) DEFAULT 'USER',
  `nama` char(100) DEFAULT NULL,
  `telepon` char(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` char(50) DEFAULT NULL,
  `lastlogin` datetime DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` char(50) DEFAULT NULL,
  `device_uuid` varchar(255) DEFAULT NULL,
  `device_serial` varchar(255) DEFAULT NULL,
  `device_params` text DEFAULT NULL,
  `fcm_token` varchar(255) DEFAULT NULL,
  `lat` varchar(255) DEFAULT NULL,
  `lng` varchar(255) DEFAULT NULL,
  `valid_email` enum('Y','N') DEFAULT 'N',
  `valid_telepon` enum('Y','N') DEFAULT 'N',
  `tanggal_aktif` datetime DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `token_expire` datetime DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `otp_secret` varchar(128) DEFAULT NULL,
  `otp_key` varchar(128) DEFAULT NULL,
  `kota` char(60) DEFAULT NULL,
  `provinsi` char(40) DEFAULT NULL,
  `google_uid` varchar(250) DEFAULT NULL,
  `apikey` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `telepon` (`telepon`),
  UNIQUE KEY `email` (`email`) USING HASH,
  KEY `tipe` (`tipe`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table t_member_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_member_config`;

CREATE TABLE `t_member_config` (
  `id_member` bigint(20) unsigned NOT NULL,
  `module_access` longtext DEFAULT NULL,
  `log_access` longtext DEFAULT NULL,
  PRIMARY KEY (`id_member`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table z_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `z_config`;

CREATE TABLE `z_config` (
  `name` varchar(250) NOT NULL,
  `value` longtext DEFAULT NULL,
  PRIMARY KEY (`name`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Dump of table z_debug
# ------------------------------------------------------------

DROP TABLE IF EXISTS `z_debug`;

CREATE TABLE `z_debug` (
  `id` bigint(15) NOT NULL AUTO_INCREMENT,
  `tanggal` datetime DEFAULT NULL,
  `logtext` text DEFAULT NULL,
  `filename` varchar(250) DEFAULT NULL,
  `line` char(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
