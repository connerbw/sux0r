-- phpMyAdmin SQL Dump
-- version 2.10.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 29, 2008 at 11:29 AM
-- Server version: 5.0.41
-- PHP Version: 5.2.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `sux0r`
--

-- --------------------------------------------------------

--
-- Table structure for table `bayes_categories`
--

CREATE TABLE `bayes_categories` (
  `id` int(11) NOT NULL auto_increment,
  `category` varchar(64) NOT NULL,
  `bayes_vectors_id` int(11) NOT NULL,
  `probability` double NOT NULL default '0',
  `token_count` bigint(20) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `grouping` (`category`,`bayes_vectors_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `bayes_categories`
--


-- --------------------------------------------------------

--
-- Table structure for table `bayes_documents`
--

CREATE TABLE `bayes_documents` (
  `id` int(11) NOT NULL auto_increment,
  `bayes_categories_id` int(11) NOT NULL,
  `body_plaintext` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `bayes_categories_id` (`bayes_categories_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `bayes_documents`
--


-- --------------------------------------------------------

--
-- Table structure for table `bayes_tokens`
--

CREATE TABLE `bayes_tokens` (
  `id` int(11) NOT NULL auto_increment,
  `token` varchar(64) NOT NULL,
  `bayes_categories_id` int(11) NOT NULL,
  `count` bigint(20) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `grouping` (`token`,`bayes_categories_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `bayes_tokens`
--


-- --------------------------------------------------------

--
-- Table structure for table `bayes_vectors`
--

CREATE TABLE `bayes_vectors` (
  `id` int(11) NOT NULL auto_increment,
  `vector` varchar(64) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `bayes_vectors`
--


-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description_html` text,
  `description_plaintext` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `bookmarks`
--


-- --------------------------------------------------------

--
-- Table structure for table `calendar`
--

CREATE TABLE `calendar` (
  `id` int(11) NOT NULL auto_increment,
  `summary` varchar(255) NOT NULL,
  `description_html` text,
  `description_plaintext` text,
  `location` text,
  `url` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `calendar`
--


-- --------------------------------------------------------

--
-- Table structure for table `calendar_dates`
--

CREATE TABLE `calendar_dates` (
  `id` int(11) NOT NULL auto_increment,
  `calendar_id` int(11) NOT NULL,
  `dtstart` datetime NOT NULL,
  `dtend` datetime default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `calendar_dates`
--


-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL auto_increment,
  `users_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_plaintext` text NOT NULL,
  `thread_id` int(11) NOT NULL,
  `parent_id` int(11) default NULL,
  `level` int(11) NOT NULL,
  `thread_pos` int(11) NOT NULL,
  `published_on` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `thread_id` (`thread_id`),
  KEY `users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `messages`
--


-- --------------------------------------------------------

--
-- Table structure for table `messages_history`
--

CREATE TABLE `messages_history` (
  `id` int(11) NOT NULL auto_increment,
  `messages_id` int(11) NOT NULL,
  `users_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_plaintext` text NOT NULL,
  `edited_on` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `messages_id` (`messages_id`),
  KEY `users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `messages_history`
--


-- --------------------------------------------------------

--
-- Table structure for table `openid_secrets`
--

CREATE TABLE `openid_secrets` (
  `id` int(11) NOT NULL auto_increment,
  `expiration` int(11) NOT NULL,
  `shared_secret` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `openid_secrets`
--


-- --------------------------------------------------------

--
-- Table structure for table `openid_trusted`
--

CREATE TABLE `openid_trusted` (
  `id` int(11) NOT NULL auto_increment,
  `auth_url` varchar(255) NOT NULL,
  `users_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `authorized` (`auth_url`,`users_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `openid_trusted`
--


-- --------------------------------------------------------

--
-- Table structure for table `rolodex`
--

CREATE TABLE `rolodex` (
  `id` int(11) NOT NULL,
  `organization_name` varchar(255) NOT NULL,
  `organization_unit` varchar(255) default NULL,
  `post_office_box` varchar(255) default NULL,
  `extended_address` varchar(255) default NULL,
  `street_address` varchar(255) default NULL,
  `locality` varchar(255) default NULL,
  `region` varchar(255) default NULL,
  `postal_code` varchar(255) default NULL,
  `country_name` varchar(255) default NULL,
  `tel` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `url` varchar(255) default NULL,
  `photo` varchar(255) default NULL,
  `latitude` varchar(255) default NULL,
  `longitude` varchar(255) default NULL,
  `note` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `rolodex`
--


-- --------------------------------------------------------

--
-- Table structure for table `socialnetwork`
--

CREATE TABLE `socialnetwork` (
  `id` int(11) NOT NULL auto_increment,
  `users_id` int(11) NOT NULL,
  `friend_users_id` int(11) NOT NULL,
  `relationship` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `friendship` (`users_id`,`friend_users_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `socialnetwork`
--


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `nickname` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `accesslevel` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `nickname` (`nickname`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` VALUES (1, 'test', 'test@test.com', '24d7d9859810e5834bbfdcc9dd931fca', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users_info`
--

CREATE TABLE `users_info` (
  `id` int(11) NOT NULL auto_increment,
  `users_id` int(11) NOT NULL,
  `given_name` varchar(255) default NULL,
  `family_name` varchar(255) default NULL,
  `street_address` varchar(255) default NULL,
  `locality` varchar(255) default NULL,
  `region` varchar(255) default NULL,
  `postcode` varchar(255) default NULL,
  `country` char(2) default NULL,
  `tel` varchar(255) default NULL,
  `url` varchar(255) default NULL,
  `dob` date default NULL,
  `gender` char(1) default NULL,
  `language` char(2) default NULL,
  `timezone` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `users_info`
--

INSERT INTO `users_info` VALUES (1, 1, 'Test', 'Testing', '', '', '', '', 'ca', '', '', NULL, NULL, 'en', 'America/Montreal', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users_openid`
--

CREATE TABLE `users_openid` (
  `id` int(11) NOT NULL auto_increment,
  `openid_url` varchar(255) NOT NULL,
  `users_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `openid_url` (`openid_url`),
  KEY `users_id` (`users_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `users_openid`
--
