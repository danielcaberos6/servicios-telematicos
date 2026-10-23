-- SUBASTAYA · Resolución administrativa de solicitudes de subasta (HU-12)
-- Permite a un administrador activo aceptar o rechazar solicitudes de subasta en revisión.
create or replace function resolver_revision_subasta(
    p_actor bigint,
    p_id_subasta bigint,
    p_decision text,
    p_motivo text default null
)
returns void language plpgsql as $$
declare
    articulo subasta%rowtype;
    v_decision text;
    v_motivo text;
begin
    -- 1. Bloqueo para evitar decisiones simultáneas sobre revisiones
    perform pg_advisory_xact_lock(8012, 1);

    -- 2. Exigir permisos de administrador activo
    perform exigir_administrador(p_actor);

    -- 3. Normalizar y validar decisión
    v_decision := case lower(trim(coalesce(p_decision, '')))
        when 'aceptada' then 'Aceptada'
        when 'rechazada' then 'Rechazada'
        else p_decision
    end;

    if v_decision is null or v_decision not in ('Aceptada', 'Rechazada') then
        raise exception 'Selecciona una decisión válida (Aceptada o Rechazada).';
    end if;

    -- 4. Validar motivo si se rechaza
    if v_decision = 'Rechazada' then
        v_motivo := trim(coalesce(p_motivo, ''));
        if length(v_motivo) not between 1 and 1000 then
            raise exception 'El rechazo necesita un motivo de 1 a 1000 caracteres.';
        end if;
    else
        v_motivo := null;
    end if;

    -- 5. Obtener y bloquear la subasta
    select * into articulo from subasta where id_subasta = p_id_subasta for update;
    if not found then
        raise exception 'La subasta no existe.' using errcode = 'P0002';
    end if;

    -- 6. Comprobar que esté pendiente de revisión
    if articulo.estado_revision <> 'En revisión' then
        raise exception 'Esta subasta ya fue resuelta previamente.';
    end if;

    -- 7. Actualizar estado y motivo en subasta
    update subasta
    set estado_revision = v_decision,
        motivo_rechazo = v_motivo
    where id_subasta = p_id_subasta;

    -- 8. Notificar al vendedor de la resolución
    if v_decision = 'Aceptada' then
        insert into notificacion (id_usuario, id_subasta, titulo, contenido)
        values (
            articulo.id_usuario,
            p_id_subasta,
            'Subasta publicada',
            'Tu subasta «' || articulo.titulo || '» fue aceptada y ya está disponible en el catálogo.'
        );
    else
        insert into notificacion (id_usuario, id_subasta, titulo, contenido)
        values (
            articulo.id_usuario,
            p_id_subasta,
            'Subasta rechazada',
            'Tu subasta «' || articulo.titulo || '» fue rechazada. Motivo: ' || v_motivo
        );
    end if;
end;
$$;
