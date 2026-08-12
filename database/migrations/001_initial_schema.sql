-- 001_initial_schema.sql
-- Frozen initial MySQL 8.4 schema for Frequent Itemset Mining & Apriori Pruning Dashboard

CREATE TABLE IF NOT EXISTS `datasets` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `source_filename` VARCHAR(255) NOT NULL,
  `format` VARCHAR(32) NOT NULL,
  `sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `byte_size` BIGINT UNSIGNED NOT NULL,
  `transaction_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `unique_item_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_datasets_created_at` (`created_at`),
  INDEX `idx_datasets_sha256` (`sha256`),
  CONSTRAINT `chk_datasets_format` CHECK (`format` IN ('basket_csv', 'basket_txt', 'mushroom'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dataset_id` BIGINT UNSIGNED NOT NULL,
  `transaction_key` VARCHAR(64) NOT NULL,
  `ordinal` INT UNSIGNED NOT NULL,
  CONSTRAINT `fk_transactions_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `uq_transactions_dataset_key` UNIQUE (`dataset_id`, `transaction_key`),
  CONSTRAINT `uq_transactions_dataset_ordinal` UNIQUE (`dataset_id`, `ordinal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_items` (
  `transaction_id` BIGINT UNSIGNED NOT NULL,
  `item_key` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`transaction_id`, `item_key`),
  CONSTRAINT `fk_transaction_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  INDEX `idx_transaction_items_item_trans` (`item_key`, `transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `experiment_runs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dataset_id` BIGINT UNSIGNED NOT NULL,
  `min_support` DECIMAL(7,6) NOT NULL,
  `min_confidence` DECIMAL(7,6) NOT NULL,
  `runtime_ms` DECIMAL(12,3) NOT NULL,
  `rule_generation_runtime_ms` DECIMAL(12,3) NOT NULL,
  `candidates_generated` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `candidates_pruned` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `candidates_evaluated` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `frequent_itemsets` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `rules_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `max_k` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_experiment_runs_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`id`) ON DELETE RESTRICT,
  INDEX `idx_experiment_runs_dataset_created` (`dataset_id`, `created_at`),
  INDEX `idx_experiment_runs_dataset_params` (`dataset_id`, `min_support`, `min_confidence`),
  CONSTRAINT `chk_experiment_runs_min_support` CHECK (`min_support` > 0.000000 AND `min_support` <= 1.000000),
  CONSTRAINT `chk_experiment_runs_min_confidence` CHECK (`min_confidence` >= 0.000000 AND `min_confidence` <= 1.000000),
  CONSTRAINT `chk_experiment_runs_runtime` CHECK (`runtime_ms` >= 0.000),
  CONSTRAINT `chk_experiment_runs_rule_runtime` CHECK (`rule_generation_runtime_ms` >= 0.000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `experiment_run_levels` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `k` SMALLINT UNSIGNED NOT NULL,
  `source` VARCHAR(24) NOT NULL,
  `generated` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `pruned` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `evaluated` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `frequent` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`run_id`, `k`),
  CONSTRAINT `fk_experiment_run_levels_run` FOREIGN KEY (`run_id`) REFERENCES `experiment_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_experiment_run_levels_k` CHECK (`k` >= 1),
  CONSTRAINT `chk_experiment_run_levels_source` CHECK (`source` IN ('singleton_scan', 'join_prune')),
  CONSTRAINT `chk_experiment_run_levels_pruned_evaluated` CHECK (`pruned` + `evaluated` = `generated`),
  CONSTRAINT `chk_experiment_run_levels_frequent` CHECK (`frequent` <= `evaluated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
