// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { mascaraTelefone } from './mascaras';

describe('mascaraTelefone', () => {
  it('monta o DDD', () => {
    expect(mascaraTelefone('9')).toBe('(9');
    expect(mascaraTelefone('91')).toBe('(91');
    expect(mascaraTelefone('919')).toBe('(91) 9');
  });

  it('formata celular 11 dígitos', () => {
    expect(mascaraTelefone('91987654321')).toBe('(91) 98765-4321');
    expect(mascaraTelefone('91998175864')).toBe('(91) 99817-5864');
  });

  it('formata fixo 10 dígitos', () => {
    expect(mascaraTelefone('9132144321')).toBe('(91) 3214-4321');
  });

  it('ignora caracteres não numéricos', () => {
    expect(mascaraTelefone('(91) 98765-4321')).toBe('(91) 98765-4321');
    expect(mascaraTelefone('91-9-8765-4321')).toBe('(91) 98765-4321');
    expect(mascaraTelefone('abc')).toBe('');
  });

  it('trunca em 11 dígitos', () => {
    expect(mascaraTelefone('919876543219999')).toBe('(91) 98765-4321');
  });

  it('vazio retorna vazio', () => {
    expect(mascaraTelefone('')).toBe('');
  });
});
