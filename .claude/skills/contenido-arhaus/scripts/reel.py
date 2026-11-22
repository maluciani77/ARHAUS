#!/usr/bin/env python3
"""
Arma un reel vertical de ARHAUS a partir de renders o fotos de obra.

Usa la misma tipografia y los mismos margenes que placas.py, a proposito:
el feed y los videos tienen que leerse como la misma mano.

Como esta pensado:
  - 1080x1920 a 30 fps, el formato de reel.
  - MUDO. La mayoria lo mira sin sonido, asi que todo lo que haya que
    entender esta en pantalla.
  - Nada se mueve rapido. Cada imagen hace un acercamiento muy lento
    (un 6% a lo largo de la escena) y entra con una disolvencia. En
    arquitectura la imagen tiene que poder mirarse.
  - Abre con placa crema y cierra con placa crema. Entre medio, las fotos.

Uso:
    python reel.py guion.json salida.mp4

El guion es un JSON asi:
    {
      "obra": "Casa Huepil",
      "lugar": "San Martin de los Andes",
      "apertura": "Una casa que se abre al bosque",
      "cierre": "arhaus.com.ar",
      "escenas": [
        {"imagen": "images/.../10.jpg", "rotulo": "El frente vidriado",
         "segundos": 3.5, "desde": "centro", "hacia": "arriba"}
      ]
    }

"desde"/"hacia" mueven el encuadre: centro, arriba, abajo, izquierda,
derecha. Si no se ponen, queda quieto y solo acerca.

Necesita Pillow y el ffmpeg de node_modules/ffmpeg-static (o uno en PATH).
"""
import json
import os
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, AQUI)
from placas import (  # noqa: E402  (comparte el sistema tipografico)
    BORGONA, CARBON, CREMA, GRIS, LOGO_POR_DEFECTO,
    cormorant, pegar_logo, partir, versalitas, worksans,
)

W, H, FPS = 1080, 1920, 30
MARGEN = 86
ZOOM = 0.06          # cuanto acerca a lo largo de una escena
DISOLVENCIA = 0.5    # segundos

# ZONA SEGURA. Instagram dibuja su propia interfaz encima del reel: abajo
# el texto del post, el usuario y los botones; arriba el nombre de la
# cuenta. Todo lo que caiga ahi se ve tapado. Estos son los margenes que
# hay que respetar, y por eso los rotulos NO van al pie de la pantalla
# aunque sobre lugar.
SEGURO_ABAJO = 430
SEGURO_ARRIBA = 200


def suave(t):
    """Arranca y frena despacio. Lineal se nota artificial."""
    return t * t * (3 - 2 * t)


def anclar(nombre, sobra):
    return {
        "centro": sobra / 2, "arriba": 0.0, "izquierda": 0.0,
        "abajo": sobra, "derecha": sobra,
    }.get(nombre, sobra / 2)


def fotogramas_escena(esc, n):
    """Devuelve los fotogramas de una escena, ya recortados a 1080x1920."""
    base = Image.open(esc["imagen"]).convert("RGB")
    # Se agranda una sola vez al tamano mas grande que hara falta y
    # despues solo se recorta: recortar es barato, redimensionar no.
    escala = max(W / base.width, H / base.height) * (1 + ZOOM)
    grande = base.resize((round(base.width * escala), round(base.height * escala)),
                         Image.LANCZOS)
    sobra_x, sobra_y = grande.width - W, grande.height - H
    x0 = anclar(esc.get("desde", "centro"), sobra_x)
    x1 = anclar(esc.get("hacia", esc.get("desde", "centro")), sobra_x)
    y0 = anclar(esc.get("desde_v", "centro"), sobra_y)
    y1 = anclar(esc.get("hacia_v", esc.get("desde_v", "centro")), sobra_y)

    for i in range(n):
        t = suave(i / max(1, n - 1))
        # el acercamiento: se recorta una caja cada vez mas chica
        z = 1 - ZOOM * t
        cw, ch = round(W * z), round(H * z)
        x = round(x0 + (x1 - x0) * t) + (W - cw) // 2
        y = round(y0 + (y1 - y0) * t) + (H - ch) // 2
        x = max(0, min(x, grande.width - cw))
        y = max(0, min(y, grande.height - ch))
        cuadro = grande.crop((x, y, x + cw, y + ch))
        if (cw, ch) != (W, H):
            cuadro = cuadro.resize((W, H), Image.BILINEAR)
        yield cuadro


def rotular(im, obra, lugar, rotulo):
    """La franja de abajo, igual que en las placas."""
    d = ImageDraw.Draw(im, "RGBA")
    # El degrade arranca bien arriba y baja hasta el pie: asi el texto se
    # lee sobre cualquier render, claro u oscuro, sin un borde duro.
    alto_degrade = 620
    for i in range(alto_degrade):
        a = int(215 * (i / alto_degrade) ** 1.9)
        d.line([(0, H - alto_degrade + i), (W, H - alto_degrade + i)], fill=(14, 14, 14, a))

    # El bloque termina donde empieza la interfaz de Instagram.
    alto_bloque = 190
    y = H - SEGURO_ABAJO - alto_bloque
    versalitas(d, (MARGEN, y), obra.upper(), worksans(27, "Medium"), 7, "#efe9df")
    d.text((MARGEN, y + 44), lugar, font=cormorant(56, "Light"), fill="#ffffff")
    if rotulo:
        d.line([(MARGEN, y + 126), (MARGEN + 64, y + 126)], fill=BORGONA, width=3)
        versalitas(d, (MARGEN, y + 148), rotulo.upper(), worksans(20), 4, "#c9c4bb")
    return im


def placa_crema(titulo, pie, logo):
    im = Image.new("RGB", (W, H), CREMA)
    d = ImageDraw.Draw(im)
    pegar_logo(im, logo, 240, (MARGEN, SEGURO_ARRIBA))
    ft = cormorant(82, "Light")
    lineas = partir(d, titulo, ft, W - 2 * MARGEN)
    # centrado entre las dos zonas seguras, no en el medio de la pantalla
    y = SEGURO_ARRIBA + (H - SEGURO_ARRIBA - SEGURO_ABAJO - len(lineas) * 96) // 2
    for ln in lineas:
        d.text((MARGEN, y), ln, font=ft, fill=CARBON)
        y += 96
    d.line([(MARGEN, y + 40), (MARGEN + 72, y + 40)], fill=BORGONA, width=3)
    if pie:
        versalitas(d, (MARGEN, H - SEGURO_ABAJO - 40), pie.upper(), worksans(24), 5, GRIS)
    return im


def mezclar(a, b, t):
    return Image.blend(a, b, t)


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    ruta_guion = sys.argv[1]
    guion = json.load(open(ruta_guion, encoding="utf-8"))
    salida = sys.argv[2]
    logo = guion.get("logo", LOGO_POR_DEFECTO)

    # Las imagenes del guion se buscan desde "raiz". Si no esta, desde la
    # carpeta del propio guion. Asi el guion se puede correr desde donde
    # sea sin que se rompan los caminos.
    raiz = guion.get("raiz") or os.path.dirname(os.path.abspath(ruta_guion))
    for esc in guion["escenas"]:
        if not os.path.isabs(esc["imagen"]):
            esc["imagen"] = os.path.join(raiz, esc["imagen"])
        if not os.path.isfile(esc["imagen"]):
            sys.exit("No encuentro la imagen: " + esc["imagen"])

    carpeta = tempfile.mkdtemp(prefix="reel-")
    n = 0
    anterior = None
    try:
        piezas = []
        piezas.append(("placa", placa_crema(guion["apertura"], guion.get("lugar", ""), logo), 2.6))
        for esc in guion["escenas"]:
            piezas.append(("foto", esc, float(esc.get("segundos", 3.2))))
        piezas.append(("placa", placa_crema(guion.get("cierre", "arhaus.com.ar"), "", logo), 2.4))

        for tipo, dato, segs in piezas:
            cuadros = int(segs * FPS)
            if tipo == "placa":
                gen = (dato.copy() for _ in range(cuadros))
            else:
                gen = (rotular(c, guion["obra"], guion["lugar"], dato.get("rotulo", ""))
                       for c in fotogramas_escena(dato, cuadros))
            for k, cuadro in enumerate(gen):
                if anterior is not None and k < int(DISOLVENCIA * FPS):
                    t = (k + 1) / (DISOLVENCIA * FPS)
                    cuadro = mezclar(anterior, cuadro, suave(t))
                cuadro.save(os.path.join(carpeta, "f%05d.jpg" % n), quality=93)
                n += 1
                ultimo = cuadro
            anterior = ultimo

        # El ffmpeg: la variable FFMPEG manda; si no, el del sistema; si
        # no, uno de ffmpeg-static que haya cerca.
        ffmpeg = os.environ.get("FFMPEG") or shutil.which("ffmpeg")
        if not ffmpeg:
            for donde in (os.getcwd(), AQUI, os.path.dirname(os.path.abspath(ruta_guion))):
                for exe in ("ffmpeg.exe", "ffmpeg"):
                    p = os.path.join(donde, "node_modules", "ffmpeg-static", exe)
                    if os.path.isfile(p):
                        ffmpeg = p
                        break
                if ffmpeg:
                    break
        if not ffmpeg:
            sys.exit("No encuentro ffmpeg. Instalalo o pone ffmpeg-static en node_modules.")

        subprocess.run([
            ffmpeg, "-y", "-framerate", str(FPS),
            "-i", os.path.join(carpeta, "f%05d.jpg"),
            "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "19",
            "-preset", "slow", "-movflags", "+faststart", salida,
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        print("%s  %d cuadros  %.1f s  %.1f MB" % (
            salida, n, n / FPS, os.path.getsize(salida) / 1048576))
    finally:
        shutil.rmtree(carpeta, ignore_errors=True)


if __name__ == "__main__":
    main()
