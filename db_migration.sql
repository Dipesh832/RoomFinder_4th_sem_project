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



    -- RoomFinder
-- Migration for an existing database: add the owner-defined maximum occupants
-- column to the rooms table.
--
-- The owner explicitly enters how many people the property allows (1 - 20).
--
-- Run against the existing database (do NOT re-run db.sql):
--   mariadb -u <user> -p roomfinderDB < db_migration_max_occupants.sql

ALTER TABLE rooms
    ADD COLUMN max_occupants INT NOT NULL DEFAULT 1 AFTER room_type;


    -- RoomFinder
-- Migration for an existing database: add the property category column to
-- the rooms table.
--
-- Categories are limited to 'Room' and 'Flat/Apartment'. The room_type column
-- keeps its existing values; existing rows get an empty string.
--
-- Run against the existing database (do NOT re-run db.sql):
--   mariadb -u <user> -p roomfinderDB < db_migration_category.sql

ALTER TABLE rooms
    ADD COLUMN category VARCHAR(50) NOT NULL AFTER price;


    -- RoomFinder
-- Migration for an existing database: add the occupation column to the
-- booking_members table.
--
-- Occupations are 'Student', 'Job/Employed', 'Self-employed/Business',
-- or free text entered when the tenant chooses 'Other'.
--
-- Safe for a populated table: the column is added with a temporary
-- default so existing rows are backfilled, then the default is dropped
-- so the final definition matches db.sql (VARCHAR(100) NOT NULL).
-- IF NOT EXISTS prevents a duplicate column if already migrated.
--
-- Run against the existing database (do NOT re-run db.sql):
--   mariadb -u <user> -p roomfinderDB < db_migration.sql

ALTER TABLE booking_members
    ADD COLUMN IF NOT EXISTS occupation VARCHAR(100) NOT NULL DEFAULT '' AFTER contact_number;

UPDATE booking_members
    SET occupation = ''
    WHERE occupation IS NULL;

ALTER TABLE booking_members
    ALTER COLUMN occupation DROP DEFAULT;