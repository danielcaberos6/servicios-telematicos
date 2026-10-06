-- SUBASTAYA · Operaciones PostgreSQL
-- Ejecutar después de Script.sql. Laravel usa consultas parametrizadas.

-- Catálogo público: solo publicaciones activas en su periodo de vigencia.
create or replace function catalogo_subastas(p_filtros jsonb default '{}'::jsonb)
returns setof subasta language sql stable as $$
    select s.* from subasta s
    where s.estado_subasta = 'Activa' and s.fecha_inicio <= current_timestamp and s.fecha_fin > current_timestamp
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
