-- Los filtros de ventas se aplican al cierre; los de movimientos a su registro.
create or replace function reporte_ventas(p_actor bigint,p_filtros jsonb) returns jsonb language plpgsql stable as $$
declare inicio timestamptz; fin timestamptz; pagina integer; resultado jsonb;
begin
    perform exigir_administrador(p_actor);
    perform validar_periodo_reportes(p_filtros);
    if nullif(p_filtros->>'estado','') is not null and p_filtros->>'estado' not in ('Pendiente','En revisión','Completada','Reembolsada') then raise exception 'Estado de operación no válido.'; end if;
    pagina:=coalesce((p_filtros->>'page')::integer,1);
    if pagina not between 1 and 100000 then raise exception 'Página no válida.'; end if;
    inicio:=(p_filtros->>'desde')::date::timestamp at time zone 'America/La_Paz';
    fin:=((p_filtros->>'hasta')::date+1)::timestamp at time zone 'America/La_Paz';
    with ordenes as materialized (
        select o.id_operacion,o.id_subasta,s.titulo,c.nombre as comprador,v.nombre as vendedor,
            o.importe,o.comision as comision_prevista,o.neto as neto_previsto,o.estado,s.fecha_fin,
            coalesce((select r.monto from reserva_fondos r where r.id_subasta=o.id_subasta and r.id_usuario=o.id_comprador and r.estado='Retenida'),0) as retenido,
            coalesce((select sum(m.importe) from movimiento_fondos m where m.tipo='Pago' and m.referencia='operacion:'||o.id_operacion and m.id_usuario=o.id_comprador and m.id_subasta=o.id_subasta),0) as total_liquidado,
            coalesce((select sum(m.importe) from movimiento_fondos m where m.tipo='Liquidación' and m.referencia='operacion:'||o.id_operacion and m.id_usuario=o.id_vendedor and m.id_subasta=o.id_subasta),0) as neto_liquidado,
            coalesce((select co.importe from comision_operacion co where co.id_operacion=o.id_operacion),0) as comision_registrada,
            coalesce((select sum(m.importe) from movimiento_fondos m where m.tipo='Devolución' and m.referencia='operacion:'||o.id_operacion and m.id_usuario=o.id_comprador and m.id_subasta=o.id_subasta),0) as devuelto
        from operacion_subasta o join subasta s using(id_subasta)
            join usuario c on c.id_usuario=o.id_comprador join usuario v on v.id_usuario=o.id_vendedor
        where o.con_fondos and s.requiere_fondos and s.fecha_fin>=inicio and s.fecha_fin<fin
            and (nullif(p_filtros->>'estado','') is null or o.estado=p_filtros->>'estado')
            and (nullif(p_filtros->>'q','') is null or strpos(lower(s.titulo||' '||c.nombre||' '||v.nombre),lower(p_filtros->>'q'))>0)
    ), pagina_ordenes as (
        select * from ordenes order by fecha_fin desc,id_operacion desc limit 15 offset (pagina-1)*15
    )
    select jsonb_build_object('total',count(*),
        'indicadores',jsonb_build_object('ordenes',count(*),'retenido',coalesce(sum(retenido),0),
            'total_liquidado',coalesce(sum(total_liquidado),0),'neto_liquidado',coalesce(sum(neto_liquidado),0),
            'comisiones',coalesce(sum(comision_registrada),0),'devuelto',coalesce(sum(devuelto),0)),
        'detalle',coalesce((select jsonb_agg(to_jsonb(p) order by fecha_fin desc,id_operacion desc) from pagina_ordenes p),'[]'::jsonb))
        into resultado from ordenes;
    return resultado;
end;
$$;

create or replace function reporte_movimientos(p_actor bigint,p_filtros jsonb) returns jsonb language plpgsql stable as $$
declare inicio timestamptz; fin timestamptz; pagina integer; resultado jsonb;
begin
    perform exigir_administrador(p_actor);
    perform validar_periodo_reportes(p_filtros);
    if nullif(p_filtros->>'tipo_movimiento','') is not null and p_filtros->>'tipo_movimiento' not in ('Recarga','Reserva','Liberación','Devolución','Liquidación','Pago') then raise exception 'Tipo de movimiento no válido.'; end if;
    pagina:=coalesce((p_filtros->>'page')::integer,1);
    if pagina not between 1 and 100000 then raise exception 'Página no válida.'; end if;
    inicio:=(p_filtros->>'desde')::date::timestamp at time zone 'America/La_Paz';
    fin:=((p_filtros->>'hasta')::date+1)::timestamp at time zone 'America/La_Paz';
    with movimientos as materialized (
        select m.id_movimiento,m.id_usuario,u.nombre as titular,m.tipo,m.importe,m.disponible,m.reservado,m.referencia,
            m.id_subasta,s.titulo,m.fecha_movimiento,'Registrado'::text as estado
        from movimiento_fondos m join usuario u using(id_usuario) left join subasta s using(id_subasta)
        where m.fecha_movimiento>=inicio and m.fecha_movimiento<fin
            and (nullif(p_filtros->>'subasta','') is null or m.id_subasta=(p_filtros->>'subasta')::bigint)
            and (nullif(p_filtros->>'operacion','') is null or m.referencia='operacion:'||(p_filtros->>'operacion'))
            and (nullif(p_filtros->>'tipo_movimiento','') is null or m.tipo=p_filtros->>'tipo_movimiento')
            and (nullif(p_filtros->>'usuario','') is null or m.id_usuario=(p_filtros->>'usuario')::bigint)
            and (nullif(p_filtros->>'q','') is null or strpos(lower(m.referencia||' '||u.nombre||' '||coalesce(s.titulo,'')),lower(p_filtros->>'q'))>0)
    ), pagina_movimientos as (
        select * from movimientos order by fecha_movimiento desc,id_movimiento desc limit 15 offset (pagina-1)*15
    )
    select jsonb_build_object('total',count(*),
        'indicadores',jsonb_build_object('movimientos',count(*),'recargado',coalesce(sum(importe) filter(where tipo='Recarga'),0),
            'pagado',coalesce(sum(importe) filter(where tipo='Pago'),0),'devuelto',coalesce(sum(importe) filter(where tipo='Devolución'),0)),
        'detalle',coalesce((select jsonb_agg(to_jsonb(p) order by fecha_movimiento desc,id_movimiento desc) from pagina_movimientos p),'[]'::jsonb))
        into resultado from movimientos;
    return resultado;
end;
$$;

create or replace function detalle_movimiento_fondos(p_actor bigint,p_movimiento bigint) returns jsonb language plpgsql stable as $$
declare resultado jsonb;
begin
    perform exigir_administrador(p_actor);
    select jsonb_build_object('id_movimiento',m.id_movimiento,'id_usuario',m.id_usuario,'titular',u.nombre,
        'tipo',m.tipo,'importe',m.importe,'disponible',m.disponible,'reservado',m.reservado,'referencia',m.referencia,
        'fecha_movimiento',m.fecha_movimiento,'estado','Registrado','id_subasta',m.id_subasta,'titulo',s.titulo,
        'id_recarga',r.id_recarga,'estado_recarga',r.estado,'fecha_recarga',r.fecha_creacion,'fecha_resolucion_recarga',r.fecha_resolucion,
        'id_operacion',o.id_operacion,'estado_operacion',o.estado,'comision_prevista',o.comision,'neto_previsto',o.neto,
        'fecha_resolucion_operacion',o.fecha_resolucion,'estado_reserva_actual',rf.estado,'monto_reserva_actual',rf.monto,
        'id_puja_actual',rf.id_puja)
    into resultado
    from movimiento_fondos m join usuario u using(id_usuario) left join subasta s using(id_subasta)
        left join recarga r on m.referencia='recarga:'||r.id_recarga::text and r.id_usuario=m.id_usuario
        left join operacion_subasta o on o.id_subasta=m.id_subasta
        left join reserva_fondos rf on rf.id_subasta=m.id_subasta and rf.id_usuario=m.id_usuario
    where m.id_movimiento=p_movimiento;
    if resultado is null then raise exception 'Movimiento no encontrado.' using errcode='P0002'; end if;
    return resultado;
end;
$$;
