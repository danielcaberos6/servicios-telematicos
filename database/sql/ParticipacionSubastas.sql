-- HU18: consultas privadas, favoritos y avisos internos de cierre.
create or replace function exigir_usuario_participante(p_actor bigint) returns void language plpgsql stable as $$
begin
    if p_actor is null or not exists(select 1 from usuario where id_usuario=p_actor and activo)
       or exists(select 1 from usuario_rol ur join rol r on r.id_rol=ur.id_rol where ur.id_usuario=p_actor and r.nombre_rol='Administrador') then
        raise exception 'Esta función está disponible solo para usuarios activos sin rol administrador.' using errcode='42501';
    end if;
end;
$$;

create or replace function listar_participaciones(p_actor bigint)
returns table(id_subasta bigint,oferta_propia numeric,oferta_actual numeric,resultado text) language plpgsql stable as $$
begin
    perform exigir_usuario_participante(p_actor);
    return query
    select s.id_subasta,m.mejor,l.monto,
        case when s.estado_subasta='Cancelada' then 'canceladas'
             when s.estado_subasta='Finalizada' or s.fecha_fin<=statement_timestamp() then
                case when l.id_usuario=p_actor then 'ganadas' else 'no_ganadas' end
             when l.id_usuario=p_actor then 'ganando' else 'superadas' end
    from (select p.id_subasta,max(p.monto) as mejor from puja p where p.id_usuario=p_actor group by p.id_subasta) m
    join subasta s on s.id_subasta=m.id_subasta
    join lateral (select p.monto,p.id_usuario from puja p where p.id_subasta=s.id_subasta order by p.monto desc,p.id_puja asc limit 1) l on true
    where s.estado_revision='Aceptada';
end;
$$;

create or replace function guardar_favorito(p_actor bigint,p_subasta bigint) returns void language plpgsql as $$
begin
    perform exigir_usuario_participante(p_actor);
    perform 1 from subasta s join usuario u on u.id_usuario=s.id_usuario
        where s.id_subasta=p_subasta and s.estado_revision='Aceptada' and u.activo for share of s;
    if not found then raise exception 'La publicación no está disponible.' using errcode='P0002'; end if;
    insert into favorito_subasta(id_usuario,id_subasta) values(p_actor,p_subasta) on conflict do nothing;
end;
$$;

create or replace function retirar_favorito(p_actor bigint,p_subasta bigint) returns void language plpgsql as $$
begin
    perform exigir_usuario_participante(p_actor);
    delete from favorito_subasta where id_usuario=p_actor and id_subasta=p_subasta;
end;
$$;

create or replace function listar_favoritos(p_actor bigint) returns setof subasta language plpgsql stable as $$
begin
    perform exigir_usuario_participante(p_actor);
    return query select s.* from favorito_subasta f join subasta s on s.id_subasta=f.id_subasta
        join usuario u on u.id_usuario=s.id_usuario
        where f.id_usuario=p_actor and s.estado_revision='Aceptada' and u.activo;
end;
$$;

create or replace function recordar_cierres_favoritos() returns integer language plpgsql as $$
declare instante timestamptz := clock_timestamp(); total integer;
begin
    with candidatas as materialized (
        select f.id_usuario,s.id_subasta,s.titulo from favorito_subasta f
        join subasta s on s.id_subasta=f.id_subasta
        join usuario u on u.id_usuario=f.id_usuario
        join usuario vendedor on vendedor.id_usuario=s.id_usuario
        where s.estado_revision='Aceptada' and s.estado_subasta='Activa' and s.fecha_inicio<=instante
          and s.fecha_fin>instante and s.fecha_fin<=instante+interval '30 minutes'
          and f.fecha_guardado<=s.fecha_fin-interval '30 minutes' and u.activo and vendedor.activo
          and not exists(select 1 from usuario_rol ur join rol r on r.id_rol=ur.id_rol where ur.id_usuario=u.id_usuario and r.nombre_rol='Administrador')
        for share of s
    ), nuevas as (
        insert into recordatorio_favorito(id_usuario,id_subasta)
        select id_usuario,id_subasta from candidatas on conflict do nothing returning id_usuario,id_subasta
    )
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
    select n.id_usuario,n.id_subasta,'Tu favorito está por finalizar','La subasta «'||c.titulo||'» cierra en 30 minutos o menos. Consulta el detalle para participar.'
    from nuevas n join candidatas c using(id_usuario,id_subasta);
    get diagnostics total = row_count;
    return total;
end;
$$;
