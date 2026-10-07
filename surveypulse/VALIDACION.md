# Validación de SurveyPulse 0.1.0 beta

## Entorno ejecutado

Moodle 5.0.11 (Build 20261005), commit `744cc0c19013e7be831dca24fafd6501d7eb8348`, PHP 8.3.6 y MariaDB 10.11.14. Instalación real de las tablas, capacidades y módulo mediante `admin/tool/phpunit/cli/init.php`. El módulo no declara dependencia de Questionnaire.

## Resultados

Suite conjunta de SurveyPulse y Questionnaire Analytics: **41 pruebas, 199 assertions, sin errores ni fallos**. SurveyPulse aporta 23 pruebas; el reporte aporta 18. Comando ejecutado:

```bash
php vendor/bin/phpunit --testsuite mod_surveypulse_testsuite,report_questionnaireanalytics_testsuite --fail-on-warning
```

Casos comprobados:

- Normalización de seis tipos de pregunta, opciones inexistentes, datos anidados, números finitos y cero.
- Envío con tablas reales, anonimato sin usuario/fecha, un solo envío y ausencia de escrituras parciales ante una respuesta inválida.
- Fechas de cierre, bloqueo de preguntas tras la primera respuesta, identificadores en respuestas no anónimas.
- Denegación al estudiante, analista de categoría con subcategorías y sin acceso fuera de su ámbito, cursos ocultos y grupos separados forzados.
- Umbrales de publicación y de comentarios, porcentaje de selección múltiple y medias de escala/número.
- Formularios reales de Moodle: renderizado y validación de las seis clases de pregunta, controles y sesskey.
- Página de analítica ejecutada dentro de Moodle: SVG y barras completos, controles Excel/CSV y traducciones sin cadenas faltantes.
- Copia/restauración con información de usuarios: preguntas, respuestas anónimas, recibos y remapeo de claves. Duplicación sin respuestas.
- Localización de datos por la API de privacidad, eliminación de respuestas identificadas, eliminación solo de recibos para respuestas anónimas y eliminación total de la actividad.
- Excel OOXML producido con las bibliotecas de Moodle: media cero numérica, texto que comienza con `=` conservado como texto, ausencia de fórmulas y de comentarios. Escape de fórmulas para CSV.
- Gráficos SVG válidos y accesos de categoría/gestión con permisos y sin duplicados.
- Sintaxis PHP de todos los archivos de ambos componentes.

## Límites de la evidencia

La validación es local y corresponde a Moodle 5.0 con MariaDB. Falta validar el tema y roles personalizados del sitio, MySQL/PostgreSQL, concurrencia con carga alta, grandes volúmenes, accesibilidad completa y app móvil. No se certifica Moodle 5.1. No hay migración automática desde Questionnaire. La suite cubre anonimato y envíos secuenciales; no sustituye una prueba de estrés concurrente.

El workflow del repositorio permite repetir las pruebas en GitHub. Su estado remoto se consulta por separado. No se empaquetan bases de datos, usuarios de prueba ni credenciales.
