-- --------------------------------------------------------
-- Host:                         192.168.200.8
-- Server versie:                5.5.34-log - MySQL Community Server (GPL) by Remi
-- Server OS:                    Linux
-- HeidiSQL Versie:              8.1.0.4545
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Structuur van  tabel nrctweets.facebook wordt geschreven
CREATE TABLE IF NOT EXISTS `facebook` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `art_id` bigint(20) NOT NULL DEFAULT '0',
  `share_count` int(10) NOT NULL DEFAULT '0',
  `comment_count` int(10) NOT NULL DEFAULT '0',
  `like_count` int(10) NOT NULL DEFAULT '0',
  `total_count` int(10) NOT NULL DEFAULT '0',
  `click_count` int(10) NOT NULL DEFAULT '0',
  `last_crawl` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `art_id` (`art_id`),
  KEY `last_crawl` (`last_crawl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Facebook informatie over een artikel';

-- Data exporteren was gedeselecteerd
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
