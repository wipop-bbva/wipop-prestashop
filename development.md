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

### Tests unitarios

```bash
cd wipop
composer test:unit
```

### Validación completa

```bash
cd wipop
composer lint
composer test:unit
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

### Probar el procesador de recurrencias

Desde la raíz de una instalación PrestaShop:

```bash
php bin/console wipop:recurring:process --limit=1
```

En el entorno Docker local:

```bash
docker compose exec prestashop bash -lc 'cd /var/www/html && php bin/console wipop:recurring:process --limit=1'
```

Una salida con `Processed 0 Wipop recurring payment(s).` es correcta si no hay
renovaciones pendientes.

## 7) Soporte de Prestashop en VScode y PHPstan.

Para que intellilense y PhpStan vean el core de PrestaShop podemos crear una copia del core de PS del contenedor en local.
```bash
docker cp tu_container:/var/www/html ./prestashop-core
```

## 8) Traducciones (i18n)

Este módulo usa el sistema moderno de traducciones de PrestaShop.

Las cadenas viven en el código y se agrupan por dominio:

- `Modules.Wipop.Wipop`: textos del módulo principal `wipop.php`
- `Modules.Wipop.Admin`: configuración y back office
- `Modules.Wipop.Shop`: front office, checkout y `.tpl`

### Cómo actualizar traducciones desde Back Office

1. Arranca el entorno con `docker compose up -d`.
2. Entra al Back Office de PrestaShop.
3. Ve a `Internacional > Traducciones`.
4. En `Modificar traducciones`, elige `Traducciones de módulos instalados`, selecciona `wipop` y el idioma.
5. Edita las cadenas y guarda.
6. En `Exportar traducciones`, exporta ese mismo módulo e idioma.
7. Descomprime el ZIP dentro de `wipop/translations/`, sustituyendo los ficheros del locale correspondiente.
8. Repite para cada idioma que haya que refrescar y haz commit de los `.xlf`.

Locales que mantenemos versionados en el repo:

- `en-US`
- `es-ES`
- `ca-ES`
- `gl-ES`
- `eu-ES`

## 9) Empaquetado

Para distribuir el módulo creamos un zip con la carpeta `wipop/`.

El release de GitHub ejecuta `composer install --no-dev --optimize-autoloader`
dentro de `wipop/` y después construye `wipop.zip` desde una lista explícita de
ficheros runtime. El paquete incluye `vendor/` con dependencias de producción,
pero excluye tests, caches, tooling local y configuración de QA.
