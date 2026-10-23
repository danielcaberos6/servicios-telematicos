-- SUBASTAYA · Publicación de subastas tras la revisión
-- Solo funciones y triggers (create or replace): puede reinstalarse sin tocar datos.
-- Requiere RevisionSubastas.sql y BusquedaGeografica.sql; redefine funciones de Funciones.sql,
-- por eso subastas:instalar-funciones lo ejecuta al final.

-- Catálogo público: solo publicaciones Aceptadas, activas y en su periodo de vigencia.
-- Con lat, lng y radio (km) se excluyen las publicaciones sin coordenadas y las
-- que quedan fuera del círculo; sin radio vuelven a ser elegibles.
create or replace function catalogo_subastas(p_filtros jsonb default '{}'::jsonb)
returns setof subasta language sql stable as $$
    select s.* from subasta s
    where s.estado_revision = 'Aceptada'
      and s.estado_subasta = 'Activa' and s.fecha_inicio <= statement_timestamp() and s.fecha_fin > statement_timestamp()
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

-- Solo se puja en subastas Aceptadas; En revisión o Rechazada no admiten ofertas.
create or replace function validar_puja() returns trigger language plpgsql as $$
declare articulo subasta%rowtype; mayor numeric(12,2);
begin
    select * into articulo from subasta where id_subasta = new.id_subasta for update;
    if not found then raise exception 'La subasta no existe.'; end if;
    if articulo.estado_revision <> 'Aceptada' then raise exception 'Esta subasta todavía no está publicada.'; end if;
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

-- Una solicitud que nunca se publicó no se finaliza ni genera avisos de cierre.
create or replace function finalizar_subastas() returns integer language plpgsql as $$
declare articulo record; ganador bigint; total integer := 0;
begin
    for articulo in select * from subasta where estado_subasta = 'Activa' and estado_revision = 'Aceptada'
                    and fecha_fin <= clock_timestamp() for update skip locked loop
        update subasta set estado_subasta = 'Finalizada' where id_subasta = articulo.id_subasta;
        select id_usuario into ganador from puja where id_subasta = articulo.id_subasta order by monto desc limit 1;
        insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
            (articulo.id_usuario,articulo.id_subasta,'Subasta finalizada','Terminó «' || articulo.titulo || '»' || case when ganador is null then ' sin ofertas.' else ' con una oferta ganadora. Consulta el detalle para coordinar la entrega.' end);
        if ganador is not null then
            insert into notificacion (id_usuario,id_subasta,titulo,contenido) values
                (ganador,articulo.id_subasta,'¡Ganaste una subasta!','Ganaste «' || articulo.titulo || '». Consulta el detalle para coordinar la entrega.');
            insert into notificacion (id_usuario,id_subasta,titulo,contenido)
            select distinct p.id_usuario,articulo.id_subasta,'Subasta finalizada','Terminó «' || articulo.titulo || '». Tu oferta no fue la ganadora.'
            from puja p where p.id_subasta = articulo.id_subasta and p.id_usuario <> ganador;
        end if;
        total := total + 1;
    end loop;
    return total;
end;
$$;

-- Al registrar una solicitud se avisa al vendedor y a cada Administrador activo; ambos
-- avisos enlazan la subasta. Las publicaciones insertadas ya aceptadas conservan su aviso.
create or replace function notificar_publicacion() returns trigger language plpgsql as $$
begin
    if new.estado_revision = 'En revisión' then
        insert into notificacion (id_usuario,id_subasta,titulo,contenido)
        values (new.id_usuario, new.id_subasta, 'Solicitud en revisión',
            'Enviaste «' || new.titulo || '» a revisión. Te avisaremos cuando un administrador tome una decisión.');
        insert into notificacion (id_usuario,id_subasta,titulo,contenido)
        select distinct u.id_usuario, new.id_subasta, 'Nueva solicitud de publicación',
            'La subasta «' || new.titulo || '» espera tu revisión.'
        from usuario u join usuario_rol ur on ur.id_usuario=u.id_usuario join rol r on r.id_rol=ur.id_rol
        where u.activo and r.nombre_rol='Administrador' and u.id_usuario<>new.id_usuario;
    elsif new.estado_revision = 'Aceptada' then
        insert into notificacion (id_usuario,id_subasta,titulo,contenido)
        values (new.id_usuario, new.id_subasta, 'Subasta publicada', 'Tu subasta «' || new.titulo || '» fue publicada correctamente.');
    end if;
    return new;
end;
$$;
