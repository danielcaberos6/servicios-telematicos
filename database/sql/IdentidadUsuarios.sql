alter table usuario add column version_identidad bigint not null default 1;
alter table usuario add column identidad_verificada boolean not null default false;

create table verificacion_identidad (
    id_verificacion bigint generated always as identity primary key,
    id_usuario bigint not null references usuario(id_usuario),
    nombres text not null,
    apellidos text not null,
    id_imagen_perfil bigint,
    version_identidad bigint not null,
    ruta_documento text not null,
    tamano integer not null check (tamano between 1 and 5242880),
    estado text not null default 'En revisión' check (estado in ('En revisión','Aprobada','Rechazada','Invalidada')),
    vigente boolean not null default true,
    id_revisor bigint references usuario(id_usuario),
    motivo text,
    fecha_envio timestamptz not null default clock_timestamp(),
    fecha_decision timestamptz,
    fecha_invalidacion timestamptz,
    motivo_invalidacion text,
    check (estado <> 'Rechazada' or length(trim(motivo)) between 1 and 1000)
);
create unique index identidad_pendiente_unica on verificacion_identidad(id_usuario) where estado='En revisión';
create index identidad_bandeja on verificacion_identidad(estado,fecha_envio,id_verificacion);
alter table notificacion add column id_verificacion bigint references verificacion_identidad(id_verificacion);

create or replace function identidad_actualizar_version() returns trigger language plpgsql as $$
begin
    if row(new.nombre,new.nombres,new.apellidos) is distinct from row(old.nombre,old.nombres,old.apellidos) then
        new.version_identidad := old.version_identidad + 1;
        new.identidad_verificada := false;
    end if;
    return new;
end;
$$;
create trigger usuario_identidad_version before update on usuario for each row execute function identidad_actualizar_version();

create or replace function identidad_invalidar_solicitudes() returns trigger language plpgsql as $$
declare cantidad integer;
begin
    if new.version_identidad is distinct from old.version_identidad then
        update verificacion_identidad set vigente=false,
            estado=case when estado='En revisión' then 'Invalidada' else estado end,
            fecha_invalidacion=clock_timestamp(),motivo_invalidacion='Cambiaste los datos o la fotografía usados en la revisión.'
            where id_usuario=new.id_usuario and vigente and estado in ('En revisión','Aprobada');
        get diagnostics cantidad = row_count;
        if cantidad > 0 then
            insert into notificacion(id_usuario,titulo,contenido) values(new.id_usuario,'Solicita una nueva verificación',
                'Cambiaste tu nombre, apellidos o fotografía. Tu identidad figura como No verificado; envía otra solicitud desde tu perfil.');
        end if;
    end if;
    return new;
end;
$$;
create trigger usuario_invalidar_identidad after update on usuario for each row execute function identidad_invalidar_solicitudes();

create or replace function identidad_cambio_foto() returns trigger language plpgsql as $$
declare titular bigint;
begin
    if tg_op='DELETE' then titular:=old.id_usuario; else titular:=new.id_usuario; end if;
    if titular is not null then
        update usuario set version_identidad=version_identidad+1,identidad_verificada=false where id_usuario=titular;
    end if;
    if tg_op='DELETE' then return old; end if;
    return new;
end;
$$;
create trigger imagen_invalidar_identidad after insert or update or delete on imagen for each row execute function identidad_cambio_foto();

create or replace function solicitar_verificacion(p_actor bigint,p_version bigint,p_ruta text,p_tamano integer)
returns bigint language plpgsql as $$
declare cuenta usuario; solicitud bigint;
begin
    select * into cuenta from usuario where id_usuario=p_actor for update;
    if not found or not cuenta.activo then raise exception 'Tu cuenta no puede enviar solicitudes.' using errcode='42501'; end if;
    if cuenta.nombres is null or cuenta.apellidos is null then raise exception 'Completa y guarda tus nombres y apellidos en el perfil.'; end if;
    if p_version is null or cuenta.version_identidad<>p_version then raise exception 'Tu perfil cambió. Actualiza la página antes de solicitar la verificación.'; end if;
    if cuenta.identidad_verificada then raise exception 'Tu identidad ya está verificada.'; end if;
    if exists(select 1 from verificacion_identidad where id_usuario=p_actor and estado='En revisión') then
        raise exception 'Ya tienes una solicitud en revisión.';
    end if;
    if p_tamano is null or p_tamano not between 1 and 5242880 or p_ruta is null
        or p_ruta !~ ('^' || p_actor || '/[A-Za-z0-9]+\.(jpg|jpeg|png)$') then raise exception 'El documento no es válido.'; end if;
    insert into verificacion_identidad(id_usuario,nombres,apellidos,id_imagen_perfil,version_identidad,ruta_documento,tamano)
        values(p_actor,cuenta.nombres,cuenta.apellidos,(select id_imagen from imagen where id_usuario=p_actor),cuenta.version_identidad,p_ruta,p_tamano)
        returning id_verificacion into solicitud;
    insert into notificacion(id_usuario,titulo,contenido,id_verificacion) values(p_actor,'Identidad en revisión','Recibimos tu carnet. Un administrador revisará tus datos.',solicitud);
    insert into notificacion(id_usuario,titulo,contenido,id_verificacion)
        select distinct u.id_usuario,'Nueva verificación de identidad','Hay una solicitud de identidad pendiente de revisión.',solicitud
        from usuario u join usuario_rol ur on ur.id_usuario=u.id_usuario join rol r on r.id_rol=ur.id_rol
        where u.activo and r.nombre_rol='Administrador' and u.id_usuario<>p_actor;
    return solicitud;
end;
$$;
