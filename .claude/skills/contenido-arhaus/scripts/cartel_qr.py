#!/usr/bin/env python3
"""
Arma el cartel con el QR que se clava en el lote.

Mismo sistema que las placas: crema, Cormorant para el titulo, Work Sans
en versalitas para los rotulos, la rayita borgona. Sale en A4 vertical a
300 dpi, listo para imprimir y plastificar.

El QR hay que generarlo aparte, porque Python aca no tiene libreria. Con
node y el paquete "qrcode":

    node -e "require('qrcode').toFile('qr.png','https://www.arhaus.com.ar/ar.html?o=huepil',{width:1400,margin:1})"

Y despues:

    python cartel_qr.py qr.png "Casa Huepil" "San Martin de los Andes" cartel.png

Un QR impreso de 12 cm se lee bien desde metro y medio, que es la
distancia a la que alguien se para frente a un cartel.
"""
import os
import sys

from PIL import Image, ImageDraw

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, AQUI)
from placas import (  # noqa: E402
    BORGONA, CARBON, CREMA, GRIS, LOGO_POR_DEFECTO,
    cormorant, partir, pegar_logo, versalitas, worksans,
)

# A4 a 300 dpi
W, H = 2480, 3508
MARGEN = 240


def cartel(ruta_qr, obra, lugar, logo=LOGO_POR_DEFECTO):
    im = Image.new("RGB", (W, H), CREMA)
    d = ImageDraw.Draw(im)

    pegar_logo(im, logo, 560, (MARGEN, MARGEN))

    y = MARGEN + 420
    versalitas(d, (MARGEN, y), "REALIDAD AUMENTADA", worksans(52, "Medium"), 14, BORGONA)

    ft = cormorant(190, "Light")
    y += 110
    for linea in partir(d, obra, ft, W - 2 * MARGEN):
        d.text((MARGEN, y), linea, font=ft, fill=CARBON)
        y += 210

    d.text((MARGEN, y + 10), lugar, font=cormorant(96, "Light"), fill=GRIS)

    # El QR, centrado y grande. En A4 impreso da unos 12 cm de lado.
    qr = Image.open(ruta_qr).convert("L").point(lambda v: 0 if v < 128 else 255)
    lado = 1400
    qr = qr.resize((lado, lado), Image.NEAREST).convert("RGB")
    qx, qy = (W - lado) // 2, y + 300
    d.rectangle([(qx - 40, qy - 40), (qx + lado + 40, qy + lado + 40)], fill="#ffffff")
    im.paste(qr, (qx, qy))

    # La instruccion, que es lo que de verdad hace que alguien lo use.
    fi = worksans(64, "Light")
    ty = qy + lado + 150
    for linea in partir(d, "Apuntá la cámara del teléfono al código y vas a ver la casa "
                           "parada sobre el lote, a tamaño real.", fi, W - 2 * MARGEN):
        d.text((MARGEN, ty), linea, font=fi, fill="#5a5a5a")
        ty += 92

    d.line([(MARGEN, H - MARGEN - 70), (MARGEN + 160, H - MARGEN - 70)],
           fill=BORGONA, width=6)
    versalitas(d, (MARGEN, H - MARGEN - 30), "ARHAUS.COM.AR", worksans(46), 10, GRIS)
    return im


def main():
    if len(sys.argv) < 5:
        sys.exit(__doc__)
    ruta_qr, obra, lugar, salida = sys.argv[1:5]
    if not os.path.isfile(ruta_qr):
        sys.exit("No encuentro el QR: " + ruta_qr)
    im = cartel(ruta_qr, obra, lugar)
    im.save(salida, dpi=(300, 300))
    print("%s  %dx%d px  A4 a 300 dpi  %.1f MB" % (
        salida, im.width, im.height, os.path.getsize(salida) / 1048576))


if __name__ == "__main__":
    main()
