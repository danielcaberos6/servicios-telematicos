-- SUBASTAYA · Operaciones PostgreSQL
-- Ejecutar después de Script.sql. Laravel usa consultas parametrizadas.
-- p_actor siempre procede de la sesión autenticada de Laravel, nunca del formulario.
-- Las funciones usan SECURITY INVOKER; las credenciales de BD son exclusivas del servidor.

-- Catálogo público: solo publicaciones activas en su periodo de vigencia.
create or replace function catalogo_subastas(p_filtros jsonb default '{}'::jsonb)
returns setof subasta language sql stable as $$
    select s.* from subasta s
    where s.estado_subasta = 'Activa' and s.fecha_inicio <= statement_timestamp() and s.fecha_fin > statement_timestamp()
      and (nullif(p_filtros->>'q', '') is null
           or strpos(lower(s.titulo), lower(p_filtros->>'q')) > 0
           or strpos(lower(coalesce(s.descripcion, '')), lower(p_filtros->>'q')) > 0)
      and (nullif(p_filtros->>'categoria', '') is null or s.id_categoria = (p_filtros->>'categoria')::bigint)
      and (nullif(p_filtros->>'estado', '') is null or s.estado_articulo = p_filtros->>'estado')
      and (nullif(p_filtros->>'ubicacion', '') is null
           or strpos(lower(coalesce(s.ubicacion, '')), lower(p_filtros->>'ubicacion')) > 0)
      and (nullif(p_filtros->>'desde', '') is null or s.fecha_inicio >= ((p_filtros->>'desde')::date::timestamp at time zone 'America/La_Paz'))
      and (nullif(p_filtros->>'hasta', '') is null or s.fecha_inicio < (((p_filtros->>'hasta')::date + 1)::timestamp at time zone 'America/La_Paz'))
      and (nullif(p_filtros->>'min', '') is null or s.monto_inicial >= (p_filtros->>'min')::numeric)
      and (nullif(p_filtros->>'max', '') is null or s.monto_inicial <= (p_filtros->>'max')::numeric);
$$;

create or replace function listar_categorias()
returns setof categoria language sql stable as $$
    select * from categoria order by nombre_categoria;
$$;

-- Usuarios: Laravel calcula el hash antes de llamar a estas funciones.
create or replace function crear_usuario(p_datos jsonb) returns usuario language plpgsql as $$
declare resultado usuario;
begin
    if length(trim(coalesce(p_datos->>'nombre',''))) = 0 or coalesce(p_datos->>'contrasena','') !~ '^\$2[ayb]\$' then
        raise exception 'Nombre y contraseña cifrada son obligatorios.';
    end if;
    insert into usuario(nombre,correo,contrasena)
    values(trim(p_datos->>'nombre'), lower(trim(p_datos->>'correo')), p_datos->>'contrasena') returning * into resultado;
    return resultado;
end;
$$;

create or replace function actualizar_usuario(p_actor bigint, p_datos jsonb) returns usuario language plpgsql as $$
declare resultado usuario;
begin
    if p_datos ? 'contrasena' and coalesce(p_datos->>'contrasena','') !~ '^\$2[ayb]\$' then
        raise exception 'La contraseña debe llegar cifrada desde Laravel.';
    end if;
    update usuario set nombre = p_datos->>'nombre', correo = lower(trim(p_datos->>'correo')),
        ciudad = p_datos->>'ciudad', telefono = p_datos->>'telefono', biografia = p_datos->>'biografia',
        contrasena = coalesce(p_datos->>'contrasena', contrasena)
    where id_usuario = p_actor returning * into resultado;
    if not found then raise exception 'Usuario no encontrado.' using errcode = 'P0002'; end if;
    return resultado;
end;
$$;

create or replace function bienvenida_usuario() returns trigger language plpgsql as $$
begin
    insert into usuario_rol(id_usuario,id_rol) select new.id_usuario,id_rol from rol where nombre_rol = 'Usuario';
    insert into notificacion(id_usuario,titulo,contenido)
    values(new.id_usuario,'¡Bienvenido a SubastaYA!','Completa tu perfil y encuentra tu próxima oportunidad.');
    return new;
end;
$$;
drop trigger if exists usuario_bienvenida on usuario;
create trigger usuario_bienvenida after insert on usuario for each row execute function bienvenida_usuario();

-- Bloqueo compartido por edición, eliminación e imágenes: evita carreras con una puja.
create or replace function exigir_subasta_propia(p_actor bigint, p_id bigint, p_editar boolean default true)
returns subasta language plpgsql as $$
declare resultado subasta;
begin
    select * into resultado from subasta where id_subasta = p_id for update;
    if not found then raise exception 'Subasta no encontrada.' using errcode = 'P0002'; end if;
    if resultado.id_usuario <> p_actor or p_actor is null then
        raise exception 'Esta subasta pertenece a otro usuario.' using errcode = '42501';
    end if;
    if exists(select 1 from puja where id_subasta = p_id) then raise exception 'Una subasta con pujas no puede modificarse ni eliminarse.'; end if;
    if p_editar and (resultado.estado_subasta <> 'Activa' or resultado.fecha_fin <= clock_timestamp()) then
        raise exception 'Solo puedes editar subastas activas que todavía no recibieron pujas.';
    end if;
    return resultado;
end;
$$;

create or replace function crear_subasta(p_actor bigint, p_datos jsonb) returns subasta language plpgsql as $$
declare resultado subasta;
begin
    insert into subasta(titulo,descripcion,ubicacion,estado_articulo,monto_inicial,fecha_inicio,fecha_fin,id_usuario,id_categoria,latitud,longitud)
    values(p_datos->>'titulo', p_datos->>'descripcion', p_datos->>'ubicacion', p_datos->>'estado_articulo',
        (p_datos->>'monto_inicial')::numeric, clock_timestamp(), (p_datos->>'fecha_fin')::timestamptz, p_actor,
        (p_datos->>'id_categoria')::bigint, (p_datos->>'latitud')::numeric, (p_datos->>'longitud')::numeric)
    returning * into resultado;
    return resultado;
end;
$$;

create or replace function actualizar_subasta(p_actor bigint, p_id bigint, p_datos jsonb) returns subasta language plpgsql as $$
declare resultado subasta;
begin
    perform exigir_subasta_propia(p_actor,p_id);
    if (p_datos->>'fecha_fin')::timestamptz <= clock_timestamp() then raise exception 'El cierre debe estar en el futuro.'; end if;
    update subasta set titulo = p_datos->>'titulo', descripcion = p_datos->>'descripcion', ubicacion = p_datos->>'ubicacion',
        estado_articulo = p_datos->>'estado_articulo', monto_inicial = (p_datos->>'monto_inicial')::numeric,
        fecha_fin = (p_datos->>'fecha_fin')::timestamptz, id_categoria = (p_datos->>'id_categoria')::bigint,
        latitud = case when p_datos ? 'latitud' then (p_datos->>'latitud')::numeric else latitud end,
        longitud = case when p_datos ? 'longitud' then (p_datos->>'longitud')::numeric else longitud end
    where id_subasta = p_id returning * into resultado;
    return resultado;
end;
$$;

create or replace function eliminar_subasta(p_actor bigint, p_id bigint) returns text[] language plpgsql as $$
declare rutas text[];
begin
    perform exigir_subasta_propia(p_actor,p_id,false);
    select coalesce(array_agg(ruta),array[]::text[]) into rutas from imagen where id_subasta = p_id;
    delete from subasta where id_subasta = p_id;
    return rutas;
end;
$$;

create or replace function listar_mis_subastas(p_actor bigint) returns setof subasta language sql stable as $$
    select * from subasta where id_usuario = p_actor;
$$;

create or replace function registrar_puja(p_actor bigint, p_id bigint, p_monto numeric) returns puja language plpgsql as $$
declare resultado puja;
begin
    if p_monto <> round(p_monto,2) then raise exception 'La oferta admite hasta dos decimales.'; end if;
    insert into puja(id_usuario,id_subasta,monto) values(p_actor,p_id,p_monto) returning * into resultado;
    return resultado;
end;
$$;

-- Las ofertas son irrevocables; no se exponen funciones para editarlas o borrarlas.
create or replace function ranking_subasta(p_id bigint, p_actor bigint)
returns table(posicion bigint,id_usuario bigint,nombre text,mejor_oferta numeric,ofertas bigint,fecha timestamptz)
language plpgsql stable as $$
begin
    if p_actor is null or not (exists(select 1 from subasta s where s.id_subasta=p_id and s.id_usuario=p_actor)
       or exists(select 1 from puja p where p.id_subasta=p_id and p.id_usuario=p_actor)) then
        raise exception 'El ranking está disponible para el autor y los participantes.' using errcode='42501';
    end if;
    return query
    with participantes as (
        select p.id_usuario,max(p.monto) as mejor,count(*) as cantidad,max(p.fecha_puja) as ultima
        from puja p where p.id_subasta=p_id group by p.id_usuario
    )
    select row_number() over(order by p.mejor desc,p.ultima,p.id_usuario),p.id_usuario,u.nombre,p.mejor,p.cantidad,p.ultima
    from participantes p join usuario u on u.id_usuario=p.id_usuario order by p.mejor desc,p.ultima,p.id_usuario;
end;
$$;

create or replace function guardar_imagen(p_actor bigint, p_subasta bigint, p_ruta text, p_tamano bigint)
returns imagen language plpgsql as $$
declare resultado imagen;
begin
    if p_subasta is not null then
        perform exigir_subasta_propia(p_actor,p_subasta);
        if (select count(*) from imagen where id_subasta=p_subasta) >= 5 then raise exception 'Máximo cinco imágenes por subasta.'; end if;
    else
        perform 1 from usuario where id_usuario=p_actor for update;
    end if;
    insert into imagen(ruta,tamano,id_usuario,id_subasta)
    values(p_ruta,p_tamano,case when p_subasta is null then p_actor else null end,p_subasta) returning * into resultado;
    return resultado;
end;
$$;

create or replace function eliminar_imagen(p_actor bigint,p_id bigint) returns text language plpgsql as $$
declare foto imagen;
begin
    select * into foto from imagen where id_imagen=p_id;
    if not found then raise exception 'Imagen no encontrada.' using errcode='P0002'; end if;
    if foto.id_subasta is not null then
        perform exigir_subasta_propia(p_actor,foto.id_subasta);
    elsif foto.id_usuario <> p_actor or p_actor is null then
        raise exception 'La imagen pertenece a otro usuario.' using errcode='42501';
    end if;
    delete from imagen where id_imagen=p_id;
    return foto.ruta;
end;
$$;

create or replace function listar_notificaciones(p_actor bigint,p_busqueda text default '',p_no_leidas boolean default false)
returns setof notificacion language sql stable as $$
    select * from notificacion where id_usuario=p_actor and (not p_no_leidas or not leido)
      and (coalesce(p_busqueda,'')='' or strpos(lower(titulo || ' ' || coalesce(contenido,'')),lower(p_busqueda))>0);
$$;

create or replace function marcar_notificacion(p_actor bigint,p_id bigint) returns void language plpgsql as $$
begin
    update notificacion set leido=true where id_notificacion=p_id and id_usuario=p_actor;
    if not found then raise exception 'Notificación no disponible.' using errcode='42501'; end if;
end;
$$;
create or replace function leer_todas_notificaciones(p_actor bigint) returns void language sql as $$
    update notificacion set leido=true where id_usuario=p_actor and not leido;
$$;
create or replace function eliminar_notificacion(p_actor bigint,p_id bigint) returns void language plpgsql as $$
begin
    delete from notificacion where id_notificacion=p_id and id_usuario=p_actor;
    if not found then raise exception 'Notificación no disponible.' using errcode='42501'; end if;
end;
$$;

create or replace function listar_resenas(p_usuario bigint)
returns table(id_resena bigint,nombre text,calificacion numeric,comentario text,fecha_creacion timestamptz)
language sql stable as $$
    select r.id_resena,u.nombre,r.calificacion,r.comentario,r.fecha_creacion
    from resenas r join usuario u on u.id_usuario=r.id_usuario_resenador
    where r.id_usuario_resenado=p_usuario order by r.fecha_creacion desc,r.id_resena desc;
$$;

-- Se conservan valoraciones fraccionarias existentes; se agrupan por la estrella más cercana.
create or replace function resumen_valoraciones(p_usuario bigint)
returns table(estrellas integer,cantidad bigint) language sql stable as $$
    select e,count(r.id_resena) from generate_series(1,5) e
    left join resenas r on r.id_usuario_resenado=p_usuario and round(r.calificacion)::integer=e
    group by e order by e;
$$;

-- Integridad y eventos
create or replace function validar_puja() returns trigger language plpgsql as $$
declare articulo subasta%rowtype; mayor numeric(12,2);
begin
    select * into articulo from subasta where id_subasta = new.id_subasta for update;
    if not found then raise exception 'La subasta no existe.'; end if;
    -- Evaluar la hora real después de obtener el bloqueo, incluso si hubo espera.
    if articulo.estado_subasta <> 'Activa' or articulo.fecha_inicio > clock_timestamp() or articulo.fecha_fin <= clock_timestamp() then
        raise exception 'La subasta no está activa.';
    end if;
    if articulo.id_usuario = new.id_usuario then raise exception 'No puedes pujar en tu propia subasta.' using errcode = '42501'; end if;
    select max(monto) into mayor from puja where id_subasta = new.id_subasta;
    if new.monto < articulo.monto_inicial or (mayor is not null and new.monto <= mayor) then
        raise exception 'La oferta debe superar la puja actual y alcanzar el monto inicial.';
    end if;
    return new;
end;
$$;
drop trigger if exists puja_validacion on puja;
create trigger puja_validacion before insert on puja for each row execute function validar_puja();

create or replace function proteger_subasta() returns trigger language plpgsql as $$
begin
    if exists (select 1 from puja where id_subasta = old.id_subasta) then
        if TG_OP = 'DELETE' then raise exception 'Una subasta con pujas no puede eliminarse.'; end if;
        if row(new.titulo,new.descripcion,new.ubicacion,new.estado_articulo,new.monto_inicial,new.fecha_inicio,new.fecha_fin,new.id_usuario,new.id_categoria,new.latitud,new.longitud)
           is distinct from row(old.titulo,old.descripcion,old.ubicacion,old.estado_articulo,old.monto_inicial,old.fecha_inicio,old.fecha_fin,old.id_usuario,old.id_categoria,old.latitud,old.longitud)
           or new.estado_subasta = 'Cancelada' then
            raise exception 'Una subasta con pujas no puede modificarse.';
        end if;
    end if;
    if TG_OP = 'DELETE' then return old; end if;
    return new;
end;
$$;
drop trigger if exists subasta_proteccion on subasta;
create trigger subasta_proteccion before update or delete on subasta for each row execute function proteger_subasta();

create or replace function notificar_publicacion() returns trigger language plpgsql as $$
begin
    insert into notificacion (id_usuario,id_subasta,titulo,contenido)
    values (new.id_usuario, new.id_subasta, 'Subasta publicada', 'Tu subasta «' || new.titulo || '» fue publicada correctamente.');
    return new;
end;
$$;
drop trigger if exists subasta_publicada on subasta;
create trigger subasta_publicada after insert on subasta for each row execute function notificar_publicacion();

create or replace function notificar_puja() returns trigger language plpgsql as $$
declare anterior bigint; propietario bigint; nombre text;
begin
    select id_usuario,titulo into propietario,nombre from subasta where id_subasta = new.id_subasta;
    select id_usuario into anterior from puja where id_subasta = new.id_subasta and id_puja <> new.id_puja order by monto desc limit 1;
    insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
        (propietario,new.id_subasta,'Nueva oferta','Recibiste una oferta de Bs ' || new.monto || ' en «' || nombre || '».');
    if anterior is not null and anterior <> new.id_usuario then
        insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
            (anterior,new.id_subasta,'Superaron tu oferta','Hay una oferta mayor en «' || nombre || '».');
    end if;
    return new;
end;
$$;
drop trigger if exists puja_notificacion on puja;
create trigger puja_notificacion after insert on puja for each row execute function notificar_puja();

create or replace function finalizar_subastas() returns integer language plpgsql as $$
declare articulo record; ganador bigint; total integer := 0;
begin
    for articulo in select * from subasta where estado_subasta = 'Activa' and fecha_fin <= clock_timestamp() for update skip locked loop
        update subasta set estado_subasta = 'Finalizada' where id_subasta = articulo.id_subasta;
        select id_usuario into ganador from puja where id_subasta = articulo.id_subasta order by monto desc limit 1;
        insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
            (articulo.id_usuario,articulo.id_subasta,'Subasta finalizada','Terminó «' || articulo.titulo || '»' || case when ganador is null then ' sin ofertas.' else ' con una oferta ganadora. Consulta el detalle para coordinar la entrega.' end);
        if ganador is not null then
            insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
                (ganador,articulo.id_subasta,'¡Ganaste una subasta!','Ganaste «' || articulo.titulo || '». Consulta el detalle para coordinar la entrega.');
        end if;
        total := total + 1;
    end loop;
    return total;
end;
$$;

