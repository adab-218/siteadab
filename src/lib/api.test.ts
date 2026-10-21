// @vitest-environment node
// (este arquivo testa o client HTTP — não precisa de DOM; jsdom + undici
// quebra em alguns runtimes de CI)
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { apiFetch, auth, ApiError, setCsrfToken } from './api';

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });

const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

beforeEach(() => {
  fetchMock.mockReset();
  setCsrfToken(null);
});

describe('apiFetch', () => {
  it('retorna body.data no envelope de sucesso', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ data: { ok: true } }));
    await expect(apiFetch<{ ok: boolean }>('/health')).resolves.toEqual({ ok: true });
  });

  it('lança ApiError com code e status quando a API retorna erro', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ error: { code: 'SEM_PERMISSAO', message: 'Sem acesso.' } }, 403)
    );
    const err = (await apiFetch('/x').catch((e) => e)) as ApiError;
    expect(err).toBeInstanceOf(ApiError);
    expect(err.code).toBe('SEM_PERMISSAO');
    expect(err.status).toBe(403);
  });

  it('lança ApiError genérico quando resposta não é JSON (mod_security/500)', async () => {
    fetchMock.mockResolvedValue(new Response('<html>406</html>', { status: 406 }));
    const err = (await apiFetch('/x').catch((e) => e)) as ApiError;
    expect(err).toBeInstanceOf(ApiError);
    expect(err.status).toBe(406);
  });

  it('não envia X-CSRF-Token em GET', async () => {
    setCsrfToken('tok');
    fetchMock.mockResolvedValue(jsonResponse({ data: [] }));
    await apiFetch('/public/programacao');
    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get('X-CSRF-Token')).toBeNull();
  });

  it('envia X-CSRF-Token em mutações', async () => {
    setCsrfToken('tok');
    fetchMock.mockResolvedValue(jsonResponse({ data: { ok: true } }));
    await apiFetch('/x', { method: 'POST', body: '{}' });
    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get('X-CSRF-Token')).toBe('tok');
  });

  it('define Content-Type json quando há body', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ data: {} }));
    await apiFetch('/x', { method: 'POST', body: '{}' });
    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get('Content-Type')).toBe('application/json');
  });
});

describe('auth', () => {
  it('login guarda o token CSRF retornado', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({
        data: { usuario: { id: 1, perfil: 'admin' }, csrf: 'abc' },
      })
    );
    await auth.login('a@b.com', 'senha123456');
    fetchMock.mockResolvedValue(jsonResponse({ data: { ok: true } }));
    await apiFetch('/x', { method: 'POST', body: '{}' });
    const headers = fetchMock.mock.calls[1][1].headers as Headers;
    expect(headers.get('X-CSRF-Token')).toBe('abc');
  });

  it('logout limpa o token CSRF', async () => {
    setCsrfToken('abc');
    fetchMock.mockResolvedValue(jsonResponse({ data: { ok: true } }));
    await auth.logout();
    fetchMock.mockResolvedValue(jsonResponse({ data: {} }));
    await apiFetch('/x', { method: 'POST', body: '{}' });
    const headers = fetchMock.mock.calls[1][1].headers as Headers;
    expect(headers.get('X-CSRF-Token')).toBeNull();
  });
});
