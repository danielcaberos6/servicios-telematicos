alter table usuario add column nombres text;
alter table usuario add column apellidos text;
alter table usuario add constraint usuario_nombres_completos check (
    (nombres is null and apellidos is null) or
    (nombres is not null and apellidos is not null and length(trim(nombres)) between 1 and 100 and length(trim(apellidos)) between 1 and 100)
);

-- Mantener nombre como presentación completa para subastas, búsquedas y reportes existentes.
create or replace function componer_nombre_usuario() returns trigger language plpgsql as $$
begin
    if tg_op='UPDATE' then
        if new.nombre is distinct from old.nombre and new.nombres is not distinct from old.nombres and new.apellidos is not distinct from old.apellidos then
            new.nombres := null;
            new.apellidos := null;
        end if;
    end if;
    if new.nombres is not null and new.apellidos is not null then
        new.nombres := trim(new.nombres);
        new.apellidos := trim(new.apellidos);
        new.nombre := new.nombres || ' ' || new.apellidos;
    end if;
    return new;
end;
$$;
create trigger usuario_componer_nombre before insert or update on usuario for each row execute function componer_nombre_usuario();
