import { mkdir, readdir, copyFile } from 'node:fs/promises';
import { resolve, join, extname } from 'node:path';

// Publish only deliberate static asset types, never PHP, dotfiles or symlinks.
const allowed = new Set(['.css', '.js', '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.txt']);
const source = resolve('public');
const destination = resolve('.vercel-static');
await mkdir(destination, { recursive: true });
async function copyAssets(directory, relative = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        if (entry.name.startsWith('.') || entry.name === 'storage' || entry.isSymbolicLink()) continue;
        const path = join(relative, entry.name);
        if (entry.isDirectory()) {
            await copyAssets(join(directory, entry.name), path);
        } else if (entry.isFile() && allowed.has(extname(entry.name).toLowerCase())) {
            await mkdir(resolve(destination, relative), { recursive: true });
            await copyFile(join(directory, entry.name), resolve(destination, path));
        }
    }
}
await copyAssets(source);
console.log('Vercel static assets prepared (PHP, secrets and storage excluded).');
