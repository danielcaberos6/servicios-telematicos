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
    constraint notificacion_pkey primary key (id_notificacion),
    constraint notificacion_id_usuario_fkey foreign key (id_usuario) 
        references usuario(id_usuario) on delete cascade
);

-- SUBASTAYA: autenticación, índices y reglas de negocio (PostgreSQL).
-- CRUD con Eloquent y transacciones; triggers para integridad en la BD.
-- Ejecutar en una base vacía, o usar `php artisan migrate`.
alter table usuario add column remember_token varchar(100);
alter table usuario alter column contrasena set not null;
create unique index usuario_correo_normalizado on usuario (lower(correo));
alter table puja add column fecha_puja timestamptz not null default now();
alter table subasta alter column estado_subasta set not null;
alter table subasta add constraint subasta_estado_valido check (estado_subasta in ('Activa', 'Cancelada', 'Finalizada'));
alter table subasta add constraint articulo_estado_valido check (estado_articulo in ('Nuevo', 'Como nuevo', 'Usado'));
create index subasta_catalogo_idx on subasta (estado_subasta, fecha_fin, fecha_inicio);
create index subasta_categoria_idx on subasta (id_categoria);
create index subasta_usuario_idx on subasta (id_usuario);
create index subasta_monto_idx on subasta (monto_inicial);
create index puja_subasta_monto_idx on puja (id_subasta, monto desc);
create index imagen_subasta_idx on imagen (id_subasta);
create unique index imagen_perfil_unica on imagen (id_usuario) where id_usuario is not null;
create index notificacion_usuario_idx on notificacion (id_usuario, leido, fecha_notificacion desc);

create or replace function validar_puja() returns trigger language plpgsql as $$
declare articulo subasta%rowtype; mayor numeric(12,2);
begin
    select * into articulo from subasta where id_subasta = new.id_subasta for update;
    if not found then raise exception 'La subasta no existe.'; end if;
    -- Evaluar la hora real después de obtener el bloqueo, incluso si hubo espera.
    if articulo.estado_subasta <> 'Activa' or articulo.fecha_inicio > clock_timestamp() or articulo.fecha_fin <= clock_timestamp() then
        raise exception 'La subasta no está activa.';
    end if;
    if articulo.id_usuario = new.id_usuario then raise exception 'No puedes pujar en tu propia subasta.'; end if;
    select max(monto) into mayor from puja where id_subasta = new.id_subasta;
    if new.monto < articulo.monto_inicial or (mayor is not null and new.monto <= mayor) then
        raise exception 'La oferta debe superar la puja actual y alcanzar el monto inicial.';
    end if;
    return new;
end;
$$;
create trigger puja_validacion before insert on puja for each row execute function validar_puja();

create or replace function proteger_subasta() returns trigger language plpgsql as $$
begin
    if exists (select 1 from puja where id_subasta = old.id_subasta) then
        if TG_OP = 'DELETE' then raise exception 'Una subasta con pujas no puede eliminarse.'; end if;
        if row(new.titulo,new.descripcion,new.ubicacion,new.estado_articulo,new.monto_inicial,new.fecha_inicio,new.fecha_fin,new.id_usuario,new.id_categoria)
           is distinct from row(old.titulo,old.descripcion,old.ubicacion,old.estado_articulo,old.monto_inicial,old.fecha_inicio,old.fecha_fin,old.id_usuario,old.id_categoria)
           or new.estado_subasta = 'Cancelada' then
            raise exception 'Una subasta con pujas no puede modificarse.';
        end if;
    end if;
    if TG_OP = 'DELETE' then return old; end if;
    return new;
end;
$$;
create trigger subasta_proteccion before update or delete on subasta for each row execute function proteger_subasta();

create or replace function notificar_publicacion() returns trigger language plpgsql as $$
begin
    insert into notificacion (id_usuario,titulo,contenido)
    values (new.id_usuario, 'Subasta publicada', 'Tu subasta «' || new.titulo || '» fue publicada correctamente.');
    return new;
end;
$$;
create trigger subasta_publicada after insert on subasta for each row execute function notificar_publicacion();

create or replace function notificar_puja() returns trigger language plpgsql as $$
declare anterior bigint; propietario bigint; nombre text;
begin
    select id_usuario,titulo into propietario,nombre from subasta where id_subasta = new.id_subasta;
    select id_usuario into anterior from puja where id_subasta = new.id_subasta and id_puja <> new.id_puja order by monto desc limit 1;
    insert into notificacion (id_usuario,titulo,contenido) values
        (propietario,'Nueva oferta','Recibiste una oferta de Bs ' || new.monto || ' en «' || nombre || '».');
    if anterior is not null and anterior <> new.id_usuario then
        insert into notificacion (id_usuario,titulo,contenido) values
            (anterior,'Superaron tu oferta','Hay una oferta mayor en «' || nombre || '».');
    end if;
    return new;
end;
$$;
create trigger puja_notificacion after insert on puja for each row execute function notificar_puja();

create or replace function finalizar_subastas() returns integer language plpgsql as $$
declare articulo record; ganador bigint; total integer := 0;
begin
    for articulo in select * from subasta where estado_subasta = 'Activa' and fecha_fin <= clock_timestamp() for update skip locked loop
        update subasta set estado_subasta = 'Finalizada' where id_subasta = articulo.id_subasta;
        select id_usuario into ganador from puja where id_subasta = articulo.id_subasta order by monto desc limit 1;
        insert into notificacion (id_usuario,titulo,contenido) values
            (articulo.id_usuario,'Subasta finalizada','Terminó «' || articulo.titulo || '»' || case when ganador is null then ' sin ofertas.' else ' con una oferta ganadora. Consulta el detalle para coordinar la entrega.' end);
        if ganador is not null then
            insert into notificacion (id_usuario,titulo,contenido) values
                (ganador,'¡Ganaste una subasta!','Ganaste «' || articulo.titulo || '». Consulta el detalle para coordinar la entrega.');
        end if;
        total := total + 1;
    end loop;
    return total;
end;
$$;


