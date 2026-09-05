<?php
// tests/bootstrap.php — autoloads CureRx classes + provides testDbPdo() for integration tests

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Returns a fresh SQLite in-memory PDO with all application tables.
 * Call in setUpBeforeClass() for each integration test class.
 * Uses SQLite-compatible DDL (no FULLTEXT, no ENUM, no AUTO_INCREMENT).
 */
function testDbPdo(): \PDO
{
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec("
        CREATE TABLE patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_code TEXT UNIQUE,
            name TEXT NOT NULL,
            age INTEGER,
            gender TEXT,
            phone TEXT,
            address TEXT,
            allergies TEXT,
            blood_group TEXT,
            deleted_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            qualifications TEXT,
            slmc_reg TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE prescriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER,
            patient_name TEXT,
            patient_age INTEGER,
            patient_gender TEXT,
            patient_phone TEXT,
            doctor_name TEXT,
            diagnosis TEXT,
            follow_up_date TEXT,
            consultation_fee REAL DEFAULT 0,
            medicines_total REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            grand_total REAL DEFAULT 0,
            deleted_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (patient_id) REFERENCES patients(id)
        );

        CREATE TABLE prescription_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prescription_id INTEGER NOT NULL,
            medicine_name TEXT NOT NULL,
            strength TEXT,
            dosage TEXT,
            instruction TEXT,
            duration TEXT,
            quantity TEXT,
            unit_price REAL DEFAULT 0,
            item_total REAL DEFAULT 0,
            item_source TEXT DEFAULT 'clinic',
            FOREIGN KEY (prescription_id) REFERENCES prescriptions(id)
        );

        CREATE TABLE waiting_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL,
            token_number INTEGER NOT NULL,
            status TEXT DEFAULT 'Waiting',
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT,
            table_name TEXT,
            record_id INTEGER,
            old_values TEXT,
            new_values TEXT,
            workstation TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");

    return $pdo;
}
