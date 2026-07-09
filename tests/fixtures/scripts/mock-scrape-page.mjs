#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

try {
    readFileSync(0, 'utf8');
    const html = readFileSync(join(__dirname, '..', 'job-sources', 'basic-listing.html'), 'utf8');
    process.stdout.write(JSON.stringify({ html }));
} catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stdout.write(JSON.stringify({ error: message }));
    process.exit(1);
}
