import React, { useEffect, useMemo, useState } from 'react';
import { X, Calendar, Loader2 } from 'lucide-react';
import { publicApi, ItemProgramacao } from '../lib/api';

interface WeeklyScheduleProps {
  onClose?: () => void;
}

const daysOfWeek = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];

const mondayOfWeek = (date: Date) => {
  const monday = new Date(date);
  const day = date.getDay();
  monday.setDate(date.getDate() + (day === 0 ? -6 : 1 - day));
  return monday;
};

const toLocalISODate = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const addDays = (d: Date, n: number) => {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
};

const WeeklySchedule: React.FC<WeeklyScheduleProps> = ({ onClose }) => {
  const [itens, setItens] = useState<ItemProgramacao[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');
  const [currentWeek, setCurrentWeek] = useState(() => mondayOfWeek(new Date()));

  useEffect(() => {
    publicApi.programacao()
      .then(setItens)
      .catch(() => setErro('Não foi possível carregar a programação no momento.'))
      .finally(() => setCarregando(false));
  }, []);

  // A11y do diálogo: Escape fecha + trava o scroll do body
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose?.();
    };
    document.addEventListener('keydown', onKey);
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = original;
    };
  }, [onClose]);

  const formatWeekRange = (startDate: Date) => {
    const endDate = addDays(startDate, 6);
    const formatDate = (date: Date) =>
      date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    return `DE ${formatDate(startDate)} A ${formatDate(endDate)}`;
  };

  // Itens da semana exibida, agrupados por data (a API devolve datas futuras)
  const itensPorData = useMemo(() => {
    const mapa = new Map<string, ItemProgramacao[]>();
    const inicio = toLocalISODate(currentWeek);
    const fim = toLocalISODate(addDays(currentWeek, 6));
    for (const item of itens) {
      if (item.data >= inicio && item.data <= fim) {
        const lista = mapa.get(item.data) ?? [];
        lista.push(item);
        mapa.set(item.data, lista);
      }
    }
    return mapa;
  }, [itens, currentWeek]);

  const getCategoriaColor = (categoria: string) => {
    const colors: { [key: string]: string } = {
      'Círculo de Oração': 'bg-purple-100 text-purple-800',
      'Mulheres': 'bg-pink-100 text-pink-800',
      'Homens': 'bg-green-100 text-green-800',
      'Adolescentes': 'bg-yellow-100 text-yellow-800',
      'Crianças': 'bg-orange-100 text-orange-800',
      'EBD': 'bg-indigo-100 text-indigo-800',
      'Mocidade': 'bg-red-100 text-red-800',
    };
    return colors[categoria] || 'bg-blue-100 text-blue-800';
  };

  return (
    <div
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Programação semanal"
    >
      <div className="bg-white rounded-lg shadow-2xl w-full max-w-7xl max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
          <div className="flex items-center justify-between">
            <div className="text-center flex-1">
              <div className="flex items-center justify-center mb-2">
                <img
                  src="https://i.imgur.com/zlIM5kM.jpeg"
                  alt="Logo AD"
                  className="h-12 w-auto mr-3"
                />
                <div>
                  <h1 className="text-2xl font-bold">ASSEMBLEIA DE DEUS</h1>
                  <h2 className="text-lg">TEMPLO ARTHUR BERNARDES</h2>
                </div>
              </div>
              <h3 className="text-xl font-semibold">Programação Semanal</h3>
              <p className="text-blue-100">{formatWeekRange(currentWeek)}</p>
            </div>
            <button
              onClick={onClose}
              className="text-white hover:text-gray-200 transition-colors ml-4"
              aria-label="Fechar programação semanal"
            >
              <X size={24} />
            </button>
          </div>
        </div>

        {/* Controls */}
        <div className="p-4 border-b bg-gray-50">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <Calendar size={16} className="text-gray-600" aria-hidden="true" />
              <label htmlFor="semana" className="sr-only">Selecionar semana</label>
              <input
                id="semana"
                type="date"
                value={toLocalISODate(currentWeek)}
                onChange={(e) => {
                  const [y, m, d] = e.target.value.split('-').map(Number);
                  if (!y || !m || !d) return;
                  setCurrentWeek(mondayOfWeek(new Date(y, m - 1, d)));
                }}
                className="border border-gray-300 rounded px-3 py-1 text-sm"
              />
            </div>
            <div className="text-sm text-gray-600">
              Atividades na semana: {[...itensPorData.values()].reduce((n, l) => n + l.length, 0)}
            </div>
          </div>
        </div>

        {/* Schedule Grid */}
        <div className="overflow-auto max-h-[60vh]">
          {carregando ? (
            <div className="flex items-center justify-center py-16 text-gray-500" role="status">
              <Loader2 size={24} className="animate-spin mr-2" aria-hidden="true" />
              Carregando programação…
            </div>
          ) : erro ? (
            <p className="text-center py-16 text-red-600" role="alert">{erro}</p>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-1 p-4">
              {daysOfWeek.map((day, idx) => {
                const data = addDays(currentWeek, idx);
                const iso = toLocalISODate(data);
                const dayItems = (itensPorData.get(iso) ?? [])
                  .slice()
                  .sort((a, b) => (a.horario_inicio ?? '').localeCompare(b.horario_inicio ?? ''));

                return (
                  <div key={day} className="border border-gray-200 rounded-lg overflow-hidden">
                    {/* Day Header */}
                    <div className="bg-blue-600 text-white p-3 text-center">
                      <h3 className="font-bold text-sm">{day}</h3>
                      <p className="text-xs text-blue-100">
                        {data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
                      </p>
                    </div>

                    {/* Events */}
                    <div className="min-h-[200px] bg-white">
                      {dayItems.length === 0 ? (
                        <div className="p-4 text-center text-gray-500">
                          <p className="text-sm">SEM PROGRAMAÇÃO</p>
                        </div>
                      ) : (
                        <div className="space-y-1 p-2">
                          {dayItems.map((item, i) => (
                            <div
                              key={`${item.tipo}-${item.titulo}-${item.data}-${i}`}
                              className="border border-gray-200 rounded p-2"
                            >
                              {item.horario_inicio && (
                                <p className="text-sm font-bold text-blue-600 mb-1">
                                  {item.horario_inicio.slice(0, 5)}
                                </p>
                              )}
                              <p className="text-xs text-gray-800 mb-2 leading-tight">
                                {item.titulo}
                              </p>
                              <span className={`inline-block px-2 py-1 rounded-full text-xs font-medium ${getCategoriaColor(item.categoria)}`}>
                                {item.categoria}
                              </span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default WeeklySchedule;
