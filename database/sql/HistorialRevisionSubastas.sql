create table decision_subasta (
    id_decision bigint generated always as identity primary key,
    id_subasta bigint not null references subasta(id_subasta) on delete cascade,
    estado text not null check (estado in ('Aceptada', 'Rechazada')),
    motivo text,
    id_revisor bigint references usuario(id_usuario),
    fecha_decision timestamptz,
    check ((id_revisor is null) = (fecha_decision is null))
);
create index decision_subasta_historial_idx on decision_subasta(id_subasta, id_decision);

-- Conservar también decisiones anteriores cuyos responsable y fecha se desconocen.
insert into decision_subasta(id_subasta, estado, motivo, id_revisor, fecha_decision)
select id_subasta, estado_revision, motivo_rechazo, id_revisor, fecha_decision
from subasta where estado_revision in ('Aceptada', 'Rechazada');

create or replace function registrar_decision_subasta() returns trigger language plpgsql as $$
begin
    if new.estado_revision in ('Aceptada', 'Rechazada') and new.estado_revision is distinct from old.estado_revision then
        insert into decision_subasta(id_subasta, estado, motivo, id_revisor, fecha_decision)
        values(new.id_subasta, new.estado_revision, new.motivo_rechazo, new.id_revisor, new.fecha_decision);
    end if;
    return new;
end;
$$;
create trigger subasta_historial_decision after update of estado_revision on subasta
for each row execute function registrar_decision_subasta();

create or replace function notificar_reenvio_subasta() returns trigger language plpgsql as $$
begin
    if old.estado_revision = 'Rechazada' and new.estado_revision = 'En revisión' then
        insert into notificacion(id_usuario, id_subasta, titulo, contenido)
        values(new.id_usuario, new.id_subasta, 'Solicitud reenviada a revisión',
            'Reenviaste «' || new.titulo || '» a revisión. Espera una nueva decisión administrativa.');
        insert into notificacion(id_usuario, id_subasta, titulo, contenido)
        select distinct u.id_usuario, new.id_subasta, 'Solicitud corregida para revisión',
            'El vendedor corrigió «' || new.titulo || '» y solicita una nueva revisión.'
        from usuario u join usuario_rol ur on ur.id_usuario=u.id_usuario join rol r on r.id_rol=ur.id_rol
        where u.activo and r.nombre_rol='Administrador' and u.id_usuario<>new.id_usuario;
    end if;
    return new;
end;
$$;
create trigger subasta_notificar_reenvio after update of estado_revision on subasta
for each row execute function notificar_reenvio_subasta();
