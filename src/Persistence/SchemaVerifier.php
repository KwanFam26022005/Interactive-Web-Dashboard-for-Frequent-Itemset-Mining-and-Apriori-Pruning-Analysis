<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

class SchemaVerifier
{
    /**
     * Verifies the database connection against the frozen Phase 1 database schema contract.
     * Returns a list of structural error descriptions (empty list means 100% schema compliance).
     *
     * @return list<string>
     */
    public static function verify(PDO $pdo): array
    {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if (!$dbName) {
            return ["No database selected."];
        }

        $errors = [];

        $expectedTables = [
            'datasets' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'extra' => 'auto_increment'],
                    'name' => ['type' => 'varchar(120)', 'nullable' => 'NO'],
                    'source_filename' => ['type' => 'varchar(255)', 'nullable' => 'NO'],
                    'format' => ['type' => 'varchar(32)', 'nullable' => 'NO', 'collation' => 'utf8mb4_bin'],
                    'sha256' => ['type' => 'char(64)', 'nullable' => 'NO', 'collation' => 'ascii_general_ci'],
                    'byte_size' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
                    'transaction_count' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'unique_item_count' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'pk' => ['id'],
                'indexes' => [
                    'idx_datasets_created_at' => ['created_at'],
                    'idx_datasets_sha256' => ['sha256'],
                ],
                'checks' => ['chk_datasets_format'],
            ],
            'transactions' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'extra' => 'auto_increment'],
                    'dataset_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
                    'transaction_key' => ['type' => 'varchar(64)', 'nullable' => 'NO'],
                    'ordinal' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO'],
                ],
                'pk' => ['id'],
                'fks' => [
                    'dataset_id' => ['ref_table' => 'datasets', 'ref_column' => 'id', 'delete_rule' => 'CASCADE'],
                ],
                'uniques' => [
                    'uq_transactions_dataset_key' => ['dataset_id', 'transaction_key'],
                    'uq_transactions_dataset_ordinal' => ['dataset_id', 'ordinal'],
                ],
            ],
            'transaction_items' => [
                'columns' => [
                    'transaction_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
                    'item_key' => ['type' => 'varchar(128)', 'nullable' => 'NO', 'collation' => 'utf8mb4_bin'],
                ],
                'pk' => ['transaction_id', 'item_key'],
                'fks' => [
                    'transaction_id' => ['ref_table' => 'transactions', 'ref_column' => 'id', 'delete_rule' => 'CASCADE'],
                ],
                'indexes' => [
                    'idx_transaction_items_item_trans' => ['item_key', 'transaction_id'],
                ],
            ],
            'experiment_runs' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'extra' => 'auto_increment'],
                    'dataset_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
                    'min_support' => ['type' => 'decimal(7,6)', 'nullable' => 'NO'],
                    'min_confidence' => ['type' => 'decimal(7,6)', 'nullable' => 'NO'],
                    'runtime_ms' => ['type' => 'decimal(12,3)', 'nullable' => 'NO'],
                    'rule_generation_runtime_ms' => ['type' => 'decimal(12,3)', 'nullable' => 'NO'],
                    'candidates_generated' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'candidates_pruned' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'candidates_evaluated' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'frequent_itemsets' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'rules_count' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'max_k' => ['type' => 'smallint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'pk' => ['id'],
                'fks' => [
                    'dataset_id' => ['ref_table' => 'datasets', 'ref_column' => 'id', 'delete_rule' => 'RESTRICT'],
                ],
                'indexes' => [
                    'idx_experiment_runs_dataset_created' => ['dataset_id', 'created_at'],
                    'idx_experiment_runs_dataset_params' => ['dataset_id', 'min_support', 'min_confidence'],
                ],
                'checks' => [
                    'chk_experiment_runs_min_support',
                    'chk_experiment_runs_min_confidence',
                    'chk_experiment_runs_runtime',
                    'chk_experiment_runs_rule_runtime',
                ],
            ],
            'experiment_run_levels' => [
                'columns' => [
                    'run_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
                    'k' => ['type' => 'smallint', 'unsigned' => true, 'nullable' => 'NO'],
                    'source' => ['type' => 'varchar(24)', 'nullable' => 'NO', 'collation' => 'utf8mb4_bin'],
                    'generated' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'pruned' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'evaluated' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                    'frequent' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'default' => '0'],
                ],
                'pk' => ['run_id', 'k'],
                'fks' => [
                    'run_id' => ['ref_table' => 'experiment_runs', 'ref_column' => 'id', 'delete_rule' => 'CASCADE'],
                ],
                'checks' => [
                    'chk_experiment_run_levels_k',
                    'chk_experiment_run_levels_source',
                    'chk_experiment_run_levels_pruned_evaluated',
                    'chk_experiment_run_levels_frequent',
                ],
            ],
        ];

        // Query existing tables in schema
        $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ?");
        $stmt->execute([$dbName]);
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $table => $spec) {
            if (!in_array($table, $existingTables, true)) {
                $errors[] = "Table '{$table}' is missing.";
                continue;
            }

            // Introspect Columns ordered by ORDINAL_POSITION
            $colStmt = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLLATION_NAME, EXTRA FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
            $colStmt->execute([$dbName, $table]);
            $colRows = $colStmt->fetchAll(PDO::FETCH_ASSOC);

            $cols = [];
            foreach ($colRows as $r) {
                $rUpper = array_change_key_case($r, CASE_UPPER);
                $cols[$rUpper['COLUMN_NAME']] = $rUpper;
            }

            $expectedColOrder = array_keys($spec['columns']);
            $actualColOrder = array_keys($cols);

            if ($expectedColOrder !== $actualColOrder) {
                $errors[] = "Table '{$table}' column order mismatch. Expected [" . implode(', ', $expectedColOrder) . "], got [" . implode(', ', $actualColOrder) . "].";
            }

            foreach ($spec['columns'] as $colName => $expectedCol) {
                if (!isset($cols[$colName])) {
                    $errors[] = "Table '{$table}' missing column '{$colName}'.";
                    continue;
                }

                $actualCol = $cols[$colName];
                $actualType = strtolower($actualCol['COLUMN_TYPE']);

                if (isset($expectedCol['type']) && !str_contains($actualType, strtolower($expectedCol['type']))) {
                    $errors[] = "Table '{$table}' column '{$colName}' type mismatch. Expected containing '{$expectedCol['type']}', got '{$actualType}'.";
                }

                if (!empty($expectedCol['unsigned']) && !str_contains($actualType, 'unsigned')) {
                    $errors[] = "Table '{$table}' column '{$colName}' missing UNSIGNED attribute.";
                }

                if (isset($expectedCol['nullable']) && $actualCol['IS_NULLABLE'] !== $expectedCol['nullable']) {
                    $errors[] = "Table '{$table}' column '{$colName}' nullability mismatch. Expected '{$expectedCol['nullable']}', got '{$actualCol['IS_NULLABLE']}'.";
                }

                if (isset($expectedCol['extra']) && !str_contains(strtolower($actualCol['EXTRA']), strtolower($expectedCol['extra']))) {
                    $errors[] = "Table '{$table}' column '{$colName}' extra mismatch. Expected '{$expectedCol['extra']}', got '{$actualCol['EXTRA']}'.";
                }

                if (isset($expectedCol['collation']) && $actualCol['COLLATION_NAME'] !== $expectedCol['collation']) {
                    $errors[] = "Table '{$table}' column '{$colName}' collation mismatch. Expected '{$expectedCol['collation']}', got '{$actualCol['COLLATION_NAME']}'.";
                }
            }

            // Introspect Primary Key
            $pkStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
            $pkStmt->execute([$dbName, $table]);
            $actualPk = $pkStmt->fetchAll(PDO::FETCH_COLUMN);

            if (isset($spec['pk']) && $spec['pk'] !== $actualPk) {
                $errors[] = "Table '{$table}' Primary Key mismatch. Expected [" . implode(', ', $spec['pk']) . "], got [" . implode(', ', $actualPk) . "].";
            }

            // Introspect Foreign Keys
            if (isset($spec['fks'])) {
                $fkSql = "SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                          FROM information_schema.key_column_usage k
                          JOIN information_schema.referential_constraints r
                            ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
                          WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL";
                $fkStmt = $pdo->prepare($fkSql);
                $fkStmt->execute([$dbName, $table]);
                $fkRows = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

                $actualFks = [];
                foreach ($fkRows as $r) {
                    $rUpper = array_change_key_case($r, CASE_UPPER);
                    $actualFks[$rUpper['COLUMN_NAME']] = [
                        'ref_table' => $rUpper['REFERENCED_TABLE_NAME'],
                        'ref_column' => $rUpper['REFERENCED_COLUMN_NAME'],
                        'delete_rule' => $rUpper['DELETE_RULE'],
                    ];
                }

                foreach ($spec['fks'] as $colName => $expectedFk) {
                    if (!isset($actualFks[$colName])) {
                        $errors[] = "Table '{$table}' missing foreign key on column '{$colName}'.";
                        continue;
                    }

                    $af = $actualFks[$colName];
                    if ($af['ref_table'] !== $expectedFk['ref_table'] || $af['ref_column'] !== $expectedFk['ref_column'] || $af['delete_rule'] !== $expectedFk['delete_rule']) {
                        $errors[] = "Table '{$table}' foreign key on '{$colName}' mismatch. Expected -> {$expectedFk['ref_table']}.{$expectedFk['ref_column']} ON DELETE {$expectedFk['delete_rule']}, got -> {$af['ref_table']}.{$af['ref_column']} ON DELETE {$af['delete_rule']}.";
                    }
                }
            }

            // Introspect Unique Constraints
            if (isset($spec['uniques'])) {
                $uniqSql = "SELECT CONSTRAINT_NAME, COLUMN_NAME
                            FROM information_schema.key_column_usage
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME != 'PRIMARY' AND CONSTRAINT_NAME LIKE 'uq_%'
                            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION";
                $uniqStmt = $pdo->prepare($uniqSql);
                $uniqStmt->execute([$dbName, $table]);
                $uniqRows = $uniqStmt->fetchAll(PDO::FETCH_ASSOC);

                $actualUniques = [];
                foreach ($uniqRows as $r) {
                    $rUpper = array_change_key_case($r, CASE_UPPER);
                    $actualUniques[$rUpper['CONSTRAINT_NAME']][] = $rUpper['COLUMN_NAME'];
                }

                foreach ($spec['uniques'] as $uName => $expectedCols) {
                    if (!isset($actualUniques[$uName])) {
                        $errors[] = "Table '{$table}' missing unique constraint '{$uName}'.";
                    } else if ($actualUniques[$uName] !== $expectedCols) {
                        $errors[] = "Table '{$table}' unique constraint '{$uName}' columns mismatch. Expected [" . implode(', ', $expectedCols) . "], got [" . implode(', ', $actualUniques[$uName]) . "].";
                    }
                }
            }

            // Introspect Non-unique Indexes
            if (isset($spec['indexes'])) {
                $idxSql = "SELECT INDEX_NAME, COLUMN_NAME
                           FROM information_schema.statistics
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 1
                           ORDER BY INDEX_NAME, SEQ_IN_INDEX";
                $idxStmt = $pdo->prepare($idxSql);
                $idxStmt->execute([$dbName, $table]);
                $idxRows = $idxStmt->fetchAll(PDO::FETCH_ASSOC);

                $actualIdxs = [];
                foreach ($idxRows as $r) {
                    $rUpper = array_change_key_case($r, CASE_UPPER);
                    $actualIdxs[$rUpper['INDEX_NAME']][] = $rUpper['COLUMN_NAME'];
                }

                foreach ($spec['indexes'] as $idxName => $expectedCols) {
                    if (!isset($actualIdxs[$idxName])) {
                        $errors[] = "Table '{$table}' missing required index '{$idxName}'.";
                    } else if ($actualIdxs[$idxName] !== $expectedCols) {
                        $errors[] = "Table '{$table}' index '{$idxName}' columns mismatch. Expected [" . implode(', ', $expectedCols) . "], got [" . implode(', ', $actualIdxs[$idxName]) . "].";
                    }
                }
            }

            // Introspect CHECK Constraints
            if (isset($spec['checks'])) {
                $chkSql = "SELECT CONSTRAINT_NAME
                           FROM information_schema.table_constraints
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'";
                $chkStmt = $pdo->prepare($chkSql);
                $chkStmt->execute([$dbName, $table]);
                $actualChecks = $chkStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($spec['checks'] as $expectedChk) {
                    if (!in_array($expectedChk, $actualChecks, true)) {
                        $errors[] = "Table '{$table}' missing CHECK constraint '{$expectedChk}'.";
                    }
                }
            }
        }

        return $errors;
    }
}
