-- 1.1.0 — Razón social del fabricante (la marca muestra el nombre corto; la ficha, el nombre legal)
ALTER TABLE cat_marcas
    ADD COLUMN razon_social VARCHAR(255) DEFAULT NULL AFTER nombre,
    ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER logo_id;
