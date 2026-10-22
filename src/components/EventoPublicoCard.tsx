import React from 'react';
import { CalendarDays, MapPin } from 'lucide-react';
import { EventoPublico } from '../lib/api';

const formatarData = (iso: string): string => {
  // 'YYYY-MM-DD' → dd/mm/aaaa (sem timezone: trata como data local)
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
};

interface Props {
  evento: EventoPublico;
}

const EventoPublicoCard: React.FC<Props> = ({ evento }) => {
  const periodo = evento.data_fim && evento.data_fim !== evento.data_inicio
    ? `${formatarData(evento.data_inicio)} a ${formatarData(evento.data_fim)}`
    : formatarData(evento.data_inicio);

  return (
    <div className="bg-white rounded-lg shadow-sm border overflow-hidden flex flex-col">
      {evento.banner_url && (
        <img src={evento.banner_url} alt="" className="w-full h-40 object-cover" loading="lazy" />
      )}
      <div className="p-5 flex flex-col gap-2 flex-1">
        <span className="self-start inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700">
          {evento.tipo}
        </span>
        <h3 className="text-lg font-bold text-gray-800">{evento.nome}</h3>
        <p className="text-sm text-gray-600 flex items-center gap-1.5">
          <CalendarDays size={15} className="text-red-600 shrink-0" />
          {periodo}
        </p>
        {evento.local && (
          <p className="text-sm text-gray-600 flex items-center gap-1.5">
            <MapPin size={15} className="text-red-600 shrink-0" />
            {evento.local}
          </p>
        )}
        {evento.descricao && (
          <p className="text-sm text-gray-600 mt-1">{evento.descricao}</p>
        )}
      </div>
    </div>
  );
};

export default EventoPublicoCard;
