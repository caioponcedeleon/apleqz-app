#!/usr/bin/env node
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

/**
 * @returns {Promise<{ url?: string, interactions?: unknown[], timeout_ms?: number }>}
 */
async function readPayload() {
    const input = readFileSync(0, 'utf8');

    return JSON.parse(input);
}

/**
 * @param {import('playwright').Page} page
 * @param {Record<string, unknown>} step
 */
async function runInteraction(page, step) {
    const type = step.type;
    const optional = step.optional === true;

    if (typeof type !== 'string' || type === '') {
        throw new Error('Interaction step requires a type.');
    }

    try {
        switch (type) {
            case 'click': {
                const selector = step.selector;

                if (typeof selector !== 'string' || selector === '') {
                    throw new Error('click interaction requires a selector.');
                }

                const maxClicks = step.repeat_until_gone === true
                    ? (typeof step.max_clicks === 'number' ? step.max_clicks : 5)
                    : 1;

                for (let attempt = 0; attempt < maxClicks; attempt += 1) {
                    const locator = page.locator(selector).first();
                    const visible = await locator.isVisible().catch(() => false);

                    if (! visible) {
                        if (attempt === 0 && optional) {
                            return;
                        }

                        break;
                    }

                    await locator.click({
                        timeout: typeof step.timeout_ms === 'number' ? step.timeout_ms : 5_000,
                    });

                    if (typeof step.wait_after_ms === 'number' && step.wait_after_ms > 0) {
                        await page.waitForTimeout(step.wait_after_ms);
                    }

                    if (step.repeat_until_gone !== true) {
                        break;
                    }
                }

                break;
            }
            case 'wait_for': {
                const selector = step.selector;

                if (typeof selector !== 'string' || selector === '') {
                    throw new Error('wait_for interaction requires a selector.');
                }

                await page.locator(selector).first().waitFor({
                    state: 'visible',
                    timeout: typeof step.timeout_ms === 'number' ? step.timeout_ms : 10_000,
                });

                break;
            }
            case 'scroll': {
                if (typeof step.selector === 'string' && step.selector !== '') {
                    await page.locator(step.selector).first().scrollIntoViewIfNeeded();
                } else {
                    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
                }

                if (typeof step.wait_after_ms === 'number' && step.wait_after_ms > 0) {
                    await page.waitForTimeout(step.wait_after_ms);
                }

                break;
            }
            case 'sleep': {
                const delay = typeof step.ms === 'number'
                    ? step.ms
                    : (typeof step.wait_after_ms === 'number' ? step.wait_after_ms : 1_000);

                await page.waitForTimeout(delay);

                break;
            }
            default:
                throw new Error(`Unknown interaction type: ${type}`);
        }
    } catch (error) {
        if (optional) {
            return;
        }

        throw error;
    }
}

async function main() {
    let payload;

    try {
        payload = await readPayload();
    } catch {
        process.stdout.write(JSON.stringify({ error: 'Invalid JSON input.' }));
        process.exit(1);

        return;
    }

    const url = payload.url;
    const interactions = Array.isArray(payload.interactions) ? payload.interactions : [];
    const timeoutMs = typeof payload.timeout_ms === 'number' ? payload.timeout_ms : 60_000;

    if (typeof url !== 'string' || url === '') {
        process.stdout.write(JSON.stringify({ error: 'url is required.' }));
        process.exit(1);

        return;
    }

    let browser;

    try {
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();
        page.setDefaultTimeout(timeoutMs);

        await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: timeoutMs,
        });

        for (const step of interactions) {
            if (! step || typeof step !== 'object') {
                continue;
            }

            await runInteraction(page, step);
        }

        const html = await page.content();
        process.stdout.write(JSON.stringify({ html }));
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        process.stdout.write(JSON.stringify({ error: message }));
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

main();
