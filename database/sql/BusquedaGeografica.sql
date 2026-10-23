-- SUBASTAYA · Búsqueda geográfica del catálogo
-- Solo funciones (create or replace): puede reinstalarse sin tocar datos.
-- Redefine catalogo_subastas de Funciones.sql con el filtro por radio.

-- Distancia ortodrómica (fórmula de haversine) en kilómetros sobre una esfera de 6371 km.
create or replace function distancia_km(p_lat1 numeric, p_lng1 numeric, p_lat2 numeric, p_lng2 numeric)
returns double precision language sql immutable strict parallel safe as $$
    select 2 * 6371.0 * asin(least(1.0, sqrt(
        power(sin(radians((p_lat2 - p_lat1)::double precision) / 2), 2)
        + cos(radians(p_lat1::double precision)) * cos(radians(p_lat2::double precision))
          * power(sin(radians((p_lng2 - p_lng1)::double precision) / 2), 2)
    )));
$$;

-- Catálogo público: solo publicaciones activas en su periodo de vigencia.
-- Con lat, lng y radio (km) se excluyen las publicaciones sin coordenadas y las
-- que quedan fuera del círculo; sin radio vuelven a ser elegibles.
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
      and (nullif(p_filtros->>'max', '') is null or s.monto_inicial <= (p_filtros->>'max')::numeric)
      and (nullif(p_filtros->>'radio', '') is null
           or (s.latitud is not null and s.longitud is not null
               and distancia_km((p_filtros->>'lat')::numeric, (p_filtros->>'lng')::numeric, s.latitud, s.longitud)
                   <= (p_filtros->>'radio')::numeric));
$$;
