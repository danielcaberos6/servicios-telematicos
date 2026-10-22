alter table categoria add column habilitada boolean not null default true;
create unique index categoria_nombre_normalizado on categoria(lower(trim(nombre_categoria)));

create or replace function validar_nombre_categoria() returns trigger language plpgsql as $$
begin
    new.nombre_categoria := trim(new.nombre_categoria);
    if new.nombre_categoria is null or length(new.nombre_categoria) not between 1 and 100 then
        raise exception 'El nombre de categoría es obligatorio y admite hasta 100 caracteres.';
    end if;
    if length(coalesce(new.descripcion,'')) > 1000 then raise exception 'La descripción admite hasta 1000 caracteres.'; end if;
    return new;
end;
$$;
create trigger categoria_validar_nombre before insert or update on categoria for each row execute function validar_nombre_categoria();

create or replace function guardar_categoria(p_actor bigint,p_id bigint,p_datos jsonb) returns bigint language plpgsql as $$
declare resultado bigint;
begin
    perform exigir_administrador(p_actor);
    if p_id is null then
        insert into categoria(nombre_categoria,descripcion) values(p_datos->>'nombre_categoria',p_datos->>'descripcion') returning id_categoria into resultado;
    else
        update categoria set nombre_categoria=p_datos->>'nombre_categoria',descripcion=p_datos->>'descripcion' where id_categoria=p_id returning id_categoria into resultado;
        if not found then raise exception 'Categoría no encontrada.' using errcode='P0002'; end if;
    end if;
    return resultado;
end;
$$;

create or replace function habilitar_categoria(p_actor bigint,p_id bigint,p_habilitada boolean) returns void language plpgsql as $$
begin
    perform exigir_administrador(p_actor);
    if p_habilitada is null then raise exception 'Selecciona el estado de la categoría.'; end if;
    update categoria set habilitada=p_habilitada where id_categoria=p_id;
    if not found then raise exception 'Categoría no encontrada.' using errcode='P0002'; end if;
end;
$$;

create or replace function validar_categoria_publicacion() returns trigger language plpgsql as $$
declare disponible boolean; validar boolean := true;
begin
    if tg_op='UPDATE' then
        validar := new.id_categoria is distinct from old.id_categoria
            or (new.estado_subasta='Activa' and old.estado_subasta is distinct from 'Activa');
    end if;
    if validar then
        select habilitada into disponible from categoria where id_categoria=new.id_categoria for share;
        if disponible is distinct from true then raise exception 'Selecciona una categoría habilitada para publicar.'; end if;
    end if;
    return new;
end;
$$;
create trigger subasta_categoria_habilitada before insert or update of id_categoria,estado_subasta on subasta
    for each row execute function validar_categoria_publicacion();
