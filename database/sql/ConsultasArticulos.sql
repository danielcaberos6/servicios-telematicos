-- HU24. El actor siempre procede de la sesión del servidor.
create or replace function validar_texto_consulta(p_texto text,p_limite integer) returns text language plpgsql immutable as $$
declare texto text := btrim(coalesce(p_texto,''));
begin
    if texto !~ '[^[:space:]]' or char_length(texto)>p_limite then
        raise exception 'Escribe un texto de entre 1 y % caracteres.',p_limite;
    end if;
    if texto ~* '[[:alnum:]_.%+-]+@[[:alnum:].-]+\.[[:alpha:]]{2,}'
       or texto ~ '(^|[^[:alnum:]])(\+?591[ .()-]*)?[0-9]([ .()-]*[0-9]){7,}([^[:alnum:]]|$)'
       or texto ~* '(wa\.me/|api\.whatsapp|t\.me/)' then
        raise exception 'No incluyas teléfonos, correos ni enlaces de contacto privado en las consultas públicas.';
    end if;
    return texto;
end;
$$;

create or replace function exigir_consulta_abierta(p_subasta bigint) returns subasta language plpgsql as $$
declare articulo subasta;
begin
    -- Compartir el bloqueo con cierre, cancelación y cambios de la publicación.
    select s.* into articulo from subasta s where s.id_subasta=p_subasta for share;
    if not found or articulo.estado_revision<>'Aceptada' then
        raise exception 'La publicación no está disponible.' using errcode='P0002';
    end if;
    perform 1 from usuario where id_usuario=articulo.id_usuario and activo for share;
    if not found then raise exception 'El vendedor no está disponible.'; end if;
    if articulo.estado_subasta<>'Activa' or articulo.fecha_inicio>clock_timestamp() or articulo.fecha_fin<=clock_timestamp() then
        raise exception 'La subasta ya no admite preguntas ni respuestas.';
    end if;
    return articulo;
end;
$$;

create or replace function preguntar_articulo(p_actor bigint,p_subasta bigint,p_texto text,p_clave uuid) returns bigint language plpgsql as $$
declare articulo subasta; texto text; resultado bigint; anterior consulta_articulo;
begin
    perform exigir_usuario_participante(p_actor);
    articulo := exigir_consulta_abierta(p_subasta);
    if articulo.id_usuario=p_actor then raise exception 'No puedes hacer preguntas en tu propia subasta.' using errcode='42501'; end if;
    texto := validar_texto_consulta(p_texto,1000);
    if p_clave is null then raise exception 'Recarga la página antes de enviar tu pregunta.'; end if;
    insert into consulta_articulo(id_usuario,id_subasta,pregunta,clave_envio)
        values(p_actor,p_subasta,texto,p_clave) on conflict(id_usuario,clave_envio) do nothing returning id_consulta into resultado;
    if resultado is null then
        select * into anterior from consulta_articulo where id_usuario=p_actor and clave_envio=p_clave;
        if anterior.id_subasta<>p_subasta or anterior.pregunta<>texto then raise exception 'Esta solicitud ya fue utilizada. Recarga la publicación.'; end if;
        return anterior.id_consulta;
    end if;
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
        values(articulo.id_usuario,p_subasta,'Nueva pregunta sobre tu artículo','Tienes una pregunta en «' || articulo.titulo || '». Abre la publicación para responder.');
    return resultado;
end;
$$;

create or replace function responder_articulo(p_actor bigint,p_consulta bigint,p_texto text) returns void language plpgsql as $$
declare consulta consulta_articulo; articulo subasta; texto text; subasta_id bigint;
begin
    perform exigir_usuario_participante(p_actor);
    select id_subasta into subasta_id from consulta_articulo where id_consulta=p_consulta;
    if not found then raise exception 'La pregunta no está disponible.' using errcode='P0002'; end if;
    articulo := exigir_consulta_abierta(subasta_id);
    if articulo.id_usuario<>p_actor then raise exception 'Solo el vendedor puede responder esta pregunta.' using errcode='42501'; end if;
    select * into consulta from consulta_articulo where id_consulta=p_consulta for update;
    if articulo.fecha_fin<=clock_timestamp() then raise exception 'La subasta ya no admite preguntas ni respuestas.'; end if;
    if consulta.pregunta_oculta then raise exception 'La pregunta no está disponible.' using errcode='P0002'; end if;
    texto := validar_texto_consulta(p_texto,2000);
    if consulta.respuesta is not null then
        if consulta.respuesta=texto then return; end if;
        raise exception 'Esta pregunta ya tiene una respuesta. Recarga la publicación.';
    end if;
    update consulta_articulo set respuesta=texto,id_responde=p_actor,fecha_respuesta=clock_timestamp() where id_consulta=p_consulta;
    insert into notificacion(id_usuario,id_subasta,titulo,contenido)
        values(consulta.id_usuario,subasta_id,'Respondieron tu pregunta','El vendedor respondió tu pregunta en «' || articulo.titulo || '».');
end;
$$;

create or replace function reportar_consulta(p_actor bigint,p_consulta bigint,p_tipo text,p_motivo text) returns void language plpgsql as $$
declare consulta consulta_articulo; motivo text := btrim(coalesce(p_motivo,''));
begin
    perform exigir_usuario_participante(p_actor);
    select c.* into consulta from consulta_articulo c join subasta s using(id_subasta) join usuario u on u.id_usuario=s.id_usuario
        where c.id_consulta=p_consulta and s.estado_revision='Aceptada' and u.activo and not c.pregunta_oculta for share of c;
    if not found then raise exception 'La consulta no está disponible.' using errcode='P0002'; end if;
    if p_tipo is null or p_tipo not in ('pregunta','respuesta') then raise exception 'Selecciona el contenido que quieres reportar.'; end if;
    if p_tipo='respuesta' and (consulta.respuesta is null or consulta.respuesta_oculta) then raise exception 'La respuesta no está disponible.' using errcode='P0002'; end if;
    if motivo !~ '[^[:space:]]' or char_length(motivo)>500 then raise exception 'Explica el motivo del reporte en un máximo de 500 caracteres.'; end if;
    insert into reporte_consulta(id_consulta,id_usuario,tipo,motivo) values(p_consulta,p_actor,p_tipo,motivo) on conflict do nothing;
end;
$$;

create or replace function moderar_consulta(p_actor bigint,p_reporte bigint,p_accion text,p_motivo text) returns void language plpgsql as $$
declare reporte reporte_consulta; consulta_id bigint; v_motivo text := btrim(coalesce(p_motivo,''));
begin
    perform exigir_administrador(p_actor);
    if p_accion is null or p_accion not in ('ocultar','desestimar') or v_motivo !~ '[^[:space:]]' or char_length(v_motivo)>500 then
        raise exception 'Selecciona una decisión y explica el motivo en un máximo de 500 caracteres.';
    end if;
    select id_consulta into consulta_id from reporte_consulta where id_reporte=p_reporte;
    if not found then raise exception 'El reporte no existe.' using errcode='P0002'; end if;
    -- Todos los moderadores bloquean primero la consulta y después sus reportes.
    perform 1 from consulta_articulo where id_consulta=consulta_id for update;
    select * into reporte from reporte_consulta where id_reporte=p_reporte for update;
    if reporte.accion is not null then return; end if;
    if p_accion='ocultar' then
        update consulta_articulo set pregunta_oculta=case when reporte.tipo='pregunta' then true else pregunta_oculta end,
            respuesta_oculta=case when reporte.tipo='respuesta' then true else respuesta_oculta end where id_consulta=consulta_id;
    end if;
    update reporte_consulta set accion=p_accion,motivo_resolucion=v_motivo,id_moderador=p_actor,fecha_resolucion=clock_timestamp()
        where id_consulta=consulta_id and tipo=reporte.tipo and accion is null;
end;
$$;
