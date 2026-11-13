/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `about_us_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `about_us_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `about_us_sections` WRITE;
/*!40000 ALTER TABLE `about_us_sections` DISABLE KEYS */;
INSERT INTO `about_us_sections` VALUES (3,8,'686e10190e93f.png','- Welcome to Tica -','Revolutionizing Events Across Africa','<p data-start=\"244\" data-end=\"571\">At <strong data-start=\"247\" data-end=\"255\">Tica</strong>, we believe every event should be <strong data-start=\"290\" data-end=\"304\">accessible</strong>, <strong data-start=\"306\" data-end=\"319\">efficient</strong>, and <strong data-start=\"325\" data-end=\"342\">unforgettable</strong>. As a proudly Nigerian-born ticketing and event management platform, our mission is to reshape how people across Africa discover, organize, and attend events &mdash; from concerts and conferences to festivals, webinars, and workshops.</p>\r\n<h2 data-start=\"578\" data-end=\"596\"><span style=\"font-size: 14pt;\"><strong>Our Story</strong></span></h2>\r\n<p data-start=\"597\" data-end=\"865\">Tica was founded to bridge the gap between passionate event organizers and eager attendees. Born out of a need for <strong data-start=\"712\" data-end=\"726\">simplicity</strong>, <strong data-start=\"728\" data-end=\"740\">security</strong>, and <strong data-start=\"746\" data-end=\"768\">digital innovation</strong>, we created a platform that doesn&rsquo;t just sell tickets &mdash; it enhances the entire event experience.</p>\r\n<p data-start=\"867\" data-end=\"1133\">As Africa&rsquo;s event culture continues to grow, we&rsquo;re building tools to match that energy. Whether you&rsquo;re hosting a local gig or managing a large-scale festival, Tica gives you the power to reach your audience, sell tickets effortlessly, and run events with confidence.</p>','2021-12-19 06:23:27','2025-07-09 07:10:18');
/*!40000 ALTER TABLE `about_us_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `action_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `background_image` varchar(255) NOT NULL,
  `first_title` varchar(255) DEFAULT NULL,
  `second_title` varchar(255) DEFAULT NULL,
  `first_button` varchar(255) DEFAULT NULL,
  `first_button_url` varchar(255) DEFAULT NULL,
  `second_button` varchar(255) DEFAULT NULL,
  `second_button_url` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `action_sections` WRITE;
/*!40000 ALTER TABLE `action_sections` DISABLE KEYS */;
INSERT INTO `action_sections` VALUES (3,8,'61a6fe5929b63.jpg','Are You Ready for This Offer?','50% Offer for Very First 50 Students and Mentors.','Become A Student','https://codecanyon.kreativdev.com/coursela/user/signup','All Courses','','6280a19f2edad.png','2021-11-30 22:47:21','2022-05-15 00:45:51');
/*!40000 ALTER TABLE `action_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_username_unique` (`username`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_role_id_foreign` (`role_id`),
  CONSTRAINT `admins_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `role_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `advertisements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `advertisements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ad_type` varchar(255) NOT NULL,
  `resolution_type` smallint(5) unsigned NOT NULL COMMENT '1 => 300 x 250, 2 => 300 x 600, 3 => 728 x 90',
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `slot` varchar(50) DEFAULT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `advertisements` WRITE;
/*!40000 ALTER TABLE `advertisements` DISABLE KEYS */;
INSERT INTO `advertisements` VALUES (9,'banner',1,'686e9039cfa38.png','https://tica.ng/',NULL,0,'2021-08-15 23:12:31','2025-07-09 14:52:25');
INSERT INTO `advertisements` VALUES (10,'banner',2,'64577e6649d8a.png','https://tica.ng/',NULL,2,'2021-08-15 23:13:44','2025-07-09 14:51:52');
INSERT INTO `advertisements` VALUES (11,'banner',2,'686e900453e44.png','https://tica.ng/',NULL,1,'2021-08-15 23:15:14','2025-07-09 14:51:32');
INSERT INTO `advertisements` VALUES (12,'banner',1,'64577e4d3d1b3.png','https://tica.ng/',NULL,0,'2021-08-15 23:16:41','2025-09-14 21:47:47');
INSERT INTO `advertisements` VALUES (14,'banner',3,'686e8fa9b6664.png','https://tica.ng/',NULL,2,'2022-05-17 08:30:56','2025-07-09 14:50:01');
INSERT INTO `advertisements` VALUES (15,'banner',1,'686e8fc12838e.png','https://tica.ng/',NULL,0,'2022-05-17 08:31:36','2025-09-14 21:47:36');
INSERT INTO `advertisements` VALUES (16,'banner',3,'686e8f90723dc.png','https://tica.ng/',NULL,0,'2022-07-18 05:47:26','2025-07-09 14:49:36');
/*!40000 ALTER TABLE `advertisements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `basic_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `basic_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uniqid` int(10) unsigned NOT NULL DEFAULT 12345,
  `favicon` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website_title` varchar(255) DEFAULT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(8,5) DEFAULT NULL,
  `longitude` decimal(8,5) DEFAULT NULL,
  `theme_version` smallint(5) unsigned NOT NULL,
  `base_currency_symbol` varchar(255) DEFAULT NULL,
  `base_currency_symbol_position` varchar(20) DEFAULT NULL,
  `base_currency_text` varchar(20) DEFAULT NULL,
  `base_currency_text_position` varchar(20) DEFAULT NULL,
  `base_currency_rate` decimal(8,2) DEFAULT NULL,
  `primary_color` varchar(30) DEFAULT NULL,
  `breadcrumb_overlay_color` varchar(30) DEFAULT NULL,
  `breadcrumb_overlay_opacity` decimal(4,2) DEFAULT NULL,
  `smtp_status` tinyint(4) DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `encryption` varchar(50) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `from_mail` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `breadcrumb` varchar(255) DEFAULT NULL,
  `disqus_status` tinyint(3) unsigned DEFAULT NULL,
  `disqus_short_name` varchar(255) DEFAULT NULL,
  `google_recaptcha_status` tinyint(4) DEFAULT NULL,
  `google_recaptcha_site_key` varchar(255) DEFAULT NULL,
  `google_recaptcha_secret_key` varchar(255) DEFAULT NULL,
  `facebook_login_status` int(11) DEFAULT 0,
  `facebook_app_id` varchar(255) DEFAULT NULL,
  `facebook_app_secret` varchar(255) DEFAULT NULL,
  `google_login_status` int(11) DEFAULT 0,
  `google_client_id` varchar(255) DEFAULT NULL,
  `google_client_secret` varchar(255) DEFAULT NULL,
  `whatsapp_status` tinyint(3) unsigned DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `whatsapp_header_title` varchar(255) DEFAULT NULL,
  `whatsapp_popup_status` tinyint(3) unsigned DEFAULT NULL,
  `whatsapp_popup_message` text DEFAULT NULL,
  `maintenance_img` varchar(255) DEFAULT NULL,
  `maintenance_status` tinyint(4) DEFAULT NULL,
  `maintenance_msg` text DEFAULT NULL,
  `bypass_token` varchar(255) DEFAULT NULL,
  `footer_logo` varchar(255) DEFAULT NULL,
  `preloader` varchar(255) DEFAULT NULL,
  `admin_theme_version` varchar(10) NOT NULL DEFAULT 'light',
  `features_section_image` varchar(255) DEFAULT NULL,
  `testimonials_section_image` varchar(255) DEFAULT NULL,
  `course_categories_section_image` varchar(255) DEFAULT NULL,
  `notification_image` varchar(255) DEFAULT NULL,
  `google_adsense_publisher_id` varchar(255) DEFAULT NULL,
  `shop_status` tinyint(4) DEFAULT 1 COMMENT '1 - active, 0 - deactive',
  `catalog_mode` tinyint(4) DEFAULT 1 COMMENT '1 - active, 0 - deactive',
  `is_shop_rating` tinyint(4) DEFAULT 1 COMMENT '1 - active, 0 - deactive',
  `shop_guest_checkout` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 - active, 0 - deactive',
  `shop_tax` float DEFAULT NULL,
  `tax` double(8,2) DEFAULT 0.00,
  `commission` double(8,2) DEFAULT 0.00,
  `organizer_email_verification` int(11) NOT NULL DEFAULT 0,
  `organizer_admin_approval` int(11) NOT NULL DEFAULT 0,
  `admin_approval_notice` longtext DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_guest_checkout_status` int(11) DEFAULT 0 COMMENT '0=deactive, 1=active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `basic_settings` WRITE;
/*!40000 ALTER TABLE `basic_settings` DISABLE KEYS */;
INSERT INTO `basic_settings` VALUES (2,12345,'69dbf8624ec69.png','686e03258fc66.png','Tica Ng','hello@tica.ng','+2347073797999','Owerri , Nigeria',34.05224,-118.24368,1,'₦','left','NGN','right',1650.00,'0011FF','030A15',0.80,1,'mail.tica.ng',587,'tls',NULL,NULL,'tickets@tica.ng','Tica Events','support@tica.ng','686e916f9088d.png',0,'Tica',0,NULL,NULL,0,'643057404544999',NULL,0,NULL,NULL,1,'+880 1686321-356','Hi, there!',1,'If you have any issues, let us know.','1632725312.png',0,'We are upgrading our site. We will come back soon. \r\nPlease stay with us.\r\nThank you.',NULL,'686e921a1dfb9.png','63cbb14274c51.gif','dark','1633502472.jpg','61bf1ed024d95.png','61bf1fc25a8f6.jpg','619b7d5e5e9df.png','',0,0,0,0,5,100.00,4.00,1,0,'Your account is deactivated or pending now please get in touch with admin.','Africa/Lagos','2025-11-29 16:13:30',1);
/*!40000 ALTER TABLE `basic_settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  `serial_number` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blog_categories_language_id_foreign` (`language_id`),
  CONSTRAINT `blog_categories_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
INSERT INTO `blog_categories` VALUES (36,8,'Business','business',1,1,'2021-10-12 22:51:29','2023-05-07 10:14:18');
INSERT INTO `blog_categories` VALUES (37,8,'Conference','conference',1,2,'2021-10-12 22:51:38','2023-05-07 10:14:01');
INSERT INTO `blog_categories` VALUES (38,8,'Wedding','wedding',1,3,'2021-10-12 22:51:52','2023-05-11 04:34:57');
INSERT INTO `blog_categories` VALUES (43,8,'Others','others',1,4,'2022-04-05 05:50:10','2022-05-15 03:12:27');
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `blog_informations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_informations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `blog_category_id` bigint(20) unsigned NOT NULL,
  `blog_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blog_informations_language_id_foreign` (`language_id`),
  KEY `blog_informations_blog_category_id_foreign` (`blog_category_id`),
  KEY `blog_informations_blog_id_foreign` (`blog_id`),
  CONSTRAINT `blog_informations_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_informations_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_informations_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `blog_informations` WRITE;
/*!40000 ALTER TABLE `blog_informations` DISABLE KEYS */;
INSERT INTO `blog_informations` VALUES (33,8,36,19,'Morbi in sem quis dui placerat ornare. Pellentesque odio nisi','morbi-in-sem-quis-dui-placerat-ornare.-pellentesque-odio-nisi','Maximilian','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 03:33:13');
INSERT INTO `blog_informations` VALUES (35,8,37,20,'Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris','donec-nec-justo-eget-felis-facilisis-fermentum.-aliquam-porttitor-mauris','Donnarumma','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 03:47:02');
INSERT INTO `blog_informations` VALUES (37,8,38,21,'Phasellus ultrices nulla quis nibh. Quisque a lectus','phasellus-ultrices-nulla-quis-nibh.-quisque-a-lectus','Gianluca','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 03:58:30');
INSERT INTO `blog_informations` VALUES (39,8,43,22,'Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus','nam-dui-mi-tincidunt-quis-accumsan-porttitor-facilisis-luctus-metus','Arnold','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 03:58:30');
INSERT INTO `blog_informations` VALUES (41,8,36,23,'Vestibulum commodo felis quis tortor.','vestibulum-commodo-felis-quis-tortor.','Modric','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 04:04:54');
INSERT INTO `blog_informations` VALUES (43,8,37,24,'Vivamus vestibulum ntulla nec ante.','vivamus-vestibulum-ntulla-nec-ante.','Karem','<p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Donec odio. Quisque volutpat mattis eros. Nullam malesuada erat ut turpis. Suspendisse urna nibh, viverra non, semper suscipit, posuere a, pede.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.</p><ul><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Aliquam tincidunt mauris eu risus.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Vestibulum auctor dapibus neque.</li><li style=\"font-style:inherit;font-weight:inherit;font-size:18px;line-height:28px;\">Nunc dignissim risus id metus.</li></ul><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.</p><p style=\"font-size:18px;line-height:26px;font-family:stolzl, sans-serif;\">Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.</p>',NULL,NULL,'2022-05-15 03:33:13','2022-05-15 04:07:12');
/*!40000 ALTER TABLE `blog_informations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blogs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) NOT NULL,
  `serial_number` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `blogs` WRITE;
/*!40000 ALTER TABLE `blogs` DISABLE KEYS */;
INSERT INTO `blogs` VALUES (19,'6280c8d9b530c.jpg',1,'2022-05-15 03:33:13','2022-05-15 03:33:13');
INSERT INTO `blogs` VALUES (20,'6280cc169c6a3.jpg',2,'2022-05-15 03:33:13','2022-05-15 03:47:02');
INSERT INTO `blogs` VALUES (21,'6280cec65474f.jpg',3,'2022-05-15 03:33:13','2022-05-15 03:58:30');
INSERT INTO `blogs` VALUES (22,'6280cf79b89b3.jpg',4,'2022-05-15 03:33:13','2022-05-15 04:01:29');
INSERT INTO `blogs` VALUES (23,'6280d0469ef47.jpg',5,'2022-05-15 03:33:13','2022-05-15 04:04:54');
INSERT INTO `blogs` VALUES (24,'6280d0d0a5182.jpg',6,'2022-05-15 03:33:13','2022-05-15 04:07:12');
/*!40000 ALTER TABLE `blogs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(255) DEFAULT NULL,
  `booking_id` varchar(255) DEFAULT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `organizer_id` bigint(20) DEFAULT NULL,
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `variation` text DEFAULT NULL,
  `price` float(8,2) DEFAULT NULL,
  `quantity` varchar(255) DEFAULT NULL,
  `discount` float DEFAULT NULL,
  `tax` float(8,2) DEFAULT 0.00,
  `commission` float(8,2) DEFAULT 0.00,
  `early_bird_discount` float DEFAULT NULL,
  `currencyText` varchar(255) DEFAULT NULL,
  `currencyTextPosition` varchar(255) DEFAULT NULL,
  `currencySymbol` varchar(255) DEFAULT NULL,
  `currencySymbolPosition` varchar(255) DEFAULT NULL,
  `paymentMethod` varchar(255) DEFAULT NULL,
  `gatewayType` varchar(255) DEFAULT NULL,
  `paymentStatus` varchar(255) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `attachmentFile` varchar(255) DEFAULT NULL,
  `event_date` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tax_percentage` double(8,2) DEFAULT 0.00,
  `commission_percentage` double(8,2) DEFAULT 0.00,
  `scan_status` int(11) NOT NULL DEFAULT 0 COMMENT '1=scanned, 0 = not scan yet',
  `conversation_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `state_id` mediumint(8) unsigned NOT NULL,
  `state_code` varchar(255) NOT NULL,
  `country_id` mediumint(8) unsigned NOT NULL,
  `country_code` char(2) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '2014-01-01 00:31:01',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `flag` tinyint(1) NOT NULL DEFAULT 1,
  `wikiDataId` varchar(255) DEFAULT NULL COMMENT 'Rapid API GeoDB Cities',
  PRIMARY KEY (`id`),
  KEY `cities_test_ibfk_1` (`state_id`),
  KEY `cities_test_ibfk_2` (`country_id`),
  CONSTRAINT `cities_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `cities_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cities` WRITE;
/*!40000 ALTER TABLE `cities` DISABLE KEYS */;
/*!40000 ALTER TABLE `cities` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `contact_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_form_title` varchar(255) DEFAULT NULL,
  `contact_form_subtitle` text DEFAULT NULL,
  `contact_addresses` text DEFAULT NULL,
  `contact_numbers` varchar(255) DEFAULT NULL,
  `contact_mails` text DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `map_zoom` varchar(255) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `contact_pages` WRITE;
/*!40000 ALTER TABLE `contact_pages` DISABLE KEYS */;
INSERT INTO `contact_pages` VALUES (1,'Contact Us','Contact Us','Owerri , Nigeria','07073797999','Support@tica.ng,Tickets@tica.ng','23.8698828','38.804826','-104.819859',8,'2022-07-17 05:00:10','2025-08-26 18:27:14');
/*!40000 ALTER TABLE `contact_pages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL COMMENT '1=user, 2=admin, 3=organizer',
  `support_ticket_id` int(11) DEFAULT NULL,
  `reply` longtext DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cookie_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `cookie_alert_status` tinyint(3) unsigned NOT NULL,
  `cookie_alert_btn_text` varchar(255) NOT NULL,
  `cookie_alert_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cookie_alerts_language_id_foreign` (`language_id`),
  CONSTRAINT `cookie_alerts_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cookie_alerts` WRITE;
/*!40000 ALTER TABLE `cookie_alerts` DISABLE KEYS */;
INSERT INTO `cookie_alerts` VALUES (1,8,1,'I Agree','<p><span class=\"Message__Text-sc-591ebf54-3 bfIDoO eapp-cookie-consent-message-textInner\">We use cookie to improve your experience on our site. By using our site you consent cookies.&nbsp;</span><a class=\"Message__PrivacyPolicyLink-sc-591ebf54-1 juOeOx eapp-cookie-consent-message-componentMore\" tabindex=\"0\" role=\"link\" href=\"../../privacy-policy/\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"Learn more\">Learn more</a></p>','2021-06-02 06:25:54','2025-11-10 11:04:56');
/*!40000 ALTER TABLE `cookie_alerts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `count_informations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `count_informations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `serial_number` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `count_informations` WRITE;
/*!40000 ALTER TABLE `count_informations` DISABLE KEYS */;
INSERT INTO `count_informations` VALUES (5,8,'fas fa-user-friends','24FFCD','Qualified Instructors',20,1,'2021-10-11 01:20:18','2022-05-15 00:17:03');
INSERT INTO `count_informations` VALUES (6,8,'fas fa-globe','FFAB74','Worldwide Students',1490,2,'2021-10-11 01:20:47','2021-12-19 04:44:42');
INSERT INTO `count_informations` VALUES (7,8,'fas fa-book-reader','00FCFF','Courses',100,3,'2021-10-11 01:21:31','2021-12-19 04:45:36');
INSERT INTO `count_informations` VALUES (8,8,'fas fa-calendar-alt','FFC924','Years\' Experience',10,4,'2021-10-11 01:21:55','2021-12-19 04:46:07');
/*!40000 ALTER TABLE `count_informations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `iso3` char(3) DEFAULT NULL,
  `numeric_code` char(3) DEFAULT NULL,
  `iso2` char(2) DEFAULT NULL,
  `phonecode` varchar(255) DEFAULT NULL,
  `capital` varchar(255) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `currency_name` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(255) DEFAULT NULL,
  `tld` varchar(255) DEFAULT NULL,
  `native` varchar(255) DEFAULT NULL,
  `region` varchar(255) DEFAULT NULL,
  `subregion` varchar(255) DEFAULT NULL,
  `timezones` text DEFAULT NULL,
  `translations` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `emoji` varchar(191) DEFAULT NULL,
  `emojiU` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `flag` tinyint(1) NOT NULL DEFAULT 1,
  `wikiDataId` varchar(255) DEFAULT NULL COMMENT 'Rapid API GeoDB Cities',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `events` varchar(255) DEFAULT NULL,
  `start_date` varchar(255) NOT NULL,
  `end_date` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) DEFAULT NULL,
  `provider_id` varchar(255) DEFAULT NULL,
  `fname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `email_verified_at` varchar(255) DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `earnings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_earning` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `earnings` WRITE;
/*!40000 ALTER TABLE `earnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `earnings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `language_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `serial_number` mediumint(9) NOT NULL,
  `is_featured` char(4) NOT NULL DEFAULT 'no',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_categories` WRITE;
/*!40000 ALTER TABLE `event_categories` DISABLE KEYS */;
INSERT INTO `event_categories` VALUES (24,'Campus & Youth Events',8,'64562c6444649.png','campus-&-youth-events',1,2,'yes','2023-05-06 14:01:00','2025-07-12 19:53:15');
INSERT INTO `event_categories` VALUES (26,'Faith-Based Events',8,'64562cf6593fc.png','faith-based-events',1,3,'yes','2023-05-06 14:03:26','2025-07-12 19:52:46');
INSERT INTO `event_categories` VALUES (28,'Conferences & Seminars',8,'6872cd187cd8d.png','conferences-&-seminars',1,4,'yes','2023-05-06 14:04:29','2025-07-12 20:01:12');
INSERT INTO `event_categories` VALUES (33,'Concerts & Shows',8,'6872ccc23e5a7.png','concerts-&-shows',1,1,'yes','2025-07-12 19:59:46','2025-07-12 19:59:46');
INSERT INTO `event_categories` VALUES (34,'Parties & Nightlife',8,'6872cd9c449b7.png','parties-&-nightlife',1,5,'yes','2025-07-12 20:03:24','2025-07-12 20:03:24');
INSERT INTO `event_categories` VALUES (35,'Arts & Culture',8,'6872ce160210a.png','arts-&-culture',1,6,'yes','2025-07-12 20:05:26','2025-07-12 20:05:26');
INSERT INTO `event_categories` VALUES (37,'Sports & Fitness',8,'69f0ae3ff2219.php','sports-&-fitness',1,7,'yes','2025-07-12 20:06:13','2026-04-28 17:55:27');
/*!40000 ALTER TABLE `event_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `event_category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `meta_description` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(255) DEFAULT NULL,
  `google_calendar_id` varchar(255) DEFAULT NULL,
  `refund_policy` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_contents` WRITE;
/*!40000 ALTER TABLE `event_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_dates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) DEFAULT NULL,
  `start_date` varchar(255) DEFAULT NULL,
  `start_time` varchar(255) DEFAULT NULL,
  `end_date` varchar(255) DEFAULT NULL,
  `end_time` varchar(255) DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `start_date_time` varchar(255) DEFAULT NULL,
  `end_date_time` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_dates` WRITE;
/*!40000 ALTER TABLE `event_dates` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_dates` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_feature_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_feature_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_feature_sections` WRITE;
/*!40000 ALTER TABLE `event_feature_sections` DISABLE KEYS */;
INSERT INTO `event_feature_sections` VALUES (1,8,'Smarter Ticketing, Better Experiences','Discover how our platform makes event booking seamless, secure, and tailored to your needs.','2022-06-06 23:24:33','2025-07-09 05:05:16');
INSERT INTO `event_feature_sections` VALUES (2,9,'test arabic sdf','text arabic fdsa','2022-06-06 23:25:08','2022-06-06 23:25:16');
INSERT INTO `event_feature_sections` VALUES (3,17,'ميزة الأحداث الرائعة','صفحة التي يقرأها. ولذلك يتم استخدام طريقة لوريم إيبسوم لأنها تعطي توزيعاَ طبيعياَ -إلى حد ما- للأحرف عوضاً عن استخدام','2023-01-31 05:48:01','2023-01-31 05:48:01');
INSERT INTO `event_feature_sections` VALUES (4,22,'ميزات إيفينتو','ل ومن الهادي واشتدّت, فكانت السادس الأراضي فصل ان. قد كان لغزو كنقطة بالرّغم, أن سق','2023-05-07 11:41:58','2023-05-11 05:38:35');
/*!40000 ALTER TABLE `event_feature_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `serial_number` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_features` WRITE;
/*!40000 ALTER TABLE `event_features` DISABLE KEYS */;
INSERT INTO `event_features` VALUES (4,8,'fas fa-globe','Online Events','Discover and attend exciting online events — anytime, anywhere.',1,'2022-06-07 00:14:56','2025-07-09 05:27:45');
INSERT INTO `event_features` VALUES (6,8,'fas fa-map-marked','Venue Events','Explore what’s coming up at top venues around you.',2,'2022-06-07 00:16:30','2025-07-09 05:28:29');
INSERT INTO `event_features` VALUES (11,8,'fas fa-ticket-alt','Ticket Variations','Offer VIP, Early Bird, Group discounts, and more — all in one event.',3,'2023-05-07 11:48:45','2025-07-09 05:32:35');
INSERT INTO `event_features` VALUES (12,8,'fas fa-qrcode','PWA Ticket Scanner','Use our PWA scanner to validate tickets at the gate — no downloads required.',5,'2023-05-07 11:51:56','2025-07-09 05:32:54');
INSERT INTO `event_features` VALUES (13,8,'fas fa-headset','Get Help Fast','Open a support ticket and our team will respond shortly.',6,'2023-05-07 11:54:11','2025-07-09 05:33:15');
INSERT INTO `event_features` VALUES (17,8,'fas fa-hand-holding-usd','Low Commission Rate','Keep more from every ticket with our ultra-low commission rates.',4,'2023-05-11 05:27:00','2025-07-09 05:34:14');
/*!40000 ALTER TABLE `event_features` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_images` WRITE;
/*!40000 ALTER TABLE `event_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_images` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organizer_id` bigint(20) DEFAULT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT '1',
  `date_type` varchar(20) DEFAULT NULL,
  `countdown_status` int(11) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `start_time` varchar(255) DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` varchar(255) DEFAULT NULL,
  `end_date_time` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_type` varchar(255) DEFAULT NULL,
  `is_featured` varchar(255) NOT NULL DEFAULT 'no',
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `serial_number` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_language_id_foreign` (`language_id`),
  CONSTRAINT `faqs_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` VALUES (5,8,'What is Tica?','Tica is a Nigerian-based event ticketing platform that helps\r\nevent organizers sell tickets seamlessly and enables attendees to\r\ndiscover and purchase tickets for events effortlessly. We also\r\nplan to expand into other e-commerce services in the near future.',1,'2021-06-26 00:35:52','2025-07-09 12:21:26');
INSERT INTO `faqs` VALUES (6,8,'Who can use Tica?','Tica is designed for event organizers, concert promoters,\r\nconference hosts, festival coordinators, and anyone planning an\r\nevent. It\'s also for event attendees looking for a simple, secure\r\nway to buy tickets online.',2,'2021-06-26 00:38:14','2025-07-09 12:22:04');
INSERT INTO `faqs` VALUES (7,8,'Is Tica available outside Nigeria?','Currently, Tica operates primarily in Nigeria, but we are working\r\nto expand into other African countries soon.',3,'2021-06-26 00:39:02','2025-07-09 12:24:23');
INSERT INTO `faqs` VALUES (16,8,'How do I create an event on Tica?','Sign up for an organizer account, log in, and use our simple\r\nevent creation tool to set up your event details, pricing, and\r\nticket types. You can start selling tickets immediately once your\r\nevent is published.',4,'2021-06-26 00:35:52','2025-07-09 12:25:23');
INSERT INTO `faqs` VALUES (17,8,'What types of tickets can I sell on Tica?','You can sell general admission, VIP, early bird, and discounted\r\ntickets, as well as tickets for virtual or hybrid events.',5,'2021-06-26 00:38:14','2025-07-09 12:26:01');
INSERT INTO `faqs` VALUES (18,8,'Can I customize my event page?','Yes, you can add images, descriptions, and branding to make your\r\nevent page reflect your unique style.',6,'2021-06-26 00:39:02','2025-07-09 12:26:38');
INSERT INTO `faqs` VALUES (22,8,'What fees does Tica charge for ticket sales?','Tica charges a service fee of 2.5% + N100 per ticket sold, depending\r\non the ticket type and volume of sales.',7,'2023-05-08 06:10:29','2025-09-13 14:27:44');
INSERT INTO `faqs` VALUES (23,8,'How do I get paid after selling tickets?','Payouts are made directly to your bank account within [3-5\r\nbusiness days] after your event ends. We support multiple payout\r\noptions, including local bank transfers and mobile money.',8,'2023-05-08 06:10:44','2025-07-09 12:38:25');
INSERT INTO `faqs` VALUES (24,8,'Can I promote my event through Tica?','Yes! We offer event promotion tools, including social media\r\nintegration, email marketing, and featured event listings.',9,'2023-05-08 06:10:57','2025-07-09 12:39:06');
INSERT INTO `faqs` VALUES (25,8,'How do I buy a ticket on Tica?','Browse the events on our platform, select the one you\'re\r\ninterested in, choose your ticket type, and complete the purchase\r\nwith your preferred payment method.',10,'2023-05-08 06:11:09','2025-07-09 12:39:27');
INSERT INTO `faqs` VALUES (36,8,'What payment methods does Tica accept?','We accept debit/credit cards, bank transfers, and mobile money\r\n(Paystack, Flutterwave, and other secure gateways).',11,'2025-07-09 12:40:06','2025-07-09 12:40:06');
INSERT INTO `faqs` VALUES (37,8,'How do I receive my tickets?','After purchasing, you\'ll receive your tickets via email or SMS.\r\nEach ticket comes with a QR code for easy entry at the event.',12,'2025-07-09 14:42:04','2025-07-09 14:42:04');
INSERT INTO `faqs` VALUES (38,8,'Can I buy tickets for someone else?','Yes, you can enter the attendee\'s details during checkout and\r\nforward them the ticket confirmation.',13,'2025-07-09 14:42:32','2025-07-09 14:42:32');
INSERT INTO `faqs` VALUES (39,8,'What if I don\'t receive my ticket?','Check your spam/junk folder first. If you still haven’t received\r\nit, contact our support team at [support@tica.com] or call\r\n[0810-XXX-XXXX] for assistance.',14,'2025-07-09 14:43:02','2025-07-09 14:43:02');
INSERT INTO `faqs` VALUES (40,8,'Are my payment details secure?','Absolutely! We use industry-standard encryption and secure\r\npayment gateways to protect your information.',15,'2025-07-09 14:43:30','2025-07-09 14:43:30');
INSERT INTO `faqs` VALUES (41,8,'What is Tica’s refund policy?','Refunds are subject to the event organizer’s policy. Some events\r\nmay offer refunds, while others may not. Please check the event\r\npage for specific refund details before purchasing.',16,'2025-07-09 14:44:09','2025-07-09 14:44:09');
INSERT INTO `faqs` VALUES (42,8,'Can I get a refund if the event is canceled?','Yes. If an event is canceled, you will receive a full refund\r\nautomatically. Refunds will be processed within 7-10 business\r\ndays.',17,'2025-07-09 14:44:42','2025-07-09 14:44:42');
INSERT INTO `faqs` VALUES (43,8,'What happens if the event date is postponed?','If an event is postponed, your ticket will remain valid for the\r\nnew date. If you can\'t attend the rescheduled event, you may be\r\neligible for a refund based on the organizer’s policy.',18,'2025-07-09 14:45:10','2025-07-09 14:45:10');
INSERT INTO `faqs` VALUES (44,8,'I’m having trouble logging in. What should I do?','Try resetting your password by clicking “Forgot Password?” on the\r\nlogin page. If the issue persists, contact our support team for\r\nassistance.',19,'2025-07-09 14:45:40','2025-07-09 14:45:40');
INSERT INTO `faqs` VALUES (45,8,'How do I contact Tica support?','You can reach us via email at [support@tica.com], call\r\n0707 379, 7999, or use our live chat feature on the website.',20,'2025-07-09 14:46:20','2025-09-13 14:29:29');
INSERT INTO `faqs` VALUES (46,8,'Will Tica offer services beyond ticket sales?','Yes! In the future, Tica will introduce additional e-commerce\r\nfeatures, including the ability to sell digital products,\r\nmerchandise, and memberships.',21,'2025-07-09 14:46:52','2025-07-09 14:46:52');
INSERT INTO `faqs` VALUES (47,8,'How can I stay updated on Tica’s new features?','Follow us on social media @tica.ng and subscribe to our\r\nnewsletter for the latest updates.',22,'2025-07-09 14:47:39','2025-09-13 14:28:24');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `background_color` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `serial_number` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `features` WRITE;
/*!40000 ALTER TABLE `features` DISABLE KEYS */;
INSERT INTO `features` VALUES (6,8,'0066FF','fas fa-book-reader','Highly Qualified Mentors & Instructors','See the E Learning Tools your competitors are already using - Start Now! Get App helps more than 800k businesses find the best software for their needs.',3,'2021-10-11 00:11:50','2022-05-15 00:03:40');
INSERT INTO `features` VALUES (7,8,'8976FF','fas fa-book','Quizzes, Videos, Code Snippets & More','See the E Learning Tools your competitors are already using - Start Now! Get App helps more than 800k businesses find the best software for their needs.',2,'2021-10-11 00:13:02','2022-05-15 00:02:41');
INSERT INTO `features` VALUES (8,8,'30BCFF','fas fa-chalkboard-teacher','Course Completion Certificate','See the E Learning Tools your competitors are already using - Start Now! Get App helps more than 800k businesses find the best software for their needs.',1,'2021-10-11 00:13:44','2022-05-15 00:01:54');
INSERT INTO `features` VALUES (12,8,'2ECC71',NULL,'Drag & Drop Lesson Contents Decoration','See the E Learning Tools your competitors are already using - Start Now! Get App helps more than 800k businesses find the best software for their needs.',4,'2022-05-15 00:05:22','2022-05-15 00:06:29');
/*!40000 ALTER TABLE `features` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `footer_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `footer_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `footer_background_color` varchar(255) DEFAULT NULL,
  `about_company` text DEFAULT NULL,
  `copyright_text` text DEFAULT NULL,
  `footer_logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `footer_texts_language_id_foreign` (`language_id`),
  CONSTRAINT `footer_texts_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `footer_contents` WRITE;
/*!40000 ALTER TABLE `footer_contents` DISABLE KEYS */;
INSERT INTO `footer_contents` VALUES (1,8,'011444','<p>Every event tells a story, and stories shape culture. TICA empowers Organizers (KultureCreators) to craft experiences that spark connection, start conversations, and build lasting communities.<br>Join the movement! Discover events today!</p>','<p>Copyright ©{2025}. All Rights Reserved.</p>','1776435258.png','2021-06-19 05:57:47','2026-04-17 19:14:18');
/*!40000 ALTER TABLE `footer_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fun_fact_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fun_fact_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `background_image` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fun_fact_sections` WRITE;
/*!40000 ALTER TABLE `fun_fact_sections` DISABLE KEYS */;
INSERT INTO `fun_fact_sections` VALUES (3,8,'61befc8312cee.jpg','Some Fun Facts from Us','2021-10-07 03:23:12','2021-12-19 03:33:55');
/*!40000 ALTER TABLE `fun_fact_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `gooogle_calendar_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gooogle_calendar_infos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `google_calendar_event_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `gooogle_calendar_infos` WRITE;
/*!40000 ALTER TABLE `gooogle_calendar_infos` DISABLE KEYS */;
/*!40000 ALTER TABLE `gooogle_calendar_infos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `guests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `guests` WRITE;
/*!40000 ALTER TABLE `guests` DISABLE KEYS */;
/*!40000 ALTER TABLE `guests` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `hero_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hero_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `background_image` varchar(255) NOT NULL,
  `first_title` varchar(255) DEFAULT NULL,
  `second_title` varchar(255) DEFAULT NULL,
  `first_button` varchar(255) DEFAULT NULL,
  `first_button_url` varchar(255) DEFAULT NULL,
  `second_button` varchar(255) DEFAULT NULL,
  `second_button_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `hero_sections` WRITE;
/*!40000 ALTER TABLE `hero_sections` DISABLE KEYS */;
INSERT INTO `hero_sections` VALUES (2,8,'686d86bd380ba.png','Events Shape Kulture, Let’s Build It Together','TICA empowers creators to spark connection, ignite culture, and build lasting communities through unforgettable events. Join the movement! Discover events today and share yours with the world.','Find Events','https://codecanyon.kreativdev.com/coursela/courses','Meet Instructors','https://codecanyon.kreativdev.com/coursela/instructors',NULL,'61bda9c61892c.png','2021-11-30 22:30:04','2025-09-15 00:33:18');
/*!40000 ALTER TABLE `hero_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `how_work_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `how_work_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `serial_number` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `how_work_items` WRITE;
/*!40000 ALTER TABLE `how_work_items` DISABLE KEYS */;
INSERT INTO `how_work_items` VALUES (1,8,'fas fa-user-plus','Join the Movement!','Sign up as an organizer or attendee (it only takes a minute).',1,'2022-06-07 00:45:47','2025-09-15 00:45:02');
INSERT INTO `how_work_items` VALUES (2,8,'fas fa-plus','Create Kulture','Launch your event, set ticket types, and connect with audiences everywhere.',2,'2022-06-07 00:48:26','2025-09-15 00:46:06');
INSERT INTO `how_work_items` VALUES (3,8,'fas fa-cart-arrow-down','Connect & Celebrate','Sell tickets, build community, and welcome your attendees.',3,'2022-06-07 00:49:09','2025-09-15 00:47:05');
INSERT INTO `how_work_items` VALUES (4,8,'fas fa-wallet','Get Rewarded','Instant e-tickets for attendees & Fast payouts for organizers.',4,'2022-06-07 00:49:38','2025-09-15 00:47:55');
/*!40000 ALTER TABLE `how_work_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `how_works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `how_works` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `how_works` WRITE;
/*!40000 ALTER TABLE `how_works` DISABLE KEYS */;
INSERT INTO `how_works` VALUES (1,8,'Your Journey Into TICAKulture              (How It Works)','Simple. Fast. Powerful.','2022-06-07 00:42:14','2025-09-15 00:49:23');
/*!40000 ALTER TABLE `how_works` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` char(255) NOT NULL,
  `direction` tinyint(4) NOT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (8,'English','en',0,1,'2021-05-31 05:58:22','2025-11-29 17:05:38');
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mail_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail_type` varchar(50) NOT NULL,
  `mail_subject` varchar(255) NOT NULL,
  `mail_body` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mail_templates` WRITE;
/*!40000 ALTER TABLE `mail_templates` DISABLE KEYS */;
INSERT INTO `mail_templates` VALUES (4,'verify_email','Verify Your Email Address','<p>Hi <b>{username}</b>,</p><p>We just need to verify your email address before you can access to your dashboard.</p><p>Verify your email address, {verification_link}.</p><p>Thank you.<br />{website_title}</p><p><br /></p>');
INSERT INTO `mail_templates` VALUES (5,'reset_password','Recover Password of Your Account','<p>Hi {customer_name},</p><p>We have received a request to reset your password. If you did not make the request, just ignore this email. Otherwise, you can reset your password using this below link.</p><p>{password_reset_link}</p><p>Thanks,<br>{website_title}</p>');
INSERT INTO `mail_templates` VALUES (9,'event_booking','Event Confirmation','<p>Hi <span style=\"font-weight:600;\">{customer_name}</span>,</p><p>You have successfully enrol in the following event.</p><p>Booking Id: #{order_id}<br />Event: {title} </p><p>Also, we have attached an invoice in this mail.</p><p>Best regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (10,'event_booking_approved','Approval of Event Booking','<p>Hi <span style=\"font-weight:600;\">{customer_name}</span>,</p><p>Your payment is completed, and we have approved your booking for the following evnent.</p><p>Booking Id: #{order_id}<br /></p><p>Event : {title}</p><p>Also, we have attached an invoice in this mail.</p><p>Best regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (11,'event_booking_rejected','Rejection of Event Booking','<p>Hi <span style=\"font-weight:600;\">{customer_name}</span>,</p><p>Your payment is not completed, thus we have rejected your Booking for the following Event.</p><p>Booking Id: #{order_id}<br /></p><p>Event : {title}</p><p>For further information, please do not hesitate to contact us.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (12,'product_order','Order Confirmation','<p>Hi <span style=\"font-weight:600;\">{customer_name}</span>,</p><p>Your Order  has been  successfully Placed.</p><p>Order Id: #{order_id}<br /></p><p>Also, we have attached an invoice in this mail.</p><p>Best regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (13,'withdraw_approve','Confirmation of Withdraw Approve','<p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\">Hi {organizer_username},</p><p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\">This email confirms that your withdrawal request  {withdraw_id} is approved. </p><p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\">Your current balance is {current_balance}, withdraw amount {withdraw_amount}, charge : {charge},payable amount {payable_amount}</p><p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\">withdraw method : {withdraw_method}. The transaction id is {transaction_id}.</p><p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\"><br /></p><p style=\"font-family:Lato, sans-serif;font-size:14px;line-height:1.82;color:rgb(0,0,0);font-style:normal;font-weight:400;text-align:left;\">Best Regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (14,'withdraw_rejected','Withdraw Request Rejected','<p>Hi {organizer_username},</p><p>This email confirms that your withdrawal request  {withdraw_id} is rejected and the balance added to your account. </p><p>Your current balance is {current_balance}</p><p><br /></p><p>Best Regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (15,'balance_add','Balance Add','<p>Hi {username}</p><p>{amount} added to your account.</p><p>Your current balance is {current_balance}. </p><p>The transaction id is {transaction_id}.<br /></p><p><br /></p><p>Best Regards.<br />{website_title}<br /></p>');
INSERT INTO `mail_templates` VALUES (16,'balance_subtract','Balance Subtract','<p>Hi {username}</p><p>{amount} subtract from your account.</p><p>Your current balance is {current_balance}.</p><p>The transaction id is {transaction_id}.<br /></p><p><br /></p><p>Best Regards.<br />{website_title}</p>');
INSERT INTO `mail_templates` VALUES (17,'product_shipping','Product Shipping Status','<p>Hi <span style=\"font-weight:600;\">{customer_name}</span>,</p><p>Your order shipping status is {status}.</p><p>Order Id: #{order_id}</p><p>Best regards.<br />{website_title}</p>');
/*!40000 ALTER TABLE `mail_templates` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu_builders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_builders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `menus` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu_builders` WRITE;
/*!40000 ALTER TABLE `menu_builders` DISABLE KEYS */;
INSERT INTO `menu_builders` VALUES (2,8,'[{\"text\":\"Home\",\"href\":\"\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"home\"},{\"text\":\"Explore Events\",\"href\":\"events\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"events\"},{\"text\":\"About Us\",\"href\":\"\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"about\"},{\"text\":\"Pricing\",\"href\":\"https://pricing.tica.ng/\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"custom\"},{\"text\":\"Promote\",\"href\":\"https://promote.tica.ng/\",\"icon\":\"empty\",\"target\":\"_blank\",\"title\":\"\",\"type\":\"custom\"},{\"text\":\"Contact\",\"href\":\"\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"contact\"},{\"text\":\"FAQ\",\"href\":\"\",\"icon\":\"empty\",\"target\":\"_self\",\"title\":\"\",\"type\":\"faq\"}]','2021-12-01 05:32:09','2025-10-06 02:43:10');
/*!40000 ALTER TABLE `menu_builders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (3,'2023_03_04_134315_drop_secondary_color_from_basic_settings_table',1);
INSERT INTO `migrations` VALUES (6,'2023_03_05_152403_add_tax_commission_percentage_column_to_bookings_table',2);
INSERT INTO `migrations` VALUES (9,'2023_03_22_115828_add_column_to_conversations_table',3);
INSERT INTO `migrations` VALUES (10,'2023_05_11_150902_create_ticket_contents_table',4);
INSERT INTO `migrations` VALUES (14,'2023_05_13_124521_create_variation_contents_table',5);
INSERT INTO `migrations` VALUES (15,'2023_05_20_154216_add_about_page_title_column_to_page_headings_table',6);
INSERT INTO `migrations` VALUES (16,'2023_05_20_154329_add_meta_keyword_description_column_to_seos_table',6);
INSERT INTO `migrations` VALUES (17,'2023_07_30_094527_add_scan_status_column_to_bookings_table',7);
INSERT INTO `migrations` VALUES (18,'2023_09_30_162759_add_tax_percentage_column_to_product_orders_table',8);
INSERT INTO `migrations` VALUES (19,'2021_02_01_030511_create_payment_invoices_table',9);
INSERT INTO `migrations` VALUES (20,'2023_11_16_062730_add_event_guest_checkout_status_to_basic_settings_table',9);
INSERT INTO `migrations` VALUES (21,'2024_02_07_055018_add_midtrans_payment_gateway_row_to_online_gateways_table',9);
INSERT INTO `migrations` VALUES (22,'2024_02_07_172740_add_iyzico_payment_gateway_into_online_gateways_table',9);
INSERT INTO `migrations` VALUES (23,'2024_02_08_153546_add_paytabs_payment_gateway_into_online_gateways',9);
INSERT INTO `migrations` VALUES (24,'2024_02_10_105443_add_toyyibpay_payment_gateway_into_online_gateways',9);
INSERT INTO `migrations` VALUES (25,'2024_02_10_122829_add_phonepe_payment_gateway_into_online_gateways_table',9);
INSERT INTO `migrations` VALUES (26,'2024_02_10_152845_add_yoco_payment_gateway_into_online_gateways',9);
INSERT INTO `migrations` VALUES (27,'2024_02_10_172724_add_xindit_payment_gateway_into_online_gateways',9);
INSERT INTO `migrations` VALUES (28,'2024_02_11_143939_add_myfatoorah_payment_gateway_into_online_gateways',9);
INSERT INTO `migrations` VALUES (29,'2024_02_12_120007_add_conversation_id_to_event_bookings_table',9);
INSERT INTO `migrations` VALUES (30,'2024_02_12_162617_add_conversation_id_to_product_orders_table',9);
INSERT INTO `migrations` VALUES (31,'2024_02_14_112643_add_perfect_money_payment_gateway_into_online_gateways_table',9);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `offline_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline_gateways` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_description` text DEFAULT NULL,
  `instructions` blob DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 -> gateway is deactive, 1 -> gateway is active.',
  `has_attachment` tinyint(1) NOT NULL COMMENT '0 -> do not need attachment, 1 -> need attachment.',
  `serial_number` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `offline_gateways` WRITE;
/*!40000 ALTER TABLE `offline_gateways` DISABLE KEYS */;
INSERT INTO `offline_gateways` VALUES (4,'BANK PAYMENT','Send Ticket Amount to Account Number With Proof Below','<p>TICA INNOVATIONS<br />0234326340<br />Union Bank of Nigeria</p>',0,1,1,'2026-04-03 15:43:23','2026-04-04 21:09:04');
/*!40000 ALTER TABLE `offline_gateways` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `online_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `online_gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `information` mediumtext NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `online_gateways` WRITE;
/*!40000 ALTER TABLE `online_gateways` DISABLE KEYS */;
INSERT INTO `online_gateways` VALUES (1,'PayPal','paypal','{}',0);
INSERT INTO `online_gateways` VALUES (2,'Instamojo','instamojo','{}',0);
INSERT INTO `online_gateways` VALUES (3,'Paystack','paystack','{}',1);
INSERT INTO `online_gateways` VALUES (4,'Flutterwave','flutterwave','{}',0);
INSERT INTO `online_gateways` VALUES (5,'Razorpay','razorpay','{}',0);
INSERT INTO `online_gateways` VALUES (6,'MercadoPago','mercadopago','{}',0);
INSERT INTO `online_gateways` VALUES (7,'Mollie','mollie','{}',0);
INSERT INTO `online_gateways` VALUES (8,'Stripe','stripe','{}',0);
INSERT INTO `online_gateways` VALUES (9,'Paytm','paytm','{}',0);
INSERT INTO `online_gateways` VALUES (10,'Midtrans','midtrans','{}',0);
INSERT INTO `online_gateways` VALUES (11,'Iyzico','iyzico','{}',0);
INSERT INTO `online_gateways` VALUES (12,'Paytabs','paytabs','{}',0);
INSERT INTO `online_gateways` VALUES (13,'Toyyibpay','toyyibpay','{}',0);
INSERT INTO `online_gateways` VALUES (14,'Phonepe','phonepe','{}',0);
INSERT INTO `online_gateways` VALUES (15,'Yoco','yoco','{}',0);
INSERT INTO `online_gateways` VALUES (16,'Xendit','xendit','{}',0);
INSERT INTO `online_gateways` VALUES (17,'Myfatoorah','myfatoorah','{}',0);
INSERT INTO `online_gateways` VALUES (18,'Perfect Money','perfect_money','{}',0);
/*!40000 ALTER TABLE `online_gateways` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `qty` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `summery` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `previous_price` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `organizer_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizer_infos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) DEFAULT NULL,
  `organizer_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `zip_code` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `organizer_infos` WRITE;
/*!40000 ALTER TABLE `organizer_infos` DISABLE KEYS */;
/*!40000 ALTER TABLE `organizer_infos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `organizers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `photo` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT '1',
  `amount` float DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `organizers` WRITE;
/*!40000 ALTER TABLE `organizers` DISABLE KEYS */;
/*!40000 ALTER TABLE `organizers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `page_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` blob NOT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_contents_language_id_foreign` (`language_id`),
  KEY `page_contents_page_id_foreign` (`page_id`),
  CONSTRAINT `page_contents_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_contents_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `page_contents` WRITE;
/*!40000 ALTER TABLE `page_contents` DISABLE KEYS */;
INSERT INTO `page_contents` VALUES (30,8,14,'Terms & Conditions','terms-&-conditions','<p> </p>\n<hr />\n<h2><strong>Terms &amp; Conditions</strong></h2>\n<p><strong>Effective Date: 05/07/2025</strong></p>\n<p>Welcome to <strong>Tica</strong>. These Terms and Conditions (“Terms”) govern your access to and use of Tica’s website, and ticketing services. By using Tica, you agree to be bound by these Terms. If you do not agree, please do not use our services.</p>\n<hr />\n<h3><strong>1. Definitions</strong></h3>\n<ul>\n<li>\n<p><strong>\"Tica\"</strong>: Refers to the digital platform operated by our company for event ticketing and e-commerce services.</p>\n</li>\n<li>\n<p><strong>\"User\"</strong>: Any individual using Tica’s services, including organizers, buyers, and sellers.</p>\n</li>\n<li>\n<p><strong>\"Organizer\"</strong>: A user who creates and manages events.</p>\n</li>\n<li>\n<p><strong>\"Buyer\"</strong>: A user who purchases tickets for events.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>2. Service Description</strong></h3>\n<p>Tica provides an online platform that enables:</p>\n<ul>\n<li>\n<p>Event organizers to create, promote, and sell tickets.</p>\n</li>\n<li>\n<p>Attendees to browse, purchase, and manage event tickets.</p>\n</li>\n<li>\n<p>Secure digital ticket delivery and management.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>3. Account Registration</strong></h3>\n<ul>\n<li>\n<p>You must provide accurate, up-to-date information when registering.</p>\n</li>\n<li>\n<p>You are responsible for maintaining the confidentiality of your login credentials.</p>\n</li>\n<li>\n<p>Tica reserves the right to suspend or terminate accounts found in violation of these Terms.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>4. Event Listings</strong></h3>\n<ul>\n<li>\n<p>Organizers are solely responsible for the accuracy of their event information.</p>\n</li>\n<li>\n<p>Tica reserves the right to remove or suspend events that violate local laws or our community standards.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>5. Fees &amp; Payment</strong></h3>\n<ul>\n<li>\n<p>Tica charges a service fee per transaction, which is communicated during the listing process.</p>\n</li>\n<li>\n<p>All payments are securely processed using trusted payment gateways compliant with Nigerian financial regulations.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>6. Ticket Purchase</strong></h3>\n<ul>\n<li>\n<p>All ticket purchases are final, unless otherwise stated in our <a href=\"https://chatgpt.com/c/686651bb-d470-8003-a865-daaafce7907b\">Refund Policy</a>.</p>\n</li>\n<li>\n<p>Buyers are responsible for reviewing all event details before purchasing.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>7. Cancellations &amp; Postponements</strong></h3>\n<ul>\n<li>\n<p>Organizers must notify Tica immediately if an event is canceled or rescheduled.</p>\n</li>\n<li>\n<p>Refunds due to cancellations or changes are handled in accordance with our <a href=\"https://chatgpt.com/c/686651bb-d470-8003-a865-daaafce7907b\">Refund Policy</a>.</p>\n</li>\n</ul>\n<hr />\n<h3><strong>8. User Conduct</strong></h3>\n<p>Users agree <strong>not to</strong>:</p>\n<ul>\n<li>\n<p>Engage in illegal activities using the platform</p>\n</li>\n<li>\n<p>Post false, misleading, or fraudulent content</p>\n</li>\n<li>\n<p>Infringe on intellectual property rights or violate any applicable laws</p>\n</li>\n</ul>\n<hr />\n<h3><strong>9. Intellectual Property</strong></h3>\n<p>All content on the Tica platform, including logos, software, text, and graphics, is the property of Tica or its licensors and may not be copied, redistributed, or used without permission.</p>\n<hr />\n<h3><strong>10. Limitation of Liability</strong></h3>\n<p>Tica shall not be held liable for:</p>\n<ul>\n<li>\n<p>Losses resulting from event cancellations or rescheduling</p>\n</li>\n<li>\n<p>False or misleading information provided by organizers</p>\n</li>\n<li>\n<p>Unauthorized access to user accounts or personal data</p>\n</li>\n</ul>\n<hr />\n<h3><strong>11. Changes to Terms</strong></h3>\n<p>Tica may update these Terms at any time. Any changes will be posted on this page. Continued use of our services after changes are made constitutes your acceptance of the revised Terms.</p>\n<hr />\n<h3><strong>12. Governing Law</strong></h3>\n<p>These Terms are governed by the laws of the <strong>Federal Republic of Nigeria</strong>. Any disputes will be subject to the exclusive jurisdiction of Nigerian courts.</p>\n<hr />\n<h3><strong>13. Contact Information</strong></h3>\n<p>For questions or support, please contact us:</p>\n<p>📧 <strong>Email:</strong> <a href=\"mailto:support@tica.com\">support@tica.com</a><br />📞 <strong>Phone:</strong> +234-707-379-3999</p>\n<hr />\n<p> </p>','terms',NULL,'2021-10-18 02:33:45','2025-09-13 14:58:07');
INSERT INTO `page_contents` VALUES (39,8,16,'Refund Policy','refund-policy','<h2> </h2>\n<h3><strong>1. Overview</strong></h3>\n<p>At <strong>Tica</strong>, we aim to provide a smooth and transparent ticketing experience. Refunds are issued in accordance with the guidelines below and are subject to the specific terms of each event.</p>\n<h3><strong>2. Eligibility for Refunds</strong></h3>\n<p>Refunds may be granted under the following conditions:</p>\n<ul>\n<li>\n<p><strong>Event Cancellation:</strong><br />If an event is canceled by the organizer, a full refund will be issued to all ticket holders.</p>\n</li>\n<li>\n<p><strong>Event Postponement:</strong><br />If an event is rescheduled, ticket holders may request a refund if they are unable to attend on the new date.</p>\n</li>\n<li>\n<p><strong>Duplicate Purchase:</strong><br />If you accidentally purchase the same ticket more than once, contact our support team within <strong>24 hours</strong> to request a refund.</p>\n</li>\n</ul>\n<h3><strong>3. Non-Refundable Situations</strong></h3>\n<p>Refunds will <strong>not</strong> be issued in the following cases:</p>\n<ul>\n<li>\n<p><strong>Change of Mind:</strong><br />Deciding not to attend the event after purchasing a ticket does not qualify for a refund.</p>\n</li>\n<li>\n<p><strong>No-Show:</strong><br />If you do not attend the event for any reason, you will not be eligible for a refund.</p>\n</li>\n<li>\n<p><strong>Service Fees:</strong><br />Tica’s service and processing fees are non-refundable under any circumstance.</p>\n</li>\n</ul>\n<h2><strong>4. Requesting a Refund</strong></h2>\n<ul>\n<li>\n<p>All refund requests must be submitted via <strong><a class=\"cursor-pointer\">support@tica.com</a></strong> within <strong>7 days</strong> of the event cancellation or postponement notification.</p>\n</li>\n<li>\n<p>Please include your <strong>ticket reference</strong>, <strong>event name</strong>, and a brief <strong>reason for the request</strong>.</p>\n</li>\n</ul>\n<hr />\n<h2><strong>5. Processing Refunds</strong></h2>\n<ul>\n<li>\n<p>Approved refunds will be processed within <strong>7–14 business days</strong>.</p>\n</li>\n<li>\n<p>All refunds will be issued via the <strong>original payment method</strong> used during purchase.</p>\n</li>\n<li>\n<p>Please note that <strong>processing times may vary</strong> depending on your financial institution.</p>\n</li>\n</ul>\n<hr />\n<h2><strong>6. Disputes</strong></h2>\n<p>In the event of a dispute between an organizer and an attendee, <strong>Tica will act as a neutral mediator</strong> to resolve the issue. All decisions made by Tica regarding the dispute are <strong>final and binding</strong>.</p>\n<hr />\n<h2><strong>7. Contact Information</strong></h2>\n<p>For all refund-related inquiries, you can reach us at:</p>\n<p>📧 <strong>Email:</strong> <a class=\"cursor-pointer\">refunds@tica.com</a><br />📞 <strong>Phone:</strong> +234-707-379-7999</p>\n<p> </p>',NULL,NULL,'2023-05-20 04:53:32','2025-09-13 14:59:11');
INSERT INTO `page_contents` VALUES (42,8,18,'Pricing','pricing','<div class=\"page\" title=\"Page 1\">\n<div class=\"layoutArea\">\n<div class=\"column\">\n<h2><strong>Simple, Transparent, and Built for Growth</strong></h2>\n<div class=\"page\" title=\"Page 1\">\n<div class=\"layoutArea\">\n<div class=\"column\">\n<p style=\"line-height:1;\"><strong>Only pay when you sell.</strong></p>\n<pre style=\"line-height:1;\">No monthly fees. No hidden charges. No commitments.<br /><br /></pre>\n<div class=\"page\" title=\"Page 1\">\n<div class=\"layoutArea\">\n<div class=\"column\">\n<p><strong>Core Platform Fees</strong></p>\n</div>\n</div>\n<div class=\"layoutArea\">\n<div class=\"column\">\n<table style=\"border-collapse: collapse; width: 100%; height: 67.1952px;\" border=\"1\"><colgroup><col style=\"width: 50%;\" /><col style=\"width: 50%;\" /></colgroup>\n<tbody>\n<tr style=\"height:22.3984px;\">\n<td style=\"height: 22.3984px;\"><strong>What You Pay</strong></td>\n<td style=\"height: 22.3984px;\"><strong>Amount</strong></td>\n</tr>\n<tr style=\"height:22.3984px;\">\n<td style=\"height: 22.3984px;\">Platform fee</td>\n<td style=\"height: 22.3984px;\">\n<p>4% of each ticket sold</p>\n</td>\n</tr>\n<tr style=\"height:22.3984px;\">\n<td style=\"height: 22.3984px;\">Processing fee </td>\n<td style=\"height: 22.3984px;\">₦100 per ticket</td>\n</tr>\n</tbody>\n</table>\n<p> </p>\n<p>Example:</p>\n</div>\n</div>\n<div class=\"layoutArea\">\n<div class=\"column\">\n<p>Sell a ₦5,000 ticket → You pay ₦200 in total fees</p>\n<p>(5% of 5,000 = ₦200)<br /><br />Your Ticket Buyers pays a 100 naira processing fee.</p>\n<p>❇️ No setup fees<br />❇️ No monthly subscriptions<br />❇️ You only pay when you make sales</p>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>',NULL,NULL,'2025-09-15 14:56:17','2026-10-18 00:11:35');
INSERT INTO `page_contents` VALUES (43,8,19,'Privacy Policy','privacy-policy','<p>Introduction<br />Tica (\"we,\" \"us,\" \"our\") respects your privacy and is committed to<br />protecting your personal data. This Privacy Policy explains how we<br />collect, use, and protect your information.<br />8<br />1. Information We Collect<br />● Personal Information: Name, email address, phone number, and<br />payment details.<br />● Event Information: Event details created by organizers.<br />● Usage Data: IP address, browser type, and app usage patterns.<br />2. How We Use Your Information<br />● To provide services: Facilitate ticket purchases and event<br />management.<br />● To improve services: Analyze usage trends to enhance Tica.<br />● To communicate: Send event updates, confirmations, and marketing<br />materials.<br />● For security: Prevent fraud and ensure compliance with laws.<br />3. Data Sharing<br />We may share your data with:<br />● Payment processors: To facilitate transactions.<br />● Event organizers: To manage ticket sales and entry.<br />● Legal authorities: When required by law.<br />4. Data Protection<br />We use encryption and secure servers to protect your data. However, no<br />method of transmission over the internet is 100% secure.<br />5. Your Rights<br />● Access: Request a copy of your data.<br />● Correction: Update your data.<br />● Deletion: Request deletion of your account.<br />● Objection: Opt out of marketing communications.<br />6. International Data Transfers<br />9<br />As we scale to other African countries, your data may be processed<br />outside Nigeria. We ensure all transfers comply with privacy laws.<br />7. Retention<br />We retain your data as long as necessary for providing services and<br />complying with legal obligations.<br />8. Updates to Privacy Policy<br />We may update this Policy periodically. Check back regularly for<br />changes.<br />9. Contact Information<br />For privacy concerns:<br />Email: privacy@tica.com<br />Phone: +234-<a href=\"tel:07073797999\">707-379-7999</a></p>','Privacy Policy',NULL,'2025-11-10 10:52:07','2025-11-10 10:52:07');
/*!40000 ALTER TABLE `page_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `page_headings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_headings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `blog_page_title` varchar(255) DEFAULT NULL,
  `blog_details_page_title` varchar(255) DEFAULT NULL,
  `contact_page_title` varchar(255) DEFAULT NULL,
  `about_page_title` varchar(255) DEFAULT NULL,
  `event_page_title` varchar(255) DEFAULT NULL,
  `shop_page_title` varchar(255) DEFAULT NULL,
  `cart_page_title` varchar(255) DEFAULT NULL,
  `event_details_page_title` varchar(255) DEFAULT NULL,
  `faq_page_title` varchar(255) DEFAULT NULL,
  `customer_forget_password_page_title` varchar(255) DEFAULT NULL,
  `organizer_forget_password_page_title` varchar(255) DEFAULT NULL,
  `organizer_page_title` varchar(255) DEFAULT NULL,
  `customer_login_page_title` varchar(255) DEFAULT NULL,
  `customer_signup_page_title` varchar(255) DEFAULT NULL,
  `organizer_login_page_title` varchar(255) DEFAULT NULL,
  `organizer_signup_page_title` varchar(255) DEFAULT NULL,
  `customer_dashboard_page_title` varchar(255) DEFAULT NULL,
  `customer_booking_page_title` varchar(255) DEFAULT NULL,
  `customer_booking_details_page_title` varchar(255) DEFAULT NULL,
  `customer_order_page_title` varchar(255) DEFAULT NULL,
  `customer_order_details_page_title` varchar(255) DEFAULT NULL,
  `customer_wishlist_page_title` varchar(255) DEFAULT NULL,
  `customer_support_ticket_page_title` varchar(255) DEFAULT NULL,
  `support_ticket_create_page_title` varchar(255) DEFAULT NULL,
  `support_ticket_details_page_title` varchar(255) DEFAULT NULL,
  `customer_edit_profile_page_title` varchar(255) DEFAULT NULL,
  `customer_change_password_page_title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_headings_language_id_foreign` (`language_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `page_headings` WRITE;
/*!40000 ALTER TABLE `page_headings` DISABLE KEYS */;
INSERT INTO `page_headings` VALUES (4,8,'Blog','Blog Details','Contact','About Us','Our Events','Shop','Cart','Event  Details','FAQ','Forget Password','Forget Password','Organizer','Customer Login','Customer Signup','Organizer Login','Organizer Signup','Dashboard','My Bookings','Booking Details','My Orders','Order Details','My Wishlists','Help Message','Create a Support Ticket','Help Message Details','Edit Profile','Change Password','2021-10-14 02:42:42','2025-07-09 14:58:53');
/*!40000 ALTER TABLE `page_headings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (14,1,'2021-10-18 02:33:45','2021-10-18 02:33:45');
INSERT INTO `pages` VALUES (16,1,'2023-05-20 04:53:32','2023-05-20 04:53:32');
INSERT INTO `pages` VALUES (18,1,'2025-09-15 14:56:17','2025-09-15 14:56:17');
INSERT INTO `pages` VALUES (19,1,'2025-11-10 10:52:06','2025-11-10 10:52:06');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `partner_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `partner_sections` WRITE;
/*!40000 ALTER TABLE `partner_sections` DISABLE KEYS */;
INSERT INTO `partner_sections` VALUES (1,8,'Our Partners','We’re proud to collaborate with brands and organisations that help us power great events and experiences.','2022-06-07 21:53:57','2025-07-10 08:27:26');
INSERT INTO `partner_sections` VALUES (2,9,'شريكنا','خدمتنا مجانية للمستخدمين لأن البائعين يدفعون لنا عندما يتلقون زيارات على شبكة','2022-06-07 21:54:13','2022-07-16 22:56:35');
INSERT INTO `partner_sections` VALUES (3,17,'شريكنا','الأحرف. خمسة قرون من الزمن لم تقضي على هذا النص، بل انه حتى صار','2023-01-31 05:52:18','2023-01-31 05:52:18');
/*!40000 ALTER TABLE `partner_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `partners` WRITE;
/*!40000 ALTER TABLE `partners` DISABLE KEYS */;
INSERT INTO `partners` VALUES (7,'686f8594941f7.png','https://creativeopenhouse.org/','1','2022-06-07 03:06:07','2025-07-10 08:21:13');
INSERT INTO `partners` VALUES (8,'686f85a04fad7.png','https://nollydicfilmacademy.com/','2','2022-06-07 03:06:16','2025-07-10 08:21:27');
/*!40000 ALTER TABLE `partners` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `payment_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `InvoiceId` bigint(20) unsigned NOT NULL,
  `InvoiceStatus` varchar(255) NOT NULL,
  `InvoiceValue` varchar(255) NOT NULL,
  `Currency` varchar(255) NOT NULL,
  `InvoiceDisplayValue` varchar(255) NOT NULL,
  `TransactionId` bigint(20) unsigned NOT NULL,
  `TransactionStatus` varchar(255) NOT NULL,
  `PaymentGateway` varchar(255) NOT NULL,
  `PaymentId` bigint(20) unsigned NOT NULL,
  `CardNumber` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payment_invoices` WRITE;
/*!40000 ALTER TABLE `payment_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_invoices` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `popups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `popups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `type` smallint(5) unsigned NOT NULL,
  `image` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `background_color` varchar(255) DEFAULT NULL,
  `background_color_opacity` decimal(3,2) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `button_text` varchar(255) DEFAULT NULL,
  `button_color` varchar(255) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `delay` int(10) unsigned NOT NULL COMMENT 'value will be in milliseconds',
  `serial_number` mediumint(8) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '0 => deactive, 1 => active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `popups_language_id_foreign` (`language_id`),
  CONSTRAINT `popups_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `popups` WRITE;
/*!40000 ALTER TABLE `popups` DISABLE KEYS */;
INSERT INTO `popups` VALUES (7,8,1,'64577a7c2cee5.png','Black Friday',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1500,1,0,'2021-08-10 05:05:12','2023-05-07 10:17:45');
INSERT INTO `popups` VALUES (8,8,2,'64577ac23d6b5.png','Month End Sale','2079FF',0.80,'ENJOY 10% OFF','Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.','Book Now','2079FF','https://codecanyon8.kreativdev.com/evento',NULL,NULL,2000,2,0,'2021-08-10 05:07:11','2025-07-09 14:52:46');
INSERT INTO `popups` VALUES (10,8,3,'64577b1c72c92.png','Summer Sale','2079FF',0.70,'Newsletter','Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.','Subscribe','2079FF',NULL,NULL,NULL,2000,3,0,'2021-08-11 05:42:11','2023-05-09 11:07:35');
INSERT INTO `popups` VALUES (11,8,4,'64577cffd4533.png','Winter Offer',NULL,NULL,'Get 10% off your first order','Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt','Book Now','2079FF','https://codecanyon8.kreativdev.com/evento',NULL,NULL,1500,4,0,'2021-08-11 06:38:08','2023-05-07 10:41:01');
INSERT INTO `popups` VALUES (14,8,7,'64577d4bcea74.png','Flash Sale','2079FF',NULL,'Hurry, Sale Ends This Friday','This is your last chance to save 30%','Yes, I Want to Save 30%','2079FF','https://codecanyon8.kreativdev.com/evento','2026-05-07','12:00:00',1500,5,0,'2021-08-11 07:15:16','2023-05-07 10:40:53');
INSERT INTO `popups` VALUES (20,8,5,'64577d6d84030.png','Email Popup',NULL,NULL,'Get 10% off your first order','Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt','Subscribe','2079FF',NULL,NULL,NULL,1500,2,0,'2022-05-17 08:08:14','2023-05-07 10:29:24');
INSERT INTO `popups` VALUES (21,8,6,'64577d905ecf9.png','Countdown Popup',NULL,NULL,'Hurry, Sale Ends This Friday','This is your last chance to save 30%','Yes,I Want to Save 30%','2079FF','https://codecanyon8.kreativdev.com/evento','2025-05-16','12:00:00',1000,1,0,'2022-05-17 08:10:41','2023-05-09 11:07:14');
/*!40000 ALTER TABLE `popups` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '1-yes, 0-no',
  `is_feature` int(11) NOT NULL DEFAULT 0 COMMENT '1-yes, 0-no',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (2,'Electronic  Accessories','Electronic-Accessories',8,1,1,'2023-05-07 08:55:58','2023-05-07 09:01:02');
INSERT INTO `product_categories` VALUES (3,'Fashion & Beauty','Fashion-&-Beauty',8,1,1,'2023-05-07 08:58:15','2023-05-07 08:59:34');
INSERT INTO `product_categories` VALUES (4,'Home Appliances','home-appliances',8,1,1,'2023-05-07 08:58:42','2023-05-07 08:58:52');
INSERT INTO `product_categories` VALUES (5,'Books','Books',8,1,1,'2023-05-07 08:59:58','2023-05-07 09:02:49');
INSERT INTO `product_categories` VALUES (6,'الملحقات الإلكترونية','الملحقات-الإلكترونية',22,1,1,'2023-05-07 09:01:30','2023-05-07 09:03:28');
INSERT INTO `product_categories` VALUES (7,'الموضة والجمال','الموضة-والجمال',22,1,1,'2023-05-07 09:02:00','2023-05-07 09:03:27');
INSERT INTO `product_categories` VALUES (8,'المنزليه','المنزليه',22,1,1,'2023-05-07 09:02:20','2023-05-07 09:03:25');
INSERT INTO `product_categories` VALUES (9,'الكتب','الكتب',22,1,1,'2023-05-07 09:02:55','2023-05-07 09:03:23');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `meta_description` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_contents` WRITE;
/*!40000 ALTER TABLE `product_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `billing_fname` varchar(255) DEFAULT NULL,
  `billing_lname` varchar(255) DEFAULT NULL,
  `billing_email` varchar(255) DEFAULT NULL,
  `billing_phone` varchar(255) DEFAULT NULL,
  `billing_country` varchar(255) DEFAULT NULL,
  `billing_state` varchar(255) DEFAULT NULL,
  `billing_city` varchar(255) DEFAULT NULL,
  `billing_zip_code` varchar(255) DEFAULT NULL,
  `billing_address` varchar(255) DEFAULT NULL,
  `shipping_fname` varchar(255) DEFAULT NULL,
  `shipping_lname` varchar(255) DEFAULT NULL,
  `shipping_email` varchar(255) DEFAULT NULL,
  `shipping_phone` varchar(255) DEFAULT NULL,
  `shipping_country` varchar(255) DEFAULT NULL,
  `shipping_state` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(255) DEFAULT NULL,
  `shipping_zip_code` varchar(255) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `cart_total` decimal(8,2) DEFAULT NULL,
  `discount` decimal(8,2) DEFAULT NULL,
  `tax` varchar(255) DEFAULT NULL,
  `tax_percentage` double(8,2) DEFAULT 0.00,
  `total` decimal(8,2) DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL,
  `gateway_type` varchar(255) DEFAULT NULL,
  `currency_text` varchar(255) DEFAULT NULL,
  `currency_text_position` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(255) DEFAULT NULL,
  `currency_symbol_position` varchar(255) DEFAULT NULL,
  `order_number` varchar(255) DEFAULT NULL,
  `shipping_method` varchar(255) DEFAULT NULL,
  `shipping_charge` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `order_status` varchar(255) DEFAULT NULL,
  `tnxid` varchar(255) DEFAULT NULL,
  `charge_id` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `receipt` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `conversation_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_orders` WRITE;
/*!40000 ALTER TABLE `product_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `review` float DEFAULT NULL,
  `comment` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock` varchar(255) DEFAULT NULL,
  `sku` int(11) DEFAULT NULL,
  `feature_image` varchar(255) DEFAULT NULL,
  `current_price` decimal(8,2) DEFAULT NULL,
  `previous_price` decimal(8,2) DEFAULT NULL,
  `is_feature` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL COMMENT 'digital-digital product, physical - physical product',
  `file_type` varchar(255) DEFAULT NULL COMMENT 'upload->file, link=>download_link',
  `download_file` varchar(255) DEFAULT NULL,
  `download_link` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subscribable_type` varchar(255) NOT NULL,
  `subscribable_id` bigint(20) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `public_key` varchar(255) DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_subscribable_type_subscribable_id_index` (`subscribable_type`,`subscribable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `quick_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quick_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `serial_number` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quick_links_language_id_foreign` (`language_id`),
  CONSTRAINT `quick_links_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `quick_links` WRITE;
/*!40000 ALTER TABLE `quick_links` DISABLE KEYS */;
INSERT INTO `quick_links` VALUES (3,8,'Terms & Conditions','https://tica.ng/terms-&-conditions',1,'2021-06-22 22:52:38','2025-07-09 06:44:02');
INSERT INTO `quick_links` VALUES (4,8,'About Us','https://tica.ng/about-us',2,'2021-06-22 22:53:09','2025-07-09 06:43:38');
INSERT INTO `quick_links` VALUES (5,8,'Contact Us','https://tica.ng/contact',1,'2021-06-22 22:53:27','2025-07-09 06:43:20');
INSERT INTO `quick_links` VALUES (9,8,'Organizers','https://tica.ng/organizers',3,'2022-10-03 00:47:32','2025-07-09 06:43:05');
INSERT INTO `quick_links` VALUES (14,8,'Privacy Policy','https://tica.ng/privacy-policy/',6,'2025-11-10 10:54:05','2025-11-10 10:55:14');
/*!40000 ALTER TABLE `quick_links` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (4,'Admin','[\"Admin Management\",\"Basic Settings\",\"Payment Gateways\",\"Push Notification\",\"Subscribers\",\"Announcement Popups\",\"Advertise\",\"FAQ Management\",\"Blog Management\",\"Custom Pages\",\"Footer\",\"Home Page\",\"Support Ticket\",\"Customer Management\",\"Organizer Mangement\",\"Transaction\",\"Event Management\",\"Event Bookings\",\"Withdraw Method\",\"Menu Builder\",\"Lifetime Earning\",\"Total Profit\"]','2021-08-06 22:42:38','2026-04-30 14:06:13');
INSERT INTO `role_permissions` VALUES (6,'Moderator','[\"Support Ticket\"]','2021-08-07 22:14:34','2023-05-03 12:59:16');
INSERT INTO `role_permissions` VALUES (14,'Supervisor',NULL,'2021-11-24 22:48:53','2021-11-24 23:12:56');
INSERT INTO `role_permissions` VALUES (15,'Tica Admin Support','[\"Push Notification\",\"Subscribers\",\"Announcement Popups\",\"Advertise\",\"FAQ Management\",\"Blog Management\",\"Customer Management\",\"Organizer Mangement\",\"Transaction\",\"Event Management\",\"Event Bookings\",\"Withdraw Method\"]','2025-09-09 03:18:19','2025-10-16 17:31:38');
INSERT INTO `role_permissions` VALUES (16,'Tica Customer Service','[\"Subscribers\",\"FAQ Management\",\"Blog Management\",\"Customer Management\",\"Event Management\"]','2025-09-09 03:20:10','2025-09-09 18:34:54');
INSERT INTO `role_permissions` VALUES (17,'Ticket Checker','[\"Organizer Mangement\"]','2025-11-22 23:22:07','2025-11-22 23:22:20');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `section_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_titles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `event_section_title` varchar(255) DEFAULT NULL,
  `category_section_title` varchar(255) DEFAULT NULL,
  `featured_instructors_section_title` varchar(255) DEFAULT NULL,
  `testimonials_section_title` varchar(255) DEFAULT NULL,
  `features_section_title` varchar(255) DEFAULT NULL,
  `blog_section_title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `section_titles` WRITE;
/*!40000 ALTER TABLE `section_titles` DISABLE KEYS */;
INSERT INTO `section_titles` VALUES (1,8,'Explore Tica Events','Explore Category','Our Instructors','Customer Feedbacks','Our Features','Latest Blog','2021-10-05 03:30:05','2025-07-09 05:01:51');
/*!40000 ALTER TABLE `section_titles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `categories_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `about_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `featured_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `features_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `how_work_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `testimonials_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `partner_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `footer_section_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (1,0,0,1,0,1,0,1,1,'2021-12-11 00:55:13','2026-04-13 01:05:50');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `seos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `meta_keyword_home` varchar(255) DEFAULT NULL,
  `meta_description_home` text DEFAULT NULL,
  `meta_keyword_event` varchar(255) DEFAULT NULL,
  `meta_description_event` text DEFAULT NULL,
  `meta_keyword_organizer` varchar(255) DEFAULT NULL,
  `meta_description_organizer` text DEFAULT NULL,
  `meta_keyword_shop` varchar(255) DEFAULT NULL,
  `meta_description_shop` text DEFAULT NULL,
  `meta_keyword_blog` varchar(255) DEFAULT NULL,
  `meta_description_blog` text DEFAULT NULL,
  `meta_keyword_faq` varchar(255) DEFAULT NULL,
  `meta_description_faq` text DEFAULT NULL,
  `meta_keyword_contact` varchar(255) DEFAULT NULL,
  `meta_description_contact` text DEFAULT NULL,
  `meta_description_about` varchar(255) DEFAULT NULL,
  `meta_keyword_about` varchar(255) DEFAULT NULL,
  `meta_keyword_customer_login` varchar(255) DEFAULT NULL,
  `meta_description_customer_login` text DEFAULT NULL,
  `meta_keyword_customer_signup` varchar(255) DEFAULT NULL,
  `meta_description_customer_signup` text DEFAULT NULL,
  `meta_keyword_organizer_login` varchar(255) DEFAULT NULL,
  `meta_description_organizer_login` text DEFAULT NULL,
  `meta_keyword_organizer_signup` varchar(255) DEFAULT NULL,
  `meta_description_organizer_signup` text DEFAULT NULL,
  `meta_keyword_customer_forget_password` varchar(255) DEFAULT NULL,
  `meta_description_customer_forget_password` text DEFAULT NULL,
  `meta_keyword_organizer_forget_password` varchar(255) DEFAULT NULL,
  `meta_description_organizer_forget_password` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seos_language_id_foreign` (`language_id`),
  CONSTRAINT `seos_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `seos` WRITE;
/*!40000 ALTER TABLE `seos` DISABLE KEYS */;
INSERT INTO `seos` VALUES (2,8,'Tica | Buy and Sell Event Tickets Online in Nigeria','Buy and sell event tickets online with fast checkout and secure payments. Explore concerts, sports, festivals, and local events. Book your tickets today.','Events,event tickets, buy tickets online, concert tickets, sports tickets, festival tickets, local events tickets, secure ticket booking, ticketing platform','Buy and sell tickets for concerts, sports, festivals, and local events in Nigeria with Tica. Enjoy fast booking, secure payments, and instant confirmation.','event tickets, buy tickets online, concert tickets, sports tickets, festival tickets, local events tickets, secure ticket booking, ticketing platform','Create yoiur events and sell ticket only on Tica.ng','Shop','Shop Description','blog','Blog Description','faq','Frequently asked question on Tica','Tica Contact us','Tica Contact','about us descriptions','about,us','Tica Customer Login','Login to your tica customer account','signup','Signup Description','organizer_login','Organizer Login Description','Organizer_signup','Organizer Signup Page','forget password','Forget Password Description','Organizer_forget','Organizer forget password','2021-07-30 05:57:39','2025-11-29 16:53:50');
/*!40000 ALTER TABLE `seos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `shipping_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_charges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `text` varchar(255) DEFAULT NULL,
  `days` varchar(255) DEFAULT NULL,
  `charge` decimal(11,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `shipping_charges` WRITE;
/*!40000 ALTER TABLE `shipping_charges` DISABLE KEYS */;
INSERT INTO `shipping_charges` VALUES (7,'Method Two',8,'Method Two Shipping Charge',NULL,10.00,'2022-06-26 00:31:09','2023-05-06 10:40:35');
INSERT INTO `shipping_charges` VALUES (11,'Method One',8,'Method One shipping charge',NULL,12.00,'2022-07-01 23:06:39','2023-05-06 10:40:16');
/*!40000 ALTER TABLE `shipping_charges` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `shop_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `value` decimal(11,2) DEFAULT NULL,
  `start_date` varchar(255) DEFAULT NULL,
  `end_date` varchar(255) DEFAULT NULL,
  `minimum_spend` decimal(11,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `shop_coupons` WRITE;
/*!40000 ALTER TABLE `shop_coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_coupons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `social_medias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_medias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `icon` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `serial_number` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `social_medias` WRITE;
/*!40000 ALTER TABLE `social_medias` DISABLE KEYS */;
INSERT INTO `social_medias` VALUES (36,'fab fa-facebook-f','https://web.facebook.com/profile.php?id=61578202947876',1,'2021-11-20 03:01:42','2025-07-10 08:30:37');
INSERT INTO `social_medias` VALUES (37,'fab fa-twitter','https://x.com/tica_ng',3,'2021-11-20 03:03:22','2025-07-10 08:31:03');
INSERT INTO `social_medias` VALUES (38,'fab fa-instagram','https://www.instagram.com/tica.ng/',2,'2021-11-20 03:04:29','2025-07-10 08:31:26');
/*!40000 ALTER TABLE `social_medias` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `country_id` mediumint(8) unsigned NOT NULL,
  `country_code` char(2) NOT NULL,
  `fips_code` varchar(255) DEFAULT NULL,
  `iso2` varchar(255) DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `flag` tinyint(1) NOT NULL DEFAULT 1,
  `wikiDataId` varchar(255) DEFAULT NULL COMMENT 'Rapid API GeoDB Cities',
  PRIMARY KEY (`id`),
  KEY `country_region` (`country_id`),
  CONSTRAINT `country_region_final` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `states` WRITE;
/*!40000 ALTER TABLE `states` DISABLE KEYS */;
/*!40000 ALTER TABLE `states` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscribers_email_id_unique` (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `subscribers` WRITE;
/*!40000 ALTER TABLE `subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscribers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `support_ticket_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `support_ticket_statuses` WRITE;
/*!40000 ALTER TABLE `support_ticket_statuses` DISABLE KEYS */;
INSERT INTO `support_ticket_statuses` VALUES (1,'active','2022-06-25 03:52:18','2023-01-29 10:07:53');
/*!40000 ALTER TABLE `support_ticket_statuses` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `ticket_number` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '1-pending, 2-open, 3-closed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_message` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `testimonial_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonial_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `review_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `testimonial_sections` WRITE;
/*!40000 ALTER TABLE `testimonial_sections` DISABLE KEYS */;
INSERT INTO `testimonial_sections` VALUES (3,8,'What Our Clients Say about Us','Morbi volutpat luctus mauris id placerat. Aenean sit amet tincidunt quam. Aenean pretium euismod ligula, quis suscipit dui.','629f26d7b602d.jpg','12k Clients reviews','2022-06-07 04:22:15','2022-06-07 04:24:54');
INSERT INTO `testimonial_sections` VALUES (4,9,'gdfsas','sdfa','629f2792b156e.jpg','sfdaf','2022-06-07 04:25:22','2022-06-07 04:25:22');
INSERT INTO `testimonial_sections` VALUES (5,17,'ما يقوله عملائنا عنا','الأحرف. خمسة قرون من الزمن لم تقضي على هذا النص، بل انه حتى صار','63d8ad0181103.png','2k','2023-01-31 05:54:09','2023-01-31 05:54:09');
INSERT INTO `testimonial_sections` VALUES (6,22,'ماذا يقول عملاؤنا عنا','وقبل وفنلندا اقتصادية كل, تسبب الأوربيين كلا كل. تطوير الساحة ا','64587a5803048.jpg','ألف مراجعات العملاء','2023-05-08 04:28:08','2023-05-08 04:29:14');
/*!40000 ALTER TABLE `testimonial_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `image` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `occupation` varchar(255) NOT NULL,
  `rating` int(11) DEFAULT 0,
  `comment` text NOT NULL,
  `serial_number` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (6,8,'6345065d82969.jpg','Jane Doe','Chief marketing officer',5,'Our service is free to users because vendors pay us when they receive web traffic. We list all vendors - not just those that pay us',1,'2021-10-11 03:21:50','2023-05-08 04:41:31');
INSERT INTO `testimonials` VALUES (9,8,'63450650b0f0a.jpg','Jef Hardy','Chief executive officer (CEO)',4,'Our service is free to users because vendors pay us when they receive web traffic. We list all vendors - not  justfdfdhghdd ghdfghdfdg',2,'2021-12-15 03:38:04','2023-05-08 04:41:20');
INSERT INTO `testimonials` VALUES (10,8,'63450657af7b1.jpg','Matt Hardy','Manager',5,'Our service is free to users because vendors pay us when they receive web traffic. We list all vendors - not  just those that pay us',3,'2021-12-15 03:40:37','2023-05-08 04:41:04');
INSERT INTO `testimonials` VALUES (15,8,'64587ddb29fdc.jpg','Patty O’Furniture','Chief financial officer',4,'While lorem ipsum\'s still resembles classical Latin, it actually has no meaning whatsoever. As Cicero\'s text doesn',4,'2023-05-08 04:43:07','2023-05-08 04:43:59');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ticket_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) DEFAULT NULL,
  `ticket_id` bigint(20) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ticket_contents` WRITE;
/*!40000 ALTER TABLE `ticket_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `event_type` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `ticket_available_type` varchar(255) DEFAULT NULL,
  `ticket_available` int(11) DEFAULT NULL,
  `max_ticket_buy_type` varchar(255) DEFAULT NULL,
  `max_buy_ticket` int(11) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `pricing_type` varchar(255) DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `f_price` float DEFAULT NULL,
  `early_bird_discount` varchar(255) NOT NULL DEFAULT 'disable',
  `early_bird_discount_amount` varchar(255) DEFAULT NULL,
  `early_bird_discount_type` varchar(255) DEFAULT NULL,
  `early_bird_discount_date` varchar(255) DEFAULT NULL,
  `early_bird_discount_time` varchar(255) DEFAULT NULL,
  `variations` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `timezones` (
  `country_code` char(3) NOT NULL,
  `timezone` varchar(125) NOT NULL DEFAULT '',
  `gmt_offset` float(10,2) DEFAULT NULL,
  `dst_offset` float(10,2) DEFAULT NULL,
  `raw_offset` float(10,2) DEFAULT NULL,
  PRIMARY KEY (`country_code`,`timezone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `timezones` WRITE;
/*!40000 ALTER TABLE `timezones` DISABLE KEYS */;
INSERT INTO `timezones` VALUES ('AD','Europe/Andorra',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('AE','Asia/Dubai',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('AF','Asia/Kabul',4.50,4.50,4.50);
INSERT INTO `timezones` VALUES ('AG','America/Antigua',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('AI','America/Anguilla',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('AL','Europe/Tirane',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('AM','Asia/Yerevan',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('AO','Africa/Luanda',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Casey',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Davis',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/DumontDUrville',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Mawson',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/McMurdo',13.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Palmer',-3.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Rothera',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/South_Pole',13.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Syowa',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('AQ','Antarctica/Vostok',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Buenos_Aires',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Catamarca',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Cordoba',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Jujuy',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/La_Rioja',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Mendoza',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Rio_Gallegos',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Salta',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/San_Juan',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/San_Luis',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Tucuman',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AR','America/Argentina/Ushuaia',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('AS','Pacific/Pago_Pago',-11.00,-11.00,-11.00);
INSERT INTO `timezones` VALUES ('AT','Europe/Vienna',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('AU','Antarctica/Macquarie',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Adelaide',10.50,9.50,9.50);
INSERT INTO `timezones` VALUES ('AU','Australia/Brisbane',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Broken_Hill',10.50,9.50,9.50);
INSERT INTO `timezones` VALUES ('AU','Australia/Currie',11.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Darwin',9.50,9.50,9.50);
INSERT INTO `timezones` VALUES ('AU','Australia/Eucla',8.75,8.75,8.75);
INSERT INTO `timezones` VALUES ('AU','Australia/Hobart',11.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Lindeman',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Lord_Howe',11.00,10.50,10.50);
INSERT INTO `timezones` VALUES ('AU','Australia/Melbourne',11.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Perth',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('AU','Australia/Sydney',11.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('AW','America/Aruba',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('AX','Europe/Mariehamn',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('AZ','Asia/Baku',4.00,5.00,4.00);
INSERT INTO `timezones` VALUES ('BA','Europe/Sarajevo',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('BB','America/Barbados',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BD','Asia/Dhaka',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('BE','Europe/Brussels',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('BF','Africa/Ouagadougou',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('BG','Europe/Sofia',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('BH','Asia/Bahrain',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('BI','Africa/Bujumbura',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('BJ','Africa/Porto-Novo',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('BL','America/St_Barthelemy',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BM','Atlantic/Bermuda',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('BN','Asia/Brunei',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('BO','America/La_Paz',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BQ','America/Kralendijk',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Araguaina',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Bahia',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Belem',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Boa_Vista',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Campo_Grande',-3.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Cuiaba',-3.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Eirunepe',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('BR','America/Fortaleza',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Maceio',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Manaus',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Noronha',-2.00,-2.00,-2.00);
INSERT INTO `timezones` VALUES ('BR','America/Porto_Velho',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('BR','America/Recife',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Rio_Branco',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('BR','America/Santarem',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BR','America/Sao_Paulo',-2.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('BS','America/Nassau',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('BT','Asia/Thimphu',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('BW','Africa/Gaborone',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('BY','Europe/Minsk',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('BZ','America/Belize',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Atikokan',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Blanc-Sablon',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('CA','America/Cambridge_Bay',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('CA','America/Creston',-7.00,-7.00,-7.00);
INSERT INTO `timezones` VALUES ('CA','America/Dawson',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('CA','America/Dawson_Creek',-7.00,-7.00,-7.00);
INSERT INTO `timezones` VALUES ('CA','America/Edmonton',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('CA','America/Glace_Bay',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('CA','America/Goose_Bay',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('CA','America/Halifax',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('CA','America/Inuvik',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('CA','America/Iqaluit',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Moncton',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('CA','America/Montreal',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Nipigon',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Pangnirtung',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Rainy_River',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Rankin_Inlet',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Regina',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Resolute',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/St_Johns',-3.50,-2.50,-3.50);
INSERT INTO `timezones` VALUES ('CA','America/Swift_Current',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Thunder_Bay',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Toronto',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CA','America/Vancouver',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('CA','America/Whitehorse',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('CA','America/Winnipeg',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('CA','America/Yellowknife',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('CC','Indian/Cocos',6.50,6.50,6.50);
INSERT INTO `timezones` VALUES ('CD','Africa/Kinshasa',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('CD','Africa/Lubumbashi',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('CF','Africa/Bangui',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('CG','Africa/Brazzaville',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('CH','Europe/Zurich',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('CI','Africa/Abidjan',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('CK','Pacific/Rarotonga',-10.00,-10.00,-10.00);
INSERT INTO `timezones` VALUES ('CL','America/Santiago',-3.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('CL','Pacific/Easter',-5.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('CM','Africa/Douala',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('CN','Asia/Chongqing',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('CN','Asia/Harbin',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('CN','Asia/Kashgar',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('CN','Asia/Shanghai',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('CN','Asia/Urumqi',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('CO','America/Bogota',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('CR','America/Costa_Rica',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('CU','America/Havana',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('CV','Atlantic/Cape_Verde',-1.00,-1.00,-1.00);
INSERT INTO `timezones` VALUES ('CW','America/Curacao',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('CX','Indian/Christmas',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('CY','Asia/Nicosia',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('CZ','Europe/Prague',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('DE','Europe/Berlin',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('DE','Europe/Busingen',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('DJ','Africa/Djibouti',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('DK','Europe/Copenhagen',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('DM','America/Dominica',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('DO','America/Santo_Domingo',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('DZ','Africa/Algiers',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('EC','America/Guayaquil',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('EC','Pacific/Galapagos',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('EE','Europe/Tallinn',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('EG','Africa/Cairo',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('EH','Africa/El_Aaiun',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('ER','Africa/Asmara',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('ES','Africa/Ceuta',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('ES','Atlantic/Canary',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('ES','Europe/Madrid',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('ET','Africa/Addis_Ababa',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('FI','Europe/Helsinki',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('FJ','Pacific/Fiji',13.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('FK','Atlantic/Stanley',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('FM','Pacific/Chuuk',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('FM','Pacific/Kosrae',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('FM','Pacific/Pohnpei',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('FO','Atlantic/Faroe',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('FR','Europe/Paris',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('GA','Africa/Libreville',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('GB','Europe/London',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('GD','America/Grenada',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('GE','Asia/Tbilisi',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('GF','America/Cayenne',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('GG','Europe/Guernsey',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('GH','Africa/Accra',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('GI','Europe/Gibraltar',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('GL','America/Danmarkshavn',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('GL','America/Godthab',-3.00,-2.00,-3.00);
INSERT INTO `timezones` VALUES ('GL','America/Scoresbysund',-1.00,0.00,-1.00);
INSERT INTO `timezones` VALUES ('GL','America/Thule',-4.00,-3.00,-4.00);
INSERT INTO `timezones` VALUES ('GM','Africa/Banjul',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('GN','Africa/Conakry',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('GP','America/Guadeloupe',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('GQ','Africa/Malabo',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('GR','Europe/Athens',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('GS','Atlantic/South_Georgia',-2.00,-2.00,-2.00);
INSERT INTO `timezones` VALUES ('GT','America/Guatemala',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('GU','Pacific/Guam',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('GW','Africa/Bissau',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('GY','America/Guyana',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('HK','Asia/Hong_Kong',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('HN','America/Tegucigalpa',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('HR','Europe/Zagreb',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('HT','America/Port-au-Prince',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('HU','Europe/Budapest',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('ID','Asia/Jakarta',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('ID','Asia/Jayapura',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('ID','Asia/Makassar',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('ID','Asia/Pontianak',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('IE','Europe/Dublin',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('IL','Asia/Jerusalem',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('IM','Europe/Isle_of_Man',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('IN','Asia/Kolkata',5.50,5.50,5.50);
INSERT INTO `timezones` VALUES ('IO','Indian/Chagos',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('IQ','Asia/Baghdad',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('IR','Asia/Tehran',3.50,4.50,3.50);
INSERT INTO `timezones` VALUES ('IS','Atlantic/Reykjavik',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('IT','Europe/Rome',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('JE','Europe/Jersey',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('JM','America/Jamaica',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('JO','Asia/Amman',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('JP','Asia/Tokyo',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('KE','Africa/Nairobi',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('KG','Asia/Bishkek',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('KH','Asia/Phnom_Penh',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('KI','Pacific/Enderbury',13.00,13.00,13.00);
INSERT INTO `timezones` VALUES ('KI','Pacific/Kiritimati',14.00,14.00,14.00);
INSERT INTO `timezones` VALUES ('KI','Pacific/Tarawa',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('KM','Indian/Comoro',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('KN','America/St_Kitts',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('KP','Asia/Pyongyang',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('KR','Asia/Seoul',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('KW','Asia/Kuwait',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('KY','America/Cayman',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('KZ','Asia/Almaty',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('KZ','Asia/Aqtau',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('KZ','Asia/Aqtobe',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('KZ','Asia/Oral',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('KZ','Asia/Qyzylorda',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('LA','Asia/Vientiane',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('LB','Asia/Beirut',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('LC','America/St_Lucia',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('LI','Europe/Vaduz',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('LK','Asia/Colombo',5.50,5.50,5.50);
INSERT INTO `timezones` VALUES ('LR','Africa/Monrovia',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('LS','Africa/Maseru',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('LT','Europe/Vilnius',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('LU','Europe/Luxembourg',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('LV','Europe/Riga',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('LY','Africa/Tripoli',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('MA','Africa/Casablanca',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('MC','Europe/Monaco',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('MD','Europe/Chisinau',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('ME','Europe/Podgorica',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('MF','America/Marigot',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('MG','Indian/Antananarivo',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('MH','Pacific/Kwajalein',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('MH','Pacific/Majuro',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('MK','Europe/Skopje',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('ML','Africa/Bamako',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('MM','Asia/Rangoon',6.50,6.50,6.50);
INSERT INTO `timezones` VALUES ('MN','Asia/Choibalsan',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('MN','Asia/Hovd',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('MN','Asia/Ulaanbaatar',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('MO','Asia/Macau',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('MP','Pacific/Saipan',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('MQ','America/Martinique',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('MR','Africa/Nouakchott',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('MS','America/Montserrat',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('MT','Europe/Malta',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('MU','Indian/Mauritius',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('MV','Indian/Maldives',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('MW','Africa/Blantyre',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('MX','America/Bahia_Banderas',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Cancun',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Chihuahua',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('MX','America/Hermosillo',-7.00,-7.00,-7.00);
INSERT INTO `timezones` VALUES ('MX','America/Matamoros',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Mazatlan',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('MX','America/Merida',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Mexico_City',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Monterrey',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('MX','America/Ojinaga',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('MX','America/Santa_Isabel',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('MX','America/Tijuana',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('MY','Asia/Kuala_Lumpur',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('MY','Asia/Kuching',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('MZ','Africa/Maputo',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('NA','Africa/Windhoek',2.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('NC','Pacific/Noumea',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('NE','Africa/Niamey',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('NF','Pacific/Norfolk',11.50,11.50,11.50);
INSERT INTO `timezones` VALUES ('NG','Africa/Lagos',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('NI','America/Managua',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('NL','Europe/Amsterdam',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('NO','Europe/Oslo',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('NP','Asia/Kathmandu',5.75,5.75,5.75);
INSERT INTO `timezones` VALUES ('NR','Pacific/Nauru',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('NU','Pacific/Niue',-11.00,-11.00,-11.00);
INSERT INTO `timezones` VALUES ('NZ','Pacific/Auckland',13.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('NZ','Pacific/Chatham',13.75,12.75,12.75);
INSERT INTO `timezones` VALUES ('OM','Asia/Muscat',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('PA','America/Panama',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('PE','America/Lima',-5.00,-5.00,-5.00);
INSERT INTO `timezones` VALUES ('PF','Pacific/Gambier',-9.00,-9.00,-9.00);
INSERT INTO `timezones` VALUES ('PF','Pacific/Marquesas',-9.50,-9.50,-9.50);
INSERT INTO `timezones` VALUES ('PF','Pacific/Tahiti',-10.00,-10.00,-10.00);
INSERT INTO `timezones` VALUES ('PG','Pacific/Port_Moresby',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('PH','Asia/Manila',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('PK','Asia/Karachi',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('PL','Europe/Warsaw',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('PM','America/Miquelon',-3.00,-2.00,-3.00);
INSERT INTO `timezones` VALUES ('PN','Pacific/Pitcairn',-8.00,-8.00,-8.00);
INSERT INTO `timezones` VALUES ('PR','America/Puerto_Rico',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('PS','Asia/Gaza',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('PS','Asia/Hebron',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('PT','Atlantic/Azores',-1.00,0.00,-1.00);
INSERT INTO `timezones` VALUES ('PT','Atlantic/Madeira',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('PT','Europe/Lisbon',0.00,1.00,0.00);
INSERT INTO `timezones` VALUES ('PW','Pacific/Palau',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('PY','America/Asuncion',-3.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('QA','Asia/Qatar',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('RE','Indian/Reunion',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('RO','Europe/Bucharest',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('RS','Europe/Belgrade',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Anadyr',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Irkutsk',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Kamchatka',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Khandyga',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Krasnoyarsk',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Magadan',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Novokuznetsk',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Novosibirsk',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Omsk',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Sakhalin',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Ust-Nera',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Vladivostok',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Yakutsk',10.00,10.00,10.00);
INSERT INTO `timezones` VALUES ('RU','Asia/Yekaterinburg',6.00,6.00,6.00);
INSERT INTO `timezones` VALUES ('RU','Europe/Kaliningrad',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('RU','Europe/Moscow',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('RU','Europe/Samara',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('RU','Europe/Volgograd',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('RW','Africa/Kigali',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('SA','Asia/Riyadh',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('SB','Pacific/Guadalcanal',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('SC','Indian/Mahe',4.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('SD','Africa/Khartoum',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('SE','Europe/Stockholm',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('SG','Asia/Singapore',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('SH','Atlantic/St_Helena',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('SI','Europe/Ljubljana',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('SJ','Arctic/Longyearbyen',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('SK','Europe/Bratislava',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('SL','Africa/Freetown',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('SM','Europe/San_Marino',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('SN','Africa/Dakar',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('SO','Africa/Mogadishu',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('SR','America/Paramaribo',-3.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('SS','Africa/Juba',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('ST','Africa/Sao_Tome',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('SV','America/El_Salvador',-6.00,-6.00,-6.00);
INSERT INTO `timezones` VALUES ('SX','America/Lower_Princes',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('SY','Asia/Damascus',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('SZ','Africa/Mbabane',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('TC','America/Grand_Turk',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('TD','Africa/Ndjamena',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('TF','Indian/Kerguelen',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('TG','Africa/Lome',0.00,0.00,0.00);
INSERT INTO `timezones` VALUES ('TH','Asia/Bangkok',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('TJ','Asia/Dushanbe',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('TK','Pacific/Fakaofo',13.00,13.00,13.00);
INSERT INTO `timezones` VALUES ('TL','Asia/Dili',9.00,9.00,9.00);
INSERT INTO `timezones` VALUES ('TM','Asia/Ashgabat',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('TN','Africa/Tunis',1.00,1.00,1.00);
INSERT INTO `timezones` VALUES ('TO','Pacific/Tongatapu',13.00,13.00,13.00);
INSERT INTO `timezones` VALUES ('TR','Europe/Istanbul',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('TT','America/Port_of_Spain',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('TV','Pacific/Funafuti',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('TW','Asia/Taipei',8.00,8.00,8.00);
INSERT INTO `timezones` VALUES ('TZ','Africa/Dar_es_Salaam',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('UA','Europe/Kiev',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('UA','Europe/Simferopol',2.00,4.00,4.00);
INSERT INTO `timezones` VALUES ('UA','Europe/Uzhgorod',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('UA','Europe/Zaporozhye',2.00,3.00,2.00);
INSERT INTO `timezones` VALUES ('UG','Africa/Kampala',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('UM','Pacific/Johnston',-10.00,-10.00,-10.00);
INSERT INTO `timezones` VALUES ('UM','Pacific/Midway',-11.00,-11.00,-11.00);
INSERT INTO `timezones` VALUES ('UM','Pacific/Wake',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('US','America/Adak',-10.00,-9.00,-10.00);
INSERT INTO `timezones` VALUES ('US','America/Anchorage',-9.00,-8.00,-9.00);
INSERT INTO `timezones` VALUES ('US','America/Boise',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('US','America/Chicago',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/Denver',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('US','America/Detroit',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Indianapolis',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Knox',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Marengo',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Petersburg',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Tell_City',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Vevay',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Vincennes',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Indiana/Winamac',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Juneau',-9.00,-8.00,-9.00);
INSERT INTO `timezones` VALUES ('US','America/Kentucky/Louisville',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Kentucky/Monticello',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Los_Angeles',-8.00,-7.00,-8.00);
INSERT INTO `timezones` VALUES ('US','America/Menominee',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/Metlakatla',-8.00,-8.00,-8.00);
INSERT INTO `timezones` VALUES ('US','America/New_York',-5.00,-4.00,-5.00);
INSERT INTO `timezones` VALUES ('US','America/Nome',-9.00,-8.00,-9.00);
INSERT INTO `timezones` VALUES ('US','America/North_Dakota/Beulah',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/North_Dakota/Center',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/North_Dakota/New_Salem',-6.00,-5.00,-6.00);
INSERT INTO `timezones` VALUES ('US','America/Phoenix',-7.00,-7.00,-7.00);
INSERT INTO `timezones` VALUES ('US','America/Shiprock',-7.00,-6.00,-7.00);
INSERT INTO `timezones` VALUES ('US','America/Sitka',-9.00,-8.00,-9.00);
INSERT INTO `timezones` VALUES ('US','America/Yakutat',-9.00,-8.00,-9.00);
INSERT INTO `timezones` VALUES ('US','Pacific/Honolulu',-10.00,-10.00,-10.00);
INSERT INTO `timezones` VALUES ('UY','America/Montevideo',-2.00,-3.00,-3.00);
INSERT INTO `timezones` VALUES ('UZ','Asia/Samarkand',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('UZ','Asia/Tashkent',5.00,5.00,5.00);
INSERT INTO `timezones` VALUES ('VA','Europe/Vatican',1.00,2.00,1.00);
INSERT INTO `timezones` VALUES ('VC','America/St_Vincent',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('VE','America/Caracas',-4.50,-4.50,-4.50);
INSERT INTO `timezones` VALUES ('VG','America/Tortola',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('VI','America/St_Thomas',-4.00,-4.00,-4.00);
INSERT INTO `timezones` VALUES ('VN','Asia/Ho_Chi_Minh',7.00,7.00,7.00);
INSERT INTO `timezones` VALUES ('VU','Pacific/Efate',11.00,11.00,11.00);
INSERT INTO `timezones` VALUES ('WF','Pacific/Wallis',12.00,12.00,12.00);
INSERT INTO `timezones` VALUES ('WS','Pacific/Apia',14.00,13.00,13.00);
INSERT INTO `timezones` VALUES ('YE','Asia/Aden',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('YT','Indian/Mayotte',3.00,3.00,3.00);
INSERT INTO `timezones` VALUES ('ZA','Africa/Johannesburg',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('ZM','Africa/Lusaka',2.00,2.00,2.00);
INSERT INTO `timezones` VALUES ('ZW','Africa/Harare',2.00,2.00,2.00);
/*!40000 ALTER TABLE `timezones` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transcation_id` varchar(255) DEFAULT NULL,
  `booking_id` varchar(255) DEFAULT NULL,
  `transcation_type` int(11) DEFAULT NULL COMMENT '1=event, 2=product, 3= withdraw, 4= balance add, 5 = balance subtract',
  `customer_id` bigint(20) DEFAULT NULL,
  `organizer_id` bigint(20) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `grand_total` double(8,2) DEFAULT NULL,
  `commission` float(8,2) DEFAULT 0.00,
  `tax` float(8,2) DEFAULT 0.00,
  `pre_balance` float(8,2) DEFAULT 0.00,
  `after_balance` float(8,2) DEFAULT 0.00,
  `gateway_type` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(255) DEFAULT NULL,
  `currency_symbol_position` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0 -> banned or deactive, 1 -> active',
  `verification_token` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `edit_profile_status` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0 -> not edited user profile, 1 -> edited user profile',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `variation_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variation_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) DEFAULT NULL,
  `ticket_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `variation_contents` WRITE;
/*!40000 ALTER TABLE `variation_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `variation_contents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `withdraw_method_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdraw_method_inputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `withdraw_payment_method_id` int(11) NOT NULL,
  `type` tinyint(4) DEFAULT NULL COMMENT '1-text, 2-select, 3-checkbox, 4-textarea, 5-datepicker, 6-timepicker, 7-number',
  `label` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `required` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1-required, 0- optional',
  `order_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `withdraw_method_inputs` WRITE;
/*!40000 ALTER TABLE `withdraw_method_inputs` DISABLE KEYS */;
INSERT INTO `withdraw_method_inputs` VALUES (20,8,1,'Account Name','Account_Name','Enter Account Name',1,1,'2025-09-30 00:21:09','2025-09-30 00:21:09');
INSERT INTO `withdraw_method_inputs` VALUES (21,8,7,'Account Number','Account_Number','Enter Account Number',1,2,'2025-09-30 00:21:37','2025-09-30 00:21:37');
INSERT INTO `withdraw_method_inputs` VALUES (22,8,1,'Bank Name','Bank_Name','Enter Bank Name',1,3,'2025-09-30 00:22:18','2025-09-30 00:22:18');
/*!40000 ALTER TABLE `withdraw_method_inputs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `withdraw_method_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdraw_method_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `withdraw_method_input_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `withdraw_method_options` WRITE;
/*!40000 ALTER TABLE `withdraw_method_options` DISABLE KEYS */;
/*!40000 ALTER TABLE `withdraw_method_options` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `withdraw_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdraw_payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fixed_charge` float(8,2) DEFAULT 0.00,
  `percentage_charge` float DEFAULT 0,
  `min_limit` varchar(255) NOT NULL,
  `max_limit` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `withdraw_payment_methods` WRITE;
/*!40000 ALTER TABLE `withdraw_payment_methods` DISABLE KEYS */;
INSERT INTO `withdraw_payment_methods` VALUES (8,NULL,NULL,'10000','500000000','Bank Payment',1,'2025-09-30 00:14:46','2026-01-12 21:40:32');
/*!40000 ALTER TABLE `withdraw_payment_methods` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `withdraws`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdraws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) NOT NULL,
  `withdraw_id` varchar(255) DEFAULT NULL,
  `method_id` int(11) NOT NULL,
  `amount` varchar(255) NOT NULL,
  `payable_amount` float(8,2) DEFAULT 0.00,
  `total_charge` float(8,2) DEFAULT 0.00,
  `additional_reference` longtext DEFAULT NULL,
  `feilds` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0-pending, 1-approved, 2-decline',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `withdraws` WRITE;
/*!40000 ALTER TABLE `withdraws` DISABLE KEYS */;
/*!40000 ALTER TABLE `withdraws` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

