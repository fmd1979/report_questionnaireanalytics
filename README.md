# Encuestas y analítica para Moodle 5

Este repositorio contiene dos plugins instalables por separado:

| Paquete | Componente | Instalación | Uso |
|---|---|---|---|
| `surveypulse/` | `mod_surveypulse` | `mod/surveypulse` | Actividad propia para crear encuestas, recoger respuestas y consultar gráficos. No necesita Questionnaire. |
| `questionnaireanalytics/` | `report_questionnaireanalytics` | `report/questionnaireanalytics` | Reporte para las encuestas existentes de Questionnaire 5.0; versión 0.1.1 beta. |

## Instalar SurveyPulse

1. Descarga `dist/mod_surveypulse-0.1.0-beta.zip` y súbelo en **Administración del sitio → Plugins → Instalar plugins**. Alternativamente copia la carpeta `surveypulse` a `mod/surveypulse`.
2. Completa la actualización de Moodle y purga las cachés.
3. En un curso, activa edición y agrega **Encuesta SurveyPulse**.
4. Define el anonimato, las fechas y el mínimo de respuestas. Guarda y abre **Gestionar preguntas**.
5. Agrega preguntas y revisa la encuesta. Los usuarios con matrícula activa y permiso de respuesta pueden enviarla una sola vez.
6. Los docentes autorizados y gestores acceden a **SurveyPulse: analítica de encuestas** en el curso o categoría. El gestor también puede entrar desde **Administración del sitio → Informes**.

Los gráficos se generan en SVG en el servidor, con tablas equivalentes. Los resultados pequeños se ocultan hasta alcanzar el mínimo configurado (5 inicialmente). Excel y CSV contienen estadísticas agregadas; no incluyen comentarios ni identificadores personales.

## Actualizar el reporte de Questionnaire

Instala `dist/report_questionnaireanalytics-0.1.1-beta.zip` como actualización del reporte existente. Conserva su nombre de componente. Incluye gráficos SVG y acceso directo en la navegación secundaria de categorías y gestión de cursos. Requiere `mod_questionnaire`.

Los dos plugins no migran datos entre sí. Questionnaire y sus encuestas existentes permanecen disponibles; las nuevas encuestas propias se crean en SurveyPulse.

## Estado y desarrollo

Ambos paquetes son beta. La validación local se realiza en Moodle 5.0.11 con PHP 8.3 y MariaDB 10.11; los resultados detallados están en cada `VALIDACION.md`. Revisa primero el funcionamiento con tu tema y tus roles en un entorno de prueba. El código usa GPL v3 o posterior.

Para generar los paquetes desde el código: `python3 scripts/package.py`. Cada ZIP tiene una sola carpeta de plugin en su raíz. El workflow de GitHub ejecuta las suites de ambos componentes en Moodle 5.0.
