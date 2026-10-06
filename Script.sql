-- =========================================================================
-- SCRIPT DE CREACIÓN DE TABLAS
-- =========================================================================

-- =========================================================================
-- 1. TABLA CATEGORIA (NUEVA)
-- =========================================================================
create table categoria (
    id_categoria bigint generated always as identity,
    nombre_categoria text not null,
    descripcion text,
    constraint categoria_pkey primary key (id_categoria),
    constraint categoria_nombre_key unique (nombre_categoria)
);

comment on table categoria is 'CLASIFICACIÓN DE LOS ARTÍCULOS EN SUBASTA (EJ: ELECTRÓNICA, VEHÍCULOS, HOGAR)';

-- =========================================================================
-- 2. TABLA ROL
-- =========================================================================
create table rol (
    id_rol bigint generated always as identity,
    nombre_rol text not null,
    constraint rol_pkey primary key (id_rol),
    constraint rol_nombre_rol_key unique (nombre_rol)
);

comment on table rol is 'ALMACENA LOS ROLES DEL SISTEMA (EJ: ADMINISTRADOR, USUARIO, MODERADOR)';

-- =========================================================================
-- 3. TABLA USUARIO
-- =========================================================================
create table usuario (
    id_usuario bigint generated always as identity,
    nombre text not null,
    biografia text,
    ciudad text,
    telefono text,
    correo text not null,
    contrasena text,
    fecha_registro timestamptz default now() not null,
    constraint usuario_pkey primary key (id_usuario),
    constraint usuario_correo_key unique (correo)
);

comment on table usuario is 'PERFILES DE USUARIOS REGISTRADOS EN LA PLATAFORMA';

-- =========================================================================
-- 4. TABLA USUARIO_ROL
-- =========================================================================
create table usuario_rol (
    id_usuario bigint not null,
    id_rol bigint not null,
    constraint usuario_rol_pkey primary key (id_usuario, id_rol), 
    constraint usuario_rol_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete cascade,
    constraint usuario_rol_id_rol_fkey foreign key (id_rol) 
        references rol(id_rol) on delete restrict
);

-- =========================================================================
-- 5. TABLA SUBASTA
-- =========================================================================
create table subasta (
    id_subasta bigint generated always as identity,
    titulo text not null,
    descripcion text,
    ubicacion text,
    latitud numeric(9,6),
    longitud numeric(10,6),
    estado_articulo text,
    estado_subasta text default 'Activa',
    monto_inicial numeric(12, 2) not null,
    fecha_inicio timestamptz not null,
    fecha_fin timestamptz not null,
    id_usuario bigint not null,
    id_categoria bigint, -- NUEVA RELACIÓN
    constraint subasta_pkey primary key (id_subasta),
    constraint subasta_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete cascade,
    constraint subasta_id_categoria_fkey foreign key (id_categoria) 
        references categoria(id_categoria) on delete set null, -- SI SE BORRA LA CATEGORÍA, LA SUBASTA QUEDA SIN CATEGORÍA PERO NO SE BORRA
    constraint check_fechas_validas check (fecha_fin > fecha_inicio),
    constraint check_monto_inicial_positivo check (monto_inicial > 0)
    ,constraint subasta_coordenadas_validas check (
        (latitud is null and longitud is null) or
        (latitud is not null and longitud is not null and latitud between -90 and 90 and longitud between -180 and 180)
    )
);

comment on table subasta is 'ARTÍCULOS PUBLICADOS PARA SUBASTA POR LOS USUARIOS';

-- =========================================================================
-- 6. TABLA PUJA
-- =========================================================================
create table puja (
    id_puja bigint generated always as identity,
    monto numeric(12, 2) not null,
    id_usuario bigint not null,
    id_subasta bigint not null,
    constraint puja_pkey primary key (id_puja),
    constraint puja_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete restrict,
    constraint puja_id_subasta_fkey foreign key (id_subasta) 
        references subasta(id_subasta) on delete cascade,
    constraint check_monto_positivo check (monto > 0)
);

comment on table puja is 'OFERTAS REALIZADAS POR LOS USUARIOS EN LAS SUBASTAS';

-- =========================================================================
-- 7. TABLA IMAGEN
-- =========================================================================
create table imagen (
    id_imagen bigint generated always as identity,
    ruta text not null,
    tamano bigint,
    id_usuario bigint,
    id_subasta bigint,
    constraint imagen_pkey primary key (id_imagen),
    constraint imagen_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete cascade,
    constraint imagen_id_subasta_fkey foreign key (id_subasta) 
        references subasta(id_subasta) on delete cascade,
    constraint check_imagen_asociacion check (
        (id_usuario is not null and id_subasta is null) or 
        (id_usuario is null and id_subasta is not null)
    )
);

-- =========================================================================
-- 8. TABLA RESENAS
-- =========================================================================
create table resenas (
    id_resena bigint generated always as identity,
    id_usuario_resenado bigint not null,
    id_usuario_resenador bigint not null,
    calificacion numeric(2, 1) not null,
    comentario text,
    fecha_creacion timestamptz default now() not null,
    constraint resenas_pkey primary key (id_resena),
    constraint resenas_id_usuario_resenado_fkey foreign key (id_usuario_resenado) 
        references usuario(id_usuario) on delete cascade,
    constraint resenas_id_usuario_resenador_fkey foreign key (id_usuario_resenador) 
        references usuario(id_usuario) on delete cascade,
    constraint check_calificacion_rango check (calificacion >= 1 and calificacion <= 5),
    constraint check_no_autoresena check (id_usuario_resenado != id_usuario_resenador)
);

-- =========================================================================
-- 9. TABLA NOTIFICACION
-- =========================================================================
create table notificacion (
    id_notificacion bigint generated always as identity,
    titulo text not null,
    contenido text,
    leido boolean default false not null,
    fecha_notificacion timestamptz default now() not null,
    id_usuario bigint not null,
    id_subasta bigint references subasta(id_subasta) on delete set null,
    constraint notificacion_pkey primary key (id_notificacion),
    constraint notificacion_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete cascade
);

-- SUBASTAYA: autenticación, índices y reglas de negocio (PostgreSQL).
-- Tablas y semillas. Instalar luego Funciones.sql para CRUD y triggers.
-- Ejecutar en una base vacía, o usar `php artisan migrate`.
alter table usuario add column remember_token varchar(100);
alter table usuario alter column contrasena set not null;
create unique index usuario_correo_normalizado on usuario (lower(correo));
alter table puja add column fecha_puja timestamptz not null default now();
alter table subasta alter column estado_subasta set not null;
alter table subasta add constraint subasta_estado_valido check (estado_subasta in ('Activa', 'Cancelada', 'Finalizada'));
alter table subasta add constraint articulo_estado_valido check (estado_articulo in ('Nuevo', 'Usado'));
create index subasta_catalogo_idx on subasta (estado_subasta, fecha_fin, fecha_inicio);
create index subasta_categoria_idx on subasta (id_categoria);
create index subasta_usuario_idx on subasta (id_usuario);
create index subasta_monto_idx on subasta (monto_inicial);
create index puja_subasta_monto_idx on puja (id_subasta, monto desc);
create index imagen_subasta_idx on imagen (id_subasta);
create unique index imagen_perfil_unica on imagen (id_usuario) where id_usuario is not null;
create index notificacion_usuario_idx on notificacion (id_usuario, leido, fecha_notificacion desc);

-- BEGIN SEMILLAS PREDETERMINADAS
insert into categoria (nombre_categoria) values
    ('Vehículos'),
    ('Antigüedades y coleccionables'),
    ('Electrónica e informática'),
    ('Muebles'),
    ('Juguetes y juegos'),
    ('Ropa, calzado y accesorios'),
    ('Instrumentos musicales'),
    ('Deporte'),
    ('Videojuegos'),
    ('Libros, películas y música'),
    ('Varios')
on conflict (nombre_categoria) do nothing;
insert into rol (nombre_rol) values ('Usuario'), ('Administrador'), ('Moderador')
on conflict (nombre_rol) do nothing;
-- END SEMILLAS PREDETERMINADAS
