create or replace function identidad_exigir_foto() returns trigger language plpgsql as $$
begin
    if not exists(select 1 from imagen where id_imagen=new.id_imagen_perfil and id_usuario=new.id_usuario) then
        raise exception 'Para solicitar la verificación de identidad, primero sube y guarda una foto de perfil donde se vea claramente tu rostro.';
    end if;
    return new;
end;
$$;

create trigger verificacion_exigir_foto before insert on verificacion_identidad
    for each row execute function identidad_exigir_foto();
