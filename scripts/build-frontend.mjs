import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve, relative, sep } from 'node:path';
import tailwindcss from '@tailwindcss/postcss';
import { build } from 'esbuild';
import postcss from 'postcss';

const root = process.cwd();
const buildDir = resolve(root, 'public/build');
const assetsDir = resolve(buildDir, 'assets');
const cssInput = 'resources/css/tica-front.css';
const reactCssInput = 'resources/css/react-app.css';
const jsInput = 'resources/js/tica-app.tsx';

rmSync(buildDir, { recursive: true, force: true });
mkdirSync(assetsDir, { recursive: true });

async function compileCss(input, outputName) {
    const source = readFileSync(resolve(root, input), 'utf8');
    const result = await postcss([tailwindcss()]).process(source, {
        from: resolve(root, input),
        map: false,
    });
    const bytes = Buffer.from(result.css);
    const file = `assets/${outputName}-${createHash('sha256').update(bytes).digest('hex').slice(0, 10)}.css`;
    writeFileSync(resolve(buildDir, file), bytes);
    return file;
}

const cssFile = await compileCss(cssInput, 'tica-front');
const reactCssFile = await compileCss(reactCssInput, 'tica-app');

const bundle = await build({
    absWorkingDir: root,
    entryPoints: [jsInput],
    outdir: assetsDir,
    entryNames: '[name]-[hash]',
    assetNames: '[name]-[hash]',
    bundle: true,
    minify: true,
    metafile: true,
    jsx: 'automatic',
    target: ['es2020'],
    plugins: [{
        name: 'compiled-css-stub',
        setup(esbuild) {
            esbuild.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'compiled-css' }));
            esbuild.onLoad({ filter: /.*/, namespace: 'compiled-css' }, () => ({ contents: '', loader: 'js' }));
        },
    }],
});

const outputPath = path => relative(buildDir, resolve(root, path)).split(sep).join('/');
const jsOutput = Object.entries(bundle.metafile.outputs).find(([, details]) => details.entryPoint === jsInput && details.exports);

if (!jsOutput) throw new Error('The customer application JavaScript bundle was not created.');

const manifest = {
    [cssInput]: { file: cssFile, src: cssInput, isEntry: true },
    [jsInput]: {
        file: outputPath(jsOutput[0]),
        src: jsInput,
        isEntry: true,
        css: [reactCssFile],
    },
};

writeFileSync(resolve(buildDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
console.log(`Built ${Object.keys(manifest).length} Laravel frontend entries.`);
