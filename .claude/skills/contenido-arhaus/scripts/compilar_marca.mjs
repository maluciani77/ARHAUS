// Compila una imagen a un .mind de MindAR sin node-canvas: la imagen ya
// viene decodificada a RGBA, asi que el "canvas" solo tiene que
// devolverla tal cual.
//
// Y ademas le deja al seguimiento el doble de puntos de los que le
// dejaria MindAR. Vale la pena explicar por que, porque es lo que hace
// que la casa no se caiga de la tarjeta en cuanto uno la mira de
// costado.
//
// Un .mind guarda dos "fotogramas de seguimiento" de cada imagen. MindAR
// los arma a 256 y a 128 pixeles de lado corto... y despues el que
// SIEMPRE usa es el segundo, el de 128 (tracker.js: TRACKING_KEYFRAME =
// 1). En la tarjeta de Huepil el de 128 da 26 puntos y el de 256 da 48.
// Con 26, apenas el angulo tapa unos cuantos ya no se puede calcular la
// pose y se pierde la tarjeta.
//
// Asi que se dan vuelta: 128 primero y 256 segundo. El formato es el
// mismo y el archivo pesa igual -son las dos mismas imagenes-, pero el
// seguimiento se queda con los 48. Probado hasta 512, que da 57: no
// paga, porque el costo por cuadro crece con los pixeles del fotograma.
//
// OJO: esto vale mientras TRACKING_KEYFRAME siga siendo 1. Si algun dia
// se actualiza mind-ar, mirar tracker.js antes de recompilar.
//
import { CompilerBase } from 'mind-ar/src/image-target/compiler-base.js';
import { resize } from 'mind-ar/src/image-target/utils/images.js';
import { extractTrackingFeatures } from 'mind-ar/src/image-target/tracker/extract-utils.js';
import 'mind-ar/src/image-target/detector/kernels/cpu/index.js';
import * as tf from '@tensorflow/tfjs';
import jpeg from 'jpeg-js';
import { PNG } from 'pngjs';
import fs from 'fs';

// 128 primero y 256 despues, al reves de MindAR: ver el encabezado.
const LADOS = [128, 256];

function listaDeSeguimiento(img) {
  const menor = Math.min(img.width, img.height);
  return LADOS.map((lado) => Object.assign(
    resize({ image: img, ratio: lado / menor }), { scale: lado / menor }));
}

class Compilador extends CompilerBase {
  createProcessCanvas(img) {
    return {
      getContext: () => ({
        drawImage: () => {},
        getImageData: () => ({ data: img.data }),
      }),
    };
  }
  compileTrack({ progressCallback, targetImages, basePercent }) {
    const porImagen = (100 - basePercent) / targetImages.length;
    let pct = 0;
    const lista = [];
    for (const ti of targetImages) {
      const imageList = listaDeSeguimiento(ti);
      const porPaso = porImagen / imageList.length;
      lista.push(extractTrackingFeatures(imageList, () => {
        pct += porPaso;
        progressCallback(basePercent + pct);
      }));
    }
    return Promise.resolve(lista);
  }
}

function leer(ruta) {
  const b = fs.readFileSync(ruta);
  if (/\.png$/i.test(ruta)) {
    const p = PNG.sync.read(b);
    return { width: p.width, height: p.height, data: p.data };
  }
  const j = jpeg.decode(b, { useTArray: true });
  return { width: j.width, height: j.height, data: j.data };
}

const [, , salida, ...entradas] = process.argv;
if (!salida || !entradas.length) {
  console.error('uso: node compilar.mjs salida.mind imagen1 [imagen2 ...]');
  process.exit(1);
}
await tf.setBackend('cpu');
const imgs = entradas.map(leer);
imgs.forEach((im, i) => console.log(entradas[i], im.width + 'x' + im.height));
const c = new Compilador();
let ultimo = -1;
await c.compileImageTargets(imgs, (p) => {
  const n = Math.floor(p / 10) * 10;
  if (n !== ultimo) { ultimo = n; process.stderr.write(n + '% ');}
});
fs.writeFileSync(salida, Buffer.from(c.exportData()));
console.log('\n' + salida, (fs.statSync(salida).size / 1048576).toFixed(2) + ' MB');
