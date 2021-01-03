-- phpMyAdmin SQL Dump
-- version 4.0.10.8
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jan 03, 2021 at 09:20 PM
-- Server version: 10.3.17-MariaDB
-- PHP Version: 7.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `tbl_account`
--

DROP TABLE IF EXISTS `tbl_account`;
CREATE TABLE IF NOT EXISTS `tbl_account` (
  `pk` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateOfEntry` date NOT NULL DEFAULT '0000-00-00',
  `dateOfRecord` date NOT NULL DEFAULT '0000-00-00',
  `account` tinyint(4) NOT NULL DEFAULT 1,
  `type` enum('deposit','withdraw') NOT NULL DEFAULT 'withdraw',
  `category` char(16) NOT NULL,
  `item` char(128) NOT NULL,
  `Amount` decimal(9,2) NOT NULL DEFAULT 0.00,
  `incTotal` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`pk`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
