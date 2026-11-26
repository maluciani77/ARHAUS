#!/usr/bin/env python3
"""
Arma la tarjeta que se escanea para ver la casa en 3D encima.

La tarjeta cumple dos papeles a la vez: es la pieza impresa que se le da
al cliente y es la MARCA que el telefono reconoce. Eso manda el diseno
mas de lo que parece: el reconocimiento se apoya en los detalles de la
imagen, asi que la cara que se escanea lleva el render a sangre. Una
tarjeta crema con el logo y nada mas se ve preciosa y no se engancha
nunca, porque no tiene de donde agarrarse.

Sale a 85x55 mm, 300 dpi, que es el tamano de tarjeta de visita.

    python tarjeta_ar.py <render> "Casa Huepil" "San Martin de los Andes" tarjeta.png

Despues, el .mind que lee el navegador se compila de ESTA misma imagen
(ver tarjeta.html y scripts/compilar_marca.mjs).
"""
import os
import sys

from PIL import Image, ImageDraw

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, AQUI)
from placas import (  # noqa: E402
    BORGONA, CARBON, CREMA, GRIS, LOGO_POR_DEFECTO,
    cormorant, llenar, partir, pegar_logo, versalitas, worksans,
)

# 85 x 55 mm a 300 dpi
W, H = 1004, 650
FRANJA = 250          # alto de la franja crema de abajo
MARGEN = 56


def tarjeta(ruta_render, obra, lugar, logo=LOGO_POR_DEFECTO):
    im = Image.new("RGB", (W, H), CREMA)

    foto = Image.open(ruta_render).convert("RGB")
    im.paste(llenar(foto, W, H - FRANJA), (0, 0))

    d = ImageDraw.Draw(im)
    y0 = H - FRANJA

    # La rayita borgona: lo unico que lleva el color de la marca.
    d.line([(MARGEN, y0 + 36), (MARGEN + 90, y0 + 36)], fill=BORGONA, width=4)

    # El logo primero, para saber cuanto ancho queda para el nombre.
    ancho_logo = 190
    x_logo = W - MARGEN - ancho_logo
    pegar_logo(im, logo, ancho_logo, (x_logo, y0 + 30), CARBON)

    ft = cormorant(76, "Light")
    y = y0 + 54
    for linea in partir(d, obra, ft, x_logo - MARGEN - 40):
        d.text((MARGEN, y), linea, font=ft, fill=CARBON)
        y += 88

    d.text((MARGEN, max(y + 4, y0 + 152)), lugar, font=worksans(28, "Light"), fill=GRIS)

    # La instruccion. Sin esto la tarjeta no se escanea: nadie adivina
    # que hay algo para mirar.
    fr = worksans(24, "Medium")
    txt = "APUNTÁ LA CÁMARA"
    ancho = sum(d.textlength(c, font=fr) + 11 for c in txt) - 11
    versalitas(d, (W - MARGEN - ancho, y0 + 196), txt, fr, 11, BORGONA)

    return im


def main():
    if len(sys.argv) < 5:
        sys.exit(__doc__)
    render, obra, lugar, salida = sys.argv[1:5]
    if not os.path.isfile(render):
        sys.exit("No encuentro el render: " + render)
    im = tarjeta(render, obra, lugar)
    im.save(salida, dpi=(300, 300))
    print("%s  %dx%d px  85x55 mm a 300 dpi  %.2f MB" % (
        salida, im.width, im.height, os.path.getsize(salida) / 1048576))


if __name__ == "__main__":
    main()
