ALTER TABLE guru_jabatan_akademik
    DROP INDEX unique_guru_jabatan_per_tahun,
    ADD UNIQUE KEY unique_guru_jabatan_per_tahun (tahun_ajaran_id, guru_id, jabatan_akademik_id, jurusan_id);
