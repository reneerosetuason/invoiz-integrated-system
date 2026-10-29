INSERT INTO users (name, last_name, first_name, sex, email, password, phone, birthday, age, role, approval_status, status)
VALUES ('Rider Test', 'Test', 'Rider', 'male', 'rider@invoiz.test', '$2y$12$kp6uQtmFNKbUSVMms4UzgexfYMaOGDiiM56C7EGHVuEdrib3R5Avy', '09123456789', '2000-01-01', 26, 'rider', 'approved', 'active');

INSERT INTO riders (user_id, name, email, phone, vehicle_type, status, is_online, is_verified)
VALUES (LAST_INSERT_ID(), 'Rider Test', 'rider@invoiz.test', '09123456789', 'Motorcycle', 'available', 0, 1);
