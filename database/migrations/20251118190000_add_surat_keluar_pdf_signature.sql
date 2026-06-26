ALTER TABLE surat_keluar
    ADD COLUMN pdf_path VARCHAR(190) NULL AFTER isi,
    ADD COLUMN pdf_signature_options TEXT NULL AFTER pdf_path,
    ADD COLUMN pdf_signed_path VARCHAR(190) NULL AFTER pdf_signature_options;
