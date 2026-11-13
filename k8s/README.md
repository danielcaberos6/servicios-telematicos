# SubastaYA en Kubernetes

Manifiestos para desplegar la imagen `danielcaberos/subastaya` en un clúster kubeadm (1 master y 2 workers, red flannel, sin balanceador ni StorageClass previos).

| Recurso | Qué hace |
|---|---|
| `postgres` (StatefulSet + Service) | PostgreSQL 16 con 5 Gi en `local-path` |
| `subastaya` (Deployment) | La aplicación. Migra y carga los datos al arrancar. Monta tres volúmenes: fotos (2 Gi), carnets de identidad (1 Gi) y adjuntos del chat (2 Gi) |
| `subastaya` (Service NodePort) | Acceso desde la red en el puerto **30080** de cualquier nodo |
| `scheduler` (Deployment) | Cierre de subastas, recordatorios y revisión de entregas cada minuto |
| `subastaya-config` / `subastaya-secret` | Configuración (`config.env`) y secretos (`secret.env`) |

La aplicación y el scheduler tienen **una sola réplica**. El arranque aplica migraciones, los volúmenes de archivos son `ReadWriteOnce` y dos schedulers repetirían las tareas.

## 1. Instalar el StorageClass (una vez por clúster)

kubeadm no trae aprovisionador de volúmenes. Sin él, los PVC quedan en `Pending`.

```sh
kubectl apply -f https://raw.githubusercontent.com/rancher/local-path-provisioner/v0.0.35/deploy/local-path-storage.yaml
kubectl -n local-path-storage rollout status deploy/local-path-provisioner
kubectl get storageclass
```

Los datos quedan en `/opt/local-path-provisioner` del worker donde arrancó cada pod. A partir de ahí, ese pod siempre vuelve al mismo nodo; si el nodo se apaga, el pod no puede moverse a otro.

## 2. Preparar los archivos en el master

```sh
git clone https://github.com/danielcaberos6/servicios-telematicos.git
cd servicios-telematicos
printf 'APP_KEY=base64:%s\nDB_PASSWORD=%s\n' "$(openssl rand -base64 32)" "$(openssl rand -hex 16)" > k8s/secret.env
```

`secret.env` no se sube al repositorio. Guarda una copia: con otra `APP_KEY` se cierran las sesiones, y la contraseña de la base solo se fija la primera vez que se crea el volumen.

Revisa `k8s/config.env`:
- `APP_URL`: la IP de un nodo con el puerto 30080.
- `APP_ENV=local` crea las cuentas de demostración (incluido el administrador) y las subastas de ejemplo. Con `production` no se crea ninguna cuenta: añade `ADMIN_CORREO` y `ADMIN_CONTRASENA` a `secret.env` para crear el administrador al arrancar, o ejecuta `kubectl -n subastaya exec -it deploy/subastaya -c app -- php artisan subastas:crear-admin admin@tu-dominio.com`.

## 3. Desplegar

```sh
kubectl apply -k k8s/
kubectl -n subastaya rollout status deploy/subastaya --timeout=10m
kubectl -n subastaya get pods -o wide
```

Abre **http://192.168.1.8:30080**; también responde en las IP de los workers. Administrador: `admin@subastaya.test` / `Admin2026!`.

El primer arranque tarda más: descarga la imagen, migra y carga los ejemplos. Mientras tanto, `scheduler` se queda en `Init` esperando a la aplicación.

## Operación

```sh
kubectl -n subastaya logs deploy/subastaya -c app -f        # aplicación
kubectl -n subastaya logs deploy/scheduler -c scheduler -f  # tareas programadas
kubectl -n subastaya exec -it deploy/subastaya -c app -- php artisan about
```

**Nueva versión:** publica la imagen con otra etiqueta (por ejemplo `1.1`, ver el README principal), cambia `newTag` en `kustomization.yaml` y ejecuta `kubectl apply -k k8s/`. Al cambiar `config.env` o `secret.env`, el mismo `apply` reinicia los pods.

**Borrar:** `kubectl delete -k k8s/` elimina también los PVC y, con ellos, **la base, las fotos, los carnets y los adjuntos**. Para detener sin perder datos:

```sh
kubectl -n subastaya scale deploy/subastaya deploy/scheduler --replicas=0
```

## Problemas frecuentes

| Síntoma | Causa |
|---|---|
| PVC en `Pending` | Falta el paso 1 (`kubectl get storageclass` vacío) |
| `subastaya` en `Init:0/1` | PostgreSQL no está listo: `kubectl -n subastaya logs deploy/subastaya -c esperar-postgres` |
| `CrashLoopBackOff` en la app | Error al migrar: `kubectl -n subastaya logs deploy/subastaya -c app --previous` |
| `ErrImagePull` | El nodo no llega a Docker Hub o la etiqueta no existe |
| Al reaplicar, `statefulset.apps/postgres configured` | Comportamiento normal de kubectl; no reinicia PostgreSQL |
