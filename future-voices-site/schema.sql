-- Future Voices School & Studio — booking backend schema
-- MySQL 8+

CREATE DATABASE IF NOT EXISTS fvss CHARACTER SET utf8mb4;
USE fvss;

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    kind ENUM('practice_room', 'recording_studio') NOT NULL DEFAULT 'practice_room',
    price_rwf INT NOT NULL
);

CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    enrollment_fee_rwf INT NOT NULL
);

-- One row per (room, date, slot). The UNIQUE constraint IS the double-booking
-- guard: two people cannot hold a confirmed/pending row for the same
-- room+date+slot at the same time (see includes/availability.php).
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    booking_date DATE NOT NULL,
    slot ENUM('09:00-12:00', '12:00-16:00', '16:00-20:00') NOT NULL,
    status ENUM('pending_payment', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL for cancelled rows, else 1. MySQL's UNIQUE index allows any
    -- number of NULLs, so cancelled bookings never block a slot, but two
    -- rows that are both pending_payment/confirmed for the same
    -- room+date+slot cannot both exist — that IS the double-booking guard.
    active_hold TINYINT GENERATED ALWAYS AS (IF(status = 'cancelled', NULL, 1)) STORED,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    UNIQUE KEY uniq_slot (room_id, booking_date, slot, active_hold)
);

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('pending_payment', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id)
);

-- Every mobile money attempt, whether for a booking or an enrollment.
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purpose ENUM('booking', 'enrollment') NOT NULL,
    reference_id INT NOT NULL,          -- bookings.id or enrollments.id
    phone VARCHAR(20) NOT NULL,
    amount_rwf INT NOT NULL,
    provider ENUM('mtn_momo', 'airtel_money') NOT NULL,
    provider_transaction_id VARCHAR(64) NULL,
    status ENUM('initiated', 'confirmed', 'failed') NOT NULL DEFAULT 'initiated',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL
);

INSERT INTO rooms (name, kind, price_rwf) VALUES
    ('Practice Room A', 'practice_room', 3000),
    ('Practice Room B', 'practice_room', 3000),
    ('Vocal Booth', 'practice_room', 2500),
    ('Recording Studio', 'recording_studio', 15000);

INSERT INTO programs (name, enrollment_fee_rwf) VALUES
    ('Vocal Training', 10000),
    ('Instrument Lessons — Guitar', 10000),
    ('Instrument Lessons — Piano / Keyboard', 10000),
    ('Instrument Lessons — Drums', 10000),
    ('Instrument Lessons — Violin', 10000),
    ('Instrument Lessons — Saxophone', 10000),
    ('Instrument Lessons — Bass Guitar', 10000),
    ('Instrument Lessons — Flute', 10000),
    ('Sound Engineering & Production', 10000);
