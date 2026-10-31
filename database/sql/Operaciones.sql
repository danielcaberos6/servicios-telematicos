-- HU23: cada cambio de entrega se serializa mediante el bloqueo de la operación.
create or replace function exigir_parte_operacion(p_actor bigint,p_id bigint) returns operacion_subasta language plpgsql stable as $$
declare op operacion_subasta;
begin
    perform exigir_usuario_participante(p_actor);
    select * into op from operacion_subasta where id_operacion=p_id;
    if not found or p_actor not in (op.id_comprador,op.id_vendedor) then
        raise exception 'Operación no encontrada.' using errcode='P0002';
    end if;
    return op;
end;
$$;

create or replace function crear_operacion_cierre(p_subasta bigint) returns bigint language plpgsql as $$
declare s subasta; ganador puja; resultado bigint; reserva reserva_fondos;
begin
    select * into s from subasta where id_subasta=p_subasta for update;
    if not found or s.estado_subasta<>'Finalizada' or s.estado_revision<>'Aceptada' then return null; end if;
    select * into ganador from puja where id_subasta=p_subasta order by monto desc,id_puja asc limit 1;
    if not found then return null; end if;
    select id_operacion into resultado from operacion_subasta where id_subasta=p_subasta;
    if found then return resultado; end if;
    if s.requiere_fondos then
        select * into reserva from reserva_fondos where id_subasta=p_subasta;
        if not found or reserva.estado<>'Retenida' or reserva.id_usuario<>ganador.id_usuario or reserva.monto<>ganador.monto then
            raise exception 'La reserva del ganador no coincide con el cierre.';
        end if;
    end if;
    insert into operacion_subasta(id_subasta,id_comprador,id_vendedor,importe,comision,neto,con_fondos,fecha_creacion)
    values(p_subasta,ganador.id_usuario,s.id_usuario,ganador.monto,
        case when s.requiere_fondos then round(ganador.monto*0.05,2) else 0 end,
        case when s.requiere_fondos then ganador.monto-round(ganador.monto*0.05,2) else 0 end,s.requiere_fondos,case when s.requiere_fondos then s.fecha_fin else clock_timestamp() end)
    returning id_operacion into resultado;
    insert into evento_operacion(id_operacion,accion,detalle) values(resultado,'Creación',case when s.requiere_fondos then 'Orden creada con fondos retenidos y comisión del 5 %.' else 'Coordinación de publicación anterior, sin reservas ni cobro de comisión.' end);
    -- El finalizador ya comunica el ganador; estos avisos identifican la operación privada.
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
    select persona,p_subasta,'Entrega por coordinar','Ya puedes coordinar «'||s.titulo||'» desde Mis operaciones.'
    from unnest(array[s.id_usuario,ganador.id_usuario]) persona;
    return resultado;
end;
$$;

create or replace function liquidar_operacion(p_actor bigint,p_id bigint,p_devolver boolean default false,p_motivo text default null) returns void language plpgsql as $$
declare op operacion_subasta; r reserva_fondos;
begin
    select * into op from operacion_subasta where id_operacion=p_id for update;
    if not found then raise exception 'Operación no encontrada.' using errcode='P0002'; end if;
    if p_actor in (op.id_comprador,op.id_vendedor) then
        perform exigir_parte_operacion(p_actor,p_id);
        if p_devolver or op.fecha_confirmacion_comprador is null or op.fecha_confirmacion_vendedor is null or exists(select 1 from incidencia_operacion where id_operacion=p_id and estado<>'Resuelta') then
            raise exception 'La entrega necesita ambas confirmaciones y ninguna incidencia abierta.';
        end if;
    else
        perform exigir_administrador(p_actor);
        if length(trim(coalesce(p_motivo,''))) not between 5 and 2000 or not exists(select 1 from incidencia_operacion where id_operacion=p_id and id_administrador=p_actor and estado<>'Resuelta') then
            raise exception 'La resolución requiere una incidencia asignada y un motivo.' using errcode='42501';
        end if;
    end if;
    if op.estado in ('Completada','Reembolsada') then return; end if;
    if op.con_fondos then
        select * into r from reserva_fondos where id_subasta=op.id_subasta;
        if not found or r.estado<>'Retenida' or r.id_usuario<>op.id_comprador or r.monto<>op.importe then raise exception 'Reserva no disponible para resolver la entrega.'; end if;
        perform id_usuario from billetera where id_usuario in (op.id_comprador,op.id_vendedor) order by id_usuario for update;
        if p_devolver then
            insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia,id_subasta)
                values(op.id_comprador,'Devolución',op.importe,op.importe,-op.importe,'operacion:'||p_id,op.id_subasta);
        else
            insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia,id_subasta) values
                (op.id_comprador,'Pago',op.importe,0,-op.importe,'operacion:'||p_id,op.id_subasta),
                (op.id_vendedor,'Liquidación',op.neto,op.neto,0,'operacion:'||p_id,op.id_subasta);
            insert into comision_operacion(id_operacion,importe) values(p_id,op.comision);
        end if;
        update reserva_fondos set estado=case when p_devolver then 'Liberada' else 'Consumida' end,fecha_actualizacion=clock_timestamp() where id_subasta=op.id_subasta;
    end if;
    update operacion_subasta set estado=case when p_devolver then 'Reembolsada' else 'Completada' end,fecha_resolucion=clock_timestamp() where id_operacion=p_id;
    insert into evento_operacion(id_operacion,id_actor,accion,detalle)
        values(p_id,p_actor,case when p_devolver then 'Devolución de reserva' else 'Liquidación' end,
            coalesce(nullif(trim(p_motivo),''),'Ambas partes confirmaron la entrega sin incidencias abiertas.'));
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
        select persona,op.id_subasta,case when p_devolver then 'Operación cancelada' else 'Entrega completada' end,
        case when not op.con_fondos then 'La coordinación terminó sin movimientos de billetera.' when p_devolver then 'El importe reservado volvió a estar disponible para el comprador. No se cobró comisión.' else 'Se registró el pago al vendedor y la comisión del 5 %.' end
        from unnest(array[op.id_comprador,op.id_vendedor]) persona;
end;
$$;

create or replace function confirmar_entrega(p_actor bigint,p_id bigint) returns void language plpgsql as $$
declare op operacion_subasta;
begin
    perform exigir_parte_operacion(p_actor,p_id);
    select * into op from operacion_subasta where id_operacion=p_id for update;
    if op.estado in ('Completada','Reembolsada') then return; end if;
    if (p_actor=op.id_comprador and op.fecha_confirmacion_comprador is null) or (p_actor=op.id_vendedor and op.fecha_confirmacion_vendedor is null) then
        update operacion_subasta set
            fecha_confirmacion_comprador=case when p_actor=id_comprador then clock_timestamp() else fecha_confirmacion_comprador end,
            fecha_confirmacion_vendedor=case when p_actor=id_vendedor then clock_timestamp() else fecha_confirmacion_vendedor end
            where id_operacion=p_id returning * into op;
        insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(p_id,p_actor,'Confirmación',case when p_actor=op.id_comprador then 'El comprador confirmó la recepción.' else 'El vendedor confirmó la entrega.' end);
    end if;
    if op.fecha_confirmacion_comprador is not null and op.fecha_confirmacion_vendedor is not null and not exists(select 1 from incidencia_operacion where id_operacion=p_id and estado<>'Resuelta') then
        perform liquidar_operacion(p_actor,p_id,false);
    end if;
end;
$$;

create or replace function abrir_incidencia(p_actor bigint,p_id bigint,p_motivo text) returns bigint language plpgsql as $$
declare op operacion_subasta; resultado bigint;
begin
    perform exigir_parte_operacion(p_actor,p_id);
    if p_motivo is null or length(trim(p_motivo)) not between 5 and 2000 then raise exception 'Describe el problema usando entre 5 y 2.000 caracteres.'; end if;
    select * into op from operacion_subasta where id_operacion=p_id for update;
    if op.estado in ('Completada','Reembolsada') then raise exception 'La operación ya está resuelta.'; end if;
    select id_incidencia into resultado from incidencia_operacion where id_operacion=p_id and estado<>'Resuelta';
    if found then return resultado; end if;
    insert into incidencia_operacion(id_operacion,id_reportante,motivo) values(p_id,p_actor,trim(p_motivo)) returning id_incidencia into resultado;
    update operacion_subasta set estado='En revisión' where id_operacion=p_id;
    insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(p_id,p_actor,'Incidencia',trim(p_motivo));
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
        select u.id_usuario,op.id_subasta,'Incidencia de entrega','Hay una incidencia para revisar en Gestión de operaciones.' from usuario u
        where u.activo and exists(select 1 from usuario_rol ur join rol r using(id_rol) where ur.id_usuario=u.id_usuario and r.nombre_rol='Administrador');
    return resultado;
end;
$$;

create or replace function asignar_incidencia(p_actor bigint,p_incidencia bigint) returns void language plpgsql as $$
declare caso incidencia_operacion;
begin
    perform exigir_administrador(p_actor);
    perform o.id_operacion from operacion_subasta o join incidencia_operacion i using(id_operacion)
        where i.id_incidencia=p_incidencia for update of o;
    select * into caso from incidencia_operacion where id_incidencia=p_incidencia for update;
    if not found then raise exception 'Incidencia no encontrada.' using errcode='P0002'; end if;
    if caso.estado='Resuelta' or (caso.id_administrador is not null and caso.id_administrador<>p_actor) then raise exception 'Esta incidencia ya está resuelta o asignada a otro administrador.'; end if;
    if caso.id_administrador is null then
        update incidencia_operacion set id_administrador=p_actor,fecha_atencion=clock_timestamp(),estado='En revisión' where id_incidencia=p_incidencia;
        insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(caso.id_operacion,p_actor,'Atención','Administrador asignado a incidencia '||p_incidencia);
    end if;
end;
$$;

create or replace function resolver_incidencia(p_actor bigint,p_incidencia bigint,p_decision text,p_motivo text) returns void language plpgsql as $$
declare caso incidencia_operacion; op operacion_subasta;
begin
    perform exigir_administrador(p_actor);
    if p_decision is null or p_decision not in ('Liquidar','Devolver') or p_motivo is null or length(trim(p_motivo)) not between 5 and 2000 then raise exception 'Selecciona una resolución y escribe el motivo (5 a 2.000 caracteres).'; end if;
    select * into caso from incidencia_operacion where id_incidencia=p_incidencia;
    if not found or caso.id_administrador is distinct from p_actor then raise exception 'Incidencia no asignada a esta cuenta.' using errcode='42501'; end if;
    select * into op from operacion_subasta where id_operacion=caso.id_operacion for update;
    select * into caso from incidencia_operacion where id_incidencia=p_incidencia for update;
    if caso.estado='Resuelta' then return; end if;
    perform liquidar_operacion(p_actor,caso.id_operacion,p_decision='Devolver',p_motivo);
    update incidencia_operacion set estado='Resuelta',decision=p_decision,resolucion=trim(p_motivo),fecha_resolucion=clock_timestamp() where id_incidencia=p_incidencia;
    insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(caso.id_operacion,p_actor,p_decision,trim(p_motivo));
end;
$$;

create or replace function proponer_encuentro(p_actor bigint,p_id bigint,p_lugar text,p_fecha timestamptz) returns void language plpgsql as $$
declare op operacion_subasta;
begin
    perform exigir_parte_operacion(p_actor,p_id);
    select * into op from operacion_subasta where id_operacion=p_id for update;
    if op.estado in ('Completada','Reembolsada') then raise exception 'La operación ya está resuelta.'; end if;
    if p_lugar is null or length(trim(p_lugar)) not between 5 and 250 or p_fecha is null or p_fecha<=clock_timestamp() or p_fecha>clock_timestamp()+interval '1 year' then raise exception 'Indica un lugar público y una fecha futura dentro del próximo año.'; end if;
    update acuerdo_operacion set estado='Reemplazado',fecha_respuesta=clock_timestamp() where id_operacion=p_id and estado='Propuesto';
    insert into acuerdo_operacion(id_operacion,id_proponente,lugar,fecha_encuentro) values(p_id,p_actor,trim(p_lugar),p_fecha);
    insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(p_id,p_actor,'Propuesta de encuentro',trim(p_lugar)||' · '||to_char(p_fecha at time zone 'America/La_Paz','DD/MM/YYYY HH24:MI')||' (Bolivia)');
    insert into notificacion(id_usuario,id_subasta,titulo,contenido) values(case when p_actor=op.id_comprador then op.id_vendedor else op.id_comprador end,op.id_subasta,'Propuesta de entrega','Revisa el lugar y la fecha propuestos en Mis operaciones.');
end;
$$;

create or replace function responder_encuentro(p_actor bigint,p_acuerdo bigint,p_aceptar boolean,p_motivo text default '') returns void language plpgsql as $$
declare acuerdo acuerdo_operacion; op operacion_subasta;
begin
    select * into acuerdo from acuerdo_operacion where id_acuerdo=p_acuerdo;
    if not found then raise exception 'Propuesta no encontrada.' using errcode='P0002'; end if;
    perform exigir_parte_operacion(p_actor,acuerdo.id_operacion);
    select * into op from operacion_subasta where id_operacion=acuerdo.id_operacion for update;
    select * into acuerdo from acuerdo_operacion where id_acuerdo=p_acuerdo for update;
    if p_actor=acuerdo.id_proponente then raise exception 'La propuesta debe responderla la otra parte.'; end if;
    if acuerdo.estado<>'Propuesto' then return; end if;
    if op.estado in ('Completada','Reembolsada') then raise exception 'La operación ya está resuelta.'; end if;
    if p_aceptar and acuerdo.fecha_encuentro<=clock_timestamp() then raise exception 'La fecha del encuentro ya pasó. Propón una nueva.'; end if;
    if p_aceptar is null or (not p_aceptar and (p_motivo is null or length(trim(p_motivo)) not between 5 and 1000)) then raise exception 'Explica el cambio solicitado (5 a 1.000 caracteres).'; end if;
    update acuerdo_operacion set estado=case when p_aceptar then 'Aceptado' else 'Cambio solicitado' end,respuesta=trim(p_motivo),fecha_respuesta=clock_timestamp() where id_acuerdo=p_acuerdo;
    insert into evento_operacion(id_operacion,id_actor,accion,detalle) values(acuerdo.id_operacion,p_actor,'Respuesta de encuentro',case when p_aceptar then 'Encuentro aceptado.' else trim(p_motivo) end);
    insert into notificacion(id_usuario,id_subasta,titulo,contenido) values(acuerdo.id_proponente,op.id_subasta,'Respuesta de entrega','Revisa la respuesta a tu propuesta en Mis operaciones.');
end;
$$;

create or replace function enviar_mensaje_operacion(p_actor bigint,p_id bigint,p_referencia uuid,p_texto text) returns bigint language plpgsql as $$
declare op operacion_subasta; anterior mensaje_operacion; resultado bigint;
begin
    perform exigir_parte_operacion(p_actor,p_id);
    if p_referencia is null or p_texto is null or length(trim(p_texto)) not between 1 and 2000 then raise exception 'Escribe un mensaje de 1 a 2.000 caracteres.'; end if;
    select * into op from operacion_subasta where id_operacion=p_id for update;
    select * into anterior from mensaje_operacion where referencia=p_referencia;
    if found then
        if anterior.id_autor<>p_actor or anterior.id_operacion<>p_id or anterior.texto<>trim(p_texto) then raise exception 'La referencia ya se utilizó para otro mensaje.'; end if;
        return anterior.id_mensaje;
    end if;
    if op.estado='Reembolsada' then raise exception 'Esta conversación está cerrada; puedes consultar su historial.'; end if;
    insert into mensaje_operacion(referencia,id_operacion,id_autor,texto) values(p_referencia,p_id,p_actor,trim(p_texto)) returning id_mensaje into resultado;
    return resultado;
end;
$$;

create or replace function exigir_chat_incidencia(p_actor bigint,p_incidencia bigint) returns bigint language plpgsql as $$
declare caso incidencia_operacion;
begin
    perform exigir_administrador(p_actor);
    select * into caso from incidencia_operacion where id_incidencia=p_incidencia;
    if not found or caso.id_administrador is distinct from p_actor or caso.estado='Resuelta' then raise exception 'No tienes una incidencia activa asignada para consultar esta conversación.' using errcode='42501'; end if;
    insert into acceso_conversacion(id_incidencia,id_administrador,motivo) values(p_incidencia,p_actor,'Revisión de incidencia: '||caso.motivo);
    return caso.id_operacion;
end;
$$;

create or replace function leer_mensajes_operacion(p_actor bigint,p_id bigint,p_hasta bigint) returns void language plpgsql as $$
declare op operacion_subasta; ultimo bigint;
begin
    op := exigir_parte_operacion(p_actor,p_id);
    select coalesce(max(id_mensaje),0) into ultimo from mensaje_operacion where id_operacion=p_id and id_mensaje<=p_hasta;
    update operacion_subasta set leido_comprador=case when p_actor=id_comprador then greatest(leido_comprador,ultimo) else leido_comprador end,
        leido_vendedor=case when p_actor=id_vendedor then greatest(leido_vendedor,ultimo) else leido_vendedor end where id_operacion=p_id;
end;
$$;

create or replace function revisar_operaciones_vencidas() returns integer language plpgsql as $$
declare op operacion_subasta; total integer:=0;
begin
    for op in select * from operacion_subasta where estado in ('Pendiente','En revisión') and aviso_72h is null
        and fecha_creacion<=clock_timestamp()-interval '72 hours' for update skip locked loop
        insert into incidencia_operacion(id_operacion,motivo) values(op.id_operacion,'Seguimiento automático: transcurrieron 72 horas sin resolver la entrega.') on conflict do nothing;
        update operacion_subasta set aviso_72h=clock_timestamp(),estado='En revisión' where id_operacion=op.id_operacion;
        insert into evento_operacion(id_operacion,accion,detalle) values(op.id_operacion,'Seguimiento','72 horas sin resolver: revisión administrativa, fondos retenidos.');
        insert into notificacion(id_usuario,id_subasta,titulo,contenido)
            select persona,op.id_subasta,'Entrega pendiente de revisión','La entrega lleva 72 horas pendiente y se derivó a revisión. Los fondos no se liberaron.' from unnest(array[op.id_comprador,op.id_vendedor]) persona;
        insert into notificacion(id_usuario,id_subasta,titulo,contenido)
            select u.id_usuario,op.id_subasta,'Entrega pendiente de revisión','Revisa el caso de seguimiento en Gestión de operaciones.' from usuario u
            where u.activo and exists(select 1 from usuario_rol ur join rol r using(id_rol) where ur.id_usuario=u.id_usuario and r.nombre_rol='Administrador');
        total:=total+1;
    end loop;
    return total;
end;
$$;

create or replace function impedir_edicion_historial_operacion() returns trigger language plpgsql as $$
begin
    raise exception 'Este historial es permanente y no admite edición ni eliminación.';
end;
$$;
drop trigger if exists mensaje_permanente on mensaje_operacion;
create trigger mensaje_permanente before update or delete on mensaje_operacion for each row execute function impedir_edicion_historial_operacion();
drop trigger if exists evento_permanente on evento_operacion;
create trigger evento_permanente before update or delete on evento_operacion for each row execute function impedir_edicion_historial_operacion();
drop trigger if exists acceso_permanente on acceso_conversacion;
create trigger acceso_permanente before update or delete on acceso_conversacion for each row execute function impedir_edicion_historial_operacion();

drop trigger if exists comision_permanente on comision_operacion;
create trigger comision_permanente before update or delete on comision_operacion for each row execute function impedir_edicion_historial_operacion();

create or replace function proteger_importes_operacion() returns trigger language plpgsql as $$
begin
    if TG_OP='DELETE' then raise exception 'La operación y su historial deben conservarse.'; end if;
    if (new.id_subasta,new.id_comprador,new.id_vendedor,new.importe,new.comision,new.neto,new.con_fondos,new.fecha_creacion)
       is distinct from (old.id_subasta,old.id_comprador,old.id_vendedor,old.importe,old.comision,old.neto,old.con_fondos,old.fecha_creacion) then
        raise exception 'Los participantes y los importes de una operación son permanentes.';
    end if;
    return new;
end;
$$;
drop trigger if exists operacion_integridad on operacion_subasta;
create trigger operacion_integridad before update or delete on operacion_subasta for each row execute function proteger_importes_operacion();
