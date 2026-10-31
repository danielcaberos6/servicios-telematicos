-- La versión se cambia mediante una migración nueva y junto con el texto publicado.
create or replace function normas_version_vigente() returns text language sql immutable as $$
    select '2026-10-31.3'::text;
$$;

create or replace function exigir_normas(p_actor bigint) returns void language plpgsql as $$
begin
    if not exists (select 1 from aceptacion_normas where id_usuario=p_actor and version=normas_version_vigente()) then
        raise exception 'Lee y acepta la versión vigente de las normas antes de publicar u ofertar.';
    end if;
end;
$$;

create or replace function aceptar_normas(p_actor bigint, p_version text) returns void language plpgsql as $$
begin
    if not exists (select 1 from usuario u join usuario_rol ur using(id_usuario) join rol r using(id_rol)
        where u.id_usuario=p_actor and u.activo and r.nombre_rol='Usuario')
        or exists (select 1 from usuario_rol ur join rol r using(id_rol) where ur.id_usuario=p_actor and r.nombre_rol='Administrador') then
        raise exception 'Solo un usuario activo puede aceptar las normas de participación.' using errcode='42501';
    end if;
    if p_version is distinct from normas_version_vigente() then
        raise exception 'Las normas cambiaron. Recarga la página y lee la versión vigente.';
    end if;
    insert into aceptacion_normas(id_usuario,version) values(p_actor,p_version) on conflict do nothing;
end;
$$;
