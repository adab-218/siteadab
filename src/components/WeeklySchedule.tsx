import React, { useState, useEffect } from 'react';
import { Plus, Edit2, Trash2, Save, X, Calendar, Clock, Users } from 'lucide-react';

interface ScheduleEvent {
  id: string;
  day: string;
  time: string;
  activity: string;
  department: string;
  responsible?: string;
}

interface WeeklyScheduleProps {
  onClose?: () => void;
}

const WeeklySchedule: React.FC<WeeklyScheduleProps> = ({ onClose }) => {
  const [events, setEvents] = useState<ScheduleEvent[]>([
    // Dados iniciais baseados na imagem
    { id: '1', day: 'Segunda', time: '19:00', activity: 'Ensaio Miss c/ Mulheres', department: 'Mulheres' },
    { id: '2', day: 'Terça', time: '08:00', activity: 'Círculo de oração geral no TC', department: 'Círculo de Oração' },
    { id: '3', day: 'Terça', time: '19:00', activity: 'Culto da Unidade no TC', department: 'Geral' },
    { id: '4', day: 'Terça', time: '19:00', activity: 'Ensaio Miss c/ adolescentes', department: 'Adolescentes' },
    { id: '5', day: 'Terça', time: '20:00', activity: 'Ensaio Miss c/ Homens', department: 'Homens' },
    { id: '6', day: 'Quarta', time: '07:00', activity: 'Consagração p/ Igreja', department: 'Geral' },
    { id: '7', day: 'Quarta', time: '19:30', activity: 'Culto da Família', department: 'Geral' },
    { id: '8', day: 'Sábado', time: '16:30', activity: 'Ensaio Miss Crianças', department: 'Crianças' },
    { id: '9', day: 'Domingo', time: '08:00', activity: 'Escola Bíblica Dominical', department: 'EBD' },
    { id: '10', day: 'Domingo', time: '18:30', activity: 'Culto de Adoração e Louvor ao Senhor (Dir. Miss c/ Homens)', department: 'Homens' },
  ]);

  const [isAddingEvent, setIsAddingEvent] = useState(false);
  const [editingEvent, setEditingEvent] = useState<string | null>(null);
  const [newEvent, setNewEvent] = useState<Omit<ScheduleEvent, 'id'>>({
    day: 'Segunda',
    time: '',
    activity: '',
    department: '',
    responsible: ''
  });

  const [currentWeek, setCurrentWeek] = useState(() => {
    const today = new Date();
    const monday = new Date(today);
    monday.setDate(today.getDate() - today.getDay() + 1);
    return monday;
  });

  const daysOfWeek = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
  
  const departments = [
    'Geral',
    'Círculo de Oração',
    'Mulheres',
    'Homens',
    'Adolescentes',
    'Crianças',
    'EBD',
    'Mocidade'
  ];

  const formatWeekRange = (startDate: Date) => {
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + 6);
    
    const formatDate = (date: Date) => {
      return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    };
    
    return `DE ${formatDate(startDate)} A ${formatDate(endDate)}`;
  };

  const getEventsForDay = (day: string) => {
    return events
      .filter(event => event.day === day)
      .sort((a, b) => a.time.localeCompare(b.time));
  };

  const handleAddEvent = () => {
    if (newEvent.time && newEvent.activity && newEvent.department) {
      const event: ScheduleEvent = {
        ...newEvent,
        id: Date.now().toString()
      };
      setEvents([...events, event]);
      setNewEvent({
        day: 'Segunda',
        time: '',
        activity: '',
        department: '',
        responsible: ''
      });
      setIsAddingEvent(false);
    }
  };

  const handleEditEvent = (id: string, updatedEvent: Omit<ScheduleEvent, 'id'>) => {
    setEvents(events.map(event => 
      event.id === id ? { ...updatedEvent, id } : event
    ));
    setEditingEvent(null);
  };

  const handleDeleteEvent = (id: string) => {
    setEvents(events.filter(event => event.id !== id));
  };

  const getDepartmentColor = (department: string) => {
    const colors: { [key: string]: string } = {
      'Geral': 'bg-blue-100 text-blue-800',
      'Círculo de Oração': 'bg-purple-100 text-purple-800',
      'Mulheres': 'bg-pink-100 text-pink-800',
      'Homens': 'bg-green-100 text-green-800',
      'Adolescentes': 'bg-yellow-100 text-yellow-800',
      'Crianças': 'bg-orange-100 text-orange-800',
      'EBD': 'bg-indigo-100 text-indigo-800',
      'Mocidade': 'bg-red-100 text-red-800'
    };
    return colors[department] || 'bg-gray-100 text-gray-800';
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
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
            >
              <X size={24} />
            </button>
          </div>
        </div>

        {/* Controls */}
        <div className="p-4 border-b bg-gray-50">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <button
                onClick={() => setIsAddingEvent(true)}
                className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
              >
                <Plus size={16} />
                Adicionar Atividade
              </button>
              
              <div className="flex items-center gap-2">
                <Calendar size={16} className="text-gray-600" />
                <input
                  type="date"
                  value={currentWeek.toISOString().split('T')[0]}
                  onChange={(e) => {
                    const newDate = new Date(e.target.value);
                    const monday = new Date(newDate);
                    monday.setDate(newDate.getDate() - newDate.getDay() + 1);
                    setCurrentWeek(monday);
                  }}
                  className="border border-gray-300 rounded px-3 py-1 text-sm"
                />
              </div>
            </div>

            <div className="text-sm text-gray-600">
              Total de atividades: {events.length}
            </div>
          </div>
        </div>

        {/* Schedule Grid */}
        <div className="overflow-auto max-h-[60vh]">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-1 p-4">
            {daysOfWeek.map((day) => {
              const dayEvents = getEventsForDay(day);
              const isEmpty = dayEvents.length === 0;
              
              return (
                <div key={day} className="border border-gray-200 rounded-lg overflow-hidden">
                  {/* Day Header */}
                  <div className="bg-blue-600 text-white p-3 text-center">
                    <h3 className="font-bold text-sm">{day}</h3>
                    <p className="text-xs text-blue-100">
                      {new Date(currentWeek.getTime() + (daysOfWeek.indexOf(day) * 24 * 60 * 60 * 1000))
                        .toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
                    </p>
                  </div>

                  {/* Events */}
                  <div className="min-h-[200px] bg-white">
                    {isEmpty ? (
                      <div className="p-4 text-center text-gray-500">
                        <p className="text-sm">SEM PROGRAMAÇÃO</p>
                      </div>
                    ) : (
                      <div className="space-y-1 p-2">
                        {dayEvents.map((event) => (
                          <div
                            key={event.id}
                            className="border border-gray-200 rounded p-2 hover:shadow-sm transition-shadow"
                          >
                            {editingEvent === event.id ? (
                              <EditEventForm
                                event={event}
                                departments={departments}
                                onSave={(updatedEvent) => handleEditEvent(event.id, updatedEvent)}
                                onCancel={() => setEditingEvent(null)}
                              />
                            ) : (
                              <div>
                                <div className="flex items-center justify-between mb-1">
                                  <span className="text-sm font-bold text-blue-600">
                                    {event.time}
                                  </span>
                                  <div className="flex gap-1">
                                    <button
                                      onClick={() => setEditingEvent(event.id)}
                                      className="text-gray-400 hover:text-blue-600 transition-colors"
                                    >
                                      <Edit2 size={12} />
                                    </button>
                                    <button
                                      onClick={() => handleDeleteEvent(event.id)}
                                      className="text-gray-400 hover:text-red-600 transition-colors"
                                    >
                                      <Trash2 size={12} />
                                    </button>
                                  </div>
                                </div>
                                <p className="text-xs text-gray-800 mb-2 leading-tight">
                                  {event.activity}
                                </p>
                                <span className={`inline-block px-2 py-1 rounded-full text-xs font-medium ${getDepartmentColor(event.department)}`}>
                                  {event.department}
                                </span>
                                {event.responsible && (
                                  <p className="text-xs text-gray-500 mt-1">
                                    Resp: {event.responsible}
                                  </p>
                                )}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Add Event Modal */}
        {isAddingEvent && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-60">
            <div className="bg-white rounded-lg p-6 w-full max-w-md mx-4">
              <h3 className="text-lg font-bold mb-4">Adicionar Nova Atividade</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Dia da Semana
                  </label>
                  <select
                    value={newEvent.day}
                    onChange={(e) => setNewEvent({ ...newEvent, day: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    {daysOfWeek.map(day => (
                      <option key={day} value={day}>{day}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Horário
                  </label>
                  <input
                    type="time"
                    value={newEvent.time}
                    onChange={(e) => setNewEvent({ ...newEvent, time: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Atividade
                  </label>
                  <input
                    type="text"
                    value={newEvent.activity}
                    onChange={(e) => setNewEvent({ ...newEvent, activity: e.target.value })}
                    placeholder="Ex: Ensaio do coral, Reunião de oração..."
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Departamento
                  </label>
                  <select
                    value={newEvent.department}
                    onChange={(e) => setNewEvent({ ...newEvent, department: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    <option value="">Selecione um departamento</option>
                    {departments.map(dept => (
                      <option key={dept} value={dept}>{dept}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Responsável (opcional)
                  </label>
                  <input
                    type="text"
                    value={newEvent.responsible}
                    onChange={(e) => setNewEvent({ ...newEvent, responsible: e.target.value })}
                    placeholder="Nome do responsável"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  />
                </div>
              </div>

              <div className="flex gap-3 mt-6">
                <button
                  onClick={handleAddEvent}
                  className="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
                >
                  <Save size={16} />
                  Salvar
                </button>
                <button
                  onClick={() => setIsAddingEvent(false)}
                  className="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

interface EditEventFormProps {
  event: ScheduleEvent;
  departments: string[];
  onSave: (event: Omit<ScheduleEvent, 'id'>) => void;
  onCancel: () => void;
}

const EditEventForm: React.FC<EditEventFormProps> = ({ event, departments, onSave, onCancel }) => {
  const [editedEvent, setEditedEvent] = useState<Omit<ScheduleEvent, 'id'>>({
    day: event.day,
    time: event.time,
    activity: event.activity,
    department: event.department,
    responsible: event.responsible || ''
  });

  const handleSave = () => {
    if (editedEvent.time && editedEvent.activity && editedEvent.department) {
      onSave(editedEvent);
    }
  };

  return (
    <div className="space-y-2">
      <input
        type="time"
        value={editedEvent.time}
        onChange={(e) => setEditedEvent({ ...editedEvent, time: e.target.value })}
        className="w-full text-xs border border-gray-300 rounded px-2 py-1"
      />
      
      <textarea
        value={editedEvent.activity}
        onChange={(e) => setEditedEvent({ ...editedEvent, activity: e.target.value })}
        className="w-full text-xs border border-gray-300 rounded px-2 py-1 resize-none"
        rows={2}
      />
      
      <select
        value={editedEvent.department}
        onChange={(e) => setEditedEvent({ ...editedEvent, department: e.target.value })}
        className="w-full text-xs border border-gray-300 rounded px-2 py-1"
      >
        {departments.map(dept => (
          <option key={dept} value={dept}>{dept}</option>
        ))}
      </select>

      <input
        type="text"
        value={editedEvent.responsible}
        onChange={(e) => setEditedEvent({ ...editedEvent, responsible: e.target.value })}
        placeholder="Responsável"
        className="w-full text-xs border border-gray-300 rounded px-2 py-1"
      />
      
      <div className="flex gap-1">
        <button
          onClick={handleSave}
          className="flex-1 bg-green-600 text-white py-1 rounded text-xs hover:bg-green-700 transition-colors flex items-center justify-center gap-1"
        >
          <Save size={10} />
          Salvar
        </button>
        <button
          onClick={onCancel}
          className="flex-1 bg-gray-300 text-gray-700 py-1 rounded text-xs hover:bg-gray-400 transition-colors"
        >
          Cancelar
        </button>
      </div>
    </div>
  );
};

export default WeeklySchedule;