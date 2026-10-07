# Validación de la entrega

## Actualización 0.1.1 beta, versión 2026100701

- Instalación real mediante la inicialización de PHPUnit de Moodle 5.0.11, PHP 8.3.6 y MariaDB 10.11.14, con Questionnaire 5.0.3.
- Suite del reporte incluida en la ejecución conjunta: **41 pruebas y 199 assertions aprobadas**, sin fallos ni errores (18 pruebas del reporte y 23 de SurveyPulse).
- Sintaxis PHP comprobada en ambos componentes: 59 archivos.
- Pruebas de SVG válido, gráficos completos sin JavaScript, opciones con cero, valores decimales, títulos únicos y texto escapado; renderizado SVG inspeccionado visualmente.
- Navegación probada con APIs reales del núcleo: callback de categoría y hook de navegación secundaria de gestión, permisos, categoría seleccionada y ausencia de duplicados.
- Las consultas de respuestas, reglas de privacidad y exportación permanecen sin modificaciones funcionales.
- La apariencia con el tema del cliente y las encuestas existentes todavía debe comprobarse en su sitio.

## Validación completa realizada para 0.1.0

Versión: 0.1.0 beta, 2026100700.

## Entorno ejecutado

- Moodle 5.0.11, Build 20261005, commit `744cc0c19013e7be831dca24fafd6501d7eb8348`.
- Questionnaire 5.0.3, rama `MOODLE_500_STABLE`, versión interna 2025041402.
- PHP 8.3.6.
- MariaDB 10.11.14, tablas UTF-8 (`utf8mb4_unicode_ci`), DML de Moodle.

## Comprobaciones aprobadas

- Instalación real mediante `admin/cli/install_database.php`, incluida la dependencia y el reporte.
- Sintaxis PHP de todos los archivos.
- PHPUnit: **13 pruebas, 50 assertions, sin fallos ni errores**.
- Casos: estadística ponderada (mediana par e impar, modas empatadas y valores negativos), protección CSV contra fórmulas, exclusión de envíos incompletos, usuarios únicos frente a envíos repetidos, anonimato, escalas con valores personalizados/cero/N/A, denegación al estudiante, analista limitado a una categoría, grupos separados y ausencia de grupo, supresión también en Excel, porcentajes de selección múltiple, aislamiento entre instancias de encuesta compartidas, límites inclusivos de fechas, renderizado de filtros/tarjetas/gráficos y lectura de comentarios con permiso y búsqueda.
- Ejecución del punto de entrada `index.php` dentro de Moodle: tarjetas, preguntas y enlace Excel presentes; sin excepción ni cadenas de traducción faltantes.
- Generación de Excel usando `MoodleExcelWorkbook`: archivo OOXML válido, cuatro hojas, 3 envíos de un usuario elegible = 100 % de participación; valores 2/4/4 = media 3,333333 y mediana 4. Una pregunta que comienza con `=2+2` queda como texto, sin fórmulas ejecutables.

## Pendiente antes de considerarlo estable

- Prueba específica en MySQL y Moodle 5.1; el entorno ejecutado usa MariaDB y Moodle 5.0.
- Prueba visual en navegador con el tema usado en su sitio, especialmente temas de terceros.
- Pruebas con sus encuestas existentes, permisos personalizados, grandes categorías y volúmenes reales.
- Revisión completa de estándares de código, accesibilidad y requisitos de publicación del directorio/Marketplace.

El workflow en la raíz del repositorio prepara las pruebas de ambos componentes en Moodle 5.0. La evidencia anterior corresponde a la ejecución local; no certifica una ejecución remota del workflow. Los ZIP no incluyen bases de datos ni datos de prueba.
