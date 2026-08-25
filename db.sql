CREATE DATABASE roomfinderDB;

USE roomfinder DB;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL ,
    password VARCHAR(255) NOT NULL,
    role ENUM('tenant', 'owner', 'admin') NOT NULL DEFAULT 'tenant',
    remember_token VARCHAR(255) NULL,
    remember_expires_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    room_type VARCHAR(50) NOT NULL,
    image VARCHAR(255),
    status ENUM('available', 'booked') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE (user_id, room_id)
);


CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    booking_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (tenant_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
