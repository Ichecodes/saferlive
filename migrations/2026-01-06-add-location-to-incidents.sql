-- Migration: add `location` column to incidents
-- Run with: mysql -u user -p database_name < 2026-01-06-add-location-to-incidents.sql
ALTER TABLE incidents
  ADD COLUMN `location` TEXT NULL;
