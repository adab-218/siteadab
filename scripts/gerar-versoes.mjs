// Gera src/lib/versoes.ts a partir do git log — roda em predev/prebuild.
// Convenção: cada commit = uma versão v1.0.N (o mais antigo é v1.0.0).
// Sem git disponível, preserva o arquivo existente (ou escreve fallback).
import { execSync } from 'node:child_process';
import { existsSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, 'src/lib/versoes.ts');

const HEADER = `// GERADO por scripts/gerar-versoes.mjs a partir do git log — não editar.
export interface Versao {
  versao: string;
  hash: string;
  data: string;
  tipo: 'feature' | 'correcao' | 'docs' | 'refactor' | 'teste' | 'chore';
  mensagem: string;
}
`;

const TIPOS = {
  feat: 'feature',
  fix: 'correcao',
  docs: 'docs',
  refactor: 'refactor',
  perf: 'correcao',
  style: 'refactor',
  test: 'teste',
  chore: 'chore',
  build: 'chore',
  ci: 'chore',
  revert: 'correcao',
};

function escrever(versoes) {
  const atual = versoes.length ? versoes[0].versao : 'v0.0.0';
  writeFileSync(
    out,
    HEADER +
      `export const VERSAO_ATUAL = ${JSON.stringify(atual)};\n` +
      `export const VERSOES: Versao[] = ${JSON.stringify(versoes, null, 2)};\n`
  );
  console.log(`versoes.ts: ${versoes.length} commits → ${atual}`);
}

let commits;
try {
  const raw = execSync('git log --format=%H%x1f%ad%x1f%s --date=short', {
    cwd: root,
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  commits = raw
    .trim()
    .split('\n')
    .filter(Boolean)
    .map((l) => l.split('\x1f'));
} catch {
  if (!existsSync(out)) {
    escrever([]);
  }
  process.exit(0);
}

const n = commits.length;
const versoes = commits.map(([hash, data, mensagem], i) => {
  const prefixo = mensagem.match(/^(\w+)(?:\(|:)/)?.[1]?.toLowerCase();
  return {
    versao: `v1.0.${n - 1 - i}`,
    hash: hash.slice(0, 7),
    data,
    tipo: TIPOS[prefixo] ?? 'chore',
    mensagem,
  };
});

escrever(versoes);
