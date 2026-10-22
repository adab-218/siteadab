import React, { useEffect, useRef } from 'react';
import { X } from 'lucide-react';

interface ModalProps {
  titulo: string;
  onClose: () => void;
  children: React.ReactNode;
  largura?: string;
}

/**
 * Modal acessível: role=dialog, aria-modal, Escape fecha,
 * scroll do body travado e foco devolvido ao fechar.
 */
const Modal: React.FC<ModalProps> = ({ titulo, onClose, children, largura = 'max-w-lg' }) => {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const focoAnterior = document.activeElement as HTMLElement | null;
    ref.current?.focus();

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = original;
      focoAnterior?.focus();
    };
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      role="presentation"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div
        ref={ref}
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        tabIndex={-1}
        className={`bg-white rounded-lg shadow-2xl w-full ${largura} max-h-[90vh] overflow-auto outline-none`}
      >
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-bold text-gray-800">{titulo}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-700 transition-colors"
            aria-label={`Fechar ${titulo}`}
          >
            <X size={20} />
          </button>
        </div>
        <div className="p-4">{children}</div>
      </div>
    </div>
  );
};

export default Modal;
