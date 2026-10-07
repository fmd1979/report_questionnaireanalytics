# SurveyPulse 0.1.0 beta

Actividad independiente de encuestas para Moodle 5.0, desarrollada por SiteEcuador. Componente: `mod_surveypulse`. No depende de Questionnaire ni del reporte anterior.

## Funciones

- Editor de preguntas con posición, obligatoriedad y seis tipos: selección simple, selección múltiple, sí/no, escala 1–5, número y texto abierto.
- Una respuesta completa por participante; exige matrícula activa y permiso `mod/surveypulse:submit`.
- Apertura/cierre programados y anonimato configurable.
- Gráficos SVG, tablas, comparación entre encuestas de la página actual y promedio por pregunta de escala. No requieren JavaScript para dibujar.
- Analítica de una actividad, curso o categoría con sus subcategorías; acceso desde cursos, categorías/gestión y administración.
- Excel y CSV agregados, con protección contra fórmulas en texto. Los comentarios quedan fuera de las exportaciones.
- Copia/restauración de Moodle, duplicación de actividad, reinicio de respuestas y API de privacidad.

## Instalación y uso

Sube el ZIP en **Administración del sitio → Plugins → Instalar plugins**, o copia esta carpeta a `mod/surveypulse`. Completa las notificaciones de instalación y purga cachés. Agrega una **Encuesta SurveyPulse** a un curso y después abre **Gestionar preguntas**. En preguntas de escala, explica el significado de 1 y 5 en el enunciado.

Al recibir la primera respuesta, las preguntas y el modo de anonimato se bloquean en el servidor, incluso ante ediciones simultáneas. Puedes seguir ajustando fechas y el mínimo de publicación. Para cambiar las preguntas, duplica o crea otra actividad; la duplicación copia la definición sin las respuestas.

El umbral inicial es 5 respuestas por pregunta. Una encuesta con envíos suficientes puede tener preguntas opcionales todavía ocultas. No se muestra tasa de participación: se presentan los envíos recibidos, sin confundirlos con la población matriculada. En selección múltiple, el denominador es quienes contestaron esa pregunta, por lo que la suma puede superar el 100 %.

## Permisos y privacidad

Los estudiantes pueden responder y no acceder a analítica. Docentes y gestores tienen permisos de lectura según el rol. `mod/surveypulse:viewall` permite analítica en las categorías asignadas, sin exigir matrícula de analista. Los comentarios requieren `mod/surveypulse:viewcomments`; la descarga requiere `mod/surveypulse:export`. La selección de categorías y cada actividad se autorizan en el servidor. Las actividades/cursos ocultos conservan sus controles. Si un curso fuerza grupos separados, un analista sin acceso a todos los grupos no obtiene resultados de todo el curso.

En modo anónimo, los envíos guardan `userid=0`, `timecreated=0` e identificadores aleatorios para evitar alinearlos por orden con los registros de participación. Estos registros solo contienen encuesta y usuario, sin enlace al envío ni fecha. No hay filtros por persona, grupos o fecha para respuestas anónimas. El texto abierto puede identificar al autor por su contenido; se muestra solo con permiso y umbral suficientes, escapado y sin fechas ni identificadores.

La API de privacidad exporta y elimina respuestas identificadas y registros de participación. Las respuestas anónimas no se pueden localizar por usuario y permanecen; borrar los datos de toda la actividad o reiniciar respuestas también las elimina. Tras eliminar por privacidad un registro de participación, ese usuario podría volver a responder. Los permisos de administrador y el acceso directo a la base de datos o registros externos exceden los controles del plugin.

La copia con información de usuarios incluye respuestas y participación; una copia sin usuarios y una duplicación conservan solo la encuesta y preguntas. No importa ni migra encuestas de Questionnaire. No incluye matrices, ramas condicionales, respuestas de invitados, edición posterior del envío, calificaciones ni integración con la app móvil.

## Validación

Consulta `VALIDACION.md` para los resultados ejecutados y los límites. Licencia GPL v3 o posterior.
