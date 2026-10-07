# Questionnaire Analytics · Moodle 5

**Versión 0.1.1 beta — SiteEcuador.** Reporte independiente para analizar encuestas de `mod_questionnaire`, sin modificar sus archivos ni sus respuestas. Licencia GPL v3 o posterior.

## Qué incluye

- Panel general con categorías (incluye descendientes), cursos y encuestas autorizadas.
- Filtros por categoría, curso, encuesta, grupo y fechas; fechas inclusivas en la zona horaria del usuario.
- Conteo de envíos completos y participación de usuarios únicos elegibles con matrícula activa.
- Barras para sí/no, selección única, múltiple y desplegables; evolución diaria en encuestas identificadas.
- Escalas: distribución por ítem, etiquetas y valores personalizados, promedio, mediana, modas, mínimo y máximo. `No aplica = -1` no interviene en estadísticas.
- Preguntas numéricas y deslizadores: distribución y estadísticas. Fechas: distribución de respuestas.
- Texto corto/largo: consulta paginada de comentarios y búsqueda, con permiso independiente.
- Respuestas de archivos: conteo, sin acceder a adjuntos.
- Excel real `.xlsx` con hojas de metadatos, resumen, distribuciones y estadísticas; CSV UTF-8.
- Gráficos SVG dibujados desde el servidor, con tabla accesible, sin dependencia de JavaScript; interfaz adaptable a móvil, español e inglés.
- Analista por categoría o sistema que puede revisar resultados sin ser docente ni estar matriculado.
- Registro de consultas y exportaciones en los logs estándar de Moodle.

## Requisitos

Moodle 5.0 o 5.1, PHP y base de datos compatibles con su versión de Moodle. Se requiere Questionnaire de la rama `MOODLE_500_STABLE`, versión interna `2025041400` o posterior; se recomienda 5.0.3. No necesita Composer, cron adicional, claves de API, servicios externos ni cambios en MySQL.

Fuentes verificadas: [Questionnaire](https://github.com/PoetOS/moodle-mod_questionnaire/tree/MOODLE_500_STABLE), [Moodle 5.0](https://github.com/moodle/moodle/tree/MOODLE_500_STABLE).

## Instalación desde Moodle

1. Instale/actualice Questionnaire compatible con Moodle 5 si hace falta.
2. Entre como administrador a **Administración del sitio → Plugins → Instalar plugins**.
3. Suba `report_questionnaireanalytics-0.1.1-beta.zip` y confirme la instalación. Moodle debe identificar el componente `report_questionnaireanalytics`, tipo **Reporte**.
4. Complete el proceso de actualización de la base de datos.
5. Entre a un curso y abra **Informes → Analítica de encuestas**. También puede usar el enlace general de navegación o **Administración del sitio → Informes → Analítica de encuestas**.

URL directa: `https://SU-MOODLE/report/questionnaireanalytics/index.php`.

Si no permite instalar por la interfaz, extraiga la carpeta `questionnaireanalytics` dentro de `report/`. En Moodle 5.1, la carpeta corresponde a `public/report/` en la distribución estándar. El criterio correcto siempre es la carpeta `report` dentro de `$CFG->dirroot`, al lado de los reportes del núcleo. Visite **Administración del sitio → Notificaciones**. Evite dejar la carpeta anidada dos veces.

Por CLI, desde la raíz administrativa de Moodle:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

En cPanel/LiteSpeed utilice el mismo PHP CLI de la instalación Moodle. No cambie permisos a 777: conserve propietario y permisos del resto de sus plugins.

## Actualizar desde 0.1.0

Suba el ZIP 0.1.1 desde **Administración del sitio → Plugins → Instalar plugins** y confirme la actualización. Si el servidor impide reemplazar archivos desde Moodle, sobrescriba `report/questionnaireanalytics/` con el contenido actualizado y visite **Notificaciones**. Después ejecute **Administración del sitio → Desarrollo → Purgar todas las cachés** y recargue con Ctrl+F5. No desinstale Questionnaire ni este reporte para actualizar.

## Acceso desde categorías y gestión de cursos

En **Cursos → categoría** y **Gestionar cursos y categorías**, aparece **Analítica de encuestas: categoría**, con esa categoría ya seleccionada. Moodle puede moverlo a «Más» si el tema reduce el espacio. El enlace respeta los permisos asignados a la categoría y comprende sus descendientes. El panel general de categorías incluye una gráfica de envíos por encuesta para la página actual.

## Gráficos que pueden no corresponder

Las preguntas abiertas se presentan como comentarios; no se convierten automáticamente en un gráfico de opciones. Las preguntas de archivo muestran conteos. Los resultados bajo el mínimo de respuestas siguen protegidos, con aviso visible. Las escalas muestran un gráfico general de promedios (solo ítems con muestra suficiente) y distribuciones por ítem. Las barras y la evolución temporal se generan como SVG, de modo que siguen visibles al cargar la página, capturarla o imprimirla aunque JavaScript falle.

## Primer uso

Abra el panel y aplique categoría o curso para reducir el selector de encuestas. Quite la selección anterior de encuesta al cambiar de ámbito. Seleccione una encuesta para ver preguntas y habilitar el filtro de grupo. Los filtros de grupo se aplican a una sola actividad.

Desde el resumen, Excel exporta todas las encuestas coincidentes para las cuales tiene permiso de exportación, incluidas las otras páginas. Desde una encuesta, exporta su resumen, distribuciones y estadísticas. Los gráficos no se incrustan como imágenes en Excel. Los comentarios, identidades y archivos no se exportan.

La búsqueda de comentarios solo afecta su listado; no altera los indicadores, las gráficas ni la exportación.

## Rol «Analista de encuestas»

1. **Administración del sitio → Usuarios → Permisos → Definir roles → Añadir nuevo rol**; cree un rol sin arquetipo.
2. Permita asignarlo en **Sistema**, **Categoría** y/o **Curso**, según su necesidad.
3. Configure estas capacidades:

| Capacidad | Uso |
| --- | --- |
| `report/questionnaireanalytics:viewall` | Consultar resultados en el ámbito donde se asigna el rol, sin permiso docente de Questionnaire. |
| `report/questionnaireanalytics:export` | Descargar Excel/CSV (opcional). |
| `report/questionnaireanalytics:viewcomments` | Leer comentarios abiertos (opcional; pueden contener datos personales escritos por participantes). |
| `moodle/site:accessallgroups` | Consultar todos los grupos, incluidas encuestas anónimas que estén configuradas con grupos separados. |
| `moodle/course:viewhiddencourses` | Ver reportes de cursos ocultos (solo si se desea). |
| `moodle/course:viewhiddenactivities` | Ver reportes de actividades ocultas (solo si se desea). |

4. Asigne el rol en la categoría elegida: **Administración del sitio → Cursos → Gestionar cursos y categorías → categoría → Permisos → Asignar roles**. Para todas las categorías, asigne en Sistema. No lo asigne en Sistema si solo debe ver una categoría.
5. Acceda mediante la URL directa del reporte. Solo aparecerán actividades autorizadas; asignaciones en una categoría se heredan a sus descendientes.

Docentes y gestores tienen `view`, `export` y `viewcomments` por defecto. Además de `view`, los docentes deben conservar `mod/questionnaire:readallresponses`; el reporte respeta los grupos separados. Un docente sin grupos asignados no obtiene resultados generales por dejar el filtro en «Todos».

## Interpretación de los resultados

- **Envíos completos:** registros con `complete = 'y'`; una persona puede aportar varios si la encuesta lo permite.
- **Elegibles actuales:** usuarios activos con matrícula activa y capacidad de responder a esa actividad. No se aplica retrospectivamente la matrícula que existía al enviar. Tampoco se evalúan restricciones individuales de disponibilidad ni aperturas/cierres para definir este denominador.
- **Participantes elegibles actuales:** personas distintas de esa misma población con al menos un envío completo en las fechas/grupos seleccionados. Respuestas históricas de usuarios que ya no están matriculados se incluyen en distribuciones, pero no en este numerador.
- **Participación:** participantes elegibles actuales / elegibles actuales × 100. No equivale a la tasa histórica de asistencia ni a la participación únicamente estudiantil si otros roles también pueden responder.
- **Grupos:** membresías actuales; una persona presente en dos grupos se cuenta una vez al agregar grupos.
- **Porcentaje por opción:** envíos que eligieron esa opción / envíos que respondieron la pregunta. En selección múltiple puede superar 100 % al sumar opciones.
- **Escalas:** porcentajes y estadística por ítem, sin «No aplica». Se usan los valores numéricos configurados, incluidos valores personalizados y cero. No se inventa un indicador de satisfacción ni se asume que la escala esté orientada de peor a mejor.
- **Encuestas públicas compartidas:** cada actividad se analiza por `questionnaireid`; tener acceso al curso propietario no abre automáticamente respuestas de otros cursos. No se fusionan preguntas de encuestas diferentes por tener el mismo nombre.
- **Preguntas eliminadas, textos de sección y saltos de página:** no se muestran como preguntas analíticas.

## Privacidad

El umbral predeterminado es **5 respuestas**. Se aplica al conjunto filtrado, a cada pregunta y a los ítems de escala; los conteos protegidos tampoco se exponen en las descargas. Se configura en **Administración del sitio → Plugins → Reportes → Configuración de analítica de encuestas**.

Las encuestas anónimas no muestran conteo de personas, participación, fecha del último envío, serie temporal ni filtro de grupos. No se enlazan respuestas anónimas con identidades. En grupos separados, se requiere acceso a todos los grupos para consultarlas.

Los comentarios pueden contener nombres u otros datos escritos por el propio participante; ocultar la identidad del envío no elimina esa información del texto. El umbral mínimo no garantiza anonimización frente a comparaciones entre filtros superpuestos. Asigne los permisos de analista a personas autorizadas y considere la política institucional al ajustar el umbral.

El plugin no crea tablas, no almacena respuestas adicionales y no las envía a IA ni a servicios externos. Las consultas y exportaciones quedan en los logs del núcleo.

## Estado y siguientes versiones

Esta entrega es beta. Antes de desplegar en producción, pruebe una copia de su sitio con sus tipos de preguntas y roles. Compatibilidad declarada: Moodle 5.0/5.1; la validación ejecutada se documenta en `VALIDACION.md`.

No incluye todavía: consolidación estadística entre preguntas equivalentes de diferentes encuestas, análisis por docente, análisis semántico/IA, frecuencia de palabras, PDF, gráficos incrustados en Excel ni integración con CoursePulse. Estas funciones requieren definir reglas explícitas de comparación y privacidad.

Para salir de beta: validar con encuestas reales, verificar Moodle 5.1/MySQL en su entorno, revisar accesibilidad y estilos con su tema, medir rendimiento con su volumen y completar los controles del directorio de plugins de Moodle.

## Desarrollo / GitHub

Repositorio: https://github.com/fmd1979/report_questionnaireanalytics . Este reporte vive en la subcarpeta `questionnaireanalytics`; la actividad propia e independiente vive en `surveypulse`. Para instalar el reporte, use su ZIP individual o copie solo esta carpeta a `report/questionnaireanalytics`.

El paquete incluye PHPUnit. El workflow de la raíz del repositorio ejecuta las pruebas de ambos componentes en Moodle 5.0; Questionnaire se instala como dependencia solo para este reporte. Consulte `VALIDACION.md` para la evidencia local; la ejecución remota tiene su propio estado en GitHub Actions.

Con un entorno PHPUnit Moodle inicializado:

```bash
php admin/tool/phpunit/cli/init.php
php vendor/bin/phpunit --testsuite report_questionnaireanalytics_testsuite
```

Mantenga la rama de Questionnaire compatible indicada. Las consultas usan DML de Moodle y nombres de tabla entre llaves; no dependen de un prefijo como `mdl_`. Los resultados se agregan en SQL, el resumen pagina 25 actividades y los comentarios 20 entradas. Las distribuciones de cardinalidad alta se limitan a 50 valores en pantalla; sus estadísticas y exportación conservan todos los valores válidos. En volúmenes elevados, mida el costo de los filtros y exportaciones antes de agregar índices o cachés.
