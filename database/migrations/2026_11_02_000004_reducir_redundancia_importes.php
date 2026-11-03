<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- Detener la migración antes de descartar un valor derivado si no coincide.
            do $$ begin
                if exists(select 1 from operacion_subasta
                    where neto <> case when con_fondos then importe-comision else 0 end) then
                    raise exception 'Hay importes netos inconsistentes. Revisarlos antes de migrar.';
                end if;
            end $$;
            alter table operacion_subasta drop column neto;
            alter table operacion_subasta add constraint operacion_comision_coherente
                check(comision<=importe and (con_fondos or comision=0));

            -- La reserva se prepara en el BEFORE INSERT de la puja: validar al confirmar.
            alter table puja add constraint puja_datos_reserva_unicos
                unique(id_puja,id_subasta,id_usuario,monto);
            alter table reserva_fondos add constraint reserva_coincide_con_puja
                foreign key(id_puja,id_subasta,id_usuario,monto)
                references puja(id_puja,id_subasta,id_usuario,monto)
                deferrable initially deferred;
            SQL);
        foreach (['Operaciones.sql', 'ReportesEconomicos.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Conservar la integridad financiera y el historial al retroceder código.
    }
};
