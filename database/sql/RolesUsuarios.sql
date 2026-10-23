create table if not exists decision_usuario (
    id_decision bigint generated always as identity primary key,
    id_actor bigint not null references usuario(id_usuario),
    id_usuario bigint not null references usuario(id_usuario),
    operacion text not null check (operacion in ('estado', 'administrador')),
    valor_anterior boolean not null,
    valor_nuevo boolean not null,
    fecha timestamptz not null default clock_timestamp()
);

-- exigir_administrador vive en Funciones.sql (sección de reportes administrativos).

create or replace function gestionar_cuenta(p_actor bigint, p_usuario bigint, p_operacion text, p_valor boolean)
returns void language plpgsql as $$
declare cuenta usuario; es_admin boolean; anterior boolean; rol_admin bigint;
begin
    -- Todas las decisiones administrativas comparten el bloqueo, incluso entre procesos.
    perform pg_advisory_xact_lock(8009, 1);
    perform exigir_administrador(p_actor);
    if p_valor is null or p_operacion is null or p_operacion not in ('estado', 'administrador') then
        raise exception 'La operación no es válida.';
    end if;
    select * into cuenta from usuario where id_usuario=p_usuario for update;
    if not found then raise exception 'Cuenta no encontrada.' using errcode='P0002'; end if;
    select id_rol into rol_admin from rol where nombre_rol='Administrador';
    es_admin := exists(select 1 from usuario_rol where id_usuario=p_usuario and id_rol=rol_admin);
    anterior := case when p_operacion='estado' then cuenta.activo else es_admin end;
    if anterior = p_valor then return; end if;
    if p_operacion='estado' and not p_valor and p_usuario=p_actor then
        raise exception 'No puedes desactivar tu propia cuenta administrativa.';
    end if;
    if p_operacion='administrador' and not p_valor and p_usuario=p_actor then
        raise exception 'No puedes retirarte tu propio rol de administrador.';
    end if;
    if es_admin and cuenta.activo and not p_valor and not exists (
        select 1 from usuario u join usuario_rol ur on ur.id_usuario=u.id_usuario
        where u.activo and ur.id_rol=rol_admin and u.id_usuario<>p_usuario
    ) then
        raise exception 'Debe quedar al menos un administrador activo.';
    end if;
    if p_operacion='estado' then
        update usuario set activo=p_valor where id_usuario=p_usuario;
    elsif p_valor then
        insert into usuario_rol(id_usuario,id_rol) values(p_usuario,rol_admin) on conflict do nothing;
    else
        delete from usuario_rol where id_usuario=p_usuario and id_rol=rol_admin;
    end if;
    insert into decision_usuario(id_actor,id_usuario,operacion,valor_anterior,valor_nuevo)
        values(p_actor,p_usuario,p_operacion,anterior,p_valor);
end;
$$;

create or replace function historial_cuenta(p_actor bigint,p_usuario bigint)
returns table(operacion text, valor_anterior boolean, valor_nuevo boolean, fecha timestamptz, revisor text)
language plpgsql stable as $$
begin
    perform exigir_administrador(p_actor);
    return query select d.operacion,d.valor_anterior,d.valor_nuevo,d.fecha,u.nombre
        from decision_usuario d join usuario u on u.id_usuario=d.id_actor
        where d.id_usuario=p_usuario order by d.fecha desc,d.id_decision desc limit 100;
end;
$$;
