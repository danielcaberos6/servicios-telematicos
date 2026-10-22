create or replace function resolver_verificacion(p_actor bigint,p_id bigint,p_decision text,p_motivo text default null)
returns void language plpgsql as $$
declare solicitud verificacion_identidad; cuenta usuario; titular bigint;
begin
    perform pg_advisory_xact_lock(8009,1);
    perform exigir_administrador(p_actor);
    if p_decision is null or p_decision not in ('Aprobada','Rechazada') then raise exception 'Selecciona una decisión válida.'; end if;
    if p_decision='Rechazada' and (p_motivo is null or length(trim(p_motivo)) not between 1 and 1000) then
        raise exception 'El rechazo necesita un motivo de 1 a 1000 caracteres.';
    end if;
    select id_usuario into titular from verificacion_identidad where id_verificacion=p_id;
    if not found then raise exception 'Solicitud no encontrada.' using errcode='P0002'; end if;
    -- El mismo orden de bloqueos que el perfil y el envío: usuario antes de solicitud.
    select * into cuenta from usuario where id_usuario=titular for update;
    select * into solicitud from verificacion_identidad where id_verificacion=p_id for update;
    if solicitud.estado<>'En revisión' or not solicitud.vigente then raise exception 'Esta solicitud ya fue resuelta o perdió vigencia.'; end if;
    if not cuenta.activo or cuenta.version_identidad<>solicitud.version_identidad
        or row(cuenta.nombres,cuenta.apellidos) is distinct from row(solicitud.nombres,solicitud.apellidos) then
        raise exception 'El perfil cambió o la cuenta está desactivada. No se puede resolver esta versión.';
    end if;
    update verificacion_identidad set estado=p_decision,id_revisor=p_actor,fecha_decision=clock_timestamp(),
        motivo=case when p_decision='Rechazada' then trim(p_motivo) else null end where id_verificacion=p_id;
    update usuario set identidad_verificada=(p_decision='Aprobada') where id_usuario=titular;
    insert into notificacion(id_usuario,titulo,contenido,id_verificacion) values(titular,
        case when p_decision='Aprobada' then 'Identidad verificada' else 'Verificación de identidad rechazada' end,
        case when p_decision='Aprobada' then 'Tu identidad fue verificada. Tu perfil ya muestra el distintivo Verificado.'
            else 'Motivo: ' || trim(p_motivo) || '. Puedes corregir tus datos y enviar otra imagen desde tu perfil.' end,p_id);
end;
$$;
