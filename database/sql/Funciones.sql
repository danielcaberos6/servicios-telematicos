-- SUBASTAYA · Operaciones PostgreSQL
-- Ejecutar después de Script.sql. Laravel usa consultas parametrizadas.
-- p_actor siempre procede de la sesión autenticada de Laravel, nunca del formulario.
-- Las funciones usan SECURITY INVOKER; las credenciales de BD son exclusivas del servidor.
--
-- El catálogo, las pujas, la edición de subastas, las imágenes de subastas y la
-- finalización viven SOLO en PublicacionSubastas.sql (catalogo_subastas, validar_puja,
-- notificar_publicacion, exigir_subasta_propia, actualizar_subasta, guardar_imagen,
-- eliminar_imagen, finalizar_subastas y sus triggers). No las redefinas aquí: este archivo
-- se reinstala antes que aquel y una copia antigua dejaría de aplicar la revisión.


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
    insert into usuario(nombre,nombres,apellidos,correo,contrasena)
    values(trim(p_datos->>'nombre'), p_datos->>'nombres', p_datos->>'apellidos', lower(trim(p_datos->>'correo')), p_datos->>'contrasena') returning * into resultado;
    return resultado;
end;
$$;

create or replace function actualizar_usuario(p_actor bigint, p_datos jsonb) returns usuario language plpgsql as $$
declare resultado usuario;
begin
    if p_datos ? 'contrasena' and coalesce(p_datos->>'contrasena','') !~ '^\$2[ayb]\$' then
        raise exception 'La contraseña debe llegar cifrada desde Laravel.';
    end if;
    -- Una clave ausente conserva el valor actual; una clave con null lo borra.
    update usuario set
        nombre = case when p_datos ? 'nombre' then p_datos->>'nombre' else nombre end,
        nombres = case when p_datos ? 'nombres' then p_datos->>'nombres' else nombres end,
        apellidos = case when p_datos ? 'apellidos' then p_datos->>'apellidos' else apellidos end,
        correo = case when p_datos ? 'correo' then lower(trim(p_datos->>'correo')) else correo end,
        ciudad = case when p_datos ? 'ciudad' then p_datos->>'ciudad' else ciudad end,
        telefono = case when p_datos ? 'telefono' then p_datos->>'telefono' else telefono end,
        biografia = case when p_datos ? 'biografia' then p_datos->>'biografia' else biografia end,
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

create or replace function crear_subasta(p_actor bigint, p_datos jsonb) returns subasta language plpgsql as $$
declare resultado subasta;
begin
    perform exigir_normas(p_actor);
    if (p_datos->>'monto_inicial')::numeric <= 0 or mod((p_datos->>'monto_inicial')::numeric,5) <> 0 then
        raise exception 'El monto inicial debe ser un múltiplo entero positivo de Bs 5.';
    end if;
    insert into subasta(titulo,descripcion,ubicacion,estado_articulo,monto_inicial,fecha_inicio,fecha_fin,id_usuario,id_categoria,latitud,longitud,incremento_puja,modelo_puja,requiere_fondos)
    values(p_datos->>'titulo', p_datos->>'descripcion', p_datos->>'ubicacion', p_datos->>'estado_articulo',
        (p_datos->>'monto_inicial')::numeric, clock_timestamp(), (p_datos->>'fecha_fin')::timestamptz, p_actor,
        (p_datos->>'id_categoria')::bigint, (p_datos->>'latitud')::numeric, (p_datos->>'longitud')::numeric,
        coalesce((p_datos->>'incremento_puja')::integer,5),2,true)
    returning * into resultado;
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
    perform exigir_normas(p_actor);
    if exists(select 1 from subasta where id_subasta=p_id and modelo_puja=2) and p_monto <> trunc(p_monto) then
        raise exception 'La oferta debe ser un monto entero, sin centavos.';
    end if;
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
create or replace function proteger_subasta() returns trigger language plpgsql as $$
begin
    if TG_OP = 'UPDATE' then
        if new.requiere_fondos is distinct from old.requiere_fondos then raise exception 'La condición de fondos no puede modificarse.'; end if;
        if old.requiere_fondos and old.estado_subasta in ('Finalizada','Cancelada') and new.estado_subasta<>old.estado_subasta then
            raise exception 'La subasta ya terminó. Los fondos se resuelven mediante la gestión de entrega.';
        end if;
        if new.modelo_puja <> old.modelo_puja then raise exception 'El modelo de puja de esta subasta no puede cambiarse.'; end if;
        if old.modelo_puja=2 and (old.condiciones_fijadas or old.estado_revision='Aceptada') and
            row(new.monto_inicial,new.incremento_puja) is distinct from row(old.monto_inicial,old.incremento_puja) then
            raise exception 'El monto inicial y el incremento no pueden cambiar después de la primera aprobación.';
        end if;
        new.condiciones_fijadas := old.condiciones_fijadas or (new.modelo_puja=2 and new.estado_revision='Aceptada');
    end if;
    if exists (select 1 from puja where id_subasta = old.id_subasta) then
        if TG_OP = 'DELETE' then raise exception 'Una subasta con pujas no puede eliminarse.'; end if;
        if row(new.titulo,new.descripcion,new.ubicacion,new.estado_articulo,new.monto_inicial,new.incremento_puja,new.fecha_inicio,new.fecha_fin,new.id_usuario,new.id_categoria,new.latitud,new.longitud)
           is distinct from row(old.titulo,old.descripcion,old.ubicacion,old.estado_articulo,old.monto_inicial,old.incremento_puja,old.fecha_inicio,old.fecha_fin,old.id_usuario,old.id_categoria,old.latitud,old.longitud)
           or (new.estado_subasta = 'Cancelada' and not old.requiere_fondos) then
            raise exception 'Una subasta con pujas no puede modificarse.';
        end if;
    end if;
    if TG_OP = 'DELETE' then return old; end if;
    return new;
end;
$$;
drop trigger if exists subasta_proteccion on subasta;
create trigger subasta_proteccion before update or delete on subasta for each row execute function proteger_subasta();

create or replace function notificar_puja() returns trigger language plpgsql as $$
declare anterior bigint; nombre text;
begin
    select titulo into nombre from subasta where id_subasta = new.id_subasta;
    select id_usuario into anterior from puja where id_subasta = new.id_subasta and id_puja <> new.id_puja order by monto desc limit 1;
    -- El vendedor consulta las pujas en el detalle y recibe un resumen al cierre.
    if anterior is not null and anterior <> new.id_usuario then
        insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
            (anterior,new.id_subasta,'Superaron tu oferta','Hay una oferta mayor en «' || nombre || '».');
    end if;
    return new;
end;
$$;
drop trigger if exists puja_notificacion on puja;
create trigger puja_notificacion after insert on puja for each row execute function notificar_puja();

-- BEGIN REPORTES ADMINISTRATIVOS
-- Los acumulados y los límites de fecha se interpretan en hora de Bolivia.
create or replace function exigir_administrador(p_actor bigint) returns void language plpgsql stable as $$
begin
    if p_actor is null or not exists (
        select 1 from usuario_rol ur join rol r on r.id_rol=ur.id_rol join usuario u on u.id_usuario=ur.id_usuario
        where ur.id_usuario=p_actor and u.activo and r.nombre_rol='Administrador'
    ) then raise exception 'Solo los administradores activos pueden acceder a esta sección.' using errcode='42501'; end if;
end;
$$;

create or replace function validar_periodo_reportes(p_filtros jsonb) returns void language plpgsql stable as $$
declare inicio date := (p_filtros->>'desde')::date; fin date := (p_filtros->>'hasta')::date;
begin
    if inicio is null or fin is null or inicio > fin then
        raise exception 'La fecha inicial no puede ser posterior a la fecha final.';
    end if;
    if inicio < date '1900-01-01' or fin > date '2100-12-31' then
        raise exception 'El periodo debe estar entre 1900 y 2100.';
    end if;
end;
$$;

create or replace function ciudades_reporte(p_actor bigint) returns table(ciudad text) language plpgsql stable as $$
begin
    perform exigir_administrador(p_actor);
    return query select distinct coalesce(nullif(trim(u.ciudad),''),'Sin ciudad') from usuario u order by 1;
end;
$$;

create or replace function reporte_subastas(p_actor bigint,p_filtros jsonb) returns jsonb language plpgsql stable as $$
declare
    d_inicio date; d_fin date; t_inicio timestamptz; t_fin timestamptz;
    pagina integer := greatest(1,coalesce((p_filtros->>'page')::integer,1)); resultado jsonb;
begin
    perform exigir_administrador(p_actor);
    perform validar_periodo_reportes(p_filtros);
    d_inicio := (p_filtros->>'desde')::date; d_fin := (p_filtros->>'hasta')::date;
    t_inicio := d_inicio::timestamp at time zone 'America/La_Paz';
    t_fin := (d_fin+1)::timestamp at time zone 'America/La_Paz';
    with base as (
        select s.id_subasta,s.titulo,u.nombre as vendedor,coalesce(c.nombre_categoria,'Sin categoría') as categoria,
            s.id_categoria,s.monto_inicial,s.fecha_inicio,s.fecha_fin,s.ubicacion,
            case when s.estado_subasta='Activa' and s.fecha_fin <= statement_timestamp() then 'Finalizada' else s.estado_subasta end as estado,
            (select count(*) from puja p where p.id_subasta=s.id_subasta) as pujas
        from subasta s join usuario u on u.id_usuario=s.id_usuario left join categoria c on c.id_categoria=s.id_categoria
        -- Las solicitudes En revisión o Rechazadas no son publicaciones.
        where s.estado_revision = 'Aceptada' and s.fecha_inicio >= t_inicio and s.fecha_inicio < t_fin
    ), filtradas as materialized (
        select * from base b where
            (nullif(p_filtros->>'categoria','') is null or b.id_categoria=(p_filtros->>'categoria')::bigint)
            and (nullif(p_filtros->>'estado','') is null or b.estado=p_filtros->>'estado')
            and (nullif(p_filtros->>'ubicacion','') is null or strpos(lower(coalesce(b.ubicacion,'')),lower(p_filtros->>'ubicacion'))>0)
            and (nullif(p_filtros->>'q','') is null or strpos(lower(b.titulo || ' ' || b.vendedor),lower(p_filtros->>'q'))>0)
    ), meses as (
        select m, (select count(*) from filtradas s where date_trunc('month',s.fecha_inicio at time zone 'America/La_Paz')=m) as cantidad
        from generate_series(date_trunc('month',d_inicio::timestamp),date_trunc('month',d_fin::timestamp),interval '1 month') m
    ), categorias as (
        select categoria as etiqueta,count(*) as cantidad from filtradas group by categoria
    ), paginadas as (
        select * from filtradas order by fecha_fin desc,id_subasta desc limit 15 offset (pagina-1)*15
    ), detalle as (
        select d.*,g.id_ganador,g.ganador,g.monto_ganador
        from paginadas d left join lateral (
            select u.id_usuario as id_ganador,u.nombre as ganador,p.monto as monto_ganador
            from puja p join usuario u on u.id_usuario=p.id_usuario
            where p.id_subasta=d.id_subasta and d.estado='Finalizada'
            order by p.monto desc,p.id_puja asc limit 1
        ) g on true
    )
    select jsonb_build_object(
        'indicadores',jsonb_build_object('total',count(*),'activas',count(*) filter(where estado='Activa'),
            'finalizadas',count(*) filter(where estado='Finalizada'),'canceladas',count(*) filter(where estado='Cancelada')),
        'categorias',coalesce((select jsonb_agg(to_jsonb(c) order by cantidad desc,etiqueta) from categorias c),'[]'::jsonb),
        'meses',(select jsonb_agg(jsonb_build_object('mes',to_char(m,'YYYY-MM'),'cantidad',cantidad) order by m) from meses),
        'detalle',coalesce((select jsonb_agg(to_jsonb(d) order by fecha_fin desc,id_subasta desc) from detalle d),'[]'::jsonb),
        'total',count(*)
    ) into resultado from filtradas;
    return resultado;
end;
$$;

create or replace function reporte_usuarios(p_actor bigint,p_filtros jsonb) returns jsonb language plpgsql stable as $$
declare
    d_inicio date; d_fin date; t_inicio timestamptz; t_fin timestamptz; t_anterior timestamptz;
    pagina integer := greatest(1,coalesce((p_filtros->>'page')::integer,1)); resultado jsonb;
begin
    perform exigir_administrador(p_actor);
    perform validar_periodo_reportes(p_filtros);
    d_inicio := (p_filtros->>'desde')::date; d_fin := (p_filtros->>'hasta')::date;
    t_inicio := d_inicio::timestamp at time zone 'America/La_Paz';
    t_fin := (d_fin+1)::timestamp at time zone 'America/La_Paz';
    t_anterior := (d_inicio-(d_fin-d_inicio+1))::timestamp at time zone 'America/La_Paz';
    with base as (
        select u.id_usuario,u.nombre,coalesce(nullif(trim(u.ciudad),''),'Sin ciudad') as ciudad,u.fecha_registro,
            (select count(*) from subasta s where s.id_usuario=u.id_usuario and s.estado_revision='Aceptada' and s.fecha_inicio<t_fin) as publicaciones,
            (select count(*) from puja p where p.id_usuario=u.id_usuario and p.fecha_puja<t_fin) as pujas,
            (select avg(r.calificacion) from resenas r where r.id_usuario_resenado=u.id_usuario and r.fecha_creacion<t_fin) as valoracion
        from usuario u where u.fecha_registro<t_fin
    ), filtrados as materialized (
        select * from base b where
            (nullif(p_filtros->>'ciudad','') is null or b.ciudad=p_filtros->>'ciudad')
            and (nullif(p_filtros->>'q','') is null or strpos(lower(b.nombre),lower(p_filtros->>'q'))>0)
            and (nullif(p_filtros->>'valoracion','') is null or b.valoracion >= (p_filtros->>'valoracion')::numeric)
            and (nullif(p_filtros->>'actividad','') is null
                or (p_filtros->>'actividad'='publicadores' and b.publicaciones>0)
                or (p_filtros->>'actividad'='postores' and b.pujas>0)
                or (p_filtros->>'actividad'='sin_actividad' and b.publicaciones+b.pujas=0))
    ), resenas_filtradas as materialized (
        select r.* from resenas r join filtrados u on u.id_usuario=r.id_usuario_resenado where r.fecha_creacion<t_fin
    ), resumen as (
        select count(*) as total,count(*) filter(where fecha_registro>=t_inicio) as nuevos,
            count(*) filter(where fecha_registro>=t_anterior and fecha_registro<t_inicio) as anteriores,
            count(*) filter(where publicaciones>0) as publicadores from filtrados
    ), meses as (
        select m,(select count(*) from filtrados u where u.fecha_registro < least(t_fin,(m+interval '1 month') at time zone 'America/La_Paz')) as cantidad
        from generate_series(date_trunc('month',d_inicio::timestamp),date_trunc('month',d_fin::timestamp),interval '1 month') m
    ), ciudades as (
        select ciudad as etiqueta,count(*) as cantidad from filtrados group by ciudad
    ), valoraciones as (
        select e as estrellas,(select count(*) from resenas_filtradas r where round(r.calificacion)::integer=e) as cantidad from generate_series(1,5) e
    ), detalle as (
        select * from filtrados order by publicaciones+pujas desc,nombre,id_usuario limit 15 offset (pagina-1)*15
    )
    select jsonb_build_object(
        'indicadores',jsonb_build_object('total',total,'nuevos',nuevos,'anteriores',anteriores,'publicadores',publicadores,
            'crecimiento',case when anteriores>0 then round(100.0*(nuevos-anteriores)/anteriores,1) when nuevos=0 then 0 else null end,
            'valoracion',(select round(avg(calificacion),2) from resenas_filtradas)),
        'meses',(select jsonb_agg(jsonb_build_object('mes',to_char(m,'YYYY-MM'),'cantidad',cantidad) order by m) from meses),
        'ciudades',coalesce((select jsonb_agg(to_jsonb(c) order by cantidad desc,etiqueta) from ciudades c),'[]'::jsonb),
        'valoraciones',(select jsonb_agg(to_jsonb(v) order by estrellas) from valoraciones v),
        'detalle',coalesce((select jsonb_agg(to_jsonb(d) order by publicaciones+pujas desc,nombre,id_usuario) from detalle d),'[]'::jsonb),
        'total',total
    ) into resultado from resumen;
    return resultado;
end;
$$;
-- END REPORTES ADMINISTRATIVOS
