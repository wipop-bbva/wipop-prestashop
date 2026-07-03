# Wipöp para PrestaShop (`wipop`)

Módulo de pago para PrestaShop que integra la pasarela Wipöp y permite a tu
tienda PrestaShop aceptar pagos a través de Wipöp.

Incluye pagos con tarjeta y Bizum, configuración
compartida del comercio, sincronización por webhook, reembolsos desde la pantalla
de pedido de PrestaShop, tokenización de tarjetas, preautorizaciones opcionales y
soporte de pagos recurrentes personalizados.

## Funcionalidades

- **Pagos con tarjeta**
  - Experiencia de checkout redirigida mediante enlaces de pago de Wipöp.
  - Tokenización COF integrada para guardar tarjetas y habilitar cargos one-click
    o pagos recurrentes personalizados.
  - Soporte de preautorizaciones con acciones de pedido para capturar o anular.
  - Reembolsos totales y parciales desde la pantalla de pedido de PrestaShop.
- **Pagos Bizum**
  - Opción de pago Bizum específica en el checkout.
- **Sincronización del comercio y herramientas de administración**
  - Página central de configuración en el Back Office de PrestaShop con entorno,
    credenciales del comercio, terminal, modo de captura, credenciales de webhook
    y métodos de pago habilitados.
  - Verificación de credenciales que comprueba los datos del comercio y los
    métodos de pago disponibles en Wipöp.
  - Configuración de pago recurrente en productos virtuales, con cargos MIT
    mensuales/anuales que reutilizan tokens COF guardados.
- **Actualización de estados de pedido**
  - Endpoint de webhook mostrado en la página de configuración del módulo, usado
    para sincronizar confirmaciones de pago, fallos, reembolsos, capturas,
    anulaciones y eventos de verificación del webhook.
  - Logs de PrestaShop con el prefijo `[wipop]` para auditar las acciones
    relevantes.

## Requisitos

- PHP 8.1 o superior.
- PrestaShop 8.1 a 9.1.
- Extensión PHP cURL habilitada.
- Cuenta de comercio Wipöp con credenciales de Sandbox y Producción.
- Credenciales del comercio:
  - Merchant ID.
  - Terminal ID.
  - Public Key.
  - Private Key.
- HTTPS habilitado en la tienda para que Wipöp pueda enviar webhooks de forma
  segura.

## Instalación

### Instalar desde ZIP

1. Descarga el paquete `wipop.zip` generado por el pipeline o por tu sistema de
   build.
2. En el Back Office de PrestaShop ve a **Módulos > Gestor de módulos**.
3. Pulsa **Subir un módulo**, selecciona el ZIP e instálalo.
4. Abre la página de configuración del módulo Wipöp y continúa con la
   configuración.

### Desarrollo local

- Sigue la guía de desarrollo en [development.md](development.md).

## Antes de empezar

- Completa el alta con Wipöp para obtener terminales de Sandbox y Producción.
- Reúne todas las credenciales del módulo: Merchant ID, Terminal ID, Public Key y
  Private Key.
- Asegúrate de que la tienda es accesible por HTTPS.
- En el portal Wipöp/BBVA, configura el webhook usando la URL, usuario y
  contraseña exactos que se muestran en la página de configuración del módulo.

## Inicio rápido

1. Instala el módulo desde ZIP.
2. Abre la página de configuración de Wipöp en el Back Office de PrestaShop.
3. Rellena Merchant ID, Terminal ID, Public Key, Private Key y el entorno
   objetivo.
4. Elige el modo de **Preautorizaciones**:
   - `Cobrar en el checkout`, o
   - `Reservar el importe para cobrarlo después`.
5. Selecciona los métodos de pago Wipöp que quieres ofrecer en el checkout.
6. Guarda los ajustes.
7. Pulsa **Verificar datos** para validar las credenciales y obtener los métodos
   de pago disponibles.
8. Configura la URL y credenciales de webhook en el portal Wipöp/BBVA.
9. Realiza un pedido de prueba en Sandbox para verificar el flujo. Asegúrate de
   que se guardan los metadatos de Wipöp y de que el webhook actualiza el estado
   del pedido.
10. Opcional: marca un producto virtual como recurrente para que, después del
    primer cargo pagado, el módulo programe cargos MIT automáticos según la
    frecuencia seleccionada.

## Configuración

### Credenciales y entorno

Los ajustes se guardan en el valor de configuración de PrestaShop
`WIPOP_SETTINGS`. Campos obligatorios:

- `Merchant ID`: proporcionado por Wipöp.
- `Public Key` / `Private Key`: claves criptográficas usadas para firmar
  peticiones.
- `Terminal ID`: número entre 1 y 99.
- `Entorno`: `Sandbox` para pruebas o `Producción` para procesar pagos reales.

### Captura manual / preautorización

Selecciona `Reservar el importe para cobrarlo después` en **Preautorizaciones**
si quieres que los cargos con tarjeta queden autorizados pero pendientes de
captura. Cuando está activo:

- El pedido se crea en el estado de pedido autorizado de Wipöp.
- Las acciones **Capturar preautorización con Wipöp** y **Anular
  preautorización con Wipöp** aparecen en los botones de acción del pedido.
- Capturar llama a la operación de captura de Wipöp y actualiza el intento de
  pago/estado de pedido local.
- Anular llama a la operación de reversión de Wipöp y cancela la preautorización
  local.
- Las preautorizaciones suelen tener un periodo de validez limitado.

Con `Cobrar en el checkout`, el cargo se captura inmediatamente durante el
checkout.

### Métodos de pago

El módulo soporta pagos con tarjeta y Bizum. Elige qué métodos están habilitados
desde la página de configuración de Wipöp.

El módulo verifica los datos del comercio contra Wipöp y solo muestra opciones de
checkout disponibles para el terminal configurado y el carrito actual.

### Webhooks

Configura el webhook en el portal Wipöp/BBVA usando los datos exactos que se
muestran en la página de configuración del módulo:

- URL del webhook.
- Usuario del webhook.
- Contraseña del webhook.

El webhook sincroniza:

- Cambios de estado de pago.
- Identificadores de transacción.
- Datos enmascarados de tarjeta y tokens de tarjeta guardada.
- Eventos de reembolso.
- Metadatos de captura manual.
- Eventos de captura/reversión de preautorizaciones.
- Eventos de verificación usados para marcar el webhook como conectado en el
  panel de administración.

Las credenciales del webhook se pueden regenerar desde la página de configuración
del módulo.

### Productos recurrentes

Los pagos recurrentes están disponibles para productos virtuales. Al editar un
producto virtual, activa **Pago recurrente** y elige `Mensual` o `Anual`.

Cuando el primer cargo se marca como pagado, el módulo:

- Verifica que el cliente tiene una tarjeta guardada/token COF reutilizable.
- Crea una suscripción recurrente local con importe, moneda, periodo, siguiente
  fecha de intento y secuencia de ejecución.
- Procesa futuros cargos MIT usando el token de tarjeta guardada.
- Añade información de pedido/administración para cada intento recurrente.

Los carritos recurrentes tienen reglas adicionales:

- Deben pagarse con tarjeta.
- Bizum no está disponible para carritos recurrentes.
- Deben contener solo productos virtuales.
- Los productos del mismo carrito recurrente deben usar el mismo periodo.
- Los cargos recurrentes se capturan inmediatamente aunque la captura manual esté
  habilitada para pagos normales con tarjeta.

Los pagos recurrentes dependen del tráfico por defecto. El módulo comprueba si
hay pagos recurrentes vencidos cuando la tienda recibe tráfico. Si hay
suscripciones activas listas para renovarse, Wipöp las procesa en segundo plano
con bloqueos internos para evitar cobros duplicados.

No hace falta configuración adicional en tiendas normales con tráfico regular.

#### Tiendas con poco tráfico

Si la tienda no recibe visitas, una renovación vencida puede procesarse más
tarde, cuando llegue la siguiente petición a la tienda.

Para tiendas con muy poco tráfico, o tiendas que necesiten una hora de renovación
más precisa, recomendamos configurar una tarea cron del servidor.

#### Cron de servidor opcional

Si tienes acceso a tareas cron en tu panel de hosting, puedes ejecutar el
procesador de recurrencias de Wipöp cada 15 minutos:

```cron
*/15 * * * * cd /ruta/a/prestashop && /usr/bin/php bin/console wipop:recurring:process --env=prod --no-debug --limit=50 >> var/logs/wipop-recurring.log 2>&1
```

Sustituye `/ruta/a/prestashop` por la carpeta raíz de tu instalación de
PrestaShop.

Puedes probar el comando manualmente con:

```bash
cd /ruta/a/prestashop
php bin/console wipop:recurring:process --limit=1
```

Una salida correcta sería:

```text
Processed 0 Wipop recurring payment(s).
```

`0` es válido: significa que no había pagos recurrentes vencidos en ese momento.

Los cargos recurrentes también se pueden detener manualmente desde la página de
pedido de PrestaShop.

Los cargos recurrentes se detienen automáticamente si el pedido padre se cancela,
se reembolsa o falla, o si los datos de tarjeta/token necesarios para futuros
cargos dejan de estar disponibles.

### Reembolsos

Los pedidos Wipöp de tarjeta pagados se pueden reembolsar desde la página de
pedido de PrestaShop. El módulo soporta reembolsos totales y parciales, valida
la consistencia de importe/moneda, guarda el evento de reembolso y crea el abono
de PrestaShop cuando es posible. Los reembolsos Bizum no están soportados desde
el módulo.

Los webhooks de reembolso también sincronizan el estado de reembolso con el
intento de pago local.

## Seguridad, credenciales y datos de tarjeta

- Los ajustes del comercio se guardan en el valor de configuración de PrestaShop
  `WIPOP_SETTINGS`.
- Las credenciales del webhook y su estado de verificación se guardan en
  `WIPOP_WEBHOOK_SETTINGS`; la contraseña del webhook se cifra antes de
  persistirse.
- El módulo usa flujos de pago alojados por Wipöp y no procesa ni almacena datos
  PAN/CVV de tarjeta en PrestaShop.
- Las tarjetas guardadas usan identificadores COF/source de Wipöp junto con datos
  enmascarados de tarjeta para visualización y futuros cargos one-click o
  recurrentes.
- Trata los backups de base de datos y el acceso al Back Office como información
  sensible porque pueden contener credenciales del comercio e identificadores de
  pago.

## Gestión de errores y logs

- Los fallos de configuración y API muestran avisos en el administrador cuando es
  posible.
- Los logs técnicos detallados se escriben en los logs de PrestaShop con el
  prefijo `[wipop]`.

## Estructura del repositorio

- `wipop/`: carpeta del módulo PrestaShop, cargada en `modules/wipop`.

## Desarrollo / QA

- Ver [development.md](development.md).
