-- SUBASTAYA · Revisión de publicaciones (esquema). Se instala una sola vez por migración.
-- Las subastas existentes ya estaban publicadas y se conservan como Aceptadas;
-- desde ahora toda subasta nueva queda En revisión hasta la decisión del Administrador.
alter table subasta add column estado_revision text not null default 'Aceptada';
alter table subasta alter column estado_revision set default 'En revisión';
alter table subasta add column motivo_rechazo text;
alter table subasta add constraint subasta_estado_revision_valido
    check (estado_revision in ('En revisión', 'Aceptada', 'Rechazada'));
-- El rechazo exige un motivo de 1 a 1000 caracteres; los demás estados no lo llevan.
alter table subasta add constraint subasta_motivo_rechazo_valido check (
    (estado_revision = 'Rechazada' and length(trim(coalesce(motivo_rechazo, ''))) between 1 and 1000)
    or (estado_revision <> 'Rechazada' and motivo_rechazo is null)
);
create index subasta_revision_idx on subasta (estado_revision, fecha_inicio);
