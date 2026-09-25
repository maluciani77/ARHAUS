-- Esquema para SQLite (uso local / testing). El equivalente para
-- Hostinger está en schema.mysql.sql — mismas tablas y columnas.

CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  rol TEXT NOT NULL CHECK (rol IN ('admin','arquitecto','cliente','director')),
  foto TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS obras (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  ubicacion TEXT,
  cliente_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  arquitecto_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  director_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
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

CREATE TABLE IF NOT EXISTS presupuestos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  concepto TEXT NOT NULL,
  monto NUMERIC NOT NULL,
  moneda TEXT NOT NULL DEFAULT 'ARS',
  fecha TEXT NOT NULL,
  detalle TEXT,
  monto_oculto INTEGER NOT NULL DEFAULT 0,
  cargado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS eventos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  titulo TEXT NOT NULL,
  fecha TEXT NOT NULL,
  hora TEXT,
  detalle TEXT,
  cargado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS novedades (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  texto TEXT,
  archivo TEXT,
  autor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS documentos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  categoria TEXT NOT NULL,
  titulo TEXT NOT NULL,
  archivo TEXT NOT NULL,
  nombre_original TEXT NOT NULL,
  tamano INTEGER NOT NULL DEFAULT 0,
  subido_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS mensajes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  autor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  texto TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS asistente_mensajes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  rol TEXT NOT NULL,
  texto TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS obra_info (
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  campo TEXT NOT NULL,
  valor TEXT NOT NULL,
  PRIMARY KEY (obra_id, campo)
);

CREATE TABLE IF NOT EXISTS propietarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  nombre TEXT NOT NULL,
  apellido TEXT NOT NULL,
  dni TEXT NOT NULL,
  cuit TEXT,
  fecha_nacimiento TEXT,
  nacionalidad TEXT,
  estado_civil TEXT,
  domicilio TEXT,
  telefono TEXT,
  email TEXT,
  actualizado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  updated_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pagos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  fecha TEXT NOT NULL,
  concepto TEXT NOT NULL,
  monto NUMERIC NOT NULL,
  moneda TEXT NOT NULL DEFAULT 'ARS',
  detalle TEXT,
  comprobante TEXT,
  comprobante_nombre TEXT,
  comprobante_subido_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  cargado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS contactos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL,
  nombre TEXT NOT NULL,
  empresa TEXT,
  rubro TEXT,
  telefono TEXT,
  email TEXT,
  notas TEXT,
  cargado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS contacto_archivos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contacto_id INTEGER NOT NULL REFERENCES contactos(id) ON DELETE CASCADE,
  obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  titulo TEXT NOT NULL,
  archivo TEXT NOT NULL,
  nombre_original TEXT NOT NULL,
  tamano INTEGER NOT NULL DEFAULT 0,
  subido_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS recordatorios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  selector TEXT NOT NULL UNIQUE,
  validador_hash TEXT NOT NULL,
  expira TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
