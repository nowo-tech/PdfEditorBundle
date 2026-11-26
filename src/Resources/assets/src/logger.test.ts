/**
 * Logger behaviour: the script-loaded line, both quiet switches, and the debug gate (REQ-ASSETS-005 rule 6).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createBundleLogger } from './logger';

type ConsoleHost = { NOWO_CONSOLE?: unknown; __nowoScriptBannerNames?: unknown };

describe('createBundleLogger', () => {
  let log: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    delete (globalThis as ConsoleHost).__nowoScriptBannerNames;
    log = vi.spyOn(console, 'log').mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    delete (globalThis as ConsoleHost).NOWO_CONSOLE;
    delete document.documentElement.dataset.nowoConsole;
  });

  it('prints the script-loaded line with and without a build id', () => {
    createBundleLogger('pdf-editor', { buildTime: '2026-11-26 10:00 UTC' }).scriptLoaded();
    createBundleLogger('pdf-editor-b', { buildTime: '' }).scriptLoaded();
    createBundleLogger('pdf-editor-c').scriptLoaded();

    expect(log).toHaveBeenCalledTimes(3);
    expect(String(log.mock.calls[0]?.[0])).toContain('[pdf-editor] script loaded, build: ');
    expect(String(log.mock.calls[1]?.[0])).not.toContain('build: ');
    expect(String(log.mock.calls[2]?.[0])).not.toContain('build: ');
  });

  it('prints nothing with the html data attribute set to quiet', () => {
    document.documentElement.dataset.nowoConsole = 'quiet';
    createBundleLogger('pdf-editor').scriptLoaded();

    expect(log).not.toHaveBeenCalled();
  });

  it('prints nothing with globalThis.NOWO_CONSOLE set to quiet', () => {
    (globalThis as ConsoleHost).NOWO_CONSOLE = 'quiet';
    createBundleLogger('pdf-editor').scriptLoaded();

    expect(log).not.toHaveBeenCalled();
  });

  it('stays silent until debug is enabled, then formats object arguments', () => {
    const spies = {
      debug: vi.spyOn(console, 'debug').mockImplementation(() => undefined),
      info: vi.spyOn(console, 'info').mockImplementation(() => undefined),
      warn: vi.spyOn(console, 'warn').mockImplementation(() => undefined),
      error: vi.spyOn(console, 'error').mockImplementation(() => undefined),
    };
    const logger = createBundleLogger('pdf-editor');

    logger.debug('a');
    logger.info('a');
    logger.warn('a');
    logger.error('a');
    for (const spy of Object.values(spies)) expect(spy).not.toHaveBeenCalled();

    logger.setDebug(true);
    logger.debug({ a: 1 });
    logger.debug();
    logger.info('x', new Error('e'));
    logger.info();
    logger.warn('w');
    logger.warn();
    logger.error('e');
    logger.error();

    for (const spy of Object.values(spies)) expect(spy).toHaveBeenCalledTimes(2);
    expect(spies.debug.mock.calls[0]?.[2]).toBe('{"a":1}');
  });
});
