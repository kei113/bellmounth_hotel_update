-- Rename columns in reservasi table
ALTER TABLE `reservasi` CHANGE `no_booking` `no_reservasi` VARCHAR(20);

-- Rename columns in reservasi_detail table
ALTER TABLE `reservasi_detail` CHANGE `booking_id` `reservasi_id` INT(11);
