// Compila una imagen a un .mind de MindAR sin node-canvas: la imagen ya
// viene decodificada a RGBA, asi que el "canvas" solo tiene que
// devolverla tal cual.
import { CompilerBase } from 'mind-ar/src/image-target/compiler-base.js';
import { buildTrackingImageList } from 'mind-ar/src/image-target/image-list.js';
import { extractTrackingFeatures } from 'mind-ar/src/image-target/tracker/extract-utils.js';
import 'mind-ar/src/image-target/detector/kernels/cpu/index.js';
import * as tf from '@tensorflow/tfjs';
import jpeg from 'jpeg-js';
import { PNG } from 'pngjs';
import fs from 'fs';

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
      const imageList = buildTrackingImageList(ti);
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
