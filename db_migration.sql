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
    ADD COLUMN relationship_detail V
    ARCHAR(100) NULL AFTER relationship;



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