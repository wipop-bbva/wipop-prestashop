# Guía de desarrollo - módulo PrestaShop Wipöp

## Requisitos

- **Docker** y **Docker Compose**

## 1) Arrancar PrestaShop + MySQL

```bash
docker compose up -d
```

## 2) Primera instalación (auto-install) y credenciales

El contenedor de PrestaShop hace la instalación automáticamente gracias a PS_INSTALL_AUTO.
- URL del Back Office: por defecto `/admin` (pero puede que PS te pida renombrarla)

```bash
docker compose logs -f prestashop
```

### Cambiar ruta Back Office

```bash
docker compose exec prestashop bash -lc 'mv /var/www/html/admin /var/www/html/admin1234'
```

## 3) Acceso

- Front Office: `http://localhost:8081`
- Back Office: revisa los logs del contenedor `prestashop`

## 4) Instalar el módulo

El módulo se monta en docker composer:
- Host: `./wipop`
- Contenedor: `/var/www/html/modules/wipop`

Para instalarlo en PS desde el Back Office:
Back office → Módulos → Gestor de módulos → busca “Wipöp” → Instalar.

## 5) Limpiar caché

```bash
docker compose exec prestashop bash -lc 'rm -rf var/cache/*'
```

## 6) QA (Coding Standards, PHPStan, PHPCS)

Este repo usa Composer para tooling (CS Fixer, PHPCS, PHPStan), pero **no instala dependencias en `wipop/vendor/`** para evitar conflictos con el Symfony del core de PrestaShop se instalan en `tools/vendor/`

### Instalar tooling (Composer)

Con Composer en local:

```bash
cd wipop
composer install
```

Tambien lo puedes hacer en docker:

```bash
docker run --rm -v "$PWD:/app" -w /app/wipop composer:2 composer install
```


### PHP CS Fixer

```bash
cd wipop
composer lint:cs
composer fix:cs
```

### PHPStan

Para ejecutarlo en local, PHPStan necesita el core de PrestaShop para resolver clases/constantes, asi que usamos `_PS_ROOT_DIR_` apuntando al root del core.

```bash
cd wipop
_PS_ROOT_DIR_="$PWD/../prestashop-core" php ../tools/vendor/bin/phpstan --configuration=tests/phpstan/phpstan.neon analyse .
```

### Header Stamp (cabeceras de licencia)

```bash
cd wipop
composer header-stamp
```

### Autoindex (añadir `index.php` a subcarpetas)

```bash
cd wipop
composer autoindex
```


## 7) Soporte de Prestashop en VScode y PHPstan.

Para que intellilense y PhpStan vean el core de PrestaShop podemos crear una copia del core de PS del contenedor en local.
```bash
docker cp tu_container:/var/www/html ./prestashop-core
```

## 8) Empaquetado

Para distribuir el módulo creamos un zip con la carpeta `wipop/`. Excluye `wipop/vendor/` del zip final (o ejecutando `composer install --no-dev` antes de empaquetar).
