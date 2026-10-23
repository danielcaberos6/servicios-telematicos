-- SUBASTAYA · Búsqueda geográfica del catálogo
-- Solo funciones (create or replace): puede reinstalarse sin tocar datos.
-- catalogo_subastas, que usa esta distancia para el filtro por radio, vive en PublicacionSubastas.sql.

-- Distancia ortodrómica (fórmula de haversine) en kilómetros sobre una esfera de 6371 km.
create or replace function distancia_km(p_lat1 numeric, p_lng1 numeric, p_lat2 numeric, p_lng2 numeric)
returns double precision language sql immutable strict parallel safe as $$
    select 2 * 6371.0 * asin(least(1.0, sqrt(
        power(sin(radians((p_lat2 - p_lat1)::double precision) / 2), 2)
        + cos(radians(p_lat1::double precision)) * cos(radians(p_lat2::double precision))
          * power(sin(radians((p_lng2 - p_lng1)::double precision) / 2), 2)
    )));
$$;
