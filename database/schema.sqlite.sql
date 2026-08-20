-- Esquema para SQLite (uso local / testing). El equivalente para
-- Hostinger está en schema.mysql.sql — mismas tablas y columnas.

CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  rol TEXT NOT NULL CHECK (rol IN ('admin','arquitecto','cliente')),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS obras (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  ubicacion TEXT,
  cliente_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  arquitecto_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  estado TEXT NOT NULL DEFAULT 'en_curso',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS etapas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  nombre TEXT NOT NULL,
  orden INTEGER NOT NULL DEFAULT 0,
  fecha TEXT,
  descripcion TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS fotos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  etapa_id INTEGER REFERENCES etapas(id) ON DELETE SET NULL,
  archivo TEXT NOT NULL,
  descripcion TEXT,
  subido_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
