#!/usr/bin/env python3
"""
Arma las placas de Instagram de ARHAUS en la direccion Editorial.

La regla del sistema es una sola: la foto nunca lleva texto encima. El
texto vive en una franja crema abajo, siempre con el mismo alto, los
mismos margenes y la misma tipografia. Asi el feed se lee parejo aunque
las fotos sean de dia, de noche, renders o planos.

Uso:
    python placas.py feed  <imagen> <obra> <lugar> [--rotulo TEXTO] [--salida ARCHIVO]
    python placas.py story <imagen> <obra> <lugar> [...]
    python placas.py texto <titulo> <cuerpo> [--salida ARCHIVO]

Ejemplos:
    python placas.py feed images/proyectos/unifamiliar/neuquen/10.jpg \
        "Casa Huepil" "San Martin de los Andes"

    python placas.py texto "Por que el frente va vidriado" \
        "En la Patagonia es por donde se va el calor. Lo que sostiene esa
         apertura es el resto de la envolvente."

Las fuentes salen de ../fuentes. Necesita Pillow.
"""
import argparse
import os
import sys

from PIL import Image, ImageDraw, ImageFont

AQUI = os.path.dirname(os.path.abspath(__file__))
FUENTES = os.path.join(AQUI, "..", "fuentes")
LOGO_POR_DEFECTO = os.path.join(AQUI, "..", "..", "..", "..", "images", "Logo2.png")

CREMA = "#f5f4f0"
CARBON = "#333333"
BORGONA = "#880723"
GRIS = "#8a8a8a"

MEDIDAS = {
    # formato: (ancho, alto, alto de la franja de texto)
    "feed": (1080, 1350, 250),
    "story": (1080, 1920, 300),
}


def cormorant(tam, peso="Light"):
    f = ImageFont.truetype(os.path.join(FUENTES, "Cormorant-var.ttf"), tam)
    f.set_variation_by_name(peso)
    return f


def worksans(tam, peso="Regular"):
    f = ImageFont.truetype(os.path.join(FUENTES, "WorkSans-var.ttf"), tam)
    f.set_variation_by_name(peso)
    return f


def llenar(im, ancho, alto):
    """Recorta al centro para llenar la caja, sin deformar."""
    escala = max(ancho / im.width, alto / im.height)
    r = im.resize((round(im.width * escala), round(im.height * escala)), Image.LANCZOS)
    x = (r.width - ancho) // 2
    y = (r.height - alto) // 2
    return r.crop((x, y, x + ancho, y + alto))


def versalitas(d, xy, txt, fuente, sep, color):
    """Texto con las letras separadas. En versalitas la separacion es lo
    que lo vuelve legible: sin ella se apelmaza."""
    x, y = xy
    for ch in txt:
        d.text((x, y), ch, font=fuente, fill=color)
        x += d.textlength(ch, font=fuente) + sep
    return x


def pegar_logo(lienzo, ruta, ancho, xy, color=CARBON):
    if not os.path.isfile(ruta):
        return False
    logo = Image.open(ruta).convert("RGBA")
    logo = logo.resize((ancho, round(logo.height * ancho / logo.width)), Image.LANCZOS)
    tinte = Image.new("RGBA", logo.size, color)
    tinte.putalpha(logo.getchannel("A"))
    lienzo.paste(tinte, xy, tinte)
    return True


def partir(d, txt, fuente, ancho_max):
    lineas, actual = [], ""
    for palabra in txt.split():
        prueba = (actual + " " + palabra).strip()
        if d.textlength(prueba, font=fuente) <= ancho_max or not actual:
            actual = prueba
        else:
            lineas.append(actual)
            actual = palabra
    if actual:
        lineas.append(actual)
    return lineas


def placa_foto(formato, imagen, obra, lugar, rotulo, logo):
    W, H, franja = MEDIDAS[formato]
    m = 64 if formato == "feed" else 74

    lienzo = Image.new("RGB", (W, H), CREMA)
    lienzo.paste(llenar(Image.open(imagen).convert("RGB"), W, H - franja), (0, 0))
    d = ImageDraw.Draw(lienzo)

    y = H - franja + (58 if formato == "feed" else 76)
    versalitas(d, (m, y), obra.upper(), worksans(27, "Medium"), 7, CARBON)
    d.text((m, y + 46), lugar, font=cormorant(58, "Light"), fill=CARBON)
    d.line([(m, y + 128), (m + 72, y + 128)], fill=BORGONA, width=3)

    if rotulo:
        versalitas(d, (m, y + 152), rotulo.upper(), worksans(20), 4, GRIS)

    pegar_logo(lienzo, logo, 190, (W - 190 - m, y + 92))
    return lienzo


def placa_texto(titulo, cuerpo, logo):
    """La placa que rompe la seriedad de puras fotos: fondo crema y una
    idea sola. Sirve para abrir un carrusel."""
    W, H, _ = MEDIDAS["feed"]
    m = 96
    lienzo = Image.new("RGB", (W, H), CREMA)
    d = ImageDraw.Draw(lienzo)

    pegar_logo(lienzo, logo, 170, (m, m))

    ft = cormorant(74, "Light")
    fc = worksans(31, "Light")
    lineas_t = partir(d, titulo, ft, W - 2 * m)
    lineas_c = partir(d, cuerpo, fc, W - 2 * m)

    # El bloque va centrado entre el logo y el pie. Si se deja arriba con
    # una altura fija, un texto corto deja medio placa vacia y uno largo
    # se come el pie.
    alto = len(lineas_t) * 88 + 120 + len(lineas_c) * 48
    arriba, abajo = m + 150, H - m - 90
    y = arriba + max(0, (abajo - arriba - alto) // 2)

    for linea in lineas_t:
        d.text((m, y), linea, font=ft, fill=CARBON)
        y += 88

    d.line([(m, y + 34), (m + 72, y + 34)], fill=BORGONA, width=3)

    y += 86
    for linea in lineas_c:
        d.text((m, y), linea, font=fc, fill="#5a5a5a")
        y += 48

    versalitas(d, (m, H - m - 24), "ARHAUS.COM.AR", worksans(20), 4, GRIS)
    return lienzo


def main():
    p = argparse.ArgumentParser(description="Placas de Instagram de ARHAUS")
    sub = p.add_subparsers(dest="formato", required=True)

    for nombre in ("feed", "story"):
        s = sub.add_parser(nombre)
        s.add_argument("imagen")
        s.add_argument("obra")
        s.add_argument("lugar")
        s.add_argument("--rotulo", default="")
        s.add_argument("--logo", default=LOGO_POR_DEFECTO)
        s.add_argument("--salida", default=None)

    s = sub.add_parser("texto")
    s.add_argument("titulo")
    s.add_argument("cuerpo")
    s.add_argument("--logo", default=LOGO_POR_DEFECTO)
    s.add_argument("--salida", default=None)

    a = p.parse_args()

    if a.formato == "texto":
        im = placa_texto(a.titulo, a.cuerpo, a.logo)
        salida = a.salida or "placa-texto.jpg"
    else:
        if not os.path.isfile(a.imagen):
            sys.exit("No encuentro la imagen: " + a.imagen)
        im = placa_foto(a.formato, a.imagen, a.obra, a.lugar, a.rotulo, a.logo)
        base = os.path.splitext(os.path.basename(a.imagen))[0]
        salida = a.salida or f"placa-{a.formato}-{base}.jpg"

    im.save(salida, "JPEG", quality=94, optimize=True, progressive=True)
    print(f"{salida}  {im.width}x{im.height}  {os.path.getsize(salida)/1024:.0f} KB")


if __name__ == "__main__":
    main()
