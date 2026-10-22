create or replace function listar_usuarios_gestion(p_actor bigint, p_filtros jsonb)
returns jsonb language plpgsql stable as $$
declare
    pagina integer := coalesce((p_filtros->>'page')::integer, 1);
    busqueda text := lower(trim(coalesce(p_filtros->>'q', '')));
    estado text := coalesce(p_filtros->>'estado', '');
begin
    if not exists (
        select 1 from usuario u
        join usuario_rol ur on ur.id_usuario = u.id_usuario
        join rol r on r.id_rol = ur.id_rol
        where u.id_usuario = p_actor and u.activo and r.nombre_rol = 'Administrador'
    ) then
        raise exception 'Solo los administradores activos pueden gestionar usuarios.' using errcode = '42501';
    end if;
    if pagina < 1 or pagina > 100000 or estado not in ('', 'activo', 'inactivo') then
        raise exception 'Los filtros de usuarios no son válidos.';
    end if;

    return (
        with filtrados as (
            select u.id_usuario, u.nombre, u.correo, u.activo, u.fecha_registro,
                exists (select 1 from usuario_rol ur join rol r on r.id_rol = ur.id_rol
                    where ur.id_usuario = u.id_usuario and r.nombre_rol = 'Administrador') as administrador
            from usuario u
            where (busqueda = '' or strpos(lower(u.nombre), busqueda) > 0 or strpos(lower(u.correo), busqueda) > 0)
                and (estado = '' or u.activo = (estado = 'activo'))
        ), detalle as (
            select * from filtrados order by lower(nombre), id_usuario limit 15 offset (pagina - 1) * 15
        )
        select jsonb_build_object(
            'total', (select count(*) from filtrados),
            'detalle', coalesce((select jsonb_agg(to_jsonb(d) order by lower(d.nombre), d.id_usuario) from detalle d), '[]'::jsonb)
        )
    );
end;
$$;
