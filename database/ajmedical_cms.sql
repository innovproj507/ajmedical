-- =====================================================================
-- AJ Medical CMS — Esquema consolidado
-- AJ Medical Supply (basado en el CMS de la SPP)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ajmedical_cms
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ajmedical_cms;

-- ─────────────────────────────────────────────────────────────
-- Administradores (generalizado de diplomado-adolescencia)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE admin_users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    username          VARCHAR(50) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    nombre_completo   VARCHAR(255) NOT NULL,
    rol               ENUM('super_admin','admin','viewer') DEFAULT 'viewer',
    activo            TINYINT(1) DEFAULT 1,
    ultimo_login      TIMESTAMP NULL DEFAULT NULL,
    login_attempts    INT DEFAULT 0,
    locked_until      TIMESTAMP NULL DEFAULT NULL,
    created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_sessions (
    id            VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id       INT NOT NULL,
    ip_address    VARCHAR(45) NOT NULL,
    user_agent    VARCHAR(500) DEFAULT NULL,
    expires_at    TIMESTAMP NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_expires (expires_at),
    KEY idx_user_id (user_id),
    CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ip_address    VARCHAR(45) NOT NULL,
    action        VARCHAR(50) NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip_action (ip_address, action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(100) NOT NULL,
    value       TEXT,
    label       VARCHAR(255) DEFAULT NULL,
    category    VARCHAR(50) NOT NULL DEFAULT 'general',
    type        ENUM('text','boolean','number','date','email','textarea') NOT NULL DEFAULT 'text',
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `key` (`key`),
    KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tabla               VARCHAR(50) NOT NULL,
    registro_id         VARCHAR(50) NOT NULL,
    accion              ENUM('insert','update','delete') NOT NULL,
    datos_anteriores    LONGTEXT,
    datos_nuevos        LONGTEXT,
    usuario             VARCHAR(100) DEFAULT NULL,
    ip_address          VARCHAR(45) DEFAULT NULL,
    fecha_accion        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tabla (tabla),
    KEY idx_registro_id (registro_id),
    KEY idx_fecha_accion (fecha_accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tipos de contenido declarativos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE content_types (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    `key`           VARCHAR(50) NOT NULL,
    label           VARCHAR(100) NOT NULL,
    label_plural    VARCHAR(100) NOT NULL,
    icon            VARCHAR(50) DEFAULT NULL,
    has_taxonomy    TINYINT(1) DEFAULT 0,
    route_prefix    VARCHAR(100) DEFAULT NULL,
    fields_schema   JSON NOT NULL,
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_entries (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id     INT NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    title               VARCHAR(255) NOT NULL,
    excerpt             TEXT,
    status              ENUM('draft','published','scheduled','archived') NOT NULL DEFAULT 'draft',
    published_at        DATETIME DEFAULT NULL,
    author_id           INT DEFAULT NULL,
    featured_image_id   INT DEFAULT NULL,
    data                JSON DEFAULT NULL,
    seo_title           VARCHAR(255) DEFAULT NULL,
    seo_description     VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_type_slug (content_type_id, slug),
    KEY idx_type_status_published (content_type_id, status, published_at),
    CONSTRAINT fk_entry_type FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE,
    CONSTRAINT fk_entry_author FOREIGN KEY (author_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Taxonomías (categorías / etiquetas)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE taxonomies (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    `key`   VARCHAR(50) NOT NULL,
    label   VARCHAR(100) NOT NULL,
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    taxonomy_id   INT NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    name          VARCHAR(255) NOT NULL,
    parent_id     INT DEFAULT NULL,
    UNIQUE KEY uk_taxonomy_slug (taxonomy_id, slug),
    CONSTRAINT fk_term_taxonomy FOREIGN KEY (taxonomy_id) REFERENCES taxonomies (id) ON DELETE CASCADE,
    CONSTRAINT fk_term_parent FOREIGN KEY (parent_id) REFERENCES terms (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_entry_terms (
    entry_id  INT NOT NULL,
    term_id   INT NOT NULL,
    PRIMARY KEY (entry_id, term_id),
    CONSTRAINT fk_cet_entry FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_cet_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Media
-- ─────────────────────────────────────────────────────────────
CREATE TABLE media (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255) NOT NULL,
    path          VARCHAR(500) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size          INT NOT NULL,
    width         INT DEFAULT NULL,
    height        INT DEFAULT NULL,
    alt_text      VARCHAR(255) DEFAULT NULL,
    uploaded_by   INT DEFAULT NULL,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE content_entries
    ADD CONSTRAINT fk_entry_featured_image FOREIGN KEY (featured_image_id) REFERENCES media (id) ON DELETE SET NULL;

CREATE TABLE content_entry_media (
    entry_id     INT NOT NULL,
    media_id     INT NOT NULL,
    role         VARCHAR(50) DEFAULT 'attachment',
    sort_order   INT DEFAULT 0,
    PRIMARY KEY (entry_id, media_id),
    CONSTRAINT fk_cem_entry FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_cem_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Menús de navegación
-- ─────────────────────────────────────────────────────────────
CREATE TABLE menus (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    `key`   VARCHAR(50) NOT NULL,
    label   VARCHAR(100) NOT NULL,
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    menu_id             INT NOT NULL,
    parent_id           INT DEFAULT NULL,
    label               VARCHAR(150) NOT NULL,
    url                 VARCHAR(500) DEFAULT NULL,
    content_entry_id    INT DEFAULT NULL,
    target_type         ENUM('url','content','term') NOT NULL DEFAULT 'url',
    open_new_tab        TINYINT(1) DEFAULT 0,
    sort_order          INT DEFAULT 0,
    KEY idx_menu (menu_id),
    CONSTRAINT fk_mi_menu FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_entry FOREIGN KEY (content_entry_id) REFERENCES content_entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Redirecciones (protección de SEO en el corte de dominio)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE redirects (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    from_path      VARCHAR(500) NOT NULL,
    to_path        VARCHAR(500) NOT NULL,
    status_code    SMALLINT NOT NULL DEFAULT 301,
    created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY from_path (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- Plugins (módulos opcionales en plugins/<key>/ — ver core/Plugins.php)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE plugins (
    `key`              VARCHAR(50) NOT NULL PRIMARY KEY,
    enabled            TINYINT(1) NOT NULL DEFAULT 0,
    installed_version  VARCHAR(20) DEFAULT NULL,
    created_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed: tipos de contenido base
-- =====================================================================
INSERT INTO content_types (`key`, label, label_plural, icon, has_taxonomy, route_prefix, fields_schema) VALUES
('pagina', 'Página', 'Páginas', 'file-text', 0, NULL,
 JSON_ARRAY(
    JSON_OBJECT('name','contenido','type','richtext','label','Contenido','required',false)
 )),
('noticia', 'Noticia', 'Noticias', 'newspaper', 1, 'noticias',
 JSON_ARRAY(
    JSON_OBJECT('name','contenido','type','richtext','label','Contenido','required',true),
    JSON_OBJECT('name','fuente','type','text','label','Fuente','required',false)
 ));

-- Taxonomías base
INSERT INTO taxonomies (`key`, label) VALUES
('categoria', 'Categoría'),
('etiqueta', 'Etiqueta');

-- Páginas iniciales (editables desde el admin)
INSERT INTO content_entries (content_type_id, slug, title, excerpt, status, published_at, data) VALUES
((SELECT id FROM content_types WHERE `key` = 'pagina'), 'inicio', 'Inicio', NULL, 'published', NOW(),
 JSON_OBJECT('contenido', '')),
((SELECT id FROM content_types WHERE `key` = 'pagina'), 'nosotros', 'Nosotros',
 'En AJ Medical Supply distribuimos insumos médicos, equipos y material de protección para clínicas, hospitales, laboratorios y profesionales de la salud, con atención cercana y entregas puntuales.',
 'published', NOW(),
 JSON_OBJECT('contenido', '<h2>¿Quiénes somos?</h2><p>AJ Medical Supply es una empresa dedicada a la distribución de insumos médicos y hospitalarios. Trabajamos con marcas reconocidas para ofrecer productos confiables a clínicas, hospitales, laboratorios, consultorios y farmacias.</p><h2>Misión</h2><p>Abastecer al sector salud con insumos de calidad, a tiempo y con un servicio personalizado.</p><h2>Visión</h2><p>Ser el aliado de confianza de los profesionales de la salud en el suministro de insumos médicos.</p>')),
((SELECT id FROM content_types WHERE `key` = 'pagina'), 'contacto', 'Contacto', NULL, 'published', NOW(),
 JSON_OBJECT('contenido', '<p>¿Necesitas una cotización o información sobre un producto? Escríbenos y te responderemos a la brevedad.</p>')),
((SELECT id FROM content_types WHERE `key` = 'pagina'), 'politicas-de-privacidad', 'Políticas de privacidad', NULL, 'published', NOW(),
 JSON_OBJECT('contenido', '<p>Los datos que nos compartes a través de los formularios de este sitio se usan únicamente para responder tu consulta o cotización. No los compartimos con terceros.</p>'));

-- Menú principal
INSERT INTO menus (`key`, label) VALUES ('principal', 'Menú Principal');
INSERT INTO menu_items (menu_id, label, url, target_type, sort_order) VALUES
((SELECT id FROM menus WHERE `key` = 'principal'), 'Inicio',    '/',          'url', 1),
((SELECT id FROM menus WHERE `key` = 'principal'), 'Catálogo',  '/catalogo',  'url', 2),
((SELECT id FROM menus WHERE `key` = 'principal'), 'Nosotros',  '/nosotros',  'url', 3),
((SELECT id FROM menus WHERE `key` = 'principal'), 'Noticias',  '/noticias',  'url', 4),
((SELECT id FROM menus WHERE `key` = 'principal'), 'Contacto',  '/contacto',  'url', 5);

-- Configuración inicial
INSERT INTO settings (`key`, value, label, category, type) VALUES
('SITE_NAME', 'AJ Medical Supply', 'Nombre del sitio', 'general', 'text'),
('SITE_TAGLINE', 'Insumos médicos y hospitalarios', 'Eslogan', 'general', 'text'),
('MAINTENANCE_MODE', 'true', 'Sitio en mantenimiento (los administradores con sesión iniciada sí ven el sitio)', 'general', 'boolean'),
('SMTP_HOST', 'smtp.gmail.com', 'Servidor SMTP', 'smtp', 'text'),
('SMTP_PORT', '465', 'Puerto SMTP', 'smtp', 'number'),
('SMTP_SECURITY', 'ssl', 'Seguridad (TLS/SSL)', 'smtp', 'text'),
('SMTP_ENABLED', 'false', 'SMTP Habilitado', 'smtp', 'boolean'),
('EMAIL_FROM', 'noreply@ajmedicalsupply.com', 'Correo Remitente', 'email', 'email'),
('EMAIL_FROM_NAME', 'AJ Medical Supply', 'Nombre Remitente', 'email', 'text'),
('EMAIL_ADMIN', 'admin@ajmedicalsupply.com', 'Correo Administrador (notificaciones y cotizaciones)', 'email', 'email'),
('CONTACT_PHONE', '', 'Teléfono', 'contacto', 'text'),
('CONTACT_WHATSAPP', '', 'WhatsApp (solo números, con código de país, ej. 50760000000)', 'contacto', 'text'),
('CONTACT_EMAIL', '', 'Correo de contacto', 'contacto', 'email'),
('CONTACT_ADDRESS', '', 'Dirección', 'contacto', 'textarea'),
('CONTACT_HOURS', 'Lunes a viernes, 8:00 a.m. - 5:00 p.m.', 'Horario de atención', 'contacto', 'text'),
('SOCIAL_FACEBOOK', '', 'Facebook (URL)', 'redes', 'text'),
('SOCIAL_INSTAGRAM', '', 'Instagram (URL)', 'redes', 'text'),
('SOCIAL_LINKEDIN', '', 'LinkedIn (URL)', 'redes', 'text'),
('HERO_EYEBROW', 'Distribuidores de insumos médicos', 'Hero: texto superior', 'hero', 'text'),
('HERO_TITLE', 'Dispositivos médicos confiables', 'Hero: título', 'hero', 'text'),
('HERO_SUBTITLE', 'Descartables, curaciones, protección personal, diagnóstico y equipos para clínicas, hospitales y profesionales de la salud.', 'Hero: subtítulo', 'hero', 'textarea'),
('HERO_STAT_1_VALUE', '+500', 'Estadística 1: valor', 'hero', 'text'),
('HERO_STAT_1_LABEL', 'Productos en catálogo', 'Estadística 1: descripción', 'hero', 'text'),
('HERO_STAT_2_VALUE', '24 h', 'Estadística 2: valor', 'hero', 'text'),
('HERO_STAT_2_LABEL', 'Respuesta a cotizaciones', 'Estadística 2: descripción', 'hero', 'text');

-- Nota: SMTP_USER y SMTP_PASS (credenciales) NO van en esta tabla — siempre en .env.

-- =====================================================================
-- Usuario admin inicial — CAMBIAR la contraseña tras el primer login.
-- Password temporal: "CambiarAhora123!" (bcrypt cost 12)
-- =====================================================================
INSERT INTO admin_users (username, email, password_hash, nombre_completo, rol) VALUES
('admin', 'admin@ajmedicalsupply.com', '$2y$12$m2kjY2K.Bo1UglxUNORJ4u6dKj2Ipot7RWKHJQfuSSA/ZjXblKVGm', 'Administrador AJ Medical', 'super_admin');
