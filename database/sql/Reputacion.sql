-- La publicación se calcula al consultar: no depende de ejecutar un cron al vencer el plazo.
create or replace view resenas_publicadas as
select r.id_resena,r.id_usuario_resenado,r.id_usuario_resenador,r.calificacion,
       case when r.comentario_oculto then null else r.comentario end as comentario,
       r.fecha_creacion,r.id_operacion,r.rol_resenado,r.comentario_oculto
from resenas r left join operacion_subasta o on o.id_operacion=r.id_operacion
where r.id_operacion is null or (o.estado='Completada' and (
    clock_timestamp()>=(o.fecha_resolucion at time zone 'America/La_Paz'+interval '14 days') at time zone 'America/La_Paz'
    or (select count(*) from resenas otra where otra.id_operacion=r.id_operacion)=2));

create or replace function valorar_operacion(p_actor bigint,p_operacion bigint,p_estrellas numeric,p_comentario text default '')
returns bigint language plpgsql as $$
declare op operacion_subasta; anterior resenas; resultado bigint;
begin
    perform exigir_parte_operacion(p_actor,p_operacion);
    select * into op from operacion_subasta where id_operacion=p_operacion for update;
    if op.estado<>'Completada' or op.fecha_resolucion is null then raise exception 'Solo puedes valorar una operación completada.'; end if;
    if p_estrellas is null or p_estrellas<>trunc(p_estrellas) or p_estrellas not between 1 and 5
       or length(coalesce(p_comentario,''))>1000 then raise exception 'Selecciona de 1 a 5 estrellas enteras y un comentario de hasta 1.000 caracteres.'; end if;
    select * into anterior from resenas where id_operacion=p_operacion and id_usuario_resenador=p_actor;
    if found then
        if anterior.calificacion<>p_estrellas or coalesce(anterior.comentario,'')<>trim(coalesce(p_comentario,'')) then raise exception 'Ya enviaste tu valoración; no se puede modificar.'; end if;
        return anterior.id_resena;
    end if;
    if clock_timestamp()>=(op.fecha_resolucion at time zone 'America/La_Paz'+interval '14 days') at time zone 'America/La_Paz' then
        raise exception 'El plazo de 14 días para valorar esta operación terminó.';
    end if;
    insert into resenas(id_usuario_resenado,id_usuario_resenador,calificacion,comentario,id_operacion,rol_resenado)
        values(case when p_actor=op.id_comprador then op.id_vendedor else op.id_comprador end,p_actor,p_estrellas,
            nullif(trim(coalesce(p_comentario,'')),''),p_operacion,case when p_actor=op.id_comprador then 'Vendedor' else 'Comprador' end)
        returning id_resena into resultado;
    insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(p_operacion,p_actor,'Valoración enviada','Se registró una valoración; su contenido se publica cuando ambos valoren o termine el plazo de 14 días.');
    return resultado;
end;
$$;

create or replace function proteger_resena_verificada() returns trigger language plpgsql as $$
declare op operacion_subasta;
begin
    if TG_OP='DELETE' then raise exception 'Las reseñas y su historial deben conservarse.'; end if;
    if TG_OP='UPDATE' then
        if (new.id_usuario_resenado,new.id_usuario_resenador,new.calificacion,new.comentario,new.fecha_creacion,new.id_operacion,new.rol_resenado)
           is distinct from (old.id_usuario_resenado,old.id_usuario_resenador,old.calificacion,old.comentario,old.fecha_creacion,old.id_operacion,old.rol_resenado) then
            raise exception 'Una reseña registrada no se modifica.';
        end if;
        return new;
    end if;
    if new.id_operacion is null then return new; end if;
    perform exigir_parte_operacion(new.id_usuario_resenador,new.id_operacion);
    select * into op from operacion_subasta where id_operacion=new.id_operacion for update;
    if op.estado<>'Completada' or op.fecha_resolucion is null or
       clock_timestamp()>=(op.fecha_resolucion at time zone 'America/La_Paz'+interval '14 days') at time zone 'America/La_Paz' then
        raise exception 'La operación no está disponible para valorar.';
    end if;
    if new.id_usuario_resenado<>(case when new.id_usuario_resenador=op.id_comprador then op.id_vendedor else op.id_comprador end)
       or new.rol_resenado<>(case when new.id_usuario_resenador=op.id_comprador then 'Vendedor' else 'Comprador' end) then
        raise exception 'Solo puedes valorar a la contraparte con su rol en esta operación.';
    end if;
    return new;
end;
$$;
drop trigger if exists resena_integridad on resenas;
create trigger resena_integridad before insert or update or delete on resenas for each row execute function proteger_resena_verificada();

create or replace function perfil_publico(p_usuario bigint)
returns table(id_usuario bigint,nombre text,ciudad text,identidad_verificada boolean,id_imagen bigint)
language sql stable as $$
    select u.id_usuario,u.nombre,case when u.ciudad_publica then u.ciudad else null end,u.identidad_verificada,
        (select i.id_imagen from imagen i where i.id_usuario=u.id_usuario and i.id_subasta is null order by i.id_imagen limit 1)
    from usuario u where u.id_usuario=p_usuario and u.activo
      and not exists(select 1 from usuario_rol ur join rol r using(id_rol) where ur.id_usuario=u.id_usuario and r.nombre_rol='Administrador');
$$;

create or replace function reputacion_publica(p_usuario bigint)
returns table(rol text,cantidad bigint,promedio numeric) language sql stable as $$
    select roles.rol,count(r.id_resena),round(avg(r.calificacion),2)
    from (values('Comprador'::text),('Vendedor'::text)) roles(rol)
    left join resenas_publicadas r on r.id_usuario_resenado=p_usuario and r.rol_resenado=roles.rol
    group by roles.rol order by roles.rol;
$$;

create or replace function reportar_resena(p_actor bigint,p_resena bigint,p_categoria text,p_detalle text) returns bigint language plpgsql as $$
declare resultado bigint; rese resenas_publicadas;
begin
    perform exigir_usuario_participante(p_actor);
    if p_categoria is null or p_categoria not in ('Insultos','Acoso','Datos personales') or p_detalle is null or length(trim(p_detalle)) not between 5 and 1000 then
        raise exception 'Selecciona insultos, acoso o datos personales y explica el problema (5 a 1.000 caracteres). Una puntuación baja no es motivo para ocultar una reseña.';
    end if;
    select * into rese from resenas_publicadas where id_resena=p_resena;
    if not found then raise exception 'Reseña no disponible.' using errcode='P0002'; end if;
    if rese.id_usuario_resenador=p_actor then raise exception 'No puedes reportar tu propia reseña.'; end if;
    insert into reporte_resena(id_resena,id_reportante,categoria,detalle) values(p_resena,p_actor,p_categoria,trim(p_detalle)) on conflict do nothing;
    select id_reporte into resultado from reporte_resena where id_resena=p_resena and id_reportante=p_actor;
    return resultado;
end;
$$;

create or replace function moderar_resena(p_actor bigint,p_reporte bigint,p_accion text,p_motivo text) returns void language plpgsql as $$
declare reporte reporte_resena;
begin
    perform exigir_administrador(p_actor);
    if p_accion is null or p_accion not in ('Ocultar comentario','Sin acción') or p_motivo is null or length(trim(p_motivo)) not between 5 and 2000 then
        raise exception 'Selecciona una acción y registra el motivo (5 a 2.000 caracteres).';
    end if;
    select * into reporte from reporte_resena where id_reporte=p_reporte for update;
    if not found then raise exception 'Reporte no encontrado.' using errcode='P0002'; end if;
    if reporte.accion is not null then return; end if;
    if p_accion='Ocultar comentario' then update resenas set comentario_oculto=true where id_resena=reporte.id_resena; end if;
    update reporte_resena set accion=p_accion,motivo_resolucion=trim(p_motivo),id_administrador=p_actor,fecha_resolucion=clock_timestamp() where id_reporte=p_reporte;
    insert into moderacion_resena(id_reporte,id_administrador,accion,motivo) values(p_reporte,p_actor,p_accion,trim(p_motivo));
end;
$$;
drop trigger if exists moderacion_resena_permanente on moderacion_resena;
create trigger moderacion_resena_permanente before update or delete on moderacion_resena for each row execute function impedir_edicion_historial_operacion();
