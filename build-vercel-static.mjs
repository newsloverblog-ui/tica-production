import { cp, mkdir, rm } from 'node:fs/promises';
import { basename, extname, relative, sep } from 'node:path';

const source = new URL('./public/', import.meta.url);
const destination = new URL('./.vercel-static/', import.meta.url);

await rm(destination, { recursive: true, force: true });
await mkdir(destination, { recursive: true });

await cp(source, destination, {
  recursive: true,
  filter(path) {
    const local = relative(source.pathname, path);

    if (local === '') {
      return true;
    }

    const segments = local.split(sep);
    const filename = basename(path);

    return segments[0] !== 'installer'
      && extname(filename).toLowerCase() !== '.php'
      && filename !== '.htaccess'
      && !filename.startsWith('qa-')
      && !filename.endsWith('-preview.html');
  },
});
