---
name: contenido-arhaus
description: Escribe contenido para ARHAUS Developers — posts de Instagram y LinkedIn, textos de las fichas de proyecto del sitio, newsletter, guiones de reel y textos del panel del cliente. Usala siempre que haya que escribir algo que vaya a salir con el nombre de ARHAUS, incluso si el pedido es corto ("un post de Huepil", "el texto de esta ficha", "algo para contar la obra de Belén"). También cuando haya que elegir qué obra o qué imagen mostrar.
---

# Contenido de ARHAUS

ARHAUS Developers es un estudio de arquitectura argentino. Pocas obras por
año, de ticket alto, ciclos de meses y clientes que llegan por
recomendación. Eso define todo lo que sigue: **el contenido no vende, hace
confianza**. Nadie contrata una casa por un carrusel; sí deja de llamar a
un estudio que parece improvisado.

## Lo primero: de dónde salen los hechos

Nunca inventes un dato de una obra. Ni los metros, ni el año, ni los
materiales, ni en qué etapa está. Si no está escrito en algún lado, no va.

Dónde mirar, en este orden:

1. **La ficha pública del proyecto**: `proyecto-<lugar>.html`. Tiene
   tipología, ubicación y estado en el bloque `ficha__dato`.
2. **El mosaico** de `proyecto.html`: el `data-leyenda` de cada pieza dice
   cómo se nombra el proyecto de cara afuera.
3. **El panel de la obra**: entrá por `panel/admin/obra.php?id=<n>`.
   Ahí están las etapas, las novedades de dirección de obra y los archivos.
4. **Las imágenes**: `images/proyectos/unifamiliar/<lugar>/`. El `alt` de
   cada foto en la ficha ya describe qué se ve; sirve de punto de partida.

Si falta un dato y hace falta, **preguntá**. Un "aproximadamente 200 m²"
inventado es peor que no decir la superficie.

## Las dos voces

ARHAUS escribe distinto según quién lee. No se mezclan.

### Hacia afuera (sitio, Instagram, LinkedIn, prensa)

Primera persona plural y español neutro. Sobrio, sin signos de admiración,
sin "¡descubrí!", sin emojis en el cuerpo del texto. Tal como está hoy en
el sitio:

> Somos un estudio de arquitectura joven formado en la UBA, con posgrado
> especializado en diseño, dirección, ejecución y gerenciamiento de obras.

> El contexto, los materiales y los detalles son nuestros pilares a la
> hora de diseñar.

Qué evitar, porque desentona con eso: superlativos ("el mejor", "único en
su tipo"), palabras de agencia ("soluciones integrales", "sinergia",
"experiencias 360"), y el pie de post con quince hashtags.

### Hacia el cliente (panel, mails de obra, WhatsApp)

Segunda persona del singular rioplatense —**vos**— y lenguaje llano. El
cliente no es arquitecto: se le explica sin jerga y sin tratarlo de tonto.
Así está escrito el panel:

> Tu casa en 3D · agarrala con el mouse para girarla

> El relevamiento de lo que había antes de empezar: fotos y videos de la
> obra tal como estaba.

Regla práctica: si una frase necesita que el lector sepa qué es un
cielorraso suspendido, reescribila.

## Qué no se publica nunca

Esto no es estilo, es una línea que no se cruza:

- **Datos personales de los propietarios.** Nombres completos, DNI, mails,
  teléfonos, montos, contratos. Las secciones `prop_archivos`,
  `propietarios` y `pagos` del panel no salen de ahí. Al nombrar una obra
  se usa el nombre del proyecto ("Casa Huepil"), nunca el del dueño.
- **Material del panel sin permiso.** Lo que el estudio le sube a un
  cliente es de ese cliente. Un render de una obra en curso se publica si
  Max dice que sí, no por defecto.
- **Obras de terceros o renders de stock** presentados como propios.

## El material que ya existe

El cuello de botella de ARHAUS no es la redacción: es que hay material muy
bueno sin usar. Antes de escribir, mirá qué hay.

| Proyecto | Ficha | Imágenes | Maqueta 3D |
|---|---|---|---|
| Casa Huepil — San Martín de los Andes | `proyecto-neuquen.html` | `unifamiliar/neuquen/` (12) | sí, `models/obras/2.glb` |
| Casa La Pampa 4510 — Villa Urquiza | `proyecto-villa-urquiza.html` | `unifamiliar/villa-urquiza/` (13) | sí |
| Casa I — Azcuénaga, San Isidro | `proyecto-san-isidro.html` | `unifamiliar/san-isidro/` (13) | obra finalizada, hay fotos del antes y después |
| Casa 424 — Pilar del Este | `proyecto-pilar.html` | `unifamiliar/pilar/` (16) | sí |
| San Benito — Escobar | `proyecto-escobar.html` | `unifamiliar/escobar/` (8) | interiorismo |
| Barrancas del Lago — Nordelta | sin ficha pública | en el panel | sí, `models/obras/3.glb` |

Lo que casi nadie más tiene y conviene aprovechar:

- **Maquetas 3D navegables** en el sitio. Un video girando la maqueta
  rinde más que cinco renders quietos.
- **Fotos día por día de obra**, en el panel. El proceso interesa más que
  el resultado: el pozo, la estructura, el encofrado.
- **Antes y después reales** en Casa I, que está finalizada.
- **Relevamientos**: cómo estaba el lugar antes de tocarlo.

## Formatos

Para todos: **una idea por pieza**. Si hay dos, son dos piezas.

**Instagram, post simple.** Primera línea que funcione sola, porque es lo
único que se ve antes del "más". Tres a cinco líneas. Nada de hashtags
genéricos de arquitectura; dos o tres, de lugar y tipología.

**Instagram, carrusel.** De cinco a siete placas. La primera plantea algo
—una decisión de proyecto, un problema del terreno—, las del medio lo
muestran con las imágenes, la última cierra. Para cada placa, indicá qué
imagen va, con su ruta.

**Reel.** De quince a treinta segundos, pensado **mudo**: lo que se
entiende tiene que estar en pantalla, no en la locución. Si es un
recorrido de maqueta, decí el ángulo. Para producirlo está la skill
`web-motion-graphics`.

**LinkedIn.** Acá va el oficio: la decisión técnica, el problema de obra y
cómo se resolvió, la coordinación entre gremios. Entre 800 y 1.300
caracteres. Es el lugar para hablarle a colegas, desarrolladores y
proveedores, no para repetir el post de Instagram.

**Ficha de proyecto del sitio.** Hoy las fichas sólo tienen tipología,
ubicación y estado: ni un párrafo. Es el agujero más grande que tiene el
sitio, para el lector y para Google. Dos o tres párrafos por obra: el
encargo, la decisión de proyecto, los materiales. El texto va dentro de
`ficha__galeria`, respetando el HTML que ya está.

**Newsletter.** Una obra, un avance, una imagen. Breve.

## Cómo trabajar

1. **Elegí el material primero, el texto después.** Pedile a Max la obra
   si no la dijo; después mirá qué imágenes hay y recién ahí escribí.
2. **Nombrá los archivos concretos.** No "una foto del frente": la ruta
   exacta, para que se pueda armar sin adivinar.
3. **Entregá el texto listo para copiar y pegar**, sin corchetes para
   completar. Si falta un dato, preguntalo antes, no lo dejes como hueco.
4. **Si lo que escribís va al repo** (una ficha, un texto del panel),
   aplicá el cambio en el archivo y dejá que Max lo mire en local antes de
   subirlo. Las imágenes del sitio llevan `?v=` cuando se reemplazan.
5. **Decí qué dudás.** Si no estás seguro de un material o un dato,
   marcalo en una línea al final en vez de acomodarlo.

## El sistema visual

Dirección **Editorial**, elegida por Max en noviembre de 2026. Una regla
la resume: **la foto nunca lleva texto encima**. El texto vive en una
franja crema abajo, siempre con el mismo alto y los mismos márgenes. Por
eso el feed se lee parejo aunque las fotos sean de día, de noche, renders
o planos.

**Paleta.** Crema `#f5f4f0` (fondo), carbón `#333333` (texto), borgoña
`#880723` (sólo la rayita bajo el título) y gris `#8a8a8a` (rótulos
secundarios). El borgoña no se usa para texto largo.

**Tipografía.** Cormorant Garamond Light para los títulos y el nombre del
lugar. Work Sans Medium, en mayúsculas y con las letras separadas, para
los rótulos. Las dos están en `fuentes/`, son las mismas del sitio y
tienen licencia OFL.

**El logo** sale de `images/Logo2.png`, que ya viene con transparencia.
Se tiñe del color que haga falta; nunca se deforma ni se le cambia el
color de marca por otro.

### Las herramientas

`scripts/placas.py` arma las placas del feed:

    python placas.py feed  <imagen> <obra> <lugar> [--rotulo TEXTO]
    python placas.py story <imagen> <obra> <lugar>
    python placas.py texto <titulo> <cuerpo>

El formato `feed` da 1080×1350, que es el que más ocupa en pantalla. La
placa `texto` es fondo crema con una sola idea: sirve para abrir un
carrusel y para que el feed no sea sólo fotos.

`scripts/tarjeta_ar.py` arma la tarjeta que se escanea para ver la casa
en 3D encima (ver `tarjeta.html`):

    python tarjeta_ar.py <render> "Casa Huepil" "San Martin de los Andes" tarjeta.png

Sale a 85x55 mm, 300 dpi. Ojo: esa tarjeta es ademas la MARCA que
reconoce el telefono, y el reconocimiento se agarra de los detalles de
la imagen. Por eso la cara que se escanea lleva el render a sangre: una
tarjeta crema con el logo y nada mas no engancha nunca. Despues hay que
compilar el marcador de ESA misma imagen:

    node scripts/compilar_marca.mjs models/marcas/huepil.mind tarjeta.png

Si se cambia el render hay que rehacer los dos: un .mind viejo con una
tarjeta nueva no reconoce nada.

`scripts/reel.py` arma los reels, a partir de un guion JSON:

    python reel.py guion.json salida.mp4

Hay un ejemplo de guion en el encabezado del script. Necesita un ffmpeg:
lo busca en `FFMPEG`, después en el PATH y después en un
`node_modules/ffmpeg-static` cercano.

### Cómo se arma un reel

- **1080×1920, mudo.** La mayoría lo mira sin sonido, así que todo lo
  que haya que entender tiene que estar en pantalla.
- **Zona segura.** Instagram dibuja su interfaz encima: abajo el texto
  del post, el usuario y los botones. Nada de texto en los últimos
  430 px ni en los primeros 200. El script ya lo respeta; si se toca a
  mano, hay que seguir respetándolo.
- **Nada se mueve rápido.** Cada imagen acerca un 6% a lo largo de la
  escena y entra con disolvencia. En arquitectura la imagen tiene que
  poder mirarse.
- **Entre 18 y 25 segundos.** Abre con placa crema, cierra con placa
  crema, cinco o seis imágenes en el medio.

### El ritmo del feed

Pensar de a bloques de tres, que es como se ve la grilla:

1. Una placa de texto que plantea algo.
2. La toma general que lo muestra.
3. El detalle que lo cierra.

Alternar obras y alternar día y noche entre bloques. Dos posts nocturnos
seguidos de la misma obra se ven repetidos en la grilla aunque sean
renders distintos.

### Lo que falta y conviene hacer

- **Reel de maqueta 3D.** Es lo único que ARHAUS tiene y la competencia
  no: girar la maqueta en video. Necesita capturar model-viewer, que es
  más trabajo que un reel de fotos. Vale la pena.
- **Antes y después de Casa I**, que está finalizada y tiene fotos de las
  dos épocas.
