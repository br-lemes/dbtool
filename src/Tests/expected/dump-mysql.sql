/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_name_idx` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `users` VALUES (1,'John Doe','john.doe@example.com','$2y$10$abc123hashedPassword','2025-07-24 09:00:00',NULL),(2,'Jane Smith','jane.smith@example.com','$2y$10$xyz789hashedPassword','2025-07-24 09:15:00','2025-07-24 10:00:00'),(3,'Alice Brown','alice.brown@example.com','$2y$10$def456hashedPassword','2025-07-24 09:30:00',NULL);
