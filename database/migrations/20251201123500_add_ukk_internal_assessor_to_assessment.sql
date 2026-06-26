ALTER TABLE ukk_penilaian_siswa
    ADD COLUMN internal_assessor_teacher_id INT UNSIGNED NULL AFTER asesor_id,
    ADD COLUMN internal_assessor_name VARCHAR(255) NULL AFTER internal_assessor_teacher_id,
    ADD CONSTRAINT fk_ukk_penilaian_internal_teacher FOREIGN KEY (internal_assessor_teacher_id) REFERENCES guru(id) ON DELETE SET NULL;
