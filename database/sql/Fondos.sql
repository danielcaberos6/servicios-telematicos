-- HU22: saldos derivados del libro de movimientos, sin importes editables por pantalla.
create or replace function crear_billetera_usuario() returns trigger language plpgsql as $$
begin
    insert into billetera(id_usuario) values(new.id_usuario) on conflict do nothing;
    return new;
end;
$$;
drop trigger if exists usuario_billetera on usuario;
create trigger usuario_billetera after insert on usuario for each row execute function crear_billetera_usuario();

create or replace function saldo_billetera(p_usuario bigint)
returns table(disponible numeric,reservado numeric,total numeric) language sql stable as $$
    select coalesce(sum(m.disponible),0),coalesce(sum(m.reservado),0),coalesce(sum(m.disponible+m.reservado),0)
    from movimiento_fondos m where m.id_usuario=p_usuario;
$$;

create or replace function exigir_acceso_fondos(p_actor bigint,p_titular bigint) returns void language plpgsql stable as $$
begin
    if not exists(select 1 from usuario where id_usuario=p_actor and activo) then
        raise exception 'La cuenta no está activa.' using errcode='42501';
    end if;
    if p_actor is distinct from p_titular then perform exigir_administrador(p_actor); end if;
end;
$$;

create or replace function consultar_billetera(p_actor bigint,p_titular bigint)
returns table(disponible numeric,reservado numeric,total numeric) language plpgsql stable as $$
begin
    perform exigir_acceso_fondos(p_actor,p_titular);
    if not exists(select 1 from billetera where id_usuario=p_titular) then
        raise exception 'Billetera no encontrada.' using errcode='P0002';
    end if;
    return query select * from saldo_billetera(p_titular);
end;
$$;

create or replace function proteger_movimiento_fondos() returns trigger language plpgsql as $$
declare saldo record;
begin
    if TG_OP<>'INSERT' then raise exception 'Los movimientos registrados no se modifican ni eliminan.'; end if;
    perform 1 from billetera where id_usuario=new.id_usuario for update;
    select * into saldo from saldo_billetera(new.id_usuario);
    if saldo.disponible+new.disponible<0 or saldo.reservado+new.reservado<0 then
        raise exception 'Saldo disponible insuficiente para esta operación.';
    end if;
    if (new.tipo='Recarga' and (new.disponible<>new.importe or new.reservado<>0))
       or (new.tipo='Reserva' and (new.disponible<>-new.importe or new.reservado<>new.importe))
       or (new.tipo in ('Liberación','Devolución') and (new.disponible<>new.importe or new.reservado<>-new.importe)) then
        raise exception 'El movimiento no coincide con su importe y tipo.';
    end if;
    return new;
end;
$$;
drop trigger if exists movimiento_integridad on movimiento_fondos;
create trigger movimiento_integridad before insert or update or delete on movimiento_fondos for each row execute function proteger_movimiento_fondos();

create or replace function solicitar_recarga(p_actor bigint,p_id uuid,p_monto numeric) returns recarga language plpgsql as $$
declare resultado recarga;
begin
    perform exigir_usuario_participante(p_actor);
    perform exigir_normas(p_actor);
    if p_id is null or p_monto is null or p_monto<>trunc(p_monto) or p_monto<5 or p_monto>10000 then
        raise exception 'La recarga debe ser un monto entero entre Bs 5 y Bs 10.000.';
    end if;
    insert into recarga(id_recarga,id_usuario,monto) values(p_id,p_actor,p_monto) on conflict do nothing;
    select * into resultado from recarga where id_recarga=p_id;
    if resultado.id_usuario<>p_actor or resultado.monto<>p_monto then
        raise exception 'La referencia ya corresponde a otra solicitud de recarga.';
    end if;
    return resultado;
end;
$$;

create or replace function resolver_recarga(p_actor bigint,p_id uuid,p_resultado text) returns recarga language plpgsql as $$
declare resultado recarga;
begin
    perform exigir_usuario_participante(p_actor);
    perform exigir_normas(p_actor);
    if p_resultado is null or p_resultado not in ('Aprobada','Rechazada','Cancelada') then raise exception 'Resultado de recarga no válido.'; end if;
    select * into resultado from recarga where id_recarga=p_id and id_usuario=p_actor for update;
    if not found then raise exception 'Recarga no encontrada.' using errcode='P0002'; end if;
    if resultado.estado<>'Pendiente' then return resultado; end if;
    if p_resultado='Aprobada' then
        perform 1 from billetera where id_usuario=p_actor for update;
        insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia)
            values(p_actor,'Recarga',resultado.monto,resultado.monto,0,'recarga:'||p_id);
    end if;
    update recarga set estado=p_resultado,fecha_resolucion=clock_timestamp() where id_recarga=p_id returning * into resultado;
    return resultado;
end;
$$;

create or replace function reservar_fondos_puja(p_subasta bigint,p_usuario bigint,p_monto numeric,p_puja bigint) returns void language plpgsql as $$
declare anterior reserva_fondos%rowtype; diferencia numeric; disponible_actual numeric;
begin
    perform exigir_usuario_participante(p_usuario);
    perform exigir_normas(p_usuario);
    select * into anterior from reserva_fondos where id_subasta=p_subasta;
    -- Todas las operaciones toman primero la subasta y después las billeteras en orden de ID.
    perform id_usuario from billetera where id_usuario in (p_usuario,anterior.id_usuario) order by id_usuario for update;
    diferencia := p_monto-case when anterior.id_usuario=p_usuario and anterior.estado='Activa' then anterior.monto else 0 end;
    select disponible into disponible_actual from saldo_billetera(p_usuario);
    if disponible_actual<diferencia then raise exception 'Saldo insuficiente. Necesitas Bs % disponibles para confirmar esta oferta. Recarga tu billetera.', diferencia; end if;
    if anterior.id_usuario is not null and anterior.id_usuario<>p_usuario and anterior.estado='Activa' then
        insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia,id_subasta)
            values(anterior.id_usuario,'Liberación',anterior.monto,anterior.monto,-anterior.monto,'puja:'||p_puja,p_subasta);
    end if;
    insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia,id_subasta)
        values(p_usuario,'Reserva',diferencia,-diferencia,diferencia,'puja:'||p_puja,p_subasta);
    insert into reserva_fondos(id_subasta,id_usuario,monto,id_puja,estado)
        values(p_subasta,p_usuario,p_monto,p_puja,'Activa')
        on conflict(id_subasta) do update set id_usuario=excluded.id_usuario,monto=excluded.monto,id_puja=excluded.id_puja,
            estado='Activa',fecha_actualizacion=clock_timestamp();
end;
$$;

create or replace function sincronizar_reserva_cierre() returns trigger language plpgsql as $$
declare reserva reserva_fondos%rowtype;
begin
    if not new.requiere_fondos or new.estado_subasta is not distinct from old.estado_subasta then return new; end if;
    select * into reserva from reserva_fondos where id_subasta=new.id_subasta and estado='Activa';
    if not found then return new; end if;
    if new.estado_subasta='Finalizada' then
        update reserva_fondos set estado='Retenida',fecha_actualizacion=clock_timestamp() where id_subasta=new.id_subasta;
    elsif new.estado_subasta='Cancelada' then
        perform 1 from billetera where id_usuario=reserva.id_usuario for update;
        insert into movimiento_fondos(id_usuario,tipo,importe,disponible,reservado,referencia,id_subasta)
            values(reserva.id_usuario,'Liberación',reserva.monto,reserva.monto,-reserva.monto,'cancelacion:'||new.id_subasta,new.id_subasta);
        update reserva_fondos set estado='Liberada',fecha_actualizacion=clock_timestamp() where id_subasta=new.id_subasta;
    end if;
    return new;
end;
$$;
drop trigger if exists subasta_reserva_cierre on subasta;
create trigger subasta_reserva_cierre after update of estado_subasta on subasta for each row execute function sincronizar_reserva_cierre();
