-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 29, 2020 at 04:10 PM
-- Server version: 5.7.26
-- PHP Version: 7.3.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `_test_storm`
--

-- --------------------------------------------------------

--
-- Table structure for table `stocks_alert`
--

DROP TABLE IF EXISTS `stocks_alert`;
CREATE TABLE IF NOT EXISTS `stocks_alert` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `fk_stock` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_alert_fk_user` (`fk_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_alert`
--

INSERT INTO `stocks_alert` (`uuid`, `name`, `fk_stock`) VALUES
('test', 'test', 'AAPL'),
('test2', 'Test 2', 'AAPL'),
('test3', 'Test 3', 'IBM');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_autoincrement`
--

DROP TABLE IF EXISTS `stocks_autoincrement`;
CREATE TABLE IF NOT EXISTS `stocks_autoincrement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_autoincrement`
--

INSERT INTO `stocks_autoincrement` (`id`, `name`) VALUES
(73, '6');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_foo`
--

DROP TABLE IF EXISTS `stocks_foo`;
CREATE TABLE IF NOT EXISTS `stocks_foo` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_industry`
--

DROP TABLE IF EXISTS `stocks_industry`;
CREATE TABLE IF NOT EXISTS `stocks_industry` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `fk_type` varchar(32) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_industry`
--

INSERT INTO `stocks_industry` (`uuid`, `name`, `fk_type`) VALUES
('telecommunications-equipment', 'Telecommunications Equipment', 'id-0');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_sector`
--

DROP TABLE IF EXISTS `stocks_sector`;
CREATE TABLE IF NOT EXISTS `stocks_sector` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name_cz` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `performance` double DEFAULT NULL,
  `general` tinyint(1) NOT NULL,
  `fk_parent` varchar(36) COLLATE utf8_czech_ci DEFAULT NULL,
  `no_stocks` int(11) DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_sector_fk_parent` (`fk_parent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_sector`
--

INSERT INTO `stocks_sector` (`uuid`, `name_cz`, `name_en`, `performance`, `general`, `fk_parent`, `no_stocks`) VALUES
('consumer-durables', 'Consumer Durables', '0', NULL, 0, NULL, 137),
('electronic-technology', 'Electronic Technology', '0', NULL, 0, NULL, 360),
('energy', 'Energie', 'Energy', 10, 1, NULL, 0),
('finance', 'Finance', NULL, NULL, 0, NULL, 1981),
('health-technology', 'Health Technology', '0', NULL, 0, NULL, 872),
('materials', 'Materialy', 'Materials', 0, 10, NULL, 10),
('miscellaneous', 'Miscellaneous', '0', NULL, 0, NULL, 2865),
('technology-services', 'Technology Services', '0', NULL, 0, NULL, 404),
('utilities', 'u2-cz', 'u2-en', NULL, 0, NULL, 0),
('utilities_aux', 'u2-cz', 'u2-en', NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `stocks_stock`
--

DROP TABLE IF EXISTS `stocks_stock`;
CREATE TABLE IF NOT EXISTS `stocks_stock` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `currency` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `region` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL,
  `ipo_year` int(11) DEFAULT NULL,
  `website` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `description` text COLLATE utf8_czech_ci,
  `import_company` datetime DEFAULT NULL,
  `import_stats` datetime DEFAULT NULL,
  `import_financial` datetime DEFAULT NULL,
  `import_prices` datetime DEFAULT NULL,
  `issue_type` enum('ad','re','ce','si','lp','cs','et','wt','ut','struct','rt','oef','cef','ps') COLLATE utf8_czech_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `ceo` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `security_name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `marketcap` double DEFAULT NULL,
  `beta` double DEFAULT NULL,
  `iex_float` double DEFAULT NULL,
  `week52high` double DEFAULT NULL,
  `week52low` double DEFAULT NULL,
  `week52change` double DEFAULT NULL,
  `shares_outstanding` double DEFAULT NULL,
  `employees` double DEFAULT NULL,
  `avg30_volume` double DEFAULT NULL,
  `avg10_volume` double DEFAULT NULL,
  `ttm_eps` double DEFAULT NULL,
  `ttm_dividend_rate` double DEFAULT NULL,
  `dividend_yield` double DEFAULT NULL,
  `dividend_amount` double DEFAULT NULL,
  `next_dividend_rate` double DEFAULT NULL,
  `next_dividend_date` date DEFAULT NULL,
  `ex_dividend_date` date DEFAULT NULL,
  `next_earnings_date` date DEFAULT NULL,
  `pe_ratio` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `day200_moving_avg` double DEFAULT NULL,
  `day50_moving_avg` double DEFAULT NULL,
  `max_change_percent` double DEFAULT NULL,
  `year5_change_percent` double DEFAULT NULL,
  `year2_change_percent` double DEFAULT NULL,
  `year1_change_percent` double DEFAULT NULL,
  `ytd_change_percent` double DEFAULT NULL,
  `month1_change_percent` double DEFAULT NULL,
  `month6_change_percent` double DEFAULT NULL,
  `month3_change_percent` double DEFAULT NULL,
  `month2_change_percent` double DEFAULT NULL,
  `day30_change_percent` double DEFAULT NULL,
  `day5_change_percent` double DEFAULT NULL,
  `fk_industry` varchar(36) COLLATE utf8_czech_ci DEFAULT NULL,
  `fk_sector` varchar(36) COLLATE utf8_czech_ci DEFAULT NULL,
  `import_last_divident` date DEFAULT NULL,
  `import_last_report` date DEFAULT NULL,
  `import_last_price` date DEFAULT NULL,
  `import_dividents` datetime DEFAULT NULL,
  `import_first_divident` date DEFAULT NULL,
  `import_first_report` date DEFAULT NULL,
  `import_first_price` date DEFAULT NULL,
  `high_price` double DEFAULT NULL,
  `low_price` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `volume` double DEFAULT NULL,
  `previous_price` double DEFAULT NULL,
  `price_change` double DEFAULT NULL,
  `price_update_ts` timestamp NULL DEFAULT NULL,
  `import_last_earnings` date DEFAULT NULL,
  `import_first_earnings` date DEFAULT NULL,
  `import_earnings` datetime DEFAULT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ex_earnings_date` date DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_stock_fk_industry` (`fk_industry`),
  KEY `stocks_stock_fk_sector` (`fk_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_stock`
--

INSERT INTO `stocks_stock` (`uuid`, `name`, `currency`, `region`, `is_enabled`, `ipo_year`, `website`, `description`, `import_company`, `import_stats`, `import_financial`, `import_prices`, `issue_type`, `logo`, `ceo`, `security_name`, `marketcap`, `beta`, `iex_float`, `week52high`, `week52low`, `week52change`, `shares_outstanding`, `employees`, `avg30_volume`, `avg10_volume`, `ttm_eps`, `ttm_dividend_rate`, `dividend_yield`, `dividend_amount`, `next_dividend_rate`, `next_dividend_date`, `ex_dividend_date`, `next_earnings_date`, `pe_ratio`, `day200_moving_avg`, `day50_moving_avg`, `max_change_percent`, `year5_change_percent`, `year2_change_percent`, `year1_change_percent`, `ytd_change_percent`, `month1_change_percent`, `month6_change_percent`, `month3_change_percent`, `month2_change_percent`, `day30_change_percent`, `day5_change_percent`, `fk_industry`, `fk_sector`, `import_last_divident`, `import_last_report`, `import_last_price`, `import_dividents`, `import_first_divident`, `import_first_report`, `import_first_price`, `high_price`, `low_price`, `price`, `volume`, `previous_price`, `price_change`, `price_update_ts`, `import_last_earnings`, `import_first_earnings`, `import_earnings`, `created`, `ex_earnings_date`) VALUES
('A', 'Agilent Technologies Inc.', 'USD', 'US', 1, NULL, 'http://www.agilent.com', 'Agilent Technologies, Inc. engages in the provision of application focused solutions for life sciences, diagnostics, and applied chemical markets. It operates through the following segments: Life Sciences and Applied Markets, Diagnostics and Genomics, and Agilent CrossLab. The Life Sciences and Applied Markets segment offers application-focused solutions that include instruments and software that enable to identify, quantify, and analyze the physical and biological properties of substances and products, as well as the clinical and life sciences research areas to interrogate samples at the molecular and cellular level. The Diagnostics and Genomics segment comprises activity providing active pharmaceutical ingredients for oligo-based therapeutics as well as solutions that include reagents, instruments, software, and consumables. The Agilent CrossLab segment includes startup, operational, training and compliance support, software as a service, and asset management and consultative services. The company was founded in May 1999 and is headquartered in Santa Clara, CA.', '2019-08-18 12:30:56', '2019-08-21 09:19:15', '2019-04-06 09:01:02', '2019-10-03 05:00:14', 'cs', 'https://storage.googleapis.com/iex/api/logos/A.png', 'Michael R. McMullen', 'Agilent Technologies, Inc.', 22473422160, 1.081669, NULL, 82.27, 61.01, 0.103149, 315993000, 14800, 2967774.6, 2894077, 3.3825, 0.641, 0.009013, 0.164, NULL, NULL, '2019-07-01', '2019-11-18', '21.030000', 72.72, 70.86, -0.01, -0.01, -0.01, 0.112921, 0.092251, 0.038501, -0.086685, 0.040911, NULL, -0.023145, 0.060763, NULL, 'health-technology', '2019-07-01', '2019-01-31', '2019-10-01', '2019-08-02 11:05:58', '2019-04-01', '2016-01-31', '2019-10-01', 71.29, 70.6, 70.58, 825712, 69.94, 0.00915, '2019-10-01 21:59:00', '2019-08-14', '2018-08-14', '2019-08-20 14:36:52', '2019-09-09 05:08:13', '2019-08-14'),
('AADR', 'AdvisorShares Dorsey Wright ADR ETF', 'USD', 'US', 1, NULL, '', '', '2020-03-22 14:15:06', '2020-03-22 15:15:02', '2019-12-09 10:06:56', '2020-02-11 10:05:12', 'et', 'https://storage.googleapis.com/iexcloud-hl37opg/api/logos/AADR.png', '', 'AdvisorShares Dorsey Wright ADR ETF', 79402500, 0.898096, NULL, 57.51, 33.75, -0.23532, 2250000, NULL, 29592.2, 65720.2, NULL, 0.3152, 0.008932, 0, NULL, '2020-03-26', '2019-12-27', NULL, NULL, 50.46, 51.63, 0.4287, -10.86, -41.72, -24.29, -35.32, -37.91, -27.8, -34.15, NULL, -35.98, -6.89, 'not-exists', 'miscellaneous', '2020-03-26', NULL, '2020-02-07', '2020-03-22 10:15:05', '2014-12-24', NULL, '2014-04-07', 37.25, 35.26, 35.29, 14867, 35.67, -0.38, '2020-03-20 19:00:00', NULL, NULL, '2019-10-31 22:26:04', '2019-09-09 03:08:13', NULL),
('AAPL', 'Apple Inc.', 'USD', 'US', 1, NULL, 'http://www.apple.com', 'Apple, Inc. engages in designing, manufacturing, and marketing of mobile communication, media devices, personal computers, and portable digital music players. It operates through the following geographical segments: Americas, Europe, Greater China, Japan, and Rest of Asia Pacific. The Americas segment includes North and South America. The Europe segment consists of European countries, as well as India, the Middle East, and Africa. The Greater China segment comprises of China, Hong Kong, and Taiwan. The Rest of Asia Pacific segment includes Australia and Asian countries. Apple was founded by Steven Paul Jobs, Ronald Gerald Wayne, and Stephen G. Wozniak on April 1, 1976 and is headquartered in Cupertino, CA.', '2019-08-18 12:31:01', '2019-08-21 09:19:18', '2019-08-21 08:14:31', '2019-10-03 05:00:16', 'cs', 'https://storage.googleapis.com/iex/api/logos/AAPL.png', 'Timothy Donald Cook', 'Apple Inc.', 950654704800, 4.5, NULL, 233.47, 142, -0.02367, 4519180000, 132000, 27928999.23, 29961121.8, 11.83, 2.96, 0.014071, 0.77, NULL, NULL, '2019-08-09', '2019-10-31', '17.780000', 185.63, 202.36, 205.130117, 1.463761, 0.362525, -0.023717, 0.332004, 0.015105, 0.222752, 0.148889, NULL, 0.045269, 0.006604, 'telecommunications-equipment', 'electronic-technology', '2019-08-09', '2019-06-30', '2019-10-01', '2019-08-02 11:10:27', '2019-02-08', '2016-06-30', '2019-10-01', NULL, NULL, 219.13, 34884482, 224.59, -0.02431, '2019-10-01 21:59:00', '2019-07-30', '2018-07-31', '2019-08-20 14:36:42', '2019-09-09 05:08:13', '2019-07-30'),
('IBM', 'International Business Machines Corporation', 'USD', 'US', 1, NULL, 'http://www.ibm.com', 'Manufactures various computer products through the use of advanced information technology', '2019-04-05 13:08:33', '2019-08-21 09:32:21', '2019-09-06 11:14:33', '2019-10-03 05:00:09', 'cs', 'https://storage.googleapis.com/iex/api/logos/IBM.png', 'Virginia M. Rometty', 'International Business Machines Corporation', 117821375000, 0.995413, NULL, 154.36, 105.94, -0.092212, 885875000, 381100, 4475763.77, 4308915.9, 9.7217, 6.33, 0.047594, 1.62, NULL, NULL, '2019-08-08', '2019-10-16', '13.680000', 133.39, 140.95, 0.783056, -0.258426, -0.082664, -0.078288, 0.17212, -0.09817, -0.021449, -0.000592, NULL, -0.03079, -0.005523, NULL, 'technology-services', '2019-08-08', '2019-06-30', '2019-10-01', '2019-08-02 12:17:12', '2019-02-07', '2016-06-30', '2019-10-01', 135.28, 132.81, 133, 3018603, 135.04, -0.01511, '2019-10-01 21:59:00', '2019-07-17', '2018-07-18', '2019-08-20 14:51:09', '2019-09-09 05:08:13', '2019-07-17'),
('MS', 'Morgan Stanley', 'USD', 'US', 1, NULL, 'http://www.morganstanley.com', 'Provides diversified financial services including brokerage, investment management and venture capital services', '2019-04-05 13:33:43', '2019-08-21 09:36:38', NULL, '2019-10-03 05:00:12', 'cs', 'https://storage.googleapis.com/iex/api/logos/MS.png', 'James Patrick Gorman', 'Morgan Stanley', 65945523000, 1.211683, NULL, 50.42, 36.74, -0.173742, 1652770000, 60348, 10154386.87, 11124258.2, 4.65, 1.2, 0.030075, 0.35, NULL, NULL, '2019-07-30', '2019-10-15', '8.580000', 43.15, 43.02, 0.196939, 0.606397, 0.109019, -0.164009, -0.000743, -0.092809, -0.048326, -0.079781, NULL, -0.077889, -0.007864, NULL, 'finance', '2014-10-29', NULL, '2019-10-01', '2019-08-20 15:49:44', '2019-07-30', NULL, '2019-10-01', 40.29, 39.83, 39.9, 10883329, 40.37, -0.01164, '2019-10-01 21:59:00', '2019-07-18', '2018-07-18', '2019-08-20 14:55:58', '2019-09-09 05:08:13', '2019-07-18'),
('TSLA', 'Tesla Inc', 'USD', 'US', 1, NULL, 'http://www.tesla.com', 'Designs and manufactures electric sports cars', '2019-04-05 14:24:41', '2019-08-21 09:45:31', NULL, '2019-10-03 05:00:16', 'cs', 'https://storage.googleapis.com/iex/api/logos/TSLA.png', 'Elon Reeve Musk', 'Tesla Inc', 40457624220, 1.313743, NULL, 379.49, 176.99, -0.267734, 179127000, 48817, 7716360.57, 5596622.2, -3.7366, NULL, NULL, NULL, NULL, NULL, NULL, '2019-11-04', '-60.450000', 276.93, 232.4, 9.688154, 0.26814, -0.168788, -0.26459, -0.268573, -0.112836, -0.250297, 0.104548, NULL, -0.01404, -0.034766, NULL, 'consumer-durables', NULL, NULL, '2019-10-01', '2019-08-20 15:56:59', NULL, NULL, '2019-10-01', NULL, NULL, 230.05, 6099724, 244.69, -0.05983, '2019-10-01 21:59:00', '2019-07-24', '2018-08-01', '2019-08-20 15:05:23', '2019-09-09 05:08:13', '2019-07-24'),
('TSLA_aux', 'Tesla Inc', 'USD', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'consumer-durables', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2020-03-29 16:09:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stocks_stock_nxn_stocks_tag`
--

DROP TABLE IF EXISTS `stocks_stock_nxn_stocks_tag`;
CREATE TABLE IF NOT EXISTS `stocks_stock_nxn_stocks_tag` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `fk_stock` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `fk_tag` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_nxn_stock_tag_fk_stock` (`fk_stock`),
  KEY `stocks_nxn_stock_tag_fk_tag` (`fk_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_stock_nxn_stocks_tag`
--

INSERT INTO `stocks_stock_nxn_stocks_tag` (`uuid`, `fk_stock`, `fk_tag`, `value`) VALUES
('A_biotechnology', 'A', 'biotechnology', NULL),
('A_health-technology', 'A', 'health-technology', NULL),
('AAPL_telecommunications-equipment', 'AAPL', 'telecommunications-equipment', NULL),
('IBM_information-technology-services', 'IBM', 'information-technology-services', NULL),
('IBM_technology-services', 'IBM', 'technology-services', NULL),
('TSLA_consumer-durables', 'TSLA', 'consumer-durables', NULL),
('TSLA_motor-vehicles', 'TSLA', 'motor-vehicles', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stocks_tag`
--

DROP TABLE IF EXISTS `stocks_tag`;
CREATE TABLE IF NOT EXISTS `stocks_tag` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_tag`
--

INSERT INTO `stocks_tag` (`uuid`, `name`) VALUES
('advertising-marketing-services', 'Advertising/Marketing Services'),
('aerospace-defense', 'Aerospace & Defense'),
('agricultural-commodities-milling', 'Agricultural Commodities/Milling'),
('air-freight-couriers', 'Air Freight/Couriers'),
('airlines', 'Airlines'),
('alternative-power-generation', 'Alternative Power Generation'),
('aluminum', 'Aluminum'),
('apparel-footwear', 'Apparel/Footwear'),
('apparel-footwear-retail', 'Apparel/Footwear Retail'),
('auto-parts-oem', 'Auto Parts: OEM'),
('automotive-aftermarket', 'Automotive Aftermarket'),
('beverages-alcoholic', 'Beverages: Alcoholic'),
('beverages-non-alcoholic', 'Beverages: Non-Alcoholic'),
('biotechnology', 'Biotechnology'),
('broadcasting', 'Broadcasting'),
('building-products', 'Building Products'),
('cable-satellite-tv', 'Cable/Satellite TV'),
('casinos-gaming', 'Casinos/Gaming'),
('catalog-specialty-distribution', 'Catalog/Specialty Distribution'),
('coal', 'Coal'),
('commercial-printing-forms', 'Commercial Printing/Forms'),
('commercial-services', 'Commercial Services'),
('communications', 'Communications'),
('computer-communications', 'Computer Communications'),
('computer-peripherals', 'Computer Peripherals'),
('computer-processing-hardware', 'Computer Processing Hardware'),
('construction-materials', 'Construction Materials'),
('consumer-durables', 'Consumer Durables'),
('consumer-non-durables', 'Consumer Non-Durables'),
('consumer-services', 'Consumer Services'),
('consumer-sundries', 'Consumer Sundries'),
('containers-packaging', 'Containers/Packaging'),
('contract-drilling', 'Contract Drilling'),
('convert1', 'John Doe'),
('convert2', 'Jane Doe'),
('data-processing-services', 'Data Processing Services'),
('department-stores', 'Department Stores'),
('discount-stores', 'Discount Stores'),
('distribution-services', 'Distribution Services'),
('drugstore-chains', 'Drugstore Chains'),
('electric-utilities', 'Electric Utilities'),
('electrical-products', 'Electrical Products'),
('electronic-components', 'Electronic Components'),
('electronic-equipment-instruments', 'Electronic Equipment/Instruments'),
('electronic-production-equipment', 'Electronic Production Equipment'),
('electronic-technology', 'Electronic Technology'),
('electronics-appliance-stores', 'Electronics/Appliance Stores'),
('electronics-appliances', 'Electronics/Appliances'),
('electronics-distributors', 'Electronics Distributors'),
('energy-minerals', 'Energy Minerals'),
('engineering-construction', 'Engineering & Construction'),
('environmental-services', 'Environmental Services'),
('finance', 'Finance'),
('finance-rental-leasing', 'Finance/Rental/Leasing'),
('financial-conglomerates', 'Financial Conglomerates'),
('financial-publishing-services', 'Financial Publishing/Services'),
('food-distributors', 'Food Distributors'),
('food-major-diversified', 'Food: Major Diversified'),
('food-meat-fish-dairy', 'Food: Meat/Fish/Dairy'),
('food-retail', 'Food Retail'),
('food-specialty-candy', 'Food: Specialty/Candy'),
('forest-products', 'Forest Products'),
('gas-distributors', 'Gas Distributors'),
('health-services', 'Health Services'),
('health-technology', 'Health Technology'),
('home-furnishings', 'Home Furnishings'),
('home-improvement-chains', 'Home Improvement Chains'),
('homebuilding', 'Homebuilding'),
('hospital-nursing-management', 'Hospital/Nursing Management'),
('hotels-resorts-cruiselines', 'Hotels/Resorts/Cruiselines'),
('household-personal-care', 'Household/Personal Care'),
('chemicals-agricultural', 'Chemicals: Agricultural'),
('chemicals-major-diversified', 'Chemicals: Major Diversified'),
('chemicals-specialty', 'Chemicals: Specialty'),
('industrial-conglomerates', 'Industrial Conglomerates'),
('industrial-machinery', 'Industrial Machinery'),
('industrial-services', 'Industrial Services'),
('industrial-specialties', 'Industrial Specialties'),
('information-technology-services', 'Information Technology Services'),
('insurance-brokers-services', 'Insurance Brokers/Services'),
('integrated-oil', 'Integrated Oil'),
('internet-retail', 'Internet Retail'),
('internet-software-services', 'Internet Software/Services'),
('investment-banks-brokers', 'Investment Banks/Brokers'),
('investment-managers', 'Investment Managers'),
('investment-trusts-mutual-funds', 'Investment Trusts/Mutual Funds'),
('janedoe', 'Jane Doe 2'),
('johndoe', 'John Doe 2'),
('life-health-insurance', 'Life/Health Insurance'),
('major-banks', 'Major Banks'),
('major-telecommunications', 'Major Telecommunications'),
('managed-health-care', 'Managed Health Care'),
('marine-shipping', 'Marine Shipping'),
('media-conglomerates', 'Media Conglomerates'),
('medical-distributors', 'Medical Distributors'),
('medical-nursing-services', 'Medical/Nursing Services'),
('medical-specialties', 'Medical Specialties'),
('metal-fabrication', 'Metal Fabrication'),
('miscellaneous', 'Miscellaneous'),
('miscellaneous-commercial-services', 'Miscellaneous Commercial Services'),
('miscellaneous-manufacturing', 'Miscellaneous Manufacturing'),
('motor-vehicles', 'Motor Vehicles'),
('movies-entertainment', 'Movies/Entertainment'),
('multi-line-insurance', 'Multi-Line Insurance'),
('non-energy-minerals', 'Non-Energy Minerals'),
('office-equipment-supplies', 'Office Equipment/Supplies'),
('oil-gas-pipelines', 'Oil & Gas Pipelines'),
('oil-gas-production', 'Oil & Gas Production'),
('oil-refining-marketing', 'Oil Refining/Marketing'),
('oilfield-services-equipment', 'Oilfield Services/Equipment'),
('other-consumer-services', 'Other Consumer Services'),
('other-consumer-specialties', 'Other Consumer Specialties'),
('other-metals-minerals', 'Other Metals/Minerals'),
('other-transportation', 'Other Transportation'),
('packaged-software', 'Packaged Software'),
('personnel-services', 'Personnel Services'),
('pharmaceuticals-generic', 'Pharmaceuticals: Generic'),
('pharmaceuticals-major', 'Pharmaceuticals: Major'),
('pharmaceuticals-other', 'Pharmaceuticals: Other'),
('precious-metals', 'Precious Metals'),
('process-industries', 'Process Industries'),
('producer-manufacturing', 'Producer Manufacturing'),
('property-casualty-insurance', 'Property/Casualty Insurance'),
('publishing-books-magazines', 'Publishing: Books/Magazines'),
('publishing-newspapers', 'Publishing: Newspapers'),
('pulp-paper', 'Pulp & Paper'),
('railroads', 'Railroads'),
('real-estate-development', 'Real Estate Development'),
('real-estate-investment-trusts', 'Real Estate Investment Trusts'),
('recreational-products', 'Recreational Products'),
('regional-banks', 'Regional Banks'),
('restaurants', 'Restaurants'),
('retail-trade', 'Retail Trade'),
('savings-banks', 'Savings Banks'),
('semiconductors', 'Semiconductors'),
('services-to-the-health-industry', 'Services to the Health Industry'),
('specialty-insurance', 'Specialty Insurance'),
('specialty-stores', 'Specialty Stores'),
('specialty-telecommunications', 'Specialty Telecommunications'),
('steel', 'Steel'),
('technology-services', 'Technology Services'),
('telecommunications-equipment', 'Telecommunications Equipment'),
('textiles', 'Textiles'),
('tobacco', 'Tobacco'),
('tools-hardware', 'Tools & Hardware'),
('transportation', 'Transportation'),
('trucking', 'Trucking'),
('trucks-construction-farm-machinery', 'Trucks/Construction/Farm Machinery'),
('utilities', 'Utilities'),
('water-utilities', 'Water Utilities'),
('wholesale-distributors', 'Wholesale Distributors'),
('wireless-telecommunications', 'Wireless Telecommunications');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_test`
--

DROP TABLE IF EXISTS `stocks_test`;
CREATE TABLE IF NOT EXISTS `stocks_test` (
  `uuid` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `test` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `flag` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_test`
--

INSERT INTO `stocks_test` (`uuid`, `name`, `test`, `flag`) VALUES
('uuid0-testInsertRow', 'name0', 'UUID0-TESTINSERTROW', 'testInsertRow'),
('uuid0-testInsertRows', 'name0', 'test0', 'testInsertRows'),
('uuid0-testSyncRow', 'name0', 'altered2', 'testSyncRow'),
('uuid0-testSyncRows', 'altered1', 'test0', 'testSyncRows'),
('uuid1-testInsertRows', 'name1', 'test1', 'testInsertRows'),
('uuid1-testSyncRows', 'altered3', 'test1', 'testSyncRows'),
('uuid2-testInsertRows', 'name2', 'test2', 'testInsertRows'),
('uuid2-testSyncRows', 'name2', 'test2', 'testSyncRows'),
('uuid3-testInsertRows', 'name3', 'test3', 'testInsertRows'),
('uuid4-testInsertRows', 'name4', 'test4', 'testInsertRows'),
('uuid5-testInsertRows', 'name5', 'test5', 'testInsertRows'),
('uuid6-testInsertRows', 'name6', 'test6', 'testInsertRows'),
('uuid7-testInsertRows', 'name7', 'test7', 'testInsertRows'),
('uuid8-testInsertRows', 'name8', 'test8', 'testInsertRows');




-- --------------------------------------------------------

--
-- Table structure for table `stocks_test`
--

DROP TABLE IF EXISTS `tests`;
CREATE TABLE IF NOT EXISTS `tests` (
    `id` varchar(36) COLLATE utf8_czech_ci NOT NULL,
    PRIMARY KEY (`uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;-- --------------------------------------------------------

--
-- Table structure for table `stocks_type`
--

DROP TABLE IF EXISTS `stocks_type`;
CREATE TABLE IF NOT EXISTS `stocks_type` (
  `id` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `fk_sector` varchar(36) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stocks_type_fk_sector` (`fk_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_type`
--

INSERT INTO `stocks_type` (`id`, `name`, `fk_sector`) VALUES
('id-0', 'name0', 'energy'),
('id-1', 'name1', 'energy'),
('id-2', 'testx', 'energy');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_type2`
--

DROP TABLE IF EXISTS `stocks_type2`;
CREATE TABLE IF NOT EXISTS `stocks_type2` (
  `id` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `fk_sector` varchar(36) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stocks_type_fk_sector` (`fk_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Dumping data for table `stocks_type2`
--

INSERT INTO `stocks_type2` (`id`, `name`, `fk_sector`) VALUES
('id-0', 'name0', 'energy'),
('id-1', 'name1', 'energy'),
('id-2', 'testx', 'energy');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `stocks_sector`
--
ALTER TABLE `stocks_sector`
  ADD CONSTRAINT `stocks_sector_fk_parent` FOREIGN KEY (`fk_parent`) REFERENCES `stocks_sector` (`uuid`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `stocks_stock`
--
ALTER TABLE `stocks_stock`
  ADD CONSTRAINT `stocks_stock_fk_sector` FOREIGN KEY (`fk_sector`) REFERENCES `stocks_sector` (`uuid`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `stocks_stock_nxn_stocks_tag`
--
ALTER TABLE `stocks_stock_nxn_stocks_tag`
  ADD CONSTRAINT `stocks_stock_fk_stock` FOREIGN KEY (`fk_stock`) REFERENCES `stocks_stock` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `stocks_stock_fk_tag` FOREIGN KEY (`fk_tag`) REFERENCES `stocks_tag` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
