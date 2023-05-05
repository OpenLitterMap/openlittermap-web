/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_verification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_verification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `photo_id` int(10) unsigned NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`added_tags`)),
  `removed_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`removed_tags`)),
  `rewarded_admin_xp` int(11) NOT NULL,
  `removed_user_xp` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_verification_logs_admin_id_foreign` (`admin_id`),
  CONSTRAINT `admin_verification_logs_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alcohol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alcohol` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `beerBottle` int(10) unsigned DEFAULT NULL,
  `spiritBottle` int(10) unsigned DEFAULT NULL,
  `wineBottle` int(10) unsigned DEFAULT NULL,
  `beerCan` int(10) unsigned DEFAULT NULL,
  `brokenGlass` int(10) unsigned DEFAULT NULL,
  `paperCardAlcoholPackaging` int(10) unsigned DEFAULT NULL,
  `plasticAlcoholPackaging` int(10) unsigned DEFAULT NULL,
  `bottleTops` int(10) unsigned DEFAULT NULL,
  `alcoholOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pint` int(10) unsigned DEFAULT NULL,
  `six_pack_rings` int(10) unsigned DEFAULT NULL,
  `alcohol_plastic_cups` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `annotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `photo_id` bigint(20) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `supercategory_id` int(10) unsigned DEFAULT NULL,
  `segmentation` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bbox` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_crowd` tinyint(1) NOT NULL DEFAULT 0,
  `area` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_id` int(10) unsigned DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `added_by` int(10) unsigned DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `reward` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adidas` int(10) unsigned DEFAULT NULL,
  `amazon` int(10) unsigned DEFAULT NULL,
  `apple` int(10) unsigned DEFAULT NULL,
  `budweiser` int(10) unsigned DEFAULT NULL,
  `coke` int(10) unsigned DEFAULT NULL,
  `colgate` int(10) unsigned DEFAULT NULL,
  `corona` int(10) unsigned DEFAULT NULL,
  `fritolay` int(10) unsigned DEFAULT NULL,
  `gillette` int(10) unsigned DEFAULT NULL,
  `heineken` int(10) unsigned DEFAULT NULL,
  `kellogs` int(10) unsigned DEFAULT NULL,
  `lego` int(10) unsigned DEFAULT NULL,
  `loreal` int(10) unsigned DEFAULT NULL,
  `nescafe` int(10) unsigned DEFAULT NULL,
  `nestle` int(10) unsigned DEFAULT NULL,
  `marlboro` int(10) unsigned DEFAULT NULL,
  `mcdonalds` int(10) unsigned DEFAULT NULL,
  `nike` int(10) unsigned DEFAULT NULL,
  `pepsi` int(10) unsigned DEFAULT NULL,
  `redbull` int(10) unsigned DEFAULT NULL,
  `samsung` int(10) unsigned DEFAULT NULL,
  `subway` int(10) unsigned DEFAULT NULL,
  `starbucks` int(10) unsigned DEFAULT NULL,
  `tayto` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `applegreen` int(10) unsigned DEFAULT NULL,
  `avoca` int(10) unsigned DEFAULT NULL,
  `bewleys` int(10) unsigned DEFAULT NULL,
  `brambles` int(10) unsigned DEFAULT NULL,
  `butlers` int(10) unsigned DEFAULT NULL,
  `cafe_nero` int(10) unsigned DEFAULT NULL,
  `centra` int(10) unsigned DEFAULT NULL,
  `costa` int(10) unsigned DEFAULT NULL,
  `esquires` int(10) unsigned DEFAULT NULL,
  `frank_and_honest` int(10) unsigned DEFAULT NULL,
  `insomnia` int(10) unsigned DEFAULT NULL,
  `lolly_and_cookes` int(10) unsigned DEFAULT NULL,
  `obriens` int(10) unsigned DEFAULT NULL,
  `supermacs` int(10) unsigned DEFAULT NULL,
  `wilde_and_greene` int(10) unsigned DEFAULT NULL,
  `asahi` int(10) unsigned DEFAULT NULL,
  `aldi` int(10) unsigned DEFAULT NULL,
  `ballygowan` int(10) unsigned DEFAULT NULL,
  `bulmers` int(10) unsigned DEFAULT NULL,
  `burgerking` int(10) unsigned DEFAULT NULL,
  `cadburys` int(10) unsigned DEFAULT NULL,
  `carlsberg` int(10) unsigned DEFAULT NULL,
  `coles` int(10) unsigned DEFAULT NULL,
  `circlek` int(10) unsigned DEFAULT NULL,
  `dunnes` int(10) unsigned DEFAULT NULL,
  `doritos` int(10) unsigned DEFAULT NULL,
  `drpepper` int(10) unsigned DEFAULT NULL,
  `duracell` int(10) unsigned DEFAULT NULL,
  `durex` int(10) unsigned DEFAULT NULL,
  `evian` int(10) unsigned DEFAULT NULL,
  `fosters` int(10) unsigned DEFAULT NULL,
  `gatorade` int(10) unsigned DEFAULT NULL,
  `guinness` int(10) unsigned DEFAULT NULL,
  `haribo` int(10) unsigned DEFAULT NULL,
  `kfc` int(10) unsigned DEFAULT NULL,
  `lidl` int(10) unsigned DEFAULT NULL,
  `lindenvillage` int(10) unsigned DEFAULT NULL,
  `lucozade` int(10) unsigned DEFAULT NULL,
  `nero` int(10) unsigned DEFAULT NULL,
  `mars` int(10) unsigned DEFAULT NULL,
  `powerade` int(10) unsigned DEFAULT NULL,
  `ribena` int(10) unsigned DEFAULT NULL,
  `sainsburys` int(10) unsigned DEFAULT NULL,
  `spar` int(10) unsigned DEFAULT NULL,
  `stella` int(10) unsigned DEFAULT NULL,
  `supervalu` int(10) unsigned DEFAULT NULL,
  `tesco` int(10) unsigned DEFAULT NULL,
  `thins` int(10) unsigned DEFAULT NULL,
  `volvic` int(10) unsigned DEFAULT NULL,
  `waitrose` int(10) unsigned DEFAULT NULL,
  `walkers` int(10) unsigned DEFAULT NULL,
  `woolworths` int(10) unsigned DEFAULT NULL,
  `wrigleys` int(10) unsigned DEFAULT NULL,
  `camel` int(10) unsigned DEFAULT NULL,
  `albertheijn` int(10) unsigned DEFAULT NULL,
  `aadrink` int(10) unsigned DEFAULT NULL,
  `amstel` int(10) unsigned DEFAULT NULL,
  `bacardi` int(10) unsigned DEFAULT NULL,
  `bullit` int(10) unsigned DEFAULT NULL,
  `caprisun` int(10) unsigned DEFAULT NULL,
  `fanta` int(10) unsigned DEFAULT NULL,
  `fernandes` int(10) unsigned DEFAULT NULL,
  `goldenpower` int(10) unsigned DEFAULT NULL,
  `hertog_jan` int(10) unsigned DEFAULT NULL,
  `lavish` int(10) unsigned DEFAULT NULL,
  `lipton` int(10) unsigned DEFAULT NULL,
  `monster` int(10) unsigned DEFAULT NULL,
  `schutters` int(10) unsigned DEFAULT NULL,
  `slammers` int(10) unsigned DEFAULT NULL,
  `spa` int(10) unsigned DEFAULT NULL,
  `modelo` int(10) unsigned DEFAULT NULL,
  `anheuser_busch` int(10) unsigned DEFAULT NULL,
  `molson_coors` int(10) unsigned DEFAULT NULL,
  `seven_eleven` int(10) unsigned DEFAULT NULL,
  `acadia` int(10) unsigned DEFAULT NULL,
  `calanda` int(10) unsigned DEFAULT NULL,
  `winston` int(10) unsigned DEFAULT NULL,
  `ok_` int(10) unsigned DEFAULT NULL,
  `tim_hortons` int(10) unsigned DEFAULT NULL,
  `wendys` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not found',
  `country_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_images` int(10) unsigned DEFAULT NULL,
  `total_smoking` int(10) unsigned DEFAULT NULL,
  `total_cigaretteButts` int(10) unsigned DEFAULT NULL,
  `total_food` int(10) unsigned DEFAULT NULL,
  `total_softdrinks` int(10) unsigned DEFAULT NULL,
  `total_plasticBottles` int(10) unsigned DEFAULT NULL,
  `total_alcohol` int(10) unsigned DEFAULT NULL,
  `total_coffee` int(10) unsigned DEFAULT NULL,
  `total_drugs` int(10) unsigned DEFAULT NULL,
  `total_needles` int(10) unsigned DEFAULT NULL,
  `total_sanitary` int(10) unsigned DEFAULT NULL,
  `total_other` int(10) unsigned DEFAULT NULL,
  `total_contributors` int(10) unsigned NOT NULL DEFAULT 0,
  `total_coastal` int(10) unsigned NOT NULL DEFAULT 0,
  `state_id` int(10) unsigned DEFAULT NULL,
  `total_pathways` int(10) unsigned DEFAULT NULL,
  `manual_verify` tinyint(1) NOT NULL DEFAULT 0,
  `total_art` int(10) unsigned DEFAULT NULL,
  `littercoin_paid` tinyint(1) NOT NULL DEFAULT 0,
  `littercoin_issued` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `total_brands` int(10) unsigned DEFAULT NULL,
  `total_adidas` int(10) unsigned DEFAULT NULL,
  `total_amazon` int(10) unsigned DEFAULT NULL,
  `total_apple` int(10) unsigned DEFAULT NULL,
  `total_budweiser` int(10) unsigned DEFAULT NULL,
  `total_coke` int(10) unsigned DEFAULT NULL,
  `total_colgate` int(10) unsigned DEFAULT NULL,
  `total_corona` int(10) unsigned DEFAULT NULL,
  `total_fritolay` int(10) unsigned DEFAULT NULL,
  `total_gillette` int(10) unsigned DEFAULT NULL,
  `total_heineken` int(10) unsigned DEFAULT NULL,
  `total_kellogs` int(10) unsigned DEFAULT NULL,
  `total_lego` int(10) unsigned DEFAULT NULL,
  `total_loreal` int(10) unsigned DEFAULT NULL,
  `total_nescafe` int(10) unsigned DEFAULT NULL,
  `total_nestle` int(10) unsigned DEFAULT NULL,
  `total_marlboro` int(10) unsigned DEFAULT NULL,
  `total_mcdonalds` int(10) unsigned DEFAULT NULL,
  `total_nike` int(10) unsigned DEFAULT NULL,
  `total_pepsi` int(10) unsigned DEFAULT NULL,
  `total_redbull` int(10) unsigned DEFAULT NULL,
  `total_samsung` int(10) unsigned DEFAULT NULL,
  `total_subway` int(10) unsigned DEFAULT NULL,
  `total_starbucks` int(10) unsigned DEFAULT NULL,
  `total_tayto` int(10) unsigned DEFAULT NULL,
  `total_applegreen` int(10) unsigned DEFAULT NULL,
  `total_avoca` int(10) unsigned DEFAULT NULL,
  `total_bewleys` int(10) unsigned DEFAULT NULL,
  `total_brambles` int(10) unsigned DEFAULT NULL,
  `total_butlers` int(10) unsigned DEFAULT NULL,
  `total_cafe_nero` int(10) unsigned DEFAULT NULL,
  `total_centra` int(10) unsigned DEFAULT NULL,
  `total_costa` int(10) unsigned DEFAULT NULL,
  `total_esquires` int(10) unsigned DEFAULT NULL,
  `total_frank_and_honest` int(10) unsigned DEFAULT NULL,
  `total_insomnia` int(10) unsigned DEFAULT NULL,
  `total_obriens` int(10) unsigned DEFAULT NULL,
  `total_lolly_and_cookes` int(10) unsigned DEFAULT NULL,
  `total_supermacs` int(10) unsigned DEFAULT NULL,
  `total_wilde_and_greene` int(10) unsigned DEFAULT NULL,
  `total_dumping` int(10) unsigned DEFAULT NULL,
  `total_industrial` int(10) unsigned DEFAULT NULL,
  `photos_per_month` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_litter` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_dogshit` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_country_id_foreign` (`country_id`),
  KEY `cities_state_id_foreign` (`state_id`),
  KEY `cities_created_by_foreign` (`created_by`),
  CONSTRAINT `cities_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `cities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `cities_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cleanup_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cleanup_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cleanup_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cleanup_user_cleanup_id_foreign` (`cleanup_id`),
  KEY `cleanup_user_user_id_foreign` (`user_id`),
  CONSTRAINT `cleanup_user_cleanup_id_foreign` FOREIGN KEY (`cleanup_id`) REFERENCES `cleanups` (`id`),
  CONSTRAINT `cleanup_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cleanups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cleanups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `lat` double NOT NULL,
  `lon` double NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `invite_link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cleanups_invite_link_unique` (`invite_link`),
  KEY `cleanups_user_id_foreign` (`user_id`),
  CONSTRAINT `cleanups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clusters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clusters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lat` double NOT NULL,
  `lon` double NOT NULL,
  `point_count` bigint(20) unsigned NOT NULL,
  `point_count_abbreviated` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `geohash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `zoom` int(11) NOT NULL,
  `year` year(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clusters_year_zoom_index` (`year`,`zoom`),
  KEY `clusters_year_index` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coastal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coastal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `microplastics` int(10) unsigned DEFAULT NULL,
  `mediumplastics` int(10) unsigned DEFAULT NULL,
  `macroplastics` int(10) unsigned DEFAULT NULL,
  `rope_small` int(10) unsigned DEFAULT NULL,
  `rope_medium` int(10) unsigned DEFAULT NULL,
  `rope_large` int(10) unsigned DEFAULT NULL,
  `fishing_gear_nets` int(10) unsigned DEFAULT NULL,
  `buoys` int(10) unsigned DEFAULT NULL,
  `degraded_plasticbottle` int(10) unsigned DEFAULT NULL,
  `degraded_plasticbag` int(10) unsigned DEFAULT NULL,
  `degraded_straws` int(10) unsigned DEFAULT NULL,
  `degraded_lighters` int(10) unsigned DEFAULT NULL,
  `balloons` int(10) unsigned DEFAULT NULL,
  `lego` int(10) unsigned DEFAULT NULL,
  `shotgun_cartridges` int(10) unsigned DEFAULT NULL,
  `coastal_other` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `styro_small` int(10) unsigned DEFAULT NULL,
  `styro_medium` int(10) unsigned DEFAULT NULL,
  `styro_large` int(10) unsigned DEFAULT NULL,
  `ghost_nets` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coffee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coffee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `coffeeCups` int(10) unsigned DEFAULT NULL,
  `coffeeLids` int(10) unsigned DEFAULT NULL,
  `coffeeOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not found',
  `shortcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_images` int(10) unsigned DEFAULT NULL,
  `total_smoking` int(10) unsigned DEFAULT NULL,
  `total_cigaretteButts` int(10) unsigned DEFAULT NULL,
  `total_food` int(10) unsigned DEFAULT NULL,
  `total_softdrinks` int(10) unsigned DEFAULT NULL,
  `total_plasticBottles` int(10) unsigned DEFAULT NULL,
  `total_alcohol` int(10) unsigned DEFAULT NULL,
  `total_coffee` int(10) unsigned DEFAULT NULL,
  `total_drugs` int(10) unsigned DEFAULT NULL,
  `total_needles` int(10) unsigned DEFAULT NULL,
  `total_sanitary` int(10) unsigned DEFAULT NULL,
  `total_other` int(10) unsigned DEFAULT NULL,
  `total_contributors` int(10) unsigned NOT NULL DEFAULT 0,
  `total_pathways` int(10) unsigned DEFAULT NULL,
  `manual_verify` tinyint(1) NOT NULL DEFAULT 0,
  `countrynameb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `littercoin_paid` tinyint(1) NOT NULL DEFAULT 0,
  `littercoin_issued` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `total_brands` int(10) unsigned DEFAULT NULL,
  `total_coastal` int(10) unsigned DEFAULT NULL,
  `total_adidas` int(10) unsigned DEFAULT NULL,
  `total_amazon` int(10) unsigned DEFAULT NULL,
  `total_apple` int(10) unsigned DEFAULT NULL,
  `total_budweiser` int(10) unsigned DEFAULT NULL,
  `total_coke` int(10) unsigned DEFAULT NULL,
  `total_colgate` int(10) unsigned DEFAULT NULL,
  `total_corona` int(10) unsigned DEFAULT NULL,
  `total_fritolay` int(10) unsigned DEFAULT NULL,
  `total_gillette` int(10) unsigned DEFAULT NULL,
  `total_heineken` int(10) unsigned DEFAULT NULL,
  `total_kellogs` int(10) unsigned DEFAULT NULL,
  `total_lego` int(10) unsigned DEFAULT NULL,
  `total_loreal` int(10) unsigned DEFAULT NULL,
  `total_nescafe` int(10) unsigned DEFAULT NULL,
  `total_nestle` int(10) unsigned DEFAULT NULL,
  `total_marlboro` int(10) unsigned DEFAULT NULL,
  `total_mcdonalds` int(10) unsigned DEFAULT NULL,
  `total_nike` int(10) unsigned DEFAULT NULL,
  `total_pepsi` int(10) unsigned DEFAULT NULL,
  `total_redbull` int(10) unsigned DEFAULT NULL,
  `total_samsung` int(10) unsigned DEFAULT NULL,
  `total_subway` int(10) unsigned DEFAULT NULL,
  `total_starbucks` int(10) unsigned DEFAULT NULL,
  `total_tayto` int(10) unsigned DEFAULT NULL,
  `total_applegreen` int(10) unsigned DEFAULT NULL,
  `total_avoca` int(10) unsigned DEFAULT NULL,
  `total_bewleys` int(10) unsigned DEFAULT NULL,
  `total_brambles` int(10) unsigned DEFAULT NULL,
  `total_butlers` int(10) unsigned DEFAULT NULL,
  `total_cafe_nero` int(10) unsigned DEFAULT NULL,
  `total_centra` int(10) unsigned DEFAULT NULL,
  `total_costa` int(10) unsigned DEFAULT NULL,
  `total_esquires` int(10) unsigned DEFAULT NULL,
  `total_frank_and_honest` int(10) unsigned DEFAULT NULL,
  `total_insomnia` int(10) unsigned DEFAULT NULL,
  `total_obriens` int(10) unsigned DEFAULT NULL,
  `total_lolly_and_cookes` int(10) unsigned DEFAULT NULL,
  `total_supermacs` int(10) unsigned DEFAULT NULL,
  `total_wilde_and_greene` int(10) unsigned DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `countrynamec` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_dumping` int(10) unsigned DEFAULT NULL,
  `total_industrial` int(10) unsigned DEFAULT NULL,
  `photos_per_month` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_litter` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_dogshit` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_slug_unique` (`slug`),
  KEY `countries_created_by_foreign` (`created_by`),
  CONSTRAINT `countries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `photo_id` int(10) unsigned NOT NULL,
  `tag` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `custom_tags_photo_id_tag_unique` (`photo_id`,`tag`),
  KEY `custom_tags_tag_index` (`tag`),
  CONSTRAINT `custom_tags_photo_id_foreign` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dogshit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dogshit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `poo` int(10) unsigned DEFAULT NULL,
  `poo_in_bag` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `amount` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drugs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drugs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `needles` int(10) unsigned DEFAULT NULL,
  `wipes` int(10) unsigned DEFAULT NULL,
  `tops` int(10) unsigned DEFAULT NULL,
  `packaging` int(10) unsigned DEFAULT NULL,
  `waterbottle` int(10) unsigned DEFAULT NULL,
  `spoons` int(10) unsigned DEFAULT NULL,
  `needlebin` int(10) unsigned DEFAULT NULL,
  `usedtinfoil` int(10) unsigned DEFAULT NULL,
  `barrels` int(10) unsigned DEFAULT NULL,
  `fullpackage` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `drugsOther` int(10) unsigned DEFAULT NULL,
  `baggie` int(10) unsigned DEFAULT NULL,
  `crack_pipes` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dumping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dumping` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `small` int(10) unsigned DEFAULT NULL,
  `medium` int(10) unsigned DEFAULT NULL,
  `large` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_subscriptions_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `experience` (
  `xp` int(11) NOT NULL,
  `level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farming`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farming` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plastic` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firewall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firewall` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(39) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whitelisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `firewall_ip_address_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `food`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `food` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sweetWrappers` int(10) unsigned DEFAULT NULL,
  `paperFoodPackaging` int(10) unsigned DEFAULT NULL,
  `plasticFoodPackaging` int(10) unsigned DEFAULT NULL,
  `plasticCutlery` int(10) unsigned DEFAULT NULL,
  `foodOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `crisp_small` int(10) unsigned DEFAULT NULL,
  `crisp_large` int(10) unsigned DEFAULT NULL,
  `styrofoam_plate` int(10) unsigned DEFAULT NULL,
  `napkins` int(10) unsigned DEFAULT NULL,
  `sauce_packet` int(10) unsigned DEFAULT NULL,
  `glass_jar` int(10) unsigned DEFAULT NULL,
  `glass_jar_lid` int(10) unsigned DEFAULT NULL,
  `pizza_box` int(10) unsigned DEFAULT NULL,
  `aluminium_foil` int(10) unsigned DEFAULT NULL,
  `chewing_gum` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `global_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `global_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xp` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `halls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `halls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `industrial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industrial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `oil` int(10) unsigned DEFAULT NULL,
  `chemical` int(10) unsigned DEFAULT NULL,
  `industrial_plastic` int(10) unsigned DEFAULT NULL,
  `bricks` int(10) unsigned DEFAULT NULL,
  `tape` int(10) unsigned DEFAULT NULL,
  `industrial_other` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xp` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `littercoins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `littercoins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `photo_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `littercoins_user_id_foreign` (`user_id`),
  KEY `littercoins_photo_id_foreign` (`photo_id`),
  CONSTRAINT `littercoins_photo_id_foreign` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`),
  CONSTRAINT `littercoins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `aluminium` int(10) unsigned DEFAULT NULL,
  `bronze` int(10) unsigned DEFAULT NULL,
  `carbon_fiber` int(10) unsigned DEFAULT NULL,
  `ceramic` int(10) unsigned DEFAULT NULL,
  `composite` int(10) unsigned DEFAULT NULL,
  `concrete` int(10) unsigned DEFAULT NULL,
  `copper` int(10) unsigned DEFAULT NULL,
  `fiberglass` int(10) unsigned DEFAULT NULL,
  `glass` int(10) unsigned DEFAULT NULL,
  `iron_or_steel` int(10) unsigned DEFAULT NULL,
  `latex` int(10) unsigned DEFAULT NULL,
  `metal` int(10) unsigned DEFAULT NULL,
  `nickel` int(10) unsigned DEFAULT NULL,
  `nylon` int(10) unsigned DEFAULT NULL,
  `paper` int(10) unsigned DEFAULT NULL,
  `plastic` int(10) unsigned DEFAULT NULL,
  `polyethylene` int(10) unsigned DEFAULT NULL,
  `polymer` int(10) unsigned DEFAULT NULL,
  `polypropylene` int(10) unsigned DEFAULT NULL,
  `polystyrene` int(10) unsigned DEFAULT NULL,
  `pvc` int(10) unsigned DEFAULT NULL,
  `rubber` int(10) unsigned DEFAULT NULL,
  `titanium` int(10) unsigned DEFAULT NULL,
  `wood` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `old_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `old_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `other` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dogshit` int(10) unsigned DEFAULT NULL,
  `dump` int(10) unsigned DEFAULT NULL,
  `plastic` int(10) unsigned DEFAULT NULL,
  `metal` int(10) unsigned DEFAULT NULL,
  `other` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `plastic_bags` int(10) unsigned DEFAULT NULL,
  `election_posters` int(10) unsigned DEFAULT NULL,
  `forsale_posters` int(10) unsigned DEFAULT NULL,
  `books` int(10) unsigned DEFAULT NULL,
  `magazine` int(10) unsigned DEFAULT NULL,
  `paper` int(10) unsigned DEFAULT NULL,
  `stationary` int(10) unsigned DEFAULT NULL,
  `washing_up` int(10) unsigned DEFAULT NULL,
  `hair_tie` int(10) unsigned DEFAULT NULL,
  `ear_plugs` int(10) unsigned DEFAULT NULL,
  `automobile` int(10) unsigned DEFAULT NULL,
  `balloons` int(10) unsigned DEFAULT NULL,
  `clothing` int(10) unsigned DEFAULT NULL,
  `pooinbag` int(10) unsigned DEFAULT NULL,
  `traffic_cone` int(10) unsigned DEFAULT NULL,
  `life_buoy` int(10) unsigned DEFAULT NULL,
  `batteries` int(10) unsigned DEFAULT NULL,
  `elec_small` int(10) unsigned DEFAULT NULL,
  `elec_large` int(10) unsigned DEFAULT NULL,
  `random_litter` int(10) unsigned DEFAULT NULL,
  `bags_litter` int(10) unsigned DEFAULT NULL,
  `cable_tie` int(10) unsigned DEFAULT NULL,
  `tyre` int(10) unsigned DEFAULT NULL,
  `overflowing_bins` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`),
  KEY `password_resets_token_index` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pathways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pathways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gutter` int(10) unsigned DEFAULT NULL,
  `gutter_long` int(10) unsigned DEFAULT NULL,
  `kerb_hole_small` int(10) unsigned DEFAULT NULL,
  `kerb_hole_large` int(10) unsigned DEFAULT NULL,
  `pathwayOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `amount` smallint(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datetime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification` double NOT NULL DEFAULT 0,
  `remaining` tinyint(1) NOT NULL DEFAULT 1,
  `lat` double DEFAULT NULL,
  `lon` double DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `road` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suburb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `smoking_id` int(10) unsigned DEFAULT NULL,
  `food_id` int(10) unsigned DEFAULT NULL,
  `coffee_id` int(10) unsigned DEFAULT NULL,
  `alcohol_id` int(10) unsigned DEFAULT NULL,
  `softdrinks_id` int(10) unsigned DEFAULT NULL,
  `drugs_id` int(10) unsigned DEFAULT NULL,
  `sanitary_id` int(10) unsigned DEFAULT NULL,
  `other_id` int(10) unsigned DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  `incorrect_verification` int(10) unsigned NOT NULL DEFAULT 0,
  `total_litter` int(10) unsigned NOT NULL DEFAULT 0,
  `coastal_id` int(10) unsigned DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `pathways_id` int(10) unsigned DEFAULT NULL,
  `generated` tinyint(1) NOT NULL DEFAULT 0,
  `art_id` int(10) unsigned DEFAULT NULL,
  `brands_id` int(10) unsigned DEFAULT NULL,
  `trashdog_id` int(10) unsigned DEFAULT NULL,
  `result_string` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `political_id` int(10) unsigned DEFAULT NULL,
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `dumping_id` bigint(20) unsigned DEFAULT NULL,
  `industrial_id` bigint(20) unsigned DEFAULT NULL,
  `bounding_box` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geohash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_id` int(10) unsigned DEFAULT NULL,
  `bbox_skipped` tinyint(1) NOT NULL DEFAULT 0,
  `skipped_by` int(10) unsigned DEFAULT NULL,
  `bbox_assigned_to` int(10) unsigned DEFAULT NULL,
  `wrong_tags` tinyint(1) NOT NULL DEFAULT 0,
  `wrong_tags_by` int(10) unsigned DEFAULT NULL,
  `bbox_verification_assigned_to` bigint(20) unsigned DEFAULT NULL,
  `five_hundred_square_filepath` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dogshit_id` bigint(20) unsigned DEFAULT NULL,
  `address_array` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `littercoin_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photos_user_id_foreign` (`user_id`),
  KEY `photos_country_id_foreign` (`country_id`),
  KEY `photos_city_id_foreign` (`city_id`),
  KEY `photos_state_id_foreign` (`state_id`),
  KEY `photos_verified_by_foreign` (`verified_by`),
  KEY `photos_geohash_index` (`geohash`),
  KEY `photos_team_id_foreign` (`team_id`),
  KEY `photos_alcohol_id_foreign` (`alcohol_id`),
  KEY `photos_art_id_foreign` (`art_id`),
  KEY `photos_brands_id_foreign` (`brands_id`),
  KEY `photos_softdrinks_id_foreign` (`softdrinks_id`),
  KEY `photos_smoking_id_foreign` (`smoking_id`),
  KEY `photos_sanitary_id_foreign` (`sanitary_id`),
  KEY `photos_political_id_foreign` (`political_id`),
  KEY `photos_pathways_id_foreign` (`pathways_id`),
  KEY `photos_other_id_foreign` (`other_id`),
  KEY `photos_food_id_foreign` (`food_id`),
  KEY `photos_drugs_id_foreign` (`drugs_id`),
  KEY `photos_dumping_id_foreign` (`dumping_id`),
  KEY `photos_industrial_id_foreign` (`industrial_id`),
  KEY `photos_trashdog_id_foreign` (`trashdog_id`),
  KEY `photos_coffee_id_foreign` (`coffee_id`),
  KEY `photos_coastal_id_foreign` (`coastal_id`),
  KEY `photos_datetime_index` (`datetime`),
  KEY `photos_created_at_index` (`created_at`),
  KEY `photos_material_id_foreign` (`material_id`),
  KEY `photos_littercoin_id_foreign` (`littercoin_id`),
  CONSTRAINT `photos_alcohol_id_foreign` FOREIGN KEY (`alcohol_id`) REFERENCES `alcohol` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_art_id_foreign` FOREIGN KEY (`art_id`) REFERENCES `arts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_brands_id_foreign` FOREIGN KEY (`brands_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `photos_coastal_id_foreign` FOREIGN KEY (`coastal_id`) REFERENCES `coastal` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_coffee_id_foreign` FOREIGN KEY (`coffee_id`) REFERENCES `coffee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `photos_drugs_id_foreign` FOREIGN KEY (`drugs_id`) REFERENCES `drugs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_dumping_id_foreign` FOREIGN KEY (`dumping_id`) REFERENCES `dumping` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_food_id_foreign` FOREIGN KEY (`food_id`) REFERENCES `food` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_industrial_id_foreign` FOREIGN KEY (`industrial_id`) REFERENCES `industrial` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_littercoin_id_foreign` FOREIGN KEY (`littercoin_id`) REFERENCES `littercoins` (`id`),
  CONSTRAINT `photos_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `material` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_other_id_foreign` FOREIGN KEY (`other_id`) REFERENCES `other` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_pathways_id_foreign` FOREIGN KEY (`pathways_id`) REFERENCES `pathways` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_political_id_foreign` FOREIGN KEY (`political_id`) REFERENCES `politicals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_sanitary_id_foreign` FOREIGN KEY (`sanitary_id`) REFERENCES `sanitary` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_smoking_id_foreign` FOREIGN KEY (`smoking_id`) REFERENCES `smoking` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_softdrinks_id_foreign` FOREIGN KEY (`softdrinks_id`) REFERENCES `softdrinks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `photos_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `photos_trashdog_id_foreign` FOREIGN KEY (`trashdog_id`) REFERENCES `trashdog` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `photos_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` int(10) unsigned NOT NULL DEFAULT 0,
  `verify` int(10) unsigned NOT NULL DEFAULT 0,
  `product_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `politicals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `politicals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `finegael` int(10) unsigned DEFAULT NULL,
  `finnafail` int(10) unsigned DEFAULT NULL,
  `greens` int(10) unsigned DEFAULT NULL,
  `sinnfein` int(10) unsigned DEFAULT NULL,
  `independent` int(10) unsigned DEFAULT NULL,
  `labour` int(10) unsigned DEFAULT NULL,
  `solidarity` int(10) unsigned DEFAULT NULL,
  `socialdemocrats` int(10) unsigned DEFAULT NULL,
  `peoplebeforeprofit` int(10) unsigned DEFAULT NULL,
  `other` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sanitary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sanitary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `condoms` int(10) unsigned DEFAULT NULL,
  `nappies` int(10) unsigned DEFAULT NULL,
  `menstral` int(10) unsigned DEFAULT NULL,
  `deodorant` int(10) unsigned DEFAULT NULL,
  `sanitaryOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ear_swabs` int(10) unsigned DEFAULT NULL,
  `tooth_pick` int(10) unsigned DEFAULT NULL,
  `tooth_brush` int(10) unsigned DEFAULT NULL,
  `wetwipes` int(10) unsigned DEFAULT NULL,
  `gloves` int(10) unsigned DEFAULT NULL,
  `facemask` int(10) unsigned DEFAULT NULL,
  `hand_sanitiser` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `smoking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `smoking` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `butts` int(10) unsigned DEFAULT NULL,
  `lighters` int(10) unsigned DEFAULT NULL,
  `cigaretteBox` int(10) unsigned DEFAULT NULL,
  `tobaccoPouch` int(10) unsigned DEFAULT NULL,
  `skins` int(10) unsigned DEFAULT NULL,
  `smokingOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `smoking_plastic` int(10) unsigned DEFAULT NULL,
  `filters` int(10) unsigned DEFAULT NULL,
  `filterbox` int(10) unsigned DEFAULT NULL,
  `vape_pen` int(10) unsigned DEFAULT NULL,
  `vape_oil` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `softdrinks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `softdrinks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `waterBottle` int(10) unsigned DEFAULT NULL,
  `fizzyDrinkBottle` int(10) unsigned DEFAULT NULL,
  `bottleLid` int(10) unsigned DEFAULT NULL,
  `bottleLabel` int(10) unsigned DEFAULT NULL,
  `tinCan` int(10) unsigned DEFAULT NULL,
  `sportsDrink` int(10) unsigned DEFAULT NULL,
  `softDrinkOther` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `straws` int(10) unsigned DEFAULT NULL,
  `plastic_cups` int(10) unsigned DEFAULT NULL,
  `plastic_cup_tops` int(10) unsigned DEFAULT NULL,
  `milk_bottle` int(10) unsigned DEFAULT NULL,
  `milk_carton` int(10) unsigned DEFAULT NULL,
  `paper_cups` int(10) unsigned DEFAULT NULL,
  `juice_cartons` int(10) unsigned DEFAULT NULL,
  `juice_bottles` int(10) unsigned DEFAULT NULL,
  `juice_packet` int(10) unsigned DEFAULT NULL,
  `ice_tea_bottles` int(10) unsigned DEFAULT NULL,
  `ice_tea_can` int(10) unsigned DEFAULT NULL,
  `energy_can` int(10) unsigned DEFAULT NULL,
  `pullring` int(10) unsigned DEFAULT NULL,
  `strawpacket` int(10) unsigned DEFAULT NULL,
  `styro_cup` int(10) unsigned DEFAULT NULL,
  `broken_glass` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `states` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not found',
  `country_id` int(10) unsigned NOT NULL,
  `total_images` int(10) unsigned DEFAULT NULL,
  `total_smoking` int(10) unsigned DEFAULT NULL,
  `total_cigaretteButts` int(10) unsigned DEFAULT NULL,
  `total_food` int(10) unsigned DEFAULT NULL,
  `total_softdrinks` int(10) unsigned DEFAULT NULL,
  `total_plasticBottles` int(10) unsigned DEFAULT NULL,
  `total_alcohol` int(10) unsigned DEFAULT NULL,
  `total_coffee` int(10) unsigned DEFAULT NULL,
  `total_drugs` int(10) unsigned DEFAULT NULL,
  `total_needles` int(10) unsigned DEFAULT NULL,
  `total_sanitary` int(10) unsigned DEFAULT NULL,
  `total_other` int(10) unsigned DEFAULT NULL,
  `total_coastal` int(10) unsigned DEFAULT NULL,
  `total_contributors` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_pathways` int(10) unsigned DEFAULT NULL,
  `manual_verify` tinyint(1) NOT NULL DEFAULT 0,
  `littercoin_paid` tinyint(1) NOT NULL DEFAULT 0,
  `littercoin_issued` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `total_brands` int(10) unsigned DEFAULT NULL,
  `total_adidas` int(10) unsigned DEFAULT NULL,
  `total_amazon` int(10) unsigned DEFAULT NULL,
  `total_apple` int(10) unsigned DEFAULT NULL,
  `total_budweiser` int(10) unsigned DEFAULT NULL,
  `total_coke` int(10) unsigned DEFAULT NULL,
  `total_colgate` int(10) unsigned DEFAULT NULL,
  `total_corona` int(10) unsigned DEFAULT NULL,
  `total_fritolay` int(10) unsigned DEFAULT NULL,
  `total_gillette` int(10) unsigned DEFAULT NULL,
  `total_heineken` int(10) unsigned DEFAULT NULL,
  `total_kellogs` int(10) unsigned DEFAULT NULL,
  `total_lego` int(10) unsigned DEFAULT NULL,
  `total_loreal` int(10) unsigned DEFAULT NULL,
  `total_nescafe` int(10) unsigned DEFAULT NULL,
  `total_nestle` int(10) unsigned DEFAULT NULL,
  `total_marlboro` int(10) unsigned DEFAULT NULL,
  `total_mcdonalds` int(10) unsigned DEFAULT NULL,
  `total_nike` int(10) unsigned DEFAULT NULL,
  `total_pepsi` int(10) unsigned DEFAULT NULL,
  `total_redbull` int(10) unsigned DEFAULT NULL,
  `total_samsung` int(10) unsigned DEFAULT NULL,
  `total_subway` int(10) unsigned DEFAULT NULL,
  `total_starbucks` int(10) unsigned DEFAULT NULL,
  `total_tayto` int(10) unsigned DEFAULT NULL,
  `statenameb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_applegreen` int(10) unsigned DEFAULT NULL,
  `total_avoca` int(10) unsigned DEFAULT NULL,
  `total_bewleys` int(10) unsigned DEFAULT NULL,
  `total_brambles` int(10) unsigned DEFAULT NULL,
  `total_butlers` int(10) unsigned DEFAULT NULL,
  `total_cafe_nero` int(10) unsigned DEFAULT NULL,
  `total_centra` int(10) unsigned DEFAULT NULL,
  `total_costa` int(10) unsigned DEFAULT NULL,
  `total_esquires` int(10) unsigned DEFAULT NULL,
  `total_frank_and_honest` int(10) unsigned DEFAULT NULL,
  `total_insomnia` int(10) unsigned DEFAULT NULL,
  `total_obriens` int(10) unsigned DEFAULT NULL,
  `total_lolly_and_cookes` int(10) unsigned DEFAULT NULL,
  `total_supermacs` int(10) unsigned DEFAULT NULL,
  `total_wilde_and_greene` int(10) unsigned DEFAULT NULL,
  `total_dumping` int(10) unsigned DEFAULT NULL,
  `total_industrial` int(10) unsigned DEFAULT NULL,
  `photos_per_month` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_litter` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_dogshit` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `states_country_id_foreign` (`country_id`),
  KEY `states_created_by_foreign` (`created_by`),
  CONSTRAINT `states_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `states_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stripe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_active` tinyint(1) NOT NULL DEFAULT 0,
  `subscription_end_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `plan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stripe_user_id_foreign` (`user_id`),
  CONSTRAINT `stripe_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_plan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_items_subscription_id_stripe_plan_unique` (`subscription_id`,`stripe_plan`),
  KEY `subscription_items_stripe_id_index` (`stripe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_plan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stripe_active` int(10) unsigned NOT NULL DEFAULT 0,
  `stripe_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_stripe_status_index` (`user_id`,`stripe_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suburbs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suburbs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `suburb` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `needles` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suburbs_country_id_foreign` (`country_id`),
  KEY `suburbs_state_id_foreign` (`state_id`),
  KEY `suburbs_city_id_foreign` (`city_id`),
  CONSTRAINT `suburbs_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `suburbs_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `suburbs_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_clusters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_clusters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int(10) unsigned NOT NULL,
  `zoom` int(11) NOT NULL,
  `lat` double NOT NULL,
  `lon` double NOT NULL,
  `point_count` bigint(20) unsigned NOT NULL,
  `point_count_abbreviated` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `geohash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_clusters_team_id_foreign` (`team_id`),
  CONSTRAINT `team_clusters_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `team` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `team_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_photos` int(10) unsigned NOT NULL DEFAULT 0,
  `total_litter` int(10) unsigned NOT NULL DEFAULT 0,
  `show_name_maps` tinyint(1) NOT NULL DEFAULT 0,
  `show_username_maps` tinyint(1) NOT NULL DEFAULT 0,
  `show_name_leaderboards` tinyint(1) NOT NULL DEFAULT 0,
  `show_username_leaderboards` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `team_user_user_id_foreign` (`user_id`),
  KEY `team_user_team_id_foreign` (`team_id`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `members` int(10) unsigned NOT NULL DEFAULT 1,
  `images_remaining` int(10) unsigned NOT NULL DEFAULT 0,
  `total_images` int(10) unsigned NOT NULL DEFAULT 0,
  `total_litter` int(10) unsigned NOT NULL DEFAULT 0,
  `leader` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_id` int(10) unsigned DEFAULT NULL,
  `type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `leaderboards` tinyint(1) NOT NULL DEFAULT 1,
  `is_trusted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_name_unique` (`name`),
  KEY `teams_leader_foreign` (`leader`),
  KEY `teams_type_id_foreign` (`type_id`),
  CONSTRAINT `teams_leader_foreign` FOREIGN KEY (`leader`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `team_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trashdog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trashdog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trashdog` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `littercat` int(11) DEFAULT NULL,
  `duck` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default.jpg',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `show_name` tinyint(1) NOT NULL DEFAULT 0,
  `show_username` tinyint(1) NOT NULL DEFAULT 0,
  `items_remaining` tinyint(1) NOT NULL DEFAULT 0,
  `role_id` int(10) unsigned DEFAULT NULL,
  `billing_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `xp` int(10) unsigned NOT NULL DEFAULT 0,
  `level` int(10) unsigned NOT NULL DEFAULT 0,
  `total_images` int(10) unsigned DEFAULT NULL,
  `total_smoking` int(10) unsigned DEFAULT NULL,
  `total_cigaretteButts` int(10) unsigned DEFAULT NULL,
  `total_food` int(10) unsigned DEFAULT NULL,
  `total_softdrinks` int(10) unsigned DEFAULT NULL,
  `total_plasticBottles` int(10) unsigned DEFAULT NULL,
  `total_alcohol` int(10) unsigned DEFAULT NULL,
  `total_coffee` int(10) unsigned DEFAULT NULL,
  `total_sanitary` int(10) unsigned DEFAULT NULL,
  `total_other` int(10) unsigned DEFAULT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last_four` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `images_remaining` int(10) unsigned NOT NULL DEFAULT 0,
  `verify_remaining` int(10) unsigned NOT NULL DEFAULT 0,
  `has_uploaded` int(10) unsigned NOT NULL DEFAULT 0,
  `total_verified` int(11) NOT NULL DEFAULT 0,
  `total_litter` int(11) NOT NULL DEFAULT 0,
  `total_verified_litter` int(10) unsigned NOT NULL DEFAULT 0,
  `emailsub` int(10) unsigned NOT NULL DEFAULT 1,
  `sub_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_pathways` int(10) unsigned DEFAULT NULL,
  `eth_wallet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `littercoin_allowance` int(10) unsigned NOT NULL DEFAULT 0,
  `has_uploaded_today` int(10) unsigned NOT NULL DEFAULT 0,
  `has_uploaded_counter` int(10) unsigned NOT NULL DEFAULT 0,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_team` int(10) unsigned DEFAULT NULL,
  `link_instagram` int(10) unsigned NOT NULL DEFAULT 0,
  `total_art` int(10) unsigned DEFAULT NULL,
  `verification_required` tinyint(1) NOT NULL DEFAULT 1,
  `prevent_others_tagging_my_photos` tinyint(1) DEFAULT 0,
  `littercoin_owed` int(10) unsigned DEFAULT NULL,
  `littercoin_paid` int(10) unsigned DEFAULT NULL,
  `count_correctly_verified` int(10) unsigned NOT NULL DEFAULT 0,
  `littercoin_instructions_received` int(11) DEFAULT NULL,
  `show_name_maps` tinyint(1) NOT NULL DEFAULT 0,
  `show_username_maps` tinyint(1) NOT NULL DEFAULT 0,
  `show_name_createdby` tinyint(1) NOT NULL DEFAULT 0,
  `show_username_createdby` tinyint(1) NOT NULL DEFAULT 0,
  `global_flag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_tags` int(11) NOT NULL DEFAULT 0,
  `remaining_teams` int(11) NOT NULL DEFAULT 1,
  `photos_per_month` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_dumping` bigint(20) unsigned DEFAULT NULL,
  `total_industrial` bigint(20) unsigned DEFAULT NULL,
  `total_coastal` bigint(20) unsigned DEFAULT NULL,
  `total_brands` bigint(20) unsigned DEFAULT NULL,
  `bbox_verification_count` int(10) unsigned NOT NULL DEFAULT 0,
  `can_bbox` tinyint(1) NOT NULL DEFAULT 0,
  `total_dogshit` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_role_id_index` (`role_id`),
  KEY `users_active_team_foreign` (`active_team`),
  KEY `users_created_at_index` (`created_at`),
  KEY `users_prevent_others_tagging_my_photos_index` (`prevent_others_tagging_my_photos`),
  CONSTRAINT `users_active_team_foreign` FOREIGN KEY (`active_team`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websockets_statistics_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `websockets_statistics_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peak_connection_count` int(11) NOT NULL,
  `websocket_message_count` int(11) NOT NULL,
  `api_message_count` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` VALUES (1,'2014_02_01_311070_create_firewall_table',1);
INSERT INTO `migrations` VALUES (2,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` VALUES (3,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` VALUES (4,'2016_06_01_000001_create_oauth_auth_codes_table',1);
INSERT INTO `migrations` VALUES (5,'2016_06_01_000002_create_oauth_access_tokens_table',1);
INSERT INTO `migrations` VALUES (6,'2016_06_01_000003_create_oauth_refresh_tokens_table',1);
INSERT INTO `migrations` VALUES (7,'2016_06_01_000004_create_oauth_clients_table',1);
INSERT INTO `migrations` VALUES (8,'2016_06_01_000005_create_oauth_personal_access_clients_table',1);
INSERT INTO `migrations` VALUES (9,'2017_02_24_151913_create_photos_table',1);
INSERT INTO `migrations` VALUES (10,'2017_02_24_152218_create_countries_table',1);
INSERT INTO `migrations` VALUES (11,'2017_02_24_152230_create_cities_table',1);
INSERT INTO `migrations` VALUES (12,'2017_02_24_152309_create_roles_table',1);
INSERT INTO `migrations` VALUES (13,'2017_02_24_152555_create_smoking_table',1);
INSERT INTO `migrations` VALUES (14,'2017_02_24_152618_create_alcohol_table',1);
INSERT INTO `migrations` VALUES (15,'2017_02_24_152641_create_drugs_table',1);
INSERT INTO `migrations` VALUES (16,'2017_02_24_152651_create_coffee_table',1);
INSERT INTO `migrations` VALUES (17,'2017_02_24_152714_create_sanitary_table',1);
INSERT INTO `migrations` VALUES (18,'2017_02_24_152754_create_soft_drinks_table',1);
INSERT INTO `migrations` VALUES (19,'2017_02_24_152915_create_other_table',1);
INSERT INTO `migrations` VALUES (20,'2017_02_24_153215_create_food_table',1);
INSERT INTO `migrations` VALUES (21,'2017_02_24_154540_add_totals_to_photos',1);
INSERT INTO `migrations` VALUES (22,'2017_02_24_234922_add_country_and_city_to_photos',1);
INSERT INTO `migrations` VALUES (23,'2017_03_01_122212_add_drugsOther_to_drugs',1);
INSERT INTO `migrations` VALUES (24,'2017_03_01_230823_create_products_table',1);
INSERT INTO `migrations` VALUES (25,'2017_03_02_001245_create_plans_table',1);
INSERT INTO `migrations` VALUES (26,'2017_03_02_124247_create_stripe_table',1);
INSERT INTO `migrations` VALUES (27,'2017_03_02_130701_update_users_stripe_id',1);
INSERT INTO `migrations` VALUES (28,'2017_03_02_165305_create_payments_table',1);
INSERT INTO `migrations` VALUES (29,'2017_03_07_162414_add_description_to_plans',1);
INSERT INTO `migrations` VALUES (30,'2017_03_07_200856_add_plan_to_stripe',1);
INSERT INTO `migrations` VALUES (31,'2017_03_07_234737_add_cashier_to_users',1);
INSERT INTO `migrations` VALUES (32,'2017_03_07_234858_make_subscriptions_table',1);
INSERT INTO `migrations` VALUES (33,'2017_03_08_163945_add_remaining_images_to_users',1);
INSERT INTO `migrations` VALUES (34,'2017_03_08_164811_add_images_to_plans',1);
INSERT INTO `migrations` VALUES (35,'2017_03_08_171526_add_default_to_images_remaining',1);
INSERT INTO `migrations` VALUES (36,'2017_03_09_111141_add_stripe_active_to_subscriptions',1);
INSERT INTO `migrations` VALUES (37,'2017_03_10_163232_change_foreign_key_photos_table_user_id',1);
INSERT INTO `migrations` VALUES (38,'2017_03_10_164009_change_foreign_key_photos_table_user_id2',1);
INSERT INTO `migrations` VALUES (39,'2017_03_11_115537_add_totals_to_countries',1);
INSERT INTO `migrations` VALUES (40,'2017_03_11_115554_add_totals_to_cities',1);
INSERT INTO `migrations` VALUES (41,'2017_03_15_223049_add_verify_remaining_to_users',1);
INSERT INTO `migrations` VALUES (42,'2017_03_15_223628_add_verify_to_plans',1);
INSERT INTO `migrations` VALUES (43,'2017_03_16_120436_add_has_uploaded_to_users',1);
INSERT INTO `migrations` VALUES (44,'2017_03_17_223858_create_email_subscriptions_table',1);
INSERT INTO `migrations` VALUES (45,'2017_03_24_161253_create_xp_level_table',1);
INSERT INTO `migrations` VALUES (46,'2017_03_24_162339_create_levels_table',1);
INSERT INTO `migrations` VALUES (47,'2017_03_27_115016_add_incorrect_verification_to_photos',1);
INSERT INTO `migrations` VALUES (48,'2017_03_29_225010_add_total_contributors_to_countries',1);
INSERT INTO `migrations` VALUES (49,'2017_04_01_222901_add_total_litter_to_photos',1);
INSERT INTO `migrations` VALUES (50,'2017_04_02_133607_add_total_contributors_to_cities',1);
INSERT INTO `migrations` VALUES (51,'2017_04_05_135152_create_awards_table',1);
INSERT INTO `migrations` VALUES (52,'2017_04_05_165718_add_total_verified_to_users',1);
INSERT INTO `migrations` VALUES (53,'2017_04_05_171539_add_total_litter_verified_to_users',1);
INSERT INTO `migrations` VALUES (54,'2017_04_06_110913_create_inspiring_quotes_table',1);
INSERT INTO `migrations` VALUES (55,'2017_04_12_140433_add_totals_to_photos_again',1);
INSERT INTO `migrations` VALUES (56,'2017_04_12_140917_create_food_table_again',1);
INSERT INTO `migrations` VALUES (57,'2017_04_12_142138_create_others_table_again',1);
INSERT INTO `migrations` VALUES (58,'2017_04_12_143107_create_alcohol_table_again',1);
INSERT INTO `migrations` VALUES (59,'2017_04_12_143854_add_time_to_coffee',1);
INSERT INTO `migrations` VALUES (60,'2017_05_08_120834_add_coastal_table',1);
INSERT INTO `migrations` VALUES (61,'2017_05_08_145013_add_coastal_id_to_photos',1);
INSERT INTO `migrations` VALUES (62,'2017_05_08_151734_add_total_coastal_to_users',1);
INSERT INTO `migrations` VALUES (63,'2017_05_08_153140_add_total_coastal_to_cities',1);
INSERT INTO `migrations` VALUES (64,'2017_05_08_153758_add_coastal_total_to_countries',1);
INSERT INTO `migrations` VALUES (65,'2017_05_11_144254_add_emailsubs_to_users',1);
INSERT INTO `migrations` VALUES (66,'2017_05_11_150300_add_randomstr_to_users',1);
INSERT INTO `migrations` VALUES (67,'2017_05_14_110536_create_states_table',1);
INSERT INTO `migrations` VALUES (68,'2017_05_14_113515_add_state_id_to_cities',1);
INSERT INTO `migrations` VALUES (69,'2017_05_14_151416_add_state_id_to_photos',1);
INSERT INTO `migrations` VALUES (70,'2017_05_18_104612_add_items_to_softdrinks',1);
INSERT INTO `migrations` VALUES (71,'2017_05_18_104757_add_stuff_to_other',1);
INSERT INTO `migrations` VALUES (72,'2017_05_18_104915_add_things_to_sanitary',1);
INSERT INTO `migrations` VALUES (73,'2017_05_18_105028_add_drugs_to_drugs',1);
INSERT INTO `migrations` VALUES (74,'2017_05_18_105145_create_pathways_table',1);
INSERT INTO `migrations` VALUES (75,'2017_05_18_111416_add_pathway_id_to_photos',1);
INSERT INTO `migrations` VALUES (76,'2017_05_18_111843_add_total_pathway_to_users',1);
INSERT INTO `migrations` VALUES (77,'2017_05_18_112059_add_total_pathways_to_countries',1);
INSERT INTO `migrations` VALUES (78,'2017_05_18_112121_add_total_pathways_to_cities',1);
INSERT INTO `migrations` VALUES (79,'2017_05_18_112151_add_total_pathways_to_states',1);
INSERT INTO `migrations` VALUES (80,'2017_05_21_133711_add_eth_wallet_id_to_users',1);
INSERT INTO `migrations` VALUES (81,'2017_05_22_105847_add_ltrx_generated_to_photos',1);
INSERT INTO `migrations` VALUES (82,'2017_05_25_113747_add_littercoinsdaily_to_users',1);
INSERT INTO `migrations` VALUES (83,'2017_05_25_174506_add_daily_counter_to_users',1);
INSERT INTO `migrations` VALUES (84,'2017_05_31_123816_create_suburbs_table',1);
INSERT INTO `migrations` VALUES (85,'2017_06_01_100620_add_ids_to_suburbs',1);
INSERT INTO `migrations` VALUES (86,'2017_06_13_130004_add_phonenum_to_users',1);
INSERT INTO `migrations` VALUES (87,'2017_06_17_120538_add_manual_verify_to_states',1);
INSERT INTO `migrations` VALUES (88,'2017_06_17_120554_add_manual_verify_to_cities',1);
INSERT INTO `migrations` VALUES (89,'2017_06_17_121445_add_manual_verify_to_countries',1);
INSERT INTO `migrations` VALUES (90,'2017_06_21_143517_add_secondcountryname_to_countries',1);
INSERT INTO `migrations` VALUES (91,'2017_06_30_114515_add_filters_to_smoking',1);
INSERT INTO `migrations` VALUES (92,'2017_06_30_114536_add_crisps_to_food',1);
INSERT INTO `migrations` VALUES (93,'2017_06_30_114603_add_papercups_to_softdrinks',1);
INSERT INTO `migrations` VALUES (94,'2017_06_30_115140_add_morestuff_to_other',1);
INSERT INTO `migrations` VALUES (95,'2017_07_05_222621_create_teams_table',1);
INSERT INTO `migrations` VALUES (96,'2017_07_05_232102_create_team_types_table',1);
INSERT INTO `migrations` VALUES (97,'2017_07_06_102948_add_teamtypes_id_to_teams',1);
INSERT INTO `migrations` VALUES (98,'2017_07_06_210837_add_description_to_teamtypes',1);
INSERT INTO `migrations` VALUES (99,'2017_07_06_211740_create_team_user',1);
INSERT INTO `migrations` VALUES (100,'2017_07_11_120023_add_active_team_to_users',1);
INSERT INTO `migrations` VALUES (101,'2017_07_12_101224_create_donates_table',1);
INSERT INTO `migrations` VALUES (102,'2017_09_19_113249_create_halls_table',1);
INSERT INTO `migrations` VALUES (103,'2017_09_27_183221_add_social_media_to_users',1);
INSERT INTO `migrations` VALUES (104,'2017_10_02_134255_create_arts_table',1);
INSERT INTO `migrations` VALUES (105,'2017_10_05_155628_add_arts_id_to_photos',1);
INSERT INTO `migrations` VALUES (106,'2017_10_05_164853_add_total_art_to_users',1);
INSERT INTO `migrations` VALUES (107,'2017_10_05_173043_add_total_art_to_cities',1);
INSERT INTO `migrations` VALUES (108,'2018_01_07_151802_add_no_verification_required_to_users',1);
INSERT INTO `migrations` VALUES (109,'2018_02_11_150508_add_littercoin_owed_to_users',1);
INSERT INTO `migrations` VALUES (110,'2018_02_11_154940_add_littercoin_paid_to_countries',1);
INSERT INTO `migrations` VALUES (111,'2018_02_11_155145_add_littercoin_paid_to_states',1);
INSERT INTO `migrations` VALUES (112,'2018_02_11_155156_add_littercoin_paid_to_cities',1);
INSERT INTO `migrations` VALUES (113,'2018_02_12_012514_add_count_correctly_verified_to_users',1);
INSERT INTO `migrations` VALUES (114,'2018_02_13_113039_littercoin_paid_and_issued_to_countries',1);
INSERT INTO `migrations` VALUES (115,'2018_02_13_113121_littercoin_paid_and_issued_to_states',1);
INSERT INTO `migrations` VALUES (116,'2018_02_13_113134_littercoin_paid_and_issued_to_cities',1);
INSERT INTO `migrations` VALUES (117,'2018_02_13_152859_add_created_by_to_countries_b',1);
INSERT INTO `migrations` VALUES (118,'2018_02_14_141742_add_created_by_to_states',1);
INSERT INTO `migrations` VALUES (119,'2018_02_14_141752_add_created_by_to_cities',1);
INSERT INTO `migrations` VALUES (120,'2018_02_14_161542_create_brands_table',1);
INSERT INTO `migrations` VALUES (121,'2018_02_14_165420_add_brands_id_to_photos',1);
INSERT INTO `migrations` VALUES (122,'2018_02_15_120822_add_total_brands_to_countries',1);
INSERT INTO `migrations` VALUES (123,'2018_02_15_120833_add_total_brands_to_states',1);
INSERT INTO `migrations` VALUES (124,'2018_02_15_120844_add_total_brands_to_cities',1);
INSERT INTO `migrations` VALUES (125,'2018_02_15_133157_add_photo_id_to_brands',1);
INSERT INTO `migrations` VALUES (126,'2018_02_15_193907_add_coastal_and_brands_to_countries',1);
INSERT INTO `migrations` VALUES (127,'2018_02_16_004631_add_coastal_and_brands_to_states',1);
INSERT INTO `migrations` VALUES (128,'2018_02_16_004646_add_coastal_and_brands_to_cities',1);
INSERT INTO `migrations` VALUES (129,'2018_02_16_223617_add_total_brands_to_countries_again',1);
INSERT INTO `migrations` VALUES (130,'2018_02_16_223643_add_total_brands_to_cities_again',1);
INSERT INTO `migrations` VALUES (131,'2018_02_22_175840_add_received_littercoin_instructions_to_users',1);
INSERT INTO `migrations` VALUES (132,'2018_03_24_123342_create_dogshits_table',1);
INSERT INTO `migrations` VALUES (133,'2018_03_24_123841_create_irish_brands_table',1);
INSERT INTO `migrations` VALUES (134,'2018_04_28_171735_add_statenameb_to_states',1);
INSERT INTO `migrations` VALUES (135,'2018_06_19_085739_create_trash_dogs_table',1);
INSERT INTO `migrations` VALUES (136,'2018_06_19_090307_add_trashdog_to_photos',1);
INSERT INTO `migrations` VALUES (137,'2018_06_22_130825_add_new_brands_for_keith',1);
INSERT INTO `migrations` VALUES (138,'2018_06_24_141559_add_keith_total_brands_to_countries',1);
INSERT INTO `migrations` VALUES (139,'2018_06_24_141703_add_keith_total_brands_to_states',1);
INSERT INTO `migrations` VALUES (140,'2018_06_24_141719_add_keith_total_brands_to_cities',1);
INSERT INTO `migrations` VALUES (141,'2018_07_17_172534_add_slug_to_countries',1);
INSERT INTO `migrations` VALUES (142,'2018_07_18_210825_add_photos_result_string',1);
INSERT INTO `migrations` VALUES (143,'2018_07_20_213553_create_global_levels_table',1);
INSERT INTO `migrations` VALUES (144,'2018_08_18_192839_add_littercat_to_trashdog',1);
INSERT INTO `migrations` VALUES (145,'2018_09_01_165440_add_new_brands_for_keith2',1);
INSERT INTO `migrations` VALUES (146,'2018_09_01_170710_add_trolley_to_others',1);
INSERT INTO `migrations` VALUES (147,'2018_09_01_180354_add_new_items_to_softdrinks',1);
INSERT INTO `migrations` VALUES (148,'2018_09_01_185618_add_new_item_to_sanitary',1);
INSERT INTO `migrations` VALUES (149,'2018_09_01_210254_add_pintglass_to_alcohol',1);
INSERT INTO `migrations` VALUES (150,'2018_10_17_221244_add_litterduck_to_trashdog',1);
INSERT INTO `migrations` VALUES (151,'2018_10_18_220654_add_styroform_to_coastal',1);
INSERT INTO `migrations` VALUES (152,'2018_10_18_220716_add_styrofoam_cups_to_softdrinks',1);
INSERT INTO `migrations` VALUES (153,'2018_10_18_221032_add_batteries_to_others_2',1);
INSERT INTO `migrations` VALUES (154,'2018_10_18_222101_add_camel_to_brands',1);
INSERT INTO `migrations` VALUES (155,'2019_02_16_104906_add_show_nameusername_locations',1);
INSERT INTO `migrations` VALUES (156,'2019_02_16_105008_add_countrynamec_to_countries',1);
INSERT INTO `migrations` VALUES (157,'2019_02_22_221347_create_websockets_statistics_entries_table',1);
INSERT INTO `migrations` VALUES (158,'2019_03_18_210704_add_cascade_delete_to_photos',1);
INSERT INTO `migrations` VALUES (159,'2019_03_18_211946_add_more_cascade_delete_to_photos',1);
INSERT INTO `migrations` VALUES (160,'2019_03_18_213310_add_even_more_cascade_delete_to_photos',1);
INSERT INTO `migrations` VALUES (161,'2019_03_31_085602_create_politicals_table',1);
INSERT INTO `migrations` VALUES (162,'2019_04_10_165600_add_politicals_id_to_photos',1);
INSERT INTO `migrations` VALUES (163,'2019_05_03_000001_create_customer_columns',1);
INSERT INTO `migrations` VALUES (164,'2019_05_03_000002_create_subscriptions_table',1);
INSERT INTO `migrations` VALUES (165,'2019_05_03_000003_create_subscription_items_table',1);
INSERT INTO `migrations` VALUES (166,'2019_05_29_232735_create_failed_jobs_table',1);
INSERT INTO `migrations` VALUES (167,'2019_10_13_141631_add_country_flag_to_global_leaderboard_settings_users',1);
INSERT INTO `migrations` VALUES (168,'2019_12_14_142815_add_web_or_mobile_and_who_verified_to_photos',1);
INSERT INTO `migrations` VALUES (169,'2020_04_25_133357_add_gloves_to_sanitary_table',1);
INSERT INTO `migrations` VALUES (170,'2020_04_25_162711_add_facemask_to_sanitary',1);
INSERT INTO `migrations` VALUES (171,'2020_05_10_114433_add_previous_tags_to_users_settings',1);
INSERT INTO `migrations` VALUES (172,'2020_05_22_172513_create_dumps_table',1);
INSERT INTO `migrations` VALUES (173,'2020_05_22_172548_create_farms_table',1);
INSERT INTO `migrations` VALUES (174,'2020_05_22_174329_add_new_items_to_alcohol',1);
INSERT INTO `migrations` VALUES (175,'2020_05_22_174341_add_new_items_to_smoking',1);
INSERT INTO `migrations` VALUES (176,'2020_05_23_174742_add_random_litter_to_other',1);
INSERT INTO `migrations` VALUES (177,'2020_05_23_211204_create_industry_table',1);
INSERT INTO `migrations` VALUES (178,'2020_05_23_212028_add_new_relationships_to_photos',1);
INSERT INTO `migrations` VALUES (179,'2020_05_23_215329_add_new_totals_to_locations',1);
INSERT INTO `migrations` VALUES (180,'2020_05_31_172148_change_alcohol_plastic_cups_name',1);
INSERT INTO `migrations` VALUES (181,'2020_06_03_223533_add_pizza_boxes_to_food',1);
INSERT INTO `migrations` VALUES (182,'2020_06_13_112015_change_smoking_plastic_name',1);
INSERT INTO `migrations` VALUES (183,'2020_06_21_154338_add_photos_per_month_string_to_countries',1);
INSERT INTO `migrations` VALUES (184,'2020_06_21_154357_add_photos_per_month_string_to_states',1);
INSERT INTO `migrations` VALUES (185,'2020_06_21_154413_add_photos_per_month_string_to_cities',1);
INSERT INTO `migrations` VALUES (186,'2020_07_18_230712_add_coorindates_to_photos',1);
INSERT INTO `migrations` VALUES (187,'2020_08_09_181045_change_donate_column_name_to_amount',1);
INSERT INTO `migrations` VALUES (188,'2020_08_20_211655_create_subscribers_table',1);
INSERT INTO `migrations` VALUES (189,'2020_08_27_213532_add_product_id_to_plans',1);
INSERT INTO `migrations` VALUES (190,'2020_09_06_204142_add_plan_id_to_plans',1);
INSERT INTO `migrations` VALUES (191,'2020_09_10_194359_change_stripe_id_to_null_in_payments',1);
INSERT INTO `migrations` VALUES (192,'2020_09_13_113648_change_items_remaining_to_bool',1);
INSERT INTO `migrations` VALUES (193,'2020_09_13_114634_create_user_settings_table',1);
INSERT INTO `migrations` VALUES (194,'2020_09_13_133848_add_email_sub_to_users_settings',1);
INSERT INTO `migrations` VALUES (195,'2020_10_10_114038_add_geohash_to_photos',1);
INSERT INTO `migrations` VALUES (196,'2020_10_17_134214_create_clusters_table',1);
INSERT INTO `migrations` VALUES (197,'2020_10_17_163329_add_zoom_level_to_clusters',1);
INSERT INTO `migrations` VALUES (198,'2020_11_08_214933_add_total_litter_to_cities',1);
INSERT INTO `migrations` VALUES (199,'2020_11_08_223610_add_total_litter_to_states',1);
INSERT INTO `migrations` VALUES (200,'2020_11_08_223654_add_total_litter_to_countries',1);
INSERT INTO `migrations` VALUES (201,'2020_11_17_215551_add_missing_values_to_teams',1);
INSERT INTO `migrations` VALUES (202,'2020_11_17_220642_add_create_teams_remaining_to_users',1);
INSERT INTO `migrations` VALUES (203,'2020_11_18_222726_change_photos_column_softdrinks_name',1);
INSERT INTO `migrations` VALUES (204,'2020_11_20_230110_add_team_id_to_photos',1);
INSERT INTO `migrations` VALUES (205,'2020_11_22_214739_add_total_photos_and_litter_to_teams_pivot',1);
INSERT INTO `migrations` VALUES (206,'2020_12_06_175558_add_settings_to_each_team',1);
INSERT INTO `migrations` VALUES (207,'2020_12_13_185423_add_hand_sanitizers_to_sanitary',1);
INSERT INTO `migrations` VALUES (208,'2020_12_15_224429_rename_tables_for_easier_translation',1);
INSERT INTO `migrations` VALUES (209,'2021_01_23_172703_add_photos_per_month_to_users',1);
INSERT INTO `migrations` VALUES (210,'2021_01_23_182830_change_user_total_category_names',1);
INSERT INTO `migrations` VALUES (211,'2021_01_24_162859_add_total_brands_to_users',1);
INSERT INTO `migrations` VALUES (212,'2021_03_08_211302_create_annotations_table',1);
INSERT INTO `migrations` VALUES (213,'2021_03_15_214607_add_bbox_skip_to_photos',1);
INSERT INTO `migrations` VALUES (214,'2021_03_18_201953_add_brands_column_to_annotations',1);
INSERT INTO `migrations` VALUES (215,'2021_03_19_204129_add_bbox_littercoin_to_users',1);
INSERT INTO `migrations` VALUES (216,'2021_03_19_205805_bbox_photo_assigned_to',1);
INSERT INTO `migrations` VALUES (217,'2021_03_20_154131_add_wrong_tags_to_photos',1);
INSERT INTO `migrations` VALUES (218,'2021_03_20_214745_can_bbox',1);
INSERT INTO `migrations` VALUES (219,'2021_03_21_211726_add_ghostnets_to_coastal',1);
INSERT INTO `migrations` VALUES (220,'2021_03_23_210323_create_permission_tables',1);
INSERT INTO `migrations` VALUES (221,'2021_03_28_142945_add_bbox_verification_assigned_to',1);
INSERT INTO `migrations` VALUES (222,'2021_04_03_104427_add_500x500_filepath_to_photos',1);
INSERT INTO `migrations` VALUES (223,'2021_04_10_163940_change_dogshit_table',1);
INSERT INTO `migrations` VALUES (224,'2021_04_10_171505_add_dogshit_id_to_photos',1);
INSERT INTO `migrations` VALUES (225,'2021_04_10_173250_add_total_dogshit_to_countries',1);
INSERT INTO `migrations` VALUES (226,'2021_04_10_174213_add_total_dogshit_to_users',1);
INSERT INTO `migrations` VALUES (227,'2021_04_24_113059_is_visible_on_leaderboards',1);
INSERT INTO `migrations` VALUES (228,'2021_05_24_220324_add_broken_glass_to_softdrinks',1);
INSERT INTO `migrations` VALUES (229,'2021_06_19_213525_add_address_array_to_photos',1);
INSERT INTO `migrations` VALUES (230,'2021_06_21_220009_add_default_value_to_cityname_on_cities',1);
INSERT INTO `migrations` VALUES (231,'2021_06_21_220629_add_default_value_to_statename_on_states',1);
INSERT INTO `migrations` VALUES (232,'2021_06_21_220729_add_default_value_to_countryname_on_countries',1);
INSERT INTO `migrations` VALUES (233,'2021_07_22_083339_fix_photos_foreign_keys',1);
INSERT INTO `migrations` VALUES (234,'2021_07_30_192305_add_albertheijn_brand_to_brands',1);
INSERT INTO `migrations` VALUES (235,'2021_10_19_133122_add_new_tags',1);
INSERT INTO `migrations` VALUES (236,'2021_10_24_113054_add_trusted_column_to_teams',1);
INSERT INTO `migrations` VALUES (237,'2021_12_28_174234_create_team_clusters_table',1);
INSERT INTO `migrations` VALUES (238,'2022_01_05_151733_add_year_to_global_clusters',1);
INSERT INTO `migrations` VALUES (239,'2022_02_03_173026_create_custom_tags_table',1);
INSERT INTO `migrations` VALUES (240,'2022_03_02_145633_add_new_brand_tags',1);
INSERT INTO `migrations` VALUES (241,'2022_03_03_140223_add_created_at_indexes',1);
INSERT INTO `migrations` VALUES (242,'2022_03_24_123909_create_admin_verification_logs_table',1);
INSERT INTO `migrations` VALUES (243,'2022_04_10_210041_add_new_brand_tags2',1);
INSERT INTO `migrations` VALUES (244,'2022_04_24_152210_add_user_settings',1);
INSERT INTO `migrations` VALUES (245,'2022_05_31_151921_create_material_table',1);
INSERT INTO `migrations` VALUES (246,'2022_06_14_133103_add_prevent_others_tagging_column_to_users',1);
INSERT INTO `migrations` VALUES (247,'2022_07_23_133530_create_littercoins_table',1);
INSERT INTO `migrations` VALUES (248,'2022_07_31_193517_create_cleanups_table',1);
INSERT INTO `migrations` VALUES (249,'2022_07_31_203008_create_cleanup_user_table',1);
INSERT INTO `migrations` VALUES (250,'2022_08_04_223959_change_material_id_foreign_key_set_null_on_cascade',1);
INSERT INTO `migrations` VALUES (251,'2022_08_27_141235_add_littercoin_id_to_photos',1);
INSERT INTO `migrations` VALUES (252,'2022_09_03_153810_change_default_pickedup',1);
