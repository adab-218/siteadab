import React from 'react';
import { useAuth } from '../../lib/auth-context';

const DashboardPage: React.FC = () => {
  const { usuario } = useAuth();

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">
          Bem-vindo{usuario?.nome ? `, ${usuario.nome.split(' ')[0]}` : ''}
        </h1>
        <p className="text-gray-500">Painel de gestão do Templo Arthur Bernardes.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {[
          { titulo: 'Membros', descricao: 'Cadastro de pessoas e membros — módulo M1 em construção.' },
          { titulo: 'Aniversariantes do mês', descricao: 'Disponível quando o módulo Membros estiver no ar (view já pronta).' },
          { titulo: 'EBD', descricao: 'Classes, chamada e relatórios — após Membros e Cultos.' },
        ].map((c) => (
          <div key={c.titulo} className="bg-white rounded-lg shadow-sm border p-5">
            <h2 className="font-semibold text-gray-800 mb-2">{c.titulo}</h2>
            <p className="text-sm text-gray-500">{c.descricao}</p>
          </div>
        ))}
      </div>
    </div>
  );
};

export default DashboardPage;
