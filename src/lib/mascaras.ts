/**
 * Máscaras de input — formato pt-BR.
 * Aplicadas no onChange; o valor enviado à API já vai mascarado
 * (backend aceita telefone VARCHAR(30) livre).
 */

/** Telefone BR: (91) 98765-4321 (celular) ou (91) 9876-4321 (fixo). */
export function mascaraTelefone(valor: string): string {
  const d = valor.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 2) return d.length ? `(${d}` : '';
  const ddd = `(${d.slice(0, 2)}) `;
  if (d.length <= 6) return ddd + d.slice(2);
  // celular 9 dígitos → hífen após o 5º; fixo 8 → após o 4º
  const corte = d.length === 11 ? 7 : 6;
  if (d.length <= corte) return ddd + d.slice(2);
  return `${ddd}${d.slice(2, corte)}-${d.slice(corte)}`;
}
