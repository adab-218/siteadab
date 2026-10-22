import React, { useRef, useState } from 'react';
import { ImagePlus, Loader2, Trash2 } from 'lucide-react';
import { ApiError } from '../../lib/api';

interface ImagemUploadProps {
  /** URL atual (ou null). */
  url: string | null;
  /** Rótulo do campo (ex.: "Foto", "Banner"). */
  rotulo: string;
  /** Proporção do preview: quadrado para fotos, retangular para banners. */
  formato?: 'quadrado' | 'banner';
  enviar: (arquivo: File) => Promise<void>;
  remover: () => Promise<void>;
}

/**
 * Upload de imagem com preview — valida tipo e 2 MB antes de enviar
 * (o servidor revalida por conteúdo via finfo).
 */
const ImagemUpload: React.FC<ImagemUploadProps> = ({ url, rotulo, formato = 'quadrado', enviar, remover }) => {
  const inputRef = useRef<HTMLInputElement>(null);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState('');

  const aoEscolher = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const arquivo = e.target.files?.[0];
    e.target.value = '';
    if (!arquivo) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(arquivo.type)) {
      setErro('Use JPG, PNG ou WebP.');
      return;
    }
    if (arquivo.size > 2 * 1024 * 1024) {
      setErro('Imagem excede 2 MB.');
      return;
    }
    setErro('');
    setEnviando(true);
    try {
      await enviar(arquivo);
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Falha no upload.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{rotulo}</label>
      <div className="flex items-center gap-3">
        <div
          className={`bg-gray-100 rounded-lg overflow-hidden border flex items-center justify-center text-gray-400 ${
            formato === 'quadrado' ? 'w-20 h-20' : 'w-40 h-20'
          }`}
        >
          {url ? (
            <img src={url} alt={rotulo} className="w-full h-full object-cover" />
          ) : (
            <ImagePlus size={22} />
          )}
        </div>
        <div className="flex flex-col gap-2">
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={aoEscolher}
            className="sr-only"
            aria-label={`Enviar ${rotulo}`}
          />
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            disabled={enviando}
            className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50 disabled:opacity-50 flex items-center gap-2"
          >
            {enviando && <Loader2 size={14} className="animate-spin" />}
            {url ? 'Trocar' : 'Enviar'}
          </button>
          {url && (
            <button
              type="button"
              onClick={async () => { setErro(''); await remover(); }}
              disabled={enviando}
              className="text-sm text-red-600 hover:text-red-800 flex items-center gap-1"
            >
              <Trash2 size={14} />
              Remover
            </button>
          )}
        </div>
      </div>
      <p className="text-xs text-gray-400 mt-1">JPG, PNG ou WebP · até 2 MB</p>
      {erro && <p className="mt-1 text-sm text-red-700" role="alert">{erro}</p>}
    </div>
  );
};

export default ImagemUpload;
