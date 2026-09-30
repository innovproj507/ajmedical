-- =====================================================================
-- Plugin Catálogo virtual — tablas propias (prefijo cat_)
-- Se ejecuta automáticamente la primera vez que se activa el plugin en /admin/plugins.
-- =====================================================================

CREATE TABLE IF NOT EXISTS cat_categorias (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    parent_id     INT DEFAULT NULL,
    nombre        VARCHAR(150) NOT NULL,
    slug          VARCHAR(180) NOT NULL,
    descripcion   TEXT,
    imagen_id     INT DEFAULT NULL,
    orden         INT NOT NULL DEFAULT 0,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cat_slug (slug),
    KEY idx_cat_parent (parent_id),
    CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES cat_categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_cat_imagen FOREIGN KEY (imagen_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cat_marcas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(150) NOT NULL,
    razon_social  VARCHAR(255) DEFAULT NULL,
    slug          VARCHAR(180) NOT NULL,
    logo_id       INT DEFAULT NULL,
    orden         INT NOT NULL DEFAULT 0,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_marca_slug (slug),
    CONSTRAINT fk_marca_logo FOREIGN KEY (logo_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cat_productos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id        INT DEFAULT NULL,
    marca_id            INT DEFAULT NULL,
    sku                 VARCHAR(80) DEFAULT NULL,
    nombre              VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    resumen             VARCHAR(500) DEFAULT NULL,
    descripcion         MEDIUMTEXT,
    especificaciones    JSON DEFAULT NULL,
    presentacion        VARCHAR(255) DEFAULT NULL,
    registro_sanitario  VARCHAR(120) DEFAULT NULL,
    -- Precio opcional: solo se muestra si CATALOGO_MOSTRAR_PRECIOS = true y el producto lo permite.
    precio              DECIMAL(12,2) DEFAULT NULL,
    precio_oferta       DECIMAL(12,2) DEFAULT NULL,
    mostrar_precio      TINYINT(1) NOT NULL DEFAULT 1,
    disponibilidad      ENUM('disponible','bajo_pedido','agotado') NOT NULL DEFAULT 'disponible',
    destacado           TINYINT(1) NOT NULL DEFAULT 0,
    estado              ENUM('draft','published') NOT NULL DEFAULT 'published',
    imagen_id           INT DEFAULT NULL,
    ficha_tecnica_id    INT DEFAULT NULL,
    orden               INT NOT NULL DEFAULT 0,
    seo_title           VARCHAR(255) DEFAULT NULL,
    seo_description     VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prod_slug (slug),
    UNIQUE KEY uk_prod_sku (sku),
    KEY idx_prod_estado (estado, destacado),
    KEY idx_prod_categoria (categoria_id),
    KEY idx_prod_marca (marca_id),
    CONSTRAINT fk_prod_categoria FOREIGN KEY (categoria_id) REFERENCES cat_categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_prod_marca FOREIGN KEY (marca_id) REFERENCES cat_marcas (id) ON DELETE SET NULL,
    CONSTRAINT fk_prod_imagen FOREIGN KEY (imagen_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_prod_ficha FOREIGN KEY (ficha_tecnica_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galería de imágenes del producto (la principal también vive en cat_productos.imagen_id)
CREATE TABLE IF NOT EXISTS cat_producto_imagenes (
    producto_id   INT NOT NULL,
    media_id      INT NOT NULL,
    orden         INT NOT NULL DEFAULT 0,
    PRIMARY KEY (producto_id, media_id),
    CONSTRAINT fk_pi_producto FOREIGN KEY (producto_id) REFERENCES cat_productos (id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de cotización enviadas desde el sitio
CREATE TABLE IF NOT EXISTS cat_cotizaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    codigo        VARCHAR(30) NOT NULL,
    nombre        VARCHAR(255) NOT NULL,
    empresa       VARCHAR(255) DEFAULT NULL,
    correo        VARCHAR(255) NOT NULL,
    telefono      VARCHAR(40) DEFAULT NULL,
    mensaje       TEXT,
    items         JSON NOT NULL,
    estado        ENUM('nueva','en_proceso','respondida','cerrada') NOT NULL DEFAULT 'nueva',
    notas_admin   TEXT,
    ip_address    VARCHAR(45) DEFAULT NULL,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cot_codigo (codigo),
    KEY idx_cot_estado (estado),
    KEY idx_cot_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración del catálogo (editable en /admin/settings, categoría "catalogo")
INSERT IGNORE INTO settings (`key`, value, label, category, type) VALUES
('CATALOGO_TITULO', 'Catálogo de productos', 'Título del catálogo', 'catalogo', 'text'),
('CATALOGO_INTRO', 'Explora nuestros insumos médicos y arma tu solicitud de cotización en línea.', 'Texto introductorio', 'catalogo', 'textarea'),
('CATALOGO_MOSTRAR_PRECIOS', 'false', 'Mostrar precios en el sitio', 'catalogo', 'boolean'),
('CATALOGO_MONEDA', '$', 'Símbolo de moneda', 'catalogo', 'text'),
('CATALOGO_NOTA_PRECIOS', 'Precios sujetos a cambio sin previo aviso.', 'Nota bajo los precios', 'catalogo', 'text'),
('CATALOGO_POR_PAGINA', '12', 'Productos por página', 'catalogo', 'number');
