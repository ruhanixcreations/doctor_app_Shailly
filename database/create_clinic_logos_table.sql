-- Create clinic_logos table for storing hospital/clinic logos
-- Supports multiple logos per client with active status

CREATE TABLE IF NOT EXISTS `clinic_logos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `logo_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_active` (`client_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
