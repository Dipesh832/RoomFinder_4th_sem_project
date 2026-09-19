-- RoomFinder
-- Migration for an existing database: add the free-text relationship detail
-- column to the bookings table.
--
-- For relationship = 'Other' the tenant's explanation is stored here.
-- For any other relationship the column stays NULL.
--
-- Run against the existing database (do NOT re-run db.sql):
--   mariadb -u <user> -p roomfinderDB < db_migration_relationship_detail.sql

ALTER TABLE bookings
    ADD COLUMN relationship_detail VARCHAR(100) NULL AFTER relationship;